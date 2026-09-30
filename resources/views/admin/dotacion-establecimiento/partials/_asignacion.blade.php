@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $asignacion = $asignacion ?? [];
    $resumenAsignacion = $asignacion['resumen'] ?? [];
    $necesidades = $asignacion['necesidades'] ?? [];
    $asignaciones = $asignacion['asignaciones'] ?? collect();
    $asignacionesHuerfanas = collect($asignacion['asignaciones_huerfanas'] ?? []);
    $subvenciones = $asignacion['subvenciones'] ?? collect();
    $docentesAsignacion = $asignacion['docentes'] ?? $docentes;
    $proceso2027Asignacion = $proceso2027 ?? ['aplica' => false];
    $subsectoresAsignacion = collect($proceso2027Asignacion['docentes_subsector']['asignaturas'] ?? [])->keyBy('key');
    $asignacion2027Habilitada = !($proceso2027Asignacion['aplica'] ?? false) || ($proceso2027Asignacion['asignacion_habilitada'] ?? false);
    $asistentesAsignacion = collect($asignacion['asistentes'] ?? []);
    $subvencionesOptions = ['General', 'SEP', 'PIE', 'Libre disposición', 'Otra', 'Sin clasificar'];
    $buildPersonalOptions = function ($personal, string $estamento) use ($fmt) {
        return collect($personal)->map(function ($persona) use ($fmt, $estamento) {
            $contrato = (float) ($persona['horas_contrato'] ?? 0);
            $asignadas = (float) ($persona['horas_asignadas_total'] ?? 0);
            $saldo = round($contrato - $asignadas, 2);
            $detalleContrato = trim((string) ($persona['horas_contrato_detalle'] ?? ''));
            $origenContrato = $detalleContrato !== '' ? ' · '.$detalleContrato : '';
            $titulo = trim((string) ($persona['titulo'] ?? ''));
            $detalleTitulo = $titulo !== '' ? ' · Título: '.$titulo : ' · Sin título declarado';
            $prioridad = $estamento === 'docente' ? (int) ($persona['prioridad_2027'] ?? 99) : 99;
            $prioridadLabel = $estamento === 'docente' ? trim((string) ($persona['prioridad_2027_label'] ?? '')) : '';
            $antiguedad = $prioridadLabel !== '' ? (string) ($persona['fecha_antiguedad'] ?? '') : '';
            $titularDisponible = (float) ($persona['horas_titulares_disponibles'] ?? 0);
            $contrataDisponible = (float) ($persona['horas_contrata_disponibles'] ?? max(0, $saldo));
            $detallePrioridad = $prioridadLabel !== '' ? ' · '.$prioridadLabel : '';
            $detalleAntiguedad = $prioridadLabel !== ''
                ? ($antiguedad !== '' ? ' · Antigüedad: '.$antiguedad : ' · Sin antigüedad')
                : '';
            $virtual = (bool) ($persona['cupo_contrata_id'] ?? false);

            return [
                'rut' => $persona['rut'],
                'rut_normalizado' => $persona['rut_normalizado'] ?? $persona['rut'],
                'nombre' => $persona['nombre'],
                'funcion' => $persona['funcion'] ?? 'Sin función',
                'estamento' => $estamento,
                'label' => $persona['nombre'].' · '.$persona['rut'].($virtual ? ' · Cupo provisional' : $detallePrioridad.$detalleAntiguedad).$detalleTitulo.' · Disponible: '.$fmt($titularDisponible).' titular + '.$fmt($contrataDisponible).' contrata',
                'virtual' => $virtual,
                'cupo_bloque' => $persona['cupo_bloque'] ?? null,
                'titulo' => $titulo,
                'es_parvularia' => \App\Support\DotacionProfesionDocenteResolver::perfilTitulo($persona)['es_educacion_parvulos'],
                'saldo' => $saldo,
                'prioridad' => $prioridad,
                'prioridad_label' => $prioridadLabel,
                'antiguedad' => $antiguedad,
                'titular_disponible' => $titularDisponible,
                'contrata_disponible' => $contrataDisponible,
            ];
        })->filter(fn ($persona) => $estamento !== 'docente' || $persona['saldo'] > 0.01)->values();
    };
    $docenteOptions = $buildPersonalOptions($docentesAsignacion, 'docente');
    $asistenteOptions = $buildPersonalOptions($asistentesAsignacion, 'asistente');
@endphp

@include('admin.dotacion-establecimiento.partials._personal_select_assets')

@once
    @push('styles')
        <style>
            .select2-container { width: 100% !important; }
            .dotacion-assignment-form {
                padding: .75rem;
                border: 1px solid #dce7f5;
                border-radius: .85rem;
                background: linear-gradient(135deg, #fbfdff 0%, #f4f8ff 100%);
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, .9);
            }
            .dotacion-selector-guide {
                display: flex;
                align-items: flex-start;
                gap: .45rem;
                padding: .5rem .625rem;
                border-radius: .6rem;
                background: #eaf2ff;
                color: #174b91;
                font-size: .75rem;
                line-height: 1.35;
            }
            .dotacion-selector-guide .bi { margin-top: .05rem; }
            .select2-container--default .select2-selection--single {
                min-height: 2.65rem;
                border-color: #dbe4f0;
                border-radius: .75rem;
                background: #fff;
                box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
            }
            .select2-container--default .select2-selection--single .select2-selection__rendered {
                padding: .25rem 2rem .25rem .65rem;
                line-height: 1.5;
                color: #1e293b;
            }
            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 100%;
                right: .45rem;
            }
            .select2-container--default.select2-container--focus .select2-selection--single,
            .select2-container--default.select2-container--open .select2-selection--single {
                border-color: #0d6efd;
                box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
            }
            .dotacion-personal-dropdown { z-index: 1080; }
            .dotacion-personal-dropdown .select2-search--dropdown { padding: .6rem; background: #f7faff; border-bottom: 1px solid #dce7f5; }
            .dotacion-personal-dropdown .select2-search__field { min-height: 2.1rem; border: 1px solid #9db7dc; border-radius: .5rem; padding: .35rem .55rem; }
            .dotacion-personal-dropdown .select2-results__option { padding: .45rem .65rem; }
            .dotacion-personal-dropdown .select2-results__option--highlighted.select2-results__option--selectable { background: #eaf2ff; color: #122e58; }
            .dotacion-personal-option { display: grid; gap: .25rem; }
            .dotacion-personal-option__name { font-weight: 700; color: #172554; }
            .dotacion-personal-option__meta { display: flex; gap: .35rem; flex-wrap: wrap; color: #475569; font-size: .78rem; }
            .dotacion-personal-option__priority { color: #0b4aa2; font-weight: 700; }
            .dotacion-personal-option__availability { color: #0f766e; }
            .dotacion-personal-selection { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            @media (max-width: 767.98px) {
                .dotacion-assignment-form { padding: .65rem; }
            }
        </style>
    @endpush
@endonce

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start gap-3">
            <span class="dotacion-icon" style="width:40px;height:40px;background:#0d6efd;"><i class="bi bi-clipboard-plus"></i></span>
            <div>
                <div class="dotacion-eyebrow">Asignación de carga horaria</div>
                <h2 class="h5 fw-bold mb-1">Asignar horas a docentes, cupos por contratar o asistentes</h2>
                <div class="text-muted small">Asocia docentes vigentes o cupos provisionales a las horas de cada bloque. Los cupos por contratar muestran su saldo asignable sin incorporarse a la base contractual vigente.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger rounded-4">
                <div class="fw-semibold mb-1">No fue posible completar la acción</div>
                <ul class="mb-0 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('success'))
            <div class="alert alert-success rounded-4">{{ session('success') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info rounded-4">{{ session('info') }}</div>
        @endif
        @if (($proceso2027Asignacion['aplica'] ?? false) && !$asignacion2027Habilitada)
            <div class="alert alert-warning rounded-4"><i class="bi bi-lock"></i> La asignación 2027 está bloqueada hasta completar planes, asociar docentes a cada asignatura, declarar combinación de cursos, definir las funciones normativas y configurar máximos suficientes. Revise el proceso guiado superior.</div>
        @endif
        <div class="alert alert-info rounded-4 small">
            <strong>Regla NT1/NT2:</strong> la necesidad contractual del plan se distribuye proporcionalmente por asignatura. Con JEC: 55 h por curso o grupo; sin JEC: NT1 35 h, NT2 31 h y NT1 + NT2 combinados 35 h. En nuevas asignaciones individuales de una Educadora con JEC, el contrato de aula se obtiene de la tabla CPEIP 65/35. PIE se asigna aparte (3 h cuando corresponda). Sin JEC solo se admite cobertura por Educadoras de Párvulos. La libre disposición impartida por otro docente con JEC se contabiliza en Plan General, una vez por grupo combinado.
            <span class="d-block mt-1">Para asignaciones nuevas de una Educadora en NT1/NT2 con JEC se aplica la tabla CPEIP 65/35: como máximo 35 h pedagógicas de aula (26 h 15 min cronológicas) equivalen a 41 h de contrato de aula. En una jornada de 44 h, las otras 3 h corresponden a trabajo colaborativo PIE. La necesidad contractual del curso o grupo permanece en 55 h más 3 h PIE cuando corresponda.</span>
            <span class="d-block mt-1">Las asignaciones históricas conservan sus valores guardados hasta que se revisen y actualicen o se ejecute un recálculo explícito. La nueva necesidad no modifica contratos del padrón.</span>
        </div>
        <div class="row g-3">
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas aula plan</div><div class="h4 fw-bold mb-0">{{ $fmt($resumenAsignacion['horas_aula_requeridas'] ?? 0) }}</div><div class="small text-muted">Asignaturas</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Aula asignada</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($resumenAsignacion['horas_aula_asignadas'] ?? 0) }}</div><div class="small text-muted">Valor real asignado</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Saldo aula</div><div class="h4 fw-bold text-warning mb-0">{{ $fmt($resumenAsignacion['horas_aula_pendientes'] ?? 0) }}</div><div class="small text-muted">Por asignar</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Aula excedida</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($resumenAsignacion['horas_aula_excedidas'] ?? 0) }}</div><div class="small text-muted">Sobre horas del plan</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes sobrecarga</div><div class="h4 fw-bold text-danger mb-0">{{ $resumenAsignacion['docentes_sobrecarga'] ?? 0 }}</div><div class="small text-muted">Asignación &gt; contrato</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes disponibles</div><div class="h4 fw-bold text-success mb-0">{{ $resumenAsignacion['docentes_disponibles'] ?? 0 }}</div><div class="small text-muted">Con saldo</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Asistentes asignados</div><div class="h4 fw-bold text-info mb-0">{{ $resumenAsignacion['asistentes_asignados'] ?? 0 }}</div><div class="small text-muted">Cobertura AAEE</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Contrato AAEE</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($resumenAsignacion['horas_contrato_asistentes'] ?? 0) }}</div><div class="small text-muted">Horas asignadas</div></div></div>
        </div>
    </div>
</div>

@include('admin.dotacion-establecimiento.partials._asignaciones_huerfanas', [
    'asignacionesHuerfanas' => $asignacionesHuerfanas,
    'resumenAsignacion' => $resumenAsignacion,
    'fmt' => $fmt,
])

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="dotacion-eyebrow">Resumen por subvención</div>
            <h2 class="h5 fw-bold mb-1">Horas registradas por subvención</h2>
            <div class="text-muted small">Se muestran separadas las horas aula de asignaturas y las horas contrato de funciones, sin mezclar ambas unidades.</div>
        </div>
        <span class="badge rounded-pill text-bg-light border">{{ $asignaciones->count() }} asignación(es)</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse ($subvenciones as $row)
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="p-3 rounded-4 border h-100">
                        <div class="small text-muted mb-1">{{ $row['subvencion'] }}</div>
                        <div class="d-flex justify-content-between"><span class="small">Horas aula</span><strong class="text-primary">{{ $fmt($row['horas_aula'] ?? 0) }}</strong></div>
                        @if (($row['horas_aula_acompanamiento'] ?? 0) > 0)
                            <div class="d-flex justify-content-between"><span class="small">Aula acompañamiento</span><strong class="text-primary">{{ $fmt($row['horas_aula_acompanamiento']) }}</strong></div>
                            <div class="d-flex justify-content-between"><span class="small">Contrato acompañamiento</span><strong>{{ $fmt($row['horas_contrato_acompanamiento'] ?? 0) }}</strong></div>
                        @endif
                        <div class="d-flex justify-content-between"><span class="small">Contrato funciones</span><strong>{{ $fmt($row['horas_contrato_funciones'] ?? 0) }}</strong></div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-muted small">Aún no existen horas asignadas por subvención.</div>
            @endforelse
        </div>
    </div>
</div>

@php
    $groups = [
        'plan_estudio' => ['title' => 'Plan de estudios y libre disposición', 'help' => 'Asignación individual por asignatura, agrupada por curso y por bloque del plan. Permite dividir una misma asignatura entre varios docentes.', 'icon' => 'bi-journal-text'],
        'pie_colaborativo' => ['title' => 'Trabajo colaborativo PIE por curso o grupo combinado', 'help' => '3 horas por curso independiente o por grupo combinado con estudiantes NEE. No permite asignar a Educadora Diferencial ni Coordinador/a PIE.', 'icon' => 'bi-people'],
        'pie_educadora_diferencial' => ['title' => 'Bolsa Educadoras Diferenciales PIE', 'help' => 'Horas de contrato calculadas desde PROF EDUC. DIF según la proporción 65/35 o 60/40 de cada curso; el total exacto se redondea una sola vez hacia arriba.', 'icon' => 'bi-universal-access'],
        'funciones' => ['title' => 'Funciones directivas, técnico-pedagógicas, planes y otras funciones', 'help' => 'Horas de contrato provenientes de Dotación funciones y planes.', 'icon' => 'bi-diagram-3'],
    ];
@endphp

@if ($proceso2027Asignacion['aplica'] ?? false)
    @include('admin.dotacion-establecimiento.partials._reserva_no_normativa')
@endif

@foreach ($groups as $groupKey => $meta)
    @php
        $items = collect($necesidades[$groupKey] ?? []);
        $isPlanGroup = $groupKey === 'plan_estudio';
        $totalReq = $items->sum(fn ($item) => (float) ($isPlanGroup ? ($item['horas_plan_requeridas'] ?? 0) : ($item['horas_contrato_requeridas'] ?? 0)));
        $totalAsig = $items->sum(fn ($item) => (float) ($isPlanGroup ? ($item['horas_plan_asignadas'] ?? 0) : ($item['horas_contrato_asignadas'] ?? 0)));
        $groupUnit = $isPlanGroup ? ' aula' : ' contrato';
        $groupCollapseId = 'dotacion-asignacion-bloque-'.$groupKey;
    @endphp
    <div class="card dotacion-section mb-4">
        <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div class="d-flex align-items-start gap-3">
                <span class="dotacion-icon" style="width:38px;height:38px;background:#0d6efd;"><i class="bi {{ $meta['icon'] }}"></i></span>
                <div>
                    <div class="dotacion-eyebrow">Bloque de asignación</div>
                    <h2 class="h5 fw-bold mb-1">{{ $meta['title'] }}</h2>
                    <div class="text-muted small">{{ $meta['help'] }}</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-2 flex-wrap">
                <div class="text-end small">
                    <div><span class="text-muted">Requeridas{{ $groupUnit }}:</span> <strong>{{ $fmt($totalReq) }}</strong></div>
                    <div><span class="text-muted">Asignadas{{ $groupUnit }}:</span> <strong>{{ $fmt($totalAsig) }}</strong></div>
                </div>
                <button class="btn btn-sm btn-outline-primary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $groupCollapseId }}" aria-expanded="true" aria-controls="{{ $groupCollapseId }}">
                    <i class="bi bi-chevron-down"></i> Mostrar / ocultar
                </button>
            </div>
        </div>

        <div class="collapse show" id="{{ $groupCollapseId }}">
        @if ($groupKey === 'plan_estudio')
            <div class="card-body pt-3">
                @forelse ($items->groupBy(fn ($item) => $item['curso_label'] ?? 'Curso sin identificar') as $cursoLabel => $cursoItems)
                    @php
                        $cursoAula = $cursoItems->sum(fn ($item) => (float) ($item['horas_plan_requeridas'] ?? 0));
                        $cursoAsig = $cursoItems->sum(fn ($item) => (float) ($item['horas_plan_asignadas'] ?? 0));
                        $cursoSaldo = max(0, round($cursoAula - $cursoAsig, 2));
                        $cursoCollapseId = $groupCollapseId.'-curso-'.$loop->iteration;
                        $asignacionesCurso = \App\Support\DotacionAsignacionPorCursoBloque::asignaciones($cursoItems);
                    @endphp
                    <div class="border rounded-4 mb-3 overflow-hidden">
                        <div class="bg-light px-3 py-3 d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <div class="dotacion-eyebrow">Curso / sección</div>
                                <div class="fw-bold fs-6">{{ $cursoLabel }}</div>
                                <div class="small text-muted">Asignaturas del tiempo mínimo obligatorio y libre disposición configurada del curso.</div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap small align-items-center">
                                <span class="badge rounded-pill text-bg-light border">Horas aula: {{ $fmt($cursoAula) }}</span>
                                <span class="badge rounded-pill text-bg-primary">Aula asignada: {{ $fmt($cursoAsig) }}</span>
                                <span class="badge rounded-pill {{ $cursoSaldo > 0.01 ? 'text-bg-warning' : 'text-bg-success' }}">Saldo aula: {{ $fmt($cursoSaldo) }}</span>
                                @if ($asignacionesCurso->isNotEmpty())
                                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.curso-bloque.destroy', $establecimiento) }}" data-confirm="¿Eliminar todas las {{ $asignacionesCurso->count() }} asignaciones del plan de estudio en {{ $cursoLabel }}? Esta acción no se puede deshacer." onsubmit="return confirm(this.dataset.confirm);">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="anio" value="{{ $anio }}">
                                        <input type="hidden" name="grupo" value="plan_estudio">
                                        <input type="hidden" name="curso_label" value="{{ $cursoLabel }}">
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar todas del curso ({{ $asignacionesCurso->count() }})</button>
                                    </form>
                                @endif
                                <button class="btn btn-sm btn-outline-primary rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $cursoCollapseId }}" aria-expanded="false" aria-controls="{{ $cursoCollapseId }}">
                                    <i class="bi bi-chevron-down"></i> Ver asignaturas
                                </button>
                            </div>
                        </div>
                        <div class="collapse" id="{{ $cursoCollapseId }}">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asignatura</th>
                                        <th>Bloque / origen</th>
                                        <th class="text-end">Horas aula</th>
                                        <th class="text-end">Aula asignada</th>
                                        <th class="text-end">Saldo aula</th>
                                        <th>Estado</th>
                                        <th style="min-width:320px;">Asignar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $lastBloque = null; @endphp
                                    @foreach ($cursoItems as $item)
                                        @php
                                            $estado = $item['estado'] ?? ['class' => 'text-bg-secondary', 'label' => 'Pendiente'];
                                            $pendingPlan = $item['horas_plan_pendientes'] ?? $item['horas_plan_requeridas'] ?? null;
                                            $bloqueActual = $item['bloque'] ?? 'Sin bloque';
                                            $cursoNt = $item['curso'] ?? null;
                                            $soloParvularia = $cursoNt instanceof \App\Models\EstablecimientoCurso
                                                && \App\Support\DotacionProfesionDocenteResolver::esCursoNt($cursoNt)
                                                && ! \App\Support\DotacionParvulariaCalculator::conJec($cursoNt, $item['proporcion_key'] ?? null);
                                            $subsectorKey = \App\Support\DotacionDocentesSubsector::keyParaNecesidad($item);
                                            $docentesPermitidosSubsector = $anio === 2027
                                                ? ($subsectoresAsignacion->get($subsectorKey)['docentes'] ?? [])
                                                : null;
                                            $docentesPlanElegibles = $anio === 2027
                                                ? \App\Support\DotacionPlanTitularPrimero::elegibles(
                                                    collect($proceso2027Asignacion['docentes'] ?? []), $docentesPermitidosSubsector ?? [], $item
                                                )
                                                : collect();
                                            $fasePlan = $anio === 2027
                                                ? \App\Support\DotacionPlanTitularPrimero::fase($docentesPlanElegibles)
                                                : null;
                                            $rutsFasePlan = $fasePlan !== null
                                                ? \App\Support\DotacionPlanTitularPrimero::opciones($docentesPlanElegibles)->pluck('rut_normalizado')->all()
                                                : [];
                                        @endphp
                                        @if ($lastBloque !== $bloqueActual)
                                            @php
                                                $asignacionesCursoBloque = \App\Support\DotacionAsignacionPorCursoBloque::asignaciones(
                                                    $cursoItems->filter(fn ($fila) => ($fila['bloque'] ?? 'Sin bloque') === $bloqueActual)
                                                );
                                            @endphp
                                            <tr class="table-secondary">
                                                <td colspan="7">
                                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                        <span class="fw-semibold small text-uppercase">{{ $bloqueActual }}</span>
                                                        @if ($asignacionesCursoBloque->isNotEmpty())
                                                            <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.curso-bloque.destroy', $establecimiento) }}" data-confirm="¿Eliminar las {{ $asignacionesCursoBloque->count() }} asignaciones del bloque {{ $bloqueActual }} en {{ $cursoLabel }}? Esta acción no se puede deshacer." onsubmit="return confirm(this.dataset.confirm);">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="anio" value="{{ $anio }}">
                                                                <input type="hidden" name="grupo" value="plan_estudio">
                                                                <input type="hidden" name="curso_label" value="{{ $cursoLabel }}">
                                                                <input type="hidden" name="bloque" value="{{ $bloqueActual }}">
                                                                <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar asignaciones del bloque ({{ $asignacionesCursoBloque->count() }})</button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @php $lastBloque = $bloqueActual; @endphp
                                        @endif
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $item['titulo'] ?? 'Asignatura' }}</div>
                                                @if (!empty($item['curso_combinado']))
                                                    <span class="badge rounded-pill text-bg-primary">Curso combinado</span>
                                                    <div class="small text-muted mt-1">Cubre: {{ collect($item['curso_combinado_cursos'] ?? [])->implode(' + ') }} · Modalidad {{ ucfirst($item['curso_combinado_modalidad'] ?? 'conjunta') }}</div>
                                                @endif
                                                @if (($item['subtipo_asignacion'] ?? null) === 'libre_disposicion')
                                                    <div class="small text-muted">Libre disposición asignable</div>
                                                @endif
                                                @if (!empty($item['nombre_personalizado']))
                                                    <span class="badge rounded-pill text-bg-info">Nombre personalizado</span>
                                                @endif
                                                @if (!empty($item['plan_comun_asociado']))
                                                    <div class="small text-muted">Plan común asociado: {{ $item['plan_comun_asociado'] }}</div>
                                                @endif
                                                @if (!empty($item['asignatura_oficial']) && ($item['asignatura_oficial'] !== ($item['titulo'] ?? null)))
                                                    <div class="small text-muted">Asignatura oficial: {{ $item['asignatura_oficial'] }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small text-muted">{{ $item['fuente'] ?? '' }}</div>
                                                @if (!empty($item['proporcion']))<span class="badge rounded-pill text-bg-light border">{{ $item['proporcion'] }}</span>@endif
                                                @if (!empty($item['origen_proporcion_label']))<div class="small text-muted mt-1">{{ $item['origen_proporcion_label'] }}</div>@endif
                                                @if ($cursoNt instanceof \App\Models\EstablecimientoCurso && \App\Support\DotacionProfesionDocenteResolver::esCursoNt($cursoNt))
                                                    <div class="small text-muted">Contrato de referencia: {{ $fmt($item['horas_contrato_requeridas'] ?? 0) }} h para cubrir esta parte del plan.</div>
                                                @endif
                                            </td>
                                            <td class="text-end fw-bold">{{ $item['horas_plan_requeridas'] !== null ? $fmt($item['horas_plan_requeridas']) : '—' }}</td>
                                            <td class="text-end text-primary fw-semibold">{{ $fmt($item['horas_plan_asignadas'] ?? 0) }}</td>
                                            <td class="text-end {{ ($pendingPlan ?? 0) > 0.01 ? 'text-warning' : 'text-success' }} fw-semibold">{{ $fmt($pendingPlan) }}</td>
                                            <td><span class="badge rounded-pill {{ $estado['class'] ?? 'text-bg-secondary' }}">{{ $estado['label'] ?? 'Pendiente' }}</span></td>
                                            <td>
                                                <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.store', $establecimiento) }}" class="vstack gap-2 dotacion-assignment-form" data-dotacion-asignacion-form>
                                                    @csrf
                                                    <input type="hidden" name="anio" value="{{ $anio }}">
                                                    <input type="hidden" name="tipo_asignacion" value="{{ $item['tipo_asignacion'] }}">
                                                    <input type="hidden" name="subtipo_asignacion" value="{{ $item['subtipo_asignacion'] }}">
                                                    <input type="hidden" name="necesidad_key" value="{{ $item['key'] }}">
                                                    <input type="hidden" name="establecimiento_curso_id" value="{{ $item['establecimiento_curso_id'] ?? '' }}">
                                                    <input type="hidden" name="dotacion_curso_combinado_id" value="{{ $item['dotacion_curso_combinado_id'] ?? '' }}">
                                                    <input type="hidden" name="dotacion_curso_combinado_asignatura_id" value="{{ $item['dotacion_curso_combinado_asignatura_id'] ?? '' }}">
                                                    <input type="hidden" name="plan_estudio_id" value="{{ $item['plan_estudio_id'] ?? '' }}">
                                                    <input type="hidden" name="plan_bloque_id" value="{{ $item['plan_bloque_id'] ?? '' }}">
                                                    <input type="hidden" name="asignatura_id" value="{{ $item['asignatura_id'] ?? '' }}">
                                                    <input type="hidden" name="asignatura_nombre" value="{{ $item['asignatura_nombre'] ?? $item['titulo'] }}">
                                                    <input type="hidden" name="dotacion_funcion_id" value="{{ $item['dotacion_funcion_id'] ?? '' }}">
                                                    <input type="hidden" name="dotacion_funcion_regla_id" value="{{ $item['dotacion_funcion_regla_id'] ?? '' }}">
                                                    @if ($proceso2027Asignacion['aplica'] ?? false)
                                                        <div class="dotacion-selector-guide"><i class="bi bi-sort-numeric-down"></i><span><strong>{{ $fasePlan === 'titular' ? 'Primero, horas titulares.' : 'Sin saldo titular asignable: sigue contrata.' }}</strong> Solo se muestran docentes asociados a esta asignatura con al menos 1 h {{ $fasePlan === 'titular' ? 'titular' : 'a contrata' }} disponible, en orden de prelación. Los saldos menores a 1 h se omiten. Si el saldo de un docente no cubre todas las horas pendientes, asigne primero una fracción del aula.</span></div>
                                                    @endif
                                                    <label class="form-label small mb-0" for="estamento-plan-{{ $cursoCollapseId }}-{{ $loop->iteration }}">Tipo de cobertura</label>
                                                    <select id="estamento-plan-{{ $cursoCollapseId }}-{{ $loop->iteration }}" name="estamento_cobertura" class="form-select form-select-sm js-estamento-cobertura" required>
                                                        <option value="docente">Cubierto por docente</option>
                                                        @unless ($soloParvularia || $fasePlan === 'titular')<option value="asistente">Cubierto por Asistente de la Educación</option>@endunless
                                                    </select>
                                                    <label class="form-label small mb-0" for="docente-plan-{{ $cursoCollapseId }}-{{ $loop->iteration }}">Docente asociado o asistente</label>
                                                    <select id="docente-plan-{{ $cursoCollapseId }}-{{ $loop->iteration }}" name="docente_rut" class="form-select form-select-sm js-personal-cobertura js-dotacion-docente-select" data-placeholder="Buscar por nombre, RUT o título..." data-fase-plan="{{ $fasePlan }}" required>
                                                        <option value="">Seleccione persona...</option>
                                                        <optgroup label="{{ $fasePlan === 'titular' ? 'Docentes con horas titulares disponibles' : ($fasePlan === 'contrata' ? 'Docentes con horas a contrata disponibles' : 'Docentes vigentes y por contratar') }}">
                                                            @foreach ($docenteOptions as $doc)
                                                                @continue($docentesPermitidosSubsector !== null && !in_array($doc['rut_normalizado'], $docentesPermitidosSubsector, true))
                                                                @continue($fasePlan !== null && !in_array($doc['rut_normalizado'], $rutsFasePlan, true))
                                                                @continue($doc['virtual'] && ($doc['cupo_bloque'] !== 'parvularia' || ! (($cursoNt instanceof \App\Models\EstablecimientoCurso && \App\Support\DotacionProfesionDocenteResolver::esCursoNt($cursoNt)) || data_get($proceso2027Asignacion, 'need_blocks.'.($item['key'] ?? '')) === 'bloque_2')))
                                                                @continue($soloParvularia && !$doc['es_parvularia'])
                                                                <option value="{{ $doc['rut'] }}" data-estamento="docente" data-fase-plan="{{ $fasePlan }}" data-nombre="{{ $doc['nombre'] }}" data-rut="{{ $doc['rut'] }}" data-titulo="{{ $doc['titulo'] }}" data-funcion="{{ $doc['funcion'] }}" data-prioridad="{{ $doc['prioridad'] }}" data-prioridad-label="{{ $doc['prioridad_label'] }}" data-antiguedad="{{ $doc['antiguedad'] }}" data-titular-disponible="{{ $fmt($doc['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($doc['contrata_disponible']) }}">{{ $fasePlan === null ? $doc['label'] : $doc['nombre'].' · '.$doc['rut'].' · Título: '.$doc['titulo'].' · '.$doc['prioridad_label'].' · Disponible '.($fasePlan === 'titular' ? 'titular: '.$fmt($doc['titular_disponible']) : 'a contrata: '.$fmt($doc['contrata_disponible'])).' h' }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                        @unless ($fasePlan === 'titular')
                                                            <optgroup label="Asistentes de la Educación">
                                                                @foreach ($asistenteOptions as $asistente)
                                                                    @continue($soloParvularia)
                                                                    <option value="{{ $asistente['rut'] }}" data-estamento="asistente" data-nombre="{{ $asistente['nombre'] }}" data-rut="{{ $asistente['rut'] }}" data-funcion="{{ $asistente['funcion'] }}" data-titular-disponible="{{ $fmt($asistente['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($asistente['contrata_disponible']) }}">{{ $asistente['label'] }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endunless
                                                    </select>
                                                    @if ($fasePlan !== null && $rutsFasePlan === [])
                                                        <div class="form-text text-warning-emphasis">No hay docentes asociados con saldo {{ $fasePlan === 'titular' ? 'titular' : 'a contrata' }} para esta asignatura.</div>
                                                    @endif
                                                    <div class="row g-2">
                                                        <div class="col-md-4">
                                                            <input type="number" name="horas_plan_pedagogicas" step="{{ $fasePlan !== null ? '0.01' : '0.25' }}" min="{{ $fasePlan !== null ? '0.01' : '0.25' }}" class="form-control form-control-sm js-horas-aula" value="{{ $pendingPlan !== null && $pendingPlan > 0 ? $pendingPlan : ($item['horas_plan_requeridas'] ?? 0) }}" placeholder="Horas aula">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <input type="number" name="horas_contrato" step="0.25" min="0.25" class="form-control form-control-sm js-horas-contrato-aaee" placeholder="Contrato AAEE" disabled>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="visually-hidden" for="subvencion-plan-{{ $cursoCollapseId }}-{{ $loop->iteration }}">Subvención del plan de estudio</label>
                                                            <select id="subvencion-plan-{{ $cursoCollapseId }}-{{ $loop->iteration }}" class="form-select form-select-sm" disabled>
                                                                <option value="General" selected>General</option>
                                                            </select>
                                                            <input type="hidden" name="subvencion" value="General">
                                                        </div>
                                                    </div>
                                                    <div class="form-text js-ayuda-aaee d-none">Para asistentes, ingrese las horas aula cubiertas y las horas de contrato AAEE. No se aplica conversión 65/35 ni 60/40.</div>
                                                    <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Observación opcional">
                                                    <button class="btn btn-sm btn-primary rounded-pill" type="submit" @disabled(!$asignacion2027Habilitada)><i class="bi bi-plus-circle"></i> Asignar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        @if (($cursoNt instanceof \App\Models\EstablecimientoCurso)
                                            && \App\Support\DotacionProfesionDocenteResolver::esCursoNt($cursoNt)
                                            && \App\Support\DotacionParvulariaCalculator::conJec($cursoNt, $item['proporcion_key'] ?? null)
                                            && (($item['subtipo_asignacion'] ?? '') === 'libre_disposicion' || ($item['curso_combinado_libre_disposicion'] ?? false))
                                            && ($item['horas_externas_libre_disposicion'] ?? 0) > 0)
                                            <tr>
                                                <td colspan="7" class="bg-light">
                                                    <div class="fw-semibold small mb-1">Acompañamiento de Educadora de Párvulos</div>
                                                    <div class="small text-muted mb-2">Otro docente imparte {{ $fmt($item['horas_externas_libre_disposicion']) }} h de libre disposición. Puede asignar hasta {{ $fmt($item['horas_acompanamiento_disponibles'] ?? 0) }} h adicionales a la Educadora que permanece en aula. Estas horas cuentan en su contrato, sin duplicar la cobertura del plan.</div>
                                                    @if (($item['horas_acompanamiento_disponibles'] ?? 0) > 0.01)
                                                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.store', $establecimiento) }}" class="vstack gap-2 dotacion-assignment-form">
                                                            @csrf
                                                            <input type="hidden" name="anio" value="{{ $anio }}">
                                                            <input type="hidden" name="tipo_asignacion" value="acompanamiento_parvularia">
                                                            <input type="hidden" name="estamento_cobertura" value="docente">
                                                            <input type="hidden" name="necesidad_key" value="{{ $item['key'] }}">
                                                            <label class="form-label small mb-1" for="acompanamiento-docente-{{ $cursoCollapseId }}-{{ $loop->iteration }}">Educadora de Párvulos</label>
                                                            <select id="acompanamiento-docente-{{ $cursoCollapseId }}-{{ $loop->iteration }}" name="docente_rut" class="form-select form-select-sm js-dotacion-docente-select" data-placeholder="Buscar Educadora de Párvulos..." required>
                                                                <option value="">Seleccione Educadora de Párvulos...</option>
                                                                @foreach ($docenteOptions as $doc)
                                                                    @continue($docentesPermitidosSubsector !== null && !in_array($doc['rut_normalizado'], $docentesPermitidosSubsector, true))
                                                                    @continue(! $doc['es_parvularia'] || ($doc['virtual'] && $doc['cupo_bloque'] !== 'parvularia'))
                                                                    <option value="{{ $doc['rut'] }}" data-estamento="docente" data-nombre="{{ $doc['nombre'] }}" data-rut="{{ $doc['rut'] }}" data-titulo="{{ $doc['titulo'] }}" data-prioridad="{{ $doc['prioridad'] }}" data-prioridad-label="{{ $doc['prioridad_label'] }}" data-antiguedad="{{ $doc['antiguedad'] }}" data-titular-disponible="{{ $fmt($doc['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($doc['contrata_disponible']) }}">{{ $doc['label'] }}</option>
                                                                @endforeach
                                                            </select>
                                                            <div class="row g-2 align-items-end">
                                                                <div class="col-md-4">
                                                                    <label class="form-label small mb-1" for="acompanamiento-horas-{{ $cursoCollapseId }}-{{ $loop->iteration }}">Horas aula de acompañamiento</label>
                                                                    <input id="acompanamiento-horas-{{ $cursoCollapseId }}-{{ $loop->iteration }}" type="number" name="horas_plan_pedagogicas" step="0.25" min="0.25" max="{{ $item['horas_acompanamiento_disponibles'] }}" value="{{ $item['horas_acompanamiento_disponibles'] }}" class="form-control form-control-sm" required>
                                                                </div>
                                                                <div class="col-md-8">
                                                                    <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit" @disabled(!$asignacion2027Habilitada)><i class="bi bi-plus-circle"></i> Asignar acompañamiento</button>
                                                                </div>
                                                            </div>
                                                            <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Observación opcional">
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                        @if (count($item['asignaciones'] ?? []) + count($item['acompanamientos'] ?? []) > 0)
                                            <tr>
                                                <td colspan="7" class="bg-light">
                                                    <div class="small fw-semibold mb-2">Asignaciones registradas para esta asignatura</div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-0">
                                                            <thead><tr><th>Personal</th><th>Estamento</th><th>Subvención</th><th class="text-end">Horas aula asignadas</th><th class="text-end">Contrato asignado</th><th>Obs.</th><th></th></tr></thead>
                                                            <tbody>
                                                                @foreach (collect($item['asignaciones'] ?? [])->concat($item['acompanamientos'] ?? []) as $asig)
                                                                    <tr>
                                                                        <td>{{ $asig->docente_nombre }}<div class="text-muted small">{{ $asig->docente_rut }}</div>@if($asig->tipo_asignacion === 'acompanamiento_parvularia')<span class="badge rounded-pill text-bg-info">Acompañamiento en aula</span>@elseif($asig->tipo_asignacion === 'plan_estudio')<div class="small text-primary">{{ $asig->proporcion_aplicada ?: 'Regla no informada' }}</div>@endif</td>
                                                                        <td><span class="badge rounded-pill {{ ($asig->estamento_cobertura ?? 'docente') === 'asistente' ? 'text-bg-info' : 'text-bg-primary' }}">{{ ($asig->estamento_cobertura ?? 'docente') === 'asistente' ? 'Asistente' : 'Docente' }}</span></td>
                                                                        <td>{{ $asig->subvencion }}</td>
                                                                        <td class="text-end fw-semibold text-primary">{{ $asig->horas_plan_pedagogicas !== null ? $fmt($asig->horas_plan_pedagogicas) : '—' }}</td>
                                                                        <td class="text-end">{{ $fmt($asig->horas_contrato) }}</td>
                                                                        <td>{{ $asig->observacion }}</td>
                                                                        <td class="text-end">
                                                                            <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.destroy', [$establecimiento, $asig]) }}" onsubmit="return confirm('¿Eliminar esta asignación?');">
                                                                                @csrf
                                                                                @method('DELETE')
                                                                                <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><i class="bi bi-trash"></i></button>
                                                                            </form>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    <tr class="table-primary">
                                        <td colspan="2" class="fw-bold">Total curso asignable</td>
                                        <td class="text-end fw-bold">{{ $fmt($cursoAula) }}</td>
                                        <td class="text-end fw-bold text-primary">{{ $fmt($cursoAsig) }}</td>
                                        <td class="text-end fw-bold {{ $cursoSaldo > 0.01 ? 'text-warning' : 'text-success' }}">{{ $fmt($cursoSaldo) }}</td>
                                        <td colspan="2" class="small text-muted">Las horas de contrato se calculan consolidadas por docente y proporción en la pestaña Docentes.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No existen necesidades calculadas para plan de estudios.</div>
                @endforelse
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Necesidad</th>
                            <th>Curso / bloque</th>
                            <th class="text-end">Horas plan</th>
                            <th class="text-end">Contrato req.</th>
                            <th class="text-end">Asignado</th>
                            <th class="text-end">Saldo</th>
                            <th>Estado</th>
                            <th style="min-width:320px;">Asignar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $estado = $item['estado'] ?? ['class' => 'text-bg-secondary', 'label' => 'Pendiente'];
                                $pendingContrato = $item['horas_contrato_pendientes'] ?? $item['horas_contrato_requeridas'] ?? 0;
                                $asignadoContrato = $item['horas_contrato_asignadas_calculo'] ?? $item['horas_contrato_asignadas'] ?? 0;
                                $asignacionAutomatica = (bool) ($item['asignacion_automatica'] ?? false);
                                $esNormativa2027 = ($proceso2027Asignacion['aplica'] ?? false)
                                    && ($item['necesidad_condicionada_por_asignacion_docente'] ?? false);
                                $cursoItem = $item['curso'] ?? null;
                                $cuposPermitidos = match (true) {
                                    $groupKey === 'pie_educadora_diferencial' => ['pie'],
                                    $groupKey === 'pie_colaborativo' && (($cursoItem instanceof \App\Models\EstablecimientoCurso && \App\Support\DotacionProfesionDocenteResolver::esCursoNt($cursoItem)) || data_get($proceso2027Asignacion, 'need_blocks.'.($item['key'] ?? '')) === 'bloque_2') => ['parvularia'],
                                    ($item['subtipo_asignacion'] ?? null) === 'pie' => ['pie'],
                                    ($item['tipo_asignacion'] ?? null) === 'otra_funcion' || (int) ($item['dotacion_funcion_id'] ?? 0) > 0 => ['parvularia', 'pie'],
                                    default => [],
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item['titulo'] ?? 'Necesidad' }}</div>
                                    <div class="small text-muted">{{ $item['fuente'] ?? '' }}</div>
                                    @if (($item['necesidad_condicionada_por_asignacion_docente'] ?? false) && ! ($item['necesidad_activada_por_docente'] ?? false))
                                        <div class="small text-warning-emphasis mt-1">No se contabiliza como necesidad hasta asignar un docente.</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $item['curso_label'] ?? 'Establecimiento' }}</div>
                                    @if (!empty($item['bloque']))<div class="small text-muted">{{ $item['bloque'] }}</div>@endif
                                    @if (!empty($item['proporcion']))<span class="badge rounded-pill text-bg-light border">{{ $item['proporcion'] }}</span>@endif
                                                @if (!empty($item['origen_proporcion_label']))<div class="small text-muted mt-1">{{ $item['origen_proporcion_label'] }}</div>@endif
                                    @if ($groupKey === 'pie_colaborativo' && count($item['asignaciones'] ?? []) > 0)
                                        @php
                                            $asignacionesCursoPie = \App\Support\DotacionAsignacionPorCursoBloque::asignaciones(
                                                $items->filter(fn ($fila) => ($fila['curso_label'] ?? 'Curso sin identificar') === ($item['curso_label'] ?? 'Curso sin identificar'))
                                            );
                                        @endphp
                                        @if ($asignacionesCursoPie->isNotEmpty())
                                            <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.curso-bloque.destroy', $establecimiento) }}" class="mt-2" data-confirm="¿Eliminar las {{ $asignacionesCursoPie->count() }} asignaciones de trabajo colaborativo PIE en {{ $item['curso_label'] ?? 'este curso' }}? Esta acción no se puede deshacer." onsubmit="return confirm(this.dataset.confirm);">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="anio" value="{{ $anio }}">
                                                <input type="hidden" name="grupo" value="pie_colaborativo">
                                                <input type="hidden" name="curso_label" value="{{ $item['curso_label'] ?? 'Curso sin identificar' }}">
                                                <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar asignaciones del curso ({{ $asignacionesCursoPie->count() }})</button>
                                            </form>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end">{{ $item['horas_plan_requeridas'] !== null ? $fmt($item['horas_plan_requeridas']) : '—' }}</td>
                                <td class="text-end fw-bold">{{ $fmt($item['horas_contrato_requeridas'] ?? 0) }}</td>
                                <td class="text-end text-primary fw-semibold">{{ $fmt($asignadoContrato) }}</td>
                                <td class="text-end {{ ($pendingContrato ?? 0) > 0.01 ? 'text-warning' : 'text-success' }} fw-semibold">{{ $fmt($pendingContrato) }}</td>
                                <td><span class="badge rounded-pill {{ $estado['class'] ?? 'text-bg-secondary' }}">{{ $estado['label'] ?? 'Pendiente' }}</span></td>
                                <td>
                                    @if ($asignacionAutomatica)
                                        <div class="alert alert-primary small mb-0">
                                            <div class="fw-semibold">Asignación automática</div>
                                            <div>Docente Directivo por asumir · {{ $fmt($item['horas_contrato_asignadas'] ?? 0) }} hrs contrato.</div>
                                            <div class="mt-1">La plaza activa {{ $fmt($item['horas_contrato_requeridas'] ?? 44) }} horas definidas como necesidad hasta asignar al docente directivo.</div>
                                        </div>
                                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.store', $establecimiento) }}" class="vstack gap-2 mt-2 dotacion-assignment-form">
                                            @csrf
                                            <input type="hidden" name="anio" value="{{ $anio }}">
                                            <input type="hidden" name="tipo_asignacion" value="funcion_directiva">
                                            <input type="hidden" name="subtipo_asignacion" value="directiva">
                                            <input type="hidden" name="necesidad_key" value="{{ $item['key'] }}">
                                            <input type="hidden" name="asignatura_nombre" value="Director(a) ADP">
                                            <input type="hidden" name="dotacion_funcion_regla_id" value="{{ $item['dotacion_funcion_regla_id'] }}">
                                            <input type="hidden" name="estamento_cobertura" value="docente">
                                            <input type="hidden" name="subvencion" value="General">
                                            <input type="hidden" name="horas_contrato" value="{{ $item['horas_contrato_requeridas'] ?? 44 }}">
                                            <label class="form-label small mb-0" for="director_adp_docente_{{ $item['dotacion_funcion_regla_id'] }}">Docente directivo</label>
                                            <div class="dotacion-selector-guide"><i class="bi bi-search"></i><span>Busque por nombre o RUT. La lista muestra la prelación 2027 y las horas disponibles antes de confirmar la asignación.</span></div>
                                            <select id="director_adp_docente_{{ $item['dotacion_funcion_regla_id'] }}" name="docente_rut" class="form-select form-select-sm js-dotacion-docente-select" data-placeholder="Buscar docente por nombre o RUT..." required>
                                                <option value="">Seleccione docente...</option>
                                                @foreach ($docenteOptions as $doc)
                                                    @continue($doc['virtual'])
                                                    <option value="{{ $doc['rut'] }}" data-estamento="docente" data-nombre="{{ $doc['nombre'] }}" data-rut="{{ $doc['rut'] }}" data-titulo="{{ $doc['titulo'] }}" data-funcion="{{ $doc['funcion'] }}" data-prioridad="{{ $doc['prioridad'] }}" data-prioridad-label="{{ $doc['prioridad_label'] }}" data-antiguedad="{{ $doc['antiguedad'] }}" data-titular-disponible="{{ $fmt($doc['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($doc['contrata_disponible']) }}">{{ $doc['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <div class="small text-muted">Contrato definido: {{ $fmt($item['horas_contrato_requeridas'] ?? 44) }} horas.</div>
                                            <button class="btn btn-sm btn-primary rounded-pill" type="submit" @disabled(!$asignacion2027Habilitada)><i class="bi bi-person-check"></i> Asignar docente directivo</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.store', $establecimiento) }}" class="vstack gap-2 dotacion-assignment-form" data-dotacion-asignacion-form>
                                        @csrf
                                        <input type="hidden" name="anio" value="{{ $anio }}">
                                        <input type="hidden" name="tipo_asignacion" value="{{ $item['tipo_asignacion'] }}">
                                        <input type="hidden" name="subtipo_asignacion" value="{{ $item['subtipo_asignacion'] }}">
                                        <input type="hidden" name="necesidad_key" value="{{ $item['key'] }}">
                                        <input type="hidden" name="establecimiento_curso_id" value="{{ $item['establecimiento_curso_id'] ?? '' }}">
                                        <input type="hidden" name="dotacion_curso_combinado_id" value="{{ $item['dotacion_curso_combinado_id'] ?? '' }}">
                                        <input type="hidden" name="dotacion_curso_combinado_asignatura_id" value="{{ $item['dotacion_curso_combinado_asignatura_id'] ?? '' }}">
                                        <input type="hidden" name="plan_estudio_id" value="{{ $item['plan_estudio_id'] ?? '' }}">
                                        <input type="hidden" name="plan_bloque_id" value="{{ $item['plan_bloque_id'] ?? '' }}">
                                        <input type="hidden" name="asignatura_id" value="{{ $item['asignatura_id'] ?? '' }}">
                                        <input type="hidden" name="asignatura_nombre" value="{{ $item['asignatura_nombre'] ?? $item['titulo'] }}">
                                        <input type="hidden" name="dotacion_funcion_id" value="{{ $item['dotacion_funcion_id'] ?? '' }}">
                                        <input type="hidden" name="dotacion_funcion_regla_id" value="{{ $item['dotacion_funcion_regla_id'] ?? '' }}">
                                        @if ($proceso2027Asignacion['aplica'] ?? false)
                                            <div class="dotacion-selector-guide"><i class="bi bi-sort-numeric-down"></i><span><strong>Selección priorizada.</strong> Busque por nombre o RUT; cada grupo titular se ordena por antigüedad y muestra el saldo contractual disponible.</span></div>
                                        @endif
                                        <label class="form-label small mb-0" for="estamento-necesidad-{{ $groupKey }}-{{ $loop->iteration }}">Tipo de cobertura</label>
                                        <select id="estamento-necesidad-{{ $groupKey }}-{{ $loop->iteration }}" name="estamento_cobertura" class="form-select form-select-sm js-estamento-cobertura" required>
                                            <option value="docente">Cubierto por docente</option>
                                            <option value="asistente">Cubierto por Asistente de la Educación</option>
                                        </select>
                                        <label class="form-label small mb-0" for="personal-necesidad-{{ $groupKey }}-{{ $loop->iteration }}">Docente o asistente</label>
                                        <select id="personal-necesidad-{{ $groupKey }}-{{ $loop->iteration }}" name="docente_rut" class="form-select form-select-sm js-personal-cobertura js-dotacion-docente-select" data-placeholder="Buscar por nombre o RUT..." required>
                                            <option value="">Seleccione persona...</option>
                                            <optgroup label="Docentes vigentes y por contratar">
                                                @foreach ($docenteOptions as $doc)
                                                @continue($doc['virtual'] && ! in_array($doc['cupo_bloque'], $cuposPermitidos, true))
                                                <option value="{{ $doc['rut'] }}" data-estamento="docente" data-nombre="{{ $doc['nombre'] }}" data-rut="{{ $doc['rut'] }}" data-titulo="{{ $doc['titulo'] }}" data-funcion="{{ $doc['funcion'] }}" data-prioridad="{{ $doc['prioridad'] }}" data-prioridad-label="{{ $doc['prioridad_label'] }}" data-antiguedad="{{ $doc['antiguedad'] }}" data-titular-disponible="{{ $fmt($doc['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($doc['contrata_disponible']) }}">{{ $doc['label'] }}</option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Asistentes de la Educación">
                                                @foreach ($asistenteOptions as $asistente)
                                                <option value="{{ $asistente['rut'] }}" data-estamento="asistente" data-nombre="{{ $asistente['nombre'] }}" data-rut="{{ $asistente['rut'] }}" data-funcion="{{ $asistente['funcion'] }}" data-titular-disponible="{{ $fmt($asistente['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($asistente['contrata_disponible']) }}">{{ $asistente['label'] }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                        <div class="row g-2">
                                            <div class="col">
                                                <label class="form-label small mb-1" for="contrato-necesidad-{{ $groupKey }}-{{ $loop->iteration }}">Horas de contrato</label>
                                                <input id="contrato-necesidad-{{ $groupKey }}-{{ $loop->iteration }}" type="number" name="horas_contrato" step="{{ $esNormativa2027 ? '0.01' : '0.25' }}" min="{{ $esNormativa2027 ? '0.01' : '0.25' }}" @if ($esNormativa2027) max="{{ $pendingContrato }}" @endif class="form-control form-control-sm" value="{{ $pendingContrato > 0 || $esNormativa2027 ? $pendingContrato : ($item['horas_contrato_requeridas'] ?? 0) }}" placeholder="Horas contrato" @disabled($esNormativa2027 && $pendingContrato <= 0)>
                                            </div>
                                            <div class="col">
                                                <label class="form-label small mb-1" for="subvencion-necesidad-{{ $groupKey }}-{{ $loop->iteration }}">Subvención</label>
                                                <select id="subvencion-necesidad-{{ $groupKey }}-{{ $loop->iteration }}" name="subvencion" class="form-select form-select-sm">
                                                    @foreach ($subvencionesOptions as $subvencion)
                                                        <option value="{{ $subvencion }}" @selected(($item['subvencion'] ?? 'General') === $subvencion)>{{ $subvencion }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Observación opcional">
                                        @if ($esNormativa2027 && $pendingContrato <= 0)
                                            <div class="form-text">No quedan horas de la definición por asignar. Revise las asignaciones registradas para realizar cambios.</div>
                                        @endif
                                        <button class="btn btn-sm btn-primary rounded-pill" type="submit" @disabled(!$asignacion2027Habilitada || ($esNormativa2027 && $pendingContrato <= 0))><i class="bi bi-plus-circle"></i> Asignar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @if (count($item['asignaciones'] ?? []) > 0)
                                <tr>
                                    <td colspan="8" class="bg-light">
                                        <div class="small fw-semibold mb-2">Asignaciones registradas</div>
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead><tr><th>Personal</th><th>Estamento</th><th>Subvención</th><th class="text-end">Horas plan</th><th class="text-end">Horas contrato</th><th>Obs.</th><th></th></tr></thead>
                                                <tbody>
                                                    @foreach ($item['asignaciones'] as $asig)
                                                        <tr>
                                                            <td>{{ $asig->docente_nombre }}<div class="text-muted small">{{ $asig->docente_rut }}</div>@if($asig->tipo_asignacion === 'plan_estudio')<div class="small text-primary">{{ $asig->proporcion_aplicada ?: 'Regla no informada' }}</div>@endif</td>
                                                            <td><span class="badge rounded-pill {{ ($asig->estamento_cobertura ?? 'docente') === 'asistente' ? 'text-bg-info' : 'text-bg-primary' }}">{{ ($asig->estamento_cobertura ?? 'docente') === 'asistente' ? 'Asistente' : 'Docente' }}</span></td>
                                                            <td>{{ $asig->subvencion }}</td>
                                                            <td class="text-end">{{ $asig->horas_plan_pedagogicas !== null ? $fmt($asig->horas_plan_pedagogicas) : '—' }}</td>
                                                            <td class="text-end fw-semibold">{{ $fmt($asig->horas_contrato) }}</td>
                                                            <td>{{ $asig->observacion }}</td>
                                                            <td class="text-end">
                                                                @if (data_get($asig, 'asignacion_automatica', false))
                                                                    <span class="badge rounded-pill text-bg-primary">Automática</span>
                                                                @else
                                                                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.destroy', [$establecimiento, $asig]) }}" onsubmit="return confirm('¿Eliminar esta asignación?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit"><i class="bi bi-trash"></i></button>
                                                                    </form>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No existen necesidades calculadas para este bloque.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
        </div>
    </div>
@endforeach


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let initPersonalSelect = function () {};
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        const $ = window.jQuery;
        const optionData = function (item) {
            const option = $(item.element);

            return {
                nombre: option.data('nombre') || item.text,
                rut: option.data('rut') || '',
                estamento: option.data('estamento') || '',
                funcion: option.data('funcion') || '',
                titulo: option.data('titulo') || '',
                prioridad: option.data('prioridad-label') || '',
                antiguedad: option.data('antiguedad') || '',
                fase: option.data('fase-plan') || '',
                titular: option.data('titular-disponible'),
                contrata: option.data('contrata-disponible'),
            };
        };
        const templateResult = function (item) {
            if (!item.id || !item.element) {
                return item.text;
            }

            const data = optionData(item);
            const result = $('<div>', { class: 'dotacion-personal-option' });
            const name = $('<div>', { class: 'dotacion-personal-option__name' })
                .text(data.nombre + (data.rut ? ' · ' + data.rut : ''));
            const meta = $('<div>', { class: 'dotacion-personal-option__meta' });

            if (data.prioridad) {
                meta.append($('<span>', { class: 'dotacion-personal-option__priority' }).text(data.prioridad));
                meta.append($('<span>').text(data.antiguedad ? 'Antigüedad: ' + data.antiguedad : 'Sin antigüedad'));
            } else if (data.estamento === 'asistente') {
                meta.append($('<span>').text('Asistente de la Educación'));
            }
            if (data.titulo) {
                meta.append($('<span>').text(data.titulo));
            } else if (data.funcion) {
                meta.append($('<span>').text(data.funcion));
            }
            const disponible = data.fase === 'titular'
                ? 'Titular disponible: ' + (data.titular || '0') + ' h'
                : (data.fase === 'contrata'
                    ? 'Contrata disponible: ' + (data.contrata || '0') + ' h'
                    : 'Disponible: ' + (data.titular || '0') + ' titular + ' + (data.contrata || '0') + ' contrata');
            meta.append($('<span>', { class: 'dotacion-personal-option__availability' }).text(disponible));

            return result.append(name, meta);
        };
        const templateSelection = function (item) {
            if (!item.id || !item.element) {
                return item.text;
            }

            const data = optionData(item);
            return $('<span>', { class: 'dotacion-personal-selection' })
                .text(data.nombre + (data.rut ? ' · ' + data.rut : ''));
        };

        initPersonalSelect = function (element) {
            $(element).select2({
                width: '100%',
                placeholder: element.dataset.placeholder || 'Buscar por nombre o RUT...',
                allowClear: true,
                minimumResultsForSearch: 0,
                dropdownCssClass: 'dotacion-personal-dropdown',
                language: {
                    noResults: function () { return 'No se encontraron personas con ese nombre o RUT.'; },
                    searching: function () { return 'Buscando personas…'; },
                },
                templateResult: templateResult,
                templateSelection: templateSelection,
            });
        };
        document.querySelectorAll('.js-dotacion-docente-select:not(.js-personal-cobertura)').forEach(initPersonalSelect);
    }
    document.querySelectorAll('[data-dotacion-asignacion-form]').forEach(function (form) {
        const estamento = form.querySelector('.js-estamento-cobertura');
        const personal = form.querySelector('.js-personal-cobertura');
        const contratoAaee = form.querySelector('.js-horas-contrato-aaee');
        const ayudaAaee = form.querySelector('.js-ayuda-aaee');

        if (!estamento || !personal) {
            return;
        }

        const personalOptions = Array.from(personal.querySelectorAll('option[data-estamento]'), function (option) {
            return option.cloneNode(true);
        });
        const sync = function () {
            const selectedEstamento = estamento.value || 'docente';
            const selectedOption = personal.options[personal.selectedIndex];
            const selectedValue = selectedOption && selectedOption.dataset.estamento === selectedEstamento
                ? selectedOption.value
                : '';
            if (window.jQuery && window.jQuery(personal).hasClass('select2-hidden-accessible')) {
                window.jQuery(personal).select2('destroy');
            }
            const placeholder = new Option('Seleccione persona...', '');
            const group = document.createElement('optgroup');
            group.label = selectedEstamento === 'asistente'
                ? 'Asistentes de la Educación'
                : (personal.dataset.fasePlan === 'titular'
                    ? 'Docentes con horas titulares disponibles'
                    : (personal.dataset.fasePlan === 'contrata'
                        ? 'Docentes con horas a contrata disponibles'
                        : 'Docentes vigentes y por contratar'));
            personal.replaceChildren(placeholder, group);
            personalOptions.forEach(function (option) {
                if (option.dataset.estamento === selectedEstamento) {
                    group.appendChild(option.cloneNode(true));
                }
            });
            personal.value = selectedValue;
            initPersonalSelect(personal);

            if (contratoAaee) {
                const isAaee = selectedEstamento === 'asistente';
                contratoAaee.disabled = !isAaee;
                contratoAaee.required = isAaee;
                if (!isAaee) {
                    contratoAaee.value = '';
                }
                if (ayudaAaee) {
                    ayudaAaee.classList.toggle('d-none', !isAaee);
                }
            }
        };

        estamento.addEventListener('change', sync);
        sync();
    });
});
</script>
@endpush
