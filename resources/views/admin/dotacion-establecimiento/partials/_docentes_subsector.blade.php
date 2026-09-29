@php
    $subsectores = $proceso['docentes_subsector'] ?? [];
    $gruposSubsector = collect($subsectores['grupos'] ?? []);
    $docentesSubsector = collect($subsectores['docentes'] ?? []);
@endphp

<section id="dotacion-docentes-subsector" class="border rounded-4 p-3 mt-3" aria-labelledby="dotacion-docentes-subsector-titulo">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h3 id="dotacion-docentes-subsector-titulo" class="h6 fw-bold mb-1">Docentes por asignatura del plan de estudio</h3>
            <p class="small text-muted mb-0">Asocie uno o varios docentes a cada asignatura consolidada por nivel. En la asignación de horas del plan solo aparecerán los docentes asociados, ordenados por prelación.</p>
        </div>
        <span class="badge rounded-pill {{ ($subsectores['completo'] ?? false) ? 'text-bg-success' : 'text-bg-warning' }}">
            {{ $subsectores['completas'] ?? 0 }} de {{ $subsectores['total'] ?? 0 }} asignaturas
        </span>
    </div>

    @if (session('subsector_success'))
        <div class="alert alert-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i> {{ session('subsector_success') }}</div>
    @endif
    @if (!($proceso['pasos']['planes']['completo'] ?? false))
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Termine la configuración de planes de estudio para asociar docentes.</div>
    @elseif (!($subsectores['disponible'] ?? false))
        <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Falta aplicar la migración de docentes por asignatura.</div>
    @elseif ($gruposSubsector->isEmpty())
        <div class="alert alert-info mb-0">No hay asignaturas del plan para asociar en este establecimiento.</div>
    @else
        @if ($errors->has('asignatura_key') || $errors->has('docentes') || $errors->has('docentes.*'))
            <div class="alert alert-danger" role="alert">{{ $errors->first('asignatura_key') ?: $errors->first('docentes') ?: $errors->first('docentes.*') }}</div>
        @endif
        @foreach ($gruposSubsector as $nivel => $grupo)
            @php $collapseId = 'dotacion-subsector-'.$nivel; @endphp
            <div class="border rounded-4 mb-2 overflow-hidden">
                <div class="bg-light p-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div class="fw-semibold">{{ $grupo['label'] }} <span class="badge rounded-pill text-bg-light border ms-1">{{ $grupo['asignaturas']->count() }} asignaturas</span></div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill collapsed" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                        <i class="bi bi-chevron-down" aria-hidden="true"></i> Ver asignaturas
                    </button>
                </div>
                <div id="{{ $collapseId }}" class="collapse">
                    <div class="vstack gap-3 p-3">
                        @foreach ($grupo['asignaturas'] as $asignatura)
                            <form method="POST" action="{{ route('admin.dotacion-establecimiento.docentes-subsector.sync', $establecimiento) }}" class="border rounded-3 p-3 bg-white">
                                @csrf
                                <input type="hidden" name="anio" value="2027">
                                <input type="hidden" name="asignatura_key" value="{{ $asignatura['key'] }}">
                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-4">
                                        <div class="fw-semibold">{{ $asignatura['nombre'] }}</div>
                                        <div class="small text-muted">{{ count($asignatura['cursos']) }} curso(s) · {{ $fmtProceso($asignatura['horas_aula']) }} h aula</div>
                                        <span class="badge rounded-pill {{ $asignatura['completo'] ? 'text-bg-success' : 'text-bg-warning' }} mt-1">{{ $asignatura['completo'] ? 'Docentes asociados' : 'Pendiente' }}</span>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label fw-semibold" for="subsector-docentes-{{ $asignatura['key'] }}">Docentes habilitados para {{ $asignatura['nombre'] }} <span class="text-danger">*</span></label>
                                        <select id="subsector-docentes-{{ $asignatura['key'] }}" name="docentes[]" class="form-select js-subsector-docentes" multiple required>
                                            @foreach ($docentesSubsector as $docente)
                                                @continue(! \App\Support\DotacionDocentesSubsector::docenteAdmisible($docente, $asignatura['nivel']))
                                                @php $rutDocente = \App\Support\DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')); @endphp
                                                <option value="{{ $rutDocente }}" data-nombre="{{ $docente['nombre'] }}" data-rut="{{ $docente['rut'] }}" data-titulo="{{ $docente['titulo'] ?? 'Sin título declarado' }}" data-prioridad-label="{{ !empty($docente['cupo_contrata_id']) ? 'Cupo por contratar' : ($docente['prioridad_2027_label'] ?? 'Docente') }}" data-antiguedad="{{ $docente['fecha_antiguedad'] ?? '' }}" data-titular-disponible="{{ $fmtProceso($docente['horas_titulares_disponibles'] ?? 0) }}" data-contrata-disponible="{{ $fmtProceso($docente['horas_contrata_disponibles'] ?? 0) }}" @selected(in_array($rutDocente, old('asignatura_key') === $asignatura['key'] ? old('docentes', []) : $asignatura['docentes'], true))>{{ $docente['nombre'] }} · {{ $docente['rut'] }} · Título: {{ $docente['titulo'] ?? 'Sin título declarado' }} · {{ !empty($docente['cupo_contrata_id']) ? 'Cupo por contratar' : ($docente['prioridad_2027_label'] ?? 'Docente') }} · Disponible: {{ $fmtProceso($docente['horas_disponibles'] ?? 0) }} h</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">Puede seleccionar varios docentes; la lista respeta la prelación y antigüedad vigentes.</div>
                                    </div>
                                    <div class="col-lg-2">
                                        <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-save" aria-hidden="true"></i> Guardar</button>
                                    </div>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</section>

@if (($subsectores['disponible'] ?? false) && $gruposSubsector->isNotEmpty())
    @include('admin.dotacion-establecimiento.partials._personal_select_assets')
    @push('styles')
        <style>
            #dotacion-docentes-subsector .select2-container { width: 100% !important; }
            #dotacion-docentes-subsector .select2-container--default .select2-selection--multiple { min-height: 2.65rem; border: 1px solid #dbe4f0; border-radius: .75rem; color: #0f172a; }
            #dotacion-docentes-subsector .select2-container--default.select2-container--focus .select2-selection--multiple { border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15); }
            #dotacion-docentes-subsector .select2-selection__choice { max-width: 100%; }
            #dotacion-docentes-subsector .select2-selection__choice__display { display: inline-block; max-width: min(32rem, 70vw); overflow: hidden; text-overflow: ellipsis; vertical-align: bottom; }
            .dotacion-subsector-dropdown .select2-search--dropdown { padding: .6rem; background: #f7faff; border-bottom: 1px solid #dce7f5; }
            .dotacion-subsector-dropdown .select2-search__field { min-height: 2.1rem; border: 1px solid #9db7dc; border-radius: .5rem; padding: .35rem .55rem; }
            .dotacion-subsector-dropdown .select2-results__option { padding: .45rem .65rem; }
            .dotacion-subsector-dropdown .select2-results__option--highlighted.select2-results__option--selectable { background: #eaf2ff; color: #122e58; }
            .dotacion-subsector-option { display: grid; gap: .25rem; }
            .dotacion-subsector-option__name { font-weight: 700; color: #172554; }
            .dotacion-subsector-option__meta { display: flex; gap: .35rem; flex-wrap: wrap; color: #475569; font-size: .78rem; }
            .dotacion-subsector-option__priority { color: #0b4aa2; font-weight: 700; }
            .dotacion-subsector-option__availability { color: #0f766e; }
        </style>
    @endpush
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (!window.jQuery || !window.jQuery.fn.select2) return;
                const $ = window.jQuery;
                window.jQuery('#dotacion-docentes-subsector .js-subsector-docentes').select2({
                    width: '100%',
                    placeholder: 'Buscar por nombre o RUT...',
                    closeOnSelect: false,
                    minimumResultsForSearch: 0,
                    dropdownCssClass: 'dotacion-subsector-dropdown',
                    templateResult: function (item) {
                        if (!item.id || !item.element) return item.text;
                        const option = $(item.element);
                        const result = $('<div>', { class: 'dotacion-subsector-option' });
                        const name = $('<div>', { class: 'dotacion-subsector-option__name' })
                            .text(option.data('nombre') + ' · ' + option.data('rut'));
                        const meta = $('<div>', { class: 'dotacion-subsector-option__meta' });
                        meta.append($('<span>').text('Título: ' + option.data('titulo')));
                        meta.append($('<span>', { class: 'dotacion-subsector-option__priority' }).text(option.data('prioridad-label')));
                        if (option.data('antiguedad')) meta.append($('<span>').text('Antigüedad: ' + option.data('antiguedad')));
                        meta.append($('<span>', { class: 'dotacion-subsector-option__availability' })
                            .text('Disponible: ' + option.data('titular-disponible') + ' titular + ' + option.data('contrata-disponible') + ' contrata'));
                        return result.append(name, meta);
                    },
                    templateSelection: function (item) {
                        if (!item.id || !item.element) return item.text;
                        const option = $(item.element);
                        return option.data('nombre') + ' · ' + option.data('rut') + ' · ' + option.data('titulo');
                    },
                    language: { noResults: function () { return 'No se encontraron docentes.'; } }
                });
            });
        </script>
    @endpush
@endif
