<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<style>
    .bolsa-trabajo-form .select2-container { width: 100% !important; }
    .bolsa-trabajo-form .select2-container--default .select2-selection--multiple {
        min-height: 38px;
        border: 1px solid #ced4da;
        border-radius: .375rem;
        padding: .15rem .35rem;
    }
    .bolsa-trabajo-form .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .25rem rgba(13,110,253,.25);
    }
    .bolsa-trabajo-form .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: #e9f2ff;
        border-color: #b6d4fe;
        color: #0a58ca;
    }
</style>

<div class="bolsa-trabajo-form">
@csrf
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Establecimiento</label>
        @php
            $selectedEstablecimientos = collect(old('establecimientos_ids', $item->establecimientos_seleccionados_ids ?? ($item->establecimiento_id ? [$item->establecimiento_id] : [])))
                ->map(fn ($id) => (string) $id)
                ->all();
        @endphp
        <select name="establecimientos_ids[]" id="establecimientos_ids" class="form-select @error('establecimientos_ids') is-invalid @enderror @error('establecimientos_ids.*') is-invalid @enderror" multiple required>
            @foreach ($establecimientos as $establecimiento)
                <option value="{{ $establecimiento->id }}" data-comuna="{{ $establecimiento->comuna }}" @selected(in_array((string) $establecimiento->id, $selectedEstablecimientos, true))>
                    {{ $establecimiento->rbd }} - {{ $establecimiento->nombre_establecimiento }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Puede seleccionar uno o varios establecimientos. El campo incluye buscador.</div>
        @error('establecimientos_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @error('establecimientos_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label">Comuna</label>
        <select name="comuna" id="comuna" class="form-select @error('comuna') is-invalid @enderror" required>
            <option value="">Seleccione</option>
            @foreach ($comunas as $comunaOption)
                <option value="{{ $comunaOption }}" @selected(old('comuna', $item->comuna) === $comunaOption)>{{ $comunaOption }}</option>
            @endforeach
        </select>
        @error('comuna')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Estamento</label>
        <select name="estamento" id="estamento" class="form-select @error('estamento') is-invalid @enderror" required>
            <option value="">Seleccione</option>
            <option value="docente" @selected(old('estamento', $item->estamento) === 'docente')>Docente</option>
            <option value="asistente" @selected(old('estamento', $item->estamento) === 'asistente')>Asistente</option>
        </select>
        @error('estamento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label class="form-label">Área de desempeño</label>
        <select name="area_desempeno_id" id="area_desempeno_id" class="form-select @error('area_desempeno_id') is-invalid @enderror" required>
            <option value="">Seleccione</option>
            @foreach ($areasGrouped as $groupLabel => $areas)
                <optgroup label="{{ $groupLabel }}">
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}" data-estamento="{{ $area->estamento }}" @selected((string) old('area_desempeno_id', $item->area_desempeno_id) === (string) $area->id)>
                            {{ $area->nombre }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <div class="form-text">Las áreas se presentan agrupadas por estamento y ordenadas alfabéticamente.</div>
        @error('area_desempeno_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Calidad contractual</label>
        <select name="calidad_contractual" class="form-select @error('calidad_contractual') is-invalid @enderror" required>
            <option value="">Seleccione</option>
            @foreach ($calidadesContractuales as $key => $label)
                <option value="{{ $key }}" @selected(old('calidad_contractual', $item->calidad_contractual) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('calidad_contractual')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Cantidad de horas</label>
        <input type="number" min="1" max="60" name="cantidad_horas" class="form-control @error('cantidad_horas') is-invalid @enderror" value="{{ old('cantidad_horas', $item->cantidad_horas) }}" required>
        @error('cantidad_horas')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Remuneración bruta</label>
        <input type="number" min="1" step="1" inputmode="numeric" name="remuneracion_bruta" class="form-control @error('remuneracion_bruta') is-invalid @enderror" value="{{ old('remuneracion_bruta', $item->remuneracion_bruta) }}" required>
        <div class="form-text">Ingrese el monto bruto en pesos, sin puntos ni comas.</div>
        @error('remuneracion_bruta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Inicio de trabajo aproximado</label>
        <input type="date" name="inicio_trabajo_aproximado" class="form-control @error('inicio_trabajo_aproximado') is-invalid @enderror" value="{{ old('inicio_trabajo_aproximado', optional($item->inicio_trabajo_aproximado)->format('Y-m-d')) }}" required>
        @error('inicio_trabajo_aproximado')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Fecha inicio postulaciones</label>
        <input type="date" name="fecha_inicio_postulaciones" class="form-control @error('fecha_inicio_postulaciones') is-invalid @enderror" value="{{ old('fecha_inicio_postulaciones', optional($item->fecha_inicio_postulaciones)->format('Y-m-d')) }}" required>
        @error('fecha_inicio_postulaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Hora inicio postulaciones</label>
        <input type="time" name="hora_inicio_postulaciones" class="form-control @error('hora_inicio_postulaciones') is-invalid @enderror" value="{{ old('hora_inicio_postulaciones', $item->hora_inicio_postulaciones) }}" required>
        @error('hora_inicio_postulaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Fecha término postulaciones</label>
        <input type="date" name="fecha_termino_postulaciones" class="form-control @error('fecha_termino_postulaciones') is-invalid @enderror" value="{{ old('fecha_termino_postulaciones', optional($item->fecha_termino_postulaciones)->format('Y-m-d')) }}" required>
        @error('fecha_termino_postulaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Hora término postulaciones</label>
        <input type="time" name="hora_termino_postulaciones" class="form-control @error('hora_termino_postulaciones') is-invalid @enderror" value="{{ old('hora_termino_postulaciones', $item->hora_termino_postulaciones) }}" required>
        @error('hora_termino_postulaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Correo de contacto</label>
        <input type="email" name="correo_contacto" class="form-control @error('correo_contacto') is-invalid @enderror" value="{{ old('correo_contacto', $item->correo_contacto) }}" maxlength="190" required>
        @error('correo_contacto')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label class="form-label">Bases (PDF)</label>
        <input type="file" name="bases_pdf" class="form-control @error('bases_pdf') is-invalid @enderror" accept="application/pdf">
        <div class="form-text">Adjunte las bases en formato PDF. Tamaño máximo permitido: 100 MB.</div>
        @if (!empty($item->bases_pdf_path))
            <div class="mt-2">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.bolsa-trabajo.bases', $item) }}">
                    <i class="bi bi-file-earmark-pdf"></i> Descargar bases actuales
                </a>
                @if (!empty($item->bases_pdf_original_name))
                    <div class="small text-muted mt-1">Archivo actual: {{ $item->bases_pdf_original_name }}</div>
                @endif
            </div>
        @endif
        @error('bases_pdf')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
</div>
<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Guardar oferta</button>
    <a class="btn btn-outline-secondary" href="{{ route('gestion.bolsa-trabajo.index') }}">Volver</a>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    const estab = document.getElementById('establecimientos_ids');
    const comuna = document.getElementById('comuna');
    const estamento = document.getElementById('estamento');
    const area = document.getElementById('area_desempeno_id');

    function syncComuna() {
        if (!estab || !comuna) return;
        const selectedOptions = Array.from(estab.selectedOptions || []);
        if (selectedOptions.length !== 1) {
            return;
        }
        const comunaValue = selectedOptions[0].getAttribute('data-comuna') || '';
        if (comunaValue) {
            comuna.value = comunaValue;
        }
    }

    function filterAreas() {
        if (!area || !estamento) return;
        const selectedEstamento = estamento.value;
        Array.from(area.options).forEach(function (option) {
            if (!option.value) return;
            const areaEstamento = option.getAttribute('data-estamento');
            option.hidden = !!selectedEstamento && areaEstamento !== selectedEstamento;
            option.disabled = !!selectedEstamento && areaEstamento !== selectedEstamento;
        });

        const selectedOption = area.options[area.selectedIndex];
        if (selectedOption && selectedOption.value && selectedOption.disabled) {
            area.value = '';
        }
    }

    if (estab) {
        estab.addEventListener('change', syncComuna);
        syncComuna();
    }
    if (estamento) {
        estamento.addEventListener('change', filterAreas);
        filterAreas();
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 && estab) {
        window.jQuery(estab).select2({
            width: '100%',
            placeholder: 'Buscar establecimiento',
            closeOnSelect: false,
            allowClear: false,
            language: {
                noResults: function () { return 'No se encontraron establecimientos'; },
                searching: function () { return 'Buscando...'; }
            }
        });
        window.jQuery(estab).on('change', syncComuna);
    }
})();
</script>

</div>
