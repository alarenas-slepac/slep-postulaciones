<?php

return [
    'timezone' => 'America/Santiago',
    'polling_ms' => 10000,
    // Claves estables: se almacenan en los escrutinios históricos.
    'papeletas' => [
        'desafiliacion' => [
            'proceso' => 'CCAF', 'titulo' => 'Desafiliación CCAF',
            'pregunta' => 'Deseo desafiliarme de CCAF Los Andes',
            'opciones' => ['si' => 'Sí', 'no' => 'No'],
        ],
        'afiliacion' => [
            'proceso' => 'CCAF', 'titulo' => 'Afiliación CCAF',
            'pregunta' => 'Deseo afiliarme a CCAF',
            'opciones' => ['los_heroes' => 'Los Héroes', '18_septiembre' => '18 de Septiembre', 'la_araucana' => 'La Araucana'],
        ],
        'mutualidad' => [
            'proceso' => 'CONSULTA_MUTUALIDADES', 'titulo' => 'Consulta Mutual',
            'pregunta' => 'Deseo afiliarme a mutual de seguridad laboral',
            'opciones' => ['achs' => 'ACHS', 'isl' => 'ISL', 'ist' => 'IST', 'mutual' => 'Mutual'],
        ],
    ],
    'routing' => [
        'enabled' => env('VOTACIONES_ROUTING_ENABLED', true),
        'base_url' => env('VOTACIONES_ROUTING_BASE_URL', 'https://router.project-osrm.org'),
        'profile' => env('VOTACIONES_ROUTING_PROFILE', 'driving'),
        'timeout_seconds' => (int) env('VOTACIONES_ROUTING_TIMEOUT', 8),
        'cache_ttl_seconds' => (int) env('VOTACIONES_ROUTING_CACHE_TTL', 86400),
        'failure_cache_ttl_seconds' => (int) env('VOTACIONES_ROUTING_FAILURE_CACHE_TTL', 60),
    ],
    'tipos_incidencia' => [
        'retraso' => 'Retraso',
        'problema_traslado' => 'Problema de traslado',
        'establecimiento_cerrado' => 'Establecimiento cerrado',
        'proceso_suspendido' => 'Proceso suspendido',
        'problema_material' => 'Problema de material',
        'otro' => 'Otro',
    ],
];
