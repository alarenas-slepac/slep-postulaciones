<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrdenTrabajoCorreoInstitucionalSoporteTi extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudReemplazo $solicitud)
    {
        $this->solicitud->loadMissing([
            'establecimiento',
            'areaDesempeno',
            'postulante.user',
            'funcionarioTitular',
            'jornadas',
        ]);
    }

    public function build()
    {
        $s = $this->solicitud;
        $subjectNum = $s->numero_solicitud ?? $s->correlativo ?? $s->id;
        $subject = "Solicitud de creación de correo institucional — OT {$subjectNum}";

        $mail = $this->subject($subject)
            ->view('emails.orden_trabajo_soporte_ti_correo_institucional', ['s' => $s]);

        if (!empty($s->orden_trabajo_pdf_path)) {
            $mail->attachFromStorageDisk('local', $s->orden_trabajo_pdf_path, 'ORDEN_TRABAJO.pdf', [
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
