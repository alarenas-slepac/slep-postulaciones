@csrf
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label required" for="rut">RUT funcionario</label>
        <input type="text" name="rut" id="rut" class="form-control @error('rut') is-invalid @enderror" value="{{ old('rut', $registro->rut) }}" placeholder="Ej: 12.345.678-9" required>
        @error('rut')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">El RUT se valida contra el último padrón activo/cargado. No se guarda relación al ID mensual del padrón.</div>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="observacion">Observación / respaldo</label>
        <input type="text" name="observacion" id="observacion" class="form-control @error('observacion') is-invalid @enderror" value="{{ old('observacion', $registro->observacion) }}" maxlength="2000" placeholder="Ej: Habilitado por anexo de contrato vigente">
        @error('observacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" name="activo" id="activo" value="1" @checked(old('activo', $registro->exists ? $registro->activo : true))>
            <label class="form-check-label fw-semibold" for="activo">Registro activo para habilitar casilla de viático</label>
        </div>
    </div>

    @if ($registro->exists)
        <div class="col-12">
            <div class="alert alert-info mb-0">
                <strong>Última validación:</strong> {{ optional($registro->validado_at)->format('d-m-Y H:i') ?: 'Sin validación registrada' }}<br>
                <strong>Funcionario:</strong> {{ $registro->nombre_completo ?: 'No informado' }}<br>
                <strong>Establecimiento:</strong> {{ $registro->establecimiento_nombre ?: 'No informado' }}<br>
                <strong>Estamento / cargo:</strong> {{ $registro->estamento ?: 'Sin estamento' }} / {{ $registro->cargo_funcion ?: 'Sin cargo' }}
            </div>
        </div>
    @endif
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.funcionarios-viatico-anexo.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary">Guardar registro</button>
</div>
