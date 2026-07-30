@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Detalle MAE · {{ $maeRegistro->nombre_completo }}</h1>
            <p class="text-muted mb-0">{{ $maeRegistro->rut_dv }} · {{ sprintf('%02d/%04d', $maeRegistro->mes, $maeRegistro->anio) }} · {{ $maeRegistro->dominio }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endeudamiento.cargas.show', $maeRegistro->carga) }}" class="btn btn-outline-primary">Ver carga</a>
            <a href="{{ route('endeudamiento.registros.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Base remuneracional</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-7">Monto imponible</dt><dd class="col-5">{{ number_format((float) $maeRegistro->monto_imponible, 0, ',', '.') }}</dd>
                        <dt class="col-7">Monto tributable</dt><dd class="col-5">{{ number_format((float) $maeRegistro->monto_tributable, 0, ',', '.') }}</dd>
                        <dt class="col-7">Imposiciones</dt><dd class="col-5">{{ number_format((float) $maeRegistro->imposiciones, 0, ',', '.') }}</dd>
                        <dt class="col-7">Salud</dt><dd class="col-5">{{ number_format((float) $maeRegistro->salud, 0, ',', '.') }}</dd>
                        <dt class="col-7">Impuesto</dt><dd class="col-5">{{ number_format((float) $maeRegistro->impuesto, 0, ',', '.') }}</dd>
                        <dt class="col-7">Total descuentos homologados</dt><dd class="col-5">{{ number_format((float) $maeRegistro->total_descuentos_homologados, 0, ',', '.') }}</dd>
                        <dt class="col-7">Total aportes patronales</dt><dd class="col-5">{{ number_format((float) $maeRegistro->total_aportes_patronales, 0, ',', '.') }}</dd>
                        <dt class="col-7">Total otros descuentos</dt><dd class="col-5">{{ number_format((float) $maeRegistro->total_otros_descuentos, 0, ',', '.') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Datos del trabajador</h2>
                    <div class="row row-cols-1 row-cols-md-2 g-2 small">
                        @foreach (($maeRegistro->datos_trabajador_json ?? []) as $label => $value)
                            <div class="col">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted">{{ $label }}</div>
                                    <div class="fw-semibold">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Descuentos y aportes homologados</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Columna origen</th>
                                    <th>Campo canónico</th>
                                    <th>Grupo</th>
                                    <th>Tipo</th>
                                    <th>Cuota</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($maeRegistro->descuentos as $item)
                                    <tr>
                                        <td>{{ $item->columna_origen }}</td>
                                        <td>{{ $item->campo_canonico }}</td>
                                        <td>{{ $item->grupo }}</td>
                                        <td>{{ $item->tipo_movimiento }}</td>
                                        <td>
                                            @php($cuotaEtiqueta = $item->cuotaEtiqueta())
                                            @if ($cuotaEtiqueta)
                                                @php($mesInicioCuota = $item->mesInicioCuotaEtiqueta((int) $maeRegistro->anio, (int) $maeRegistro->mes))
                                                <span class="badge text-bg-info">{{ $cuotaEtiqueta }}</span>
                                                @if ($mesInicioCuota)
                                                    <div class="small text-muted mt-1">Inicio calculado: {{ $mesInicioCuota }}</div>
                                                @elseif ($item->cuotaEsIndefinida())
                                                    <div class="small text-muted mt-1">Sin fecha de inicio calculable.</div>
                                                @endif
                                                @if ($item->cuota_observacion)<div class="small text-muted mt-1">{{ $item->cuota_observacion }}</div>@endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format((float) $item->valor, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">No hay descuentos homologados guardados para este registro.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Otros descuentos</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nombre original</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($maeRegistro->otrosDescuentos as $item)
                                    <tr>
                                        <td>{{ $item->columna_origen }}</td>
                                        <td class="text-end">{{ number_format((float) $item->valor, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-muted py-3">Sin otros descuentos registrados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Observaciones</h2>
                    <div class="small text-muted mb-3">{{ $maeRegistro->observaciones_importacion ?: 'Sin observaciones de importación.' }}</div>
                    <details>
                        <summary class="fw-semibold">Ver fila cruda importada</summary>
                        <pre class="bg-light border rounded p-2 small mt-2" style="max-height: 320px; overflow:auto;">{{ json_encode($maeRegistro->raw_row_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
