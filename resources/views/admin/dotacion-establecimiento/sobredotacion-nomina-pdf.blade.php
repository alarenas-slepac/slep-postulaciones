<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Horas contractuales vacantes - RBD {{ $establecimiento->rbd }} - {{ $anio }}</title>
    <style>
        @page { margin: 88pt 26pt 30pt; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.3pt; line-height: 1.25; color: #0f172a; }
        .header { position: fixed; top: -68pt; left: 0; right: 0; border-bottom: 2pt solid #0b3d91; padding-bottom: 5pt; }
        h1 { font-size: 12.5pt; color: #0b3d91; margin: 0 0 3pt; }
        h2 { font-size: 10.5pt; color: #0b3d91; background: #eef6ff; border-left: 3pt solid #0d6efd; padding: 6pt 7pt; margin: 13pt 0 7pt; page-break-after: avoid; }
        .meta, .note { color: #475569; font-size: 7.3pt; }
        .notice { border: .6pt solid #c9dcf5; background: #f8fbff; padding: 6pt; margin: 0 0 8pt; font-size: 7.6pt; }
        .warning { color: #8a4b00; background: #fff8e1; border-color: #f3d99c; }
        .summary { page-break-inside: avoid; page-break-after: avoid; padding-top: 5pt; border-top: .7pt solid #c9dcf5; }
        .identity { font-size: 9pt; font-weight: bold; color: #0b3d91; margin: 0 0 2pt; }
        .background { margin: 0 0 4pt; word-wrap: break-word; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; margin: 3pt 0; font-size: 8pt; }
        thead { display: table-header-group; }
        thead tr { page-break-after: avoid; }
        tr { page-break-inside: avoid; }
        th, td { width: 16.666%; border: .5pt solid #dbe4f0; padding: 3pt; vertical-align: top; word-wrap: break-word; }
        th { font-size: 7pt; font-weight: normal; text-align: center; color: #334155; background: #f8fafc; }
        td { text-align: center; font-weight: bold; }
        .block-hours th { color: #0b3d91; background: #eef6ff; }
        .justification-title { margin: 4pt 0 1pt; font-weight: bold; font-size: 7.8pt; page-break-before: avoid; }
        .keep-next { page-break-after: avoid; }
        .text { margin: 1pt 0 5pt; white-space: pre-wrap; word-wrap: break-word; orphans: 2; widows: 2; }
        .totals { page-break-inside: avoid; margin-top: 7pt; }
        .totals p { margin: 4pt 0; font-weight: bold; color: #0b3d91; }
        .empty { border: .5pt solid #dbe4f0; color: #64748b; padding: 6pt; margin: 0 0 6pt; }
        .definitions { margin-top: 10pt; padding-top: 6pt; border-top: 1pt solid #c9dcf5; font-size: 7pt; color: #475569; }
        .definitions p { margin: 3pt 0; }
    </style>
</head>
<body>
@php
    $fmt = static fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $fecha = static function ($v) {
        if (!$v) { return '-'; }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $v);
        return $d && $d->format('Y-m-d') === (string) $v ? $d->format('d-m-Y') : (string) $v;
    };
    $camposGenerales = ['contrato_original' => 'Contrato original', 'contrato_considerado' => 'Contrato considerado',
        'aula_asignada' => 'Aula asignada', 'contrato_asignado' => 'Contrato asignado', 'reservadas' => 'Reservadas', 'pendientes' => 'Saldo general'];
    $camposBloque = ['contrato_bloque' => 'Contrato del bloque', 'saldo_bloque' => 'Saldo del bloque', 'horas_titular' => 'Titular: supresión',
        'horas_contrata' => 'Contrata: no renovación', 'con_justificacion' => 'Con justificación vigente', 'sin_justificacion' => 'Sin justificación vigente'];
@endphp
<header class="header">
    <h1>Horas contractuales vacantes - Dotación {{ $anio }}</h1>
    <div class="meta">RBD {{ $establecimiento->rbd }} - {{ $establecimiento->nombre_establecimiento }} - {{ $establecimiento->comuna }}</div>
    <div class="meta">Fuente contractual: {{ $informe['periodos_padron'] ?? 'Período no informado' }} - Generado: {{ now()->format('d-m-Y H:i') }}</div>
</header>
<p class="notice">Contrato sin asignación registrada. Nómina completa del establecimiento y año seleccionados, en orden de prelación y con los mismos datos del Excel. Todas las cifras son horas; Aula asignada corresponde a horas aula. Etapa 7 informa horas en revisión.</p>
@if(!($informe['justificaciones_disponibles'] ?? false))
    <p class="notice warning">La tabla de justificaciones no está disponible. Los saldos se informan sin justificación vigente.</p>
@endif
@foreach($bloques as $clave => $bloque)
    <h2>{{ $bloque['titulo'] }} - {{ $bloque['filas']->count() }} docente(s) - {{ $fmt($bloque['totales']['saldo_bloque']) }} h sin asignación</h2>
    @forelse($bloque['filas'] as $fila)
        <div class="summary">
            <p class="identity">{{ $loop->iteration }}. {{ $fila['nombre'] }} - {{ $fila['rut'] }}</p>
            <p class="background">{{ $fila['titulo'] ?: 'Sin título declarado' }}<br>
                <strong>{{ $fila['prelacion'] }}</strong> | Tramo: {{ $fila['tramo'] ?: '-' }} | Antigüedad: {{ $fecha($fila['fecha_antiguedad']) }}
            </p>
            <table>
                <thead><tr>@foreach($camposGenerales as $etiqueta)<th>{{ $etiqueta }}</th>@endforeach</tr></thead>
                <tbody><tr>@foreach($camposGenerales as $campo => $etiqueta)<td>{{ $fmt($fila[$campo] ?? null) }}</td>@endforeach</tr></tbody>
            </table>
            <table class="block-hours">
                <thead><tr>@foreach($camposBloque as $etiqueta)<th>{{ $etiqueta }}</th>@endforeach</tr></thead>
                <tbody><tr>@foreach($camposBloque as $campo => $etiqueta)<td>{{ $fmt($fila[$campo] ?? null) }}</td>@endforeach</tr></tbody>
            </table>
        </div>
        @foreach(['titular' => 'Supresión titular', 'contrata' => 'No renovación contrata'] as $tipo => $etiqueta)
            @php
                $texto = (string) ($fila['justificacion_'.$tipo] ?? 'Sin justificación');
                $detalle = !in_array($texto, ['Sin justificación', 'No aplica', ''], true);
            @endphp
            <p class="justification-title {{ $detalle ? 'keep-next' : '' }}">{{ $etiqueta }}: {{ $fmt($fila['horas_'.$tipo]) }} h - {{ $fila['estado_'.$tipo] }}@if(!$detalle && $texto === 'No aplica') - No aplica @endif</p>
            @if($detalle)<p class="text">{{ $texto }}</p>@endif
        @endforeach
    @empty
        <p class="empty">No hay docentes con horas contractuales sin asignación en este bloque.</p>
    @endforelse
    <div class="totals">
        <p>Total {{ $bloque['titulo'] }} - {{ $bloque['filas']->count() }} docente(s)</p>
        <table><thead><tr>@foreach($camposGenerales as $etiqueta)<th>{{ $etiqueta }}</th>@endforeach</tr></thead>
            <tbody><tr>@foreach($camposGenerales as $campo => $etiqueta)<td>{{ $fmt($bloque['totales'][$campo]) }}</td>@endforeach</tr></tbody>
        </table>
        <table class="block-hours"><thead><tr>@foreach($camposBloque as $etiqueta)<th>{{ $etiqueta }}</th>@endforeach</tr></thead>
            <tbody><tr>@foreach($camposBloque as $campo => $etiqueta)<td>{{ $fmt($bloque['totales'][$campo]) }}</td>@endforeach</tr></tbody>
        </table>
    </div>
@endforeach
<div class="definitions">
    <p><strong>Lectura del informe.</strong> Contrato original y considerado, asignaciones, reservas y saldo general corresponden al docente. Contrato y saldo del bloque mantienen la distribución de Etapa 7. Los datos generales pueden repetirse entre bloques; no se suman entre secciones. Un valor no determinado y su total se muestran como «-».</p>
    <p><strong>Justificaciones.</strong> Vigente requiere texto y coincidencia con las horas actuales. Desactualizada conserva el fundamento y la cantidad anterior, pero no suma horas justificadas. Sin saldo actual conserva el texto anterior si existe; de lo contrario, indica No aplica. Sin texto para horas actuales: Sin justificación.</p>
    <p><strong>Alcance.</strong> Se excluyen cupos virtuales, asistentes, contratos protegidos, funciones declaradas ya asignadas y personas sin saldo. Se conserva la prelación del SGA y el neteo de la fracción NT. Las horas sujetas a supresión o no renovación son propuestas en revisión.</p>
</div>
</body>
</html>
