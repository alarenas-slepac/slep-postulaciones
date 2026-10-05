@extends('layouts.app')

@push('styles')
    @vite(['resources/css/dotacion-establecimiento.css', 'resources/js/dotacion-asignacion.js'])
@endpush

@section('content')
<div class="dotacion-workspace">
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

    <div class="slep-card p-4 d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <div class="small text-muted text-uppercase mb-2">Dotación · Funciones del establecimiento</div>
            <h1 class="h2 fw-bold mb-1">Dotación funciones y planes</h1>
            <div class="text-muted small">{{ $establecimiento->rbd }} — {{ $establecimiento->nombre_establecimiento }} · {{ $establecimiento->comuna ?: 'Sin comuna' }} · Año {{ $anio }}</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary rounded-pill" href="{{ $volverUrl }}">
                <i class="bi bi-arrow-left"></i> {{ $accionesContexto ? 'Volver a Dotación docente' : 'Volver a establecimientos' }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">No fue posible guardar la información</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <div class="text-muted small">Matrícula total</div>
                <div class="fs-3 fw-bold">{{ number_format((int) $contexto['matricula_total'], 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100"><div class="card-body">
                <div class="text-muted small">Cursos con estudiantes NEE</div>
                <div class="fs-3 fw-bold text-success">{{ number_format((int) $contexto['cursos_nee'], 0, ',', '.') }}</div>
                <div class="small text-muted">Coordinación PIE = 2 hrs por curso.</div>
            </div></div>
        </div>
        <div class="col-md-4">
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
                    <form data-dotacion-save method="POST" action="{{ route('admin.dotacion-funciones.config', [$establecimiento, ...$accionesContexto]) }}">
                        @csrf
                        <input type="hidden" name="formulario" value="parametros">
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <div class="alert alert-info small mb-3">
                            <div class="fw-semibold">Inspector(a) General</div>
                            <div>Se considera cargo fijo con 44 horas, independiente de si Director(a) es ADP. Esta regla se calcula automáticamente en la dotación directiva.</div>
                        </div>
                        @if ($canConfigureDirectorAdp)
                            <input type="hidden" name="director_adp" value="0">
                            <div class="form-check form-switch border rounded p-3 ps-5 mb-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="director_adp" name="director_adp" value="1" @checked(old('formulario') === 'parametros' ? old('director_adp') : $config->director_adp)>
                                <label class="form-check-label fw-semibold" for="director_adp">Director(a) ADP</label>
                                <div class="form-text">Habilita 44 horas como función directiva normativa sólo para este establecimiento y año.</div>
                            </div>
                        @endif
                        <label class="form-label" for="funciones-observacion-config">Observación</label>
                        <textarea id="funciones-observacion-config" class="form-control" name="observacion" rows="3" @disabled(!$canEdit)>{{ old('formulario') === 'parametros' ? old('observacion', $config->observacion) : $config->observacion }}</textarea>
                        @if ($canEdit || $canConfigureDirectorAdp)
                            <div class="mt-3">
                                <button class="btn btn-primary rounded-pill" type="submit"><i class="bi bi-save"></i> Guardar parámetros</button>
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

    <div data-dotacion-catalog="funciones">
    @include('admin.dotacion-establecimiento.partials._catalogo_filtros', ['catalogoId' => 'funciones-filtro', 'catalogoTitulo' => 'Revisar funciones del establecimiento', 'catalogoBusqueda' => 'Función, categoría o fundamento', 'catalogoEstados' => ['automatica' => 'Calculadas automáticamente', 'declarada' => 'Funciones declaradas', 'observado' => 'Observadas', 'en_revision' => 'En revisión', 'validado_uatp' => 'Validadas']])
    @foreach ($categorias as $categoriaKey => $categoriaLabel)
        <div class="card shadow-sm mb-3" data-catalog-group>
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
                            <tr data-catalog-row data-catalog-search="{{ $item['nombre_funcion'] }} {{ $categoriaLabel }} {{ $item['detalle'] }}" data-catalog-state="automatica">
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
                            <tr data-catalog-row data-catalog-search="{{ $funcion->nombre_funcion }} {{ $categoriaLabel }} {{ $funcion->descripcion_funcion }} {{ $funcion->fundamento }}" data-catalog-state="declarada {{ $funcion->estado }}" data-catalog-error="{{ $errors->any() && in_array(old('formulario'), ['validar:'.$funcion->id, 'observar:'.$funcion->id], true) ? '1' : '0' }}">
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
                                <td class="dotacion-function-actions">
                                    @if ($canValidate)
                                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.validar', [$establecimiento, $funcion, ...$accionesContexto]) }}" data-dotacion-save>
                                            @csrf
                                            <input type="hidden" name="formulario" value="validar:{{ $funcion->id }}">
                                            <label class="small fw-semibold" for="funcion-aprobadas-{{ $funcion->id }}">Horas aprobadas · {{ $funcion->nombre_funcion }}</label>
                                            <div class="d-flex align-items-start gap-2"><input id="funcion-aprobadas-{{ $funcion->id }}" type="number" name="horas_aprobadas" class="form-control form-control-sm" min="0" max="200" value="{{ old('formulario') === 'validar:'.$funcion->id ? old('horas_aprobadas') : ($funcion->horas_aprobadas ?? $funcion->horas_declaradas) }}"><button type="submit" class="btn btn-sm btn-primary rounded-pill">Validar</button></div>
                                            @if (old('formulario') === 'validar:'.$funcion->id) @error('horas_aprobadas')<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endif
                                        </form>
                                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.observar', [$establecimiento, $funcion, ...$accionesContexto]) }}" data-dotacion-save>
                                            @csrf
                                            <input type="hidden" name="formulario" value="observar:{{ $funcion->id }}">
                                            <label class="small fw-semibold" for="funcion-observar-{{ $funcion->id }}">Motivo de observación · {{ $funcion->nombre_funcion }}</label>
                                            <input id="funcion-observar-{{ $funcion->id }}" type="text" name="observacion" class="form-control form-control-sm mb-2" value="{{ old('formulario') === 'observar:'.$funcion->id ? old('observacion') : '' }}" required>
                                            @if (old('formulario') === 'observar:'.$funcion->id) @error('observacion')<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endif
                                            <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill">Observar</button>
                                        </form>
                                    @endif
                                    @if ($canEdit)
                                        <form method="POST" action="{{ route('admin.dotacion-funciones.manual.destroy', [$establecimiento, $funcion, ...$accionesContexto]) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta función declarada?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" aria-label="Eliminar función {{ $funcion->nombre_funcion }}"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
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
    </div>

    @if (($proceso2027['aplica'] ?? false) && !($proceso2027['funciones_no_normativas_habilitadas'] ?? false))
        <div class="alert alert-info" role="status"><i class="bi bi-lock" aria-hidden="true"></i> Para 2027 la creación de funciones declaradas/no normativas se habilitará cuando estén cubiertas las necesidades obligatorias.
            <a class="d-block fw-semibold mt-2" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio]).'#dotacion-etapas-titulo' }}">Revisar etapas pendientes y máximos por bloque</a>
        </div>
    @endif

    @if ($canEdit && (!($proceso2027['aplica'] ?? false) || ($proceso2027['funciones_no_normativas_habilitadas'] ?? false)))
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Agregar coordinación técnico-pedagógica</div>
                    <div class="card-body">
                        <form data-dotacion-save method="POST" action="{{ route('admin.dotacion-funciones.manual.store', [$establecimiento, ...$accionesContexto]) }}">
                            @csrf
                            <input type="hidden" name="formulario" value="coordinacion">
                            <input type="hidden" name="anio" value="{{ $anio }}">
                            <input type="hidden" name="tipo" value="coordinacion">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="funciones-form-5-tipo_coordinacion" class="form-label">Tipo</label>
                                    <select id="funciones-form-5-tipo_coordinacion" class="form-select" name="tipo_coordinacion">
                                        @foreach (['Ciclo', 'Técnico Profesional', 'Especialidad', 'Evaluación', 'Currículum', 'Apoyo UTP', 'Apoyo Directivo', 'Otro'] as $tipoCoordinacion)
                                            <option value="{{ $tipoCoordinacion }}" @selected(old('formulario') === 'coordinacion' && old('tipo_coordinacion') === $tipoCoordinacion)>{{ $tipoCoordinacion }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="funciones-form-5-nombre_funcion" class="form-label">Nombre coordinación</label>
                                    <input id="funciones-form-5-nombre_funcion" class="form-control" name="nombre_funcion" value="{{ old('formulario') === 'coordinacion' ? old('nombre_funcion') : '' }}" required placeholder="Ej: Coordinador Primer Ciclo">
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'nombre_funcion', 'formularioErrores' => 'coordinacion', 'controlId' => 'funciones-form-5-nombre_funcion'])
                                </div>
                                <div class="col-md-4">
                                    <label for="funciones-form-5-horas_declaradas" class="form-label">Horas declaradas</label>
                                    <input id="funciones-form-5-horas_declaradas" type="number" class="form-control" name="horas_declaradas" min="0" max="200" value="{{ old('formulario') === 'coordinacion' ? old('horas_declaradas') : (int) (($contexto['matricula_total'] ?? 0) > 300 ? 5 : 3) }}" required>
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'horas_declaradas', 'formularioErrores' => 'coordinacion', 'controlId' => 'funciones-form-5-horas_declaradas'])
                                    <div class="form-text">Sugerencia: {{ (int) (($contexto['matricula_total'] ?? 0) > 300 ? 5 : 3) }} hrs.</div>
                                </div>
                                <div class="col-md-8">
                                    <label for="funciones-form-5-fundamento" class="form-label">Fundamento</label>
                                    <input id="funciones-form-5-fundamento" class="form-control" name="fundamento" value="{{ old('formulario') === 'coordinacion' ? old('fundamento') : '' }}" placeholder="Fundamento o foco de la coordinación">
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'fundamento', 'formularioErrores' => 'coordinacion', 'controlId' => 'funciones-form-5-fundamento'])
                                </div>
                                <div class="col-12">
                                    <label for="funciones-form-5-descripcion_funcion" class="form-label">Descripción</label>
                                    <textarea id="funciones-form-5-descripcion_funcion" class="form-control" name="descripcion_funcion" rows="2">{{ old('formulario') === 'coordinacion' ? old('descripcion_funcion') : '' }}</textarea>
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'descripcion_funcion', 'formularioErrores' => 'coordinacion', 'controlId' => 'funciones-form-5-descripcion_funcion'])
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary rounded-pill" type="submit"><i class="bi bi-plus-circle"></i> Agregar coordinación</button>
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
                        <form data-dotacion-save method="POST" action="{{ route('admin.dotacion-funciones.manual.store', [$establecimiento, ...$accionesContexto]) }}">
                            @csrf
                            <input type="hidden" name="formulario" value="otra-funcion">
                            <input type="hidden" name="anio" value="{{ $anio }}">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label for="funciones-form-6-tipo" class="form-label">Tipo</label>
                                    <select id="funciones-form-6-tipo" class="form-select" name="tipo" required>
                                        <option value="otra" @selected(old('formulario') === 'otra-funcion' && old('tipo') === 'otra')>Otra función docente</option>
                                        <option value="orientador" @selected(old('formulario') === 'otra-funcion' && old('tipo') === 'orientador')>Orientador(a)</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label for="funciones-form-6-nombre_funcion" class="form-label">Nombre función</label>
                                    <input id="funciones-form-6-nombre_funcion" class="form-control" name="nombre_funcion" value="{{ old('formulario') === 'otra-funcion' ? old('nombre_funcion') : '' }}" required placeholder="Ej: Evaluador/a, Curriculista, Subdirector/a">
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'nombre_funcion', 'formularioErrores' => 'otra-funcion', 'controlId' => 'funciones-form-6-nombre_funcion'])
                                </div>
                                <div class="col-md-4">
                                    <label for="funciones-form-6-horas_declaradas" class="form-label">Horas declaradas</label>
                                    <input id="funciones-form-6-horas_declaradas" type="number" class="form-control" name="horas_declaradas" min="0" max="200" value="{{ old('formulario') === 'otra-funcion' ? old('horas_declaradas') : '' }}" required>
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'horas_declaradas', 'formularioErrores' => 'otra-funcion', 'controlId' => 'funciones-form-6-horas_declaradas'])
                                </div>
                                <div class="col-md-8">
                                    <label for="funciones-form-6-fundamento" class="form-label">Fundamento</label>
                                    <input id="funciones-form-6-fundamento" class="form-control" name="fundamento" value="{{ old('formulario') === 'otra-funcion' ? old('fundamento') : '' }}" placeholder="Justificación de la función">
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'fundamento', 'formularioErrores' => 'otra-funcion', 'controlId' => 'funciones-form-6-fundamento'])
                                </div>
                                <div class="col-12">
                                    <label for="funciones-form-6-descripcion_funcion" class="form-label">Descripción</label>
                                    <textarea id="funciones-form-6-descripcion_funcion" class="form-control" name="descripcion_funcion" rows="2">{{ old('formulario') === 'otra-funcion' ? old('descripcion_funcion') : '' }}</textarea>
                                    @include('admin.dotacion-establecimiento.partials._campo_error', ['campo' => 'descripcion_funcion', 'formularioErrores' => 'otra-funcion', 'controlId' => 'funciones-form-6-descripcion_funcion'])
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary rounded-pill" type="submit"><i class="bi bi-plus-circle"></i> Agregar función</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @include('admin.dotacion-establecimiento.partials._restore_context')
</div>
@endsection
