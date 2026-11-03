<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Docentes por asignatura - RBD {{ $establecimiento->rbd }} - {{ $anio }}</title>
    <style>
        @page { margin: 100pt 28pt 34pt; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.4; color: #0f172a; }
        .report-header { position: fixed; top: -78pt; left: 0; right: 0; border-bottom: 2pt solid #0b3d91; padding-bottom: 6pt; }
        h1 { margin: 0 0 3pt; font-size: 12.5pt; color: #0b3d91; }
        .meta { font-size: 8pt; color: #334155; }
        .notice { padding: 8pt; margin: 0 0 10pt; border: 0.7pt solid #c9dcf5; background: #eef6ff; color: #0d47a1; font-size: 8pt; }
        .block + .block, .subject + .subject { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; font-size: 8.5pt; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { padding: 6pt 5pt; border: 0.5pt solid #dbe4f0; vertical-align: top; word-wrap: break-word; }
        th { text-align: left; background: #eef6ff; color: #0b3d91; }
        .subject-title { background: #0b3d91; color: #fff; }
        .block-label { font-size: 8pt; font-weight: normal; margin-bottom: 4pt; }
        .person-name { font-weight: bold; }
        .secondary { font-size: 7.5pt; color: #475569; margin-top: 3pt; }
        .block-total td { background: #0b3d91; color: #fff; font-weight: bold; }
        h2 { font-size: 12pt; margin: 0 0 3pt; }
        .summary { font-size: 8pt; font-weight: normal; }
        .num { text-align: right; }
        .total td { background: #eef6ff; font-weight: bold; }
        .empty { padding: 12pt; color: #64748b; }
    </style>
</head>
<body>
@php
    $bloques = $bloques ?? collect(\App\Support\DotacionNominaAsignaturas::BLOQUES)->map(function ($titulo, $key) use ($informe) {
        $items = collect($informe['asignaturas'] ?? [])->filter(fn ($item) => ($item['bloque'] ?? 'plan_estudio') === $key)->values();
        return ['titulo' => $titulo, 'asignaturas' => $items, 'horas_asignadas' => $items->sum('horas_asignadas')];
    })->filter(fn ($bloque) => $bloque['asignaturas']->isNotEmpty());
    $fmt = static fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    $fecha = static function ($value) {
        if (!$value) return '-';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value ? $date->format('d-m-Y') : (string) $value;
    };
@endphp
<header class="report-header">
    <h1>Docentes por asignatura en orden de prelación - {{ $anio }}</h1>
    <div class="meta">RBD {{ $establecimiento->rbd }} - {{ $establecimiento->nombre_establecimiento }} - {{ $establecimiento->comuna }}</div>
    <div class="meta">Fuente contractual: {{ $informe['periodos_padron'] ?? 'Período no informado' }}</div>
</header>
<p class="notice">Horas aula semanales del plan vigente, separadas por bloque y asignatura. Cada docente conserva la prelación del SGA. Las horas corresponden sólo al bloque informado; una persona o asignatura puede aparecer en ambos bloques con horas distintas. Se excluyen cupos, asistentes, reservas, acompañamientos y asignaciones fantasmas. PIE especializado no contiene asignaturas del plan y no genera secciones artificiales. Sin antecedentes en la nómina anual, el docente se informa al final de la asignatura.</p>
@forelse($bloques as $bloque)
<section class="block">
@foreach($bloque['asignaturas'] as $asignatura)
    <section class="subject">
        <table>
            <colgroup><col style="width:4%"><col style="width:32%"><col style="width:27%"><col style="width:27%"><col style="width:10%"></colgroup>
            <thead>
                <tr><th colspan="5" class="subject-title"><div class="block-label">Bloque: {{ $bloque['titulo'] }}</div><h2>{{ $asignatura['nombre'] }}</h2><div class="summary">{{ $asignatura['docentes']->count() }} docente(s) - {{ $fmt($asignatura['horas_asignadas']) }} horas aula asignadas</div></th></tr>
                <tr><th style="width:4%">N°</th><th style="width:32%">Docente / RUT</th><th style="width:27%">Título</th><th style="width:27%">Prelación / antecedentes</th><th style="width:10%" class="num">Horas aula</th></tr>
            </thead>
            <tbody>
                @forelse($asignatura['docentes'] as $docente)
                    <tr><td>{{ $loop->iteration }}</td><td><div class="person-name">{{ $docente['nombre'] }}</div><div class="secondary">RUT: {{ $docente['rut'] }}</div></td><td>{{ $docente['titulo'] }}</td><td>{{ $docente['prelacion'] }}<div class="secondary">Tramo: {{ $docente['tramo'] ?: '-' }}<br>Antigüedad: {{ $fecha($docente['fecha_antiguedad']) }}</div></td><td class="num">{{ $fmt($docente['horas_asignadas']) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty">Sin docentes con horas aula asignadas a esta asignatura.</td></tr>
                @endforelse
                <tr class="total"><td colspan="4">Total de horas aula asignadas a {{ $asignatura['nombre'] }}</td><td class="num">{{ $fmt($asignatura['horas_asignadas']) }}</td></tr>
                @if($loop->last)
                    <tr class="block-total"><td colspan="4">Total {{ $bloque['titulo'] }}</td><td class="num">{{ $fmt($bloque['horas_asignadas']) }}</td></tr>
                @endif
            </tbody>
        </table>
    </section>
@endforeach
</section>
@empty
    <p class="empty">No hay asignaturas del plan vigente para este establecimiento y año.</p>
@endforelse
</body>
</html>
