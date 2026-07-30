@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Curso <span class="text-danger">*</span></label>
        <select name="curso_id" class="form-select" required>
            <option value="">Seleccione curso...</option>
            @foreach ($cursos as $curso)
                <option value="{{ $curso->id }}" data-nivel="{{ $curso->nivel_educativo }}" data-modalidad="{{ $curso->modalidad }}" @selected((string) old('curso_id', $plan->curso_id) === (string) $curso->id)>
                    {{ $curso->nombre }} — {{ $curso->nivel_educativo }}{{ $curso->modalidad ? ' / '.$curso->modalidad : '' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label">Año <span class="text-danger">*</span></label>
        <input type="number" name="anio" class="form-control" min="2020" max="2100" value="{{ old('anio', $plan->anio) }}" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Régimen <span class="text-danger">*</span></label>
        <select name="regimen_jec" class="form-select" required>
            <option value="Con JEC" @selected(old('regimen_jec', $plan->regimen_jec) === 'Con JEC')>Con JEC</option>
            <option value="Sin JEC" @selected(old('regimen_jec', $plan->regimen_jec) === 'Sin JEC')>Sin JEC</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Nombre del plan <span class="text-danger">*</span></label>
        <input type="text" name="nombre_plan" class="form-control" value="{{ old('nombre_plan', $plan->nombre_plan) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Nivel educativo</label>
        <input type="text" name="nivel_educativo" class="form-control" value="{{ old('nivel_educativo', $plan->nivel_educativo) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Modalidad</label>
        <input type="text" name="modalidad" class="form-control" value="{{ old('modalidad', $plan->modalidad) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Subtotal semanal</label>
        <input type="number" name="horas_semanales_subtotal" class="form-control" step="0.01" min="0" value="{{ old('horas_semanales_subtotal', $plan->horas_semanales_subtotal) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Libre disposición</label>
        <input type="number" name="horas_semanales_libre_disposicion" class="form-control" step="0.01" min="0" value="{{ old('horas_semanales_libre_disposicion', $plan->horas_semanales_libre_disposicion) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">Total semanal <span class="text-danger">*</span></label>
        <input type="number" name="horas_semanales_total" class="form-control" step="0.01" min="1" value="{{ old('horas_semanales_total', $plan->horas_semanales_total) }}" required>
    </div>

    <div class="col-md-3">
        <label class="form-label">Total anual</label>
        <input type="number" name="horas_anuales_total" class="form-control" step="0.01" min="0" value="{{ old('horas_anuales_total', $plan->horas_anuales_total) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Decreto / referencia</label>
        <input type="text" name="decreto_referencia" class="form-control" value="{{ old('decreto_referencia', $plan->decreto_referencia) }}">
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked(old('activo', $plan->activo ?? true))>
            <label class="form-check-label" for="activo">Activo</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label">Observación</label>
        <textarea name="observacion" class="form-control" rows="2">{{ old('observacion', $plan->observacion) }}</textarea>
    </div>
</div>

<hr>

<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
    <div>
        <h2 class="h6 mb-0">Bloques del plan de estudio</h2>
        <div class="text-muted small">Permite estructurar el plan por ámbitos y definir qué bloques podrá completar cada establecimiento.</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="add-bloque-row">
        <i class="bi bi-plus-circle"></i> Agregar bloque
    </button>
</div>

@php
    $oldBloques = old('bloques');
    $bloques = $oldBloques !== null
        ? collect($oldBloques)
        : ($plan->exists ? $plan->bloques : collect());
    $tiposBloque = [
        'plan_comun_formacion_general' => 'Plan común formación general',
        'plan_comun_formacion_general_electivo' => 'Plan común formación general electivo',
        'plan_diferenciado_hc' => 'Plan diferenciado HC',
        'plan_diferenciado_tp' => 'Plan diferenciado TP',
        'plan_diferenciado_artistico' => 'Plan diferenciado artístico',
        'libre_disposicion' => 'Horas de libre disposición',
        'total' => 'Total',
    ];
@endphp

<div class="table-responsive mb-4">
    <table class="table table-sm align-middle" id="bloques-table">
        <thead>
            <tr>
                <th style="width: 75px;">Orden</th>
                <th>Nombre bloque</th>
                <th style="width: 240px;">Tipo</th>
                <th style="width: 130px;">Horas sem.</th>
                <th style="width: 130px;">Horas anuales</th>
                <th style="width: 130px;">Selección EE</th>
                <th style="width: 140px;">Personalizadas</th>
                <th style="width: 95px;">Activo</th>
                <th style="width: 70px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($bloques as $idx => $bloque)
                @php $row = is_array($bloque) ? $bloque : $bloque->toArray(); @endphp
                <tr>
                    <td>
                        @if (! empty($row['id']))
                            <input type="hidden" name="bloques[{{ $idx }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <input type="number" class="form-control form-control-sm" name="bloques[{{ $idx }}][orden]" min="1" value="{{ $row['orden'] ?? ($idx + 1) }}">
                    </td>
                    <td><input type="text" class="form-control form-control-sm" name="bloques[{{ $idx }}][nombre]" value="{{ $row['nombre'] ?? '' }}"></td>
                    <td>
                        <select class="form-select form-select-sm" name="bloques[{{ $idx }}][tipo_bloque]">
                            @foreach ($tiposBloque as $value => $label)
                                <option value="{{ $value }}" @selected(($row['tipo_bloque'] ?? 'plan_comun_formacion_general') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="bloques[{{ $idx }}][horas_semanales]" value="{{ $row['horas_semanales'] ?? '' }}"></td>
                    <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="bloques[{{ $idx }}][horas_anuales]" value="{{ $row['horas_anuales'] ?? '' }}"></td>
                    <td class="text-center"><input type="hidden" name="bloques[{{ $idx }}][permite_asignaturas_establecimiento]" value="0"><input class="form-check-input" type="checkbox" name="bloques[{{ $idx }}][permite_asignaturas_establecimiento]" value="1" @checked(! empty($row['permite_asignaturas_establecimiento']))></td>
                    <td class="text-center"><input type="hidden" name="bloques[{{ $idx }}][permite_asignaturas_personalizadas]" value="0"><input class="form-check-input" type="checkbox" name="bloques[{{ $idx }}][permite_asignaturas_personalizadas]" value="1" @checked(! empty($row['permite_asignaturas_personalizadas']))></td>
                    <td class="text-center"><input type="hidden" name="bloques[{{ $idx }}][activo]" value="0"><input class="form-check-input" type="checkbox" name="bloques[{{ $idx }}][activo]" value="1" @checked(array_key_exists('activo', $row) ? $row['activo'] : true)></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-bloque-row">Quitar</button></td>
                </tr>
            @empty
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
    <div>
        <h2 class="h6 mb-0">Detalle de asignaturas / componentes</h2>
        <div class="text-muted small">Opcional. Permite registrar asignaturas, subtotales, libre disposición y total del plan.</div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary" id="add-asignatura-row">
        <i class="bi bi-plus-circle"></i> Agregar fila
    </button>
</div>

@php
    $oldAsignaturas = old('asignaturas');
    $asignaturas = $oldAsignaturas !== null
        ? collect($oldAsignaturas)
        : ($plan->exists ? $plan->asignaturas : collect());
@endphp

<div class="table-responsive">
    <table class="table table-sm align-middle" id="asignaturas-table">
        <thead>
            <tr>
                <th style="width: 80px;">Orden</th>
                <th>Asignatura / componente</th>
                <th style="width: 180px;">Tipo</th>
                <th style="width: 160px;">Horas semanales</th>
                <th style="width: 160px;">Horas anuales</th>
                <th style="width: 70px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($asignaturas as $idx => $asignatura)
                @php $row = is_array($asignatura) ? $asignatura : $asignatura->toArray(); @endphp
                <tr>
                    <td><input type="number" class="form-control form-control-sm" name="asignaturas[{{ $idx }}][orden]" min="1" value="{{ $row['orden'] ?? ($idx + 1) }}"></td>
                    <td><input type="text" class="form-control form-control-sm" name="asignaturas[{{ $idx }}][asignatura]" value="{{ $row['asignatura'] ?? '' }}"></td>
                    <td>
                        <select class="form-select form-select-sm" name="asignaturas[{{ $idx }}][tipo_bloque]">
                            @foreach (['asignatura' => 'Asignatura', 'subtotal' => 'Subtotal', 'libre_disposicion' => 'Libre disposición', 'total' => 'Total', 'plan_diferenciado' => 'Plan diferenciado'] as $value => $label)
                                <option value="{{ $value }}" @selected(($row['tipo_bloque'] ?? 'asignatura') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="asignaturas[{{ $idx }}][horas_semanales]" value="{{ $row['horas_semanales'] ?? '' }}"></td>
                    <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="asignaturas[{{ $idx }}][horas_anuales]" value="{{ $row['horas_anuales'] ?? '' }}"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-asignatura-row">Quitar</button></td>
                </tr>
            @empty
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex justify-content-end gap-2 mt-3">
    <a class="btn btn-outline-danger" href="{{ route('admin.planes-estudio.index') }}">Cancelar</a>
    <button class="btn btn-primary" type="submit">Guardar</button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoBloqueOptions = `{!! collect($tiposBloque)->map(fn($label, $value) => '<option value="'.$value.'">'.$label.'</option>')->implode('') !!}`;

    function bindDynamicTable(tableSelector, addButtonId, rowBuilder) {
        const tableBody = document.querySelector(`${tableSelector} tbody`);
        const addButton = document.getElementById(addButtonId);
        let index = tableBody ? tableBody.querySelectorAll('tr').length : 0;
        if (!tableBody || !addButton) return;
        tableBody.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-bloque-row') || event.target.classList.contains('remove-asignatura-row')) {
                const row = event.target.closest('tr');
                if (row) row.remove();
            }
        });
        addButton.addEventListener('click', function () {
            const current = index++;
            const row = document.createElement('tr');
            row.innerHTML = rowBuilder(current);
            tableBody.appendChild(row);
        });
    }

    bindDynamicTable('#bloques-table', 'add-bloque-row', function (current) {
        return `
            <td><input type="number" class="form-control form-control-sm" name="bloques[${current}][orden]" min="1" value="${current + 1}"></td>
            <td><input type="text" class="form-control form-control-sm" name="bloques[${current}][nombre]"></td>
            <td><select class="form-select form-select-sm" name="bloques[${current}][tipo_bloque]">${tipoBloqueOptions}</select></td>
            <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="bloques[${current}][horas_semanales]"></td>
            <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="bloques[${current}][horas_anuales]"></td>
            <td class="text-center"><input type="hidden" name="bloques[${current}][permite_asignaturas_establecimiento]" value="0"><input class="form-check-input" type="checkbox" name="bloques[${current}][permite_asignaturas_establecimiento]" value="1"></td>
            <td class="text-center"><input type="hidden" name="bloques[${current}][permite_asignaturas_personalizadas]" value="0"><input class="form-check-input" type="checkbox" name="bloques[${current}][permite_asignaturas_personalizadas]" value="1"></td>
            <td class="text-center"><input type="hidden" name="bloques[${current}][activo]" value="0"><input class="form-check-input" type="checkbox" name="bloques[${current}][activo]" value="1" checked></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-bloque-row">Quitar</button></td>
        `;
    });

    bindDynamicTable('#asignaturas-table', 'add-asignatura-row', function (current) {
        return `
            <td><input type="number" class="form-control form-control-sm" name="asignaturas[${current}][orden]" min="1" value="${current + 1}"></td>
            <td><input type="text" class="form-control form-control-sm" name="asignaturas[${current}][asignatura]"></td>
            <td>
                <select class="form-select form-select-sm" name="asignaturas[${current}][tipo_bloque]">
                    <option value="asignatura">Asignatura</option>
                    <option value="subtotal">Subtotal</option>
                    <option value="libre_disposicion">Libre disposición</option>
                    <option value="total">Total</option>
                    <option value="plan_diferenciado">Plan diferenciado</option>
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="asignaturas[${current}][horas_semanales]"></td>
            <td><input type="number" class="form-control form-control-sm" step="0.01" min="0" name="asignaturas[${current}][horas_anuales]"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-asignatura-row">Quitar</button></td>
        `;
    });
});
</script>
@endpush
