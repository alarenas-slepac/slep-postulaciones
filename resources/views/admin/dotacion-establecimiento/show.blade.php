@extends('layouts.app')

@section('content')
    @php
        $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
        $activeTab = $tab ?? 'resumen';
        $horasContratoActuales = (float) ($resumen['horas_contrato_docentes'] ?? 0);
        $horasContratoAula = (float) ($resumen['horas_contrato_docentes_aula'] ?? $horasContratoActuales);
        $horasContratoDocentePie = (float) ($resumen['horas_contrato_docente_pie'] ?? 0);
        $horasContratoCoordinacionPie = (float) ($resumen['horas_contrato_docente_pie_coordinacion'] ?? 0);
        $horasContratoEducadorasDiferenciales = (float) ($resumen['horas_contrato_docente_pie_educadoras_diferenciales'] ?? 0);
        $horasContratoPieNecesarias = (float) ($resumen['horas_contrato_pie_necesarias'] ?? 0);
        $desgloseContratoPieNecesario = $resumen['horas_contrato_pie_necesarias_desglose'] ?? [];
        $horasContratoPieNecesariasAsignadas = (float) ($desgloseContratoPieNecesario['total_asignadas'] ?? 0);
        $horasPlanAsignadas = (float) ($resumen['horas_plan_asignadas'] ?? $resumen['horas_aula_asignadas'] ?? 0);
        $horasContratoPlanAsignadas = (float) ($resumen['horas_plan_contrato_asignadas'] ?? 0);
        $trabajoColaborativoPieAsignadas = (float) ($resumen['trabajo_colaborativo_pie_asignadas'] ?? 0);
        $contratoPlanMasPieAsignadas = (float) ($resumen['contrato_plan_mas_trabajo_colaborativo_pie_asignadas'] ?? ($horasContratoPlanAsignadas + $trabajoColaborativoPieAsignadas));
        $horasContratoRequeridas = (float) ($resumen['horas_contrato_requeridas'] ?? (($resumen['contrato_plan_mas_trabajo_colaborativo_pie'] ?? 0) + ($resumen['horas_dotacion_funciones'] ?? 0) + $horasContratoPieNecesarias));
        $desgloseContratoBloque = $resumen['horas_dotacion_desglose'] ?? [];
        $horasBloqueNormativas = (float) ($resumen['horas_dotacion_funciones_normativas'] ?? $desgloseContratoBloque['total_normativas'] ?? 0);
        $horasBloqueDeclaradas = (float) ($resumen['horas_dotacion_funciones_declaradas'] ?? $desgloseContratoBloque['total_declaradas'] ?? 0);
        $horasBloqueDeclaradasAsignadas = (float) ($desgloseContratoBloque['total_declaradas_asignadas'] ?? 0);
        $brechaDotacionGeneral = (float) ($resumen['brecha_dotacion_general'] ?? ((($resumen['contrato_plan_mas_trabajo_colaborativo_pie'] ?? 0) + $horasBloqueNormativas + $horasBloqueDeclaradas) - $horasContratoAula));
        $brechaDotacionPie = (float) ($resumen['brecha_dotacion_pie'] ?? ($horasContratoPieNecesarias - $horasContratoDocentePie));
        $brechaContratoFinal = (float) ($resumen['brecha_contrato_final'] ?? ($horasContratoRequeridas - $horasContratoActuales));
        $resultadoBrecha = static function (float $valor) use ($fmt): array {
            if ($valor < -0.01) {
                return ['label' => 'Horas de sobredotación', 'value' => $fmt(abs($valor)), 'tone' => 'danger', 'icon' => 'bi-exclamation-triangle'];
            }

            if ($valor > 0.01) {
                return ['label' => 'Horas necesarias', 'value' => '+'.$fmt($valor), 'tone' => 'success', 'icon' => 'bi-plus-circle'];
            }

            return ['label' => 'Dotación cuadrada', 'value' => '0', 'tone' => 'primary', 'icon' => 'bi-check-circle'];
        };
        $resultadoGeneral = $resultadoBrecha($brechaDotacionGeneral);
        $resultadoPie = $resultadoBrecha($brechaDotacionPie);
        $resultadoFinal = $resultadoBrecha($brechaContratoFinal);
        $desgloseNormativoItems = [
            ['label' => 'Funciones directivas', 'assigned' => $desgloseContratoBloque['funciones_directivas_normativas_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['funciones_directivas_normativas'] ?? 0, 'tone' => 'primary', 'icon' => 'bi-person-badge'],
            ['label' => 'Téc.-pedagógicas normativas', 'assigned' => $desgloseContratoBloque['funciones_tecnico_pedagogicas_normativas_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['funciones_tecnico_pedagogicas_normativas'] ?? 0, 'tone' => 'success', 'icon' => 'bi-shield-check'],
            ['label' => 'Planes normativos', 'assigned' => $desgloseContratoBloque['planes_normativos_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['planes_normativos'] ?? 0, 'tone' => 'warning', 'icon' => 'bi-journal-check'],
        ];
        $desgloseDeclaradoItems = [
            ['label' => 'Téc.-pedagógicas declaradas', 'assigned' => $desgloseContratoBloque['funciones_tecnico_pedagogicas_declaradas_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['funciones_tecnico_pedagogicas_declaradas'] ?? 0, 'tone' => 'success', 'icon' => 'bi-building-add'],
            ['label' => 'Otras funciones declaradas', 'assigned' => $desgloseContratoBloque['otras_funciones_declaradas_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['otras_funciones_declaradas'] ?? 0, 'tone' => 'secondary', 'icon' => 'bi-plus-square-dotted'],
        ];
        if ((float) ($desgloseContratoBloque['funciones_directivas_declaradas'] ?? 0) > 0) {
            $desgloseDeclaradoItems[] = ['label' => 'Funciones directivas declaradas', 'assigned' => $desgloseContratoBloque['funciones_directivas_declaradas_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['funciones_directivas_declaradas'], 'tone' => 'primary', 'icon' => 'bi-person-add'];
        }
        if ((float) ($desgloseContratoBloque['planes_declarados'] ?? 0) > 0) {
            $desgloseDeclaradoItems[] = ['label' => 'Planes declarados', 'assigned' => $desgloseContratoBloque['planes_declarados_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['planes_declarados'], 'tone' => 'warning', 'icon' => 'bi-journal-plus'];
        }
        if ((float) ($desgloseContratoBloque['otras_funciones_pie'] ?? 0) > 0) {
            $desgloseDeclaradoItems[] = ['label' => 'Otras funciones PIE declaradas', 'assigned' => $desgloseContratoBloque['otras_funciones_pie_asignadas'] ?? 0, 'value' => $desgloseContratoBloque['otras_funciones_pie'], 'tone' => 'info', 'icon' => 'bi-universal-access'];
        }
        $kpis = [
            ['label' => 'Matrícula', 'value' => number_format((int) ($resumen['matricula_total'] ?? 0), 0, ',', '.'), 'hint' => 'Estudiantes con matrícula vigente.', 'tone' => 'dark', 'icon' => 'bi-people'],
            ['label' => 'Cursos', 'value' => number_format((int) ($resumen['cursos_total'] ?? 0), 0, ',', '.'), 'hint' => 'Cursos con matrícula.', 'tone' => 'primary', 'icon' => 'bi-grid-3x3-gap'],
            ['label' => 'Docentes', 'value' => number_format((int) ($resumen['docentes_total'] ?? 0), 0, ',', '.'), 'hint' => 'Base contractual vigente.', 'tone' => 'success', 'icon' => 'bi-person-workspace'],
            ['label' => 'Horas plan', 'value' => $fmt($horasPlanAsignadas).' / '.$fmt($resumen['horas_plan_total'] ?? 0), 'hint' => 'Horas aula asignadas / requeridas.', 'tone' => 'primary', 'icon' => 'bi-journal-text'],
            ['label' => 'Contrato plan', 'value' => $fmt($horasContratoPlanAsignadas).' / '.$fmt($resumen['horas_plan_contrato_equivalente'] ?? 0), 'hint' => 'Contrato asignado / requerido para el plan.', 'tone' => 'info', 'icon' => 'bi-calculator'],
            ['label' => 'Trabajo colab. PIE', 'value' => $fmt($trabajoColaborativoPieAsignadas).' / '.$fmt($resumen['trabajo_colaborativo_pie'] ?? 0), 'hint' => 'Horas asignadas / requeridas.', 'tone' => 'success', 'icon' => 'bi-universal-access'],
            ['label' => 'Contrato plan + PIE', 'value' => $fmt($contratoPlanMasPieAsignadas).' / '.$fmt($resumen['contrato_plan_mas_trabajo_colaborativo_pie'] ?? 0), 'hint' => 'Contrato asignado / requerido.', 'tone' => 'info', 'icon' => 'bi-plus-square'],
            ['label' => 'Contrato bloque normativo', 'value' => $fmt($horasBloqueNormativas), 'hint' => 'Funciones y planes calculados por normativa.', 'tone' => 'warning', 'icon' => 'bi-shield-check'],
            ['label' => 'Contrato bloque declarado', 'value' => $fmt($horasBloqueDeclaradasAsignadas).' / '.$fmt($horasBloqueDeclaradas), 'hint' => 'Asignadas / declaradas por el establecimiento.', 'tone' => 'secondary', 'icon' => 'bi-building-add'],
            ['label' => 'Horas contrato PIE necesarias', 'value' => $fmt($horasContratoPieNecesariasAsignadas).' / '.$fmt($horasContratoPieNecesarias), 'hint' => 'Asignadas / necesarias para Coordinación PIE y Educadoras Diferenciales.', 'tone' => 'info', 'icon' => 'bi-universal-access'],
            ['label' => 'Horas contrato docentes', 'value' => $fmt($horasContratoActuales), 'hint' => 'Horas contratadas vigentes.', 'tone' => 'dark', 'icon' => 'bi-briefcase'],
            ['label' => 'Horas contrato aula', 'value' => $fmt($horasContratoAula), 'hint' => 'Contrato vigente descontando las asignaciones docentes PIE.', 'tone' => 'primary', 'icon' => 'bi-easel2'],
            ['label' => 'Horas contrato docente PIE', 'value' => $fmt($horasContratoDocentePie), 'hint' => 'Coordinación PIE: '.$fmt($horasContratoCoordinacionPie).' · Bolsa Educ. Diferenciales: '.$fmt($horasContratoEducadorasDiferenciales).'.', 'tone' => 'info', 'icon' => 'bi-universal-access'],
        ];
    @endphp

    <style>
        .dotacion-hero { background: linear-gradient(135deg, #ffffff 0%, #f7fbff 55%, #eef5ff 100%); border: 1px solid #dbe8fb; border-radius: 1.25rem; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        .dotacion-icon { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: #0d6efd; color: #fff; box-shadow: 0 10px 20px rgba(13, 110, 253, .22); }
        .dotacion-kpi { border: 1px solid #e5ecf6; border-radius: 1rem; box-shadow: 0 8px 20px rgba(15, 23, 42, .045); height: 100%; }
        .dotacion-kpi .kpi-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: .85rem; background: #f2f6ff; }
        .dotacion-breakdown { background: linear-gradient(135deg, #fffaf0 0%, #ffffff 55%, #f8fbff 100%); }
        .dotacion-breakdown-item { border: 1px solid #e5ecf6; border-radius: .85rem; background: rgba(255, 255, 255, .9); height: 100%; }
        .dotacion-section { border: 1px solid #dce7f5; border-radius: 1rem; box-shadow: 0 8px 20px rgba(15, 23, 42, .045); overflow: hidden; }
        .dotacion-section-header { background: #fff; border-bottom: 1px solid #e6edf6; padding: 1rem 1.25rem; }
        .dotacion-eyebrow { color: #64748b; font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .dotacion-pill-tabs { border: 1px solid #e2eaf6; border-radius: 1rem; background: #fff; padding: .45rem; box-shadow: 0 8px 18px rgba(15, 23, 42, .04); }
        .dotacion-pill-tabs .nav-link { border-radius: .75rem; font-weight: 700; color: #0b4aa2; }
        .dotacion-pill-tabs .nav-link.active { background: #0d6efd; color: #fff; box-shadow: 0 8px 18px rgba(13, 110, 253, .22); }
        .dotacion-badge-soft { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
    </style>

    <div class="dotacion-hero p-4 p-lg-5 mb-4">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-4">
            <div class="d-flex gap-3 align-items-start">
                <span class="dotacion-icon"><i class="bi bi-bar-chart-steps fs-5"></i></span>
                <div>
                    <div class="dotacion-eyebrow mb-1">Dotación · Establecimiento</div>
                    <h1 class="display-6 fw-bold mb-1">Dotación Establecimiento</h1>
                    <p class="mb-2 text-muted fs-6">Consolidado de cursos, horas plan, contrato equivalente, funciones docentes y brecha contractual.</p>
                    <div class="fw-semibold text-dark">{{ $establecimiento->rbd }} — {{ $establecimiento->nombre_establecimiento }} · {{ $establecimiento->comuna ?: 'Sin comuna' }} · Año {{ $anio }}</div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start justify-content-xl-end">
                @if (Route::has('admin.dotacion-funciones.show') && $activeRole !== 'supervisor_plani')
                    <a class="btn btn-outline-primary rounded-pill px-4" href="{{ route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => $anio]) }}">
                        <i class="bi bi-diagram-3"></i> Funciones y planes
                    </a>
                @endif
                @if (Route::has('admin.dotacion-establecimiento.pdf'))
                    <a class="btn btn-primary rounded-pill px-4" href="{{ route('admin.dotacion-establecimiento.pdf', [$establecimiento, 'anio' => $anio]) }}">
                        <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                    </a>
                @endif
                <a class="btn btn-outline-secondary rounded-pill px-4" href="{{ route('admin.dotacion-establecimiento.index', ['anio' => $anio]) }}">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    @if (!empty($alertas))
        <div class="alert alert-warning shadow-sm border-0 rounded-4 mb-4">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle"></i> Alertas de consolidación</div>
            <ul class="mb-0 small">
                @foreach ($alertas as $alerta)
                    <li>{{ $alerta }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('admin.dotacion-establecimiento.partials._proporcion_excepcion')

    <div class="row g-3 mb-3">
        @foreach ($kpis as $kpi)
            <div class="col-xxl col-xl-3 col-md-4 col-sm-6">
                <div class="card dotacion-kpi border-0">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div class="text-muted small fw-semibold">{{ $kpi['label'] }}</div>
                            <span class="kpi-icon text-{{ $kpi['tone'] }}"><i class="bi {{ $kpi['icon'] }}"></i></span>
                        </div>
                        <div class="fs-3 fw-bold text-{{ $kpi['tone'] }}">{{ $kpi['value'] }}</div>
                        <div class="small text-muted">{{ $kpi['hint'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-12">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card dotacion-kpi border-0 border-{{ $resultadoGeneral['tone'] }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <div class="text-muted small fw-semibold">Dotación general</div>
                                    <div class="small text-muted">{{ $resultadoGeneral['label'] }}</div>
                                </div>
                                <span class="kpi-icon text-{{ $resultadoGeneral['tone'] }}"><i class="bi {{ $resultadoGeneral['icon'] }}"></i></span>
                            </div>
                            <div class="fs-2 fw-bold text-{{ $resultadoGeneral['tone'] }}">{{ $resultadoGeneral['value'] }}</div>
                            <div class="small text-muted mt-1">
                                (Contrato plan + trabajo colaborativo PIE + bloque normativo + bloque declarado) − contrato aula.<br>
                                <span class="fw-semibold">({{ $fmt($resumen['horas_plan_contrato_equivalente'] ?? 0) }} + {{ $fmt($resumen['trabajo_colaborativo_pie'] ?? 0) }} + {{ $fmt($horasBloqueNormativas) }} + {{ $fmt($horasBloqueDeclaradas) }}) − {{ $fmt($horasContratoAula) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card dotacion-kpi border-0 border-{{ $resultadoPie['tone'] }}">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div>
                                    <div class="text-muted small fw-semibold">Dotación PIE</div>
                                    <div class="small text-muted">{{ $resultadoPie['label'] }}</div>
                                </div>
                                <span class="kpi-icon text-{{ $resultadoPie['tone'] }}"><i class="bi {{ $resultadoPie['icon'] }}"></i></span>
                            </div>
                            <div class="fs-2 fw-bold text-{{ $resultadoPie['tone'] }}">{{ $resultadoPie['value'] }}</div>
                            <div class="small text-muted mt-1">
                                Horas de contrato PIE necesarias − horas contrato docente PIE.<br>
                                <span class="fw-semibold">{{ $fmt($horasContratoPieNecesarias) }} − {{ $fmt($horasContratoDocentePie) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl col-xl-3 col-md-4 col-sm-6">
            <div class="card dotacion-kpi border-0 border-{{ $resultadoFinal['tone'] }}">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div class="text-muted small fw-semibold">{{ $resultadoFinal['label'] }}</div>
                        <span class="kpi-icon text-{{ $resultadoFinal['tone'] }}"><i class="bi {{ $resultadoFinal['icon'] }}"></i></span>
                    </div>
                    <div class="fs-3 fw-bold text-{{ $resultadoFinal['tone'] }}">{{ $resultadoFinal['value'] }}</div>
                    <div class="small text-muted">Resultado contractual final para comparación.</div>
                    <div class="small text-muted mt-1">Requeridas: <span class="fw-semibold">{{ $fmt($horasContratoRequeridas) }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card dotacion-kpi dotacion-breakdown border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                        <div>
                            <div class="text-muted small fw-semibold">Desglose contrato bloque dotación</div>
                            <div class="small text-muted">Separa las horas normativas de las declaradas. La cobertura normativa se muestra como horas asignadas / horas requeridas.</div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">Total bloque</div>
                            <div class="fs-4 fw-bold text-warning">{{ $fmt($resumen['horas_dotacion_funciones'] ?? 0) }}</div>
                            <div class="small text-muted">Normativas {{ $fmt($horasBloqueNormativas) }} · Declaradas {{ $fmt($horasBloqueDeclaradas) }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <div class="small fw-bold text-uppercase text-muted">Horas normativas</div>
                        <span class="badge rounded-pill text-bg-warning">Total {{ $fmt($horasBloqueNormativas) }}</span>
                    </div>
                    <div class="row g-2">
                        @foreach ($desgloseNormativoItems as $item)
                            <div class="col-lg-4 col-md-6">
                                <div class="dotacion-breakdown-item p-3">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <div>
                                            <div class="small text-muted fw-semibold">{{ $item['label'] }}</div>
                                            <div class="fs-5 fw-bold text-{{ $item['tone'] }}">{{ $fmt($item['assigned']) }} / {{ $fmt($item['value']) }}</div>
                                            <div class="small text-muted">Asignadas / requeridas</div>
                                        </div>
                                        <span class="kpi-icon text-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <hr class="my-3">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <div class="small fw-bold text-uppercase text-muted">Horas declaradas por el establecimiento</div>
                        <span class="badge rounded-pill text-bg-secondary">Asignadas / declaradas: {{ $fmt($horasBloqueDeclaradasAsignadas) }} / {{ $fmt($horasBloqueDeclaradas) }}</span>
                    </div>
                    <div class="row g-2">
                        @foreach ($desgloseDeclaradoItems as $item)
                            <div class="col-xxl col-xl-3 col-md-4 col-sm-6">
                                <div class="dotacion-breakdown-item p-3">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <div>
                                            <div class="small text-muted fw-semibold">{{ $item['label'] }}</div>
                                            <div class="fs-5 fw-bold text-{{ $item['tone'] }}">{{ $fmt($item['assigned']) }} / {{ $fmt($item['value']) }}</div>
                                            <div class="small text-muted">Asignadas / declaradas</div>
                                        </div>
                                        <span class="kpi-icon text-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card dotacion-kpi border-0 bg-info-subtle">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                        <div>
                            <div class="text-muted small fw-semibold">Horas de contrato PIE necesarias</div>
                            <div class="small text-muted">Horas automáticas normativas separadas del contrato bloque dotación.</div>
                        </div>
                        <div class="text-end">
                            <div class="small text-muted">Asignadas / necesarias</div>
                            <div class="fs-4 fw-bold text-info">{{ $fmt($horasContratoPieNecesariasAsignadas) }} / {{ $fmt($horasContratoPieNecesarias) }}</div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="dotacion-breakdown-item p-3">
                                <div class="small text-muted fw-semibold">Coordinador(a) PIE</div>
                                <div class="fs-5 fw-bold text-info">{{ $fmt($desgloseContratoPieNecesario['coordinacion_pie_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoPieNecesario['coordinacion_pie'] ?? 0) }}</div>
                                <div class="small text-muted">Asignadas / necesarias</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dotacion-breakdown-item p-3">
                                <div class="small text-muted fw-semibold">Educadoras diferenciales PIE</div>
                                <div class="fs-5 fw-bold text-info">{{ $fmt($desgloseContratoPieNecesario['educadoras_diferenciales_asignadas'] ?? 0) }} / {{ $fmt($desgloseContratoPieNecesario['educadoras_diferenciales'] ?? 0) }}</div>
                                <div class="small text-muted">Asignadas / necesarias</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dotacion-pill-tabs mb-4">
        <ul class="nav nav-pills gap-2" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'resumen' ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'resumen']) }}">
                    <i class="bi bi-grid-3x3-gap"></i> Resumen Establecimiento
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'docentes' ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'docentes']) }}">
                    <i class="bi bi-person-workspace"></i> Docentes
                </a>
            </li>
            @if ($canViewSobredotacion ?? false)
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab === 'sobredotacion' ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'sobredotacion']) }}">
                        <i class="bi bi-person-exclamation"></i> Detalle sobredotación
                    </a>
                </li>
            @endif
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'asignacion' ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'asignacion']) }}">
                    <i class="bi bi-clipboard-plus"></i> Asignación de horas
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'asignaturas' ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'asignaturas']) }}">
                    <i class="bi bi-journal-check"></i> Horas por asignatura
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'cursos-combinados' ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'cursos-combinados']) }}">
                    <i class="bi bi-intersect"></i> Cursos combinados
                </a>
            </li>
            @if (Route::has('admin.dotacion-funciones.show') && $activeRole !== 'supervisor_plani')
                <li class="nav-item" role="presentation">
                    <a class="nav-link" href="{{ route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => $anio]) }}">
                        <i class="bi bi-diagram-3"></i> Funciones y planes
                    </a>
                </li>
            @endif
        </ul>
    </div>

    @if ($activeTab === 'sobredotacion')
        @include('admin.dotacion-establecimiento.partials._sobredotacion')
    @elseif ($activeTab === 'docentes')
        @include('admin.dotacion-establecimiento.partials._docentes')
    @elseif ($activeTab === 'asignacion')
        @include('admin.dotacion-establecimiento.partials._asignacion')
    @elseif ($activeTab === 'asignaturas')
        @include('admin.dotacion-establecimiento.partials._asignaturas')
    @elseif ($activeTab === 'cursos-combinados')
        @include('admin.dotacion-establecimiento.partials._cursos_combinados')
    @else
        @include('admin.dotacion-establecimiento.partials._resumen')
    @endif
@endsection
