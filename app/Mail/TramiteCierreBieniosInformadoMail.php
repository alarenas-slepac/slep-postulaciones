<?php

namespace App\Mail;

use App\Models\Tramite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TramiteCierreBieniosInformadoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tramite $tramite)
    {
        $this->tramite = $tramite->loadMissing('user');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Trámite de Reconocimiento de Bienios finalizado',
        );
    }

    public function content(): Content
    {
        $fechaReconocimiento = optional($this->tramite->enviado_at)->format('d-m-Y') ?: '—';

        return new Content(
            view: 'emails.tramites.cierre_bienios_informado_html',
            text: 'emails.tramites.cierre_bienios_informado_text',
            with: [
                'tramite' => $this->tramite,
                'fechaReconocimiento' => $fechaReconocimiento,
                'platformName' => config('brand.platform_name', 'Plataforma SLEP Andalién Costa'),
                'slepLogoUrl' => asset(config('brand.logo_slep', 'branding/logo-andaliencosta.png')),
                'sgaLogoUrl' => asset(config('brand.logo_email', 'branding/04_lockup_horizontal.png')),
            ],
        );
    }
}
