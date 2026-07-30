<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContratoTrabajoFirmadoEnviado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SolicitudReemplazo $solicitud,
        public ?string $recipientLabel = null,
    ) {}

    public function build()
    {
        $s = $this->solicitud->loadMissing([
            'establecimiento',
            'funcionarioTitular',
            'areaDesempeno',
            'postulante.user',
        ]);

        $subject = "Contrato firmado reemplazo {$s->numero_solicitud}";

        return $this->subject($subject)
            ->view('emails.contrato-trabajo-firmado-enviado', [
                's' => $s,
                'recipientLabel' => $this->recipientLabel,
            ])
            ->attachFromStorageDisk('local', $s->contrato_trabajo_firmado_pdf_path, 'CONTRATO_TRABAJO_FIRMADO.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
