<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class SolicitudReemplazoRechazadaUatp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudReemplazo $solicitud) {}

    public function build()
    {
        $s = $this->solicitud->load([
            'establecimiento',
            'funcionarioTitular',
            'postulante.user',
            'areaDesempeno',
            'jornadas',
        ]);

        $pdf = Pdf::loadView('pdf.solicitud-reemplazo', ['s' => $s]);

        return $this->subject("Solicitud de reemplazo {$s->numero_solicitud} (Rechazada por UATP)")
            ->view('emails.solicitud-reemplazo-rechazada-uatp', ['s' => $s])
            ->attachData($pdf->output(), "Solicitud-{$s->numero_solicitud}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
