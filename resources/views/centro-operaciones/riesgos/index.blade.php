@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
@php
    $puedeEvaluar = auth()->user()->hasAnyRole(['admin', 'director_ejecutivo', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'gabinete_slep']);
@endphp
<div class="co-shell co-risk-shell">
    <header class="co-hero">
        <div class="co-module-identity">
            <div class="co-module-icon co-module-icon--risk"><i class="bi bi-shield-check"></i></div>
            <div>
                <div class="co-eyebrow">Centro de Operaciones</div>
                <h1>Riesgo por establecimiento</h1>
                <p>Evaluación IRTE, vigencia y priorización territorial.</p>
            </div>
        </div>
        <div class="co-hero-actions">
            @hasanyrole('admin|gabinete_slep')
                <a class="btn btn-outline-primary" href="{{ route('centro-operaciones.riesgos.configuracion') }}"><i class="bi bi-sliders"></i> Mantenedor IRTE</a>
            @endhasanyrole
            <a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.index') }}"><i class="bi bi-arrow-left"></i> Panel</a>
        </div>
    </header>

    <section class="co-kpis co-risk-kpis">
        <article class="co-kpi co-kpi--blue"><i class="bi bi-clipboard-data"></i><div><span>Evaluados</span><strong>{{ $metricas['evaluados'] }}</strong><small>con evaluación publicada</small></div></article>
        <article class="co-kpi co-kpi--red"><i class="bi bi-exclamation-octagon"></i><div><span>Críticos</span><strong>{{ $metricas['criticos'] }}</strong><small>IRTE desde 80</small></div></article>
        <article class="co-kpi co-kpi--yellow"><i class="bi bi-exclamation-triangle"></i><div><span>Atención prioritaria</span><strong>{{ $metricas['atencion'] }}</strong><small>IRTE entre 60 y 79</small></div></article>
        <article class="co-kpi co-kpi--gray"><i class="bi bi-hourglass-split"></i><div><span>Sin evaluación</span><strong>{{ $metricas['sin_evaluacion'] }}</strong><small>pendientes de clasificación</small></div></article>
        <article class="co-kpi co-kpi--purple"><i class="bi bi-calendar-x"></i><div><span>Vencidas</span><strong>{{ $metricas['vencidos'] }}</strong><small>requieren actualización</small></div></article>
    </section>

    <section class="co-card">
        <div class="co-card-head"><div><span class="co-eyebrow">Cobertura territorial</span><h2>Establecimientos</h2></div></div>
        <form method="GET" class="co-risk-filters">
            <div><label for="buscar">Nombre o RBD</label><input id="buscar" name="buscar" class="form-control" value="{{ request('buscar') }}" placeholder="Buscar establecimiento"></div>
            <div><label for="comuna">Comuna</label><select id="comuna" name="comuna" class="form-select"><option value="">Todas</option>@foreach($comunas as $comuna)<option value="{{ $comuna }}" @selected(request('comuna') === $comuna)>{{ $comuna }}</option>@endforeach</select></div>
            <button class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button>
            @if(request()->hasAny(['buscar', 'comuna']))<a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.riesgos.index') }}">Limpiar</a>@endif
        </form>
        <div class="table-responsive">
            <table class="table co-table align-middle mb-0">
                <thead><tr><th>Establecimiento</th><th>Comuna</th><th>Matrícula</th><th>IRTE</th><th>Categoría</th><th>Vigencia</th><th></th></tr></thead>
                <tbody>
                @forelse($establecimientos as $establecimiento)
                    @php($evaluacion = $establecimiento->ultimaEvaluacionRiesgoCentroOperaciones)
                    <tr>
                        <td><strong>{{ $establecimiento->nombre_establecimiento }}</strong><small class="d-block text-muted">RBD {{ $establecimiento->rbd }}</small></td>
                        <td>{{ $establecimiento->comuna ?: 'Sin comuna' }}</td>
                        <td>{{ number_format($matriculas[$establecimiento->id]['total'] ?? 0, 0, ',', '.') }}</td>
                        <td><strong class="co-irte-value">{{ $evaluacion?->irte ?? '—' }}</strong></td>
                        <td>@if($evaluacion)<span class="co-risk-badge co-risk-badge--{{ $evaluacion->categoria }}">{{ $evaluacion->categoria_label }}</span>@else<span class="co-risk-badge co-risk-badge--sin_evaluacion">Sin evaluación</span>@endif</td>
                        <td>@if($evaluacion?->vigente_hasta)<span class="{{ $evaluacion->esta_vencida ? 'text-danger fw-semibold' : '' }}">{{ $evaluacion->vigente_hasta->format('d/m/Y') }}</span>@else—@endif</td>
                        <td class="text-end">@if($puedeEvaluar)<a class="btn btn-sm btn-outline-primary" href="{{ route('centro-operaciones.riesgos.evaluar', $establecimiento) }}">{{ $evaluacion ? 'Reevaluar' : 'Evaluar' }}</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="co-empty">No se encontraron establecimientos.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($establecimientos->hasPages())<div class="co-card-footer">{{ $establecimientos->links() }}</div>@endif
    </section>
</div>
@endsection
