<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe de Dotación Establecimiento</title>
    <style>
        @page { margin: 18px 16px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.5px; color: #111827; }
        .header { border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 10px; }
        .header-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header-table td { vertical-align: middle; border: 0; padding: 0; }
        .logo { height: 42px; max-width: 190px; object-fit: contain; }
        .title { font-size: 16px; font-weight: 700; color: #0b3d91; margin: 0 0 2px 0; }
        .subtitle { font-size: 9px; color: #64748b; margin: 0; }
        .section-title { font-size: 11px; font-weight: 700; color: #0b3d91; margin: 10px 0 5px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; }
        th { background: #eaf2ff; font-weight: 700; color: #0f172a; }
        .meta td { width: 16.6%; }
        .label { color: #64748b; font-size: 7px; text-transform: uppercase; font-weight: 700; }
        .value { font-size: 10px; font-weight: 700; margin-top: 2px; }
        .summary th { text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .muted { color: #64748b; }
        .total-row td { background: #f1f5f9; font-weight: 700; }
        .group-row td { background: #d9fdd9; font-weight: 700; color: #064e3b; }
        .badge { display: inline-block; border-radius: 999px; padding: 2px 6px; font-size: 7px; font-weight: 700; white-space: nowrap; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .badge-green { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-gray { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .danger { color: #b91c1c; font-weight: 700; }
        .success { color: #15803d; font-weight: 700; }
        .primary { color: #0d6efd; font-weight: 700; }
        .note { background: #f8fafc; border: 1px solid #d1d5db; padding: 7px; margin-top: 8px; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: auto; }
        .footer { margin-top: 10px; font-size: 7px; color: #64748b; text-align: right; }
        .small { font-size: 7.5px; }
    </style>
</head>
<body>
@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $fmtSigned = fn ($value) => ((float) $value > 0.01 ? '+' : '').$fmt($value);
    $logoData = null;
    foreach ([
        public_path(config('brand.logo_pdf', 'branding/01_logo_principal.png')),
        public_path(config('brand.logo_lockup_horizontal', 'branding/04_lockup_horizontal.png')),
    ] as $logoFile) {
        if (is_file($logoFile)) {
            $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
            $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFile));
            break;
        }
    }
    $platformName = config('brand.platform_name', 'Plataforma SLEP Andalién Costa');
    $bloquesInformeDotacion = $bloquesContratoDotacion ?? $bloques ?? [];
    $totalBloquesDotacion = collect($bloquesInformeDotacion)->sum(fn ($bloque) => (float) ($bloque['total'] ?? 0));
    $totalAutomaticas = collect($bloquesInformeDotacion)->sum(fn ($bloque) => (float) ($bloque['automaticas'] ?? 0));
    $totalDeclaradas = collect($bloquesInformeDotacion)->sum(fn ($bloque) => (float) ($bloque['declaradas'] ?? 0));
    $desgloseContratoBloque = $resumen['horas_dotacion_desglose'] ?? [];
    $horasBloqueNormativas = (float) ($resumen['horas_dotacion_funciones_normativas'] ?? $desgloseContratoBloque['total_normativas'] ?? $totalAutomaticas);
    $horasBloqueDeclaradas = (float) ($resumen['horas_dotacion_funciones_declaradas'] ?? $desgloseContratoBloque['total_declaradas'] ?? $totalDeclaradas);
    $horasBloqueDeclaradasAsignadas = (float) ($desgloseContratoBloque['total_declaradas_asignadas'] ?? 0);
    $horasContratoPieNecesarias = (float) ($resumen['horas_contrato_pie_necesarias'] ?? 0);
    $desgloseContratoPieNecesario = $resumen['horas_contrato_pie_necesarias_desglose'] ?? [];
    $horasContratoActuales = (float) ($resumen['horas_contrato_docentes'] ?? 0);
    $horasContratoAula = (float) ($resumen['horas_contrato_docentes_aula'] ?? $horasContratoActuales);
    $horasContratoDocentePie = (float) ($resumen['horas_contrato_docente_pie'] ?? 0);
    $horasContratoCoordinacionPie = (float) ($resumen['horas_contrato_docente_pie_coordinacion'] ?? 0);
    $horasContratoEducadorasDiferenciales = (float) ($resumen['horas_contrato_docente_pie_educadoras_diferenciales'] ?? 0);
    $horasContratoRequeridas = (float) ($resumen['horas_contrato_requeridas'] ?? (($resumen['contrato_plan_mas_trabajo_colaborativo_pie'] ?? 0) + ($resumen['horas_dotacion_funciones'] ?? 0) + $horasContratoPieNecesarias));
    $sobredotacion = max(0, $horasContratoActuales - $horasContratoRequeridas);
    $horasPorContratar = max(0, $horasContratoRequeridas - $horasContratoActuales);
    $brechaTexto = $sobredotacion > 0.01 ? 'Sobredotación' : ($horasPorContratar > 0.01 ? 'Horas por contratar' : 'Dotación cuadrada');
    $brechaValor = $sobredotacion > 0.01 ? $sobredotacion : ($horasPorContratar > 0.01 ? $horasPorContratar : 0);
    $brechaClass = $sobredotacion > 0.01 ? 'badge-red' : ($horasPorContratar > 0.01 ? 'badge-green' : 'badge-blue');
    $horasAulaAsignadas = (float) ($resumen['horas_aula_asignadas'] ?? $resumen['horas_aula_docentes'] ?? 0);
    $horasContrato6535 = (float) ($resumen['horas_contrato_65_35'] ?? 0);
    $horasContrato6040 = (float) ($resumen['horas_contrato_60_40'] ?? 0);
    $horasContratoEspecial = (float) ($resumen['horas_contrato_especial'] ?? 0);
    $horasFuncionesAsignadas = (float) ($resumen['horas_funciones_asignadas'] ?? 0);
    $horasContratoCalculado = (float) ($resumen['horas_contrato_calculado'] ?? 0);
    $diferenciaContratoCalculado = (float) ($resumen['diferencia_contrato_calculado'] ?? ($horasContratoActuales - $horasContratoCalculado));
    $necesidadesPlan = collect($necesidadesPlan ?? []);
    $asignaturasResumenItems = collect(data_get($asignaturasResumen ?? [], 'items', []));
    $asignaturasResumenTotales = data_get($asignaturasResumen ?? [], 'resumen', []);
    $cursosCombinadosGrupos = collect(data_get($cursosCombinados ?? [], 'grupos', []))->where('activo', true)->values();
    $cursosCombinadosResumen = data_get($cursosCombinados ?? [], 'resumen', []);
    $generatedByName = $generatedBy ? trim(($generatedBy->nombres ?? '').' '.($generatedBy->apellido_paterno ?? '').' '.($generatedBy->apellido_materno ?? '')) : '';
    $generatedByName = $generatedByName !== '' ? $generatedByName : ($generatedBy->name ?? 'Sistema');
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td style="width: 220px;">
                @if ($logoData)
                    <img class="logo" src="{{ $logoData }}" alt="{{ $platformName }}">
                @endif
            </td>
            <td style="text-align: right;">
                <div class="title">Informe de Dotación Establecimiento</div>
                <p class="subtitle">{{ $platformName }}</p>
                <p class="subtitle">Generado: {{ $generatedAt->format('d-m-Y H:i') }} | Usuario: {{ $generatedByName }}</p>
            </td>
        </tr>
    </table>
</div>

<table class="meta avoid-break">
    <tr>
        <td><div class="label">RBD</div><div class="value">{{ $establecimiento->rbd }}</div></td>
        <td colspan="2"><div class="label">Establecimiento</div><div class="value">{{ $establecimiento->nombre_establecimiento }}</div></td>
        <td><div class="label">Comuna</div><div class="value">{{ $establecimiento->comuna ?: 'Sin comuna' }}</div></td>
        <td><div class="label">Año</div><div class="value">{{ $anio }}</div></td>
        <td><div class="label">Estado brecha</div><div class="value"><span class="badge {{ $brechaClass }}">{{ $brechaTexto }}</span></div></td>
    </tr>
</table>

@if ((($proporcionExcepcion ?? null)?->activa ?? false))
    <div class="note avoid-break">
        <strong>Proporción especial 60/40 activa:</strong> aplicada a todos los niveles del establecimiento para {{ $anio }}.
        Justificación: {{ $proporcionExcepcion->justificacion }}
    </div>
@endif

@if ($cursosCombinadosGrupos->isNotEmpty())
    <div class="section-title">Cursos combinados</div>
    <table class="avoid-break">
        <thead>
            <tr>
                <th style="width:18%;">Grupo</th>
                <th style="width:31%;">Cursos integrantes</th>
                <th style="width:10%;" class="text-right">Horas brutas</th>
                <th style="width:10%;" class="text-right">Horas requeridas</th>
                <th style="width:10%;" class="text-right">Reducción</th>
                <th style="width:11%;" class="text-right">Contrato requerido</th>
                <th style="width:10%;">Proporción</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cursosCombinadosGrupos as $grupo)
                <tr>
                    <td><strong>{{ $grupo['nombre'] }}</strong></td>
                    <td>{{ collect($grupo['miembros'] ?? [])->pluck('label')->implode(' + ') }}</td>
                    <td class="text-right">{{ $fmt(data_get($grupo, 'totales.horas_brutas', 0)) }}</td>
                    <td class="text-right primary">{{ $fmt(data_get($grupo, 'totales.horas_requeridas', 0)) }}</td>
                    <td class="text-right success">{{ $fmt(data_get($grupo, 'totales.reduccion', 0)) }}</td>
                    <td class="text-right">{{ $fmt(data_get($grupo, 'totales.horas_contrato', 0)) }}</td>
                    <td>{{ $grupo['proporcion_label'] ?? 'Automática' }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total cursos combinados</td>
                <td class="text-right">{{ $fmt($cursosCombinadosResumen['horas_brutas'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($cursosCombinadosResumen['horas_requeridas'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($cursosCombinadosResumen['reduccion'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($cursosCombinadosResumen['horas_contrato'] ?? 0) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endif

<div class="section-title">Resumen ejecutivo</div>
<table class="summary avoid-break">
    <thead>
        <tr>
            <th>Matrícula</th>
            <th>Cursos</th>
            <th>Docentes</th>
            <th>Horas plan</th>
            <th>Contrato plan</th>
            <th>Trabajo colab. PIE</th>
            <th>Contrato plan + colab.</th>
            <th>Bloque normativo</th>
            <th>Bloque declarado<br><span class="small">Asig. / decl.</span></th>
            <th>Contrato PIE necesario</th>
            <th>Horas contrato docentes</th>
            <th>Brecha</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-right">{{ number_format((int) ($resumen['matricula_total'] ?? 0), 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format((int) ($resumen['cursos_total'] ?? 0), 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format((int) ($resumen['docentes_total'] ?? 0), 0, ',', '.') }}</td>
            <td class="text-right primary">{{ $fmt($resumen['horas_plan_total'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($resumen['horas_plan_contrato_equivalente'] ?? 0) }}</td>
            <td class="text-right success">{{ $fmt($resumen['trabajo_colaborativo_pie'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($resumen['contrato_plan_mas_trabajo_colaborativo_pie'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($horasBloqueNormativas) }}</td>
            <td class="text-right">{{ $fmt($horasBloqueDeclaradasAsignadas) }} / {{ $fmt($horasBloqueDeclaradas) }}</td>
            <td class="text-right primary">{{ $fmt($horasContratoPieNecesarias) }}</td>
            <td class="text-right">{{ $fmt($resumen['horas_contrato_docentes'] ?? 0) }}</td>
            <td class="text-right"><span class="badge {{ $brechaClass }}">{{ $fmt($brechaValor) }} - {{ $brechaTexto }}</span></td>
        </tr>
    </tbody>
</table>

<div class="section-title">Desglose horas contrato docentes</div>
<table class="summary avoid-break">
    <thead>
        <tr>
            <th>Contrato aula</th>
            <th>Coordinación PIE asignada</th>
            <th>Bolsa Educadoras Diferenciales asignada</th>
            <th>Contrato docente PIE</th>
            <th>Total contrato docentes vigente</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-right">{{ $fmt($horasContratoAula) }}</td>
            <td class="text-right">{{ $fmt($horasContratoCoordinacionPie) }}</td>
            <td class="text-right">{{ $fmt($horasContratoEducadorasDiferenciales) }}</td>
            <td class="text-right primary">{{ $fmt($horasContratoDocentePie) }}</td>
            <td class="text-right">{{ $fmt($horasContratoActuales) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Desglose contrato bloque dotación</div>
<table class="summary avoid-break">
    <thead>
        <tr>
            <th colspan="2">Funciones directivas</th>
            <th colspan="2">Téc.-pedagógicas normativas</th>
            <th colspan="2">Planes normativos</th>
            <th>Total normativas</th>
        </tr>
        <tr>
            <th>Asignadas</th><th>Requeridas</th>
            <th>Asignadas</th><th>Requeridas</th>
            <th>Asignadas</th><th>Requeridas</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-right primary">{{ $fmt($desgloseContratoBloque['funciones_directivas_normativas_asignadas'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['funciones_directivas_normativas'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_normativas_asignadas'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_normativas'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($desgloseContratoBloque['planes_normativos_asignadas'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['planes_normativos'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($horasBloqueNormativas) }}</td>
        </tr>
    </tbody>
</table>

<table class="summary avoid-break" style="margin-top: 4px;">
    <thead>
        <tr>
            <th>Directivas<br><span class="small">Asig. / decl.</span></th>
            <th>Téc.-pedagógicas<br><span class="small">Asig. / decl.</span></th>
            <th>Planes<br><span class="small">Asig. / decl.</span></th>
            <th>Otras funciones PIE<br><span class="small">Asig. / decl.</span></th>
            <th>Otras funciones<br><span class="small">Asig. / decl.</span></th>
            <th>Total declaradas<br><span class="small">Asig. / decl.</span></th>
            <th>Total bloque</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['funciones_directivas_declaradas_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['funciones_directivas_declaradas'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_declaradas_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_declaradas'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['planes_declarados_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['planes_declarados'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['otras_funciones_pie_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['otras_funciones_pie'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['otras_funciones_declaradas_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['otras_funciones_declaradas'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($horasBloqueDeclaradasAsignadas) }} / {{ $fmt($horasBloqueDeclaradas) }}</td>
            <td class="text-right primary">{{ $fmt($resumen['horas_dotacion_funciones'] ?? 0) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Horas de contrato PIE necesarias</div>
<table class="summary avoid-break">
    <thead>
        <tr>
            <th>Coordinador(a) PIE</th>
            <th>Educadoras diferenciales PIE</th>
            <th>Total contrato PIE necesario</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-right">{{ $fmt($desgloseContratoPieNecesario['coordinacion_pie'] ?? 0) }}</td>
            <td class="text-right">{{ $fmt($desgloseContratoPieNecesario['educadoras_diferenciales'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($horasContratoPieNecesarias) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Asignación real y conversión contractual</div>
<table class="summary avoid-break">
    <thead>
        <tr>
            <th>Horas aula plan</th>
            <th>Horas aula asignadas</th>
            <th>Contrato 65/35</th>
            <th>Contrato 60/40</th>
            <th>Contrato especial</th>
            <th>Funciones asignadas</th>
            <th>Total contrato calculado</th>
            <th>Diferencia con contrato vigente</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-right">{{ $fmt($resumen['horas_plan_total'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($horasAulaAsignadas) }}</td>
            <td class="text-right">{{ $fmt($horasContrato6535) }}</td>
            <td class="text-right">{{ $fmt($horasContrato6040) }}</td>
            <td class="text-right">{{ $fmt($horasContratoEspecial) }}</td>
            <td class="text-right">{{ $fmt($horasFuncionesAsignadas) }}</td>
            <td class="text-right primary">{{ $fmt($horasContratoCalculado) }}</td>
            <td class="text-right {{ $diferenciaContratoCalculado < -0.01 ? 'danger' : ($diferenciaContratoCalculado > 0.01 ? 'success' : 'primary') }}">{{ $fmt(abs($diferenciaContratoCalculado)) }} {{ $diferenciaContratoCalculado < -0.01 ? 'sobrecarga' : ($diferenciaContratoCalculado > 0.01 ? 'disponible' : 'cuadra') }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Cálculo de necesidad contractual</div>
<table class="avoid-break">
    <thead>
        <tr>
            <th>Concepto</th>
            <th class="text-right">Horas</th>
            <th>Observación</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Contrato equivalente plan</td>
            <td class="text-right">{{ $fmt($resumen['horas_plan_contrato_equivalente'] ?? 0) }}</td>
            <td>Contrato requerido para cubrir horas del plan de estudios.</td>
        </tr>
        <tr>
            <td>Trabajo colaborativo PIE</td>
            <td class="text-right">{{ $fmt($resumen['trabajo_colaborativo_pie'] ?? 0) }}</td>
            <td>3 horas por curso con estudiantes NEE.</td>
        </tr>
        <tr>
            <td>Funciones directivas</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['funciones_directivas_normativas_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['funciones_directivas_normativas'] ?? 0) }}</td>
            <td>Horas normativas asignadas / requeridas.</td>
        </tr>
        <tr>
            <td>Funciones técnico-pedagógicas normativas</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_normativas_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_normativas'] ?? 0) }}</td>
            <td>Horas normativas asignadas / requeridas.</td>
        </tr>
        <tr>
            <td>Funciones técnico-pedagógicas declaradas</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_declaradas_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['funciones_tecnico_pedagogicas_declaradas'] ?? 0) }}</td>
            <td>Horas asignadas / declaradas por el establecimiento.</td>
        </tr>
        <tr>
            <td>Coordinador(a) PIE necesario</td>
            <td class="text-right">{{ $fmt($desgloseContratoPieNecesario['coordinacion_pie'] ?? 0) }}</td>
            <td>Horas automáticas normativas de coordinación del Programa de Integración Escolar.</td>
        </tr>
        <tr>
            <td>Educadoras diferenciales PIE necesarias</td>
            <td class="text-right primary">{{ $fmt($desgloseContratoPieNecesario['educadoras_diferenciales'] ?? 0) }}</td>
            <td>Bolsa contractual automática normativa de Educadoras Diferenciales PIE.</td>
        </tr>
        <tr class="total-row">
            <td>Horas de contrato PIE necesarias</td>
            <td class="text-right primary">{{ $fmt($horasContratoPieNecesarias) }}</td>
            <td>Suma de Coordinador(a) PIE y Educadoras Diferenciales PIE normativas.</td>
        </tr>
        <tr>
            <td>Planes normativos</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['planes_normativos_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['planes_normativos'] ?? 0) }}</td>
            <td>Horas normativas asignadas / requeridas.</td>
        </tr>
        <tr>
            <td>Contrato bloque normativo</td>
            <td class="text-right">{{ $fmt($horasBloqueNormativas) }}</td>
            <td>Funciones directivas, técnico-pedagógicas y planes calculados por normativa.</td>
        </tr>
        <tr>
            <td>Contrato bloque declarado</td>
            <td class="text-right">{{ $fmt($horasBloqueDeclaradasAsignadas) }} / {{ $fmt($horasBloqueDeclaradas) }}</td>
            <td>Total de horas asignadas / declaradas por el establecimiento.</td>
        </tr>
        <tr>
            <td>Otras funciones declaradas</td>
            <td class="text-right">{{ $fmt($desgloseContratoBloque['otras_funciones_declaradas_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoBloque['otras_funciones_declaradas'] ?? 0) }}</td>
            <td>Horas asignadas / declaradas fuera de los bloques normativos.</td>
        </tr>
        <tr class="total-row">
            <td>Contrato bloque dotación</td>
            <td class="text-right">{{ $fmt($resumen['horas_dotacion_funciones'] ?? 0) }}</td>
            <td>Suma de directivos, técnico-pedagógicas normativas y declaradas, planes, otras funciones y eventuales horas PIE declaradas; excluye las horas PIE automáticas normativas.</td>
        </tr>
        <tr class="total-row">
            <td>Horas que debiesen contratarse</td>
            <td class="text-right">{{ $fmt($horasContratoRequeridas) }}</td>
            <td>Contrato equivalente plan + trabajo colaborativo PIE + contrato bloque dotación + horas de contrato PIE necesarias.</td>
        </tr>
        <tr>
            <td>Horas contrato aula</td>
            <td class="text-right">{{ $fmt($horasContratoAula) }}</td>
            <td>Base contractual docente vigente menos las horas asignadas a Coordinación PIE y Bolsa Educadoras Diferenciales PIE.</td>
        </tr>
        <tr>
            <td>Horas contrato docente PIE</td>
            <td class="text-right primary">{{ $fmt($horasContratoDocentePie) }}</td>
            <td>Coordinación PIE: {{ $fmt($horasContratoCoordinacionPie) }} h + Bolsa Educadoras Diferenciales PIE: {{ $fmt($horasContratoEducadorasDiferenciales) }} h.</td>
        </tr>
        <tr class="total-row">
            <td>Horas contrato docentes vigentes</td>
            <td class="text-right">{{ $fmt($horasContratoActuales) }}</td>
            <td>Base contractual docente vigente del establecimiento.</td>
        </tr>
        <tr class="total-row">
            <td>Diferencia</td>
            <td class="text-right {{ $sobredotacion > 0.01 ? 'danger' : ($horasPorContratar > 0.01 ? 'success' : 'primary') }}">{{ $fmt($brechaValor) }}</td>
            <td>{{ $brechaTexto }}</td>
        </tr>
    </tbody>
</table>

@if (!empty($alertas))
    <div class="note avoid-break"><strong>Alertas de consolidación:</strong>
        <ul style="margin: 4px 0 0 14px; padding: 0;">
            @foreach ($alertas as $alerta)
                <li>{{ $alerta }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="section-title">Cantidad de cursos por nivel</div>
<table>
    <thead>
        <tr>
            <th>Nivel</th>
            <th class="text-right">Matrícula</th>
            <th class="text-right">Cursos</th>
            <th class="text-right">Horas plan por curso</th>
            <th class="text-right">Total horas plan</th>
            <th>Proporción</th>
            <th class="text-right">Contrato equiv.</th>
            <th class="text-right">Trabajo colab. PIE</th>
            <th class="text-right">Contrato + colab.</th>
            <th>Fuente / alerta</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($cursos['grupos'] ?? []) as $grupo)
            <tr class="group-row"><td colspan="10">{{ $grupo['label'] ?? 'Grupo' }}</td></tr>
            @foreach (($grupo['niveles'] ?? []) as $nivelKey)
                @php $row = $cursos['rows'][$nivelKey] ?? null; @endphp
                @continue(!$row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-right">{{ number_format((int) ($row['matricula'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((int) ($row['cursos'] ?? 0), 0, ',', '.') }}</td>
                    <td class="text-right">{{ ($row['horas_por_nivel'] ?? null) !== null ? $fmt($row['horas_por_nivel']) : 'Variable' }}</td>
                    <td class="text-right primary">{{ $fmt($row['total_horas'] ?? 0) }}</td>
                    <td><span class="badge badge-gray">{{ $row['proporcion_docente_label'] ?? '—' }}</span></td>
                    <td class="text-right primary">{{ $fmt($row['total_horas_contrato_equivalente'] ?? 0) }}</td>
                    <td class="text-right success">{{ $fmt($row['total_trabajo_colaborativo_pie'] ?? 0) }}</td>
                    <td class="text-right primary">{{ $fmt($row['total_contrato_mas_trabajo_colaborativo_pie'] ?? 0) }}</td>
                    <td class="small">{{ ((int) ($row['sin_horas_plan'] ?? 0) > 0) ? 'Revisar plan' : 'Plan asociado' }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total {{ $grupo['label'] ?? 'grupo' }}</td>
                <td class="text-right">{{ number_format((int) ($grupo['totales']['matricula'] ?? 0), 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format((int) ($grupo['totales']['cursos'] ?? 0), 0, ',', '.') }}</td>
                <td></td>
                <td class="text-right primary">{{ $fmt($grupo['totales']['horas'] ?? 0) }}</td>
                <td></td>
                <td class="text-right primary">{{ $fmt($grupo['totales']['horas_contrato_equivalente'] ?? 0) }}</td>
                <td class="text-right success">{{ $fmt($grupo['totales']['trabajo_colaborativo_pie'] ?? 0) }}</td>
                <td class="text-right primary">{{ $fmt($grupo['totales']['contrato_mas_trabajo_colaborativo_pie'] ?? 0) }}</td>
                <td></td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>Total establecimiento</td>
            <td class="text-right">{{ number_format((int) ($cursos['totales']['matricula'] ?? 0), 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format((int) ($cursos['totales']['cursos'] ?? 0), 0, ',', '.') }}</td>
            <td></td>
            <td class="text-right primary">{{ $fmt($cursos['totales']['horas'] ?? 0) }}</td>
            <td></td>
            <td class="text-right primary">{{ $fmt($cursos['totales']['horas_contrato_equivalente'] ?? 0) }}</td>
            <td class="text-right success">{{ $fmt($cursos['totales']['trabajo_colaborativo_pie'] ?? 0) }}</td>
            <td class="text-right primary">{{ $fmt($cursos['totales']['contrato_mas_trabajo_colaborativo_pie'] ?? 0) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="section-title">Resumen por bloque de dotación</div>
<table>
    <thead>
        <tr>
            <th>Bloque</th>
            <th class="text-right">Normativas</th>
            <th class="text-right">Declaradas/aprobadas</th>
            <th class="text-right">Total</th>
            <th>Detalle principal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($bloquesInformeDotacion as $bloque)
            <tr>
                <td><strong>{{ $bloque['label'] }}</strong></td>
                <td class="text-right">{{ $fmt($bloque['automaticas'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($bloque['declaradas'] ?? 0) }}</td>
                <td class="text-right primary">{{ $fmt($bloque['total'] ?? 0) }}</td>
                <td class="small">
                    @php
                        $detalleBloque = collect(array_slice($bloque['items'] ?? [], 0, 6))
                            ->map(fn ($item) => (string) ($item['nombre'] ?? 'Ítem').': '.$fmt($item['horas'] ?? 0))
                            ->implode(' | ');
                    @endphp
                    {{ $detalleBloque !== '' ? $detalleBloque : 'Sin registros.' }}
                </td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>Total bloque de dotación</td>
            <td class="text-right">{{ $fmt($totalAutomaticas) }}</td>
            <td class="text-right">{{ $fmt($totalDeclaradas) }}</td>
            <td class="text-right primary">{{ $fmt($totalBloquesDotacion) }}</td>
            <td>Total de contrato considerado en funciones directivas, técnico-pedagógicas, planes, otras funciones y eventuales horas PIE declaradas. Las horas PIE automáticas normativas se informan por separado.</td>
        </tr>
    </tbody>
</table>

<div class="page-break"></div>
<div class="section-title">Consolidado de horas por asignatura</div>
<div class="note avoid-break">
    Las horas aula se agrupan por asignatura en todo el establecimiento. La equivalencia contractual asignada se consolida por docente y proporción; las horas titulares consideran únicamente contratos identificados como Titular o Planta. La cobertura realizada por asistentes de la educación se informa en columnas separadas y utiliza las horas de contrato AAEE registradas manualmente.
</div>
<table>
    <thead>
        <tr>
            <th style="width: 15%;">Asignatura</th>
            <th style="width: 11%;">Proporción / origen</th>
            <th class="text-right" style="width: 6%;">Aula plan</th>
            <th class="text-right" style="width: 7%;">Aula asignada</th>
            <th class="text-right" style="width: 8%;">Contrato requerido</th>
            <th class="text-right" style="width: 8%;">Contrato asignado</th>
            <th class="text-right" style="width: 7%;">Aula titulares</th>
            <th class="text-right" style="width: 8%;">Contrato titulares</th>
            <th class="text-right" style="width: 7%;">Aula AAEE</th>
            <th class="text-right" style="width: 8%;">Contrato AAEE</th>
            <th class="text-right" style="width: 6%;">Saldo aula</th>
            <th class="text-right" style="width: 7%;">Saldo contrato</th>
            <th style="width: 8%;">Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($asignaturasResumenItems as $asignaturaResumen)
            @php
                $saldoAulaAsignatura = (float) data_get($asignaturaResumen, 'saldo_aula', 0);
                $saldoContratoAsignatura = (float) data_get($asignaturaResumen, 'saldo_contrato', 0);
            @endphp
            <tr>
                <td><strong>{{ data_get($asignaturaResumen, 'asignatura', 'Asignatura') }}</strong></td>
                <td>
                    {{ collect(data_get($asignaturaResumen, 'proporciones', []))->implode(', ') ?: '—' }}
                    <br><span class="small muted">{{ collect(data_get($asignaturaResumen, 'origenes_proporcion', []))->implode(', ') ?: 'Regla general' }}</span>
                </td>
                <td class="text-right">{{ $fmt(data_get($asignaturaResumen, 'horas_aula_plan', 0)) }}</td>
                <td class="text-right primary">{{ $fmt(data_get($asignaturaResumen, 'horas_aula_asignadas', 0)) }}</td>
                <td class="text-right">{{ $fmt(data_get($asignaturaResumen, 'horas_contrato_requeridas_total', 0)) }}</td>
                <td class="text-right">{{ $fmt(data_get($asignaturaResumen, 'horas_contrato_asignadas_total', 0)) }}</td>
                <td class="text-right success">{{ $fmt(data_get($asignaturaResumen, 'horas_aula_titulares', 0)) }}</td>
                <td class="text-right success">{{ $fmt(data_get($asignaturaResumen, 'horas_contrato_titulares', 0)) }}</td>
                <td class="text-right primary">{{ $fmt(data_get($asignaturaResumen, 'horas_aula_asistentes', 0)) }}</td>
                <td class="text-right primary">{{ $fmt(data_get($asignaturaResumen, 'horas_contrato_asistentes', 0)) }}</td>
                <td class="text-right {{ $saldoAulaAsignatura > 0.01 ? 'danger' : ($saldoAulaAsignatura < -0.01 ? 'danger' : 'success') }}">{{ $fmtSigned($saldoAulaAsignatura) }}</td>
                <td class="text-right {{ $saldoContratoAsignatura > 0.01 ? 'danger' : ($saldoContratoAsignatura < -0.01 ? 'danger' : 'success') }}">{{ $fmtSigned($saldoContratoAsignatura) }}</td>
                <td><span class="badge badge-gray">{{ data_get($asignaturaResumen, 'estado.label', 'Sin estado') }}</span></td>
            </tr>
        @empty
            <tr><td colspan="13" class="text-center muted">No existen asignaturas configuradas para consolidar.</td></tr>
        @endforelse
        @if ($asignaturasResumenItems->isNotEmpty())
            <tr class="total-row">
                <td colspan="2">Total establecimiento</td>
                <td class="text-right">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_aula_plan', 0)) }}</td>
                <td class="text-right primary">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_aula_asignadas', 0)) }}</td>
                <td class="text-right">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_contrato_requeridas', 0)) }}</td>
                <td class="text-right">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_contrato_asignadas', 0)) }}</td>
                <td class="text-right success">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_aula_titulares', 0)) }}</td>
                <td class="text-right success">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_contrato_titulares', 0)) }}</td>
                <td class="text-right primary">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_aula_asistentes', 0)) }}</td>
                <td class="text-right primary">{{ $fmt(data_get($asignaturasResumenTotales, 'horas_contrato_asistentes', 0)) }}</td>
                <td class="text-right">{{ $fmtSigned((float) data_get($asignaturasResumenTotales, 'saldo_aula', 0)) }}</td>
                <td class="text-right">{{ $fmtSigned((float) data_get($asignaturasResumenTotales, 'saldo_contrato', 0)) }}</td>
                <td>{{ number_format((float) data_get($asignaturasResumenTotales, 'porcentaje_cobertura_titular', 0), 1, ',', '.') }}% titular</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="page-break"></div>
<div class="section-title">Asignación de horas aula por asignatura</div>
<div class="note avoid-break">
    Esta sección utiliza exclusivamente horas aula pedagógicas. La conversión docente a horas de contrato se presenta posteriormente en la cuadratura individual. Para asistentes de la educación se muestran las horas de contrato AAEE registradas manualmente.
</div>
<table>
    <thead>
        <tr>
            <th style="width: 13%;">Curso / sección</th>
            <th style="width: 20%;">Asignatura</th>
            <th style="width: 19%;">Bloque / origen</th>
            <th style="width: 15%;">Proporción / origen</th>
            <th class="text-right" style="width: 7%;">Horas aula plan</th>
            <th class="text-right" style="width: 9%;">Horas aula asignadas</th>
            <th class="text-right" style="width: 8%;">Saldo aula</th>
            <th style="width: 15%;">Personal asignado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($necesidadesPlan as $necesidad)
            @php
                $requeridasAula = (float) data_get($necesidad, 'horas_plan_requeridas', 0);
                $asignadasAula = (float) data_get($necesidad, 'horas_plan_asignadas', 0);
                $saldoAula = max(0, round($requeridasAula - $asignadasAula, 2));
                $asignacionesAula = collect(data_get($necesidad, 'asignaciones', []));
                $docentesAsignados = $asignacionesAula
                    ->map(function ($asignacionAula) use ($fmt) {
                        $nombre = trim((string) data_get($asignacionAula, 'docente_nombre', 'Personal'));
                        $horas = $fmt(data_get($asignacionAula, 'horas_plan_pedagogicas', 0));
                        $esAsistente = data_get($asignacionAula, 'estamento_cobertura', 'docente') === 'asistente';
                        $detalle = $nombre.': '.$horas.' h aula · '.($esAsistente ? 'Asistente' : 'Docente');
                        if ($esAsistente) {
                            $detalle .= ' · '.$fmt(data_get($asignacionAula, 'horas_contrato', 0)).' h contrato AAEE';
                        }
                        return $nombre !== '' ? $detalle : null;
                    })
                    ->filter()
                    ->implode(' | ');
            @endphp
            <tr>
                <td>{{ data_get($necesidad, 'curso_label', '—') }}</td>
                <td><strong>{{ data_get($necesidad, 'titulo', 'Asignatura') }}</strong></td>
                <td class="small">{{ data_get($necesidad, 'bloque') ?: data_get($necesidad, 'fuente', '—') }}</td>
                <td>
                    <span class="badge badge-gray">{{ data_get($necesidad, 'proporcion', '—') }}</span>
                    <br><span class="small muted">{{ data_get($necesidad, 'origen_proporcion_label', 'Regla general') }}</span>
                </td>
                <td class="text-right">{{ $fmt($requeridasAula) }}</td>
                <td class="text-right primary">{{ $fmt($asignadasAula) }}</td>
                <td class="text-right {{ $saldoAula > 0.01 ? 'danger' : 'success' }}">{{ $fmt($saldoAula) }}</td>
                <td class="small">{{ $docentesAsignados !== '' ? $docentesAsignados : 'Sin asignación.' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center muted">No existen asignaturas de plan configuradas para este establecimiento y año.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="page-break"></div>
<div class="section-title">Cuadratura contractual por docente</div>
<div class="note avoid-break">
    Las horas aula corresponden a la asignación real por asignatura. Las columnas 65/35 y 60/40 muestran su equivalencia contractual consolidada; las funciones se mantienen como horas de contrato directas.
</div>
<table>
    <thead>
        <tr>
            <th style="width: 8%;">RUT</th>
            <th style="width: 21%;">Docente</th>
            <th class="text-right" style="width: 8%;">Contrato vigente</th>
            <th class="text-right" style="width: 8%;">Horas aula</th>
            <th class="text-right" style="width: 8%;">Contrato 65/35</th>
            <th class="text-right" style="width: 8%;">Contrato 60/40</th>
            <th class="text-right" style="width: 8%;">Contrato especial</th>
            <th class="text-right" style="width: 8%;">Funciones</th>
            <th class="text-right" style="width: 8%;">Total calculado</th>
            <th class="text-right" style="width: 7%;">Diferencia</th>
            <th style="width: 8%;">Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($docentes as $docente)
            @php
                $estado = $docente['estado_cuadratura'] ?? ['label' => 'Sin estado'];
                $diferencia = $docente['diferencia'] ?? null;
            @endphp
            <tr>
                <td>{{ $docente['rut'] }}</td>
                <td><strong>{{ $docente['nombre'] }}</strong><br><span class="muted small">{{ $docente['funcion'] }} · {{ $docente['titulo'] }}</span></td>
                <td class="text-right"><strong>{{ $fmt($docente['horas_contrato'] ?? 0) }}</strong></td>
                <td class="text-right primary">{{ $fmt($docente['horas_aula'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($docente['horas_contrato_65_35'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($docente['horas_contrato_60_40'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($docente['horas_contrato_especial'] ?? 0) }}</td>
                <td class="text-right">{{ $fmt($docente['horas_funciones_total'] ?? 0) }}</td>
                <td class="text-right primary">{{ $fmt($docente['horas_asignadas_total'] ?? 0) }}</td>
                <td class="text-right {{ is_numeric($diferencia) && (float) $diferencia < -0.01 ? 'danger' : (is_numeric($diferencia) && (float) $diferencia > 0.01 ? 'success' : 'primary') }}">{{ is_numeric($diferencia) ? $fmt(abs((float) $diferencia)) : '—' }}</td>
                <td><span class="badge badge-gray">{{ $estado['label'] ?? 'Sin estado' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="11" class="text-center muted">No se encontraron docentes vigentes para este establecimiento y año.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Criterios de cálculo aplicados</div>
<div class="note">
    <ul style="margin: 0 0 0 14px; padding: 0;">
        <li>El informe excluye establecimientos de sala cuna desde el módulo de Dotación Establecimiento.</li>
        <li>La asignación de asignaturas se controla en horas aula pedagógicas, sin mostrar contrato requerido en la distribución por asignatura.</li>
        <li>Las equivalencias 65/35 y 60/40 se calculan sobre el total consolidado de horas aula asignadas a cada docente.</li>
        <li>Para cursos independientes, el contrato equivalente del plan se calcula por curso. En cursos combinados, primero se consolidan las horas aula según la modalidad configurada y luego se aplica la proporción contractual del grupo.</li>
        <li>NT1 y NT2 aplican regla especial de conversión contractual según régimen JEC o sin JEC.</li>
        <li>El trabajo colaborativo PIE considera 3 horas por curso con estudiantes NEE.</li>
        <li>Las horas PROF EDUC. DIF se convierten a horas de contrato según la proporción 65/35 o 60/40 de cada curso; la bolsa total se redondea una sola vez hacia arriba.</li>
        <li>Las funciones directivas, técnico-pedagógicas, PIE, planes y otras funciones provienen del módulo Dotación funciones y planes.</li>
        <li>Las asignaturas y funciones pueden ser cubiertas por asistentes de la educación. Estas horas se identifican separadamente y no se someten a la proporción docente 65/35 o 60/40.</li>
    </ul>
</div>

<div class="footer">Informe generado por {{ $platformName }} - {{ $generatedAt->format('d-m-Y H:i') }}</div>
</body>
</html>
