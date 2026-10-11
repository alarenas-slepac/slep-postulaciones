<?php

return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'url' => env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3210'),
    'token' => env('WHATSAPP_SERVICE_TOKEN', ''),
    'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    'media_timeout' => (int) env('WHATSAPP_MEDIA_TIMEOUT', 45),
    'queue_connection' => env('WHATSAPP_QUEUE_CONNECTION', 'database'),
    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),
    'reemplazos' => [
        'enabled' => (bool) env('WHATSAPP_REEMPLAZOS_ENABLED', false),
    ],
    'reemplazantes' => [
        'enabled' => (bool) env('WHATSAPP_REEMPLAZANTES_ENABLED', false),
    ],
];
