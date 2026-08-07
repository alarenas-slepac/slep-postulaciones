<?php
return [
    'timezone' => 'America/Santiago',

    'roles_visualizacion' => [
        'admin',
        'director_ejecutivo',
        'funcionario_slep',
        'coordinador_gdp',
        'coordinador_uatp',
        'comunicaciones',
        'gabinete_slep',
        'secretaria_direccion_ejecutiva',
    ],

    'rol_reporte' => 'funcionario_directivo_estab',

    'servicios' => [
        'agua_potable' => ['label' => 'Agua potable', 'icon' => 'bi-droplet-fill'],
        'energia_electrica' => ['label' => 'Energía eléctrica', 'icon' => 'bi-lightning-charge-fill'],
        'internet' => ['label' => 'Internet', 'icon' => 'bi-wifi'],
        'telefonia' => ['label' => 'Telefonía', 'icon' => 'bi-telephone-fill'],
        'calefaccion' => ['label' => 'Calefacción', 'icon' => 'bi-thermometer-sun'],
        'infraestructura' => ['label' => 'Infraestructura', 'icon' => 'bi-buildings-fill'],
        'sistema_seguridad' => ['label' => 'Sistema de seguridad', 'icon' => 'bi-shield-check'],
        'camaras' => ['label' => 'Cámaras', 'icon' => 'bi-camera-video-fill'],
        'alarma' => ['label' => 'Alarma', 'icon' => 'bi-bell-fill'],
        'cocina_junaeb' => ['label' => 'Cocina / JUNAEB', 'icon' => 'bi-fork-knife'],
        'transporte_escolar' => ['label' => 'Transporte escolar', 'icon' => 'bi-bus-front-fill'],
        'control_plagas' => ['label' => 'Control de plagas', 'icon' => 'bi-bug-fill'],
        'extintores' => ['label' => 'Extintores', 'icon' => 'bi-fire-extinguisher'],
    ],

    'estados_servicio' => [
        'operativo' => 'Operativo',
        'alerta' => 'Alerta',
        'critico' => 'Crítico',
    ],

    'funcionamientos' => [
        'si' => ['label' => 'Sí', 'description' => 'Funcionamiento normal', 'severity' => 'operativo'],
        'parcialmente' => ['label' => 'Parcialmente', 'description' => 'Con restricciones', 'severity' => 'alerta'],
        'no' => ['label' => 'No', 'description' => 'No está funcionando', 'severity' => 'critico'],
    ],

    'afectaciones' => [
        'suspension_parcial' => ['label' => 'Suspensión parcial', 'severity' => 'alerta'],
        'suspension_total' => ['label' => 'Suspensión total', 'severity' => 'critico'],
        'jornada_reducida' => ['label' => 'Jornada reducida', 'severity' => 'alerta'],
        'sin_alimentacion' => ['label' => 'Sin alimentación', 'severity' => 'alerta'],
        'sin_transporte' => ['label' => 'Sin transporte', 'severity' => 'alerta'],
        'otro' => ['label' => 'Otra afectación', 'severity' => 'alerta'],
        'albergue' => ['label' => 'Utilizado como albergue', 'severity' => 'alerta'],
    ],

    'incidencias' => [
        'corte_agua' => ['label' => 'Corte de agua', 'severity' => 'critico'],
        'corte_energia' => ['label' => 'Corte de energía', 'severity' => 'critico'],
        'corte_internet' => ['label' => 'Corte de internet', 'severity' => 'alerta'],
        'robo' => ['label' => 'Robo', 'severity' => 'alerta'],
        'vandalismo' => ['label' => 'Vandalismo', 'severity' => 'alerta'],
        'filtraciones' => ['label' => 'Filtraciones', 'severity' => 'alerta'],
        'dano_estructural' => ['label' => 'Daño estructural', 'severity' => 'critico'],
        'emergencia_sanitaria' => ['label' => 'Emergencia sanitaria', 'severity' => 'critico'],
        'violencia_escolar' => ['label' => 'Violencia escolar', 'severity' => 'critico'],
        'accidente_escolar' => ['label' => 'Accidente escolar', 'severity' => 'critico'],
        'problemas_calefaccion' => ['label' => 'Problemas de calefacción', 'severity' => 'alerta'],
        'toma_establecimiento' => ['label' => 'Toma de establecimiento', 'severity' => 'critico'],
        'amago_incendio' => ['label' => 'Amago de incendio', 'severity' => 'critico'],
        'control_plagas_vencido' => [
            'label' => 'Control de plagas vencido',
            'severity' => 'critico',
            'automatic' => true,
        ],
        'extintor_no_operativo' => [
            'label' => 'Extintor no operativo',
            'severity' => 'critico',
            'automatic' => true,
        ],
    ],

    'modalidades_incidencia' => [
        'evacuacion' => [
            'simulacro' => 'Simulacro',
            'emergencia_declarada' => 'Emergencia declarada',
        ],
    ],

    'severidades_modalidad_incidencia' => [
        'evacuacion' => [
            'simulacro' => 'alerta',
            'emergencia_declarada' => 'critico',
        ],
    ],

    /*
     * Unidades visibles exclusivamente en el Centro de Operaciones. No se
     * incorporan al catálogo general de establecimientos ni a otros módulos.
     */
    'unidades_operacionales' => [
        'internado_nueva_zelandia' => [
            'label' => 'Internado',
            'nombre_reporte' => 'Internado · Liceo Nueva Zelandia',
            'establecimiento_nombre_contiene' => 'Nueva Zelandia',
            'matricula_total' => 0,
            'docentes_total' => 0,
            'asistentes_total' => 0,
        ],
    ],

    'prioridades' => [
        'sin_novedad' => ['label' => 'Sin novedad', 'severity' => 'operativo'],
        'durante_dia' => ['label' => 'Atención durante el día', 'severity' => 'alerta'],
        'urgente' => ['label' => 'Atención urgente', 'severity' => 'alerta'],
        'inmediata' => ['label' => 'Atención inmediata', 'severity' => 'critico'],
    ],

    'severidad_orden' => [
        'sin_reporte' => -1,
        'operativo' => 0,
        'alerta' => 1,
        'critico' => 2,
    ],
];