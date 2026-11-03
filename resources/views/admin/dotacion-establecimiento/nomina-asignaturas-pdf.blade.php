<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Docentes por asignatura - RBD {{ $establecimiento->rbd }} - {{ $anio }}</title>
    <style>
        @page { margin: 82pt 28pt 34pt; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.4; color: #0f172a; }
        .report-header { position: fixed; top: -62pt; left: 0; right: 0; border-bottom: 2pt solid #0b3d91; padding-bottom: 6pt; }
        h1 { margin: 0 0 3pt; font-size: 14pt; color: #0b3d91; }
        .meta { font-size: 8pt; color: #334155; }
        .notice { padding: 8pt; margin: 0 0 10pt; border: 0.7pt solid #c9dcf5; background: #eef6ff; color: #0d47a1; font-size: 8pt; }
        .subject + .subject { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; font-size: 8.5pt; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { padding: 6pt 5pt; border: 0.5pt solid #dbe4f0; vertical-align: top; word-wrap: break-word; }
        th { text-align: left; background: #eef6ff; color: #0b3d91; }
        .subject-title { background: #0b3d91; color: #fff; }
        h2 { font-size: 12pt; margin: 0 0 3pt; }
        .summary { font-size: 8pt; font-weight: normal; }
        .num { text-align: right; }
        .total td { background: #eef6ff; font-weight: bold; }
        .empty { padding: 12pt; color: #64748b; }
    </style>
</head>
<body>
@php
    $asignaturas = collect($informe['asignaturas'] ?? []);
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
<p class="notice">Horas aula semanales efectivamente asignadas en el plan vigente, sumadas por docente y asignatura en todos sus cursos. La agrupación curricular corresponde al consolidado de asignaturas del SGA. Se excluyen cupos por contratar, asistentes, reservas, acompañamientos y asignaciones fantasmas. Una asociación sin horas no se informa como asignación. La prelación conserva los grupos del SGA y sus desempates por antigüedad y nombre; sin antecedentes en la nómina anual, se informa al final de la asignatura.</p>
@forelse($asignaturas as $asignatura)
    <section class="subject">
        <table>
            <colgroup><col style="width:3%"><col style="width:10%"><col style="width:22%"><col style="width:22%"><col style="width:17%"><col style="width:8%"><col style="width:9%"><col style="width:9%"></colgroup>
            <thead>
                <tr><th colspan="8" class="subject-title"><h2>{{ $asignatura['nombre'] }}</h2><div class="summary">{{ $asignatura['docentes']->count() }} docente(s) - {{ $fmt($asignatura['horas_asignadas']) }} horas aula asignadas</div></th></tr>
                <tr><th style="width:3%">N°</th><th style="width:10%">RUT</th><th style="width:22%">Nombre completo</th><th style="width:22%">Título</th><th style="width:17%">Grupo de prelación</th><th style="width:8%">Tramo</th><th style="width:9%">Antigüedad</th><th style="width:9%" class="num">Horas aula asignadas</th></tr>
            </thead>
            <tbody>
                @forelse($asignatura['docentes'] as $docente)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $docente['rut'] }}</td><td>{{ $docente['nombre'] }}</td><td>{{ $docente['titulo'] }}</td><td>{{ $docente['prelacion'] }}</td><td>{{ $docente['tramo'] ?: '-' }}</td><td>{{ $fecha($docente['fecha_antiguedad']) }}</td><td class="num">{{ $fmt($docente['horas_asignadas']) }}</td></tr>
                @empty
                    <tr><td colspan="8" class="empty">Sin docentes con horas aula asignadas a esta asignatura.</td></tr>
                @endforelse
                <tr class="total"><td colspan="7">Total de horas aula asignadas a {{ $asignatura['nombre'] }}</td><td class="num">{{ $fmt($asignatura['horas_asignadas']) }}</td></tr>
            </tbody>
        </table>
    </section>
@empty
    <p class="empty">No hay asignaturas del plan vigente para este establecimiento y año.</p>
@endforelse
</body>
</html>
