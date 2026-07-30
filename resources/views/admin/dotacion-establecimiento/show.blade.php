@extends('layouts.app')

@section('content')
    @php
        $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
        $activeTab = $tab ?? 'resumen';
        $horasContratoActuales = (float) ($resumen['horas_contrato_docentes'] ?? 0);
        $horasContratoRequeridas = (float) ($resumen['horas_contrato_requeridas'] ?? (($resumen['contrato_plan_mas_trabajo_colaborativo_pie'] ?? 0) + ($resumen['horas_dotacion_funciones'] ?? 0)));
        $sobredotacion = max(0, $horasContratoActuales - $horasContratoRequeridas);
        $horasPorContratar = max(0, $horasContratoRequeridas - $horasContratoActuales);
        $brechaLabel = $sobredotacion > 0.01 ? 'Horas de sobredotación' : ($horasPorContratar > 0.01 ? 'Horas por contratar' : 'Dotación cuadrada');
        $brechaValue = $sobredotacion > 0.01 ? $sobredotacion : ($horasPorContratar > 0.01 ? $horasPorContratar : 0);
        $brechaTone = $sobredotacion > 0.01 ? 'danger' : ($horasPorContratar > 0.01 ? 'success' : 'primary');
        $kpis = [
            ['label' => 'Matrícula', 'value' => number_format((int) ($resumen['matricula_total'] ?? 0), 0, ',', '.'), 'hint' => 'Estudiantes con matrícula vigente.', 'tone' => 'dark', 'icon' => 'bi-people'],
            ['label' => 'Cursos', 'value' => number_format((int) ($resumen['cursos_total'] ?? 0), 0, ',', '.'), 'hint' => 'Cursos con matrícula.', 'tone' => 'primary', 'icon' => 'bi-grid-3x3-gap'],
            ['label' => 'Docentes', 'value' => number_format((int) ($resumen['docentes_total'] ?? 0), 0, ',', '.'), 'hint' => 'Base contractual vigente.', 'tone' => 'success', 'icon' => 'bi-person-workspace'],
            ['label' => 'Horas plan', 'value' => $fmt($resumen['horas_plan_total'] ?? 0), 'hint' => ((float) ($resumen['horas_plan_reduccion_cursos_combinados'] ?? 0) > 0 ? 'Necesidad ajustada por cursos combinados.' : 'Horas pedagógicas/aula.'), 'tone' => 'primary', 'icon' => 'bi-journal-text'],
            ['label' => 'Contrato plan', 'value' => $fmt($resumen['horas_plan_contrato_equivalente'] ?? 0), 'hint' => ((float) ($resumen['horas_plan_contrato_reduccion_cursos_combinados'] ?? 0) > 0 ? 'Equivalente ajustado después de consolidar cursos combinados.' : 'Equivalente contractual redondeado por curso.'), 'tone' => 'info', 'icon' => 'bi-calculator'],
            ['label' => 'Trabajo colab. PIE', 'value' => $fmt($resumen['trabajo_colaborativo_pie'] ?? 0), 'hint' => '3 horas por curso con NEE.', 'tone' => 'success', 'icon' => 'bi-universal-access'],
            ['label' => 'Contrato plan + PIE', 'value' => $fmt($resumen['contrato_plan_mas_trabajo_colaborativo_pie'] ?? 0), 'hint' => 'Plan más trabajo colaborativo.', 'tone' => 'info', 'icon' => 'bi-plus-square'],
            ['label' => 'Contrato bloque dotación', 'value' => $fmt($resumen['horas_dotacion_funciones'] ?? 0), 'hint' => 'Directivos, funciones, PIE y planes.', 'tone' => 'warning', 'icon' => 'bi-diagram-3'],
            ['label' => 'Horas contrato docentes', 'value' => $fmt($horasContratoActuales), 'hint' => 'Horas contratadas vigentes.', 'tone' => 'dark', 'icon' => 'bi-briefcase'],
        ];
    @endphp

    <style>
        .dotacion-hero { background: linear-gradient(135deg, #ffffff 0%, #f7fbff 55%, #eef5ff 100%); border: 1px solid #dbe8fb; border-radius: 1.25rem; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        .dotacion-icon { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: #0d6efd; color: #fff; box-shadow: 0 10px 20px rgba(13, 110, 253, .22); }
        .dotacion-kpi { border: 1px solid #e5ecf6; border-radius: 1rem; box-shadow: 0 8px 20px rgba(15, 23, 42, .045); height: 100%; }
        .dotacion-kpi .kpi-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: .85rem; background: #f2f6ff; }
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
                @if (Route::has('admin.dotacion-funciones.show'))
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
        <div class="col-xxl col-xl-3 col-md-4 col-sm-6">
            <div class="card dotacion-kpi border-0 border-{{ $brechaTone }}">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div class="text-muted small fw-semibold">Brecha final</div>
                        <span class="kpi-icon text-{{ $brechaTone }}"><i class="bi {{ $brechaTone === 'danger' ? 'bi-exclamation-triangle' : ($brechaTone === 'success' ? 'bi-plus-circle' : 'bi-check-circle') }}"></i></span>
                    </div>
                    <div class="fs-3 fw-bold text-{{ $brechaTone }}">{{ $fmt($brechaValue) }}</div>
                    <div class="small text-muted">{{ $brechaLabel }}</div>
                    <div class="small text-muted mt-1">Requeridas: <span class="fw-semibold">{{ $fmt($horasContratoRequeridas) }}</span></div>
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
            @if (Route::has('admin.dotacion-funciones.show'))
                <li class="nav-item" role="presentation">
                    <a class="nav-link" href="{{ route('admin.dotacion-funciones.show', [$establecimiento, 'anio' => $anio]) }}">
                        <i class="bi bi-diagram-3"></i> Funciones y planes
                    </a>
                </li>
            @endif
        </ul>
    </div>

    @if ($activeTab === 'docentes')
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
