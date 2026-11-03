<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Detalle sobredotación - RBD {{ $establecimiento->rbd }} - {{ $anio }}</title>
    <style>
        @page { margin: 102pt 28pt 34pt; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.4; color: #0f172a; }
        .report-header { position: fixed; top: -82pt; left: 0; right: 0; border-bottom: 2pt solid #0b3d91; padding-bottom: 6pt; }
        h1 { margin: 0 0 3pt; font-size: 14pt; color: #0b3d91; }
        h2 { font-size: 13pt; color: #0b3d91; margin: 0 0 9pt; }
        h3 { font-size: 10.5pt; color: #0b3d91; margin: 14pt 0 6pt; page-break-after: avoid; }
        .meta, .secondary { color: #475569; font-size: 8pt; }
        .block { page-break-before: always; }
        .notice { margin: 0 0 10pt; padding: 8pt; border: .7pt solid #c9dcf5; background: #eef6ff; color: #0d47a1; font-size: 8.5pt; }
        .warning { border-color: #f3d99c; background: #fff8e1; color: #8a4b00; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5pt; margin-bottom: 9pt; }
        thead { display: table-header-group; }
        thead tr { page-break-after: avoid; }
        tr { page-break-inside: avoid; }
        th, td { border: .5pt solid #dbe4f0; padding: 6pt 5pt; vertical-align: top; word-wrap: break-word; }
        th { text-align: left; color: #0b3d91; background: #eef6ff; font-size: 7.5pt; line-height: 1.25; }
        .num { text-align: right; }
        .total td { background: #eef6ff; font-weight: bold; }
        .person { font-weight: bold; }
        .detail-title { color: #0b3d91; font-weight: bold; margin: 10pt 0 4pt; }
        .keep-next { page-break-after: avoid; }
        .text { white-space: pre-wrap; word-wrap: break-word; margin: 3pt 0 9pt; }
        .empty { color: #64748b; padding: 8pt; border: .5pt solid #dbe4f0; }
    </style>
</head>
<body>
@php
    $fmt = static fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $fecha = static function ($valor) {
        if (!$valor) { return '-'; }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $valor);
        return $fecha && $fecha->format('Y-m-d') === (string) $valor ? $fecha->format('d-m-Y') : (string) $valor;
    };
    $resumen = $informe['resumen'] ?? [];
    $camposResumen = [
        'docentes_analizados' => 'Docentes analizados', 'horas_dotacion_total' => 'Contrato Aula',
        'horas_asignadas_protegidas' => 'Asignaciones protegidas: plan, colaboración y normativa',
        'horas_declaradas_asignadas' => 'Funciones declaradas asignadas a docentes',
        'horas_declaradas_requeridas' => 'Funciones declaradas requeridas', 'horas_declaradas_pendientes' => 'Declaradas pendientes de cobertura docente',
        'horas_sobredotacion_total' => 'Contrato sin asignación registrada', 'horas_sobredotacion_planta' => 'Sin asignación Planta',
        'horas_sobredotacion_contrata' => 'Sin asignación Contrata', 'horas_universo_revision' => 'Universo sujeto a revisión',
        'horas_declaradas_titulares' => 'Declaradas asignadas: titulares', 'horas_declaradas_contrata' => 'Declaradas asignadas: contrata',
        'horas_declaradas_sin_cobertura' => 'Declaradas sin cobertura contractual', 'horas_sobreasignadas' => 'Horas sobreasignadas',
        'horas_sobredotacion_protegida' => 'Saldo protegido fuera de revisión', 'horas_declaradas_protegidas' => 'Declaradas protegidas fuera de revisión',
    ];
    $camposConciliacion = ['contrato' => 'Contrato docente vigente', 'asignadas' => 'Asignadas a docentes', 'reservadas' => 'Reservadas para otras funciones',
        'saldo_neto' => 'Saldo contractual neto', 'saldo_nomina' => 'Saldo de la nómina revisable', 'maximo' => 'Máximo autorizado',
        'diferencia_maximo' => 'Diferencia contractual respecto del máximo', 'plan_individual' => 'Plan y trabajo colaborativo PIE: conversión por docente',
        'plan_institucional' => 'Plan y trabajo colaborativo PIE: necesidad consolidada por curso', 'diferencia_plan' => 'Diferencia entre las bases del plan',
        'cobertura_aaee' => 'Cobertura AAEE sin consumir contrato docente', 'otros_ajustes' => 'Otros ajustes de cobertura y margen autorizado'];
@endphp
<header class="report-header">
    <h1>Detalle completo de sobredotación - Dotación {{ $anio }}</h1>
    <div class="meta">RBD {{ $establecimiento->rbd }} - {{ $establecimiento->nombre_establecimiento }} - {{ $establecimiento->comuna }}</div>
    <div class="meta">Fuente contractual: {{ $informe['periodos_padron'] ?? 'Período no informado' }} - Generado: {{ now()->format('d-m-Y H:i') }}</div>
</header>
<h2>Resumen del establecimiento</h2>
<p class="notice">Informe completo de la pestaña Detalle sobredotación del establecimiento y año seleccionados, sin limitarse a los filtros de revisión de pantalla. Las nóminas conservan la prelación del SGA. Las horas son contractuales salvo donde se indica aula; los saldos de Etapa 7 son propuestas en revisión.</p>
@php($brecha = (float) ($resumen['brecha_estructural'] ?? 0))
<p class="detail-title">{{ $brecha < -0.01 ? 'Sobredotación estructural' : ($brecha > 0.01 ? 'Horas estructuralmente necesarias' : 'Dotación estructural cuadrada') }}: {{ $fmt(abs($brecha)) }} h</p>
<table>
    <thead><tr><th style="width:39%">Indicador general de Aula</th><th style="width:11%" class="num">Cantidad</th><th style="width:39%">Indicador general de Aula</th><th style="width:11%" class="num">Cantidad</th></tr></thead>
    <tbody>
    @foreach(array_chunk($camposResumen, 2, true) as $campos)
        <tr>@foreach($campos as $campo => $label)<td>{{ $label }}</td><td class="num">{{ $fmt($resumen[$campo] ?? 0) }}{{ $campo === 'docentes_analizados' ? '' : ' h' }}</td>@endforeach</tr>
    @endforeach
    </tbody>
</table>
<p class="secondary">Base de la brecha estructural: plan y trabajo colaborativo PIE {{ $fmt($informe['formula']['contrato_plan_pie'] ?? 0) }} h + funciones normativas {{ $fmt($informe['formula']['bloque_normativo'] ?? 0) }} h - contrato Aula general {{ $fmt($informe['formula']['contrato_aula'] ?? 0) }} h. Las funciones declaradas se revisan por separado.</p>
<p class="secondary">El saldo neto puede compensar sobreasignaciones; las vacantes revisables son individuales y excluyen contratos protegidos. Las reservas se descuentan una sola vez. Los contratos originales, considerados y saldos generales de una persona pueden repetirse entre bloques y no se suman entre secciones.</p>
@if(!empty($informe['conciliacion']))
    <h3>Conciliación de horas de contrato - Plan general y funciones</h3>
    <table><thead><tr><th style="width:78%">Concepto</th><th style="width:22%" class="num">Horas</th></tr></thead><tbody>
    @foreach($camposConciliacion as $campo => $label)<tr><td>{{ $label }}</td><td class="num">{{ $fmt($informe['conciliacion'][$campo] ?? null) }}</td></tr>@endforeach
    </tbody></table>
    <p class="secondary">Contrato vigente = asignadas + reservadas + saldo neto. La conciliación mantiene separados el máximo autorizado, la conversión por docente, la necesidad por curso y la cobertura AAEE; estas últimas horas no consumen contrato docente.</p>
@endif
@foreach($informe['bloques'] as $clave => $bloque)
<section class="block">
    <h2>{{ $bloque['titulo'] }}</h2>
    @if($bloque['cuadratura'])
        <h3>Contrato y ocupación del bloque</h3>
        <table><thead><tr><th style="width:39%">{{ $bloque['titulo'] }} - Cuadratura</th><th style="width:11%" class="num">Horas</th><th style="width:39%">Concepto</th><th style="width:11%" class="num">Horas</th></tr></thead><tbody>
        @foreach(array_chunk($bloque['cuadratura'], 2, true) as $campos)
            <tr>
                @foreach($campos as $label => $valor)<td>{{ $label }}</td><td class="num">{{ $fmt($valor) }}</td>@endforeach
                @if(count($campos) === 1)<td></td><td></td>@endif
            </tr>
        @endforeach
        </tbody></table>
    @endif
    <h3>Contrato sin asignación registrada - {{ $bloque['filas']->count() }} docente(s)</h3>
    <p class="secondary keep-next">Saldos del bloque en orden de prelación. Titular corresponde a horas sujetas a supresión; contrata a horas sujetas a no renovación. Los contratos con fuero maternal u horas gremiales se informan por separado.</p>
    <table>
        <thead><tr><th style="width:22%">{{ $bloque['titulo'] }} - Docente</th><th style="width:25%">Antecedentes y prelación</th><th style="width:23%">Contrato individual (h)</th><th style="width:8%" class="num">Contrato del bloque (h)</th><th style="width:8%" class="num">Saldo del bloque (h)</th><th style="width:7%" class="num">Titular (h)</th><th style="width:7%" class="num">Contrata (h)</th></tr></thead>
        <tbody>
        @forelse($bloque['filas'] as $fila)
            <tr>
                <td><div class="person">{{ $loop->iteration }}. {{ $fila['nombre'] }}</div><div>{{ $fila['rut'] }}</div><div class="secondary">{{ $fila['tipo_contrato'] }}<br>{{ $fila['funcion'] }}</div></td>
                <td>{{ $fila['titulo'] }}<br><strong>{{ $fila['prelacion'] }}</strong><br>Tramo: {{ $fila['tramo'] ?: '-' }}<br>Antigüedad: {{ $fecha($fila['fecha_antiguedad']) }}</td>
                <td>
                    Original: <strong>{{ $fmt($fila['contrato_original']) }}</strong> | Considerado: <strong>{{ $fmt($fila['contrato_considerado']) }}</strong><br>
                    Aula asignada: <strong>{{ $fmt($fila['aula_asignada']) }}</strong> | Contrato asignado: <strong>{{ $fmt($fila['contrato_asignado']) }}</strong><br>
                    Reservadas: <strong>{{ $fmt($fila['reservadas']) }}</strong> | Saldo general: <strong>{{ $fmt($fila['pendientes']) }}</strong><br>
                    Justificación vigente del bloque:<br>Con: {{ $fmt($fila['con_justificacion']) }} | Sin: {{ $fmt($fila['sin_justificacion']) }}
                </td>
                @foreach(['contrato_bloque', 'saldo_bloque', 'horas_titular', 'horas_contrata'] as $campo)<td class="num">{{ $fmt($fila[$campo]) }}</td>@endforeach
            </tr>
        @empty<tr><td colspan="7">No hay horas contractuales sin asignación en este bloque.</td></tr>@endforelse
            <tr class="total"><td colspan="3">Total {{ $bloque['titulo'] }}</td>@foreach(['contrato_bloque', 'saldo_bloque', 'horas_titular', 'horas_contrata'] as $campo)<td class="num">{{ $fmt($bloque['totales'][$campo]) }}</td>@endforeach</tr>
        </tbody>
    </table>
    <p class="secondary">Horas con justificación vigente: {{ $fmt($bloque['totales']['con_justificacion']) }}. Horas sin justificación vigente: {{ $fmt($bloque['totales']['sin_justificacion']) }}.</p>
    <h3>Justificaciones completas - {{ $bloque['titulo'] }}</h3>
    @if(!$informe['justificaciones_disponibles'])<p class="notice warning">La tabla de justificaciones no está disponible. Los saldos se informan sin justificación vigente.</p>@endif
    @forelse($bloque['filas'] as $fila)
        @foreach(['titular' => 'Supresión de horas titulares', 'contrata' => 'No renovación de horas a contrata'] as $tipo => $label)
            @if($fila['horas_'.$tipo] > 0.01)
                <p class="detail-title {{ $fila['estado_'.$tipo] !== 'Sin justificación' ? 'keep-next' : '' }}">{{ $fila['nombre'] }} - {{ $fila['rut'] }} - {{ $label }}: {{ $fmt($fila['horas_'.$tipo]) }} h - {{ str_starts_with($fila['estado_'.$tipo], 'Desactualizada') ? 'Desactualizada' : $fila['estado_'.$tipo] }}</p>
                @if($fila['horas_registradas_'.$tipo] !== null || $fila['actualizada_'.$tipo])
                <p class="secondary keep-next">
                    @if($fila['horas_registradas_'.$tipo] !== null)
                        - Registrada para {{ $fmt($fila['horas_registradas_'.$tipo]) }} h
                    @endif
                    @if($fila['actualizada_'.$tipo])
                        - Actualizada: {{ $fila['actualizada_'.$tipo] }}
                    @endif
                </p>
                @endif
                @if($fila['estado_'.$tipo] !== 'Sin justificación')
                    <p class="text">{{ $fila['justificacion_'.$tipo] }}</p>
                @endif
            @endif
        @endforeach
    @empty<p class="empty">No hay justificaciones asociadas a saldos actuales en este bloque.</p>@endforelse
    <h3>Justificaciones fuera de la nómina de saldos - {{ $bloque['titulo'] }}</h3>
    @forelse($bloque['justificaciones_fuera'] as $registro)
        <p class="detail-title keep-next">{{ $registro['nombre'] }} - {{ $registro['rut'] }} - {{ $registro['tipo'] === 'titular' ? 'Titular' : 'Contrata' }}</p>
        <p class="secondary keep-next">Sin saldo actual de esta calidad. Registrada para {{ $fmt($registro['horas']) }} h. Actualizada: {{ $registro['actualizada'] ?: '-' }}. Puede mantener la exclusión de otras funciones hasta su eliminación en el SGA.</p>
        <p class="text">{{ trim($registro['texto']) !== '' ? $registro['texto'] : 'Sin justificación' }}</p>
    @empty<p class="empty">No hay justificaciones fuera de la nómina de saldos en este bloque.</p>@endforelse
    <h3>Contratos protegidos por situación docente - {{ $bloque['titulo'] }}</h3>
    @if($bloque['protegidos']->isEmpty())
        <p class="empty">No hay contratos protegidos en este bloque.</p>
    @else
    <p class="secondary keep-next">Información del contrato general de la persona, excluida de propuestas de reducción; puede repetirse si aporta a varios bloques.</p>
    <table><thead><tr><th style="width:30%">{{ $bloque['titulo'] }} - Docente</th><th style="width:35%">Situación y antecedentes</th><th style="width:17.5%" class="num">Contrato original protegido (h)</th><th style="width:17.5%" class="num">Horas necesarias contabilizadas (h)</th></tr></thead><tbody>
    @forelse($bloque['protegidos'] as $p)<tr><td><strong>{{ $p['nombre'] }}</strong><br>{{ $p['rut'] }}</td><td>{{ $p['motivo_proteccion'] }}<br>{{ $p['titulo'] ?? '' }}<br>{{ $p['prelacion'] ?? '' }}</td><td class="num">{{ $fmt($p['contrato_original']) }}</td><td class="num">{{ $fmt($p['contrato_considerado']) }}</td></tr>
    @empty<tr><td colspan="4">No hay contratos protegidos en este bloque.</td></tr>@endforelse
    </tbody></table>
    @endif
    <h3>Docentes con sobreasignación contractual - {{ $bloque['titulo'] }}</h3>
    @if($bloque['sobrecargas']->isEmpty())
        <p class="empty">No hay docentes con sobreasignación contractual en este bloque.</p>
    @else
    <p class="secondary keep-next">Ocupación individual, incluidas reservas y redondeo NT; no se compensa con el saldo de otra persona. Los importes generales pueden repetirse entre bloques.</p>
    <table><thead><tr><th style="width:40%">{{ $bloque['titulo'] }} - Docente</th><th style="width:20%" class="num">Contrato considerado (h)</th><th style="width:20%" class="num">Ocupado (h)</th><th style="width:20%" class="num">Exceso (h)</th></tr></thead><tbody>
    @forelse($bloque['sobrecargas'] as $p)
        @php($ocupado = round((float) $p['horas_asignadas_total'] + (float) ($p['redondeo_parvularia'] ?? 0), 2))
        <tr><td><strong>{{ $p['nombre'] }}</strong><br>{{ $p['rut'] }}<br>{{ $p['titulo'] ?? '' }}</td><td class="num">{{ $fmt($p['horas_contrato']) }}</td><td class="num">{{ $fmt($ocupado) }}</td><td class="num">{{ $fmt($ocupado - $p['horas_contrato']) }}</td></tr>
    @empty<tr><td colspan="4">No hay docentes con sobreasignación contractual en este bloque.</td></tr>@endforelse
    </tbody></table>
    @endif
    <h3>Funciones declaradas asignadas a docentes (revisables) - {{ $bloque['titulo'] }}</h3>
    @if($bloque['ajustes']->isEmpty())
        <p class="empty">No existen funciones declaradas asignadas susceptibles de ajuste en este bloque.</p>
    @else
    <p class="secondary keep-next">Se agrupan una sola vez por el bloque del perfil docente. Se conservan las cifras individuales de la base Aula de la pestaña; no son nuevas horas vacantes ni bajas ejecutadas.</p>
    <table><thead><tr><th style="width:25%">{{ $bloque['titulo'] }} - Docente</th><th style="width:10%" class="num">Contrato Aula</th><th style="width:9%" class="num">Protegidas</th><th style="width:10%" class="num">Declaradas asignadas</th><th style="width:9%" class="num">Titulares</th><th style="width:9%" class="num">Contrata</th><th style="width:9%" class="num">Sin cobertura</th><th style="width:10%" class="num">Sin asignación</th><th style="width:9%" class="num">Sobreasignadas</th></tr></thead><tbody>
    @forelse($bloque['ajustes'] as $p)<tr><td><strong>{{ $p['nombre'] }}</strong><br>{{ $p['rut'] }} - {{ $p['tipo_contrato'] }}<br>{{ $p['funcion'] }}</td>@foreach(['horas_contrato_categoria', 'horas_asignadas_protegidas', 'horas_declaradas_ajustables', 'horas_declaradas_titulares', 'horas_declaradas_contrata', 'horas_declaradas_sin_cobertura', 'horas_sobredotacion_total', 'horas_sobreasignadas'] as $campo)<td class="num">{{ $fmt($p[$campo]) }}</td>@endforeach</tr>
    @empty<tr><td colspan="9">No existen funciones declaradas asignadas susceptibles de ajuste en este bloque.</td></tr>@endforelse
    </tbody></table>
    @foreach($bloque['ajustes'] as $p)
        <p class="detail-title keep-next">Desglose de funciones - {{ $p['nombre'] }} - {{ $p['rut'] }}</p>
        <table><thead><tr><th style="width:20%">Tipo</th><th style="width:40%">Función o actividad - {{ $bloque['titulo'] }}</th><th style="width:15%">Subtipo</th><th style="width:15%">Subvención</th><th style="width:10%" class="num">Horas</th></tr></thead><tbody>
        @forelse($p['horas_declaradas_detalle'] ?? [] as $d)<tr><td>{{ $d['tipo_label'] }}</td><td>{{ $d['nombre'] }}</td><td>{{ $d['subtipo_label'] ?: '-' }}</td><td>{{ $d['subvencion'] ?: '-' }}</td><td class="num">{{ $fmt($d['horas']) }}</td></tr>@empty<tr><td colspan="5">No existe un desglose individual disponible para estas horas declaradas.</td></tr>@endforelse
        </tbody></table>
    @endforeach
    @endif
</section>
@endforeach
@if(($informe['otros_registros'] ?? collect())->isNotEmpty())
<section class="block"><h2>Otros registros históricos de justificación</h2>
@foreach($informe['otros_registros'] as $r)<p class="detail-title">{{ $r['nombre'] }} - {{ $r['rut'] }} - {{ $r['bloque'] }} / {{ $r['tipo'] }}</p><p class="secondary">Registrada para {{ $fmt($r['horas']) }} h. Actualizada: {{ $r['actualizada'] ?: '-' }}</p><p class="text">{{ $r['texto'] }}</p>@endforeach
</section>
@endif
</body>
</html>
