<?php

namespace App\Mail;

use App\Models\Tramite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TramiteBieniosSolicitudRecibidaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tramite $tramite)
    {
        $this->tramite = $tramite->loadMissing('user');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Solicitud de Reconocimiento de Bienios recibida',
        );
    }

    public function content(): Content
    {
        $fechaRecepcion = optional($this->tramite->enviado_at)->format('d-m-Y H:i') ?: '—';

        return new Content(
            view: 'emails.tramites.bienios_solicitud_recibida_html',
            text: 'emails.tramites.bienios_solicitud_recibida_text',
            with: [
                'tramite' => $this->tramite,
                'fechaRecepcion' => $fechaRecepcion,
                'platformName' => config('brand.platform_name', 'Plataforma SLEP Andalién Costa'),
                'periodName' => config('brand.period_name', 'SLEP Andalién Costa 2026'),
                'slepLogoUrl' => asset(config('brand.logo_slep', 'branding/logo-andaliencosta.png')),
                'sgaLogoUrl' => asset(config('brand.logo_email', 'branding/04_lockup_horizontal.png')),
                'plazoMaximoDias' => 30,
            ],
        );
    }
}
