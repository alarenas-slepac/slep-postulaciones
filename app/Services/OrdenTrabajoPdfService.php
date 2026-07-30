<?php

namespace App\Services;

use App\Models\SolicitudReemplazo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrdenTrabajoPdfService
{
    /**
     * Genera el PDF de Orden de Trabajo y lo guarda en storage/app (disco local).
     * Retorna el path guardado (ej: private/solicitudes-reemplazo/{id}/ORDEN_TRABAJO_XXXXX-AAAA.pdf).
     */
    public function generateAndStore(SolicitudReemplazo $s): string
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

        $otNumero = $this->makeOtNumber($s);
        $safeNumero = preg_replace('/[^0-9A-Za-z\- ]/', '', $otNumero);
        $safeNumero = Str::of($safeNumero)->replace(' ', '_')->__toString();

        $pdf = Pdf::loadView('pdf.orden-trabajo', [
            's' => $s,
            'otNumero' => $otNumero,
        ])->setPaper('letter', 'portrait');

        $dir = "private/solicitudes-reemplazo/{$s->id}";
        $filename = "ORDEN_TRABAJO_{$safeNumero}.pdf";
        $path = "{$dir}/{$filename}";

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /**
     * Formato solicitado:
     * - Si fecha_inicio y fecha_termino son el mismo año: {XXXXX}-{AAAA}
     * - Si abarcan años distintos: {XXXXX}-{AAAA} a {AAAA}-{XXXXX}
     */
    public function makeOtNumber(SolicitudReemplazo $s): string
    {
        $num = $s->numero_solicitud ?? $s->correlativo ?? $s->id;
        $correl = str_pad((string) (int) $num, 5, '0', STR_PAD_LEFT);

        $yIni = $s->fecha_inicio?->format('Y') ?? (string) ($s->anio ?? now()->year);
        $yFin = $s->fecha_termino?->format('Y') ?? $yIni;

        if ($yIni === $yFin) {
            return "{$correl}-{$yIni}";
        }

        return "{$correl}-{$yIni} a {$yFin}-{$correl}";
    }
}