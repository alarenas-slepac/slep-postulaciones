<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vitrina publica
    |--------------------------------------------------------------------------
    |
    | La administracion puede quedar operativa antes del lanzamiento publico.
    | Manten esta bandera en false durante la carga y validacion editorial.
    |
    */
    'publica_habilitada' => env('ADMISION_PUBLICA_HABILITADA', false),
    'mostrar_proximamente' => env('ADMISION_MOSTRAR_PROXIMAMENTE', true),
    'anio' => (int) env('ADMISION_ANIO', 2027),
    'titulo' => env('ADMISION_TITULO', 'Admision Escolar'),
    'descripcion' => env(
        'ADMISION_DESCRIPCION',
        'Conoce los establecimientos del SLEP Andalien Costa y encuentra una comunidad educativa para cada trayectoria.'
    ),
    'por_pagina' => (int) env('ADMISION_POR_PAGINA', 12),

    /*
    |--------------------------------------------------------------------------
    | Archivos e imagenes
    |--------------------------------------------------------------------------
    |
    | El limite de 100 MB corresponde al archivo original. Antes de almacenarlo,
    | el sistema reduce sus dimensiones, elimina metadatos y lo convierte a WebP.
    |
    */
    'media_disk' => env('ADMISION_MEDIA_DISK', 'public'),
    'media_directory' => env('ADMISION_MEDIA_DIRECTORY', 'admision-establecimientos'),
    'max_imagen_mb' => max(100, (int) env('ADMISION_MAX_IMAGEN_MB', 100)),
    'max_carga_total_mb' => max(200, (int) env('ADMISION_MAX_CARGA_TOTAL_MB', 200)),
    'max_imagenes_por_carga' => (int) env('ADMISION_MAX_IMAGENES_POR_CARGA', 10),
    'max_imagenes_por_establecimiento' => (int) env('ADMISION_MAX_IMAGENES_POR_ESTABLECIMIENTO', 20),
    'min_imagenes_publicacion' => (int) env('ADMISION_MIN_IMAGENES_PUBLICACION', 1),
    'max_megapixeles' => (int) env('ADMISION_MAX_MEGAPIXELES', 80),

    'optimizacion' => [
        'logo' => [
            'max_width' => (int) env('ADMISION_LOGO_MAX_WIDTH', 1600),
            'max_height' => (int) env('ADMISION_LOGO_MAX_HEIGHT', 1600),
            'quality' => (int) env('ADMISION_LOGO_WEBP_QUALITY', 88),
        ],
        'director' => [
            'max_width' => (int) env('ADMISION_DIRECTOR_MAX_WIDTH', 1600),
            'max_height' => (int) env('ADMISION_DIRECTOR_MAX_HEIGHT', 1600),
            'quality' => (int) env('ADMISION_DIRECTOR_WEBP_QUALITY', 84),
        ],
        'galeria' => [
            'max_width' => (int) env('ADMISION_GALERIA_MAX_WIDTH', 2400),
            'max_height' => (int) env('ADMISION_GALERIA_MAX_HEIGHT', 1800),
            'quality' => (int) env('ADMISION_GALERIA_WEBP_QUALITY', 82),
        ],
    ],

    /*
    | Limites conservadores para Imagick. La memoria se complementa con cache en
    | disco, permitiendo procesar fotografias grandes sin cargar todo en PHP.
    */
    'imagick' => [
        'memory_mb' => (int) env('ADMISION_IMAGICK_MEMORY_MB', 96),
        'map_mb' => (int) env('ADMISION_IMAGICK_MAP_MB', 256),
        'disk_mb' => (int) env('ADMISION_IMAGICK_DISK_MB', 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enlaces y contacto
    |--------------------------------------------------------------------------
    */
    'sae_url' => env('ADMISION_SAE_URL', 'https://www.sistemadeadmisionescolar.cl/'),
    'contacto_email' => env('ADMISION_CONTACTO_EMAIL', config('brand.support_email')),
    'contacto_telefono' => env('ADMISION_CONTACTO_TELEFONO', config('brand.support_phone')),
];
