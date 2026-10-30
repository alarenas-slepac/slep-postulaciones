<?php

return [
    'roles' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'supervisor_plani'],
    'estamentos' => ['asistente' => 'Asistentes de la educación', 'docente' => 'Docentes'],
    'documentos' => [
        'embarazo' => 'Certificado de embarazo',
        'orden_trabajo' => 'Orden de trabajo',
        'ley_dominga' => 'Certificado Ley Dominga',
        'lactancia' => 'Solicitud de beneficio de lactancia',
        'nacimiento' => 'Certificado de nacimiento',
        'postnatal_parental' => 'Permiso postnatal parental',
        'rex_postnatal' => 'REX de permiso postnatal parental',
    ],
    'modalidades' => ['completo' => 'Completo', 'compartido' => 'Compartido'],
    'regla_fuero_dominga' => '30_dias',
    'roles_ampliacion_dominga' => ['admin', 'coordinador_gdp'],
    'causales_ampliacion_dominga' => ['parto_prematuro' => 'Fallecimiento por parto prematuro', 'durante_parto' => 'Fallecimiento durante el parto'],
    'max_casos_importacion' => 500,
    'max_hijos_importacion' => 2000,
    'max_documento_kb' => 10240,
];
