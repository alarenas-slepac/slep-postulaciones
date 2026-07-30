<?php

namespace App\Mail;

use App\Models\Tramite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TramiteAnuladoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tramite $tramite)
    {
        $this->tramite = $tramite->loadMissing('user', 'anuladoPor');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Anulación de trámite #' . $this->tramite->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tramites.anulado_html',
            text: 'emails.tramites.anulado_text',
            with: [
                'tramite' => $this->tramite,
            ],
        );
    }
}
