<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        .muted {
            color: #666;
        }

        .mb {
            margin-bottom: 10px;
        }

        .header {
            width: 100%;
            margin-bottom: 12px;
        }

        .header td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .logo {
            height: 52px;
            max-width: 220px;
            object-fit: contain;
        }

        table.tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .tbl th,
        .tbl td {
            border: 1px solid #ddd;
            padding: 6px;
        }

        .tbl th {
            background: #f3f3f3;
            text-align: left;
        }

        .right {
            text-align: right;
        }

        h2,
        h3 {
            margin: 0 0 6px 0;
        }

        h4 {
            margin: 8px 0 4px 0;
        }
    </style>
</head>

<body>
    @php
        $fmt = function ($n) {
            $n = (float) ($n ?? 0);
            $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
            return $s === '' ? '0' : $s;
        };

        $formatRut = function ($rut) {
            $rut = strtoupper(preg_replace('/[^0-9K]/', '', (string) $rut));
            if ($rut === '' || strlen($rut) < 2) {
                return (string) $rut;
            }
            return substr($rut, 0, -1) . '-' . substr($rut, -1);
        };

        $logoData = null;
        foreach ([
            public_path(config('brand.logo_pdf', 'branding/01_logo_principal.png')),
            public_path(config('brand.logo_lockup_horizontal', 'branding/04_lockup_horizontal.png')),
            public_path('branding/01_logo_principal.svg'),
        ] as $logoFile) {
            if (is_file($logoFile)) {
                $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
                $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFile));
                break;
            }
        }

        $postUser = $s->postulante?->user;
        $postName = $postUser
            ? trim(
                ($postUser->apellido_paterno ?? '') .
                    ' ' .
                    ($postUser->apellido_materno ?? '') .
                    ' ' .
                    ($postUser->nombres ?? ''),
            )
            : null;
        $postRut = $postUser?->rut ?? null;

        $titBas = $s->jornadas->sum('titular_basica');
        $titMed = $s->jornadas->sum('titular_media');
        $titTot = $s->jornadas->sum('titular_total');

        $repBas = $s->jornadas->sum('reemplazo_basica');
        $repMed = $s->jornadas->sum('reemplazo_media');
        $repTot = $s->jornadas->sum('reemplazo_total');
        $estatutoTitular = strtoupper(trim((string) ($s->funcionarioTitular?->estatuto ?? '')));
        $titularEsDocente = in_array($estatutoTitular, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($estatutoTitular, 'DOC');
    @endphp

    <table class="header">
        <tr>
            <td style="width:35%;">
                @if ($logoData)
                    <img class="logo" src="{{ $logoData }}" alt="Plataforma SLEP Andalién Costa">
                @endif
            </td>
            <td style="width:65%; text-align:right;">
                <h2>Solicitud de Reemplazo ({{ $s->anio }})</h2>
                <div class="muted">
                    <strong>N°:</strong> {{ $s->numero_solicitud }} |
                    <strong>Fecha:</strong> {{ cl_datetime($s->created_at, 'd/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    <h3>Establecimiento</h3>
    <p class="mb">
        {{ $s->establecimiento->rbd ?? '' }} -
        {{ $s->establecimiento->nombre_establecimiento ?? ($s->establecimiento->nombre ?? '') }}<br>
        <span class="muted">Comuna:</span> {{ $s->establecimiento->comuna ?? '—' }}
    </p>

    <h3>Funcionario a reemplazar</h3>
    <p class="mb">
        {{ $s->funcionarioTitular->rut ?? '' }} - {{ $s->funcionarioTitular->nombre ?? '' }}<br>
        <span class="muted">Estatuto:</span> {{ $s->funcionarioTitular->estatuto ?? '—' }} |
        <span class="muted">Escalafón:</span> {{ $s->funcionarioTitular->escalafon ?? '—' }}<br>
        <span class="muted">Área de desempeño:</span> {{ $s->areaDesempeno?->nombre ?? '—' }}
    </p>

    <h4>Distribución de jornada (Titular)</h4>
    <table class="tbl mb">
        <thead>
            <tr>
                <th>Financiamiento</th>
                <th class="right">HRS BÁSICA</th>
                <th class="right">HRS MEDIA</th>
                <th class="right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($s->jornadas as $j)
                <tr>
                    <td>{{ $j->financiamiento }}</td>
                    <td class="right">{{ $fmt($j->titular_basica) }}</td>
                    <td class="right">{{ $fmt($j->titular_media) }}</td>
                    <td class="right">{{ $fmt($j->titular_total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th class="right">TOTAL</th>
                <th class="right">{{ $fmt($titBas) }}</th>
                <th class="right">{{ $fmt($titMed) }}</th>
                <th class="right">{{ $fmt($titTot) }}</th>
            </tr>
        </tfoot>
    </table>

    @if ($titularEsDocente)
        <p class="mb">
            <span class="muted">Horas Aula Cronológicas (titular):</span> {{ $fmt($s->horas_aula_cronologicas_titular) }}<br>
            <span class="muted">Horas Aula Pedagógicas (titular):</span> {{ $fmt($s->horas_aula_pedagogicas_titular) }}
        </p>
    @endif

    <h3>Detalle del reemplazo</h3>
    <p class="mb">
        <span class="muted">Tipo:</span> {{ $s->tipo_reemplazo }} @if ($s->tipo_reemplazo === 'Otras')
            ({{ $s->tipo_reemplazo_otro }})
        @endif
        <br>
        <span class="muted">Periodo:</span> {{ $s->fecha_inicio->format('d/m/Y') }} -
        {{ $s->fecha_termino->format('d/m/Y') }}<br>
        <span class="muted">Propone postulante:</span> {{ $s->propone_reemplazo ? 'Sí' : 'No' }}
    </p>

    @if ($s->propone_reemplazo && $s->postulante && $postUser)
        <h3>Postulante propuesto</h3>
        <p class="mb">
            <strong>{{ $formatRut($postRut) }}</strong> - {{ $postName }}<br>
            <span class="muted">Área de desempeño:</span> {{ $s->postulante->areaDesempeno?->nombre ?? '—' }}
        </p>
    @else
        <h3>Postulante propuesto</h3>
        <p class="mb"><span class="muted">Sin postulante propuesto por el establecimiento.</span></p>
    @endif

    {{-- ✅ SIEMPRE mostrar horas reemplazo --}}
    <h4>Distribución de jornada (Reemplazo)</h4>
    <table class="tbl mb">
        <thead>
            <tr>
                <th>Financiamiento</th>
                <th class="right">HRS BÁSICA</th>
                <th class="right">HRS MEDIA</th>
                <th class="right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($s->jornadas as $j)
                <tr>
                    <td>{{ $j->financiamiento }}</td>
                    <td class="right">{{ $fmt($j->reemplazo_basica) }}</td>
                    <td class="right">{{ $fmt($j->reemplazo_media) }}</td>
                    <td class="right">{{ $fmt($j->reemplazo_total) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th class="right">TOTAL</th>
                <th class="right">{{ $fmt($repBas) }}</th>
                <th class="right">{{ $fmt($repMed) }}</th>
                <th class="right">{{ $fmt($repTot) }}</th>
            </tr>
        </tfoot>
    </table>

    @if ($titularEsDocente)
        <p class="mb">
            <span class="muted">Horas Aula Cronológicas (reemplazo):</span> {{ $fmt($s->horas_aula_cronologicas_reemplazo) }}<br>
            <span class="muted">Horas Aula Pedagógicas (reemplazo):</span> {{ $fmt($s->horas_aula_pedagogicas_reemplazo) }}
        </p>
    @endif

    <p class="mb">
        <span class="muted">Declaración de responsabilidad:</span>
        {{ $s->declaracion_responsabilidad_aceptada ? 'Aceptada' : 'No registrada' }}
    </p>

    @if ($s->observaciones)
        <h3>Observaciones</h3>
        <p class="mb">{{ $s->observaciones }}</p>
    @endif


    @if (!empty($s->justificacion_tecnica_uatp))
        <h3>Justificación técnica UATP</h3>
        <p class="mb">{{ $s->justificacion_tecnica_uatp }}</p>
    @endif

    @if (!empty($s->plani_motivo_rechazo))
        <h3>Motivo de rechazo Planificación</h3>
        <p class="mb">{{ $s->plani_motivo_rechazo }}</p>
    @endif

    @if ($s->planiDecisionUser || $s->plani_decision_at)
        <p class="mb">
            <span class="muted">Decisión Planificación:</span> {{ $s->estado === 'rechazada_plani' ? 'Rechazada' : 'Validada' }}<br>
            <span class="muted">Usuario:</span> {{ $s->planiDecisionUser?->full_name ?? ($s->planiDecisionUser?->email ?? '—') }}<br>
            <span class="muted">Fecha:</span> {{ cl_datetime($s->plani_decision_at, 'd/m/Y H:i') }}
        </p>
    @endif

    @if (!empty($s->observacion_slep))
        <h3>Observación SLEP</h3>
        <p class="mb">
            {{ $s->observacion_slep }}<br>
            <span class="muted">
                Informada por: {{ $s->observacionSlepUser?->nombre_completo ?: ($s->observacionSlepUser?->email ?? 'Usuario SLEP') }}
                @if ($s->observacion_slep_at) | Fecha: {{ cl_datetime($s->observacion_slep_at, 'd/m/Y H:i') }} @endif
            </span>
        </p>
    @endif

    <p class="muted">Estado: {{ str_replace('_', ' ', $s->estado) }}</p>
</body>

</html>
