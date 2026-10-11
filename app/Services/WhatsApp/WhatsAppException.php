<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly bool $retryable = false)
    {
        // No adjuntar la excepción HTTP original: puede contener autorización o contenido.
        parent::__construct('WhatsApp: '.$errorCode);
    }
}
