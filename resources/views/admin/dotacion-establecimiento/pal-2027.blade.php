@extends('layouts.app')

@section('content')
@php $esConsolidado = isset($establecimientos); @endphp
<div class="container-fluid py-4 pal-2027">
    <style>
        .pal-2027 .pal-muted { color: #64748b; }
        .pal-2027 .card { border-color: #dbe4f0; }
        .pal-2027 .pal-back { color: #334155; background: #fff; border-color: #dbe4f0; box-shadow: none; }
        .pal-2027 .pal-back:hover, .pal-2027 .pal-back:focus-visible { color: #0f172a; background: #f8fafc; border-color: #94a3b8; }
        .pal-2027 .pal-warning { color: #8a4b00; background: #fff8e1; border: 1px solid #f3d99c; }
    </style>
    <header class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
        <div>
            <div class="pal-muted small mb-1">Dotación · Plan Anual Local 2027</div>
            <h1 class="h2 fw-bold">{{ $esConsolidado ? 'PAL 2027 · Todos los establecimientos' : 'Resumen de dotación PAL' }}</h1>
            <p class="pal-muted mb-0">{{ $esConsolidado ? 'Un solo Excel con todos los establecimientos participantes en Dotación, incluidos los que aún no tienen datos 2027.' : 'RBD '.$establecimiento->rbd.' · '.$establecimiento->nombre_establecimiento }}</p>
        </div>
        <div class="d-flex flex-wrap align-items-start gap-2">
            <a class="btn pal-back rounded-3" href="{{ $esConsolidado ? route('admin.dotacion-establecimiento.index', ['anio' => 2027]) : route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => 2027]) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a Dotación</a>
            <a class="btn btn-primary rounded-3 fw-semibold {{ $esConsolidado && $establecimientos->isEmpty() ? 'disabled' : '' }}" @if($esConsolidado && $establecimientos->isEmpty()) aria-disabled="true" @else href="{{ $esConsolidado ? route('admin.dotacion-establecimiento.pal-2027.todos', ['descargar' => 1]) : route('admin.dotacion-establecimiento.pal-2027', [$establecimiento, 'descargar' => 1]) }}" @endif><i class="bi bi-download" aria-hidden="true"></i> {{ $esConsolidado ? 'Descargar Excel consolidado' : 'Descargar borrador Excel' }}</a>
        </div>
    </header>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="alert pal-warning rounded-4" role="status">
        <strong><i class="bi bi-pencil-square" aria-hidden="true"></i> Borrador para revisión.</strong>
        Se conservan las fórmulas originales. El aula 65/35 o 60/40 sin equivalencia directa se convierte a horas aula equivalentes de la tabla PAL para conservar el contrato SGA; Observaciones mantiene el aula real y la conversión.
        Revise las incompatibilidades restantes y la hoja «Excluidos PAL 2027» antes de utilizar «Resumen».
    </div>
    @unless ($esConsolidado)
    <div class="row g-3 mb-4">
        @foreach ([['Docentes identificados', $reporte['personas']], ['Filas contractuales', count($reporte['docentes'])], ['Filas incompatibles', $reporte['incompatibles']], ['Horas de contrato necesarias para el plan', number_format($reporte['contrato_plan_sga'], 2, ',', '.')]] as [$label, $value])
            <div class="col-12 col-sm-6 col-xl-3"><div class="card rounded-4 shadow-sm h-100"><div class="card-body"><div class="small pal-muted">{{ $label }}</div><div class="h3 fw-bold mb-0 mt-2">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
    @endunless
    <section class="card rounded-4 shadow-sm mb-4" aria-labelledby="pal-contenido">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold" id="pal-contenido">Contenido y criterios de la descarga</h2>
            <ul class="mb-0">
                @if ($esConsolidado)
                    <li>Incluye todos los establecimientos del proceso. Los filtros de búsqueda, comuna, página y establecimiento del listado no limitan este archivo.</li>
                    <li>«Resumen» obtiene sus RBD desde «Info Establecimiento» e incluye también los que aún no tienen docentes cargados. Conserva los cálculos de personas y horas por RBD.</li>
                @endif
                <li>Completa «Info Establecimiento» e «Info Funcionario (DOCENTE)» con los datos del proceso 2027.</li>
                <li>«Info Establecimiento» muestra las horas requeridas por el plan. Detalla los cursos de cada combinación y cuenta su plan compartido una sola vez; los planes distintos se presentan en filas separadas.</li>
                <li>Columna H de establecimientos: contrato necesario para el plan de estudios, sin agregar PIE ni funciones.</li>
                <li>Separa titular y plazo fijo. Las reservas conservan su calidad contractual y el resto se distribuye primero en titular y luego contrata; se identifica cada función y proporción.</li>
                <li>Para educadoras con título de Párvulos y aula asignada en Parvularia, convierte el contrato especial a horas aula equivalentes 65/35. Conserva el contrato y deja las horas aula reales en Observaciones.</li>
                <li>El aula 65/35 y 60/40 sin equivalencia directa se representa con horas aula equivalentes de su misma tabla. Conserva el contrato y las fórmulas N/O/J; Observaciones distingue el aula real SGA de la equivalencia PAL. Las filas 60/40 permanecen separadas.</li>
                <li>Los saldos titulares y a plazo fijo sin asignar se clasifican en FUNCIÓN «Otros (Talleristas / Apoyo / etc.)», columna R. Del saldo titular se descuentan las horas justificadas en Sobredotación 2027.</li>
                <li>Las reservas de General «Horas para otras funciones», titulares y a plazo fijo, se incorporan en FUNCIÓN «Otros», columna R. La justificación titular en Sobredotación se descuenta una sola vez entre saldo y reservas titulares.</li>
                <li>Las horas excluidas por situaciones 2027 se incorporan en FUNCIÓN «Otros», respetando titular y contrata. Observaciones detalla el motivo, el contrato y las horas incorporadas o excluidas. Los docentes con situación BIR 2027 se excluyen del PAL.</li>
                <li>Asistentes y VTF quedan vacías. Las plazas sin persona identificada no se presentan como funcionarios.</li>
                <li>«Excluidos PAL 2027» detalla personas y horas excluidas, motivo, calidad contractual y año base. Incluye quienes no continúan en 2027 según la situación del padrón anterior, no renovaciones, BIR y Sobredotación justificada. Los cupos sin persona y los excesos de asignación se identifican con una base de referencia distinta.</li>
                <li>La hoja «Resumen» conserva sus fórmulas y necesita Excel compatible con FILTER, UNIQUE, VSTACK y LET. Revise el recálculo al abrir.</li>
            </ul>
            <p class="small pal-muted mt-3 mb-0">@unless($esConsolidado) Año del padrón utilizado por el SGA: {{ $reporte['anio_padron'] ?: 'Sin datos' }}. @endunless Los cursos y asignaciones corresponden exclusivamente a 2027. Asistentes y VTF conservan el alcance anterior: las salas cuna no participan en este proceso. Descargar no modifica registros.</p>
        </div>
    </section>
    @if ($esConsolidado)
    <section class="card rounded-4 shadow-sm" aria-labelledby="pal-establecimientos">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold" id="pal-establecimientos">{{ $establecimientos->count() }} establecimientos incluidos</h2>
            <p class="pal-muted mb-3">Los casos incompatibles y las horas pendientes se indican en el Excel. Un establecimiento sin cursos 2027 aparece identificado para su revisión.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th scope="col">RBD</th><th scope="col">Establecimiento</th><th scope="col">Comuna</th></tr></thead>
                    <tbody>
                        @forelse($establecimientos as $ee)
                            <tr><td class="text-nowrap">{{ $ee->rbd }}</td><td class="fw-semibold">{{ $ee->nombre_establecimiento }}</td><td>{{ $ee->comuna ?: 'Sin comuna' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="pal-muted py-4">No hay establecimientos participantes en Dotación.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @else
    <section class="card rounded-4 shadow-sm" aria-labelledby="pal-observaciones">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold" id="pal-observaciones">Observaciones para la revisión</h2>
            @forelse ($reporte['advertencias'] as $advertencia)
                <p class="mb-2"><i class="bi bi-exclamation-triangle text-warning" aria-hidden="true"></i> {{ $advertencia }}</p>
            @empty
                <p class="pal-muted mb-0">No se detectaron incompatibilidades de formato. Revise las horas pendientes y las observaciones de cada fila antes de presentar el archivo.</p>
            @endforelse
        </div>
    </section>
    @endif
</div>
@endsection
