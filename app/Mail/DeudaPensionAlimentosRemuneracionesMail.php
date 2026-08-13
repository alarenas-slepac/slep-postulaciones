<?php

namespace App\Mail;

use App\Models\SolicitudReemplazoDeudaPension;
use App\Models\UserDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class DeudaPensionAlimentosRemuneracionesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SolicitudReemplazoDeudaPension $deuda,
        public UserDocument $declaracion
    ) {
    }

    public function build()
    {
        $this->deuda->loadMissing(['solicitud.establecimiento', 'postulante.user', 'activadoPor']);
        $numero = $this->deuda->solicitud?->numero_solicitud ?: $this->deuda->solicitud_reemplazo_id;

        $mail = $this->subject("Antecedentes de deuda de pensión de alimentos – Solicitud {$numero}")
            ->view('emails.deuda-pension-alimentos-remuneraciones');

        $adjuntos = [
            ['local', $this->deuda->certificado_deuda_path, 'certificado-deuda-pension.pdf', $this->deuda->certificado_deuda_mime],
            ['local', $this->deuda->resolucion_path, 'resolucion-dictamen-deuda-pension.pdf', $this->deuda->resolucion_mime],
            ['public', $this->declaracion->path, 'declaracion-jurada-cargo-publico.pdf', $this->declaracion->mime],
        ];

        foreach ($adjuntos as [$disk, $path, $nombre, $mime]) {
            if ($path && Storage::disk($disk)->exists($path)) {
                $mail->attachFromStorageDisk($disk, $path, $nombre, [
                    'mime' => $mime ?: 'application/pdf',
                ]);
            }
        }

        return $mail;
    }
}
