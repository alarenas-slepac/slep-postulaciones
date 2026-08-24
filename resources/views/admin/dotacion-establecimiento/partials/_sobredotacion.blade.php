@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $tipoDetalle = in_array($sobredotacionTipo ?? 'aula', ['aula', 'pie'], true) ? ($sobredotacionTipo ?? 'aula') : 'aula';
    $detalle = $sobredotacion[$tipoDetalle] ?? [];
    $sobredotacionItems = collect($detalle['items'] ?? []);
    $ajusteItems = collect($detalle['ajustes'] ?? []);
    $sobredotacionResumen = $detalle['resumen'] ?? [];
    $formula = $detalle['formula'] ?? [];
    $esAula = $tipoDetalle === 'aula';
    $brechaEstructural = (float) ($sobredotacionResumen['brecha_estructural'] ?? 0);
    $resultadoEstructural = $brechaEstructural < -0.01
        ? ['label' => 'Sobredotación estructural', 'value' => $fmt(abs($brechaEstructural)), 'class' => 'alert-danger']
        : ($brechaEstructural > 0.01
            ? ['label' => 'Horas estructuralmente necesarias', 'value' => '+'.$fmt($brechaEstructural), 'class' => 'alert-success']
            : ['label' => 'Dotación estructural cuadrada', 'value' => '0', 'class' => 'alert-primary']);
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start gap-3">
            <span class="dotacion-icon" style="width:40px;height:40px;background:#dc3545;"><i class="bi bi-person-exclamation"></i></span>
            <div>
                <div class="dotacion-eyebrow">Análisis contractual por docente</div>
                <h2 class="h5 fw-bold mb-1">Detalle sobredotación</h2>
                <div class="text-muted small">Distingue las horas contractuales realmente sin asignación de las horas declaradas que sí están asignadas, pero pueden revisarse o redistribuirse.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <ul class="nav nav-pills gap-2 mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $esAula ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'sobredotacion', 'sobredotacion_tipo' => 'aula']) }}">
                    <i class="bi bi-easel2"></i> Horas contrato Aula
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ !$esAula ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'sobredotacion', 'sobredotacion_tipo' => 'pie']) }}">
                    <i class="bi bi-universal-access"></i> Horas contrato docente PIE
                </a>
            </li>
        </ul>

        @if ($esAula)
            <div class="alert {{ $resultadoEstructural['class'] }} border-0 rounded-4">
                <div class="fw-bold mb-1">Brecha estructural de Dotación General</div>
                <div>(Contrato plan + trabajo colaborativo PIE + bloque normativo + bloque declarado) − contrato Aula.</div>
                <div class="fw-semibold mt-1">({{ $fmt($formula['contrato_plan_pie'] ?? 0) }} + {{ $fmt($formula['bloque_normativo'] ?? 0) }} + {{ $fmt($formula['bloque_declarado'] ?? 0) }}) − {{ $fmt($formula['contrato_aula'] ?? 0) }}</div>
                <div class="fs-4 fw-bold mt-2">{{ $resultadoEstructural['label'] }}: {{ $resultadoEstructural['value'] }}</div>
                <div class="small mt-1">Este indicador institucional se presenta como referencia y no reemplaza el cálculo factual de horas sin asignación por docente.</div>
            </div>

            <div class="row g-3">
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes analizados</div><div class="h4 fw-bold mb-0">{{ number_format((int) ($sobredotacionResumen['docentes_analizados'] ?? 0), 0, ',', '.') }}</div><div class="small text-muted">Con contrato Aula o asignación</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Contrato Aula</div><div class="h4 fw-bold mb-0">{{ $fmt($sobredotacionResumen['horas_dotacion_total'] ?? 0) }}</div><div class="small text-muted">Contrato individualizado</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-success-subtle h-100"><div class="small text-muted">Asignaciones protegidas</div><div class="h4 fw-bold text-success mb-0">{{ $fmt($sobredotacionResumen['horas_asignadas_protegidas'] ?? 0) }}</div><div class="small text-muted">Plan, colaboración y normativa</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-warning-subtle h-100"><div class="small text-muted">Declaradas ajustables</div><div class="h4 fw-bold text-warning-emphasis mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_ajustables'] ?? 0) }}</div><div class="small text-muted">Asignadas, no normativas</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Sobredotación sin asignación</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</div><div class="small text-muted">Horas contractuales vacantes</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-primary-subtle h-100"><div class="small text-muted">Sin asignación Planta</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</div><div class="small text-muted">Horas titulares</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-info-subtle h-100"><div class="small text-muted">Sin asignación Contrata</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</div><div class="small text-muted">Horas a contrata</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-secondary-subtle h-100"><div class="small text-muted">Potencial total de ajuste</div><div class="h4 fw-bold text-secondary mb-0">{{ $fmt($sobredotacionResumen['horas_potencial_ajuste'] ?? 0) }}</div><div class="small text-muted">Sin asignación + declaradas</div></div></div>
            </div>
        @else
            @php
                $resultadoPieSobredotacion = (float) ($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) > 0.01;
                $resultadoPieNecesidad = (float) ($sobredotacionResumen['horas_necesarias_pendientes'] ?? 0) > 0.01;
            @endphp
            <div class="alert {{ $resultadoPieSobredotacion ? 'alert-danger' : ($resultadoPieNecesidad ? 'alert-success' : 'alert-primary') }} border-0 rounded-4">
                <div class="fw-bold mb-1">Dotación PIE</div>
                <div>Horas de contrato PIE necesarias − horas contrato docente PIE.</div>
                <div class="fw-semibold mt-1">{{ $fmt($formula['contrato_pie_necesario'] ?? 0) }} − {{ $fmt($formula['contrato_docente_pie'] ?? 0) }}</div>
                <div class="fs-4 fw-bold mt-2">
                    @if ($resultadoPieSobredotacion)
                        Horas de sobredotación: {{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}
                    @elseif ($resultadoPieNecesidad)
                        Horas necesarias: +{{ $fmt($sobredotacionResumen['horas_necesarias_pendientes'] ?? 0) }}
                    @else
                        Dotación cuadrada: 0
                    @endif
                </div>
            </div>
            <div class="row g-3">
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes analizados</div><div class="h4 fw-bold mb-0">{{ number_format((int) ($sobredotacionResumen['docentes_analizados'] ?? 0), 0, ',', '.') }}</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Contrato docente PIE</div><div class="h4 fw-bold mb-0">{{ $fmt($sobredotacionResumen['horas_dotacion_total'] ?? 0) }}</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas necesarias</div><div class="h4 fw-bold mb-0">{{ $fmt($sobredotacionResumen['horas_necesarias_total'] ?? 0) }}</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Sobredotación PIE</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-primary-subtle h-100"><div class="small text-muted">Sobredotación Planta</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-info-subtle h-100"><div class="small text-muted">Sobredotación Contrata</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</div></div></div>
            </div>
        @endif
    </div>
</div>

@if ($esAula)
    <div class="alert alert-info border-0 rounded-4 small">
        <i class="bi bi-info-circle"></i>
        <strong>Cálculo individual:</strong> las funciones directivas, técnico-pedagógicas y planes normativos se imputan directamente al docente que los tiene asignados, junto con contrato plan y trabajo colaborativo PIE. Las horas declaradas también reducen el saldo sin asignación, pero se informan separadamente como posibles ajustes. En contratos mixtos, las asignaciones cubren primero Planta y luego Contrata.
    </div>

    @if ((float) ($sobredotacionResumen['horas_sobreasignadas'] ?? 0) > 0.01)
        <div class="alert alert-warning border-0 rounded-4 small">
            <i class="bi bi-exclamation-triangle"></i> Existen {{ $fmt($sobredotacionResumen['horas_sobreasignadas']) }} hora(s) asignadas por sobre el contrato Aula de sus docentes. Se informan en la tabla para revisión y no generan horas contractuales adicionales.
        </div>
    @endif

    <div class="card dotacion-section mb-4">
        <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="dotacion-eyebrow">Horas contractuales vacantes</div>
                <h2 class="h5 fw-bold mb-1">Sobredotación sin asignación</h2>
                <div class="text-muted small">Sólo aparecen docentes cuyo contrato Aula supera la suma de sus asignaciones protegidas y declaradas.</div>
            </div>
            <span class="badge rounded-pill text-bg-danger">{{ $sobredotacionItems->count() }} docente(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>RUT</th><th>Docente</th><th>Función</th><th class="text-end">Contrato Aula</th><th class="text-end">Protegidas</th><th class="text-end">Declaradas</th><th class="text-end">Total asignado</th><th class="text-end">Sin asignación</th><th class="text-end">Planta</th><th class="text-end">Contrata</th><th class="text-end">Sobreasignadas</th></tr></thead>
                <tbody>
                    @forelse ($sobredotacionItems as $docente)
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ $docente['rut'] }}</td>
                            <td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['tipo_contrato'] }}</div></td>
                            <td>{{ $docente['funcion'] }}</td>
                            <td class="text-end fw-semibold">{{ $fmt($docente['horas_contrato_categoria']) }}</td>
                            <td class="text-end text-success">{{ $fmt($docente['horas_asignadas_protegidas']) }}</td>
                            <td class="text-end text-warning-emphasis">{{ $fmt($docente['horas_declaradas_ajustables']) }}</td>
                            <td class="text-end fw-semibold">{{ $fmt($docente['horas_asignadas_total']) }}</td>
                            <td class="text-end text-danger fw-bold">{{ $fmt($docente['horas_sobredotacion_total']) }}</td>
                            <td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_sobredotacion_planta']) }}</td>
                            <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_sobredotacion_contrata']) }}</td>
                            <td class="text-end {{ $docente['horas_sobreasignadas'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $fmt($docente['horas_sobreasignadas']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>No se identificaron horas contractuales sin asignación.</td></tr>
                    @endforelse
                </tbody>
                @if ($sobredotacionItems->isNotEmpty())
                    <tfoot class="table-light fw-bold"><tr><td colspan="7">Total sobredotación sin asignación</td><td class="text-end text-danger">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</td><td class="text-end text-primary">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</td><td class="text-end text-info">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="card dotacion-section">
        <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="dotacion-eyebrow">Funciones no normativas asignadas</div>
                <h2 class="h5 fw-bold mb-1">Horas de posible ajuste</h2>
                <div class="text-muted small">Son horas del bloque declarado que actualmente tienen asignación. No forman parte de la sobredotación sin asignación, pero pueden revisarse. Abre cada docente para conocer las funciones que componen sus horas.</div>
            </div>
            <span class="badge rounded-pill text-bg-warning">{{ $ajusteItems->count() }} docente(s)</span>
        </div>
        <div class="card-body border-bottom">
            <div class="row g-3">
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-warning-subtle h-100"><div class="small text-muted">Total posible ajuste</div><div class="h4 fw-bold text-warning-emphasis mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_ajustables'] ?? 0) }}</div><div class="small text-muted">Funciones declaradas asignadas</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-primary-subtle h-100"><div class="small text-muted">Horas titulares</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_titulares'] ?? 0) }}</div><div class="small text-muted">Imputadas a contrato Planta</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-info-subtle h-100"><div class="small text-muted">Horas Contrata</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_contrata'] ?? 0) }}</div><div class="small text-muted">Imputadas a contrato Contrata</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Sin cobertura contractual</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_sin_cobertura'] ?? 0) }}</div><div class="small text-muted">Declaradas sobre el contrato disponible</div></div></div>
            </div>
            <div class="small text-muted mt-3"><i class="bi bi-info-circle"></i> Para clasificar las horas declaradas se imputan primero las asignaciones protegidas y se conserva primero el contrato Titular/Planta; luego se utiliza Contrata.</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th><span class="visually-hidden">Detalle</span></th><th>Docente</th><th>Función</th><th class="text-end">Contrato Aula</th><th class="text-end">Protegidas</th><th class="text-end">Ajustables</th><th class="text-end">Titulares</th><th class="text-end">Contrata</th><th class="text-end">Sin cobertura</th><th class="text-end">Sin asignación</th><th class="text-end">Sobreasignadas</th></tr></thead>
                <tbody>
                    @forelse ($ajusteItems as $docente)
                        @php($detalleId = 'detalle-ajuste-docente-'.$loop->index)
                        <tr>
                            <td><button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" data-bs-toggle="collapse" data-bs-target="#{{ $detalleId }}" aria-expanded="false" aria-controls="{{ $detalleId }}" title="Ver desglose de horas"><i class="bi bi-chevron-down"></i><span class="visually-hidden">Ver detalle de {{ $docente['nombre'] }}</span></button></td>
                            <td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['rut'] }} · {{ $docente['tipo_contrato'] }}</div></td>
                            <td>{{ $docente['funcion'] }}</td>
                            <td class="text-end fw-semibold">{{ $fmt($docente['horas_contrato_categoria']) }}</td>
                            <td class="text-end text-success">{{ $fmt($docente['horas_asignadas_protegidas']) }}</td>
                            <td class="text-end text-warning-emphasis fw-bold">{{ $fmt($docente['horas_declaradas_ajustables']) }}</td>
                            <td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_declaradas_titulares']) }}</td>
                            <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_declaradas_contrata']) }}</td>
                            <td class="text-end {{ $docente['horas_declaradas_sin_cobertura'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $fmt($docente['horas_declaradas_sin_cobertura']) }}</td>
                            <td class="text-end text-danger">{{ $fmt($docente['horas_sobredotacion_total']) }}</td>
                            <td class="text-end {{ $docente['horas_sobreasignadas'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $fmt($docente['horas_sobreasignadas']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="11" class="p-0 border-0">
                                <div class="collapse" id="{{ $detalleId }}">
                                    <div class="p-3 bg-light border-top border-bottom">
                                        <div class="fw-semibold mb-2"><i class="bi bi-list-ul"></i> Desglose de horas de posible ajuste</div>
                                        <div class="table-responsive rounded-3 border bg-white">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light"><tr><th>Tipo</th><th>Función o actividad</th><th>Subtipo</th><th>Subvención</th><th class="text-end">Horas</th></tr></thead>
                                                <tbody>
                                                    @forelse (($docente['horas_declaradas_detalle'] ?? []) as $detalle)
                                                        <tr><td><span class="badge text-bg-light border">{{ $detalle['tipo_label'] }}</span></td><td class="fw-semibold">{{ $detalle['nombre'] }}</td><td>{{ $detalle['subtipo_label'] ?: '—' }}</td><td>{{ $detalle['subvencion'] ?: '—' }}</td><td class="text-end fw-bold text-warning-emphasis">{{ $fmt($detalle['horas']) }}</td></tr>
                                                    @empty
                                                        <tr><td colspan="5" class="text-center text-muted py-3">No existe un desglose individual disponible para estas horas declaradas.</td></tr>
                                                    @endforelse
                                                </tbody>
                                                <tfoot class="table-light fw-bold"><tr><td colspan="4">Total docente</td><td class="text-end text-warning-emphasis">{{ $fmt($docente['horas_declaradas_ajustables']) }}</td></tr></tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-5">No existen horas declaradas asignadas susceptibles de ajuste.</td></tr>
                    @endforelse
                </tbody>
                @if ($ajusteItems->isNotEmpty())
                    <tfoot class="table-light fw-bold"><tr><td colspan="5">Total horas declaradas de posible ajuste</td><td class="text-end text-warning-emphasis">{{ $fmt($sobredotacionResumen['horas_declaradas_ajustables'] ?? 0) }}</td><td class="text-end text-primary">{{ $fmt($sobredotacionResumen['horas_declaradas_titulares'] ?? 0) }}</td><td class="text-end text-info">{{ $fmt($sobredotacionResumen['horas_declaradas_contrata'] ?? 0) }}</td><td class="text-end text-danger">{{ $fmt($sobredotacionResumen['horas_declaradas_sin_cobertura'] ?? 0) }}</td><td colspan="2"></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
@else
    <div class="alert alert-info border-0 rounded-4 small">
        <i class="bi bi-info-circle"></i> La necesidad PIE se distribuye conservando primero horas Planta y luego Contrata.
    </div>
    <div class="card dotacion-section">
        <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><div class="dotacion-eyebrow">Contrato docente PIE</div><h2 class="h5 fw-bold mb-1">Nómina de sobredotación PIE</h2></div>
            <span class="badge rounded-pill text-bg-danger">{{ $sobredotacionItems->count() }} registro(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>RUT</th><th>Docente</th><th>Función</th><th class="text-end">Contrato docente PIE</th><th class="text-end">Necesidad cubierta</th><th class="text-end">Sobredotación total</th><th class="text-end">Planta</th><th class="text-end">Contrata</th></tr></thead>
                <tbody>
                    @forelse ($sobredotacionItems as $docente)
                        <tr><td class="text-nowrap fw-semibold">{{ $docente['rut'] }}</td><td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['tipo_contrato'] }}</div></td><td>{{ $docente['funcion'] }}</td><td class="text-end fw-semibold">{{ $fmt($docente['horas_contrato_categoria']) }}</td><td class="text-end">{{ $fmt($docente['horas_necesidad_cubierta']) }}</td><td class="text-end text-danger fw-bold">{{ $fmt($docente['horas_sobredotacion_total']) }}</td><td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_sobredotacion_planta']) }}</td><td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_sobredotacion_contrata']) }}</td></tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>No se identificaron docentes con sobredotación PIE.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
