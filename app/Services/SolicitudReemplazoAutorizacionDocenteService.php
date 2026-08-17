<?php

namespace App\Services;

use App\Mail\SolicitudAutorizacionDocenteMail;
use App\Models\DocumentType;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoAutorizacionDocente;
use App\Models\UserDocument;
use App\Support\NotificationAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SolicitudReemplazoAutorizacionDocenteService
{
    /** @return array<int, string> */
    public function slugsRequeridos(SolicitudReemplazo $solicitud): array
    {
        $slugs = [
            'antecedentes_especiales',
            'titulo',
            'inhabilidades_menores',
        ];

        if ($this->esAreaReligion($solicitud)) {
            $slugs[] = 'idoneidad_religion';
        }

        return $slugs;
    }

    /** @return array<int, string> */
    public function slugsAdjuntables(SolicitudReemplazo $solicitud): array
    {
        $slugs = [
            'antecedentes_especiales',
            'titulo',
            'titulo_mencion',
            'inhabilidades_menores',
        ];

        if ($this->esAreaReligion($solicitud)) {
            $slugs[] = 'idoneidad_religion';
        }

        return $slugs;
    }

    public function esSolicitudDocenteConPropuesta(SolicitudReemplazo $solicitud): bool
    {
        $solicitud->loadMissing(['funcionarioTitular', 'postulante.user', 'areaDesempeno']);
        $estatuto = Str::upper(trim((string) $solicitud->funcionarioTitular?->estatuto));
        $esDocente = in_array($estatuto, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true)
            || Str::contains($estatuto, 'DOC');

        return $esDocente
            && (bool) $solicitud->propone_reemplazo
            && $solicitud->postulante?->user !== null;
    }

    public function cumpleRegistroParaAprobacionUatp(SolicitudReemplazo $solicitud): bool
    {
        if (! $this->esSolicitudDocenteConPropuesta($solicitud)) {
            return true;
        }

        $solicitud->loadMissing('autorizacionDocente');
        $autorizacion = $solicitud->autorizacionDocente;

        if (! $autorizacion || blank($autorizacion->solicitado_at)) {
            return true;
        }

        return filled(trim((string) $autorizacion->numero_autorizacion));
    }

    public function esAreaReligion(SolicitudReemplazo $solicitud): bool
    {
        $solicitud->loadMissing('areaDesempeno');
        $area = Str::lower(Str::ascii(trim((string) $solicitud->areaDesempeno?->nombre)));

        return in_array($area, ['religion catolica', 'religion evangelica'], true);
    }

    /**
     * @return Collection<int, UserDocument>
     *
     * @throws ValidationException
     */
    public function documentosRequeridos(SolicitudReemplazo $solicitud): Collection
    {
        $solicitud->loadMissing('postulante.user');
        $usuario = $solicitud->postulante?->user;

        if (! $usuario) {
            throw ValidationException::withMessages([
                'postulante' => 'La solicitud no tiene un postulante propuesto con usuario asociado.',
            ]);
        }

        $slugsRequeridos = $this->slugsRequeridos($solicitud);
        $slugs = $this->slugsAdjuntables($solicitud);
        $tipos = DocumentType::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        $documentos = UserDocument::query()
            ->with('type')
            ->where('user_id', $usuario->id)
            ->whereHas('type', fn ($query) => $query->whereIn('slug', $slugs))
            ->orderByDesc('id')
            ->get()
            ->unique(fn (UserDocument $documento) => (string) $documento->type?->slug)
            ->keyBy(fn (UserDocument $documento) => (string) $documento->type?->slug);

        $faltantes = [];
        $ordenados = collect();

        foreach ($slugs as $slug) {
            $tipo = $tipos->get($slug);
            $documento = $documentos->get($slug);
            $etiqueta = $tipo?->label ?: Str::headline(str_replace('_', ' ', $slug));
            $disk = (string) ($documento?->disk ?? 'public');
            $path = (string) ($documento?->path ?? '');

            if (! $tipo || ! $documento || $path === '' || ! Storage::disk($disk)->exists($path)) {
                if (in_array($slug, $slugsRequeridos, true)) {
                    $faltantes[] = $etiqueta;
                }
                continue;
            }

            $ordenados->push($documento);
        }

        if ($faltantes !== []) {
            throw ValidationException::withMessages([
                'documentos' => 'No se puede enviar la solicitud de autorización. Faltan documentos: ' . implode(', ', $faltantes) . '.',
            ]);
        }

        return $ordenados->values();
    }

    public function documentoTitulo(SolicitudReemplazo $solicitud): ?UserDocument
    {
        $solicitud->loadMissing('postulante.user');
        $userId = $solicitud->postulante?->user?->id;

        if (! $userId) {
            return null;
        }

        $documento = UserDocument::query()
            ->with('type')
            ->where('user_id', $userId)
            ->whereHas('type', fn ($query) => $query->where('slug', 'titulo'))
            ->whereNotNull('path')
            ->where('path', '<>', '')
            ->latest('id')
            ->first();

        if (! $documento) {
            return null;
        }

        $disk = (string) ($documento->disk ?? 'public');

        return Storage::disk($disk)->exists((string) $documento->path) ? $documento : null;
    }

    /** @param Collection<int, UserDocument> $documentos */
    public function enviarCorreo(
        SolicitudReemplazoAutorizacionDocente $autorizacion,
        Collection $documentos,
        string $correoDestino
    ): void {
        $autorizacion->loadMissing([
            'solicitud.establecimiento',
            'solicitud.areaDesempeno',
            'solicitud.postulante.user',
            'solicitadoPor',
        ]);

        $numeroSolicitud = $autorizacion->solicitud?->numero_solicitud ?: $autorizacion->solicitud_reemplazo_id;
        $asunto = "Solicitud de autorización docente – Solicitud {$numeroSolicitud}";

        NotificationAudit::sendMail(
            $correoDestino,
            new SolicitudAutorizacionDocenteMail($autorizacion, $documentos),
            [
                'event_key' => 'solicitud_reemplazo.autorizacion_docente.solicitada',
                'description' => 'Envío de antecedentes para solicitar autorización docente',
                'subject' => $asunto,
                'related' => $autorizacion,
                'context' => [
                    'solicitud_reemplazo_id' => $autorizacion->solicitud_reemplazo_id,
                    'numero_solicitud' => $numeroSolicitud,
                    'documentos' => $documentos->map(fn (UserDocument $documento) => $documento->type?->slug)->filter()->values()->all(),
                ],
            ]
        );
    }
}
