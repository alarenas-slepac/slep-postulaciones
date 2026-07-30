<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class SolicitudReemplazoCreada extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudReemplazo $solicitud) {}

    public function build()
    {
        $s = $this->solicitud->load([
            'establecimiento',
            'funcionarioTitular',
            'areaDesempeno',          // ✅ NUEVO
            'postulante.user',
            'postulante.areaDesempeno',
            'jornadas',
        ]);



        $pdf = Pdf::loadView('pdf.solicitud-reemplazo', ['s' => $s]);

        return $this->subject("Solicitud de reemplazo {$s->numero_solicitud} (Pendiente UATP)")
            ->view('emails.solicitud-reemplazo-creada', ['s' => $s])
            ->attachData($pdf->output(), "Solicitud-{$s->numero_solicitud}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
