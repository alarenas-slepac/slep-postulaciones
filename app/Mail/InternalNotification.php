<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class InternalNotification extends BaseMailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $toName    Nombre del destinatario (para el saludo)
     * @param string $title     Título/asunto del correo
     * @param string $message   Cuerpo principal (puede incluir saltos de línea \n)
     * @param string|null $ctaText Texto del botón (opcional)
     * @param string|null $ctaUrl  URL del botón (opcional)
     */
    public function __construct(
        string $toName,
        string $title,
        string $message,
        ?string $ctaText = null,
        ?string $ctaUrl = null
    ) {
        $lines = preg_split("/\r\n|\n|\r/", trim($message)) ?: [];

        parent::__construct(
            subjectText: $title,
            greeting: 'Hola ' . $toName,
            lines: $lines,
            actionText: $ctaText,
            actionUrl: $ctaUrl,
            outroLines: ['Este es un aviso automático, por favor no responder a este correo.'],
            salutation: null
        );
    }
}
