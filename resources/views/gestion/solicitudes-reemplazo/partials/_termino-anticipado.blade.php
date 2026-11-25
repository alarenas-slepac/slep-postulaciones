@php
    $fechaMinima = max($s->fecha_inicio->toDateString(), $s->fecha_inicio_trabajo?->toDateString() ?? '');
    $fechaMaxima = $s->fecha_termino->copy()->subDay()->toDateString();
@endphp

<details class="mt-3" @if ($errors->hasAny(['fecha_termino_anticipado', 'motivo_termino_anticipado'])) open @endif>
    <summary class="btn btn-outline-primary">Adelantar fecha de término</summary>
    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.termino-anticipado', $s) }}"
          class="border rounded-4 bg-body-tertiary p-3 mt-3" aria-label="Adelantar fecha de término">
        @csrf
        @method('PATCH')

        <p class="small text-muted mb-3">Término vigente {{ $s->fecha_termino->format('d/m/Y') }}. El establecimiento podrá consultar el motivo en el historial.</p>

        <div class="row g-3 align-items-start">
            <div class="col-md-4 col-lg-3">
                <label for="fecha-termino-anticipado" class="form-label">Nueva fecha de término <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="date" id="fecha-termino-anticipado" name="fecha_termino_anticipado"
                       class="form-control @error('fecha_termino_anticipado') is-invalid @enderror" required
                       min="{{ $fechaMinima }}" max="{{ $fechaMaxima }}" value="{{ old('fecha_termino_anticipado') }}"
                       aria-describedby="fecha-termino-anticipado-error">
                <div id="fecha-termino-anticipado-error" class="invalid-feedback">@error('fecha_termino_anticipado'){{ $message }}@enderror</div>
            </div>
            <div class="col-md-8 col-lg-9">
                <label for="motivo-termino-anticipado" class="form-label">Motivo del término anticipado <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea id="motivo-termino-anticipado" name="motivo_termino_anticipado" rows="3" maxlength="5000" required
                          class="form-control @error('motivo_termino_anticipado') is-invalid @enderror"
                          aria-describedby="motivo-termino-anticipado-error">{{ old('motivo_termino_anticipado') }}</textarea>
                <div id="motivo-termino-anticipado-error" class="invalid-feedback">@error('motivo_termino_anticipado'){{ $message }}@enderror</div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Guardar nueva fecha</button>
            </div>
        </div>
    </form>
</details>
