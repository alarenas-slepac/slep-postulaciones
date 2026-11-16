@extends('layouts.app')

@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush

@section('content')
@php($fmt = fn ($valor) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($valor))
<div class="slep-listing" data-slep-listing>
    <header class="slep-listing__header">
        <div class="slep-listing__heading">
            <span class="slep-listing__icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <div>
                <p class="slep-listing__eyebrow">Dotación establecimiento · {{ $anio }}</p>
                <h1>Nómina completa por bloque</h1>
                <p class="mb-0 slep-listing__muted">Todos los docentes del padrón base, con sus horas de contrato consolidadas y título profesional.</p>
            </div>
        </div>
        <div class="slep-listing__actions">
            <a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.index', ['anio' => $anio]) }}">Volver a dotación</a>
            <a class="slep-listing__button slep-listing__button--primary" href="{{ route('admin.dotacion-establecimiento.nomina-completa.export', $filtros) }}"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Exportar nómina completa (Excel)</a>
        </div>
    </header>

    <div class="slep-record-state slep-record-state--info mb-4" role="note">
        Se incluyen docentes con horas ajustadas, justificadas y sin asignación, además de quienes no continúan en la proyección. Cada docente ocupa una sola fila por establecimiento; si aporta a varios bloques, su contrato se distribuye en la misma fila.
    </div>

    <div class="row g-3 mb-4" aria-label="Resumen de los resultados filtrados">
        @foreach (['personas' => 'Docentes (RUT únicos)', 'registros' => 'Registros docente-establecimiento', 'establecimientos' => 'Establecimientos', 'contrato' => 'Horas de contrato original'] as $campo => $label)
            <div class="col-sm-6 col-xl-3"><section class="slep-listing__panel p-3 h-100"><p class="slep-listing__muted mb-1">{{ $label }}</p><div class="fs-3 fw-bold">{{ $campo === 'contrato' ? $fmt($resumen[$campo]) : number_format($resumen[$campo], 0, ',', '.') }}</div></section></div>
        @endforeach
    </div>

    @if($errors->any())
        <div class="slep-listing__notice slep-listing__notice--danger" role="alert"><strong>Revise los filtros.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="slep-listing__panel" aria-labelledby="nomina-filtros">
        <div class="slep-listing__panel-header"><h2 id="nomina-filtros">Buscar docentes por bloque</h2></div>
        <form method="GET" class="slep-listing__filters" data-slep-filter-form>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-2"><label class="form-label" for="nomina-anio">Año</label><input type="number" class="form-control" id="nomina-anio" name="anio" value="{{ $anio }}" min="2020" max="2100" required></div>
                <div class="col-sm-6 col-xl-4"><label class="form-label" for="nomina-bloque">Bloque contractual</label><select class="form-select" id="nomina-bloque" name="bloque"><option value="">Todos los bloques</option>@foreach($bloques as $key => $label)<option value="{{ $key }}" @selected($filtros['bloque'] === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-xl-6"><label class="form-label" for="nomina-ee">Establecimiento</label><select class="form-select" id="nomina-ee" name="establecimiento_id" data-slep-select="search"><option value="">Todos los establecimientos autorizados</option>@foreach($establecimientos as $ee)<option value="{{ $ee->id }}" @selected($filtros['establecimiento_id'] === (int) $ee->id)>{{ $ee->rbd }} · {{ $ee->nombre_establecimiento }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-xl-4"><label class="form-label" for="nomina-comuna">Comuna</label><select class="form-select" id="nomina-comuna" name="comuna"><option value="">Todas las comunas</option>@foreach($comunas as $comuna)<option value="{{ $comuna }}" @selected($filtros['comuna'] === $comuna)>{{ $comuna }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-xl-8"><label class="form-label" for="nomina-q">Nombre, RUT, título o establecimiento</label><input type="search" class="form-control" id="nomina-q" name="q" value="{{ $filtros['q'] }}" maxlength="150"></div>
            </div>
            <div class="slep-listing__filter-actions">
                <p class="mb-0 slep-listing__muted">El Excel respeta los filtros y descarga todos los resultados, no sólo esta página.</p>
                <div class="slep-listing__actions"><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.nomina-completa.index', ['anio' => $anio]) }}">Limpiar</a><button type="submit" class="slep-listing__button slep-listing__button--primary" data-slep-submit><span data-slep-submit-label>Buscar</span></button></div>
            </div>
        </form>
    </section>

    <section class="slep-listing__panel" aria-labelledby="nomina-resultados">
        <div class="slep-listing__panel-header"><div><h2 id="nomina-resultados">{{ $bloques[$filtros['bloque']] ?? 'Todos los bloques' }}</h2><p class="mb-0 slep-listing__muted">El contrato original se conserva completo. Las horas consideradas en dotación se muestran como referencia.</p></div></div>
        <div class="slep-listing__table-wrap">
            <table class="slep-listing__table">
                <thead><tr><th scope="col" style="width:16%">Docente</th><th scope="col" style="width:16%">Establecimiento</th><th scope="col" style="width:16%">Título profesional</th><th scope="col" style="width:10%">Bloques</th><th scope="col" style="width:10%">Contrato original</th><th scope="col" style="width:12%">Distribución del contrato</th><th scope="col" style="width:12%">Consideración en dotación</th><th scope="col" style="width:8%">Padrón</th></tr></thead>
                <tbody>
                    @forelse($items as $fila)
                        <tr>
                            <td data-label="Docente"><strong class="slep-listing__person">{{ $fila['nombre'] }}</strong><div>{{ $fila['rut'] }}</div><div class="small slep-listing__muted">{{ $fila['tramo'] ?: 'Sin tramo' }}</div></td>
                            <td data-label="Establecimiento"><strong>RBD {{ $fila['rbd'] }}</strong><div>{{ $fila['establecimiento'] }}</div><div class="small slep-listing__muted">{{ $fila['comuna'] }}</div></td>
                            <td data-label="Título profesional">{{ $fila['titulo'] }}</td>
                            <td data-label="Bloques">{{ $fila['bloques_label'] }}</td>
                            <td data-label="Contrato original"><strong>{{ $fmt($fila['contrato_original']) }} h</strong><div class="small">Titulares: {{ $fmt($fila['titulares']) }} h</div><div class="small">Contrata: {{ $fmt($fila['contrata']) }} h</div>@if($fila['otras_calidades'] > 0)<div class="small">Otras calidades: {{ $fmt($fila['otras_calidades']) }} h</div>@endif</td>
                            <td data-label="Distribución del contrato">@foreach($bloques as $key => $label)<div class="small">{{ $label }}: <strong>{{ $fmt($fila[$key]) }} h</strong></div>@endforeach</td>
                            <td data-label="Consideración en dotación"><strong>{{ $fmt($fila['contrato_considerado']) }} h</strong><div class="small">{{ $fila['estado_dotacion'] }}</div>@if($fila['justificada'])<span class="slep-status-pill slep-status-pill--info">Justificación registrada</span>@endif</td>
                            <td data-label="Padrón" style="width:8%">{{ $fila['periodo_padron'] }}<div class="small slep-listing__muted">{{ $fila['registros_contrato'] }} línea(s) consolidada(s)</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="text-center p-4"><h3>No hay docentes para esta selección</h3><p class="mb-0">Revise el año, el bloque y los filtros de búsqueda.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.ui.paginacion', ['paginador' => $items, 'etiqueta' => 'Páginas de la nómina completa'])
    </section>
</div>
@endsection
