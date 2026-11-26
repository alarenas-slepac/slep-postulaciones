<?php

namespace App\Services;

use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoProrroga;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrdenTrabajoPdfService
{
    public function __construct(private readonly OrdenTrabajoFirmaService $firmas) {}

    /**
     * Genera el PDF de Orden de Trabajo y lo guarda en storage/app (disco local).
     * Retorna el path guardado (ej: private/solicitudes-reemplazo/{id}/ORDEN_TRABAJO_XXXXX-AAAA.pdf).
     */
    public function generateAndStore(SolicitudReemplazo $s, ?int $modificacionTerminoId = null): string
    {
        // Asegura traer los últimos valores (postulant_profile_id, fecha_inicio_trabajo, etc.)
        // por si el caller hizo update() por query y no refrescó la instancia.
        if ($s->exists && !$s->isDirty()) {
            $s->refresh();
        }

        // Fallback: si la solicitud no tiene postulante asignado (caso: no propone reemplazo),
        // pero ya existe postulante asociado al contrato, lo usamos para la OT.
        if (empty($s->postulant_profile_id) && !empty($s->contrato_trabajo_postulant_profile_id)) {
            $s->postulant_profile_id = (int) $s->contrato_trabajo_postulant_profile_id;
            $s->unsetRelation('postulante');
        }

        $s->loadMissing([
            'establecimiento',
            'areaDesempeno',
            'postulante.user',
            'funcionarioTitular',
            'jornadas',
            'ordenTrabajoCreadaPor',
        ]);

        $yaEmitida = $s->orden_trabajo_pdf_path || in_array($s->estado, ['aceptada', 'cerrado', 'cerrada'], true);
        $otNumero = $this->makeOtNumber($s);
        $safeNumero = preg_replace('/[^0-9A-Za-z\- ]/', '', $otNumero);
        $safeNumero = Str::of($safeNumero)->replace(' ', '_')->__toString();

        $s->orden_trabajo_firmante = $yaEmitida
            ? ($s->orden_trabajo_firmante ?? $this->firmas->titularInstitucional())
            : $this->firmas->vigente();
        $pdf = Pdf::loadView('pdf.orden-trabajo', [
            's' => $s,
            'otNumero' => $otNumero,
            'firmaOt' => $this->firmas->paraPdf($s->orden_trabajo_firmante),
        ])->setPaper('letter', 'portrait');

        $dir = "private/solicitudes-reemplazo/{$s->id}";
        $suffix = $modificacionTerminoId ? "_MODIFICACION_{$modificacionTerminoId}" : '';
        $filename = "ORDEN_TRABAJO_{$safeNumero}{$suffix}.pdf";
        $path = "{$dir}/{$filename}";

        if (! Storage::disk('local')->put($path, $pdf->output())) {
            throw new \RuntimeException('No se pudo guardar la Orden de Trabajo.');
        }

        return $path;
    }

    /**
     * Formato solicitado:
     * - Si fecha_inicio y fecha_termino son el mismo año: {XXXXX}-{AAAA}
     * - Si abarcan años distintos: {XXXXX}-{AAAA} a {AAAA}-{XXXXX}
     */
    public function makeOtNumber(SolicitudReemplazo $s): string
    {
        $num = $s->correlativo ?? $s->numero_solicitud ?? $s->id;
        $correl = str_pad((string) (int) $num, 5, '0', STR_PAD_LEFT);

        $yIni = $s->fecha_inicio?->format('Y') ?? (string) ($s->anio ?? now()->year);
        $yFin = $s->fecha_termino?->format('Y') ?? $yIni;

        if ($yIni === $yFin) {
            return "{$correl}-{$yIni}";
        }

        return "{$correl}-{$yIni} a {$yFin}-{$correl}";
    }

    public function generarProrroga(
        SolicitudReemplazoProrroga $prorroga,
        int $numero,
        array $reemplazo,
        array $vigencia,
        string $motivo,
        bool $regenerar = false,
    ): string {
        $emisor = $prorroga->ordenTrabajoEmitidaPor;
        $inicialesEmisor = mb_strtolower(implode('', array_map(
            fn ($parte) => mb_substr(trim((string) $parte), 0, 1, 'UTF-8'),
            [$emisor?->nombres, $emisor?->apellido_paterno, $emisor?->apellido_materno],
        )), 'UTF-8');
        $yaEmitida = $regenerar || $prorroga->orden_trabajo_pdf_path || $prorroga->getRawOriginal('orden_trabajo_emitida_at');
        $prorroga->orden_trabajo_firmante ??= $yaEmitida ? $this->firmas->titularInstitucional() : $this->firmas->vigente();
        $firmaOt = $this->firmas->paraPdf($prorroga->orden_trabajo_firmante);
        $contenido = Pdf::loadView('pdf.orden-trabajo-prorroga', compact('prorroga', 'numero', 'reemplazo', 'vigencia', 'motivo', 'inicialesEmisor', 'firmaOt'))
            ->setPaper('letter', 'portrait')->output();
        // Mantiene disponible el PDF anterior hasta que se confirme el reemplazo.
        $version = $regenerar ? '_'.Str::uuid() : '';
        $ruta = "reemplazos/prorrogas/{$prorroga->anio_diciembre}/orden_trabajo_{$numero}{$version}.pdf";
        if (! Storage::disk('local')->put($ruta, $contenido)) {
            throw new \RuntimeException('No se pudo guardar la Orden de Trabajo de prórroga.');
        }

        return $ruta;
    }
}
