<?php

return [
    'dimensiones_masivas_habilitadas' => ['compin'],

    'estados' => [
        'administrativo' => [
            'ingresada' => 'Ingresada',
            'recibida' => 'Recibida',
            'en_tramitacion' => 'En tramitación',
            'autorizada' => 'Autorizada',
            'reducida' => 'Reducida',
            'rechazada' => 'Rechazada',
            'pendiente_antecedentes' => 'Pendiente de antecedentes',
            'cerrada' => 'Cerrada',
            'otro' => 'Otro estado',
        ],
        'compin' => [
            'sin_informacion' => 'Sin información',
            'en_tramite' => 'En trámite',
            'autorizada' => 'Autorizada',
            'reducida' => 'Reducida',
            'rechazada' => 'Rechazada',
            'ampliada' => 'Ampliada',
            'pendiente_antecedentes' => 'Pendiente de antecedentes',
            'sin_resolucion' => 'Sin resolución',
            'otro' => 'Otro estado oficial',
        ],
        'recuperacion' => [
            'no_evaluada' => 'No evaluada',
            'no_recuperable' => 'No recuperable',
            'pendiente' => 'Pendiente de recuperación',
            'en_cobro' => 'En gestión de cobro',
            'recuperada_parcial' => 'Recuperada parcialmente',
            'recuperada_total' => 'Recuperada totalmente',
            'cerrada_sin_recuperacion' => 'Cerrada sin recuperación',
        ],
    ],

    'aliases' => [
        'administrativo' => [
            'ingresada' => ['INGRESADA', 'IMPORTADA SEGUIMIENTO'],
            'recibida' => ['RECIBIDA', 'OTORGADA', '1- OTORGADA'],
            'en_tramitacion' => ['TRAMITE', 'EN TRAMITE', 'EN TRAMITACIÓN', 'TRAMITADA', 'AUTORIZAR', 'SIN INFORMACION', 'SIN INFORMACIÓN', 'SIN RESOLUCION', 'SIN RESOLUCIÓN'],
            'autorizada' => ['AUTORIZADA', 'AUTORIZADAS', 'AUTORIZADO', 'AUTIRIZADA'],
            'reducida' => ['REDUCIDA', 'REDUCCION', 'REDUCCIÓN'],
            'rechazada' => ['RECHAZADA', 'RECHAZO'],
            'pendiente_antecedentes' => ['PENDIENTE DE ANTECEDENTES', 'ANTECEDENTES PENDIENTES'],
            'cerrada' => ['CERRADA', 'FINALIZADA'],
            'otro' => ['AMPLIADA'],
        ],
        'compin' => [
            'sin_informacion' => ['SIN INFORMACION', 'SIN INFORMACIÓN'],
            'en_tramite' => ['TRAMITE', 'EN TRAMITE', 'EN TRAMITACIÓN', 'TRAMITADA', 'AUTORIZAR', 'IMPORTADA SEGUIMIENTO'],
            'autorizada' => ['AUTORIZADA', 'AUTORIZADAS', 'AUTORIZADO', 'AUTIRIZADA', 'OTORGADA', '1- OTORGADA'],
            'reducida' => ['REDUCIDA', 'REDUCCION', 'REDUCCIÓN'],
            'rechazada' => ['RECHAZADA', 'RECHAZO'],
            'ampliada' => ['AMPLIADA'],
            'pendiente_antecedentes' => ['PENDIENTE DE ANTECEDENTES', 'ANTECEDENTES PENDIENTES'],
            'sin_resolucion' => ['SIN RESOLUCION', 'SIN RESOLUCIÓN'],
        ],
        'recuperacion' => [
            'no_evaluada' => ['NO EVALUADA', 'SIN EVALUAR'],
            'no_recuperable' => ['NO RECUPERABLE', 'NO SE PUEDE RECUPERAR'],
            'pendiente' => ['PENDIENTE', 'PENDIENTE DE RECUPERACION', 'PENDIENTE DE RECUPERACIÓN'],
            'en_cobro' => ['EN COBRO', 'EN GESTION DE COBRO', 'EN GESTIÓN DE COBRO'],
            'recuperada_parcial' => ['RECUPERADA PARCIAL', 'RECUPERADA PARCIALMENTE'],
            'recuperada_total' => ['RECUPERADA', 'RECUPERADA TOTAL', 'RECUPERADA TOTALMENTE', 'PAGADA'],
            'cerrada_sin_recuperacion' => ['CERRADA SIN RECUPERACION', 'CERRADA SIN RECUPERACIÓN'],
        ],
    ],

    'roles' => [
        'lectura' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'digitador_licencias', 'analista_licencias', 'analista_smc', 'administrador_licencias'],
        'digitacion' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'digitador_licencias', 'analista_licencias', 'administrador_licencias'],
        'estado_administrativo' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'analista_licencias', 'administrador_licencias'],
        'estado_compin' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'analista_smc', 'administrador_licencias'],
        'estado_recuperacion' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'administrador_licencias'],
        'importacion_compin' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'analista_smc', 'administrador_licencias'],
        'configuracion' => ['admin', 'funcionario_slep', 'coordinador_gdp', 'administrador_licencias'],
    ],
];
