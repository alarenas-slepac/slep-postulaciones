@extends('layouts.app')

@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush

@section('content')
    @inject('prorrogas', 'App\Services\SolicitudReemplazoProrrogaService')
    @php
        $ruta = 'gestion.solicitudes-reemplazo.prorrogas.';
        $fecha = fn ($valor) => \Carbon\CarbonImmutable::parse($valor)->format('d/m/Y');
    @endphp
    <div class="slep-listing" data-slep-listing>
        <header class="slep-listing__header">
            <div class="slep-listing__heading">
                <span class="slep-listing__icon"><i class="bi bi-calendar-plus" aria-hidden="true"></i></span>
                <div>
                    <h1>Prórrogas Reemplazos</h1>
                    <p class="mb-0 slep-listing__muted">Revisa los reemplazos con seis meses de continuidad al último día cubierto en diciembre de {{ $anio }}.</p>
                </div>
            </div>
            <a class="slep-listing__button slep-listing__button--secondary"
                href="{{ route($ruta.'index', array_replace($filtros, ['anio' => $anio, 'estamento' => $estamento, 'exportar' => 1])) }}">
                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Descargar nómina Excel
            </a>
        </header>

        @if ($errors->any())
            <div class="slep-listing__notice slep-listing__notice--danger" data-slep-errors tabindex="-1" role="alert">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="slep-listing__panel" aria-labelledby="prorroga-filtros">
            <div class="slep-listing__panel-header">
                <div>
                    <h2 id="prorroga-filtros">Filtrar prórrogas</h2>
                    <p class="mb-0 slep-listing__muted">Período de prórroga del {{ $fecha($vigencia['desde']) }} al {{ $fecha($vigencia['hasta']) }}.</p>
                </div>
            </div>
            <form method="GET" action="{{ route($ruta.'index') }}" class="slep-listing__filters" data-slep-filter-form>
                <input type="hidden" name="estamento" value="{{ $estamento }}">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="prorroga-anio" class="form-label">Año de diciembre</label>
                        <input id="prorroga-anio" name="anio" type="number" class="form-control" min="1000" max="9998" value="{{ $anio }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="prorroga-buscar" class="form-label">Nombre, RUT, establecimiento o RBD</label>
                        <input id="prorroga-buscar" name="q" type="search" class="form-control" maxlength="150" value="{{ $filtros['q'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label for="prorroga-estado" class="form-label">Estado</label>
                        <select id="prorroga-estado" name="estado" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" @selected(($filtros['estado'] ?? null) === 'pendiente')>Pendiente de revisión</option>
                            <option value="registrada" @selected(($filtros['estado'] ?? null) === 'registrada')>Registrada</option>
                        </select>
                    </div>
                    @if ($estamento === 'junji')
                        <div class="col-md-6">
                            <label for="prorroga-area" class="form-label">Área de desempeño</label>
                            <select id="prorroga-area" name="area_id" class="form-select">
                                <option value="">Todas las áreas</option>
                                @foreach ($areasJunji as $areaId => $cargo)
                                    <option value="{{ $areaId }}" @selected((int) ($filtros['area_id'] ?? 0) === (int) $areaId)>{{ $cargo }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div class="slep-listing__filter-actions justify-content-end">
                    <div class="slep-listing__actions">
                        <a href="{{ route($ruta.'index', compact('anio', 'estamento')) }}" class="slep-listing__button slep-listing__button--secondary">Limpiar</a>
                        <button type="submit" class="slep-listing__button slep-listing__button--primary" data-slep-submit>
                            <i class="bi bi-search" aria-hidden="true"></i><span data-slep-submit-label>Buscar</span>
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <nav class="slep-listing__actions mb-4" aria-label="Estamentos de reemplazo">
            @foreach ($estamentos as $clave => $etiqueta)
                <a class="slep-listing__button slep-listing__button--{{ $estamento === $clave ? 'primary' : 'secondary' }}"
                    href="{{ route($ruta.'index', array_replace($filtros, ['anio' => $anio, 'estamento' => $clave, 'area_id' => $clave === 'junji' ? ($filtros['area_id'] ?? null) : null])) }}"
                    @if ($estamento === $clave) aria-current="page" @endif>
                    {{ $etiqueta }} · {{ $conteos->get($clave, 0) }}
                </a>
            @endforeach
        </nav>

        <section class="slep-listing__panel" aria-labelledby="prorroga-resultados">
            <div class="slep-listing__panel-header"><h2 id="prorroga-resultados">{{ $estamentos[$estamento] }}</h2></div>
            <div class="slep-listing__table-wrap">
                <table class="slep-listing__table" role="table">
                    <thead>
                        <tr>
                            <th scope="col">Reemplazante</th>
                            <th scope="col">Último establecimiento</th>
                            <th scope="col">Continuidad en diciembre</th>
                            <th scope="col" style="width: 20%">Jornada y financiamiento</th>
                            <th scope="col" style="min-width: 11rem">Estado</th>
                            <th scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($paginador as $caso)
                        @php
                            $establecimientos = collect($caso['periodos'])->pluck('establecimiento')->filter()->unique();
                            $registrada = isset($caso['registrada']);
                            $expediente = $caso['expediente'] ?? null;
                            $ultimo = $caso['ultimo_reemplazo'];
                        @endphp
                        <tr role="row">
                            <td data-label="Reemplazante"><strong class="slep-listing__person">{{ $caso['nombre'] }}</strong><div>{{ \App\Support\Rut::format($caso['rut']) }}</div></td>
                            <td data-label="Último establecimiento" class="text-break">
                                {{ $ultimo['establecimiento'] ?? 'Sin establecimiento informado' }}
                                @if (! empty($ultimo['cargo']))<div class="small slep-listing__muted">{{ $ultimo['cargo'] }}</div>@endif
                                @if ($establecimientos->count() > 1)<div class="small slep-listing__muted">{{ $establecimientos->count() }} establecimientos en total</div>@endif
                            </td>
                            <td data-label="Continuidad en diciembre">
                                {{ $fecha($caso['inicio_continuidad']) }} al {{ $fecha($caso['fecha_corte']) }}
                                <div class="small slep-listing__muted">{{ count($caso['periodos']) }} períodos de reemplazo</div>
                            </td>
                            <td data-label="Jornada y financiamiento">
                                <div>{{ isset($ultimo['horas']) ? number_format($ultimo['horas'], 2, ',', '.').' h' : 'Jornada sin informar' }}</div>
                                <div class="small slep-listing__muted">{{ $prorrogas->financiamiento($ultimo) ?: 'Financiamiento sin informar' }}</div>
                            </td>
                            <td data-label="Estado">
                                <span class="slep-status-pill slep-status-pill--{{ $registrada ? 'success' : 'warning' }} text-nowrap">{{ $registrada ? 'Registrada' : 'Pendiente de revisión' }}</span>
                                @if ($caso['cobertura'])<div class="small slep-listing__muted mt-1">Tiene cobertura registrada en enero o febrero</div>@endif
                                @if ($expediente?->orden_trabajo_pdf_path)
                                    <div class="small mt-1">OT N° {{ $expediente->correlativoOrdenTrabajo?->id }}</div>
                                @endif
                            </td>
                            <td data-label="Acción">
                                <a class="slep-listing__button slep-listing__button--secondary" href="{{ route($ruta.'show', ['anio' => $anio, 'estamento' => $estamento, 'rut' => $caso['rut']]) }}"
                                    aria-label="Ver períodos de {{ $caso['nombre'] }}">Ver períodos</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center p-4">No hay reemplazos para el estamento, el año y los filtros seleccionados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.ui.paginacion', ['paginador' => $paginador, 'etiqueta' => 'Páginas de prórrogas'])
        </section>
    </div>
@endsection
