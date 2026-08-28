<?php

return [
    'timezone' => 'America/Santiago',
    'polling_ms' => 10000,
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
