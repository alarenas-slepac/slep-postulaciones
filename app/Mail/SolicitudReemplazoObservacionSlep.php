<?php

namespace App\Mail;

use App\Models\SolicitudReemplazo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudReemplazoObservacionSlep extends Mailable
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
            'areaDesempeno',
            'observacionSlepUser',
        ]);

        return $this->subject("Solicitud de reemplazo {$s->numero_solicitud} - Observación SLEP")
            ->view('emails.solicitud-reemplazo-observacion-slep', ['s' => $s]);
    }
}
