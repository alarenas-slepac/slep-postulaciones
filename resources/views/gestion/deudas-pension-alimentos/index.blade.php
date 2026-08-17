@extends('layouts.app')

@include('deudas-pension-alimentos._styles')

@section('content')
    @php
        $enviadas = $deudas->getCollection()->filter(fn ($deuda) => $deuda->estadoFlujo() === \App\Models\SolicitudReemplazoDeudaPension::ESTADO_ENVIADO)->count();
        $listas = $deudas->getCollection()->filter(fn ($deuda) => $deuda->estadoFlujo() === \App\Models\SolicitudReemplazoDeudaPension::ESTADO_LISTO_ENVIO)->count();
        $tone = fn ($estado) => match ($estado) {
            \App\Models\SolicitudReemplazoDeudaPension::ESTADO_ENVIADO => 'success',
            \App\Models\SolicitudReemplazoDeudaPension::ESTADO_LISTO_ENVIO => 'info',
            \App\Models\SolicitudReemplazoDeudaPension::ESTADO_PENDIENTE_DOCUMENTOS => 'danger',
            default => 'warning',
        };
    @endphp

    <div class="container py-4">
        <div class="dpa-header mb-4">
            <div class="dpa-header__top">
                <div>
                    <div class="dpa-eyebrow"><span class="dpa-eyebrow__icon"><i class="bi bi-shield-exclamation"></i></span> Reemplazos · Gestión SLEP</div>
                    <h1 class="dpa-title">Deuda de pensión de alimentos</h1>
                    <p class="dpa-subtitle">Bandeja de postulantes marcados como deudores, documentos pendientes y expedientes enviados a Remuneraciones.</p>
                </div>
                <span class="dpa-role-pill"><i class="bi bi-person-badge"></i> {{ \App\Support\SlepUiRegistry::roleLabel(auth()->user()?->activeRoleName()) }}</span>
            </div>
            <div class="dpa-summary">
                <div class="dpa-summary__item"><div class="dpa-summary__label">Registros visibles</div><div class="dpa-summary__value">{{ $deudas->total() }}</div></div>
                <div class="dpa-summary__item"><div class="dpa-summary__label">Listos para enviar en esta página</div><div class="dpa-summary__value">{{ $listas }}</div></div>
                <div class="dpa-summary__item"><div class="dpa-summary__label">Enviados en esta página</div><div class="dpa-summary__value">{{ $enviadas }}</div></div>
            </div>
        </div>

        <form method="GET" class="dpa-panel mb-4">
            <div class="dpa-panel__header"><div class="dpa-panel__title">Buscar expediente</div><p class="dpa-panel__subtitle">Consulta por número de solicitud, nombre o RUT del postulante.</p></div>
            <div class="dpa-panel__body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-9"><label for="q" class="form-label fw-semibold">Búsqueda</label><input id="q" name="q" class="form-control" value="{{ request('q') }}" placeholder="Ej.: SR-2026-001, 12.345.678-9 o nombre"></div>
                    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Buscar</button><a class="btn btn-outline-secondary" href="{{ route('gestion.deudas-pension-alimentos.index') }}">Limpiar</a></div>
                </div>
            </div>
        </form>

        <div class="dpa-panel">
            <div class="dpa-panel__header"><div class="dpa-panel__title">Expedientes de deuda</div><p class="dpa-panel__subtitle">La solicitud permanece en Derivada SLEP hasta que el expediente sea enviado.</p></div>
            <div class="table-responsive">
                <table class="table dpa-table align-middle mb-0">
                    <thead><tr><th>Solicitud</th><th>Postulante</th><th>RUT</th><th>Estado solicitud</th><th>Estado deuda</th><th>Activación</th><th class="text-end">Acción</th></tr></thead>
                    <tbody>
                        @forelse ($deudas as $deuda)
                            @php $estado = $deuda->estadoFlujo(); @endphp
                            <tr>
                                <td class="fw-semibold">{{ $deuda->solicitud?->numero_solicitud ?? $deuda->solicitud_reemplazo_id }}</td>
                                <td><div class="fw-semibold">{{ $deuda->postulante?->user?->full_name ?? '—' }}</div><div class="small text-muted">{{ $deuda->solicitud?->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento' }}</div></td>
                                <td class="text-nowrap">{{ \App\Support\Rut::format($deuda->postulante?->user?->rut) ?? '—' }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', (string) $deuda->solicitud?->estado)) }}</td>
                                <td><span class="dpa-status dpa-status--{{ $tone($estado) }}">{{ \App\Models\SolicitudReemplazoDeudaPension::estados()[$estado] ?? $estado }}</span></td>
                                <td><div>{{ optional($deuda->activado_at)->format('d/m/Y H:i') ?? '—' }}</div><div class="small text-muted">{{ $deuda->activadoPor?->full_name ?? '—' }}</div></td>
                                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.deudas-pension-alimentos.show', $deuda) }}"><i class="bi bi-eye"></i> Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="dpa-empty"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No se encontraron expedientes de deuda.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($deudas->hasPages())<div class="dpa-panel__body border-top">{{ $deudas->links() }}</div>@endif
        </div>
    </div>
@endsection
