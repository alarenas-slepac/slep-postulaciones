@extends('layouts.app')
@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush
@section('content')
@php($fmt = fn ($v) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($v))
<div class="slep-listing" data-slep-listing>
    <header class="slep-listing__header">
        <div class="slep-listing__heading"><span class="slep-listing__icon"><i class="bi bi-person-workspace" aria-hidden="true"></i></span><div><p class="slep-listing__eyebrow">Dotación establecimiento · {{ $anio }}</p><h1>Docentes a disposición</h1><p class="mb-0 slep-listing__muted">Distribuya horas sujetas a supresión o no renovación y consulte sus destinos.</p></div></div>
        <a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.index', ['anio' => $anio]) }}">Volver a dotación</a>
    </header>
    <div class="slep-record-state slep-record-state--info mb-4" role="note">Se consideran sólo establecimientos revisados y finalizados para {{ $anio }}. La distribución conserva el contrato y el establecimiento de origen, sin modificar el padrón ni asignar cursos automáticamente.</div>
    <section class="slep-listing__panel" aria-labelledby="disposicion-filtros">
        <div class="slep-listing__panel-header"><h2 id="disposicion-filtros">Buscar docentes</h2></div>
        <form class="slep-listing__filters" method="GET" data-slep-filter-form>
            <input type="hidden" name="bloque" value="{{ $bloque }}">
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label" for="dis-anio">Año</label><input class="form-control" id="dis-anio" name="anio" type="number" min="2020" max="2100" value="{{ $anio }}" required></div>
                <div class="col-md-5"><label class="form-label" for="dis-q">Docente, RUT, título o RBD</label><input class="form-control" id="dis-q" name="q" maxlength="150" value="{{ $filtros['q'] ?? '' }}"></div>
                <div class="col-md-5"><label class="form-label" for="dis-origen">Establecimiento de origen</label><select class="form-select" id="dis-origen" name="origen_id" data-slep-select="search"><option value="">Todos los establecimientos</option>@foreach($establecimientos as $ee)<option value="{{ $ee->id }}" @selected(($filtros['origen_id'] ?? '') == $ee->id)>{{ $ee->rbd }} · {{ $ee->nombre_establecimiento }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="dis-tipo">Tipo de horas</label><select class="form-select" id="dis-tipo" name="tipo"><option value="">Todos los tipos</option><option value="titular" @selected(($filtros['tipo'] ?? '') === 'titular')>Supresión total o parcial</option><option value="contrata" @selected(($filtros['tipo'] ?? '') === 'contrata')>No renovación</option></select></div>
                <div class="col-md-6"><label class="form-label" for="dis-estado">Estado de distribución</label><select class="form-select" id="dis-estado" name="estado"><option value="">Todos los estados</option>@foreach($estados as $key => $label)<option value="{{ $key }}" @selected(($filtros['estado'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="slep-listing__filter-actions"><p class="mb-0 slep-listing__muted">Los saldos se consultan por año, bloque y tipo de contrato.</p><div class="slep-listing__actions"><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.disposicion.index', ['anio' => $anio, 'bloque' => $bloque]) }}">Limpiar</a><button class="slep-listing__button slep-listing__button--primary" data-slep-submit type="submit"><span data-slep-submit-label>Buscar</span></button></div></div>
        </form>
    </section>
    <nav class="slep-listing__actions mb-4" aria-label="Bloques de docentes a disposición">@foreach($bloques as $key => $label)<a class="slep-listing__button slep-listing__button--{{ $bloque === $key ? 'primary' : 'secondary' }}" href="{{ route('admin.dotacion-establecimiento.disposicion.index', array_merge($filtros, ['anio' => $anio, 'bloque' => $key, 'page' => 1])) }}" @if($bloque === $key) aria-current="page" @endif>{{ $label }} · {{ $conteos[$key] ?? 0 }}</a>@endforeach</nav>
    <section class="slep-listing__panel" aria-labelledby="disposicion-nomina">
        <div class="slep-listing__panel-header"><div><h2 id="disposicion-nomina">{{ $bloques[$bloque] }}</h2><p class="mb-0 slep-listing__muted">{{ $fmt($resumen['horas']) }} h de origen · {{ $fmt($resumen['distribuidas']) }} h distribuidas · {{ $fmt($resumen['saldo']) }} h disponibles en los resultados filtrados.</p></div></div>
        <div class="slep-listing__table-wrap"><table class="slep-listing__table" role="table"><thead><tr><th scope="col" style="width:20%">Docente</th><th scope="col" style="width:16%">Origen</th><th scope="col" style="width:22%">Tipo y justificación</th><th scope="col" style="width:14%">Horas</th><th scope="col" style="width:14%">Distribución</th><th scope="col" style="width:14%">Acciones</th></tr></thead><tbody>
            @forelse($items as $fila)
                <tr role="row"><td data-label="Docente"><strong class="slep-listing__person">{{ $fila['nombre'] }}</strong><div>{{ $fila['rut'] }}</div><div>{{ $fila['funcion'] }}</div><div class="small slep-listing__muted">{{ $fila['titulo'] }}</div>@if($fila['prelacion'] ?? '')<div class="small mt-2">{{ $fila['prelacion'] }} · {{ $fila['tramo'] ?: 'Sin tramo' }} · {{ $fila['fecha_antiguedad'] ?: 'Sin antigüedad' }}</div>@endif</td>
                <td data-label="Origen"><strong>RBD {{ $fila['rbd'] }}</strong><div>{{ $fila['establecimiento'] }}</div><div class="small">Año {{ $anio }}</div></td>
                <td data-label="Tipo y justificación"><strong>{{ $fila['tipo'] }}</strong><div class="small mb-2">{{ $fila['situacion'] }}</div><span class="slep-status-pill slep-status-pill--{{ $fila['estado_justificacion'] === 'Vigente' ? 'success' : 'warning' }}">{{ $fila['estado_justificacion'] }}</span><details class="mt-2"><summary>Ver justificación</summary><p class="mt-2 text-break" style="white-space: pre-line">{{ $fila['justificacion'] }}</p></details></td>
                <td data-label="Horas"><div>Contrato: {{ $fila['contrato'] === null ? 'Sin información' : $fmt($fila['contrato']).' h' }}</div><div>A justificar: <strong>{{ $fmt($fila['horas']) }} h</strong></div><div>Distribuidas: {{ $fmt($fila['distribuidas']) }} h</div><div>Disponible: <strong>{{ $fmt($fila['saldo']) }} h</strong></div>@if($fila['exceso'] > 0)<div class="text-danger">Exceso: {{ $fmt($fila['exceso']) }} h</div>@endif</td>
                <td data-label="Distribución"><span class="slep-status-pill slep-status-pill--{{ $fila['exceso'] > 0 ? 'danger' : ($fila['estado'] === 'asignado' ? 'success' : 'info') }}">{{ $estados[$fila['estado']] }}</span><div class="small mt-2">{{ $fila['historial']->count() }} registro(s) en historial</div></td>
                <td data-label="Acciones" style="width:14%"><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.disposicion.gestionar', array_intersect_key($fila, array_flip(['anio', 'origen_id', 'rut', 'bloque', 'tipo_horas']))) }}">{{ $fila['saldo'] > 0 && ! $fila['exceso'] ? 'Asignar establecimiento' : 'Ver distribuciones' }}</a></td></tr>
            @empty<tr><td colspan="6"><div class="text-center p-4"><h3>No hay docentes para esta selección</h3><p class="mb-0">Revise el año, el bloque y los filtros. Marque primero los establecimientos revisados y finalizados en la vista general de dotación.</p></div></td></tr>@endforelse
        </tbody></table></div>
        @include('partials.ui.paginacion', ['paginador' => $items, 'etiqueta' => 'Páginas de docentes a disposición'])
    </section>
</div>
@endsection
