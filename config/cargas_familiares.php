<?php

return [
    'acceso_solicitantes' => [
        'roles_habilitados' => ['funcionario_ac'],
        'roles_bloqueados_temporalmente' => ['postulante', 'funcionario'],
        'mensaje_bloqueo' => 'El acceso a Mis Cargas Familiares se encuentra temporalmente bloqueado para postulantes y funcionarios. SLEP informara cuando el tramite este nuevamente disponible.',
    ],

    'sexo' => [
        '01' => '01 Masculino',
        '02' => '02 Femenino',
    ],

    'beneficios' => [
        '01' => '01 - Asignacion familiar',
        '02' => '02 - Asignacion maternal',
    ],

    'parentescos' => [
        'conyuge' => 'Conyuge',
        'hijo_hija' => 'Hijo/a',
        'hijastro_hijastra' => 'Hijastro/a',
        'nieto_nieta' => 'Nieto/a',
        'bisnieto_bisnieta' => 'Bisnieto/a',
        'madre_viuda' => 'Madre viuda',
        'ascendiente' => 'Ascendiente',
        'trabajadora_embarazada' => 'Trabajadora embarazada',
        'conyuge_embarazada' => 'Conyuge embarazada',
        'menor_a_cargo' => 'Menor a cargo por medida de proteccion',
        'conviviente_civil' => 'Hijo/a de conviviente civil',
        'extranjero' => 'Extranjero',
        'otro' => 'Otro segun respaldo',
    ],

    'documentos' => [
        'formulario_solicitud_asignacion' => 'Formulario de Solicitud de Asignacion Familiar y Maternal',
        'declaracion_jurada_ingresos_pdf' => 'Declaracion Jurada de Ingresos firmada',
        'certificado_matrimonio' => 'Certificado de matrimonio',
        'resolucion_invalidez_compin' => 'Resolucion que acredita situacion de discapacidad emitida por COMPIN',
        'certificado_nacimiento_causante' => 'Certificado de nacimiento del causante',
        'certificado_matrimonio_beneficiario' => 'Certificado de matrimonio del beneficiario',
        'certificado_estudios' => 'Certificado de alumno regular / certificado de estudios',
        'contrato_jornada_parcial' => 'Contrato de trabajo de jornada parcial',
        'certificado_nacimiento_padre_madre' => 'Certificado de nacimiento del padre o madre',
        'certificado_nacimiento_abuelo_abuela' => 'Certificado de nacimiento del abuelo o abuela',
        'certificado_defuncion_padres' => 'Certificado de defuncion de ambos padres',
        'informe_asistente_social_abandono' => 'Resolucion u oficio del Tribunal de Familia que acredita abandono',
        'certificado_nacimiento_beneficiario' => 'Certificado de nacimiento del beneficiario',
        'certificado_nacimiento_ascendiente' => 'Certificado de nacimiento del ascendiente causante',
        'certificados_descendientes_intermedios' => 'Certificados de nacimiento de descendientes intermedios',
        'certificado_matrimonio_madre' => 'Certificado de matrimonio de la madre',
        'certificado_defuncion_conyuge_madre' => 'Certificado de defuncion del conyuge de la madre',
        'resolucion_tribunal_familia' => 'Resolucion u oficio del Tribunal de Familia por medida de proteccion',
        'certificado_quinto_mes_embarazo' => 'Certificado que acredita quinto mes de embarazo',
        'certificado_quinto_mes_visado_compin' => 'Certificado de embarazo visado por COMPIN',
        'certificado_acuerdo_union_civil' => 'Certificado de acuerdo de union civil',
        'certificado_nacimiento_carga' => 'Certificado de nacimiento de la carga',
        'certificado_civil_pais_origen' => 'Certificado de nacimiento o matrimonio del pais de origen',
        'certificado_apostillado' => 'Certificado apostillado por cada certificado civil',
        'certificado_consulado_minrel' => 'Certificado civil timbrado por Consulado y Ministerio de Relaciones Exteriores',
        'cedula_identidad_extranjero' => 'Copia de cedula de identidad para extranjeros',
        'declaracion_hijo_mayor_expensas' => 'Declaracion Jurada de Hijo Mayor que vive a expensas',
        'certificado_nacimiento' => 'Certificado de Nacimiento',
        'otro_respaldo' => 'Otro respaldo',
    ],

    'codigos_causante' => [
        '01' => [
            'nombre' => 'Conyuge mujer o varon',
            'parentesco' => 'conyuge',
            'documentos_obligatorios' => ['certificado_matrimonio'],
            'documentos_condicionales' => [],
        ],
        '03' => [
            'nombre' => 'Conyuge mujer o varon',
            'parentesco' => 'conyuge',
            'documentos_obligatorios' => ['certificado_matrimonio'],
            'documentos_condicionales' => [],
        ],
        '02' => [
            'nombre' => 'Conyuge en situacion de discapacidad',
            'parentesco' => 'conyuge',
            'documentos_obligatorios' => ['certificado_matrimonio', 'resolucion_invalidez_compin'],
            'documentos_condicionales' => [],
        ],
        '04' => [
            'nombre' => 'Hijo, adoptado o hijastro menor o igual a 18 anos',
            'parentesco' => 'hijo_hija',
            'documentos_obligatorios' => ['certificado_nacimiento_causante'],
            'documentos_condicionales' => [
                'es_hijastro' => [
                    'pregunta' => 'Es hijastro/a',
                    'documento' => 'certificado_matrimonio_beneficiario',
                    'ayuda' => 'Obligatorio cuando el causante es hijastro/a.',
                ],
            ],
        ],
        '05' => [
            'nombre' => 'Hijo, adoptado o hijastro en situacion de discapacidad sin limite de edad',
            'parentesco' => 'hijo_hija',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_invalidez_compin'],
            'documentos_condicionales' => [
                'es_hijastro' => [
                    'pregunta' => 'Es hijastro/a',
                    'documento' => 'certificado_matrimonio_beneficiario',
                    'ayuda' => 'Obligatorio cuando el causante es hijastro/a.',
                ],
            ],
        ],
        '06' => [
            'nombre' => 'Hijo, adoptado o hijastro entre 18 y 24 anos, estudiante',
            'parentesco' => 'hijo_hija',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'certificado_estudios'],
            'documentos_condicionales' => [
                'es_hijastro' => [
                    'pregunta' => 'Es hijastro/a',
                    'documento' => 'certificado_matrimonio_beneficiario',
                    'ayuda' => 'Obligatorio cuando el causante es hijastro/a.',
                ],
                'estudiante_trabajador' => [
                    'pregunta' => 'Es estudiante trabajador',
                    'documento' => 'contrato_jornada_parcial',
                    'ayuda' => 'Obligatorio si el estudiante trabaja con jornada parcial.',
                ],
            ],
        ],
        '07' => [
            'nombre' => 'Nietos y bisnietos huerfanos o abandonados hasta 18 anos',
            'parentesco' => 'nieto_nieta',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'certificado_nacimiento_padre_madre'],
            'documentos_condicionales' => [
                'es_bisnieto' => ['pregunta' => 'Es bisnieto/a', 'documento' => 'certificado_nacimiento_abuelo_abuela', 'ayuda' => 'Obligatorio si el causante es bisnieto/a.'],
                'es_huerfano' => ['pregunta' => 'Es huerfano/a de padre y madre', 'documento' => 'certificado_defuncion_padres', 'ayuda' => 'Obligatorio si se acredita orfandad.'],
                'es_abandonado' => ['pregunta' => 'Es abandonado/a por los padres', 'documento' => 'informe_asistente_social_abandono', 'ayuda' => 'Obligatorio si se acredita abandono mediante respaldo del Tribunal de Familia.'],
            ],
        ],
        '08' => [
            'nombre' => 'Nietos y bisnietos en situacion de discapacidad sin limite de edad, huerfanos o abandonados',
            'parentesco' => 'nieto_nieta',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_invalidez_compin', 'certificado_nacimiento_padre_madre'],
            'documentos_condicionales' => [
                'es_bisnieto' => ['pregunta' => 'Es bisnieto/a', 'documento' => 'certificado_nacimiento_abuelo_abuela', 'ayuda' => 'Obligatorio si el causante es bisnieto/a.'],
                'es_huerfano' => ['pregunta' => 'Es huerfano/a de padre y madre', 'documento' => 'certificado_defuncion_padres', 'ayuda' => 'Obligatorio si se acredita orfandad.'],
                'es_abandonado' => ['pregunta' => 'Es abandonado/a por los padres', 'documento' => 'informe_asistente_social_abandono', 'ayuda' => 'Obligatorio si se acredita abandono mediante respaldo del Tribunal de Familia.'],
            ],
        ],
        '09' => [
            'nombre' => 'Madre viuda',
            'parentesco' => 'madre_viuda',
            'documentos_obligatorios' => ['certificado_nacimiento_beneficiario', 'certificado_matrimonio_madre', 'certificado_defuncion_conyuge_madre'],
            'documentos_condicionales' => [],
        ],
        '10' => [
            'nombre' => 'Ascendiente mayor de 65 anos',
            'parentesco' => 'ascendiente',
            'documentos_obligatorios' => ['certificado_nacimiento_beneficiario', 'certificado_nacimiento_ascendiente'],
            'documentos_condicionales' => [
                'beneficiario_es_nieto' => ['pregunta' => 'El beneficiario acredita el vinculo como nieto/a', 'documento' => 'certificados_descendientes_intermedios', 'ayuda' => 'Obligatorio cuando se debe acreditar el vinculo mediante descendientes intermedios.'],
            ],
        ],
        '11' => [
            'nombre' => 'Ascendiente en situacion de discapacidad sin limite de edad',
            'parentesco' => 'ascendiente',
            'documentos_obligatorios' => ['certificado_nacimiento_beneficiario', 'resolucion_invalidez_compin', 'certificado_nacimiento_ascendiente'],
            'documentos_condicionales' => [
                'beneficiario_es_nieto' => ['pregunta' => 'El beneficiario acredita el vinculo como nieto/a', 'documento' => 'certificados_descendientes_intermedios', 'ayuda' => 'Obligatorio cuando se debe acreditar el vinculo mediante descendientes intermedios.'],
            ],
        ],
        '17' => [
            'nombre' => 'Nietos y bisnietos huerfanos o abandonados entre 18 y 24 anos, estudiantes',
            'parentesco' => 'nieto_nieta',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'certificado_nacimiento_padre_madre', 'certificado_estudios'],
            'documentos_condicionales' => [
                'es_bisnieto' => ['pregunta' => 'Es bisnieto/a', 'documento' => 'certificado_nacimiento_abuelo_abuela', 'ayuda' => 'Obligatorio si el causante es bisnieto/a.'],
                'es_huerfano' => ['pregunta' => 'Es huerfano/a de padre y madre', 'documento' => 'certificado_defuncion_padres', 'ayuda' => 'Obligatorio si se acredita orfandad.'],
                'es_abandonado' => ['pregunta' => 'Es abandonado/a por los padres', 'documento' => 'informe_asistente_social_abandono', 'ayuda' => 'Obligatorio si se acredita abandono mediante respaldo del Tribunal de Familia.'],
            ],
        ],
        '18' => [
            'nombre' => 'Ninos huerfanos o abandonados menores de 18 anos al cuidado de alguna institucion',
            'parentesco' => 'menor_a_cargo',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_tribunal_familia'],
            'documentos_condicionales' => [],
        ],
        '19' => [
            'nombre' => 'Nietos huerfanos o abandonados entre 18 y 24 anos, estudiantes al cuidado de alguna institucion',
            'parentesco' => 'nieto_nieta',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_tribunal_familia', 'certificado_estudios'],
            'documentos_condicionales' => [],
        ],
        '20' => [
            'nombre' => 'Ninos huerfanos o abandonados en situacion de discapacidad al cuidado de alguna institucion',
            'parentesco' => 'menor_a_cargo',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_tribunal_familia', 'resolucion_invalidez_compin'],
            'documentos_condicionales' => [],
        ],
        '21' => [
            'nombre' => 'Trabajadora embarazada',
            'parentesco' => 'trabajadora_embarazada',
            'documentos_obligatorios' => ['certificado_quinto_mes_embarazo'],
            'documentos_condicionales' => [
                'certificado_particular_o_isapre' => ['pregunta' => 'El certificado fue emitido por Isapre o medico particular', 'documento' => 'certificado_quinto_mes_visado_compin', 'ayuda' => 'En este caso debe venir visado por COMPIN.'],
            ],
        ],
        '22' => [
            'nombre' => 'Conyuge embarazada',
            'parentesco' => 'conyuge_embarazada',
            'documentos_obligatorios' => ['certificado_matrimonio', 'certificado_quinto_mes_embarazo'],
            'documentos_condicionales' => [
                'certificado_particular_o_isapre' => ['pregunta' => 'El certificado fue emitido por Isapre o medico particular', 'documento' => 'certificado_quinto_mes_visado_compin', 'ayuda' => 'En este caso debe venir visado por COMPIN.'],
            ],
        ],
        '26' => [
            'nombre' => 'Menor a cargo de persona natural por medida de proteccion, menor o igual a 18 anos',
            'parentesco' => 'menor_a_cargo',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_tribunal_familia'],
            'documentos_condicionales' => [],
        ],
        '27' => [
            'nombre' => 'Menor a cargo de persona natural por medida de proteccion, en situacion de discapacidad de cualquier edad',
            'parentesco' => 'menor_a_cargo',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_tribunal_familia', 'resolucion_invalidez_compin'],
            'documentos_condicionales' => [],
        ],
        '28' => [
            'nombre' => 'Menor a cargo de persona natural por medida de proteccion entre 18 y 24 anos, estudiante',
            'parentesco' => 'menor_a_cargo',
            'documentos_obligatorios' => ['certificado_nacimiento_causante', 'resolucion_tribunal_familia', 'certificado_estudios'],
            'documentos_condicionales' => [],
        ],
        '29' => [
            'nombre' => 'Acuerdo de union civil, solo para hijos del otro conviviente civil',
            'parentesco' => 'conviviente_civil',
            'documentos_obligatorios' => ['certificado_acuerdo_union_civil', 'certificado_nacimiento_carga'],
            'documentos_condicionales' => [],
        ],
        '30' => [
            'nombre' => 'Extranjeros',
            'parentesco' => 'extranjero',
            'documentos_obligatorios' => ['certificado_civil_pais_origen', 'certificado_apostillado', 'certificado_consulado_minrel', 'cedula_identidad_extranjero'],
            'documentos_condicionales' => [],
        ],
    ],
];
