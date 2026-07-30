<?php

return [
    // Si no defines estas variables en .env, tomamos valores por defecto seguros.
    'name'                 => env('ORG_NAME', config('app.name', 'SLEP AC Postulaciones')),
    'website'              => env('ORG_WEBSITE', config('app.url', 'http://localhost')),
    'support_email'        => env('ORG_SUPPORT_EMAIL', config('mail.from.address')),
    'remuneraciones_email' => env('ORG_REMUNERACIONES_EMAIL', 'remuneraciones@slepandaliencosta.gob.cl'),
    'address'              => env('ORG_ADDRESS', ''), // opcional: “Av. Ejemplo 123, Comuna, Región”
];
