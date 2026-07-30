@php
    $idx = $index;
    $causante = (array) $causante;
    $parentescoOptions = $parentescoOptions ?? config('cargas_familiares.parentescos', []);
    $beneficioOptions = $beneficioOptions ?? config('cargas_familiares.beneficios', []);
    $causanteOptions = $causanteOptions ?? collect(config('cargas_familiares.codigos_causante', []))
        ->mapWithKeys(fn ($item, $code) => [(string) $code => str_pad((string) $code, 2, '0', STR_PAD_LEFT) . ' - ' . (string) ($item['nombre'] ?? $code)])
        ->all();
@endphp
<div class="causante-card border rounded p-3 mb-3 bg-light" data-causante-index="{{ $idx }}">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="fw-semibold">{{ is_numeric($idx) ? 'Causante #' . ((int) $idx + 1) : 'Nuevo causante' }}</div>
            <div class="small text-muted js-edad-label">Edad: -</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger js-remove-causante"><i class="bi bi-trash"></i> Quitar</button>
    </div>

    @if ($cargasVigentes->count())
        <div class="row g-3 mb-2">
            <div class="col-md-8">
                <label class="form-label">Usar datos de una carga vigente para actualizar</label>
                <select name="causantes[{{ $idx }}][carga_familiar_id]" class="form-select js-carga-vigente">
                    <option value="">No usar carga vigente / nuevo causante</option>
                    @foreach ($cargasVigentes as $carga)
                        @php
                            $payload = [
                                'run' => $carga->causante_run,
                                'dv' => $carga->causante_dv,
                                'apellido_paterno' => $carga->causante_apellido_paterno,
                                'apellido_materno' => $carga->causante_apellido_materno,
                                'nombres' => $carga->causante_nombres,
                                'sexo' => $carga->sexo,
                                'parentesco' => $carga->parentesco,
                                'codigo_tipo_causante' => $carga->codigo_tipo_causante,
                                'codigo_tipo_beneficio' => $carga->tipo_beneficio,
                                'fecha_nacimiento' => $carga->fecha_nacimiento?->format('Y-m-d'),
                                'fecha_inicio' => $carga->fecha_inicio?->format('Y-m-d'),
                            ];
                        @endphp
                        <option value="{{ $carga->id }}" data-payload='@json($payload)' @selected((string) ($causante['carga_familiar_id'] ?? '') === (string) $carga->id)>
                            {{ $carga->causante_nombre_completo }} · {{ $carga->causante_rut_completo }} · {{ $carga->parentesco ?: 'Sin parentesco' }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Al seleccionarla, se completan los datos actuales del causante para solicitar actualizacion.</div>
            </div>
        </div>
    @else
        <input type="hidden" name="causantes[{{ $idx }}][carga_familiar_id]" value="{{ $causante['carga_familiar_id'] ?? '' }}">
    @endif

    <div class="row g-3">
        <div class="col-md-2">
            <label class="form-label">RUN <span class="text-danger">*</span></label>
            <input type="text" name="causantes[{{ $idx }}][run]" class="form-control" value="{{ old('causantes.' . $idx . '.run', $causante['run'] ?? '') }}" required>
        </div>
        <div class="col-md-1">
            <label class="form-label">DV <span class="text-danger">*</span></label>
            <input type="text" name="causantes[{{ $idx }}][dv]" class="form-control text-uppercase" value="{{ old('causantes.' . $idx . '.dv', $causante['dv'] ?? '') }}" maxlength="1" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Apellido paterno <span class="text-danger">*</span></label>
            <input type="text" name="causantes[{{ $idx }}][apellido_paterno]" class="form-control" value="{{ old('causantes.' . $idx . '.apellido_paterno', $causante['apellido_paterno'] ?? '') }}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Apellido materno</label>
            <input type="text" name="causantes[{{ $idx }}][apellido_materno]" class="form-control" value="{{ old('causantes.' . $idx . '.apellido_materno', $causante['apellido_materno'] ?? '') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Nombres <span class="text-danger">*</span></label>
            <input type="text" name="causantes[{{ $idx }}][nombres]" class="form-control" value="{{ old('causantes.' . $idx . '.nombres', $causante['nombres'] ?? '') }}" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Sexo <span class="text-danger">*</span></label>
            <select name="causantes[{{ $idx }}][sexo]" class="form-select" required>
                <option value="">Seleccione...</option>
                <option value="01" @selected(old('causantes.' . $idx . '.sexo', $causante['sexo'] ?? '') === '01')>01 Masculino</option>
                <option value="02" @selected(old('causantes.' . $idx . '.sexo', $causante['sexo'] ?? '') === '02')>02 Femenino</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Parentesco <span class="text-danger">*</span></label>
            @php
                $parentescoActual = old('causantes.' . $idx . '.parentesco', $causante['parentesco'] ?? 'hijo_hija');
            @endphp
            <select name="causantes[{{ $idx }}][parentesco]" class="form-select js-parentesco-causante" required>
                <option value="">Seleccione...</option>
                @foreach ($parentescoOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $parentescoActual === (string) $value || (string) $parentescoActual === (string) $label)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Cod. beneficio <span class="text-danger">*</span></label>
            @php
                $beneficioActual = old('causantes.' . $idx . '.codigo_tipo_beneficio', $causante['codigo_tipo_beneficio'] ?? '01');
            @endphp
            <select name="causantes[{{ $idx }}][codigo_tipo_beneficio]" class="form-select" required>
                <option value="">Seleccione...</option>
                @foreach ($beneficioOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $beneficioActual === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Cod. causante <span class="text-danger">*</span></label>
            @php
                $causanteActual = old('causantes.' . $idx . '.codigo_tipo_causante', $causante['codigo_tipo_causante'] ?? '04');
            @endphp
            <select name="causantes[{{ $idx }}][codigo_tipo_causante]" class="form-select js-codigo-causante" required>
                <option value="">Seleccione...</option>
                @foreach ($causanteOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $causanteActual === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Fecha nacimiento <span class="text-danger">*</span></label>
            <input type="date" name="causantes[{{ $idx }}][fecha_nacimiento]" class="form-control js-fecha-nacimiento" value="{{ old('causantes.' . $idx . '.fecha_nacimiento', $causante['fecha_nacimiento'] ?? '') }}" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Fecha inicio beneficio solicitado <span class="text-danger">*</span></label>
            <input type="date" name="causantes[{{ $idx }}][fecha_inicio_beneficio]" class="form-control" value="{{ old('causantes.' . $idx . '.fecha_inicio_beneficio', $causante['fecha_inicio_beneficio'] ?? now()->toDateString()) }}" required>
        </div>
        <div class="col-12">
            <label class="form-label">Observaciones / modificacion solicitada</label>
            <textarea name="causantes[{{ $idx }}][observaciones]" rows="2" class="form-control">{{ old('causantes.' . $idx . '.observaciones', $causante['observaciones'] ?? '') }}</textarea>
        </div>
    </div>

    <hr>
    <div class="fw-semibold mb-2">Documentos del causante segun Codigo Tipo de Causante</div>
    <div class="alert alert-light border small js-documentacion-causante-info mb-3"></div>
    <div class="row g-3 js-documentos-causante"></div>
</div>
