@php
    /** @var \App\Models\Establecimiento $item */
@endphp

<form method="POST" action="{{ $action }}" class="js-validate">
    @csrf
    @if($method ?? false)
        @method($method)
    @endif

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">COD_ESTAB</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" name="cod_estab" value="{{ old('cod_estab', $item->cod_estab) }}"
                class="form-control @error('cod_estab') is-invalid @enderror" required>
            @error('cod_estab')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">RBD</label>
            <input type="text" inputmode="numeric" pattern="[0-9]*" name="rbd" value="{{ old('rbd', $item->rbd) }}"
                class="form-control @error('rbd') is-invalid @enderror" required>
            @error('rbd')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label">DV</label>
            <input type="text" name="dv" value="{{ old('dv', $item->dv) }}"
                class="form-control @error('dv') is-invalid @enderror" maxlength="2">
            @error('dv')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">COMUNA</label>
            <input type="text" name="comuna" value="{{ old('comuna', $item->comuna) }}"
                class="form-control @error('comuna') is-invalid @enderror" maxlength="120">
            @error('comuna')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-8">
            <label class="form-label">NOMBRE_ESTABLECIMIENTO</label>
            <input type="text" name="nombre_establecimiento" value="{{ old('nombre_establecimiento', $item->nombre_establecimiento) }}"
                class="form-control @error('nombre_establecimiento') is-invalid @enderror" required maxlength="255">
            @error('nombre_establecimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-2">
            <label class="form-label">% ASIGNACION ZONA</label>
            <input type="number" name="asignacion_zona" value="{{ old('asignacion_zona', $item->asignacion_zona ?? 0) }}"
                class="form-control @error('asignacion_zona') is-invalid @enderror" min="0" max="100">
            @error('asignacion_zona')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-2">
            <label class="form-label">Matrícula total</label>
            <input type="number" name="matricula_total" value="{{ old('matricula_total', $item->matricula_total) }}"
                class="form-control @error('matricula_total') is-invalid @enderror" min="0">
            @error('matricula_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Fuente principal para el Centro de Operaciones.</div>
        </div>

        <div class="col-md-6">
            <label class="form-label">CLASIFICACIÓN</label>
            <input type="text" name="clasificacion" value="{{ old('clasificacion', $item->clasificacion) }}"
                class="form-control @error('clasificacion') is-invalid @enderror" maxlength="255">
            @error('clasificacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">TIPO_ESTAB</label>
            <input type="text" name="tipo_estab" value="{{ old('tipo_estab', $item->tipo_estab) }}"
                class="form-control @error('tipo_estab') is-invalid @enderror" maxlength="80">
            @error('tipo_estab')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Latitud</label>
            <input type="number" step="0.0000001" name="latitud" value="{{ old('latitud', $item->latitud) }}"
                class="form-control @error('latitud') is-invalid @enderror" min="-90" max="90">
            @error('latitud')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label class="form-label">Longitud</label>
            <input type="number" step="0.0000001" name="longitud" value="{{ old('longitud', $item->longitud) }}"
                class="form-control @error('longitud') is-invalid @enderror" min="-180" max="180">
            @error('longitud')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="unidocencia" name="unidocencia"
                            {{ old('unidocencia', (bool) ($item->unidocencia ?? false)) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="unidocencia">¿Establecimiento con unidocencia?</label>
                    </div>
                    <div class="form-text">Cuando esté marcado, las solicitudes de reemplazo docente quedan exceptuadas de la regla mínima de 8 días. La regla JUNJI/sala cuna sigue usando el campo Sala Cuna.</div>
                    @error('unidocencia')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label d-block">TIPO ENSEÑANZA</label>
            <div class="row g-2">
                @php
                    $checks = [
                        'sala_cuna' => 'Sala Cuna',
                        'pre_escolar' => 'Pre-Escolar',
                        'basica' => 'Básica',
                        'media' => 'Media',
                        'tecnico_profesional' => 'Técnico-Profesional',
                        'adultos' => 'Adultos',
                        'especial' => 'Especial',
                    ];
                @endphp

                @foreach ($checks as $k => $label)
                    <div class="col-md-3 col-sm-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="{{ $k }}" name="{{ $k }}"
                                {{ old($k, (bool) $item->{$k}) ? 'checked' : '' }}>
                            <label class="form-check-label" for="{{ $k }}">{{ $label }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>


        <div class="col-12">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <div>
                            <div class="fw-semibold">Vista previa de ubicación</div>
                            <div class="text-muted small">Se actualizará automáticamente al informar latitud y longitud.</div>
                        </div>
                        <a href="#" class="btn btn-sm btn-outline-primary d-none" id="previewMapsLink" target="_blank" rel="noopener">Abrir en Google Maps</a>
                    </div>
                    <div class="text-muted small mb-2">Coordenadas: <span id="previewCoords">Sin coordenadas registradas</span></div>
                    <div id="mapaEstablecimientoPreviewWrap" class="rounded overflow-hidden border d-none" style="height: 280px;">
                        <iframe id="mapaEstablecimientoPreviewFrame" title="Vista previa del establecimiento" width="100%" height="280" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div id="mapaEstablecimientoPreviewEmpty" class="alert alert-light border mb-0">
                        Ingresa latitud y longitud válidas para visualizar el mapa incrustado.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-save"></i> Guardar
            </button>
            <a href="{{ route('admin.establecimientos.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @include('partials.form-validation')
</form>


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const latInput = document.querySelector('input[name="latitud"]');
    const lngInput = document.querySelector('input[name="longitud"]');
    const coords = document.getElementById('previewCoords');
    const wrap = document.getElementById('mapaEstablecimientoPreviewWrap');
    const frame = document.getElementById('mapaEstablecimientoPreviewFrame');
    const empty = document.getElementById('mapaEstablecimientoPreviewEmpty');
    const mapsLink = document.getElementById('previewMapsLink');
    if (!latInput || !lngInput || !coords || !wrap || !frame || !empty || !mapsLink) return;

    function buildEmbed(lat, lng) {
        const delta = 0.0045;
        const left = lng - delta;
        const right = lng + delta;
        const bottom = lat - delta;
        const top = lat + delta;
        return 'https://www.openstreetmap.org/export/embed.html?bbox='
            + encodeURIComponent(`${left},${bottom},${right},${top}`)
            + '&layer=mapnik&marker=' + encodeURIComponent(`${lat},${lng}`);
    }

    function refreshMap() {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            coords.textContent = `${lat}, ${lng}`;
            frame.src = buildEmbed(lat, lng);
            wrap.classList.remove('d-none');
            empty.classList.add('d-none');
            mapsLink.href = 'https://www.google.com/maps?q=' + encodeURIComponent(`${lat},${lng}`);
            mapsLink.classList.remove('d-none');
        } else {
            coords.textContent = 'Sin coordenadas registradas';
            frame.removeAttribute('src');
            wrap.classList.add('d-none');
            empty.classList.remove('d-none');
            mapsLink.href = '#';
            mapsLink.classList.add('d-none');
        }
    }

    latInput.addEventListener('input', refreshMap);
    lngInput.addEventListener('input', refreshMap);
    refreshMap();
});
</script>
@endpush
