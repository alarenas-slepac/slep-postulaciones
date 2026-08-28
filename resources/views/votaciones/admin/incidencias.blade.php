@extends('layouts.app')

@section('content')
<div class="va-shell" data-votaciones-admin>
    <header class="va-topbar"><div><p class="va-topbar__eyebrow">Control operacional</p><h1>Incidencias</h1><p>Seguimiento de situaciones reportadas, información pública y resolución interna.</p></div></header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    <section class="va-kpis">
        <article class="va-kpi va-kpi--orange"><div class="va-kpi__icon"><i class="bi bi-exclamation-triangle"></i></div><div><strong>{{ (int) ($totales->abiertas ?? 0) }}</strong><span>Incidencias abiertas</span></div></article>
        <article class="va-kpi va-kpi--red"><div class="va-kpi__icon"><i class="bi bi-exclamation-octagon"></i></div><div><strong>{{ (int) ($totales->criticas ?? 0) }}</strong><span>Incidencias críticas</span></div></article>
        <article class="va-kpi va-kpi--green"><div class="va-kpi__icon"><i class="bi bi-check2-circle"></i></div><div><strong>{{ (int) ($totales->resueltas ?? 0) }}</strong><span>Incidencias resueltas</span></div></article>
        <article class="va-kpi"><div class="va-kpi__icon"><i class="bi bi-list-check"></i></div><div><strong>{{ $incidencias->total() }}</strong><span>Registros filtrados</span></div></article>
    </section>

    <form method="GET" class="va-filterbar">
        <label><span>Jornada</span><select class="form-select" name="jornada"><option value="">Todas</option>@foreach($jornadas as $opcion)<option value="{{ $opcion->slug }}" @selected(request('jornada') === $opcion->slug)>{{ $opcion->nombre }}</option>@endforeach</select></label>
        <label><span>Grupo</span><select class="form-select" name="grupo"><option value="">Todos</option>@foreach($grupos as $grupo)<option value="{{ $grupo->id }}" @selected((int) request('grupo') === $grupo->id)>{{ $grupo->numero }}. {{ $grupo->nombre }}</option>@endforeach</select></label>
        <label><span>Estado</span><select class="form-select" name="estado"><option value="">Todos</option><option value="abierta" @selected(request('estado') === 'abierta')>Abierta</option><option value="resuelta" @selected(request('estado') === 'resuelta')>Resuelta</option></select></label>
        <label><span>Tipo</span><select class="form-select" name="tipo"><option value="">Todos</option>@foreach(config('votaciones.tipos_incidencia') as $key => $label)<option value="{{ $key }}" @selected(request('tipo') === $key)>{{ $label }}</option>@endforeach</select></label>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
        @if(request()->query())<a class="btn btn-outline-secondary" href="{{ route('votaciones.admin.incidencias.index') }}">Limpiar</a>@endif
    </form>

    <section class="va-card">
        @if($incidencias->isEmpty())
            <div class="va-empty"><div><i class="bi bi-check-circle"></i><h2>No existen incidencias</h2><p>No se encontraron registros con los filtros seleccionados.</p></div></div>
        @else
            <div class="table-responsive"><table class="table va-table va-table-responsive-cards"><thead><tr><th>Fecha</th><th>Grupo / establecimiento</th><th>Tipo</th><th>Estado</th><th>Información pública</th><th>Responsable</th><th></th></tr></thead><tbody>
            @foreach($incidencias as $incidencia)
                <tr>
                    <td><span class="va-table__title">{{ $incidencia->created_at->timezone(config('votaciones.timezone'))->format('d-m-Y') }}</span><span class="va-table__meta">{{ $incidencia->created_at->timezone(config('votaciones.timezone'))->format('H:i') }}</span></td>
                    <td data-label="Grupo / establecimiento"><strong>{{ $incidencia->grupo?->nombre ?? 'General' }}</strong><span class="va-table__meta">{{ $incidencia->ruta?->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento específico' }}</span></td>
                    <td data-label="Tipo">{{ config('votaciones.tipos_incidencia.'.$incidencia->tipo, str($incidencia->tipo)->replace('_', ' ')->title()) }}</td>
                    <td data-label="Estado"><span class="va-status va-status--{{ $incidencia->estado }}">{{ $incidencia->estado }}</span></td>
                    <td data-label="Información pública">@if($incidencia->publica)<span class="text-primary"><i class="bi bi-eye"></i> Pública</span>@else<span class="text-muted"><i class="bi bi-eye-slash"></i> Interna</span>@endif</td>
                    <td data-label="Responsable">{{ $incidencia->reportadaPor?->display_name ?? 'Sistema' }}</td>
                    <td data-label="Acciones"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#incidencia-{{ $incidencia->id }}">Ver detalle</button></td>
                </tr>
            @endforeach
            </tbody></table></div>
        @endif
    </section>
    <div class="mt-3">{{ $incidencias->links() }}</div>

    @foreach($incidencias as $incidencia)
        <div class="modal fade va-modal" id="incidencia-{{ $incidencia->id }}" tabindex="-1" aria-labelledby="titulo-incidencia-{{ $incidencia->id }}" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title h5" id="titulo-incidencia-{{ $incidencia->id }}">{{ config('votaciones.tipos_incidencia.'.$incidencia->tipo, $incidencia->tipo) }}</h2><p class="text-muted small mb-0">{{ $incidencia->jornada?->nombre }} · {{ $incidencia->grupo?->nombre ?? 'General' }}</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body">
            <section class="p-3 mb-3 rounded-3 bg-primary-subtle border border-primary-subtle"><span class="va-field-label text-primary">Información pública</span>@if($incidencia->publica && $incidencia->mensaje_publico)<p class="mb-0">{{ $incidencia->mensaje_publico }}</p>@else<p class="text-muted mb-0">Esta incidencia no publica información en el tablero ciudadano.</p>@endif</section>
            <section class="p-3 rounded-3 bg-light border"><span class="va-field-label">Información interna</span><p class="mb-0">{{ $incidencia->detalle_interno }}</p></section>
            @if($incidencia->estado === 'resuelta')<section class="p-3 mt-3 rounded-3 bg-success-subtle border border-success-subtle"><span class="va-field-label text-success">Resolución</span><p class="mb-1">{{ $incidencia->resolucion }}</p><small>{{ $incidencia->resueltaPor?->display_name ?? 'Sistema' }} · {{ $incidencia->resuelta_at?->format('d-m-Y H:i') }}</small></section>@endif
        </div><div class="modal-footer">
            @if($incidencia->estado === 'abierta' && auth()->user()->can('votaciones.admin'))<form method="POST" action="{{ route('votaciones.admin.incidencias.resolver', $incidencia) }}" class="d-flex flex-grow-1 gap-2" data-disable-on-submit>@csrf @method('PATCH')<input class="form-control" name="resolucion" required maxlength="2000" placeholder="Resolución aplicada"><button class="btn btn-success">Resolver</button></form>@else<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>@endif
        </div></div></div></div>
    @endforeach
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
