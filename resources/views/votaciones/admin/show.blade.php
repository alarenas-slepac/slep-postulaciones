@extends('layouts.app')

@section('content')
<div class="va-shell" data-votaciones-admin data-votaciones-admin-jornada data-routing-url="{{ route('votaciones.admin.jornadas.ruta-vial', $jornada) }}">
    <header class="va-topbar">
        <div><p class="va-topbar__eyebrow">Administración de jornada</p><h1>{{ $jornada->nombre }}</h1><p>{{ $jornada->fecha->format('d-m-Y') }} · {{ $jornada->procesos->pluck('codigo')->join(' + ') }}</p></div>
        <div class="va-topbar__actions">
            @if($jornada->publica)<a class="btn btn-outline-light" target="_blank" rel="noopener" href="{{ route('public.votaciones.show', $jornada) }}"><i class="bi bi-box-arrow-up-right"></i> Vista pública</a>@endif
            @if($jornada->estado === 'borrador')
                <a class="btn btn-outline-light" href="{{ route('votaciones.admin.jornadas.edit', $jornada) }}"><i class="bi bi-pencil"></i> Editar</a>
                <form method="POST" action="{{ route('votaciones.admin.jornadas.publicar', $jornada) }}" data-disable-on-submit>@csrf<button class="btn btn-light" onclick="return confirm('¿Publicar esta jornada?')"><i class="bi bi-broadcast"></i> Publicar</button></form>
            @elseif($jornada->estado === 'suspendida')
                <form method="POST" action="{{ route('votaciones.admin.jornadas.publicar', $jornada) }}" data-disable-on-submit>@csrf<button class="btn btn-light"><i class="bi bi-play-circle"></i> Reanudar</button></form>
            @endif
        </div>
    </header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    @php
        $totalRutas = $jornada->grupos->sum(fn($grupo) => $grupo->rutas->count());
        $rutasFinalizadas = $jornada->grupos->flatMap->rutas->filter(fn($ruta) => $ruta->visita?->estado === 'finalizada')->count();
    @endphp
    <section class="va-kpis">
        <article class="va-kpi"><div class="va-kpi__icon"><i class="bi bi-activity"></i></div><div><strong class="fs-5"><span class="va-status va-status--{{ $jornada->estado }}">{{ str($jornada->estado)->replace('_', ' ') }}</span></strong><span>Estado de jornada</span></div></article>
        <article class="va-kpi"><div class="va-kpi__icon"><i class="bi bi-people"></i></div><div><strong>{{ $jornada->grupos->count() }}</strong><span>Grupos configurados</span></div></article>
        <article class="va-kpi va-kpi--green"><div class="va-kpi__icon"><i class="bi bi-building-check"></i></div><div><strong>{{ $rutasFinalizadas }} / {{ $totalRutas }}</strong><span>Establecimientos atendidos</span></div></article>
        <article class="va-kpi va-kpi--orange"><div class="va-kpi__icon"><i class="bi bi-exclamation-triangle"></i></div><div><strong>{{ $jornada->incidencias->where('estado', 'abierta')->count() }}</strong><span>Incidencias abiertas</span></div></article>
    </section>

    @if(in_array($jornada->estado, ['publicada', 'en_curso'], true))
        <section class="va-warning mb-3"><i class="bi bi-pause-circle fs-4"></i><div class="flex-grow-1"><strong>Suspender publicación</strong><div>La jornada dejará de estar disponible públicamente hasta que sea reanudada.</div><form class="d-flex flex-wrap gap-2 mt-2" method="POST" action="{{ route('votaciones.admin.jornadas.suspender', $jornada) }}" data-disable-on-submit>@csrf<input class="form-control form-control-sm flex-grow-1" name="motivo" required maxlength="1000" placeholder="Motivo obligatorio"><button class="btn btn-sm btn-outline-danger">Suspender jornada</button></form></div></section>
    @endif

    <section class="va-card mb-4" id="rutas">
        <div class="va-card__header"><div><h2>Mapa operacional de rutas</h2><p>Recorridos por carretera, orden de paradas y estado de coordenadas.</p></div><span class="votaciones-admin-map-status" data-votaciones-admin-map-status></span></div>
        <div class="va-card__body"><div class="votaciones-admin-map" data-votaciones-admin-map aria-label="Mapa de las rutas configuradas"></div><div class="votaciones-admin-routing-status mt-3" data-votaciones-admin-routing-status aria-live="polite">Calculando recorridos por carretera…</div><div class="votaciones-admin-distance-summary" data-votaciones-admin-distance-summary></div></div>
    </section>
    <script type="application/json" id="votaciones-admin-rutas-data">{!! json_encode($mapaRutas, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>

    <div id="grupos" class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <div><p class="va-eyebrow text-primary">Equipos y recorridos</p><h2 class="h4 mb-1">Gestión de grupos</h2><p class="text-muted mb-0">Responsables, integrantes y constructor de rutas.</p></div>
        @if($jornada->estado === 'borrador')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-crear-grupo"><i class="bi bi-plus-circle me-1"></i> Crear grupo</button>@endif
    </div>

    <div class="va-group-list">
        @forelse($jornada->grupos as $grupo)
            @php
                $actual = $grupo->rutas->first(fn($ruta) => in_array($ruta->visita?->estado, ['en_traslado', 'en_votacion'], true));
                $finalizadas = $grupo->rutas->filter(fn($ruta) => $ruta->visita?->estado === 'finalizada')->count();
                $totalGrupo = $grupo->rutas->count();
                $avance = $totalGrupo ? (int) round(($finalizadas / $totalGrupo) * 100) : 0;
                $incidenciasGrupo = $jornada->incidencias->where('grupo_votacion_id', $grupo->id)->where('estado', 'abierta')->count();
            @endphp
            <article class="va-group" id="grupo-{{ $grupo->id }}">
                <header class="va-group__head">
                    <div class="va-group__identity"><span class="va-group__number">{{ $grupo->numero }}</span><div><h2>{{ $grupo->nombre }}</h2><p>Responsable: {{ $grupo->encargado?->display_name ?? 'Sin asignar' }} · {{ $grupo->miembros->count() }} integrante(s)</p></div></div>
                    <div class="d-flex flex-wrap align-items-center gap-2"><span class="va-status va-status--{{ $grupo->estado }}">{{ str($grupo->estado)->replace('_', ' ') }}</span><a class="btn btn-sm btn-outline-primary" href="{{ route('votaciones.operacion.show', $grupo) }}"><i class="bi bi-phone"></i> Abrir operación</a></div>
                </header>
                <div class="va-group__body">
                    <div class="va-group__stats">
                        <div class="va-mini-stat"><span>Ubicación actual</span><strong>{{ $actual?->establecimiento?->nombre_establecimiento ?? 'Sin actividad' }}</strong></div>
                        <div class="va-mini-stat"><span>Ruta</span><strong>{{ $totalGrupo }} establecimiento(s)</strong></div>
                        <div class="va-mini-stat"><span>Avance</span><strong>{{ $finalizadas }} / {{ $totalGrupo }} · {{ $avance }}%</strong></div>
                        <div class="va-mini-stat"><span>Incidencias abiertas</span><strong>{{ $incidenciasGrupo }}</strong></div>
                    </div>

                    @if($jornada->estado === 'borrador')
                        <details class="mb-3"><summary class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-gear me-1"></i> Editar equipo</summary><div class="va-route-panel mt-2">
                            <form method="POST" action="{{ route('votaciones.admin.grupos.update', $grupo) }}" class="row g-3" data-disable-on-submit>@csrf @method('PUT')
                                <div class="col-sm-2"><label class="form-label">Número</label><input class="form-control" type="number" min="1" name="numero" value="{{ $grupo->numero }}" required></div>
                                <div class="col-sm-4"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="{{ $grupo->nombre }}" required maxlength="255"></div>
                                <div class="col-sm-6"><label class="form-label">Encargado</label><select class="form-select" name="encargado_id" required>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected($grupo->encargado_id === $usuario->id)>{{ $usuario->display_name }} · {{ $usuario->email }}</option>@endforeach</select></div>
                                <div class="col-12"><label class="form-label">Integrantes / Ministros de Fe</label><select class="form-select" name="miembros[]" multiple size="4">@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected($grupo->miembros->contains($usuario))>{{ $usuario->display_name }} · {{ $usuario->email }}</option>@endforeach</select></div>
                                <div class="col-12 text-end"><button class="btn btn-primary">Guardar equipo</button></div>
                            </form>
                            <form method="POST" action="{{ route('votaciones.admin.grupos.destroy', $grupo) }}" class="mt-2 text-end" onsubmit="return confirm('¿Eliminar el grupo y su ruta?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Eliminar grupo</button></form>
                        </div></details>
                    @endif

                    <div class="va-route-builder">
                        <section class="va-route-panel">
                            <h3><i class="bi bi-building-add me-1"></i> Establecimientos disponibles</h3>
                            @if($jornada->estado === 'borrador')
                                <form method="POST" action="{{ route('votaciones.admin.rutas.store', $grupo) }}" data-disable-on-submit>@csrf
                                    <label class="form-label" for="buscar-establecimiento-{{ $grupo->id }}">Buscar por nombre, RBD o comuna</label><input class="form-control mb-2" id="buscar-establecimiento-{{ $grupo->id }}" type="search" data-votacion-establecimiento-search placeholder="Ej.: 5001 o Coronel" autocomplete="off">
                                    <select class="form-select mb-2" name="establecimiento_id" data-votacion-establecimiento-select required><option value="">Seleccione un establecimiento</option>@foreach($establecimientos as $establecimiento)<option value="{{ $establecimiento->id }}" data-search="{{ $establecimiento->rbd }} {{ $establecimiento->nombre_establecimiento }} {{ $establecimiento->comuna }}" @disabled($grupo->rutas->contains('establecimiento_id', $establecimiento->id))>RBD {{ $establecimiento->rbd }} · {{ $establecimiento->nombre_establecimiento }} · {{ $establecimiento->comuna }}{{ $coordenadasValidas->get($establecimiento->id, false) ? '' : ' · SIN COORDENADAS' }}</option>@endforeach</select>
                                    <button class="btn btn-outline-primary w-100"><i class="bi bi-plus-circle"></i> Agregar a la ruta</button>
                                </form>
                            @else
                                <p class="text-muted small mb-0"><i class="bi bi-lock me-1"></i> La ruta queda bloqueada una vez publicada la jornada.</p>
                            @endif
                        </section>
                        <section class="va-route-panel">
                            <h3><i class="bi bi-signpost-split me-1"></i> Ruta de {{ $grupo->nombre }}</h3>
                            <div class="va-route-list">
                                @forelse($grupo->rutas as $ruta)
                                    @php($logo = $ruta->establecimiento->admisionPerfil?->logoUrl() ?? asset(config('brand.logo_principal', 'branding/01_logo_principal.png')))
                                    <div class="va-route-stop">
                                        <span class="va-route-stop__order">{{ $ruta->orden }}</span><span class="va-school-logo"><img src="{{ $logo }}" alt=""></span>
                                        <div><strong>{{ $ruta->establecimiento->nombre_establecimiento }}</strong><small>RBD {{ $ruta->establecimiento->rbd }} · {{ $ruta->establecimiento->comuna }} · @if($coordenadasValidas->get($ruta->establecimiento_id, false))<span class="text-success">Coordenadas válidas</span>@else<span class="text-danger">Sin coordenadas</span>@endif</small></div>
                                        <div class="va-route-stop__actions">
                                            @if($jornada->estado === 'borrador')
                                                <form method="POST" action="{{ route('votaciones.admin.rutas.mover', $ruta) }}">@csrf @method('PATCH')<input type="hidden" name="direccion" value="subir"><button class="btn btn-outline-secondary" title="Subir parada" aria-label="Subir {{ $ruta->establecimiento->nombre_establecimiento }}"><i class="bi bi-arrow-up"></i></button></form>
                                                <form method="POST" action="{{ route('votaciones.admin.rutas.mover', $ruta) }}">@csrf @method('PATCH')<input type="hidden" name="direccion" value="bajar"><button class="btn btn-outline-secondary" title="Bajar parada" aria-label="Bajar {{ $ruta->establecimiento->nombre_establecimiento }}"><i class="bi bi-arrow-down"></i></button></form>
                                                <form method="POST" action="{{ route('votaciones.admin.rutas.destroy', $ruta) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger" title="Quitar parada" aria-label="Quitar {{ $ruta->establecimiento->nombre_establecimiento }}" onclick="return confirm('¿Quitar este establecimiento de la ruta?')"><i class="bi bi-x-lg"></i></button></form>
                                            @else
                                                <span class="va-status va-status--{{ $ruta->visita?->estado ?? 'pendiente' }}">{{ str($ruta->visita?->estado ?? 'pendiente')->replace('_', ' ') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="va-empty py-3" style="min-height:130px"><div><i class="bi bi-signpost"></i><h3>Ruta vacía</h3><p>Agrega establecimientos para construir el recorrido.</p></div></div>
                                @endforelse
                            </div>
                        </section>
                    </div>
                </div>
            </article>
        @empty
            <section class="va-card va-empty"><div><i class="bi bi-people"></i><h2>Aún no hay grupos configurados</h2><p>Crea un equipo, asigna su responsable y luego construye su ruta.</p></div></section>
        @endforelse
    </div>

    <div class="va-section-grid mt-4">
        <section class="va-card"><div class="va-card__header"><div><h2>Incidencias recientes</h2><p>Información operacional registrada durante la jornada.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('votaciones.admin.incidencias.index', ['jornada' => $jornada->slug]) }}">Ver todas</a></div><div class="va-card__body">
            @forelse($jornada->incidencias->take(4) as $incidencia)<article class="va-incident"><div class="va-incident__head"><h3>{{ config('votaciones.tipos_incidencia.'.$incidencia->tipo, $incidencia->tipo) }}</h3><span class="va-status va-status--{{ $incidencia->estado }}">{{ $incidencia->estado }}</span></div><p>{{ $incidencia->detalle_interno }}</p><small class="text-muted">{{ $incidencia->grupo?->nombre ?? 'General' }} · {{ $incidencia->created_at->format('d-m-Y H:i') }}</small></article>@empty<div class="va-empty py-3" style="min-height:150px"><div><i class="bi bi-check-circle"></i><h3>Sin incidencias</h3><p>No existen incidencias registradas para esta jornada.</p></div></div>@endforelse
        </div></section>
        <section class="va-card"><div class="va-card__header"><div><h2>Últimos movimientos</h2><p>Bitácora operacional inmutable.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('votaciones.admin.bitacora.index', ['jornada' => $jornada->slug]) }}">Ver bitácora</a></div><div class="va-card__body"><div class="va-timeline">
            @forelse($jornada->bitacora->take(5) as $evento)<article class="va-timeline__item"><time class="va-timeline__time">{{ $evento->created_at->timezone(config('votaciones.timezone'))->format('H:i') }}<br>{{ $evento->created_at->format('d-m') }}</time><span class="va-timeline__dot"></span><div class="va-timeline__content"><strong>{{ str($evento->evento)->replace('_', ' ')->title() }}</strong><p>{{ $evento->descripcion }}</p><small>{{ $evento->usuario?->display_name ?? 'Sistema' }}</small></div></article>@empty<div class="va-empty py-3" style="min-height:150px"><div><i class="bi bi-clock-history"></i><h3>Sin movimientos</h3></div></div>@endforelse
        </div></div></section>
    </div>

    @can('votaciones.admin')
        <section class="va-card mt-4"><div class="va-card__header"><div><h2><i class="bi bi-shield-exclamation text-warning me-1"></i> Correcciones administrativas</h2><p>Acciones sensibles que quedan registradas en la bitácora.</p></div></div><div class="va-card__body"><div class="va-warning mb-3"><i class="bi bi-exclamation-diamond fs-4"></i><div><strong>Esta acción modificará información operacional registrada.</strong><div>Verifica el valor actual, indica el nuevo valor y registra un motivo claro.</div></div></div>
            <details class="mb-3"><summary class="btn btn-outline-warning">Corregir jornada</summary><form method="POST" action="{{ route('votaciones.admin.jornadas.corregir', $jornada) }}" class="row g-3 mt-2" data-disable-on-submit>@csrf<div class="col-md-3"><label class="form-label">Campo</label><select class="form-select" name="campo" required><option value="estado">Estado</option><option value="iniciada_at">Fecha/hora inicio</option><option value="finalizada_at">Fecha/hora término</option></select></div><div class="col-md-3"><label class="form-label">Valor actual</label><input class="form-control" value="{{ $jornada->estado }}" disabled></div><div class="col-md-3"><label class="form-label">Nuevo valor</label><input class="form-control" name="valor" required></div><div class="col-md-3"><label class="form-label">Motivo</label><input class="form-control" name="motivo" required maxlength="1000"></div><div class="col-12 text-end"><button class="btn btn-warning" onclick="return confirm('¿Confirmar la corrección administrativa?')">Registrar corrección</button></div></form></details>
            <details><summary class="btn btn-outline-warning">Corregir una visita</summary><div class="mt-3">@foreach($jornada->grupos as $grupo)@foreach($grupo->rutas as $ruta)@if($ruta->visita)<form class="row g-2 align-items-end border rounded-3 p-2 mb-2" method="POST" action="{{ route('votaciones.admin.visitas.corregir', $ruta->visita) }}" data-disable-on-submit>@csrf<div class="col-12"><strong>{{ $grupo->nombre }} · {{ $ruta->establecimiento->nombre_establecimiento }}</strong> <span class="va-status va-status--{{ $ruta->visita->estado }}">{{ str($ruta->visita->estado)->replace('_', ' ') }}</span></div><div class="col-md-3"><label class="form-label">Campo</label><select class="form-select" name="campo"><option value="estado">Estado</option><option value="inicio_traslado_at">Inicio traslado</option><option value="inicio_votacion_at">Inicio votación</option><option value="fin_votacion_at">Fin votación</option></select></div><div class="col-md-3"><label class="form-label">Nuevo valor</label><input class="form-control" name="valor" required></div><div class="col-md-4"><label class="form-label">Motivo</label><input class="form-control" name="motivo" required maxlength="1000"></div><div class="col-md-2 d-grid"><button class="btn btn-warning" onclick="return confirm('¿Confirmar la corrección de esta visita?')">Corregir</button></div></form>@endif @endforeach @endforeach</div></details>
        </div></section>
    @endcan

    @if($jornada->estado === 'borrador')
        <div class="modal fade va-modal" id="modal-crear-grupo" tabindex="-1" aria-labelledby="titulo-crear-grupo" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title h5" id="titulo-crear-grupo">Crear grupo operativo</h2><p class="text-muted small mb-0">Asigna responsable e integrantes antes de construir la ruta.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><form method="POST" action="{{ route('votaciones.admin.grupos.store', $jornada) }}" data-disable-on-submit>@csrf<div class="modal-body"><div class="row g-3"><div class="col-sm-3"><label class="form-label">Número</label><input class="form-control" type="number" min="1" name="numero" value="{{ old('numero', $jornada->grupos->max('numero') + 1) }}" required></div><div class="col-sm-9"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="{{ old('nombre') }}" placeholder="Grupo 1" required maxlength="255"></div><div class="col-12"><label class="form-label">Encargado</label><select class="form-select" name="encargado_id" required><option value="">Seleccione una persona</option>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}">{{ $usuario->display_name }} · {{ $usuario->email }}</option>@endforeach</select></div><div class="col-12"><label class="form-label">Integrantes / Ministros de Fe</label><select class="form-select" name="miembros[]" multiple size="5">@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}">{{ $usuario->display_name }} · {{ $usuario->email }}</option>@endforeach</select></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear grupo</button></div></form></div></div></div>
    @endif
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite(['resources/js/votaciones-admin.js', 'resources/js/votaciones-admin-rutas.js'])
@endpush
