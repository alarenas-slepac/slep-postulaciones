@extends('layouts.app')

@section('content')
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
            <h1 class="h2 fw-bold">Resumen de dotación PAL</h1>
            <p class="pal-muted mb-0">RBD {{ $establecimiento->rbd }} · {{ $establecimiento->nombre_establecimiento }}</p>
        </div>
        <div class="d-flex flex-wrap align-items-start gap-2">
            <a class="btn pal-back rounded-3" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => 2027]) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a Dotación</a>
            <a class="btn btn-primary rounded-3 fw-semibold" href="{{ route('admin.dotacion-establecimiento.pal-2027', [$establecimiento, 'descargar' => 1]) }}"><i class="bi bi-download" aria-hidden="true"></i> Descargar borrador Excel</a>
        </div>
    </header>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="alert pal-warning rounded-4" role="status">
        <strong><i class="bi bi-pencil-square" aria-hidden="true"></i> Borrador para revisión.</strong>
        Se conservan las fórmulas originales. Las horas que no pueden representarse se explican en la columna Nivel o en Observaciones y quedan sin cargar en las entradas numéricas.
        En esas filas, los ceros calculados por Excel no significan ausencia de horas. Revise estos casos antes de utilizar «Resumen».
    </div>
    <div class="row g-3 mb-4">
        @foreach ([['Docentes identificados', $reporte['personas']], ['Filas contractuales', count($reporte['docentes'])], ['Filas incompatibles', $reporte['incompatibles']], ['Horas de contrato necesarias para el plan', number_format($reporte['contrato_plan_sga'], 2, ',', '.')]] as [$label, $value])
            <div class="col-12 col-sm-6 col-xl-3"><div class="card rounded-4 shadow-sm h-100"><div class="card-body"><div class="small pal-muted">{{ $label }}</div><div class="h3 fw-bold mb-0 mt-2">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
    <section class="card rounded-4 shadow-sm mb-4" aria-labelledby="pal-contenido">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold" id="pal-contenido">Contenido y criterios de la descarga</h2>
            <ul class="mb-0">
                <li>Completa «Info Establecimiento» e «Info Funcionario (DOCENTE)» con los datos del proceso 2027.</li>
                <li>Columna H de establecimientos: contrato necesario para el plan de estudios, sin agregar PIE ni funciones.</li>
                <li>Separa titular y plazo fijo. La distribución cubre primero titular y luego contrata; se identifica cada función y proporción.</li>
                <li>Las horas pendientes se informan en Observaciones. No se convierten en otras funciones.</li>
                <li>Asistentes y VTF quedan vacías. Las plazas sin persona identificada no se presentan como funcionarios.</li>
                <li>La hoja «Resumen» conserva sus fórmulas y necesita Excel compatible con FILTER, UNIQUE, VSTACK y LET. Revise el recálculo al abrir.</li>
            </ul>
            <p class="small pal-muted mt-3 mb-0">Año del padrón utilizado por el SGA: {{ $reporte['anio_padron'] ?: 'Sin datos' }}. Los cursos y asignaciones corresponden exclusivamente a 2027. Descargar no modifica registros.</p>
        </div>
    </section>
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
</div>
@endsection
