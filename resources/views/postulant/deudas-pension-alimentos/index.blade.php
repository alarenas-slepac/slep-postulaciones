@extends('layouts.app')

@include('deudas-pension-alimentos._styles')

@section('content')
    <div class="container py-4">
        <div class="dpa-header mb-4">
            <div class="dpa-header__top"><div><div class="dpa-eyebrow"><span class="dpa-eyebrow__icon"><i class="bi bi-file-earmark-lock"></i></span> Postulante · Reemplazos</div><h1 class="dpa-title">Deuda de pensión de alimentos</h1><p class="dpa-subtitle">Consulta las solicitudes asociadas y carga la resolución o dictamen actualizado requerido para continuar el proceso.</p></div><span class="dpa-role-pill"><i class="bi bi-person"></i> Mi expediente</span></div>
            <div class="dpa-summary"><div class="dpa-summary__item"><div class="dpa-summary__label">Casos asociados</div><div class="dpa-summary__value">{{ $deudas?->total() ?? 0 }}</div></div><div class="dpa-summary__item"><div class="dpa-summary__label">Condición registrada</div><div class="dpa-summary__value fs-6">{{ $profile?->deudor_pension_alimentos ? 'Deudor registrado' : 'Sin registro activo' }}</div></div><div class="dpa-summary__item"><div class="dpa-summary__label">Acción requerida</div><div class="dpa-summary__value fs-6">Resolución y cuota vigente</div></div></div>
        </div>

        @if (!$profile)
            <div class="alert alert-warning">Tu cuenta no tiene un perfil de postulante asociado.</div>
        @else
            <div class="dpa-panel">
                <div class="dpa-panel__header"><div class="dpa-panel__title">Solicitudes asociadas</div><p class="dpa-panel__subtitle">Sólo se muestran expedientes vinculados a tu perfil.</p></div>
                <div class="table-responsive"><table class="table dpa-table align-middle mb-0"><thead><tr><th>Solicitud</th><th>Establecimiento</th><th>Estado solicitud</th><th>Estado expediente</th><th>Activación</th><th class="text-end">Acción</th></tr></thead><tbody>
                    @forelse ($deudas as $deuda)
                        @php $estado = $deuda->estadoFlujo(); @endphp
                        <tr><td class="fw-semibold">{{ $deuda->solicitud?->numero_solicitud ?? $deuda->solicitud_reemplazo_id }}</td><td>{{ $deuda->solicitud?->establecimiento?->nombre_establecimiento ?? '—' }}</td><td>{{ ucfirst(str_replace('_', ' ', (string) $deuda->solicitud?->estado)) }}</td><td><span class="dpa-status dpa-status--{{ $estado === \App\Models\SolicitudReemplazoDeudaPension::ESTADO_ENVIADO ? 'success' : ($estado === \App\Models\SolicitudReemplazoDeudaPension::ESTADO_LISTO_ENVIO ? 'info' : 'warning') }}">{{ \App\Models\SolicitudReemplazoDeudaPension::estados()[$estado] ?? $estado }}</span></td><td>{{ optional($deuda->activado_at)->format('d/m/Y H:i') ?? '—' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('postulant.deudas-pension-alimentos.show', $deuda) }}"><i class="bi bi-eye"></i> Ver</a></td></tr>
                    @empty
                        <tr><td colspan="6"><div class="dpa-empty"><i class="bi bi-check-circle fs-2 d-block mb-2"></i>No tienes expedientes de deuda asociados.</div></td></tr>
                    @endforelse
                </tbody></table></div>
                @if ($deudas->hasPages())<div class="dpa-panel__body border-top">{{ $deudas->links() }}</div>@endif
            </div>
        @endif
    </div>
@endsection
