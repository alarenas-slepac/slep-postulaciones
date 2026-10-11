<?php

return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'url' => env('WHATSAPP_SERVICE_URL', 'http://127.0.0.1:3210'),
    'token' => env('WHATSAPP_SERVICE_TOKEN', ''),
    'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 3),
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 15),
    'queue_connection' => env('WHATSAPP_QUEUE_CONNECTION', 'database'),
    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),
];
