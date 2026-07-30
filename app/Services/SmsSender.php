<?php

namespace App\Services;

use Vonage\Client;
use Vonage\SMS\Message\SMS;
use Illuminate\Support\Str;

class SmsSender
{
    public function __construct(private Client $client) {}

    public function send(string $to, string $text): void
    {
        $to = trim($to);
        if ($to === '') return;

        $to = $this->toE164Chile($to);
        $from = config('services.vonage.from', 'SLEP');

        // corta a ~140 chars para SMS
        $text = Str::limit($text, 140, '…');

        $message = new SMS($to, $from, $text);
        $this->client->sms()->send($message);
    }

    private function toE164Chile(string $raw): string
    {
        // Limpia y normaliza a +56XXXXXXXXX
        $s = preg_replace('/\D+/', '', $raw) ?: '';
        if ($s === '') return '';

        if (str_starts_with($s, '56')) {
            return '+' . $s;
        }

        if (str_starts_with($s, '0')) {
            $s = substr($s, 1);
        }

        return '+56' . $s;
    }
}
