<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudReemplazoValidadaPlani extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudReemplazo $solicitud)
    {
    }

    public function build()
    {
        $s = $this->solicitud->load([
            'establecimiento',
            'funcionarioTitular',
            'postulante.user',
            'areaDesempeno',
            'jornadas',
            'uatpDecisionUser',
            'planiDecisionUser',
        ]);

        $pdf = Pdf::loadView('pdf.solicitud-reemplazo', ['s' => $s]);

        return $this->subject("Solicitud de reemplazo {$s->numero_solicitud} (Validada por Planificación)")
            ->view('emails.solicitud-reemplazo-validada-plani', ['s' => $s])
            ->attachData($pdf->output(), "Solicitud-{$s->numero_solicitud}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
