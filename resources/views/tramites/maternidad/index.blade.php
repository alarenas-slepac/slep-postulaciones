@extends('layouts.app')
@include('tramites.maternidad.partials._assets')
@section('content')
<div class="slep-listing mat-workspace" data-slep-listing>
    <header class="slep-listing__header">
        <div class="slep-listing__heading"><span class="slep-listing__icon"><i class="bi slep-icon-baby" aria-hidden="true"></i></span><div>
            <p class="slep-listing__eyebrow">Trámites y operación</p><h1>Maternidad</h1>
            <p class="mb-0 slep-listing__muted">Seguimiento de casos, beneficios, documentos e historial de las funcionarias.</p>
        </div></div>
        <div class="slep-listing__actions">
            <a class="slep-listing__button slep-listing__button--secondary" href="{{ route('tramites.maternidad.importar') }}"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>Carga masiva</a>
            <a class="slep-listing__button slep-listing__button--primary" href="{{ route('tramites.maternidad.create') }}"><i class="bi bi-plus-circle" aria-hidden="true"></i>Registrar caso</a>
        </div>
    </header>
    @include('tramites.maternidad.partials._mensajes')
    <nav class="mat-tabs mb-4" aria-label="Estamentos de Maternidad">
        @foreach (config('maternidad.estamentos') as $key => $nombre)
            <a class="mat-tab {{ $estamento === $key ? 'is-active' : '' }}" @if ($estamento === $key) aria-current="page" @endif href="{{ route('tramites.maternidad.index', array_merge($filtros, ['estamento' => $key])) }}">{{ $nombre }} <span>{{ $conteos[$key] ?? 0 }} casos</span></a>
        @endforeach
    </nav>
    <section class="slep-listing__panel" aria-labelledby="mat-filtros">
        <div class="slep-listing__panel-header"><h2 id="mat-filtros">Buscar expedientes</h2></div>
        <form method="GET" class="slep-listing__filters" data-slep-filter-form>
            <input type="hidden" name="estamento" value="{{ $estamento }}">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4"><label class="form-label" for="mat-q">RUT, nombre o referencia</label><input class="form-control" id="mat-q" name="q" value="{{ $filtros['q'] ?? '' }}" maxlength="150"></div>
                <div class="col-lg-4"><label class="form-label" for="mat-ee">Establecimiento</label><select class="form-select" id="mat-ee" name="establecimiento_id" data-slep-select="search"><option value="">Todos los establecimientos</option>@foreach ($establecimientos as $ee)<option value="{{ $ee->id }}" @selected((string) ($filtros['establecimiento_id'] ?? '') === (string) $ee->id)>{{ $ee->rbd }} · {{ $ee->nombre_establecimiento }}</option>@endforeach</select></div>
                <div class="col-lg-4"><label class="form-label" for="mat-estado">Estado de seguimiento</label><select class="form-select" id="mat-estado" name="estado" data-slep-select="compact"><option value="">Todos</option>@foreach (['vigente' => 'Con fecha de fuero vigente', 'vencido' => 'Con fecha de fuero cumplida', 'sin_fecha' => 'Sin fecha de término', 'pendiente' => 'Documentos pendientes'] as $key => $label)<option value="{{ $key }}" @selected(($filtros['estado'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="slep-listing__filter-actions"><p class="mb-0 slep-listing__muted">Cada embarazo o proceso tiene su propio caso. Una funcionaria puede aparecer en varios expedientes.</p><div class="slep-listing__actions"><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('tramites.maternidad.index', ['estamento' => $estamento]) }}">Limpiar</a><button class="slep-listing__button slep-listing__button--primary" type="submit" data-slep-submit><i class="bi bi-search" aria-hidden="true"></i><span data-slep-submit-label>Buscar</span></button></div></div>
        </form>
    </section>
    <section class="slep-listing__panel" aria-labelledby="mat-listado">
        <div class="slep-listing__panel-header"><div><h2 id="mat-listado">{{ config('maternidad.estamentos.'.$estamento) }}</h2><p class="mb-0 slep-listing__muted small">{{ $casos->total() }} caso(s) según los filtros aplicados.</p></div></div>
        <div class="slep-listing__table-wrap"><table class="slep-listing__table mat-table" role="table"><thead><tr><th scope="col">Funcionaria y caso</th><th scope="col">Vínculo laboral</th><th scope="col">Fechas de seguimiento</th><th scope="col">Documentación</th><th scope="col">Acciones</th></tr></thead><tbody>
        @forelse ($casos as $caso)
            <tr role="row"><td role="cell" data-label="Funcionaria y caso"><strong class="slep-listing__person">{{ $caso->nombre_completo }}</strong><div>{{ \App\Support\RutChile::format($caso->rut) }}</div><div class="small slep-listing__muted mt-1">{{ $caso->codigo }} · {{ $caso->referencia_caso }}</div><div class="small slep-listing__muted">Registro: {{ $caso->fecha_registro?->format('d-m-Y') }}</div></td>
                <td role="cell" data-label="Vínculo laboral"><strong>{{ $caso->establecimiento_nombre }}</strong><div class="small">RBD {{ $caso->establecimiento_rbd }} · {{ $caso->comuna ?: 'Comuna no informada' }}</div><div class="mt-2">{{ $caso->calidad_juridica }}</div><div class="small slep-listing__muted">{{ $caso->fecha_ingreso?->format('d-m-Y') ?? 'Ingreso sin fecha' }} — {{ $caso->fecha_termino?->format('d-m-Y') ?? 'Sin término informado' }}</div></td>
                <td role="cell" data-label="Fechas de seguimiento"><div>FPP: <strong>{{ $caso->fpp?->format('d-m-Y') ?? 'Sin informar' }}</strong></div><div>FNH: <strong>{{ $caso->fnh?->format('d-m-Y') ?? 'Sin informar' }}</strong></div><div class="mt-2">Término de fuero: <strong>{{ $caso->fecha_termino_fuero?->format('d-m-Y') ?? 'Sin calcular' }}</strong></div><span class="slep-status-pill slep-status-pill--{{ $caso->base_fuero === 'parto_probable' ? 'info' : 'neutral' }} mt-2">{{ ['parto_probable' => 'Estimado por FPP', 'nacimiento_certificado' => 'Nacimiento certificado', 'ley_dominga' => 'Ley Dominga · regla SGA'][$caso->base_fuero] ?? 'Sin base de cálculo' }}</span></td>
                <td role="cell" data-label="Documentación">@if ($caso->documentos_pendientes)<span class="slep-status-pill slep-status-pill--warning">{{ count($caso->documentos_pendientes) }} respaldo(s) pendiente(s)</span><ul class="small ps-3 mt-2 mb-0">@foreach ($caso->documentos_pendientes as $tipo)<li>{{ config('maternidad.documentos.'.$tipo) }}</li>@endforeach</ul>@else<span class="slep-status-pill slep-status-pill--success">Documentación completa</span>@endif</td>
                <td role="cell" data-label="Acciones"><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('tramites.maternidad.show', $caso) }}">Abrir caso<span class="visually-hidden"> {{ $caso->codigo }} de {{ $caso->nombre_completo }}</span></a></td></tr>
        @empty
            <tr><td colspan="5" class="slep-listing__empty"><i class="bi bi-folder2-open" aria-hidden="true"></i><strong>No hay casos para estos filtros.</strong><p class="slep-listing__muted mb-0 mt-2">Registre un caso individual o utilice la plantilla de carga masiva.</p></td></tr>
        @endforelse
        </tbody></table></div>
        @include('partials.ui.paginacion', ['paginador' => $casos, 'etiqueta' => 'Páginas de casos de maternidad'])
    </section>
</div>
@endsection
