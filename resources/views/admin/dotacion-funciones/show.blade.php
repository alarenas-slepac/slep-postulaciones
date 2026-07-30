@extends('layouts.app')

@section('content')
    @php
        $categoriaClass = [
            'directiva' => 'primary',
            'tecnico_pedagogica' => 'success',
            'pie' => 'info',
            'planes_programas' => 'warning',
            'otras_funciones_docentes' => 'secondary',
        ];
        $estadoClass = [
            'borrador' => 'secondary',
            'en_revision' => 'warning',
            'observado' => 'danger',
            'validado_uatp' => 'success',
            'rechazado' => 'dark',
        ];
    @endphp

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Dotación funciones y planes</h1>
            <div class="text-muted small">{{ $establecimiento->rbd }} — {{ $establecimiento->nombre_establecimiento }} · {{ $establecimiento->comuna ?: 'Sin comuna' }} · Año {{ $anio }}</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.dotacion-funciones.index', ['anio' => $anio]) }}">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">No fue posible guardar la información</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <div class="text-muted small">Matrícula total</div>
                <div class="fs-3 fw-bold">{{ number_format((int) $contexto['matricula_total'], 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <div class="text-muted small">Cursos con estudiantes NEE</div>
                <div class="fs-3 fw-bold text-success">{{ number_format((int) $contexto['cursos_nee'], 0, ',', '.') }}</div>
                <div class="small text-muted">Coordinación PIE = 2 hrs por curso.</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <div class="text-muted small">Matrícula NT1 + NT2</div>
                <div class="fs-3 fw-bold text-warning">{{ number_format((int) $contexto['matricula_nt1_nt2'], 0, ',', '.') }}</div>
                <div class="small text-muted">Transición educativa.</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <div class="text-muted small">Total horas estimadas</div>
                <div class="fs-3 fw-bold text-primary">{{ number_format((int) $resumen['horas_totales'], 0, ',', '.') }}</div>
                <div class="small text-muted">Automáticas + declaradas/aprobadas.</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Parámetros del establecimiento</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.dotacion-funciones.config', [$establecimiento]) }}">
                        @csrf
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <div class="alert alert-info small mb-3">
                            <div class="fw-semibold">Inspector(a) General</div>
                            <div>Se considera cargo fijo con 44 horas, independiente de si Director(a) es ADP. Esta regla se calcula automáticamente en la dotación directiva.</div>
                        </div>
                        <label class="form-label">Observación</label>
                        <textarea class="form-control" name="observacion" rows="3" @disabled(!$canEdit)>{{ old('observacion', $config->observacion) }}</textarea>
                        @if ($canEdit)
                            <div class="mt-3">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar parámetros</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Consolidado por bloque</div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($bloquesConsolidados as $bloqueKey => $bloqueLabel)
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="small text-muted">{{ $bloqueLabel }}</div>
                                    <div class="fs-4 fw-bold text-{{ $categoriaClass[$bloqueKey] ?? 'secondary' }}">{{ number_format((int) ($resumen['consolidado_por_bloque'][$bloqueKey]['total'] ?? 0), 0, ',', '.') }} hrs</div>
                                    <div class="small text-muted">Aut.: {{ number_format((int) ($resumen['consolidado_por_bloque'][$bloqueKey]['automaticas'] ?? 0), 0, ',', '.') }} · Decl./aprob.: {{ number_format((int) ($resumen['consolidado_por_bloque'][$bloqueKey]['declaradas'] ?? 0), 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info shadow-sm">
        <div class="fw-semibold"><i class="bi bi-info-circle"></i> Criterios aplicados</div>
        <div class="small">
            Coordinación PIE se calcula a <strong>2 horas por curso con estudiantes NEE</strong>, sin máximo; si supera 44 horas, la diferencia debe asignarse a otro/a docente diferencial. Orientador(a) no es obligatorio y queda como función declarable. Coordinadores de Ciclo, TP, Especialidad u otros pueden declararse múltiples veces por establecimiento, con 3 o 5 horas sugeridas según matrícula.
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Resumen consolidado de dotación</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Bloque</th>
                        <th class="text-end">Horas automáticas</th>
                        <th class="text-end">Horas declaradas/aprobadas</th>
                        <th class="text-end">Total horas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bloquesConsolidados as $bloqueKey => $bloqueLabel)
                        <tr>
                            <td>
                                <span class="badge text-bg-{{ $categoriaClass[$bloqueKey] ?? 'secondary' }} me-2">&nbsp;</span>
                                <span class="fw-semibold">{{ $bloqueLabel }}</span>
                            </td>
                            <td class="text-end">{{ number_format((int) ($resumen['consolidado_por_bloque'][$bloqueKey]['automaticas'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($resumen['consolidado_por_bloque'][$bloqueKey]['declaradas'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-bold text-primary">{{ number_format((int) ($resumen['consolidado_por_bloque'][$bloqueKey]['total'] ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total establecimiento</th>
                        <th class="text-end">{{ number_format((int) ($resumen['horas_automaticas'] ?? 0), 0, ',', '.') }}</th>
                        <th class="text-end">{{ number_format((int) ($resumen['horas_declaradas'] ?? 0), 0, ',', '.') }}</th>
                        <th class="text-end text-primary">{{ number_format((int) ($resumen['horas_totales'] ?? 0), 0, ',', '.') }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @foreach ($categorias as $categoriaKey => $categoriaLabel)
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                <div class="fw-semibold"><span class="badge text-bg-{{ $categoriaClass[$categoriaKey] ?? 'secondary' }} me-2">&nbsp;</span>{{ $categoriaLabel }}</div>
                <div class="small text-muted">Horas sugeridas y registros declarados</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Función / plan</th>
                            <th>Origen</th>
                            <th>Detalle / fundamento</th>
                            <th class="text-end">Hrs sugeridas</th>
                            <th class="text-end">Hrs declaradas</th>
                            <th class="text-end">Hrs aprobadas</th>
                            <th>Estado</th>
                            <th class="text-end" style="width: 260px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($sugerencias[$categoriaKey] ?? collect()) as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item['nombre_funcion'] }}</div>
                                    @if (($item['codigo'] ?? '') === 'coordinador_pie')
                                        @php($dist = \App\Support\DotacionFuncionesCalculator::distribucionCoordinacionPie((int) $item['horas_sugeridas']))
                                        @if (!empty($dist))
                                            <div class="small text-muted">
                                                Distribución: @foreach ($dist as $d) Docente {{ $d['docente'] }}: {{ $d['horas'] }} hrs{{ !$loop->last ? ' · ' : '' }} @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td><span class="badge text-bg-light border">Automática</span></td>
                                <td class="small text-muted">{{ $item['detalle'] }}</td>
                                <td class="text-end fw-semibold">{{ number_format((int) $item['horas_sugeridas'], 0, ',', '.') }}</td>
                                <td class="text-end">—</td>
                                <td class="text-end">—</td>
                                <td><span class="badge text-bg-primary">Calculado</span></td>
                                <td class="text-end text-muted small">Sin acción</td>
                            </tr>
                        @endforeach

                        @foreach (($manuales[$categoriaKey] ?? collect()) as $funcion)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $funcion->nombre_funcion }}</div>
                                    @if ($funcion->tipo_coordinacion)
                                        <div class="small text-muted">{{ $funcion->tipo_coordinacion }}</div>
                                    @endif
                                </td>
                                <td><span class="badge text-bg-light border">Declarada</span></td>
                                <td class="small text-muted">
                                    {{ $funcion->descripcion_funcion ?: $funcion->fundamento ?: 'Sin detalle.' }}
                                    @if ($funcion->observacion)
                                        <div class="mt-1"><strong>Obs.:</strong> {{ $funcion->observacion }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ $funcion->horas_sugeridas !== null ? number_format((int) $funcion->horas_sugeridas, 0, ',', '.') : '—' }}</td>
                                <td class="text-end fw-semibold">{{ number_format((int) ($funcion->horas_declaradas ?? 0), 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold text-success">{{ $funcion->horas_aprobadas !== null ? number_format((int) $funcion->horas_aprobadas, 0, ',', '.') : '—' }}</td>
                                <td><span class="badge text-bg-{{ $estadoClass[$funcion->estado] ?? 'secondary' }}">{{ $funcion->estadoLabel() }}</span></td>
                                <td class="text-end">
                                    @if ($canValidate)
                                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.validar', [$establecimiento, $funcion]) }}" class="d-inline-flex gap-1 mb-1">
                                            @csrf
                                            <input type="number" name="horas_aprobadas" class="form-control form-control-sm" style="width: 80px" min="0" max="200" value="{{ $funcion->horas_aprobadas ?? $funcion->horas_declaradas }}" title="Horas aprobadas">
                                            <button class="btn btn-sm btn-success" title="Validar"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.observar', [$establecimiento, $funcion]) }}" class="d-inline-flex gap-1 mb-1">
                                            @csrf
                                            <input type="text" name="observacion" class="form-control form-control-sm" style="width: 120px" placeholder="Observación" required>
                                            <button class="btn btn-sm btn-outline-warning" title="Observar"><i class="bi bi-exclamation-triangle"></i></button>
                                        </form>
                                    @endif
                                    @if ($canEdit)
                                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.destroy', [$establecimiento, $funcion]) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta función declarada?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        @if (($sugerencias[$categoriaKey] ?? collect())->isEmpty() && ($manuales[$categoriaKey] ?? collect())->isEmpty())
                            <tr><td colspan="8" class="text-center text-muted py-3">Sin registros para esta categoría.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if ($canEdit)
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Agregar coordinación técnico-pedagógica</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.store', [$establecimiento]) }}">
                            @csrf
                            <input type="hidden" name="anio" value="{{ $anio }}">
                            <input type="hidden" name="tipo" value="coordinacion">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select" name="tipo_coordinacion">
                                        <option value="Ciclo">Ciclo</option>
                                        <option value="Técnico Profesional">Técnico Profesional</option>
                                        <option value="Especialidad">Especialidad</option>
                                        <option value="Evaluación">Evaluación</option>
                                        <option value="Currículum">Currículum</option>
                                        <option value="Apoyo UTP">Apoyo UTP</option>
                                        <option value="Apoyo Directivo">Apoyo Directivo</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nombre coordinación</label>
                                    <input class="form-control" name="nombre_funcion" required placeholder="Ej: Coordinador Primer Ciclo">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Horas declaradas</label>
                                    <input type="number" class="form-control" name="horas_declaradas" min="0" max="200" value="{{ (int) (($contexto['matricula_total'] ?? 0) > 300 ? 5 : 3) }}" required>
                                    <div class="form-text">Sugerencia: {{ (int) (($contexto['matricula_total'] ?? 0) > 300 ? 5 : 3) }} hrs.</div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Fundamento</label>
                                    <input class="form-control" name="fundamento" placeholder="Fundamento o foco de la coordinación">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" name="descripcion_funcion" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-success" type="submit"><i class="bi bi-plus-circle"></i> Agregar coordinación</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Agregar otra función docente</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.store', [$establecimiento]) }}">
                            @csrf
                            <input type="hidden" name="anio" value="{{ $anio }}">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Tipo</label>
                                    <select class="form-select" name="tipo" required>
                                        <option value="otra">Otra función docente</option>
                                        <option value="orientador">Orientador(a)</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label">Nombre función</label>
                                    <input class="form-control" name="nombre_funcion" required placeholder="Ej: Evaluador/a, Curriculista, Subdirector/a">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Horas declaradas</label>
                                    <input type="number" class="form-control" name="horas_declaradas" min="0" max="200" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Fundamento</label>
                                    <input class="form-control" name="fundamento" placeholder="Justificación de la función">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" name="descripcion_funcion" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle"></i> Agregar función</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
