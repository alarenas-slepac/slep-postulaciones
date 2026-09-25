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

            return [
                'rut' => $persona['rut'],
                'rut_normalizado' => $persona['rut_normalizado'] ?? $persona['rut'],
                'nombre' => $persona['nombre'],
                'funcion' => $persona['funcion'] ?? 'Sin función',
                'estamento' => $estamento,
                'label' => $persona['nombre'].' · '.$persona['rut'].$detallePrioridad.$detalleAntiguedad.$detalleTitulo.' · Disponible: '.$fmt($titularDisponible).' titular + '.$fmt($contrataDisponible).' contrata',
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

@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
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
                min-height: calc(1.5em + .5rem + 2px);
                border-color: #b9cbe4;
                border-radius: .55rem;
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
                <h2 class="h5 fw-bold mb-1">Asignar horas a docentes o asistentes vigentes</h2>
                <div class="text-muted small">Asocia docentes o asistentes de la educación a las horas aula de cada asignatura y a las horas contrato de PIE, funciones directivas, técnico-pedagógicas, planes y otras funciones. La equivalencia contractual 65/35 o 60/40 se consolida en la pestaña Docentes.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger rounded-4">
                <div class="fw-semibold mb-1">No fue posible guardar la asignación</div>
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
        @if (($proceso2027Asignacion['aplica'] ?? false) && !$asignacion2027Habilitada)
            <div class="alert alert-warning rounded-4"><i class="bi bi-lock"></i> La asignación 2027 está bloqueada hasta completar planes, declarar combinación de cursos, definir las funciones normativas y configurar máximos suficientes. Revise el proceso guiado superior.</div>
        @endif
        <div class="alert alert-info rounded-4 small">
            <strong>Regla NT1/NT2:</strong> para <em>Pedagogía en Educación de Párvulos</em>, el contrato asignado se distribuye proporcionalmente: horas de plan asignadas / total del plan × base contractual. Con JEC: 55 h por curso o grupo; sin JEC: NT1 35 h, NT2 31 h y NT1 + NT2 combinados 35 h. PIE se asigna aparte (3 h cuando corresponda). Sin JEC solo se admite cobertura por Educadoras de Párvulos. La libre disposición de otro docente con JEC mantiene 65/35 y se contabiliza en Plan General, una vez por grupo combinado.
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
                                        @endphp
                                        @if ($lastBloque !== $bloqueActual)
                                            <tr class="table-secondary">
                                                <td colspan="7" class="fw-semibold small text-uppercase">{{ $bloqueActual }}</td>
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
                                                        <div class="dotacion-selector-guide"><i class="bi bi-sort-numeric-down"></i><span><strong>Selección priorizada.</strong> Busque por nombre o RUT; cada grupo titular se ordena por antigüedad y muestra el saldo contractual disponible.</span></div>
                                                    @endif
                                                    <select name="estamento_cobertura" class="form-select form-select-sm js-estamento-cobertura" required>
                                                        <option value="docente">Cubierto por docente</option>
                                                        @unless ($soloParvularia)<option value="asistente">Cubierto por Asistente de la Educación</option>@endunless
                                                    </select>
                                                    <select name="docente_rut" class="form-select form-select-sm js-personal-cobertura js-dotacion-docente-select" data-placeholder="Buscar por nombre o RUT..." required>
                                                        <option value="">Seleccione persona...</option>
                                                        <optgroup label="Docentes">
                                                            @foreach ($docenteOptions as $doc)
                                                                @continue($soloParvularia && !$doc['es_parvularia'])
                                                                <option value="{{ $doc['rut'] }}" data-estamento="docente" data-nombre="{{ $doc['nombre'] }}" data-rut="{{ $doc['rut'] }}" data-titulo="{{ $doc['titulo'] }}" data-funcion="{{ $doc['funcion'] }}" data-prioridad="{{ $doc['prioridad'] }}" data-prioridad-label="{{ $doc['prioridad_label'] }}" data-antiguedad="{{ $doc['antiguedad'] }}" data-titular-disponible="{{ $fmt($doc['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($doc['contrata_disponible']) }}">{{ $doc['label'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                        <optgroup label="Asistentes de la Educación">
                                                            @foreach ($asistenteOptions as $asistente)
                                                                @continue($soloParvularia)
                                                                <option value="{{ $asistente['rut'] }}" data-estamento="asistente" data-nombre="{{ $asistente['nombre'] }}" data-rut="{{ $asistente['rut'] }}" data-funcion="{{ $asistente['funcion'] }}" data-titular-disponible="{{ $fmt($asistente['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($asistente['contrata_disponible']) }}">{{ $asistente['label'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    </select>
                                                    <div class="row g-2">
                                                        <div class="col-md-4">
                                                            <input type="number" name="horas_plan_pedagogicas" step="0.25" min="0.25" class="form-control form-control-sm js-horas-aula" value="{{ $pendingPlan !== null && $pendingPlan > 0 ? $pendingPlan : ($item['horas_plan_requeridas'] ?? 0) }}" placeholder="Horas aula">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <input type="number" name="horas_contrato" step="0.25" min="0.25" class="form-control form-control-sm js-horas-contrato-aaee" placeholder="Contrato AAEE" disabled>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <select name="subvencion" class="form-select form-select-sm">
                                                                @foreach ($subvencionesOptions as $subvencion)
                                                                    <option value="{{ $subvencion }}" @selected(($item['subvencion'] ?? 'General') === $subvencion)>{{ $subvencion }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-text js-ayuda-aaee d-none">Para asistentes, ingrese las horas aula cubiertas y las horas de contrato AAEE. No se aplica conversión 65/35 ni 60/40.</div>
                                                    <input type="text" name="excepcion_prelacion" class="form-control form-control-sm" maxlength="2000" placeholder="Justificación si omite prelación o antigüedad">
                                                    <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Observación opcional">
                                                    <button class="btn btn-sm btn-primary rounded-pill" type="submit" @disabled(!$asignacion2027Habilitada)><i class="bi bi-plus-circle"></i> Asignar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        @if (count($item['asignaciones'] ?? []) > 0)
                                            <tr>
                                                <td colspan="7" class="bg-light">
                                                    <div class="small fw-semibold mb-2">Asignaciones registradas para esta asignatura</div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-0">
                                                            <thead><tr><th>Personal</th><th>Estamento</th><th>Subvención</th><th class="text-end">Horas aula asignadas</th><th class="text-end">Contrato asignado</th><th>Obs.</th><th></th></tr></thead>
                                                            <tbody>
                                                                @foreach ($item['asignaciones'] as $asig)
                                                                    <tr>
                                                                        <td>{{ $asig->docente_nombre }}<div class="text-muted small">{{ $asig->docente_rut }}</div>@if($asig->tipo_asignacion === 'plan_estudio')<div class="small text-primary">{{ $asig->proporcion_aplicada ?: 'Regla no informada' }}</div>@endif</td>
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
                                            <div class="mt-1">La plaza activa 44 horas como necesidad hasta asignar al docente directivo.</div>
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
                                            <input type="hidden" name="horas_contrato" value="44">
                                            <label class="form-label small mb-0" for="director_adp_docente_{{ $item['dotacion_funcion_regla_id'] }}">Docente directivo</label>
                                            <div class="dotacion-selector-guide"><i class="bi bi-search"></i><span>Busque por nombre o RUT. La lista muestra la prelación 2027 y las horas disponibles antes de confirmar la asignación.</span></div>
                                            <select id="director_adp_docente_{{ $item['dotacion_funcion_regla_id'] }}" name="docente_rut" class="form-select form-select-sm js-dotacion-docente-select" data-placeholder="Buscar docente por nombre o RUT..." required>
                                                <option value="">Seleccione docente...</option>
                                                @foreach ($docenteOptions as $doc)
                                                    <option value="{{ $doc['rut'] }}" data-estamento="docente" data-nombre="{{ $doc['nombre'] }}" data-rut="{{ $doc['rut'] }}" data-titulo="{{ $doc['titulo'] }}" data-funcion="{{ $doc['funcion'] }}" data-prioridad="{{ $doc['prioridad'] }}" data-prioridad-label="{{ $doc['prioridad_label'] }}" data-antiguedad="{{ $doc['antiguedad'] }}" data-titular-disponible="{{ $fmt($doc['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($doc['contrata_disponible']) }}">{{ $doc['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <div class="small text-muted">Contrato fijo: 44 horas.</div>
                                            <input type="text" name="excepcion_prelacion" class="form-control form-control-sm" maxlength="2000" placeholder="Justificación si omite prelación o antigüedad">
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
                                        <select name="estamento_cobertura" class="form-select form-select-sm js-estamento-cobertura" required>
                                            <option value="docente">Cubierto por docente</option>
                                            <option value="asistente">Cubierto por Asistente de la Educación</option>
                                        </select>
                                        <select name="docente_rut" class="form-select form-select-sm js-personal-cobertura js-dotacion-docente-select" data-placeholder="Buscar por nombre o RUT..." required>
                                            <option value="">Seleccione persona...</option>
                                            <optgroup label="Docentes">
                                                @foreach ($docenteOptions as $doc)
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
                                                <input type="number" name="horas_contrato" step="0.25" min="0.25" class="form-control form-control-sm" value="{{ $pendingContrato > 0 ? $pendingContrato : ($item['horas_contrato_requeridas'] ?? 0) }}" placeholder="Horas contrato">
                                            </div>
                                            <div class="col">
                                                <select name="subvencion" class="form-select form-select-sm">
                                                    @foreach ($subvencionesOptions as $subvencion)
                                                        <option value="{{ $subvencion }}" @selected(($item['subvencion'] ?? 'General') === $subvencion)>{{ $subvencion }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <input type="text" name="excepcion_prelacion" class="form-control form-control-sm" maxlength="2000" placeholder="Justificación si omite prelación o antigüedad">
                                        <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Observación opcional">
                                        <button class="btn btn-sm btn-primary rounded-pill" type="submit" @disabled(!$asignacion2027Habilitada)><i class="bi bi-plus-circle"></i> Asignar</button>
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
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
            meta.append($('<span>', { class: 'dotacion-personal-option__availability' })
                .text('Disponible: ' + (data.titular || '0') + ' titular + ' + (data.contrata || '0') + ' contrata'));

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

        $('.js-dotacion-docente-select').select2({
            width: '100%',
            placeholder: 'Buscar por nombre o RUT...',
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
    }
    document.querySelectorAll('[data-dotacion-asignacion-form]').forEach(function (form) {
        const estamento = form.querySelector('.js-estamento-cobertura');
        const personal = form.querySelector('.js-personal-cobertura');
        const contratoAaee = form.querySelector('.js-horas-contrato-aaee');
        const ayudaAaee = form.querySelector('.js-ayuda-aaee');

        if (!estamento || !personal) {
            return;
        }

        const sync = function () {
            const selectedEstamento = estamento.value || 'docente';
            Array.from(personal.options).forEach(function (option) {
                const optionEstamento = option.dataset.estamento;
                if (!optionEstamento) {
                    option.disabled = false;
                    return;
                }
                option.disabled = optionEstamento !== selectedEstamento;
            });

            const selectedOption = personal.options[personal.selectedIndex];
            if (selectedOption && selectedOption.dataset.estamento && selectedOption.dataset.estamento !== selectedEstamento) {
                personal.value = '';
            }

            if (window.jQuery && window.jQuery(personal).hasClass('select2-hidden-accessible')) {
                window.jQuery(personal).trigger('change.select2');
            }

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
