<?php

namespace App\Mail;

use App\Models\Tramite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TramiteManualApplicantNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Tramite $tramite, public string $messageBody)
    {
        $this->tramite = $tramite->loadMissing('user');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notificación sobre tu trámite #' . $this->tramite->id . ' (' . $this->tramite->tipo_label . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tramites.manual_applicant_notification_html',
            text: 'emails.tramites.manual_applicant_notification_text',
            with: [
                'tramite' => $this->tramite,
                'messageBody' => $this->messageBody,
                'ctaUrl' => route('tramites.show', $this->tramite),
                'appName' => config('brand.platform_name', config('app.name')),
            ],
        );
    }
}
