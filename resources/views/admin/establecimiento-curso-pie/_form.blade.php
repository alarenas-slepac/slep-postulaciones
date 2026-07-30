@php
    $selectedCurso = old('establecimiento_curso_id', $pie->establecimiento_curso_id);
    $selectedEstado = old('estado', $pie->estado ?: 'borrador');
    $canChangeEstado = in_array($activeRole, ['admin', 'coordinador_uatp'], true);
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        <label class="form-label">Curso/sección <span class="text-danger">*</span></label>
        <select class="form-select" name="establecimiento_curso_id" required>
            <option value="">Seleccione curso/sección...</option>
            @foreach ($cursosDisponibles as $cursoItem)
                @php
                    $label = trim(($cursoItem->establecimiento?->rbd ?: $cursoItem->rbd).' — '.($cursoItem->establecimiento?->nombre_establecimiento ?: 'Sin establecimiento').' · '.($cursoItem->nombre_seccion ?: trim(($cursoItem->curso?->nombre ?? '').' '.($cursoItem->letra ?? ''))).' · '.$cursoItem->anio.' · Matrícula '.$cursoItem->matricula);
                @endphp
                <option value="{{ $cursoItem->id }}" @selected((string) $selectedCurso === (string) $cursoItem->id)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">El registro se cruza contra la tabla establecimiento_cursos. No se crean cursos nuevos desde este formulario.</div>
    </div>

    <div class="col-md-2">
        <label class="form-label">Año <span class="text-danger">*</span></label>
        <input type="number" class="form-control" name="anio" min="2020" max="2100" value="{{ old('anio', $pie->anio ?: now()->year) }}" required>
    </div>

    <div class="col-md-2">
        <label class="form-label">Estado</label>
        @if ($canChangeEstado)
            <select class="form-select" name="estado" required>
                @foreach ($estados as $key => $label)
                    <option value="{{ $key }}" @selected($selectedEstado === $key)>{{ $label }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="estado" value="{{ $selectedEstado }}">
            <div class="form-control bg-light">{{ $estados[$selectedEstado] ?? 'Borrador' }}</div>
        @endif
    </div>

    @if ($cursoSeleccionado)
        <div class="col-12">
            <div class="alert alert-info mb-0">
                <div class="fw-semibold">Curso seleccionado</div>
                <div class="small">
                    {{ $cursoSeleccionado->establecimiento?->rbd ?: $cursoSeleccionado->rbd }} — {{ $cursoSeleccionado->establecimiento?->nombre_establecimiento }} ·
                    {{ $cursoSeleccionado->nombre_seccion ?: trim(($cursoSeleccionado->curso?->nombre ?? '').' '.($cursoSeleccionado->letra ?? '')) }} ·
                    Matrícula: <strong>{{ number_format((int) $cursoSeleccionado->matricula, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label">NEET <span class="text-danger">*</span></label>
        <input type="number" class="form-control" name="necesidades_transitorias" min="0" max="9999" value="{{ old('necesidades_transitorias', $pie->necesidades_transitorias ?? 0) }}" required>
        <div class="form-text">Necesidades Educativas Especiales Transitorias.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label">NEEP <span class="text-danger">*</span></label>
        <input type="number" class="form-control" name="necesidades_permanentes" min="0" max="9999" value="{{ old('necesidades_permanentes', $pie->necesidades_permanentes ?? 0) }}" required>
        <div class="form-text">Necesidades Educativas Especiales Permanentes.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Total PIE</label>
        <div class="form-control bg-light" id="totalPiePreview">{{ number_format((int) old('total_pie', $pie->total_pie ?? 0), 0, ',', '.') }}</div>
        <div class="form-text">Se calcula como NEET + NEEP y se valida contra matrícula.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Observación</label>
        <textarea class="form-control" name="observacion" rows="4" maxlength="2000" placeholder="Observación técnica o antecedente del establecimiento">{{ old('observacion', $pie->observacion) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const neet = document.querySelector('[name="necesidades_transitorias"]');
        const neep = document.querySelector('[name="necesidades_permanentes"]');
        const total = document.getElementById('totalPiePreview');
        const refresh = () => {
            const value = (parseInt(neet?.value || '0', 10) || 0) + (parseInt(neep?.value || '0', 10) || 0);
            total.textContent = value.toLocaleString('es-CL');
        };
        neet?.addEventListener('input', refresh);
        neep?.addEventListener('input', refresh);
        refresh();
    });
</script>
@endpush
