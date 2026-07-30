@extends('layouts.app')

@push('styles')
<style>
    .badge-applicable-dark {
        background-color: #198754 !important;
        color: #fff !important;
    }
    .text-applicable-dark {
        color: #146c43 !important;
    }
</style>
@endpush

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Detalle de cálculo de endeudamiento</h1>
            <p class="text-muted mb-0">Registro individual con prelación aplicada y descuentos marcados para mantener o eliminar.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Volver</a>
            <a href="{{ route('endeudamiento.topes.export-pdf', array_merge(request()->query(), ['maeRegistro' => $analysis['registro']->id])) }}" class="btn btn-danger" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i> Exportar PDF</a>
            <a href="{{ route('endeudamiento.registros.show', $analysis['registro']->id) }}" class="btn btn-outline-primary">Ver registro base</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first('general') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Período</div><div class="fw-semibold">{{ sprintf('%02d/%04d', $analysis['registro']->mes, $analysis['registro']->anio) }}</div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Dominio</div><div class="fw-semibold">{{ $analysis['registro']->dominio }}</div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">RUT-DV</div><div class="fw-semibold">{{ $analysis['registro']->rut_dv }}</div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Versión</div><div class="fw-semibold">v{{ $analysis['registro']->carga?->version }}{{ $analysis['registro']->carga?->es_vigente ? ' vigente' : '' }}</div></div></div></div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">{{ $analysis['registro']->nombre_completo }}</h2>
            <div class="row g-3">
                <div class="col-md-2"><div class="text-muted small">Total haberes</div><div class="fw-semibold">${{ number_format($analysis['base_calculo'], 0, ',', '.') }}</div></div>
                <div class="col-md-2"><div class="text-muted small">Máx. 45%</div><div class="fw-semibold text-primary">${{ number_format($analysis['monto_maximo_endeudamiento'], 0, ',', '.') }}</div></div>
                <div class="col-md-2"><div class="text-muted small">Total descuentos</div><div class="fw-semibold">${{ number_format($analysis['total_descuentos'], 0, ',', '.') }}</div></div>
                <div class="col-md-2"><div class="text-muted small">% descuento</div><div class="fw-semibold">{{ number_format($analysis['porcentaje_total_descuento'], 2, ',', '.') }}%</div></div>
                <div class="col-md-2"><div class="text-muted small">Monto excedido</div><div class="fw-semibold text-danger">${{ number_format($analysis['monto_excedido'], 0, ',', '.') }}</div></div>
                <div class="col-md-2"><div class="text-muted small">Estado</div>
                    @if ($analysis['estado'] === 'cumple')
                        <span class="badge text-bg-success">Dentro de tope</span>
                    @elseif ($analysis['estado'] === 'excede_tope')
                        <span class="badge text-bg-danger">Con exceso</span>
                    @else
                        <span class="badge text-bg-warning">Revisión</span>
                    @endif
                </div>
            </div>
            @if (!empty($analysis['observaciones']))
                <div class="alert alert-secondary mt-3 mb-0 small">
                    {{ implode(' | ', $analysis['observaciones']) }}
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card shadow-sm border-success"><div class="card-body"><div class="text-muted small">Aplicable</div><div class="h5 mb-0 text-applicable-dark">${{ number_format($analysis['totales']['aplicable_total'], 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm border-danger"><div class="card-body"><div class="text-muted small">No aplicable</div><div class="h5 mb-0 text-danger">${{ number_format($analysis['totales']['no_aplicable_total'], 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm border-secondary"><div class="card-body"><div class="text-muted small">Patronal excluido</div><div class="h5 mb-0 text-secondary">${{ number_format($analysis['totales']['patronal'], 0, ',', '.') }}</div></div></div></div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light fw-semibold">Descuentos legales base MAE</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><div class="text-muted small">Imposiciones</div><div class="fw-semibold">${{ number_format($analysis['descuentos_legales']['imposiciones'] ?? 0, 0, ',', '.') }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Salud</div><div class="fw-semibold">${{ number_format($analysis['descuentos_legales']['salud'] ?? 0, 0, ',', '.') }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Impuesto</div><div class="fw-semibold">${{ number_format($analysis['descuentos_legales']['impuesto'] ?? 0, 0, ',', '.') }}</div></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light fw-semibold">Detalle de descuentos</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Descuento / columna</th>
                        <th>Grupo</th>
                        <th>Subgrupo</th>
                        <th>Prioridad</th>
                        <th>Monto</th>
                        <th>Cuota</th>
                        <th>Aplicable</th>
                        <th>No aplicable</th>
                        <th>Estado</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($analysis['detalles'] as $detalle)
                        <tr class="{{ $detalle['estado_aplicacion'] === 'eliminar' ? ($detalle['primero_sobre_tope'] ? 'table-warning' : 'table-danger') : ($detalle['estado_aplicacion'] === 'revision' ? 'table-warning' : ($detalle['estado_aplicacion'] === 'patronal_excluido' ? 'table-secondary' : '')) }}">
                            <td>{{ $detalle['orden_resolucion'] }}</td>
                            <td>{{ $detalle['columna_origen'] }}</td>
                            <td>{{ $detalle['grupo'] ?: '—' }}</td>
                            <td>{{ $detalle['subgrupo'] ?: '—' }}</td>
                            <td>{{ $detalle['prioridad_label'] }}</td>
                            <td>${{ number_format($detalle['valor_original'], 0, ',', '.') }}</td>
                            <td>
                                @if ($detalle['cuota_label'])
                                    <span class="badge text-bg-info">{{ $detalle['cuota_label'] }}</span>
                                    @if ($detalle['mes_inicio_cuota_label'])
                                        <div class="small text-muted mt-1">Inicio calculado: {{ $detalle['mes_inicio_cuota_label'] }}</div>
                                    @elseif (($detalle['cuota_actual'] ?? null) === 0)
                                        <div class="small text-muted mt-1">Sin fecha de inicio calculable.</div>
                                    @endif
                                    @if ($detalle['cuota_observacion'])<div class="small text-muted mt-1">{{ $detalle['cuota_observacion'] }}</div>@endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-applicable-dark">${{ number_format($detalle['valor_aplicable'], 0, ',', '.') }}</td>
                            <td class="text-danger">${{ number_format($detalle['valor_no_aplicable'], 0, ',', '.') }}</td>
                            <td>
                                @if ($detalle['estado_aplicacion'] === 'aplicar')
                                    <span class="badge badge-applicable-dark">Aplicar</span>
                                @elseif ($detalle['estado_aplicacion'] === 'patronal_excluido')
                                    <span class="badge text-bg-secondary">Patronal</span>
                                @elseif ($detalle['estado_aplicacion'] === 'revision')
                                    <span class="badge text-bg-warning">Revisión</span>
                                @else
                                    <span class="badge text-bg-danger">Eliminar</span>
                                @endif
                            </td>
                            <td class="small">{{ $detalle['motivo'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
