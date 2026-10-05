@if (old('formulario') === $formularioErrores)
    @error($campo)
        <div id="{{ $controlId }}-error" class="invalid-feedback d-block" data-save-field-error="{{ $campo }}">{{ $message }}</div>
    @enderror
@endif
