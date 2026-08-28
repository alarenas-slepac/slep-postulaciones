@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $toneClass = ['primary' => 'primary', 'success' => 'success', 'info' => 'info', 'warning' => 'warning', 'secondary' => 'secondary'];
    $bloquesResumenDotacion = $bloquesContratoDotacion ?? $bloques ?? [];
    $totalBloquesDotacion = collect($bloquesResumenDotacion)->sum(fn ($bloque) => (float) ($bloque['total'] ?? 0));
    $totalAutomaticas = collect($bloquesResumenDotacion)->sum(fn ($bloque) => (float) ($bloque['automaticas'] ?? 0));
    $totalDeclaradas = collect($bloquesResumenDotacion)->sum(fn ($bloque) => (float) ($bloque['declaradas'] ?? 0));
    $cursosPlanesResumen = $cursos['resumen_cursos_planes'] ?? [
        'grupos' => $cursos['grupos'] ?? [],
        'rows' => $cursos['rows'] ?? [],
        'combinados' => [],
        'totales' => $cursos['totales'] ?? [],
        'tiene_cursos_combinados' => false,
    ];
    $gruposCursosPlanes = $cursosPlanesResumen['grupos'] ?? [];
    $rowsCursosPlanes = $cursosPlanesResumen['rows'] ?? [];
    $cursosCombinadosResumen = collect($cursosPlanesResumen['combinados'] ?? []);
    $totalesCursosPlanes = $cursosPlanesResumen['totales'] ?? ($cursos['totales'] ?? []);
    $tieneCursosCombinadosResumen = (bool) ($cursosPlanesResumen['tiene_cursos_combinados'] ?? false);
    $estadoSteps = [
        ['label' => 'Cursos', 'detail' => ((int) ($resumen['cursos_total'] ?? 0) > 0) ? 'Cursos con matrícula' : 'Sin cursos', 'ok' => (int) ($resumen['cursos_total'] ?? 0) > 0, 'icon' => 'bi-grid-3x3-gap'],
        ['label' => 'Planes', 'detail' => ((float) ($resumen['horas_plan_total'] ?? 0) > 0) ? 'Plan asociado' : 'Sin horas plan', 'ok' => (float) ($resumen['horas_plan_total'] ?? 0) > 0, 'icon' => 'bi-journal-check'],
        ['label' => 'PIE', 'detail' => ((float) ($resumen['trabajo_colaborativo_pie'] ?? 0) > 0) ? 'Cursos con NEE' : 'Sin trabajo PIE', 'ok' => true, 'icon' => 'bi-universal-access'],
        ['label' => 'Funciones', 'detail' => ($totalBloquesDotacion > 0) ? 'Bloques calculados' : 'Sin bloques', 'ok' => $totalBloquesDotacion > 0, 'icon' => 'bi-diagram-3'],
        ['label' => 'Brecha', 'detail' => (($resumen['sobredotacion_horas'] ?? 0) > 0 || ($resumen['horas_por_contratar'] ?? 0) > 0) ? 'Requiere revisión' : 'Sin diferencia', 'ok' => !(($resumen['sobredotacion_horas'] ?? 0) > 0 || ($resumen['horas_por_contratar'] ?? 0) > 0), 'icon' => 'bi-activity'],
    ];
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start gap-3">
            <span class="dotacion-icon" style="width:40px;height:40px;background:#0d6efd;"><i class="bi bi-building"></i></span>
            <div>
                <div class="dotacion-eyebrow">Identificación del establecimiento</div>
                <h2 class="h5 fw-bold mb-1">Datos del establecimiento</h2>
                <div class="text-muted small">Información base usada para consolidar dotación y cálculo anual.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="p-3 rounded-4 bg-light h-100"><div class="text-muted small fw-semibold">RBD</div><div class="fw-bold">{{ $establecimiento->rbd }}</div></div></div>
            <div class="col-md-5"><div class="p-3 rounded-4 bg-light h-100"><div class="text-muted small fw-semibold">Nombre EE</div><div class="fw-bold">{{ $establecimiento->nombre_establecimiento }}</div></div></div>
            <div class="col-md-2"><div class="p-3 rounded-4 bg-light h-100"><div class="text-muted small fw-semibold">Comuna</div><div class="fw-bold">{{ $establecimiento->comuna ?: '—' }}</div></div></div>
            <div class="col-md-2"><div class="p-3 rounded-4 bg-light h-100"><div class="text-muted small fw-semibold">Año</div><div class="fw-bold">{{ $anio }}</div></div></div>
        </div>
    </div>
</div>

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="dotacion-eyebrow">Seguimiento del cálculo</div>
            <h2 class="h5 fw-bold mb-1">Estado de la dotación</h2>
            <div class="text-muted small">Avance de la información necesaria para consolidar la dotación del establecimiento.</div>
        </div>
        <span class="badge rounded-pill text-bg-light border">Año {{ $anio }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach ($estadoSteps as $step)
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="h-100 p-3 rounded-4 border {{ $step['ok'] ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle' }}">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="rounded-circle d-inline-flex align-items-center justify-content-center {{ $step['ok'] ? 'bg-success text-white' : 'bg-warning text-dark' }}" style="width:36px;height:36px;"><i class="bi {{ $step['icon'] }}"></i></span>
                            <span class="badge rounded-pill {{ $step['ok'] ? 'text-bg-success' : 'text-bg-warning' }}">{{ $step['ok'] ? 'Completado' : 'Revisar' }}</span>
                        </div>
                        <div class="dotacion-eyebrow">Etapa {{ $loop->iteration }}</div>
                        <div class="fw-bold">{{ $step['label'] }}</div>
                        <div class="small text-muted mt-2">{{ $step['detail'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <div class="dotacion-eyebrow">Resumen por bloque</div>
                <h2 class="h5 fw-bold mb-1">Bloques de dotación · horas de contrato</h2>
                <div class="text-muted small">Horas normativas, declaradas/aprobadas y total por bloque.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge rounded-pill text-bg-warning">Normativas: {{ $fmt($totalAutomaticas) }}</span>
                <span class="badge rounded-pill text-bg-secondary">Declaradas: {{ $fmt($totalDeclaradas) }}</span>
                <span class="badge rounded-pill text-bg-primary">Total bloque: {{ $fmt($totalBloquesDotacion) }}</span>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            @foreach ($bloquesResumenDotacion as $bloque)
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="p-3 rounded-4 border h-100 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="small text-muted fw-semibold">{{ $bloque['label'] }}</div>
                                <div class="fs-4 fw-bold text-{{ $toneClass[$bloque['tone']] ?? 'secondary' }}">{{ $fmt($bloque['total'] ?? 0) }}</div>
                            </div>
                            <span class="rounded-3 d-inline-flex align-items-center justify-content-center bg-light text-{{ $toneClass[$bloque['tone']] ?? 'secondary' }}" style="width:34px;height:34px;"><i class="bi {{ $bloque['icon'] }}"></i></span>
                        </div>
                        <div class="small text-muted mt-2">Normativas: {{ $fmt($bloque['automaticas'] ?? 0) }} · Declaradas: {{ $fmt($bloque['declaradas'] ?? 0) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="table-responsive border rounded-4">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bloque</th>
                        <th class="text-end">Normativas</th>
                        <th class="text-end">Declaradas/aprobadas</th>
                        <th class="text-end">Total</th>
                        <th>Detalle principal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bloquesResumenDotacion as $bloque)
                        <tr>
                            <td class="fw-semibold"><i class="bi {{ $bloque['icon'] }} text-{{ $toneClass[$bloque['tone']] ?? 'secondary' }}"></i> {{ $bloque['label'] }}</td>
                            <td class="text-end">{{ $fmt($bloque['automaticas'] ?? 0) }}</td>
                            <td class="text-end">{{ $fmt($bloque['declaradas'] ?? 0) }}</td>
                            <td class="text-end fw-bold text-primary">{{ $fmt($bloque['total'] ?? 0) }}</td>
                            <td class="small text-muted">
                                @forelse (array_slice($bloque['items'] ?? [], 0, 4) as $item)
                                    <span class="badge dotacion-badge-soft me-1 mb-1">{{ $item['nombre'] }}: {{ $fmt($item['horas']) }}</span>
                                @empty
                                    Sin registros.
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total bloque de dotación</td>
                        <td class="text-end">{{ $fmt($totalAutomaticas) }}</td>
                        <td class="text-end">{{ $fmt($totalDeclaradas) }}</td>
                        <td class="text-end text-primary">{{ $fmt($totalBloquesDotacion) }}</td>
                        <td class="small text-muted">Total de contrato considerado en funciones directivas, técnico-pedagógicas, planes, otras funciones y eventuales horas PIE declaradas. Coordinación PIE y Educadoras Diferenciales normativas se informan en un bloque independiente.</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="dotacion-eyebrow">Cursos y planes</div>
            <h2 class="h5 fw-bold mb-1">Cantidad de cursos por nivel</h2>
            <div class="text-muted small">Horas plan, contrato equivalente y trabajo colaborativo PIE por nivel y grupo combinado.</div>
        </div>
        <span class="badge rounded-pill {{ $tieneCursosCombinadosResumen ? 'text-bg-primary' : 'text-bg-light border' }}">
            {{ $tieneCursosCombinadosResumen ? $cursosCombinadosResumen->count().' grupo(s) combinado(s)' : 'Contrato redondeado por curso' }}
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="p-3 rounded-4 bg-light"><div class="small text-muted">Horas plan</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($totalesCursosPlanes['horas'] ?? 0) }}</div></div></div>
            <div class="col-md-3"><div class="p-3 rounded-4 bg-light"><div class="small text-muted">Contrato equivalente</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($totalesCursosPlanes['horas_contrato_equivalente'] ?? 0) }}</div></div></div>
            <div class="col-md-3"><div class="p-3 rounded-4 bg-light"><div class="small text-muted">Trabajo colab. PIE</div><div class="h4 fw-bold text-success mb-0">{{ $fmt($totalesCursosPlanes['trabajo_colaborativo_pie'] ?? 0) }}</div></div></div>
            <div class="col-md-3"><div class="p-3 rounded-4 bg-light"><div class="small text-muted">Contrato + colab.</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($totalesCursosPlanes['contrato_mas_trabajo_colaborativo_pie'] ?? 0) }}</div></div></div>
        </div>
        <div class="alert alert-info border-0 rounded-4 small">
            <i class="bi bi-info-circle"></i>
            Para NT1 y NT2 se aplica regla especial sobre las primeras 32 h del plan: Con JEC equivale a 50 h de contrato y Sin JEC equivale a 47 h. Solo en cursos con JEC se agregan las horas de libre disposición efectivamente asignadas a docentes distintos de una Educadora de Párvulos, con un máximo de 6 h plan por curso; su contrato equivalente se calcula mediante 65/35. El resto de cursos usa 65/35 o 60/40 según corresponda. Las 3 h de trabajo colaborativo PIE se muestran aquí y no se duplican en el bloque PIE.
            @if ($tieneCursosCombinadosResumen)
                <span class="d-block mt-1"><i class="bi bi-intersect"></i> Los cursos combinados se presentan en filas consolidadas: sus horas plan y contrato reemplazan la suma individual de los cursos integrantes; el contrato equivalente se redondea una sola vez hacia arriba después de consolidar el grupo. El trabajo colaborativo PIE también se consolida en una sola necesidad de 3 h por grupo.</span>
            @endif
        </div>
        <div class="table-responsive border rounded-4">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nivel</th>
                        <th class="text-end">Matrícula {{ $anio }}</th>
                        <th class="text-end">Cursos {{ $anio }}</th>
                        <th class="text-end">Horas plan por curso</th>
                        <th class="text-end">Total horas plan</th>
                        <th class="text-center">Proporción</th>
                        <th class="text-end">Contrato equiv.</th>
                        <th class="text-end">Trabajo colab. PIE</th>
                        <th class="text-end">Contrato + colab.</th>
                        <th>Fuente / alerta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($gruposCursosPlanes as $grupoKey => $grupo)
                        @continue((int) ($grupo['totales']['matricula'] ?? 0) <= 0)
                        <tr class="table-success">
                            <th colspan="10">{{ $grupo['label'] }} @if ($tieneCursosCombinadosResumen)<span class="badge text-bg-light border ms-1">Cursos independientes</span>@endif</th>
                        </tr>
                        @foreach ($grupo['niveles'] as $nivelKey)
                            @php($row = $rowsCursosPlanes[$nivelKey] ?? null)
                            @continue(! $row || (int) ($row['matricula'] ?? 0) <= 0)
                            <tr>
                                <td class="fw-semibold">{{ $row['label'] }}</td>
                                <td class="text-end">{{ number_format((int) $row['matricula'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((int) $row['cursos'], 0, ',', '.') }}</td>
                                <td class="text-end">@if ($row['horas_variable'])<span class="badge text-bg-warning">Variable</span>@else{{ $fmt($row['horas_por_nivel']) }}@endif</td>
                                <td class="text-end fw-semibold">{{ $fmt($row['total_horas']) }}</td>
                                <td class="text-center"><span class="badge dotacion-badge-soft">{{ $row['proporcion_docente_label'] ?? '—' }}</span><div class="small text-muted mt-1">{{ $row['origen_proporcion_label'] ?? 'Regla general' }}</div></td>
                                <td class="text-end fw-semibold text-info">{{ $fmt($row['total_horas_contrato_equivalente'] ?? 0) }}</td>
                                <td class="text-end fw-semibold text-success">@if (($row['total_trabajo_colaborativo_pie'] ?? 0) > 0){{ $fmt($row['total_trabajo_colaborativo_pie']) }}@else<span class="text-muted">—</span>@endif</td>
                                <td class="text-end fw-semibold text-info">{{ $fmt($row['total_contrato_mas_trabajo_colaborativo_pie'] ?? (($row['total_horas_contrato_equivalente'] ?? 0) + ($row['total_trabajo_colaborativo_pie'] ?? 0))) }}</td>
                                <td class="small">
                                    @if ((int) ($row['cursos_refuerzo_ld_otro_docente'] ?? 0) > 0)
                                        <span class="text-primary d-block"><i class="bi bi-person-plus"></i> {{ (int) $row['cursos_refuerzo_ld_otro_docente'] }} curso(s) JEC: +{{ $fmt($row['horas_plan_refuerzo_ld_otro_docente'] ?? 0) }} h plan / +{{ $fmt($row['horas_contrato_refuerzo_ld_otro_docente'] ?? 0) }} h contrato por libre disposición</span>
                                    @endif
                                    @if ((int) $row['sin_horas_plan'] > 0)<span class="text-warning"><i class="bi bi-exclamation-triangle"></i> {{ (int) $row['sin_horas_plan'] }} curso(s) sin horas de plan</span>@elseif ((int) $row['cursos'] > 0)<span class="text-muted">Plan asociado</span>@else<span class="text-muted">—</span>@endif
                                </td>
                            </tr>
                        @endforeach
                        <tr class="fw-semibold">
                            <td>{{ $tieneCursosCombinadosResumen ? 'Subtotal' : 'Total' }} {{ $grupo['label'] }}{{ $tieneCursosCombinadosResumen ? ' independiente' : '' }}</td>
                            <td class="text-end">{{ number_format((int) ($grupo['totales']['matricula'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($grupo['totales']['cursos'] ?? 0), 0, ',', '.') }}</td>
                            <td></td>
                            <td class="text-end text-primary">{{ $fmt($grupo['totales']['horas'] ?? 0) }}</td>
                            <td></td>
                            <td class="text-end text-info">{{ $fmt($grupo['totales']['horas_contrato_equivalente'] ?? 0) }}</td>
                            <td class="text-end text-success">{{ $fmt($grupo['totales']['trabajo_colaborativo_pie'] ?? 0) }}</td>
                            <td class="text-end text-info">{{ $fmt($grupo['totales']['contrato_mas_trabajo_colaborativo_pie'] ?? (($grupo['totales']['horas_contrato_equivalente'] ?? 0) + ($grupo['totales']['trabajo_colaborativo_pie'] ?? 0))) }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                    @if ($cursosCombinadosResumen->isNotEmpty())
                        <tr class="table-primary">
                            <th colspan="10"><i class="bi bi-intersect"></i> Cursos combinados activos</th>
                        </tr>
                        @foreach ($cursosCombinadosResumen as $row)
                            <tr class="table-primary-subtle">
                                <td>
                                    <div class="fw-semibold">{{ $row['label'] }}</div>
                                    <div class="small text-muted">{{ $row['miembros_label'] }}</div>
                                    <span class="badge text-bg-primary mt-1">Grupo combinado</span>
                                </td>
                                <td class="text-end">{{ number_format((int) ($row['matricula'] ?? 0), 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((int) ($row['cursos'] ?? 0), 0, ',', '.') }}</td>
                                <td class="text-end">
                                    @if (!($row['horas_plan_por_curso_variable'] ?? false))
                                        <span class="fw-semibold">{{ $fmt($row['horas_plan_por_curso'] ?? 0) }} h</span>
                                        <div class="small text-muted">por curso</div>
                                    @else
                                        <span class="badge text-bg-warning">Variable</span>
                                        @foreach (($row['horas_plan_por_curso_detalle'] ?? []) as $detalleCurso)
                                            <div class="small text-muted">{{ $detalleCurso['curso'] }}: {{ $fmt($detalleCurso['horas']) }} h</div>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-end fw-semibold text-primary">{{ $fmt($row['total_horas'] ?? 0) }}</td>
                                <td class="text-center"><span class="badge dotacion-badge-soft">{{ $row['proporcion_docente_label'] ?? '—' }}</span><div class="small text-muted mt-1">Configuración del grupo</div></td>
                                <td class="text-end fw-semibold text-info">{{ $fmt($row['total_horas_contrato_equivalente'] ?? 0) }}</td>
                                <td class="text-end fw-semibold text-success">@if (($row['total_trabajo_colaborativo_pie'] ?? 0) > 0){{ $fmt($row['total_trabajo_colaborativo_pie']) }}@else<span class="text-muted">—</span>@endif</td>
                                <td class="text-end fw-semibold text-info">{{ $fmt($row['total_contrato_mas_trabajo_colaborativo_pie'] ?? 0) }}</td>
                                <td class="small">
                                    <span class="text-primary d-block"><i class="bi bi-intersect"></i> Reemplaza la suma individual de {{ (int) ($row['cursos'] ?? 0) }} cursos.</span>
                                    @if (($row['horas_plan_reduccion'] ?? 0) > 0)
                                        <span class="text-success d-block">Reducción: {{ $fmt($row['horas_plan_reduccion']) }} h plan no duplicadas.</span>
                                    @endif
                                    @if (($row['horas_plan_refuerzo_ld_otro_docente'] ?? 0) > 0)
                                        <span class="text-primary d-block">+{{ $fmt($row['horas_plan_refuerzo_ld_otro_docente']) }} h plan / +{{ $fmt($row['horas_contrato_refuerzo_ld_otro_docente'] ?? 0) }} h contrato por libre disposición.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @php($totalesCombinados = $cursosPlanesResumen['totales_combinados'] ?? [])
                        <tr class="fw-semibold table-primary-subtle">
                            <td>Total cursos combinados</td>
                            <td class="text-end">{{ number_format((int) ($totalesCombinados['matricula'] ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format((int) ($totalesCombinados['cursos'] ?? 0), 0, ',', '.') }}</td>
                            <td></td>
                            <td class="text-end text-primary">{{ $fmt($totalesCombinados['horas'] ?? 0) }}</td>
                            <td></td>
                            <td class="text-end text-info">{{ $fmt($totalesCombinados['horas_contrato_equivalente'] ?? 0) }}</td>
                            <td class="text-end text-success">{{ $fmt($totalesCombinados['trabajo_colaborativo_pie'] ?? 0) }}</td>
                            <td class="text-end text-info">{{ $fmt($totalesCombinados['contrato_mas_trabajo_colaborativo_pie'] ?? 0) }}</td>
                            <td></td>
                        </tr>
                    @endif
                    @if (empty($gruposCursosPlanes) && $cursosCombinadosResumen->isEmpty())
                        <tr><td colspan="10" class="text-center text-muted py-4">No existen cursos con matrícula vigente para el año seleccionado.</td></tr>
                    @endif
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total establecimiento</td>
                        <td class="text-end">{{ number_format((int) ($totalesCursosPlanes['matricula'] ?? 0), 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((int) ($totalesCursosPlanes['cursos'] ?? 0), 0, ',', '.') }}</td>
                        <td></td>
                        <td class="text-end text-primary">{{ $fmt($totalesCursosPlanes['horas'] ?? 0) }}</td>
                        <td></td>
                        <td class="text-end text-info">{{ $fmt($totalesCursosPlanes['horas_contrato_equivalente'] ?? 0) }}</td>
                        <td class="text-end text-success">{{ $fmt($totalesCursosPlanes['trabajo_colaborativo_pie'] ?? 0) }}</td>
                        <td class="text-end text-info">{{ $fmt($totalesCursosPlanes['contrato_mas_trabajo_colaborativo_pie'] ?? 0) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
