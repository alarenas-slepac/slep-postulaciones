@extends('layouts.app')

@push('styles')
<style>
    .ar-page-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);overflow:hidden}.ar-header-top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1.5rem 1.75rem}.ar-eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.45rem}.ar-icon{width:2.75rem;height:2.75rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 100%);color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.28);font-size:1.2rem}.ar-title{font-size:clamp(1.7rem,2vw,2.2rem);line-height:1.1;font-weight:800;color:#0f172a;margin-bottom:.4rem}.ar-subtitle{color:#475569;font-size:1rem;margin-bottom:0;max-width:60rem}.ar-summary-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;padding:1.2rem 1.75rem 1.75rem;border-top:1px solid #e5edf6;background:linear-gradient(180deg,#fcfdff 0%,#f8fbff 100%)}.ar-summary-card{border:1px solid #e2e8f0;border-radius:18px;background:#fff;padding:1rem}.ar-summary-label{color:#64748b;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.ar-summary-value{color:#0f172a;font-weight:850;font-size:1.65rem;line-height:1;margin-top:.45rem}.ar-calendar{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:.75rem}.ar-weekday{text-align:center;color:#64748b;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.ar-day{min-height:145px;border:1px solid #e2e8f0;border-radius:18px;background:#fff;padding:.75rem;display:flex;flex-direction:column;gap:.45rem}.ar-day-muted{background:#f8fafc;color:#94a3b8}.ar-day-today{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}.ar-day-number{font-weight:850;color:#0f172a}.ar-event{display:block;text-decoration:none;border-radius:12px;padding:.38rem .5rem;color:#fff;font-size:.75rem;line-height:1.15;box-shadow:0 6px 16px rgba(15,23,42,.10)}.ar-event:hover{color:#fff;opacity:.92}.ar-event.bg-success{background:#16a34a!important}.ar-event.bg-primary{background:#2563eb!important}.ar-event.bg-warning{background:#f59e0b!important;color:#111827}.ar-event.bg-danger{background:#dc2626!important}.ar-event.bg-secondary{background:#64748b!important}@media(max-width:992px){.ar-summary-strip{grid-template-columns:repeat(2,minmax(0,1fr))}.ar-calendar{grid-template-columns:1fr}.ar-weekday{display:none}}
</style>
@endpush

@section('content')
@php
    $prev = $fechaBase->copy()->subMonth()->format('Y-m');
    $next = $fechaBase->copy()->addMonth()->format('Y-m');
    $today = now()->toDateString();
    $cursor = $inicioMes->copy()->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $calendarEnd = $finMes->copy()->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
@endphp

<div class="container-fluid py-3">
    <div class="ar-page-header mb-4">
        <div class="ar-header-top">
            <div>
                <div class="ar-eyebrow"><span class="ar-icon"><i class="bi bi-calendar-event"></i></span> Gestión y control</div>
                <h1 class="ar-title">Agendamiento Proyector y Salas de Reuniones</h1>
                <p class="ar-subtitle">Calendario mensual con filtros por sala/recurso, control de disponibilidad y flujo de aprobación para salas configuradas.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <a href="{{ route('gestion.agendamientos-recursos.index', array_merge(request()->except('month'), ['month' => $prev])) }}" class="btn btn-outline-primary"><i class="bi bi-chevron-left"></i> Mes anterior</a>
                <a href="{{ route('gestion.agendamientos-recursos.create', ['fecha' => now()->format('Y-m-d'), 'recurso_id' => $filtros['recurso_id']]) }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Nuevo agendamiento</a>
                @if($puedeAdministrarRecursos)
                    <a href="{{ route('gestion.agendamientos-recursos.recursos.index') }}" class="btn btn-outline-dark"><i class="bi bi-gear me-1"></i> Salas</a>
                @endif
                <a href="{{ route('gestion.agendamientos-recursos.index', array_merge(request()->except('month'), ['month' => $next])) }}" class="btn btn-outline-primary">Mes siguiente <i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
        <div class="ar-summary-strip">
            <div class="ar-summary-card"><div class="ar-summary-label">Total mes</div><div class="ar-summary-value">{{ $resumen['total_mes'] }}</div></div>
            <div class="ar-summary-card"><div class="ar-summary-label">Pendientes</div><div class="ar-summary-value">{{ $resumen['pendientes'] }}</div></div>
            <div class="ar-summary-card"><div class="ar-summary-label">Aprobados/Vigentes</div><div class="ar-summary-value">{{ $resumen['aprobados'] }}</div></div>
            <div class="ar-summary-card"><div class="ar-summary-label">Salas</div><div class="ar-summary-value">{{ $resumen['salas'] }}</div></div>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="month" value="{{ $fechaBase->format('Y-m') }}">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Sala / recurso</label>
                    <select name="recurso_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($recursosCatalogo as $recurso)
                            <option value="{{ $recurso->id }}" @selected((int) $filtros['recurso_id'] === (int) $recurso->id)>{{ $recurso->nombre }} @if($recurso->requiere_aprobacion) · requiere aprobación @endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="activos" @selected($filtros['estado'] === 'activos')>Activos, pendientes y aprobados</option>
                        <option value="todos" @selected($filtros['estado'] === 'todos')>Todos</option>
                        @foreach($estados as $value => $label)
                            <option value="{{ $value }}" @selected($filtros['estado'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                    <a class="btn btn-outline-secondary" href="{{ route('gestion.agendamientos-recursos.index', ['month' => $fechaBase->format('Y-m')]) }}">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-9">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0 text-capitalize">{{ $fechaBase->translatedFormat('F Y') }}</h2>
                    <a href="{{ route('gestion.agendamientos-recursos.index') }}" class="btn btn-sm btn-outline-secondary">Mes actual</a>
                </div>
                <div class="card-body p-4">
                    <div class="ar-calendar mb-2">
                        @foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $dia)
                            <div class="ar-weekday">{{ $dia }}</div>
                        @endforeach
                        @while($cursor->lte($calendarEnd))
                            @php
                                $dayKey = $cursor->toDateString();
                                $items = $agendamientosPorDia->get($dayKey, collect());
                                $isCurrentMonth = $cursor->month === $fechaBase->month;
                            @endphp
                            <div class="ar-day {{ $isCurrentMonth ? '' : 'ar-day-muted' }} {{ $dayKey === $today ? 'ar-day-today' : '' }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="ar-day-number">{{ $cursor->format('d') }}</span>
                                    <a href="{{ route('gestion.agendamientos-recursos.create', ['fecha' => $dayKey, 'recurso_id' => $filtros['recurso_id']]) }}" class="small text-decoration-none" title="Nuevo"><i class="bi bi-plus-circle"></i></a>
                                </div>
                                @forelse($items as $item)
                                    <a href="{{ route('gestion.agendamientos-recursos.show', $item) }}" class="ar-event bg-{{ $item->badge_class }}">
                                        <strong>{{ substr((string) $item->hora_inicio, 0, 5) }}</strong> {{ $item->tipo_recurso_label }}<br>
                                        <span>{{ \Illuminate\Support\Str::limit($item->titulo, 28) }}</span>
                                        @if($item->estado === 'pendiente')<br><span>Pendiente</span>@endif
                                    </a>
                                @empty
                                    <span class="text-muted small mt-auto">Sin reservas</span>
                                @endforelse
                            </div>
                            @php $cursor->addDay(); @endphp
                        @endwhile
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 p-4"><h2 class="h6 mb-0">Próximos agendamientos</h2></div>
                <div class="card-body p-4">
                    @forelse($proximos as $item)
                        <a href="{{ route('gestion.agendamientos-recursos.show', $item) }}" class="d-block text-decoration-none border rounded-3 p-3 mb-2">
                            <div class="d-flex justify-content-between"><strong>{{ $item->fecha?->format('d-m-Y') }}</strong><span class="badge text-bg-{{ $item->badge_class }}">{{ $item->estado_label }}</span></div>
                            <div class="small text-muted">{{ $item->horario }}</div>
                            <div class="small fw-semibold">{{ $item->tipo_recurso_label }}</div>
                            <div class="small text-dark">{{ \Illuminate\Support\Str::limit($item->titulo, 45) }}</div>
                        </a>
                    @empty
                        <p class="text-muted mb-0">No hay agendamientos próximos.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
