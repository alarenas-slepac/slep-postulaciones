@php
    $cargosJson = json_encode($cargosPorEstamento ?? [], JSON_UNESCAPED_UNICODE);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Estamento *</label>
        <select name="estamento" id="estamento" class="form-select @error('estamento') is-invalid @enderror" required>
            <option value="">Seleccione...</option>
            @foreach($estamentos as $estamento)
                <option value="{{ $estamento }}" @selected(old('estamento', $valor->estamento) === $estamento)>{{ $estamento }}</option>
            @endforeach
        </select>
        @error('estamento') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Cargo / función o tramo *</label>
        <input list="cargosSugeridos" name="cargo_funcion" id="cargo_funcion" value="{{ old('cargo_funcion', $valor->cargo_funcion) }}" class="form-control @error('cargo_funcion') is-invalid @enderror" required>
        <datalist id="cargosSugeridos"></datalist>
        @error('cargo_funcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Vigente desde *</label>
        <input type="date" name="vigente_desde" value="{{ old('vigente_desde', optional($valor->vigente_desde)->format('Y-m-d')) }}" class="form-control @error('vigente_desde') is-invalid @enderror" required>
        @error('vigente_desde') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Vigente hasta</label>
        <input type="date" name="vigente_hasta" value="{{ old('vigente_hasta', optional($valor->vigente_hasta)->format('Y-m-d')) }}" class="form-control @error('vigente_hasta') is-invalid @enderror">
        @error('vigente_hasta') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label fw-semibold">Valor 100% *</label>
        <input type="number" min="0" name="valor_100" id="valor_100" value="{{ old('valor_100', $valor->valor_100) }}" class="form-control @error('valor_100') is-invalid @enderror" required>
        @error('valor_100') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label fw-semibold">Valor 60%</label>
        <input type="number" min="0" name="valor_60" id="valor_60" value="{{ old('valor_60', $valor->valor_60) }}" class="form-control @error('valor_60') is-invalid @enderror" placeholder="Auto 60%">
        <div class="form-text">Aplica cuando el servicio contempla colación.</div>
        @error('valor_60') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2">
        <label class="form-label fw-semibold">Valor 40% *</label>
        <input type="number" min="0" name="valor_40" value="{{ old('valor_40', $valor->valor_40) }}" class="form-control @error('valor_40') is-invalid @enderror" required>
        @error('valor_40') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" @checked(old('activo', $valor->activo ?? true))>
            <label class="form-check-label fw-semibold" for="activo">Registro activo</label>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    El valor 60% queda parametrizado para cometidos donde el servicio contempla colación. Si se deja vacío, el sistema lo calcula como 60% del valor 100% y lo guarda explícitamente.
</div>

@push('scripts')
<script>
(function () {
    const cargosPorEstamento = {!! $cargosJson !!};
    const estamento = document.getElementById('estamento');
    const cargoInput = document.getElementById('cargo_funcion');
    const datalist = document.getElementById('cargosSugeridos');
    const valor100 = document.getElementById('valor_100');
    const valor60 = document.getElementById('valor_60');

    function actualizarCargos() {
        const items = cargosPorEstamento[estamento.value] || [];
        datalist.innerHTML = items.map(item => `<option value="${item}"></option>`).join('');
    }

    function calcularValor60() {
        if (!valor60.value && valor100.value) {
            valor60.placeholder = Math.round(parseInt(valor100.value || '0', 10) * 0.60).toString();
        }
    }

    estamento?.addEventListener('change', actualizarCargos);
    valor100?.addEventListener('input', calcularValor60);
    actualizarCargos();
    calcularValor60();
})();
</script>
@endpush
