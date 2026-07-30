@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Complementar cuotas de descuento</h1>
            <p class="text-muted mb-0">Selecciona una carga MAE procesada, el descuento exacto y adjunta una nómina con RUT y número de cuota.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endeudamiento.cuotas.plantilla') }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Descargar plantilla</a>
            <a href="{{ route('endeudamiento.cuotas.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">No fue posible procesar la nómina:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('endeudamiento.cuotas.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Carga MAE procesada</label>
                            <select id="mae_carga_selector" name="mae_carga_id" class="form-select" required>
                                <option value="">Seleccionar período, dominio y versión</option>
                                @foreach ($cargas as $carga)
                                    <option value="{{ $carga->id }}" @selected((int) old('mae_carga_id', $cargaSeleccionada?->id) === (int) $carga->id)>
                                        {{ sprintf('%02d/%04d', $carga->mes, $carga->anio) }} · {{ $carga->dominio }} · v{{ $carga->version }}{{ $carga->es_vigente ? ' vigente' : ' histórica' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Al cambiar la carga, la página actualizará la lista de descuentos disponibles.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Descuento a complementar</label>
                            <select name="columna_normalizada" class="form-select" required @disabled(!$cargaSeleccionada)>
                                <option value="">Seleccionar descuento</option>
                                @foreach ($descuentos as $descuento)
                                    <option value="{{ $descuento->columna_normalizada }}" @selected(old('columna_normalizada') === $descuento->columna_normalizada)>
                                        {{ $descuento->columna_origen }} · {{ number_format($descuento->total_registros, 0, ',', '.') }} registros · {{ number_format($descuento->total_con_cuota, 0, ',', '.') }} con cuota
                                    </option>
                                @endforeach
                            </select>
                            @if ($cargaSeleccionada && $descuentos->isEmpty())
                                <div class="form-text text-danger">La carga seleccionada no tiene descuentos disponibles para complementar.</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nómina de cuotas</label>
                            <input type="file" name="excel" class="form-control" accept=".xlsx,.xls,.csv" required @disabled(!$cargaSeleccionada)>
                            <div class="form-text">Columnas obligatorias: <strong>RUT</strong> y <strong>CUOTA_ACTUAL</strong>. Opcionales: <strong>TOTAL_CUOTAS</strong> y <strong>OBSERVACION</strong>. Se admite <strong>0</strong>, <strong>cero</strong>, <strong>indefinido</strong>, <strong>sin inicio</strong> o <strong>sin término</strong>.</div>
                        </div>

                        <div class="alert alert-info small">
                            El sistema buscará cada RUT únicamente dentro de la versión MAE seleccionada y asociará la cuota al descuento exacto. Las filas no encontradas, duplicadas o inconsistentes quedarán informadas como error sin bloquear las filas válidas.
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('endeudamiento.cuotas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button class="btn btn-primary" @disabled(!$cargaSeleccionada || $descuentos->isEmpty())><i class="bi bi-check2-circle"></i> Revisar y asociar cuotas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Reglas de asociación</h2>
                    <ul class="small mb-0 ps-3">
                        <li>La carga MAE debe estar procesada.</li>
                        <li>El RUT debe existir en esa carga.</li>
                        <li>El funcionario debe tener el descuento seleccionado.</li>
                        <li>La cuota actual admite enteros desde cero. El valor 0, o texto equivalente, identifica una cuota indefinida.</li>
                        <li>Si cuota actual y total son mayores que cero, el total no puede ser menor. Un total 0 significa sin término.</li>
                        <li>Los valores 135 de 0 significan que lleva 135 cuotas y continúa indefinidamente; 0 de 0 significa sin inicio ni término, pero el descuento igualmente se aplica.</li>
                    </ul>
                </div>
            </div>
            @if ($cargaSeleccionada)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">Carga seleccionada</h2>
                        <dl class="row small mb-0">
                            <dt class="col-5">Período</dt><dd class="col-7">{{ sprintf('%02d/%04d', $cargaSeleccionada->mes, $cargaSeleccionada->anio) }}</dd>
                            <dt class="col-5">Dominio</dt><dd class="col-7">{{ $cargaSeleccionada->dominio }}</dd>
                            <dt class="col-5">Versión</dt><dd class="col-7">v{{ $cargaSeleccionada->version }}{{ $cargaSeleccionada->es_vigente ? ' vigente' : '' }}</dd>
                            <dt class="col-5">Estado</dt><dd class="col-7">{{ $cargaSeleccionada->estado }}</dd>
                        </dl>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('mae_carga_selector');
    if (!selector) return;
    selector.addEventListener('change', function () {
        const url = new URL(@json(route('endeudamiento.cuotas.create')), window.location.origin);
        if (this.value) url.searchParams.set('carga_id', this.value);
        window.location.href = url.toString();
    });
});
</script>
@endpush
