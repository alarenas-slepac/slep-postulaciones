<?php

namespace App\Mail;

use App\Models\BolsaTrabajoOferta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BolsaTrabajoDifusionRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BolsaTrabajoOferta $oferta, public string $accion = 'creada')
    {
        $this->oferta->loadMissing(['establecimiento', 'areaDesempeno', 'creador']);
    }

    public function build()
    {
        $accionLabel = $this->accion === 'actualizada' ? 'actualización' : 'difusión';

        return $this->subject('Solicitud de ' . $accionLabel . ' de oferta laboral — Bolsa de Trabajo #' . $this->oferta->id)
            ->view('emails.bolsa_trabajo_difusion_request', [
                'oferta' => $this->oferta,
                'accion' => $this->accion,
                'portalUrl' => 'https://postulaciones.slepandaliencosta.cl',
            ]);
    }
}
