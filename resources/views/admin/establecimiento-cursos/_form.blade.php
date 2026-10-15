@php
    $selectedEstablecimiento = old('establecimiento_id', $establecimientoCurso->establecimiento_id);
    $selectedCurso = old('curso_id', $establecimientoCurso->curso_id);
    $selectedPlan = old('plan_estudio_id', $establecimientoCurso->plan_estudio_id);
    $selectedRegimen = old('regimen_jec', $establecimientoCurso->regimen_jec ?: 'Con JEC');
@endphp

<div class="row g-3">
    <div class="col-lg-6">
        <label class="form-label">Establecimiento <span class="text-danger">*</span></label>
        <select class="form-select" name="establecimiento_id" required>
            <option value="">Seleccione establecimiento...</option>
            @foreach ($establecimientos as $comuna => $items)
                <optgroup label="{{ $comuna }}">
                    @foreach ($items as $establecimiento)
                        <option value="{{ $establecimiento->id }}" @selected((string) $selectedEstablecimiento === (string) $establecimiento->id)>
                            {{ $establecimiento->rbd }} — {{ $establecimiento->nombre_establecimiento }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Año <span class="text-danger">*</span></label>
        <input type="number" class="form-control" name="anio" min="2020" max="2100" value="{{ old('anio', $establecimientoCurso->anio ?: now()->year) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Régimen JEC <span class="text-danger">*</span></label>
        <select class="form-select" name="regimen_jec" required>
            <option value="Con JEC" @selected($selectedRegimen === 'Con JEC')>Con JEC</option>
            <option value="Sin JEC" @selected($selectedRegimen === 'Sin JEC')>Sin JEC</option>
            <option value="No aplica" @selected($selectedRegimen === 'No aplica')>No aplica</option>
        </select>
        <div class="form-text">Para EPJA o Educación Especial puede registrarse como No aplica, asociando plan Sin JEC si existe.</div>
    </div>

    <div class="col-lg-5">
        <label class="form-label">Curso base <span class="text-danger">*</span></label>
        <select class="form-select" name="curso_id" required>
            <option value="">Seleccione curso...</option>
            @foreach ($cursos as $curso)
                <option value="{{ $curso->id }}" @selected((string) $selectedCurso === (string) $curso->id)>
                    {{ $curso->nombre }}{{ $curso->modalidad ? ' · '.$curso->modalidad : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label">Letra</label>
        <input type="text" class="form-control" name="letra" maxlength="20" value="{{ old('letra', $establecimientoCurso->letra) }}" placeholder="A">
    </div>

    <div class="col-md-2">
        <label class="form-label">Matrícula <span class="text-danger">*</span></label>
        <input type="number" class="form-control" name="matricula" min="0" max="9999" value="{{ old('matricula', $establecimientoCurso->matricula ?? 0) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Activo</label>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $establecimientoCurso->activo ?? true))>
            <label class="form-check-label" for="activo">Curso/sección activo</label>
        </div>
    </div>

    <div class="col-lg-6">
        <label class="form-label">Nombre sección</label>
        <input type="text" class="form-control" name="nombre_seccion" maxlength="160" value="{{ old('nombre_seccion', $establecimientoCurso->nombre_seccion) }}" placeholder="Ej.: 1° Básico A">
        <div class="form-text">Si se deja vacío, se arma con curso base + letra.</div>
    </div>

    <div class="col-lg-6">
        <label class="form-label" for="plan-estudio-asociado">Plan de estudio asociado</label>
        <select id="plan-estudio-asociado" class="form-select @error('plan_estudio_id') is-invalid @enderror" name="plan_estudio_id" aria-describedby="plan-estudio-ayuda @error('plan_estudio_id') plan-estudio-error @enderror">
            <option value="">Asignar automáticamente si hay un único plan coincidente</option>
            @foreach ($planes as $plan)
                <option value="{{ $plan->id }}" data-curso="{{ $plan->curso_id }}" data-anio="{{ $plan->anio }}" data-regimen="{{ $plan->regimen_jec }}" @selected((string) $selectedPlan === (string) $plan->id)>
                    {{ $plan->anio }} · {{ $plan->curso?->nombre }} · {{ $plan->regimen_jec }} · {{ $plan->nombre_plan }} · {{ number_format((float) $plan->horas_semanales_total, 2, ',', '.') }} h/semana{{ $plan->activo ? '' : ' · Inactivo (asociado)' }}
                </option>
            @endforeach
        </select>
        <div id="plan-estudio-ayuda" class="form-text">Seleccione la variante del establecimiento, por ejemplo «Plan 43 horas». La asociación automática se aplica sólo si hay un único plan activo coincidente. Para «No aplica», se busca Sin JEC.</div>
        @error('plan_estudio_id')<div id="plan-estudio-error" class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Fuente</label>
        <input type="text" class="form-control" name="fuente" maxlength="120" value="{{ old('fuente', $establecimientoCurso->fuente ?: 'manual') }}">
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('plan-estudio-asociado');
    if (!select) return;
    const form = select.closest('form');
    const curso = form?.querySelector('[name="curso_id"]');
    const anio = form?.querySelector('[name="anio"]');
    const regimen = form?.querySelector('[name="regimen_jec"]');
    if (!curso || !anio || !regimen) return;
    const options = Array.from(select.options).filter(option => option.value).map(option => option.cloneNode(true));
    const sync = function () {
        const previous = select.value;
        const actualRegimen = regimen.value === 'No aplica' ? 'Sin JEC' : regimen.value;
        const matches = options.filter(option => option.dataset.curso === curso.value && option.dataset.anio === anio.value && option.dataset.regimen === actualRegimen);
        select.replaceChildren(new Option(matches.length > 1 ? 'Seleccione la variante del plan...' : 'Asignar automáticamente si hay un único plan coincidente', ''));
        matches.forEach(option => select.appendChild(option.cloneNode(true)));
        select.value = matches.some(option => option.value === previous) ? previous : '';
        select.required = matches.length > 1;
    };
    [curso, anio, regimen].forEach(field => field.addEventListener('change', sync));
    sync();
});
</script>
@endpush
