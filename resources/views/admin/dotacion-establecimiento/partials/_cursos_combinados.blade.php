@php
    $fmt = $fmt ?? fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $config = $cursosCombinados ?? [];
    $tablesReady = (bool) ($config['tables_ready'] ?? false);
    $groups = collect($config['grupos'] ?? []);
    $availableCourses = collect($config['cursos_disponibles'] ?? []);
    $summary = $config['resumen'] ?? [];
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-start gap-3">
                <span class="dotacion-icon" style="width:40px;height:40px;background:#6f42c1;"><i class="bi bi-intersect"></i></span>
                <div>
                    <div class="dotacion-eyebrow">Configuración curricular</div>
                    <h2 class="h5 fw-bold mb-1">Cursos combinados</h2>
                    <div class="text-muted small">Consolida las horas aula de cursos que funcionan simultáneamente. Los cursos originales, su matrícula y sus planes se conservan para trazabilidad.</div>
                </div>
            </div>
            <span class="badge rounded-pill text-bg-light border">Año {{ $anio }}</span>
        </div>
    </div>

    <div class="card-body">
        @if (!$tablesReady)
            <div class="alert alert-warning rounded-4 mb-0">
                <div class="fw-semibold">Configuración pendiente</div>
                Ejecute la migración del parche para habilitar cursos combinados.
            </div>
        @else
            <div class="row g-3 mb-4">
                <div class="col-lg-3 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Grupos activos</div><div class="h4 fw-bold mb-0">{{ (int) ($summary['grupos_activos'] ?? 0) }}</div></div></div>
                <div class="col-lg-3 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Cursos combinados</div><div class="h4 fw-bold mb-0">{{ (int) ($summary['cursos_combinados'] ?? 0) }}</div></div></div>
                <div class="col-lg-3 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas aula brutas</div><div class="h4 fw-bold mb-0">{{ $fmt($summary['horas_brutas'] ?? 0) }}</div></div></div>
                <div class="col-lg-3 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Reducción por combinación</div><div class="h4 fw-bold text-success mb-0">{{ $fmt($summary['reduccion'] ?? 0) }}</div><div class="small text-muted">Horas aula no duplicadas</div></div></div>
            </div>

            <div class="alert alert-info rounded-4">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle"></i> Regla de cálculo</div>
                <div class="small">Conjunta: usa la mayor carga de la asignatura entre los cursos. Separada: suma todas las cargas. Personalizada: utiliza el valor informado. Mixta: suma horas conjuntas más horas exclusivas por curso. Se permite combinar NT1 y NT2 conservando su regla especial con o sin JEC. Las asignaciones individuales anteriores no se borran: si dejan de corresponder a la nueva necesidad aparecerán en el bloque Horas fantasmas para revisión manual.</div>
            </div>
        @endif
    </div>
</div>

@if ($tablesReady && $canManageCursosCombinados)
    @if ($errors->any())
        <div class="alert alert-danger rounded-4 shadow-sm mb-4">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon"></i> No fue posible guardar el curso combinado</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card dotacion-section mb-4">
        <div class="dotacion-section-header">
            <div class="dotacion-eyebrow">Nuevo grupo</div>
            <h2 class="h5 fw-bold mb-1">Combinar cursos del establecimiento</h2>
            <div class="text-muted small">Seleccione al menos dos cursos que se impartan simultáneamente.</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.dotacion-establecimiento.cursos-combinados.store', $establecimiento) }}" class="row g-3">
                @csrf
                <input type="hidden" name="anio" value="{{ $anio }}">
                <div class="col-lg-5">
                    <label class="form-label fw-semibold">Nombre del grupo</label>
                    <input type="text" name="nombre" class="form-control" maxlength="180" value="{{ old('nombre') }}" placeholder="Ej.: NT1 y NT2 A" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-semibold">Proporción contractual</label>
                    <select name="proporcion" class="form-select" required>
                        <option value="auto" @selected(old('proporcion', 'auto') === 'auto')>Automática si todos coinciden</option>
                        <option value="65_35" @selected(old('proporcion') === '65_35')>65/35</option>
                        <option value="60_40" @selected(old('proporcion') === '60_40')>60/40</option>
                        <option value="nt_jec" @selected(old('proporcion') === 'nt_jec')>NT1/NT2 con JEC · regla especial</option>
                        <option value="nt_sin_jec" @selected(old('proporcion') === 'nt_sin_jec')>NT1/NT2 sin JEC · regla especial</option>
                    </select>
                    <div class="form-text">Para NT1 y NT2 puede usar Automática cuando ambos cursos comparten el mismo régimen.</div>
                </div>
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Observación</label>
                    <input type="text" name="observacion" class="form-control" maxlength="2000" value="{{ old('observacion') }}" placeholder="Justificación o antecedente">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">Cursos disponibles</label>
                    <div class="row g-2">
                        @forelse ($availableCourses->where('disponible', true) as $course)
                            <div class="col-xl-3 col-md-4 col-sm-6">
                                <label class="border rounded-3 p-2 d-flex gap-2 align-items-start h-100">
                                    <input class="form-check-input mt-1" type="checkbox" name="curso_ids[]" value="{{ $course['id'] }}" @checked(in_array((string) $course['id'], array_map('strval', old('curso_ids', [])), true))>
                                    <span><span class="fw-semibold">{{ $course['label'] }}</span><br><span class="small text-muted">Matrícula {{ $course['matricula'] }}</span></span>
                                </label>
                            </div>
                        @empty
                            <div class="col-12 text-muted small">No existen dos cursos disponibles para crear otra combinación activa.</div>
                        @endforelse
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-primary rounded-pill px-4" type="submit" @disabled($availableCourses->where('disponible', true)->count() < 2)>
                        <i class="bi bi-plus-circle"></i> Crear curso combinado
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($tablesReady)
    @forelse ($groups as $group)
        @php
            $memberIds = collect($group['miembros'] ?? [])->pluck('id')->map(fn ($id) => (int) $id);
            $candidateCourses = $availableCourses->filter(fn ($course) => (bool) ($course['disponible'] ?? false) || $memberIds->contains((int) $course['id']));
            $subjectRows = collect($group['asignaturas'] ?? []);
            $totals = $group['totales'] ?? [];
        @endphp
        <div class="card dotacion-section mb-4 border-{{ $group['activo'] ? 'primary' : 'secondary' }}">
            <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h2 class="h5 fw-bold mb-0">{{ $group['nombre'] }}</h2>
                        <span class="badge rounded-pill {{ $group['activo'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $group['activo'] ? 'Activo' : 'Inactivo' }}</span>
                        <span class="badge rounded-pill text-bg-light border">{{ $group['proporcion_label'] }}</span>
                    </div>
                    <div class="small text-muted mt-1">{{ collect($group['miembros'] ?? [])->pluck('label')->implode(' + ') }}</div>
                    @if (!empty($group['observacion']))<div class="small mt-1">{{ $group['observacion'] }}</div>@endif
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge rounded-pill text-bg-light border">Brutas {{ $fmt($totals['horas_brutas'] ?? 0) }}</span>
                    <span class="badge rounded-pill text-bg-primary">Requeridas {{ $fmt($totals['horas_requeridas'] ?? 0) }}</span>
                    <span class="badge rounded-pill text-bg-success">Reducción {{ $fmt($totals['reduccion'] ?? 0) }}</span>
                    <span class="badge rounded-pill {{ ($totals['pendientes'] ?? 0) > 0.01 ? 'text-bg-warning' : 'text-bg-success' }}">Saldo {{ $fmt($totals['pendientes'] ?? 0) }}</span>
                </div>
            </div>

            <div class="card-body">
                @if ($canManageCursosCombinados)
                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.cursos-combinados.update', [$establecimiento, $group['id']]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <div class="row g-3 mb-4">
                            <div class="col-lg-5">
                                <label class="form-label fw-semibold">Nombre</label>
                                <input class="form-control" name="nombre" value="{{ $group['nombre'] }}" required maxlength="180">
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label fw-semibold">Proporción</label>
                                <select class="form-select" name="proporcion" required>
                                    <option value="auto" @selected($group['proporcion'] === 'auto')>Automática</option>
                                    <option value="65_35" @selected($group['proporcion'] === '65_35')>65/35</option>
                                    <option value="60_40" @selected($group['proporcion'] === '60_40')>60/40</option>
                                    <option value="nt_jec" @selected($group['proporcion'] === 'nt_jec')>NT1/NT2 con JEC · regla especial</option>
                                    <option value="nt_sin_jec" @selected($group['proporcion'] === 'nt_sin_jec')>NT1/NT2 sin JEC · regla especial</option>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label fw-semibold">Estado</label>
                                <select class="form-select" name="activo">
                                    <option value="1" @selected($group['activo'])>Activo</option>
                                    <option value="0" @selected(!$group['activo'])>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label fw-semibold">Contrato requerido</label>
                                <div class="form-control bg-light fw-bold">{{ $fmt($totals['horas_contrato'] ?? 0) }}</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Cursos integrantes</label>
                                <div class="row g-2">
                                    @foreach ($candidateCourses as $course)
                                        <div class="col-xl-3 col-md-4 col-sm-6">
                                            <label class="border rounded-3 p-2 d-flex gap-2 align-items-start h-100">
                                                <input class="form-check-input mt-1" type="checkbox" name="curso_ids[]" value="{{ $course['id'] }}" @checked($memberIds->contains((int) $course['id']))>
                                                <span><span class="fw-semibold">{{ $course['label'] }}</span><br><span class="small text-muted">Matrícula {{ $course['matricula'] }}</span></span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Observación</label>
                                <textarea class="form-control" name="observacion" rows="2" maxlength="2000">{{ $group['observacion'] }}</textarea>
                            </div>
                        </div>

                        @if ($group['activo'] && $subjectRows->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Asignatura</th>
                                            <th>Horas por curso</th>
                                            <th style="min-width:170px;">Modalidad</th>
                                            <th style="min-width:125px;">Horas conjuntas</th>
                                            <th style="min-width:125px;">Horas personalizadas</th>
                                            <th style="min-width:210px;">Horas exclusivas por curso</th>
                                            <th>Resultado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($subjectRows as $index => $subject)
                                            @php
                                                $mode = $subject['curso_combinado_modalidad'] ?? 'conjunta';
                                                $exclusiveValues = collect($subject['horas_exclusivas'] ?? []);
                                            @endphp
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="asignaturas[{{ $index }}][key]" value="{{ $subject['curso_combinado_asignatura_key'] }}">
                                                    <input type="hidden" name="asignaturas[{{ $index }}][nombre]" value="{{ $subject['titulo'] }}">
                                                    <div class="fw-semibold">{{ $subject['titulo'] }}</div>
                                                    <div class="small text-muted">Brutas {{ $fmt($subject['horas_plan_brutas'] ?? 0) }} · Reducción {{ $fmt($subject['horas_plan_reduccion'] ?? 0) }}</div>
                                                </td>
                                                <td class="small">
                                                    @foreach (($subject['curso_combinado_horas_por_curso'] ?? []) as $label => $hours)
                                                        <div>{{ $label }}: <strong>{{ $fmt($hours) }}</strong></div>
                                                    @endforeach
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" name="asignaturas[{{ $index }}][modalidad]">
                                                        @foreach (\App\Models\DotacionCursoCombinadoAsignatura::MODALIDADES as $key => $label)
                                                            <option value="{{ $key }}" @selected($mode === $key)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="number" class="form-control form-control-sm" name="asignaturas[{{ $index }}][horas_conjuntas]" step="0.25" min="0" value="{{ $subject['horas_conjuntas'] ?? '' }}" placeholder="Máximo por curso"></td>
                                                <td><input type="number" class="form-control form-control-sm" name="asignaturas[{{ $index }}][horas_personalizadas]" step="0.25" min="0.25" value="{{ $subject['horas_personalizadas'] ?? '' }}" placeholder="Total requerido"></td>
                                                <td>
                                                    @foreach (($group['miembros'] ?? []) as $member)
                                                        <div class="input-group input-group-sm mb-1">
                                                            <span class="input-group-text" style="min-width:105px;">{{ $member['label'] }}</span>
                                                            <input type="number" class="form-control" name="asignaturas[{{ $index }}][horas_exclusivas][{{ $member['id'] }}]" step="0.25" min="0" value="{{ $exclusiveValues->get((string) $member['id'], $exclusiveValues->get($member['id'], '')) }}">
                                                        </div>
                                                    @endforeach
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-primary">{{ $fmt($subject['horas_plan_requeridas'] ?? 0) }} aula</div>
                                                    <div class="small text-muted">{{ $fmt($subject['horas_contrato_requeridas'] ?? 0) }} contrato · {{ $subject['proporcion'] ?? '—' }}</div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif ($group['activo'])
                            <div class="alert alert-warning rounded-4">El grupo no tiene asignaturas calculables. Revise que los cursos tengan planes de estudio activos.</div>
                        @else
                            <div class="alert alert-secondary rounded-4">Active el grupo y guarde para generar la matriz de asignaturas combinadas.</div>
                        @endif

                        <div class="d-flex justify-content-end mt-3">
                            <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-save"></i> Guardar y recalcular</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.cursos-combinados.destroy', [$establecimiento, $group['id']]) }}" class="mt-2 text-end" onsubmit="return confirm('¿Eliminar este curso combinado? Los cursos originales no serán eliminados.');">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><i class="bi bi-trash"></i> Eliminar grupo</button>
                    </form>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Asignatura</th><th>Modalidad</th><th class="text-end">Horas aula</th><th class="text-end">Contrato</th><th class="text-end">Saldo</th></tr></thead>
                            <tbody>
                                @forelse ($subjectRows as $subject)
                                    <tr><td>{{ $subject['titulo'] }}</td><td>{{ ucfirst($subject['curso_combinado_modalidad'] ?? 'conjunta') }}</td><td class="text-end">{{ $fmt($subject['horas_plan_requeridas'] ?? 0) }}</td><td class="text-end">{{ $fmt($subject['horas_contrato_requeridas'] ?? 0) }}</td><td class="text-end">{{ $fmt($subject['horas_plan_pendientes'] ?? 0) }}</td></tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">Sin asignaturas consolidadas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @empty
        @if ($tablesReady)
            <div class="card dotacion-section"><div class="card-body text-center py-5 text-muted"><i class="bi bi-intersect fs-2 d-block mb-2"></i>Aún no existen cursos combinados configurados para este establecimiento y año.</div></div>
        @endif
    @endforelse
@endif
