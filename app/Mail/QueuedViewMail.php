<?php

namespace App\Mail;

use App\Services\Notifications\NotificationEventCatalog;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mime\Email;

/** Adaptador de vistas/callbacks existentes; conserva su clave del catálogo. */
class QueuedViewMail extends Mailable
{
    public function __construct(private mixed $sourceView, private array $sourceData, private Email $prepared)
    {
        $key = app(NotificationEventCatalog::class)->resolveMail($sourceData, $prepared->getSubject() ?? '');
        if ($key) { $this->with('sga_event_key', $key); }
        foreach (['sga_occurrence_id', 'sga_dispatch_log_id'] as $field) {
            if (isset($sourceData[$field])) { $this->with($field, $sourceData[$field]); }
        }
    }

    public function build(): static
    {
        return $this->view($this->sourceView, $this->sourceData)->withSymfonyMessage(function (Email $message) {
            $message->setHeaders(clone $this->prepared->getHeaders());
            if ($this->prepared->getHtmlBody() !== null) { $message->html($this->prepared->getHtmlBody()); }
            if ($this->prepared->getTextBody() !== null) { $message->text($this->prepared->getTextBody()); }
            foreach ($this->prepared->getAttachments() as $attachment) { $message->addPart(clone $attachment); }
        });
    }
}
