@php
    $proceso = $proceso2027 ?? ['aplica' => false];
    $configProceso = $proceso['configuracion'] ?? null;
    $fmtProceso = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $canConfigureConvivencia = \App\Support\DotacionConvivenciaAnual::puedeConfigurar(auth()->user()?->activeRoleName());
    $convivenciaCentralizada = \App\Support\DotacionConvivenciaAnual::horas($establecimiento->id, $anio) !== null;
@endphp

@if ($proceso['aplica'] ?? false)
    <div class="card dotacion-section mb-4 border-primary">
        <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="dotacion-eyebrow">Proceso guiado 2027</div>
                <h2 class="h5 fw-bold mb-1">Avance de dotación por establecimiento</h2>
                <div class="small text-muted">Complete las etapas en orden. Las funciones no normativas se habilitan al finalizar la cobertura obligatoria.</div>
            </div>
            <span class="badge rounded-pill text-bg-primary">Año 2027</span>
        </div>
        <div class="card-body">
            @include('admin.dotacion-establecimiento.partials._docentes_subsector')

            <div class="row g-3">
                <div class="col-lg-5">
                    <form id="dotacion-decision-combinacion" method="POST" action="{{ route('admin.dotacion-establecimiento.proceso-2027.update', $establecimiento) }}" class="border rounded-4 p-3 h-100" data-dotacion-save>
                        @csrf
                        <input type="hidden" name="anio" value="2027">
                        <label for="proceso-decision-combinacion" class="form-label fw-semibold">Decisión sobre combinación de cursos</label>
                        <select id="proceso-decision-combinacion" name="decision_combinacion" class="form-select mb-2 @error('decision_combinacion') is-invalid @enderror" required>
                            <option value="">Seleccione una decisión...</option>
                            @foreach (\App\Models\DotacionProceso2027Configuracion::COMBINACIONES as $key => $label)
                                <option value="{{ $key }}" @selected(old('decision_combinacion', $configProceso?->decision_combinacion) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('decision_combinacion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <label for="proceso-observacion-combinacion" class="form-label small">Fundamento o antecedente (opcional)</label>
                        <textarea id="proceso-observacion-combinacion" name="observacion_combinacion" class="form-control mb-2 @error('observacion_combinacion') is-invalid @enderror" rows="2" maxlength="2000">{{ old('observacion_combinacion', $configProceso?->observacion_combinacion) }}</textarea>
                        @error('observacion_combinacion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <a class="d-block small mb-3" data-dotacion-contexto-salida href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'cursos-combinados']) }}">Revisar cursos combinados</a>
                        <button class="btn btn-outline-primary btn-sm rounded-pill" type="submit"><i class="bi bi-check2-circle"></i> Guardar decisión</button>
                    </form>
                </div>
                <div class="col-lg-7">
                    <div id="dotacion-maximos">
                    @if ($canManageProceso2027Maximos ?? false)
                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.proceso-2027.update', $establecimiento) }}" class="border rounded-4 p-3" data-dotacion-save>
                            @csrf
                            <input type="hidden" name="anio" value="2027">
                            <div class="fw-semibold mb-2">Máximos autorizados para asignar por componente</div>
                            <a class="btn btn-outline-primary btn-sm rounded-pill mb-3" href="{{ route('admin.dotacion-funciones.maximos.index', ['anio' => $anio]) }}"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Carga masiva de máximos</a>
                            <div class="row g-2">
                                @foreach (($proceso['bloques'] ?? []) as $key => $bloque)
                                    <div class="col-md-4">
                                        <label for="proceso-maximo-{{ $key }}" class="form-label small fw-semibold">{{ $bloque['label'] }}</label>
                                        <input id="proceso-maximo-{{ $key }}" type="number" name="max_horas_{{ $key }}" min="0" max="9999" step="0.01" class="form-control @error('max_horas_'.$key) is-invalid @enderror" value="{{ old('max_horas_'.$key, $bloque['maximo']) }}" required>
                                        @error('max_horas_'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">Horas necesarias: {{ $fmtProceso($bloque['requeridas']) }} h</div>
                                        @if ($bloque['maximo_insuficiente'] ?? false)<div class="small text-danger mt-1">El máximo autorizado es inferior a la necesidad en {{ $fmtProceso($bloque['requeridas'] - $bloque['maximo']) }} h. Requiere revisión por un rol autorizado.</div>@endif
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn btn-primary btn-sm rounded-pill mt-3" type="submit"><i class="bi bi-save"></i> Guardar máximos</button>
                        </form>
                    @else
                        <div class="border rounded-4 p-3 h-100 bg-light">
                            <div class="fw-semibold">Máximos autorizados por componente</div>
                            <div class="small text-muted">La configuración de máximos corresponde a Administración, Coordinación UATP, Coordinación GDP o Supervisión de Planificación. Consulte los valores en la tabla de cobertura.</div>
                        </div>
                    @endif
                    </div>
                </div>
            </div>

            @php $funcionesNormativas = collect($proceso['funciones_normativas'] ?? []); @endphp
            @if ($funcionesNormativas->isNotEmpty())
                <div id="dotacion-definicion-normativas" class="border rounded-4 p-3 mt-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                        <div>
                            <div class="fw-semibold">Definición de funciones normativas</div>
                            <div class="small text-muted">Seleccione las funciones que utilizará y sus horas de contrato. Por defecto se aplica el cálculo del sistema; puede reducirlo sin superar las horas calculadas. Las horas definidas se suman como necesidad obligatoria.</div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge rounded-pill {{ ($proceso['pasos']['normativas']['completo'] ?? false) ? 'text-bg-success' : 'text-bg-warning' }}">Bolsa potencial: {{ $fmtProceso(collect($proceso['bloques'] ?? [])->sum('horas_normativas_potenciales')) }} h</span>
                            @if ($canConfigureConvivencia)
                                <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('admin.dotacion-funciones.convivencia.index', ['anio' => $anio]) }}"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Carga de horas de Convivencia</a>
                            @endif
                        </div>
                    </div>
                    @if ($canManageProceso2027Normativas ?? false)
                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.proceso-2027.update', $establecimiento) }}" data-dotacion-save>
                            @csrf
                            <input type="hidden" name="anio" value="2027">
                            <input type="hidden" name="funciones_normativas_configuradas" value="1">
                            <div class="row g-2">
                                @foreach ($funcionesNormativas as $funcion)
                                    <div class="col-lg-6">
                                        @php
                                            $indiceFuncion = $loop->index;
                                            $campoHoras = 'funciones_normativas.'.$indiceFuncion.'.horas';
                                            $soloLecturaConvivencia = ($funcion['codigo'] ?? '') === \App\Support\DotacionConvivenciaAnual::CODIGO && ($convivenciaCentralizada || ! $canConfigureConvivencia);
                                        @endphp
                                        <div class="border rounded-4 bg-white p-3 h-100">
                                            <input type="hidden" name="funciones_normativas[{{ $loop->index }}][key]" value="{{ $funcion['key'] }}">
                                            <input type="hidden" name="funciones_normativas[{{ $loop->index }}][usar]" value="0">
                                            <div class="form-check mb-2">
                                                <input id="normativa-uso-{{ $indiceFuncion }}" class="form-check-input" type="checkbox" name="funciones_normativas[{{ $indiceFuncion }}][usar]" value="1" @checked(old('funciones_normativas.'.$indiceFuncion.'.usar', $funcion['se_utilizara']))>
                                                <label class="form-check-label fw-semibold" for="normativa-uso-{{ $indiceFuncion }}">{{ $funcion['titulo'] }}</label>
                                            </div>
                                            <div class="row g-2 align-items-start">
                                                <div class="col-sm-5">
                                                    <label class="form-label small fw-semibold mb-1" for="normativa-horas-{{ $indiceFuncion }}">Horas de contrato <span class="text-danger">*</span></label>
                                                    <input id="normativa-horas-{{ $indiceFuncion }}" type="number" name="funciones_normativas[{{ $indiceFuncion }}][horas]" min="0" max="{{ $funcion['horas_potenciales'] }}" step="0.01" value="{{ $soloLecturaConvivencia ? $funcion['horas'] : old($campoHoras, $funcion['horas']) }}" class="form-control rounded-3 {{ $soloLecturaConvivencia ? 'bg-light' : '' }} @error($campoHoras) is-invalid @enderror" aria-describedby="normativa-ayuda-{{ $indiceFuncion }}" @readonly($soloLecturaConvivencia) required>
                                                    @if ($soloLecturaConvivencia)<div class="form-text">Las horas de este cargo se administran mediante la carga masiva anual.</div>@endif
                                                    @error($campoHoras)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                <div id="normativa-ayuda-{{ $indiceFuncion }}" class="col-sm-7 small text-muted pt-sm-4">
                                                    <div>Calculadas: <strong>{{ $fmtProceso($funcion['horas_potenciales']) }} h</strong> (máximo).</div>
                                                    <div>Asignadas: {{ $fmtProceso($funcion['horas_asignadas']) }} h{{ $funcion['asignacion_existente'] ? ' · La función se mantiene activa.' : '' }}</div>
                                                </div>
                                            </div>
                                            @if ($funcion['exceso_asignado'] > 0.01)
                                                <div class="alert alert-warning small rounded-3 mt-3 mb-0" role="status"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Las asignaciones exceden la definición en {{ $fmtProceso($funcion['exceso_asignado']) }} h. Ajuste las asignaciones; no se modifican automáticamente.</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn btn-primary rounded-pill mt-3" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> Guardar definición</button>
                        </form>
                    @else
                        <div class="small text-muted">La definición puede realizarla el funcionario directivo del establecimiento, Administración o Coordinación UATP.</div>
                        <div class="row g-2 mt-2">
                            @foreach ($funcionesNormativas as $funcion)
                                <div class="col-lg-6"><div class="border rounded-4 bg-white p-3 h-100"><div class="fw-semibold">{{ $funcion['titulo'] }}</div><div class="small text-muted">{{ $funcion['se_utilizara'] ? 'En uso' : 'Sin utilizar' }} · Definidas: {{ $fmtProceso($funcion['horas']) }} h · Calculadas: {{ $fmtProceso($funcion['horas_potenciales']) }} h · Asignadas: {{ $fmtProceso($funcion['horas_asignadas']) }} h</div>@if ($funcion['exceso_asignado'] > 0.01)<div class="small text-warning-emphasis mt-2">Exceso sobre la definición: {{ $fmtProceso($funcion['exceso_asignado']) }} h.</div>@endif</div></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div id="dotacion-definicion-normativas" class="alert alert-info mt-3 mb-0" role="status">No hay funciones normativas calculadas para este establecimiento y año.</div>
            @endif

            <div class="table-responsive mt-4">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Componente</th><th class="text-end">Normativas potenciales</th><th class="text-end">Máximo</th><th class="text-end">Titulares</th><th class="text-end">Contrata</th><th class="text-end">Sin padrón vigente</th><th class="text-end">Contrato asignado y reservado</th><th class="text-end">Cobertura obligatoria</th><th class="text-end">Pendiente obligatorio</th><th class="text-end">Saldo no normativas</th></tr></thead>
                    <tbody>
                        @foreach (($proceso['bloques'] ?? []) as $bloque)
                            <tr>
                                <td class="fw-semibold">{{ $bloque['label'] }}</td>
                                <td class="text-end">{{ $bloque['horas_normativas_potenciales'] > 0 ? $fmtProceso($bloque['horas_normativas_potenciales']) : '—' }}</td>
                                <td class="text-end">{{ $bloque['maximo'] === null ? 'Pendiente' : $fmtProceso($bloque['maximo']) }}</td>
                                <td class="text-end text-success">{{ $fmtProceso($bloque['titulares_con_redondeo'] ?? $bloque['titulares_asignadas']) }}</td>
                                <td class="text-end text-primary">{{ $fmtProceso($bloque['contrata_con_redondeo'] ?? $bloque['contrata_asignadas']) }}</td>
                                <td class="text-end {{ $bloque['sin_padron_asignadas'] > 0.01 ? 'text-warning fw-semibold' : '' }}">{{ $fmtProceso($bloque['sin_padron_asignadas']) }}</td>
                                <td class="text-end fw-semibold">{{ $fmtProceso($bloque['asignadas_con_redondeo'] ?? $bloque['asignadas']) }}
                                    @if (($bloque['redondeo_parvularia'] ?? 0) > 0.01)
                                        <div class="small text-muted fw-normal">Registradas: {{ $fmtProceso($bloque['asignadas']) }} h · redondeo individual Parvularia: +{{ $fmtProceso($bloque['redondeo_parvularia']) }} h</div>
                                    @endif
                                    @if (($bloque['asignadas_libre_disposicion_nt_otro_docente'] ?? 0) > 0.01)
                                        <div class="small text-muted fw-normal">Incluye {{ $fmtProceso($bloque['asignadas_libre_disposicion_nt_otro_docente']) }} h de libre disposición NT1/NT2 impartidas por otros docentes</div>
                                    @endif
                                    @if (($bloque['asignadas_acompanamiento'] ?? 0) > 0.01)
                                        <div class="small text-muted fw-normal">Incluye {{ $fmtProceso($bloque['asignadas_acompanamiento']) }} h de acompañamiento simultáneo</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ $fmtProceso($bloque['asignadas_obligatorias']) }}
                                    @if (($bloque['horas_aula_libre_disposicion_nt_otro_docente'] ?? 0) > 0.01)
                                        <div class="small text-muted">Libre disposición NT1/NT2: {{ $fmtProceso($bloque['horas_aula_libre_disposicion_nt_otro_docente']) }} h aula → {{ $fmtProceso($bloque['contrato_necesario_libre_disposicion_nt_otro_docente']) }} h contrato necesarios (bloque consolidado)</div>
                                    @endif
                                    @if (abs($bloque['ajuste_cobertura_plan'] ?? 0) > 0.01)
                                        <div class="small text-muted">Plan consolidado: {{ ($bloque['ajuste_cobertura_plan'] ?? 0) > 0 ? '+' : '' }}{{ $fmtProceso($bloque['ajuste_cobertura_plan']) }} h</div>
                                    @endif
                                    @if (($bloque['asignadas_asistentes_obligatorias'] ?? 0) > 0.01)
                                        <div class="small text-muted">Cobertura AAEE: +{{ $fmtProceso($bloque['asignadas_asistentes_obligatorias']) }} h</div>
                                    @endif
                                </td>
                                <td class="text-end {{ $bloque['pendientes'] > 0.01 ? 'text-warning fw-semibold' : 'text-success' }}">{{ $fmtProceso($bloque['pendientes']) }}</td>
                                <td class="text-end {{ $bloque['maximo_insuficiente'] ? 'text-danger fw-semibold' : '' }}">{{ $bloque['saldo_maximo'] === null ? '—' : $fmtProceso($bloque['saldo_maximo']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="small text-muted mt-2">Titulares y contrata provienen de las jornadas del padrón vigente; «Sin padrón vigente» identifica asignaciones que requieren revisión de su origen. La cobertura del plan se confirma con las horas aula de cada asignatura y su contrato necesario se calcula consolidado por curso. La libre disposición NT1/NT2 impartida por otros docentes se cuenta en Plan general; el acompañamiento simultáneo de Parvularia cuenta en el contrato de la Educadora, sin ocupar un segundo cupo del máximo del bloque. El redondeo individual de Parvularia sólo ajusta las cifras mostradas; no modifica asignaciones guardadas ni máximos. La cobertura AAEE de otras necesidades obligatorias se muestra por separado del contrato docente.</div>
            <div class="alert alert-info small rounded-4 mt-3 mb-0" role="note"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>El contrato asignado del plan se calcula sumando primero las horas aula de cada docente y convirtiendo ese total según 65/35 o 60/40. Incluye libre disposición. Las funciones, el trabajo colaborativo PIE y las reservas ya son horas de contrato y se suman directamente. La cobertura por AAEE se informa aparte y no consume contrato docente.</div>
            @if (!($proceso['funciones_no_normativas_habilitadas'] ?? false))
                @php
                    $etapasPendientes = collect($proceso['pasos'] ?? [])->filter(fn ($paso) => ! $paso['completo'])->pluck('label');
                    $bloquesPendientes = collect($proceso['bloques'] ?? [])->filter(fn ($bloque) => $bloque['pendientes'] > 0.01);
                @endphp
                <div class="alert alert-warning small mt-3 mb-0" role="status">
                    <i class="bi bi-lock me-1" aria-hidden="true"></i><strong>Las funciones no normativas aún están bloqueadas.</strong>
                    @if ($etapasPendientes->isNotEmpty())
                        <div class="mt-1">Etapas pendientes: {{ $etapasPendientes->implode(', ') }}.</div>
                    @endif
                    @if ($bloquesPendientes->isNotEmpty())
                        <div class="mt-1">Horas obligatorias por asignar: {{ $bloquesPendientes->map(fn ($bloque) => $bloque['label'].' ('.$fmtProceso($bloque['pendientes']).' h)')->implode('; ') }}.</div>
                        <a class="d-inline-block mt-2 fw-semibold" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'asignacion', 'asig_pendientes' => 1]).'#dotacion-asignacion' }}">Ver necesidades pendientes</a>
                    @endif
                    @foreach (($proceso['bloques'] ?? []) as $bloque)
                        @if ($bloque['maximo'] === null)
                            <div class="mt-2">{{ $bloque['label'] }}: falta definir el máximo autorizado.</div>
                        @elseif ($bloque['maximo_insuficiente'] ?? false)
                            <div class="mt-2">{{ $bloque['label'] }}: el máximo autorizado de {{ $fmtProceso($bloque['maximo']) }} h es inferior a las {{ $fmtProceso($bloque['requeridas']) }} h necesarias.</div>
                        @endif
                    @endforeach
                    <a class="d-inline-block mt-2 fw-semibold" href="#dotacion-etapas-titulo">Revisar etapas y siguiente acción</a>
                    <div class="mt-1">Revise las etapas indicadas y el saldo disponible del bloque contractual de cada docente.</div>
                </div>
            @else
                <div class="alert alert-success small mt-3 mb-0"><i class="bi bi-unlock"></i> Funciones no normativas habilitadas: {{ $fmtProceso($proceso['capacidad_no_normativas'] ?? 0) }} horas disponibles.</div>
            @endif
        </div>
    </div>
    @if (($tab ?? '') !== 'sobredotacion')
        @include('admin.dotacion-establecimiento.partials._conciliacion_general')
    @endif
@endif
