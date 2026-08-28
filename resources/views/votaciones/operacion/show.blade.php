@extends('layouts.app')

@section('content')
@php
    $rutas = $grupo->rutas->values();
    $actual = $rutas->first(fn($ruta) => in_array($ruta->visita?->estado, ['en_traslado', 'en_votacion'], true));
    $actual ??= $grupo->estado === 'pendiente' ? $rutas->first() : null;
    $proxima = $actual ? $rutas->first(fn($ruta) => $ruta->orden > $actual->orden && $ruta->visita?->estado !== 'finalizada') : null;
    $finalizadas = $rutas->filter(fn($ruta) => $ruta->visita?->estado === 'finalizada')->count();
    $total = $rutas->count();
    $porcentaje = $total ? (int) round(($finalizadas / $total) * 100) : 0;
    $logoActual = $actual?->establecimiento?->admisionPerfil?->logoUrl() ?? asset(config('brand.logo_principal', 'branding/01_logo_principal.png'));
@endphp
<div class="va-shell va-operation" data-votaciones-admin>
    <header class="va-operation-hero"><div class="va-operation-hero__top"><div><a class="text-white-50 text-decoration-none small" href="{{ route('votaciones.operacion.index') }}"><i class="bi bi-arrow-left"></i> Mis grupos</a><p class="va-topbar__eyebrow mt-2 mb-0">Grupo {{ $grupo->numero }}</p><h1>{{ $grupo->nombre }}</h1><p>{{ $grupo->jornada->nombre }} · {{ $grupo->jornada->procesos->pluck('codigo')->join(' + ') }}</p></div><span class="va-status va-status--{{ $grupo->estado }}">{{ str($grupo->estado)->replace('_', ' ') }}</span></div><div class="va-progress mt-3"><div class="va-progress__bar"><span style="width:{{ $porcentaje }}%"></span></div><div class="d-flex justify-content-between small mt-2 text-white-50"><span>{{ $finalizadas }} de {{ $total }} establecimientos atendidos</span><strong class="text-white">{{ $porcentaje }}%</strong></div></div></header>
    @include('votaciones.partials.alertas')

    @if($actual)
        <section class="va-current">
            <span class="va-field-label">{{ $grupo->estado === 'pendiente' ? 'Primera parada' : 'Establecimiento actual' }}</span>
            <div class="va-current__school"><span class="va-school-logo"><img src="{{ $logoActual }}" alt=""></span><div><h2>{{ $actual->establecimiento->nombre_establecimiento }}</h2><p>RBD {{ $actual->establecimiento->rbd }} · {{ $actual->establecimiento->comuna }}</p><span class="va-status va-status--{{ $actual->visita?->estado ?? 'pendiente' }} mt-2">{{ str($actual->visita?->estado ?? 'pendiente')->replace('_', ' ') }}</span></div></div>

            @if($grupo->estado === 'pendiente')
                <form method="POST" action="{{ route('votaciones.operacion.grupos.iniciar', $grupo) }}" data-disable-on-submit>@csrf<button class="btn btn-success va-primary-action" onclick="return confirm('¿Iniciar el recorrido del grupo?')"><i class="bi bi-play-circle me-1"></i> INICIAR JORNADA</button></form>
            @elseif($actual->visita?->estado === 'en_traslado')
                <form method="POST" action="{{ route('votaciones.operacion.rutas.iniciar', $actual) }}" data-disable-on-submit>@csrf<label class="form-label mt-3" for="inicio-votacion">Hora efectiva de inicio</label><input class="form-control" id="inicio-votacion" type="datetime-local" name="fecha_hora" value="{{ now(config('votaciones.timezone'))->format('Y-m-d\TH:i') }}"><button class="btn btn-success va-primary-action"><i class="bi bi-check2-circle me-1"></i> INICIAR VOTACIÓN</button></form>
            @elseif($actual->visita?->estado === 'en_votacion')
                <div class="mt-3 small text-success fw-bold"><i class="bi bi-clock"></i> Inicio: {{ $actual->visita->inicio_votacion_at?->timezone(config('votaciones.timezone'))->format('H:i') }}</div>
                <form method="POST" action="{{ route('votaciones.operacion.rutas.finalizar', $actual) }}" data-disable-on-submit>@csrf<label class="form-label mt-3" for="fin-votacion">Hora efectiva de término</label><input class="form-control" id="fin-votacion" type="datetime-local" name="fecha_hora" value="{{ now(config('votaciones.timezone'))->format('Y-m-d\TH:i') }}"><button class="btn btn-danger va-primary-action" onclick="return confirm('¿Finalizar la votación en este establecimiento?')"><i class="bi bi-stop-circle me-1"></i> FINALIZAR VOTACIÓN</button></form>
            @endif
        </section>
    @elseif($grupo->estado === 'finalizado')
        <section class="va-card va-empty mt-3"><div><i class="bi bi-check-circle text-success"></i><h2>Recorrido finalizado</h2><p>Todos los establecimientos de este grupo fueron atendidos.</p></div></section>
    @endif

    @if($proxima)<div class="va-next"><span>Próximo establecimiento</span><strong>{{ $proxima->establecimiento->nombre_establecimiento }}</strong><small>{{ $proxima->establecimiento->comuna }}</small></div>@endif

    <div class="d-grid d-sm-flex gap-2 mt-3"><button class="btn btn-outline-warning flex-grow-1" data-bs-toggle="modal" data-bs-target="#modal-incidencia"><i class="bi bi-exclamation-triangle me-1"></i> Reportar incidencia</button>@can('votaciones.manage-jornadas')<a class="btn btn-outline-primary" href="{{ route('votaciones.admin.jornadas.show', $grupo->jornada) }}#grupo-{{ $grupo->id }}"><i class="bi bi-speedometer2"></i> Ver administración</a>@endcan</div>

    <section class="va-card mt-3"><div class="va-card__header"><div><h2>Ruta del grupo</h2><p>Orden y estado de todas las paradas.</p></div></div><div class="va-card__body"><div class="va-operation-route">
        @foreach($rutas as $ruta)
            @php($estado = $ruta->visita?->estado ?? 'pendiente')
            <article @class(['va-operation-stop', 'is-current' => $actual?->id === $ruta->id, 'is-done' => $estado === 'finalizada'])><span class="va-operation-stop__icon">@if($estado === 'finalizada')<i class="bi bi-check-lg"></i>@elseif($actual?->id === $ruta->id)<i class="bi bi-geo-alt-fill"></i>@else{{ $ruta->orden }}@endif</span><div><strong>{{ $ruta->establecimiento->nombre_establecimiento }}</strong><small>RBD {{ $ruta->establecimiento->rbd }} · {{ $ruta->establecimiento->comuna }}</small></div><span class="va-status va-status--{{ $estado }}">{{ str($estado)->replace('_', ' ') }}</span></article>
        @endforeach
    </div></div></section>

    <div class="modal fade va-modal" id="modal-incidencia" tabindex="-1" aria-labelledby="titulo-incidencia" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-end"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title h5" id="titulo-incidencia">Reportar incidencia</h2><p class="text-muted small mb-0">El detalle interno nunca se publica.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><form method="POST" action="{{ route('votaciones.operacion.incidencias.store', $grupo) }}" data-disable-on-submit>@csrf<div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Tipo</label><select class="form-select" name="tipo" required>@foreach(config('votaciones.tipos_incidencia') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Establecimiento</label><select class="form-select" name="ruta_votacion_id"><option value="">Incidencia general</option>@foreach($rutas as $ruta)<option value="{{ $ruta->id }}" @selected($actual?->id === $ruta->id)>{{ $ruta->orden }}. {{ $ruta->establecimiento->nombre_establecimiento }}</option>@endforeach</select></div><div class="col-12"><label class="form-label">Detalle interno</label><textarea class="form-control" name="detalle_interno" rows="4" required maxlength="2000" placeholder="Describe qué ocurrió y qué apoyo se requiere"></textarea></div><div class="col-12"><label class="d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="publica" value="1" id="incidencia-publica" data-toggle-public-message="#mensaje-publico"><span>Mostrar información en el tablero público</span></label></div><div class="col-12" id="mensaje-publico" hidden><label class="form-label">Mensaje público</label><textarea class="form-control" name="mensaje_publico" rows="3" maxlength="1000" placeholder="Mensaje breve, sin nombres ni datos personales"></textarea><div class="form-text">Este es el único texto visible para la ciudadanía.</div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-warning"><i class="bi bi-exclamation-triangle"></i> Registrar incidencia</button></div></form></div></div></div>
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
