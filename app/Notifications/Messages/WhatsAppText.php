<?php

namespace App\Notifications\Messages;

final readonly class WhatsAppText
{
    public function __construct(public string $text, public string $idempotencyKey, public string $eventKey) {}
}
