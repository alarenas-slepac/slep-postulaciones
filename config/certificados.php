<?php

return [
    'roles_emision_general' => [
        'admin',
        'coordinador_gdp',
        'funcionario_slep',
    ],

    'roles_emision_propia' => [
        'funcionario',
    ],

    'institucion' => [
        'nombre' => 'Servicio Local de Educación Pública Andalién Costa',
        'rut' => '61981100-3',
        'domicilio' => 'Manuel Montt 798 Coronel',
        'ciudad_emision' => 'Coronel',
        'incorporacion_desde' => '2025-01-01',
        'email' => 'certificadosantiguedad@slepandaliencosta.gob.cl',
    ],

    'firmante' => [
        'nombre' => 'Makarena Paredes Aguilera',
        'cargo' => 'Subdirectora de Gestión y Desarrollo de las Personas',
    ],

    'recursos' => [
        'logo' => resource_path('branding/certificados/logo-slep-gob.png'),
        'timbre' => resource_path('branding/certificados/timbre-gdp.png'),
        'firma' => resource_path('branding/certificados/firma-subdirectora-gdp.png'),
        'fuente_regular' => resource_path('fonts/certificados/century-gothic-regular.ttf'),
        'fuente_bold' => resource_path('fonts/certificados/century-gothic-bold.ttf'),
    ],

    'importacion' => [
        'chunk_size' => 750,
        'max_errores_guardados' => 200,
    ],
];
