@extends('layouts.app')

@push('styles')
    <style>
        .ip-hero, .ip-panel { border: 1px solid #d9e4f3; background: #fff; box-shadow: 0 14px 34px rgba(15,23,42,.06); }
        .ip-hero { border-radius: 24px; overflow: hidden; background: linear-gradient(135deg,#fff 0%,#f1f7ff 100%); }
        .ip-hero__body { padding: 1.6rem 1.75rem; display: flex; align-items: start; justify-content: space-between; gap: 1rem; }
        .ip-eyebrow { color: #1d4ed8; font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .45rem; }
        .ip-title { color: #0f172a; font-size: clamp(1.65rem, 2vw, 2.15rem); font-weight: 800; margin: 0 0 .4rem; }
        .ip-subtitle { color: #475569; margin: 0; max-width: 48rem; }
        .ip-primary, .ip-secondary { min-height: 46px; border-radius: 13px; padding: .7rem 1rem; display: inline-flex; align-items: center; justify-content: center; gap: .5rem; font-weight: 800; text-decoration: none; }
        .ip-primary { color: #fff; background: #1d4ed8; border: 1px solid #1d4ed8; box-shadow: 0 10px 20px rgba(37,99,235,.22); }
        .ip-primary:hover { color: #fff; background: #1e40af; }
        .ip-secondary { color: #1d4ed8; background: #fff; border: 1px solid #bfdbfe; }
        .ip-secondary:hover { color: #1e40af; background: #eff6ff; }
        .ip-stats { padding: 0 1.75rem 1.6rem; display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .85rem; }
        .ip-stat { border: 1px solid #dbe6f2; border-radius: 17px; background: rgba(255,255,255,.92); padding: 1rem; }
        .ip-stat__label { color: #64748b; text-transform: uppercase; letter-spacing: .05em; font-size: .75rem; font-weight: 800; }
        .ip-stat__value { color: #0f172a; font-size: 1.55rem; font-weight: 800; line-height: 1.2; margin-top: .3rem; }
        .ip-panel { border-radius: 20px; overflow: hidden; }
        .ip-panel__header { padding: 1.2rem 1.4rem; border-bottom: 1px solid #e8eef5; }
        .ip-panel__title { font-size: 1.18rem; font-weight: 800; color: #0f172a; margin: 0 0 .2rem; }
        .ip-panel__help { color: #64748b; margin: 0; font-size: .92rem; }
        .ip-filter { padding: 1.15rem 1.4rem; border-bottom: 1px solid #e8eef5; background: #fbfdff; }
        .ip-label { font-size: .86rem; color: #334155; font-weight: 800; margin-bottom: .35rem; }
        .ip-table th { background: #f8fafc; color: #334155; font-size: .83rem; text-transform: uppercase; letter-spacing: .025em; padding: .9rem; white-space: nowrap; }
        .ip-table td { padding: 1rem .9rem; vertical-align: middle; border-color: #e8eef5; }
        .ip-name { color: #0f172a; font-weight: 800; }
        .ip-meta { color: #64748b; font-size: .85rem; margin-top: .2rem; }
        .ip-status { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; padding: .36rem .68rem; font-size: .78rem; font-weight: 800; }
        .ip-status--solicitada { color: #92400e; background: #fef3c7; }
        .ip-status--resuelta { color: #166534; background: #dcfce7; }
        .ip-empty { padding: 3rem 1rem; text-align: center; color: #64748b; }
        @media (max-width: 991.98px) { .ip-stats { grid-template-columns: repeat(2,minmax(0,1fr)); } }
        @media (max-width: 767.98px) { .ip-hero__body { display: block; } .ip-hero__body .ip-primary { margin-top: 1rem; width: 100%; } .ip-stats { grid-template-columns: 1fr; padding: 0 1rem 1rem; } }
    </style>
@endpush

@section('content')
    <div class="container py-4">
        <section class="ip-hero mb-4">
            <div class="ip-hero__body">
                <div>
                    <div class="ip-eyebrow"><i class="bi bi-shield-check"></i> Trámites y operación</div>
                    <h1 class="ip-title">Idoneidad psicológica AAEE</h1>
                    <p class="ip-subtitle">Gestiona solicitudes para asistentes de la educación con contrato a plazo fijo, reemplazo o suplencia, tomando siempre el último padrón vigente de cada establecimiento.</p>
                </div>
                <a href="{{ route('tramites.idoneidad-psicologica.create') }}" class="ip-primary"><i class="bi bi-plus-circle"></i> Nueva solicitud</a>
            </div>
            <div class="ip-stats">
                <div class="ip-stat"><div class="ip-stat__label">Procesos registrados</div><div class="ip-stat__value">{{ number_format($resumen['procesos'], 0, ',', '.') }}</div></div>
                <div class="ip-stat"><div class="ip-stat__label">Solicitudes con pendientes</div><div class="ip-stat__value">{{ number_format($resumen['solicitadas'], 0, ',', '.') }}</div></div>
                <div class="ip-stat"><div class="ip-stat__label">Resultados completos</div><div class="ip-stat__value">{{ number_format($resumen['resueltas'], 0, ',', '.') }}</div></div>
                <div class="ip-stat"><div class="ip-stat__label">Personas por resolver</div><div class="ip-stat__value">{{ number_format($resumen['personas_pendientes'], 0, ',', '.') }}</div></div>
            </div>
        </section>

        <section class="ip-panel">
            <div class="ip-panel__header">
                <h2 class="ip-panel__title">Solicitudes de idoneidad</h2>
                <p class="ip-panel__help">Cada proceso conserva una nómina de respaldo; los resultados pueden registrarse uno a uno o mediante Excel.</p>
            </div>
            <form class="ip-filter" method="GET" action="{{ route('tramites.idoneidad-psicologica.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3"><label class="ip-label" for="estado">Estado</label><select class="form-select" name="estado" id="estado"><option value="">Todos</option>@foreach($estados as $key => $label)<option value="{{ $key }}" @selected(request('estado') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="ip-label" for="desde">Inicio desde</label><input class="form-control" id="desde" name="desde" type="date" value="{{ request('desde') }}"></div>
                    <div class="col-md-3"><label class="ip-label" for="hasta">Término hasta</label><input class="form-control" id="hasta" name="hasta" type="date" value="{{ request('hasta') }}"></div>
                    <div class="col-md-3 d-flex gap-2"><button class="ip-primary flex-grow-1" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="ip-secondary" href="{{ route('tramites.idoneidad-psicologica.index') }}" title="Limpiar filtros"><i class="bi bi-arrow-counterclockwise"></i></a></div>
                </div>
            </form>
            @if($solicitudes->isEmpty())
                <div class="ip-empty"><i class="bi bi-clipboard-x fs-2 d-block mb-2"></i>No hay solicitudes con los filtros seleccionados.</div>
            @else
                <div class="table-responsive"><table class="table ip-table mb-0"><thead><tr><th>Proceso</th><th>Período de ingresos</th><th>Nómina</th><th>Avance de resultados</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>
                    @foreach($solicitudes as $solicitud)
                        <tr>
                            <td><div class="ip-name">Solicitud #{{ $solicitud->id }}</div><div class="ip-meta">Solicitada {{ optional($solicitud->solicitada_at)->format('d-m-Y H:i') ?? 'sin fecha' }}@if($solicitud->solicitante) · {{ trim(($solicitud->solicitante->nombres ?? '').' '.($solicitud->solicitante->apellido_paterno ?? '')) }}@endif</div></td>
                            <td>{{ optional($solicitud->fecha_inicio)->format('d-m-Y') }} <span class="text-muted">al</span> {{ optional($solicitud->fecha_termino)->format('d-m-Y') }}</td>
                            <td><strong>{{ number_format($solicitud->funcionarios_count, 0, ',', '.') }}</strong> personas</td>
                            <td><span class="text-success fw-bold">{{ $solicitud->funcionarios_aceptados_count }} aceptados</span><span class="text-muted"> · </span><span class="text-danger fw-bold">{{ $solicitud->funcionarios_rechazados_count }} rechazados</span><div class="ip-meta">{{ $solicitud->funcionarios_solicitados_count }} pendiente(s)</div></td>
                            <td><span class="ip-status ip-status--{{ $solicitud->estado }}"><i class="bi {{ $solicitud->estado === 'resuelta' ? 'bi-check2-circle' : 'bi-hourglass-split' }}"></i>{{ $estados[$solicitud->estado] ?? $solicitud->estado }}</span></td>
                            <td class="text-end"><a class="ip-secondary" href="{{ route('tramites.idoneidad-psicologica.show', $solicitud) }}"><i class="bi bi-arrow-right"></i> Abrir</a></td>
                        </tr>
                    @endforeach
                </tbody></table></div>
                <div class="p-3 border-top">{{ $solicitudes->links() }}</div>
            @endif
        </section>
    </div>
@endsection
