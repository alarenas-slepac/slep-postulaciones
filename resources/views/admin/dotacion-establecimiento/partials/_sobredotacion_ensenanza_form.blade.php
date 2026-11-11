@once
    @push('styles')
        @vite('resources/js/slep-listados.js')
    @endpush
@endonce
@php
    $catalogoAreas = collect($areasDocentesJustificacion ?? []);
    $nivelSeleccionado = $esFormularioActivo ? old('nivel_ensenanza') : $registro?->nivel_ensenanza;
    $nivelSeleccionado = is_string($nivelSeleccionado) ? $nivelSeleccionado : '';
    $areasSeleccionadas = $esFormularioActivo ? old('areas_desempeno_ids', []) : ($registro?->areasEnsenanzaIds() ?? []);
    $areasSeleccionadas = is_array($areasSeleccionadas) ? array_map(fn ($id) => is_scalar($id) ? (string) $id : '', $areasSeleccionadas) : [];
    $errorNivel = $esFormularioActivo && $errors->has('nivel_ensenanza');
    $errorAreas = $esFormularioActivo && ($errors->has('areas_desempeno_ids') || $errors->has('areas_desempeno_ids.*'));
    $areasFueraCatalogo = collect($registro?->areas_ensenanza ?? [])->reject(fn ($area) => $catalogoAreas->contains('id', $area['id'] ?? null));
@endphp
<div class="slep-listing mb-3" data-slep-listing>
    <div class="mb-3">
        <label class="form-label" for="{{ $idCampo }}-nivel">Nivel de enseñanza <span class="text-danger">*</span></label>
        <select class="form-select {{ $errorNivel ? 'is-invalid' : '' }}" id="{{ $idCampo }}-nivel" name="nivel_ensenanza" required @disabled(! ($ensenanzaJustificacionDisponible ?? false)) @if($errorNivel) aria-invalid="true" aria-describedby="{{ $idCampo }}-nivel-error" @endif>
            <option value="">Seleccione un nivel</option>
            @foreach(\App\Models\DotacionSobredotacionJustificacion::NIVELES_ENSENANZA as $valor => $etiqueta)
                <option value="{{ $valor }}" @selected($nivelSeleccionado === $valor)>{{ $etiqueta }}</option>
            @endforeach
        </select>
        @if($errorNivel)<div class="invalid-feedback" id="{{ $idCampo }}-nivel-error">{{ $errors->first('nivel_ensenanza') }}</div>@endif
    </div>
    <div>
        <label class="form-label" for="{{ $idCampo }}-areas">Área de enseñanza <span class="text-danger">*</span></label>
        <select class="form-select {{ $errorAreas ? 'is-invalid' : '' }}" id="{{ $idCampo }}-areas" name="areas_desempeno_ids[]" multiple size="4" data-slep-select="search" data-slep-dropdown-parent="body" aria-describedby="{{ $idCampo }}-areas-ayuda{{ $errorAreas ? ' '.$idCampo.'-areas-error' : '' }}" required @disabled(! ($ensenanzaJustificacionDisponible ?? false)) @if($errorAreas) aria-invalid="true" @endif>
            @foreach($catalogoAreas as $area)
                <option value="{{ $area->id }}" @selected(in_array((string) $area->id, $areasSeleccionadas, true))>{{ $area->nombre }}</option>
            @endforeach
        </select>
        <p class="form-text" id="{{ $idCampo }}-areas-ayuda">Seleccione una o más áreas de desempeño docente. El listado incluye todas las áreas activas de Docentes del perfil de postulante.</p>
        @if($errorAreas)<div class="invalid-feedback" id="{{ $idCampo }}-areas-error">{{ $errors->first('areas_desempeno_ids') ?: $errors->first('areas_desempeno_ids.*') }}</div>@endif
        @if($catalogoAreas->isEmpty() && ($ensenanzaJustificacionDisponible ?? false))<p class="small text-warning-emphasis mt-2 mb-0">No hay áreas docentes activas en el catálogo. Debe habilitar al menos una antes de guardar.</p>@endif
        @if($areasFueraCatalogo->isNotEmpty())<p class="small text-warning-emphasis mt-2 mb-0">Áreas registradas fuera del catálogo activo: {{ $areasFueraCatalogo->pluck('nombre')->implode(', ') }}. Revise la selección antes de actualizar.</p>@endif
    </div>
</div>
