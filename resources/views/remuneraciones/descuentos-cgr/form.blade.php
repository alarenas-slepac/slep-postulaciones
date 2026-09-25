@extends('layouts.app')

@section('content')
    @include('remuneraciones.descuentos-cgr._styles')
    @php
        $editando = $descuentoCgr->exists;
        $valor = fn (string $campo, mixed $predeterminado = '') => old($campo, $descuentoCgr->{$campo} ?? $predeterminado);
        $periodoPrimerDescuento = old('fecha_primer_descuento', $descuentoCgr->fecha_primer_descuento?->format('Y-m'));
        $periodoPrimerDescuento = $periodoPrimerDescuento ? substr((string) $periodoPrimerDescuento, 0, 7) : '';
    @endphp
    <div class="cgr-page">
        <div class="cgr-page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <div class="cgr-page-header__eyebrow"><span class="cgr-page-header__icon"><i class="bi bi-bank" aria-hidden="true"></i></span> Remuneraciones · Descuentos CGR</div>
                <h1 class="mb-2">{{ $editando ? 'Editar descuento CGR' : 'Nuevo descuento CGR' }}</h1>
                <p class="mb-0">Ingresa los datos exactamente como figuran en el dictamen o resolución.</p>
            </div>
            <a href="{{ $editando ? route('descuentos-cgr.show', $descuentoCgr) : route('descuentos-cgr.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger"><strong><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Revisa los datos ingresados.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ $editando ? route('descuentos-cgr.update', $descuentoCgr) : route('descuentos-cgr.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($editando) @method('PUT') @endif

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-person-vcard me-2 text-primary" aria-hidden="true"></i>Identificación y resolución</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input id="rut" name="rut" class="form-control @error('rut') is-invalid @enderror" value="{{ $valor('rut') }}" required maxlength="12" placeholder="12.345.678-5" autocomplete="off">
                            <button id="buscar-funcionario" type="button" class="btn btn-outline-primary" data-url="{{ route('descuentos-cgr.funcionario.buscar') }}">
                                <i class="bi bi-search me-1"></i>Buscar
                            </button>
                        </div>
                        <div id="funcionario-feedback" class="form-text" aria-live="polite">Ingresa el RUT con o sin puntos y presiona Buscar.</div>
                        @error('rut') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-8">
                        <label for="nombre" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input id="nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ $valor('nombre') }}" required maxlength="255" readonly>
                        <div class="form-text">Se completa desde funcionarios autorizados de Administración Central o desde el registro más reciente en reemplazos personal.</div>
                        @error('nombre') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="numero_resolucion" class="form-label">N° dictamen o resolución <span class="text-danger">*</span></label>
                        <input id="numero_resolucion" name="numero_resolucion" class="form-control @error('numero_resolucion') is-invalid @enderror" value="{{ $valor('numero_resolucion') }}" required maxlength="100">
                        @error('numero_resolucion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_resolucion" class="form-label">Fecha resolución</label>
                        <input id="fecha_resolucion" type="date" name="fecha_resolucion" class="form-control @error('fecha_resolucion') is-invalid @enderror" value="{{ old('fecha_resolucion', $descuentoCgr->fecha_resolucion?->format('Y-m-d')) }}">
                        @error('fecha_resolucion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="resolucion_pdf" class="form-label">Resolución PDF {{ $editando ? '' : '*' }}</label>
                        <input id="resolucion_pdf" type="file" name="resolucion_pdf" class="form-control @error('resolucion_pdf') is-invalid @enderror" accept="application/pdf" @required(! $editando)>
                        @error('resolucion_pdf') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Máximo 20 MB. @if ($editando)Deja vacío para conservar el documento actual.@endif</div>
                    </div>
                    <div class="col-md-6">
                        <label for="institucion_reintegro" class="form-label">Institución a la que se debe reintegrar</label>
                        <input id="institucion_reintegro" name="institucion_reintegro" class="form-control @error('institucion_reintegro') is-invalid @enderror" value="{{ $valor('institucion_reintegro') }}" maxlength="255" placeholder="Opcional">
                        @error('institucion_reintegro') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="estamento_funcionario" class="form-label">Estamento o escalafón del funcionario</label>
                        <input id="estamento_funcionario" name="estamento_funcionario" class="form-control @error('estamento_funcionario') is-invalid @enderror" value="{{ $valor('estamento_funcionario') }}" maxlength="255" placeholder="Para el certificado de Auditoría">
                        @error('estamento_funcionario') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-calculator me-2 text-primary" aria-hidden="true"></i>Parámetros del descuento</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label for="deuda_definitiva_pesos" class="form-label">Deuda definitiva (pesos) <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text">$</span><input id="deuda_definitiva_pesos" type="number" name="deuda_definitiva_pesos" class="form-control @error('deuda_definitiva_pesos') is-invalid @enderror" min="1" step="1" value="{{ $valor('deuda_definitiva_pesos') }}" required></div>
                        @error('deuda_definitiva_pesos') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="deuda_equivalente_utm" class="form-label">Deuda equivalente (UTM) <span class="text-danger">*</span></label>
                        <input id="deuda_equivalente_utm" type="number" name="deuda_equivalente_utm" class="form-control @error('deuda_equivalente_utm') is-invalid @enderror" min="0.0001" step="0.0001" value="{{ $valor('deuda_equivalente_utm') }}" required>
                        @error('deuda_equivalente_utm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="cuota_utm" class="form-label">Cuota (UTM) según resolución <span class="text-danger">*</span></label>
                        <input id="cuota_utm" type="number" name="cuota_utm" class="form-control @error('cuota_utm') is-invalid @enderror" min="0.0001" step="0.0001" value="{{ $valor('cuota_utm') }}" required>
                        @error('cuota_utm') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="numero_cuotas" class="form-label">N° de cuotas <span class="text-danger">*</span></label>
                        <input id="numero_cuotas" type="number" name="numero_cuotas" class="form-control @error('numero_cuotas') is-invalid @enderror" min="1" max="600" step="1" value="{{ $valor('numero_cuotas') }}" required>
                        @error('numero_cuotas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="tasa_interes_anual" class="form-label">Tasa interés anual <span class="text-danger">*</span></label>
                        <div class="input-group"><input id="tasa_interes_anual" type="number" name="tasa_interes_anual" class="form-control @error('tasa_interes_anual') is-invalid @enderror" min="0" max="100" step="0.0001" value="{{ $valor('tasa_interes_anual') }}" required><span class="input-group-text">%</span></div>
                        @error('tasa_interes_anual') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="tasa_interes_mensual" class="form-label">Tasa interés mensual <span class="text-danger">*</span></label>
                        <div class="input-group"><input id="tasa_interes_mensual" type="number" name="tasa_interes_mensual" class="form-control @error('tasa_interes_mensual') is-invalid @enderror" min="0" max="100" step="0.0001" value="{{ $valor('tasa_interes_mensual') }}" required><span class="input-group-text">%</span></div>
                        @error('tasa_interes_mensual') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_primer_descuento" class="form-label">Primer descuento <span class="text-danger">*</span></label>
                        <input id="fecha_primer_descuento" type="month" name="fecha_primer_descuento" class="form-control @error('fecha_primer_descuento') is-invalid @enderror" value="{{ $periodoPrimerDescuento }}" required>
                        @error('fecha_primer_descuento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" class="form-control @error('observaciones') is-invalid @enderror" rows="3" maxlength="5000">{{ $valor('observaciones') }}</textarea>
                        @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="card mb-4"><div class="card-body d-flex justify-content-end flex-wrap gap-2">
                <a href="{{ $editando ? route('descuentos-cgr.show', $descuentoCgr) : route('descuentos-cgr.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1" aria-hidden="true"></i>{{ $editando ? 'Guardar y recalcular' : 'Registrar y calcular' }}</button>
            </div></div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rutInput = document.getElementById('rut');
    const nombreInput = document.getElementById('nombre');
    const buscarButton = document.getElementById('buscar-funcionario');
    const feedback = document.getElementById('funcionario-feedback');

    if (!rutInput || !nombreInput || !buscarButton || !feedback) return;

    let rutResuelto = rutInput.value.trim();

    const mostrarEstado = (mensaje, tipo = 'muted') => {
        feedback.textContent = mensaje;
        feedback.className = `form-text text-${tipo}`;
    };

    const buscar = async () => {
        const rut = rutInput.value.trim();
        if (!rut) {
            nombreInput.value = '';
            mostrarEstado('Ingresa un RUT antes de buscar.', 'danger');
            rutInput.focus();
            return;
        }

        buscarButton.disabled = true;
        mostrarEstado('Buscando funcionario...', 'muted');

        try {
            const url = new URL(buscarButton.dataset.url, window.location.origin);
            url.searchParams.set('rut', rut);
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'No fue posible buscar el funcionario.');
            }

            rutInput.value = payload.rut;
            nombreInput.value = payload.nombre;
            rutResuelto = payload.rut;
            const fuente = payload.fuente || 'el padrón de funcionarios';
            const periodo = payload.periodo ? ` (${payload.periodo})` : '';
            mostrarEstado(`Funcionario encontrado en ${fuente}${periodo}.`, 'success');
        } catch (error) {
            nombreInput.value = '';
            mostrarEstado(error.message || 'No fue posible buscar el funcionario.', 'danger');
        } finally {
            buscarButton.disabled = false;
        }
    };

    rutInput.addEventListener('input', () => {
        if (rutInput.value.trim() !== rutResuelto) {
            nombreInput.value = '';
            mostrarEstado('Presiona Buscar para validar el RUT y completar el nombre.', 'muted');
        }
    });
    rutInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscar();
        }
    });
    buscarButton.addEventListener('click', buscar);
});
</script>
@endpush
