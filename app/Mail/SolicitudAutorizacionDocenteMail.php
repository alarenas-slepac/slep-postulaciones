<?php

namespace App\Mail;

use App\Models\SolicitudReemplazoAutorizacionDocente;
use App\Models\UserDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SolicitudAutorizacionDocenteMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<int, UserDocument> $documentos */
    public function __construct(
        public SolicitudReemplazoAutorizacionDocente $autorizacion,
        public Collection $documentos
    ) {
    }

    public function build()
    {
        $this->autorizacion->loadMissing([
            'solicitud.establecimiento',
            'solicitud.areaDesempeno',
            'solicitud.postulante.user',
            'solicitadoPor',
        ]);

        $numeroSolicitud = $this->autorizacion->solicitud?->numero_solicitud ?: $this->autorizacion->solicitud_reemplazo_id;
        $mail = $this->subject("Solicitud de autorización docente – Solicitud {$numeroSolicitud}")
            ->view('emails.solicitud-autorizacion-docente');

        foreach ($this->documentos as $documento) {
            $disk = (string) ($documento->disk ?? 'public');
            $path = (string) $documento->path;

            if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $extension = pathinfo($documento->original_name ?: $path, PATHINFO_EXTENSION);
            $base = Str::slug((string) ($documento->type?->label ?: 'documento')) ?: 'documento';
            $nombre = $base . ($extension !== '' ? '.' . Str::lower($extension) : '');

            $mail->attachFromStorageDisk($disk, $path, $nombre, [
                'mime' => $documento->mime ?: (Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream'),
            ]);
        }

        return $mail;
    }
}
