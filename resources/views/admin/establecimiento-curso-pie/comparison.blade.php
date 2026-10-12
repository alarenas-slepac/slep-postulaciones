@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    @vite(['resources/css/pie-comparison.css', 'resources/js/pie-comparison.js'])
@endpush
@section('content')
@php
    $oldRows = collect(old('rows', []))->keyBy('course_id');
    $editableCount = count(array_filter($rows, fn ($row) => $row['editable']));
    $formatHours = fn ($minutes) => \App\Support\PieHorasCalculator::formatMinutes($minutes === null ? null : (int) $minutes);
    $summaries = [];
    foreach ($years as $year) {
        $summaries[$year] = ['courses' => 0, 'neet' => 0, 'neep' => 0, 'total' => 0, 'unknown' => 0, 'total_crono_minutos' => 0, 'prof_educ_dif_minutos' => 0, 'pae_minutos' => 0];
        foreach ($rows as $row) {
            if ($row['year'] !== $year) continue;
            $summaries[$year]['courses']++;
            $summaries[$year]['neet'] += $row['neet']; $summaries[$year]['neep'] += $row['neep'];
            $summaries[$year]['total'] += $row['neet'] + $row['neep'];
            $unknown = false;
            foreach (\App\Services\Pie\PieComparisonService::HOUR_FIELDS as $field) {
                if (($row['calculation'][$field] ?? null) === null) $unknown = true;
                else $summaries[$year][$field] += (int) $row['calculation'][$field];
            }
            if ($unknown) $summaries[$year]['unknown']++;
        }
    }
    $rowIndex = 0;
    $clientData = ['rows' => $rows, 'groups' => $groups, 'years' => $years, 'matrix' => $matrix];
@endphp
<div class="pie-compare">
    <header class="pc-header p-4 mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div><div class="pc-context mb-2"><i class="bi bi-layout-split me-2" aria-hidden="true"></i>Estudiantes PIE por curso</div>
            <h1 class="mb-2">Comparación PIE entre años</h1><p class="text-secondary mb-0">Edita NEET y NEEP de ambos años, compara sus totales y guarda todos los cambios juntos.</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('admin.establecimiento-curso-pie.index', ['anio' => $years[1], 'establecimiento_id' => $filters['establecimiento_id'] ?? null]) }}">Volver al listado PIE</a>
    </header>
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <div id="pc-errors" class="alert alert-danger {{ $errors->any() ? '' : 'd-none' }}" role="alert" tabindex="-1">
        <p class="fw-bold mb-2">Revisa los datos antes de guardar.</p><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        <a class="btn btn-outline-secondary mt-3" href="{{ route('admin.establecimiento-curso-pie.comparison', $filters) }}">Recargar datos guardados</a>
    </div>
    <section class="pc-panel p-4 mb-4" aria-labelledby="pc-filters-title">
        <h2 id="pc-filters-title" class="h4 mb-3">Seleccionar establecimiento y años</h2>
        <form id="pc-filters" method="GET" action="{{ route('admin.establecimiento-curso-pie.comparison') }}" class="row g-3 align-items-end">
            <div class="col-12 col-lg-6"><label for="pc-establishment" class="form-label fw-bold">Establecimiento</label>
                <select class="form-select" id="pc-establishment" name="establecimiento_id" required><option value="">Seleccionar establecimiento</option>
                @foreach($establishments as $comuna => $items)<optgroup label="{{ $comuna }}">@foreach($items as $item)<option value="{{ $item->id }}" @selected((int) ($filters['establecimiento_id'] ?? 0) === $item->id)>{{ $item->rbd }} · {{ $item->nombre_establecimiento }}</option>@endforeach</optgroup>@endforeach</select>
                <div class="form-text">Busca por RBD o nombre. Se incluyen establecimientos con cursos activos.</div>
            </div>
            @foreach(['anio_a' => 'Primer año', 'anio_b' => 'Segundo año'] as $field => $label)<div class="col-6 col-lg-2"><label for="pc-{{ $field }}" class="form-label fw-bold">{{ $label }}</label><input class="form-control" type="number" id="pc-{{ $field }}" name="{{ $field }}" value="{{ $filters[$field] }}" min="2020" max="2100" step="1" required></div>@endforeach
            <div class="col-12 col-lg-2"><button class="btn btn-primary w-100">Comparar años</button></div>
        </form>
    </section>
    @if(!$establishment)
        <section class="pc-panel text-center p-5"><i class="bi bi-building fs-1 text-secondary" aria-hidden="true"></i><h2 class="h4 mt-3">Selecciona un establecimiento</h2><p class="text-secondary mb-0">La comparación mostrará sus cursos y secciones en los dos años elegidos.</p></section>
    @else
        <section class="pc-panel p-4 mb-4"><h2 class="h4 mb-2">{{ $establishment->nombre_establecimiento }}</h2><p class="text-secondary mb-2">RBD {{ $establishment->rbd }} · {{ $establishment->comuna ?: 'Sin comuna' }} · {{ $years[0] }} / {{ $years[1] }}</p>
            <p class="small text-secondary mb-0">Se compara el mismo nivel y letra de sección; no representa la promoción de estudiantes al curso siguiente. Los cursos que existen en un solo año aparecen sin contraparte.</p></section>
        <div class="row g-4 mb-4">
        @foreach($years as $year)
            <div class="col-12 col-xl-6"><section class="pc-panel p-4" data-summary-year="{{ $year }}" aria-labelledby="pc-summary-{{ $year }}">
                <div class="d-flex justify-content-between gap-3 mb-3"><h2 id="pc-summary-{{ $year }}" class="h4 mb-0">Totales {{ $year }}</h2><span class="text-secondary small">{{ $summaries[$year]['courses'] }} cursos/secciones</span></div>
                <div class="row g-3 mb-3">@foreach(['neet' => 'NEET', 'neep' => 'NEEP', 'total' => 'Total estudiantes PIE'] as $field => $label)<div class="col-4"><div class="small text-secondary">{{ $label }}</div><output class="pc-number d-block" data-summary="{{ $field }}">{{ $summaries[$year][$field] }}</output></div>@endforeach</div>
                <div class="pc-hours row g-3 p-3">@foreach(['total_crono_minutos' => 'Total crono', 'prof_educ_dif_minutos' => 'Prof. Educ. Dif.', 'pae_minutos' => 'PAEC'] as $field => $label)<div class="col-4"><div class="small text-secondary">{{ $label }}</div><output class="fw-bold d-block" data-summary="{{ $field }}">{{ $formatHours($summaries[$year][$field]) }}</output></div>@endforeach</div>
                <p class="small text-secondary mt-3 mb-0" data-summary="notice">{{ $summaries[$year]['unknown'] ? $summaries[$year]['unknown'].' cursos tienen horas sin cálculo; la suma de horas es parcial.' : 'Los totales incluyen todos los cursos activos mostrados para este año.' }}</p>
            </section></div>
        @endforeach
        </div>
        @if(!$groups)<section class="pc-panel text-center p-5"><h2 class="h4">No hay cursos activos en estos años</h2><p class="text-secondary mb-0">Selecciona otros años para consultar este establecimiento.</p></section>
        @else
        <form id="pc-editor" action="{{ route('admin.establecimiento-curso-pie.comparison.update') }}" method="POST">
            @csrf @method('PUT')
            @foreach($filters as $field => $value)<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endforeach
            <input type="hidden" name="rules_token" value="{{ old('rules_token', $rules_token) }}">
            <section class="pc-panel p-4 mb-4" aria-labelledby="pc-table-title">
                <h2 id="pc-table-title" class="h4 mb-2">Cursos y secciones</h2><p class="small text-secondary mb-3">NEET + NEEP debe ser menor o igual a la matrícula. Las horas nuevas usan el catálogo PIE vigente y las reglas JEC/EPJA. Se mantienen estado y observaciones.</p>
                <noscript><div class="alert alert-info">La actualización al instante requiere JavaScript. Puedes editar y guardar; el servidor recalculará las horas al guardar.</div></noscript>
                <div class="table-responsive"><table class="table pc-table align-top mb-0"><caption class="visually-hidden">Comparación editable PIE {{ $years[0] }} y {{ $years[1] }}</caption>
                    <thead><tr><th scope="col">Curso / sección</th>@foreach($years as $year)<th scope="col">{{ $year }} · NEET, NEEP y horas PIE</th>@endforeach<th scope="col">Variación {{ $years[0] }} → {{ $years[1] }}</th></tr></thead>
                    <tbody>@foreach($groups as $groupIndex => $group)<tr data-group="{{ $groupIndex }}">
                        <th scope="row"><div class="fw-bold">{{ $group['label'] }}</div><div class="small text-secondary mt-1">{{ $group['letter'] ? 'Sección '.$group['letter'] : 'Sin letra de sección' }}</div></th>
                        @foreach($years as $year)<td>@forelse($group['years'][$year] as $courseId)
                            @php($row = $rows[$courseId]) @php($oldRow = $oldRows->get($courseId, []))
                            <div class="pc-course p-3 mb-2" data-course-id="{{ $courseId }}">
                                <div class="fw-bold mb-1">{{ $row['label'] }}</div><div class="small text-secondary mb-2">Matrícula {{ $row['matricula'] }} · {{ $row['regime'] === 'con_jec' ? 'CON JEC' : 'SIN JEC' }}{{ str_contains($row['rule_key'], ':epja') ? ' · EPJA' : '' }}</div>
                                <p class="small text-secondary mb-2">{{ $row['plan'] }} · {{ $row['state'] }}</p>
                                @if($row['editable'])
                                    <input type="hidden" name="rows[{{ $rowIndex }}][course_id]" value="{{ $courseId }}"><input type="hidden" name="rows[{{ $rowIndex }}][version]" data-version value="{{ $oldRow['version'] ?? $row['version'] }}">
                                    <div class="row g-2 mb-3">@foreach(['neet' => 'NEET', 'neep' => 'NEEP'] as $field => $label)<div class="col-4"><label class="form-label fw-bold small" for="pc-{{ $courseId }}-{{ $field }}">{{ $label }} {{ $year }}</label><input class="form-control" id="pc-{{ $courseId }}-{{ $field }}" data-count="{{ $field }}" name="rows[{{ $rowIndex }}][{{ $field }}]" type="number" min="0" max="9999" step="1" required value="{{ $oldRow[$field] ?? $row[$field] }}" aria-describedby="pc-error-{{ $courseId }}"></div>@endforeach
                                        <div class="col-4"><div class="form-label fw-bold small">Total PIE</div><output class="pc-row-total d-block" data-output="total">{{ $row['neet'] + $row['neep'] }}</output></div></div>
                                    @php($rowIndex++)
                                @else<div class="alert alert-warning small mb-2">Registro PIE inconsistente. Revisa su identidad en el listado antes de editarlo.</div><p class="small mb-2">NEET {{ $row['neet'] }} · NEEP {{ $row['neep'] }} · Total {{ $row['neet'] + $row['neep'] }}</p>@endif
                                <dl class="pc-calculation mb-0">@foreach(['total_crono_minutos' => 'Total crono', 'prof_educ_dif_minutos' => 'Prof. Educ. Dif.', 'pae_minutos' => 'PAEC'] as $field => $label)<div><dt>{{ $label }}</dt><dd class="mb-0" data-output="{{ $field }}">{{ $formatHours($row['calculation'][$field] ?? null) }}</dd></div>@endforeach</dl>
                                <p class="small text-secondary mt-2 mb-0" data-output="note">{{ $row['calculation']['calculo_observacion'] ?? '' }}</p>
                                <p class="invalid-feedback d-block mb-0" id="pc-error-{{ $courseId }}" data-error aria-live="polite"></p>
                            </div>
                        @empty<div class="pc-missing p-3">No existe curso/sección activo en {{ $year }}.</div>@endforelse</td>@endforeach
                        <td><output class="fw-bold d-block" data-delta="students">{{ $group['years'][$years[0]] && $group['years'][$years[1]] ? collect($group['years'][$years[1]])->sum(fn ($id) => $rows[$id]['neet'] + $rows[$id]['neep']) - collect($group['years'][$years[0]])->sum(fn ($id) => $rows[$id]['neet'] + $rows[$id]['neep']) : '—' }}</output><span class="small text-secondary d-block">estudiantes PIE</span><output class="fw-bold d-block mt-3" data-delta="hours">—</output><span class="small text-secondary d-block">horas crono</span></td>
                    </tr>@endforeach</tbody>
                </table></div>
            </section>
            {{-- Al final: un formulario truncado por max_input_vars no puede guardarse parcialmente. --}}
            <input type="hidden" name="expected_rows" value="{{ $editableCount }}">
            <div class="pc-savebar pc-panel p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div><p class="fw-bold mb-1" id="pc-dirty-status" role="status" aria-live="polite">Edita los valores y guarda los cambios de ambos años.</p><p class="small text-secondary mb-0">Se guardan juntos; si una fila tiene un error, no se guarda ninguna modificación.</p></div>
                <button class="btn btn-primary" id="pc-save" @disabled(!$editableCount)>Guardar todas las modificaciones</button>
            </div>
        </form>
        <script type="application/json" id="pc-data">@json($clientData)</script>
        @endif
    @endif
</div>
@endsection
@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush
