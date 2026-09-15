@php
    $fmtProyeccion = fn ($horas) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($horas);
    $baseProyeccion = $proyeccion['anio_base'];
    $destinoProyeccion = $proyeccion['anio_proyeccion'];
    $categoriasProyeccion = ['aula' => 'Aula · plan general y funciones normativas', 'parvularia' => 'Educación Parvularia', 'pie' => 'Docente PIE'];
    $gruposCobertura = ['plan_estudio' => 'Plan de estudio', 'funciones' => 'Funciones y planes', 'pie_colaborativo' => 'Trabajo colaborativo PIE', 'pie_educadora_diferencial' => 'Educadoras diferenciales PIE'];
    $necesidadCategorias = [
        'aula' => $proyeccion['necesarias']['plan_general'] + $proyeccion['necesarias']['funciones_normativas'] + $proyeccion['necesarias_adicionales']['aula'],
        'parvularia' => $proyeccion['necesarias']['parvularia'] + $proyeccion['necesarias_adicionales']['parvularia'],
        'pie' => $proyeccion['necesarias']['pie'] + $proyeccion['necesarias_adicionales']['pie'],
    ];
@endphp

<div class="border rounded-4 bg-white shadow-sm mb-4 overflow-hidden">
    <div class="bg-primary text-white px-4 py-3 d-flex justify-content-between flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-calendar2-range me-2" aria-hidden="true"></i>Planificación de dotación</span>
        <span>Base {{ $baseProyeccion }} <i class="bi bi-arrow-right mx-2" aria-hidden="true"></i> Proyección {{ $destinoProyeccion }}</span>
    </div>
    <div class="p-4">
        <h1 class="h2 fw-bold mb-2">Proyección dotación {{ $destinoProyeccion }}</h1>
        <p class="fw-semibold mb-2">{{ $establecimiento->rbd }} · {{ $establecimiento->nombre_establecimiento }}</p>
        <p class="text-muted mb-3">Mantiene los cursos, planes de estudio y necesidades de funciones de {{ $baseProyeccion }}. El contrato proyectado incluye las horas de quienes continúan y las horas necesarias conservadas como vacantes. La cobertura considera a las personas que continuarán en {{ $destinoProyeccion }}. No reemplaza la configuración anual de {{ $destinoProyeccion }}.</p>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $baseProyeccion, 'tab' => 'docentes']) }}"><i class="bi bi-person-check me-1" aria-hidden="true"></i> Revisar continuidad docente</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $baseProyeccion]) }}">Volver a dotación {{ $baseProyeccion }}</a>
        </div>
    </div>
</div>

@if (!($continuidadDisponible ?? false))
    <div class="alert alert-warning">La continuidad aún no está habilitada. Esta proyección considera que todos continúan hasta instalar la migración y registrar las decisiones.</div>
@endif

<section aria-labelledby="proyeccion-personas" class="mb-4">
    <h2 id="proyeccion-personas" class="h5 fw-bold">Disponibilidad docente</h2>
    <div class="row g-3">
        @foreach ([
            ['Docentes que continúan', $proyeccion['docentes_continuan'], 'person-check', ''],
            ['Docentes que no continúan', $proyeccion['docentes_no_continuan'], 'person-dash', ''],
            ['Horas de contrato proyectadas', $fmtProyeccion($proyeccion['contratos']['total']), 'briefcase', ''],
            ['Hrs vacantes por cubrir '.$destinoProyeccion, $fmtProyeccion($proyeccion['horas_vacantes_por_cubrir']), 'calendar-minus', 'text-success-emphasis'],
        ] as [$label, $value, $icon, $color])
            <div class="col-sm-6 col-xl-3"><div class="border rounded-3 bg-white p-3 h-100"><div class="{{ $color ?: 'text-muted' }} small"><i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</div><div class="fs-2 fw-bold mt-1 {{ $color }}">{{ $value }}</div></div></div>
        @endforeach
    </div>
    <p class="small text-muted mt-2">Las vacantes conservadas están incluidas en las horas de contrato proyectadas. Las horas no necesarias quedan excluidas; la persona que sale no aporta cobertura.</p>
</section>

@if (!($conservacionHorasDisponible ?? false))
    <div class="alert alert-warning">Hasta instalar la migración de conservación de horas, las salidas registradas conservan sus horas necesarias por defecto.</div>
@endif

@if (count($proyeccion['reservas']) > 0)
    <section class="border rounded-3 bg-white mb-4" aria-labelledby="proyeccion-reservas">
        <div class="p-3"><h2 id="proyeccion-reservas" class="h5 fw-bold">Horas necesarias conservadas de docentes que no continúan</h2><p class="small text-muted mb-0">Estas horas se proyectan como vacantes. Las ya incluidas en el plan o las funciones se cuentan una sola vez; solo las adicionales incrementan la necesidad de la categoría correspondiente.</p></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead class="table-light"><tr><th scope="col">Docente que sale</th><th scope="col">Categoría</th><th scope="col" class="text-end">Vacantes conservadas</th><th scope="col" class="text-end">Ya incluidas en necesidades</th><th scope="col" class="text-end">Necesarias adicionales</th></tr></thead>
            <tbody>@foreach ($proyeccion['reservas'] as $reserva)
                <tr><th scope="row" class="fw-normal">{{ $reserva['nombre'] }}</th><td>{{ $categoriasProyeccion[$reserva['categoria']] }}</td><td class="text-end text-success-emphasis fw-bold">{{ $fmtProyeccion($reserva['horas_necesarias']) }}</td><td class="text-end">{{ $fmtProyeccion($reserva['ya_contempladas']) }}</td><td class="text-end">{{ $fmtProyeccion($reserva['adicionales']) }}</td></tr>
            @endforeach</tbody>
        </table></div>
    </section>
@endif

<section aria-labelledby="proyeccion-necesidades" class="mb-4">
    <h2 id="proyeccion-necesidades" class="h5 fw-bold">Necesidades del establecimiento que se mantienen</h2>
    <div class="row g-3">
        @foreach (['plan_general' => 'Contrato plan general + PIE', 'parvularia' => 'Contrato Educación Parvularia + PIE', 'funciones_normativas' => 'Funciones directivas, técnico-pedagógicas y planes normativos', 'pie' => 'Contrato PIE necesario', 'otras_funciones' => 'Otras funciones no normativas'] as $key => $label)
            <div class="col-md-6 col-xl"><div class="border rounded-3 bg-white p-3 h-100"><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-bold text-primary mt-1">{{ $fmtProyeccion($proyeccion['necesarias'][$key]) }} <span class="fs-6 fw-normal">h contrato</span></div></div></div>
        @endforeach
    </div>
</section>

<section aria-labelledby="proyeccion-brechas" class="mb-4">
    <h2 id="proyeccion-brechas" class="h5 fw-bold">Contrato disponible y brecha {{ $destinoProyeccion }}</h2>
    <div class="row g-3">
        @foreach ($categoriasProyeccion as $key => $label)
            @php $brecha = $proyeccion['brechas'][$key]; @endphp
            <div class="col-lg-4"><div class="border rounded-3 bg-white p-4 h-100">
                <h3 class="h6 fw-bold">{{ $label }}</h3>
                <dl class="row mb-2 small">
                    <dt class="col-8 fw-normal">Horas necesarias</dt><dd class="col-4 text-end">{{ $fmtProyeccion($necesidadCategorias[$key]) }}</dd>
                    @if ($proyeccion['necesarias_adicionales'][$key] > 0)
                        <dt class="col-8 fw-normal text-muted">Incluye adicionales por situación</dt><dd class="col-4 text-end text-muted">{{ $fmtProyeccion($proyeccion['necesarias_adicionales'][$key]) }}</dd>
                    @endif
                    <dt class="col-8 fw-normal">Horas contrato {{ $baseProyeccion }}</dt><dd class="col-4 text-end">{{ $fmtProyeccion($proyeccion['contratos_base'][$key]) }}</dd>
                    <dt class="col-8 fw-normal">Horas contrato {{ $destinoProyeccion }}</dt><dd class="col-4 text-end fw-bold">{{ $fmtProyeccion($proyeccion['contratos'][$key]) }}</dd>
                    <dt class="col-8 fw-normal text-muted">De docentes que continúan</dt><dd class="col-4 text-end text-muted">{{ $fmtProyeccion($proyeccion['contratos_cubiertos'][$key]) }}</dd>
                    <dt class="col-8 fw-normal text-success-emphasis">Incluye vacantes por cubrir</dt><dd class="col-4 text-end text-success-emphasis">{{ $fmtProyeccion($proyeccion['contratos_vacantes'][$key]) }}</dd>
                </dl>
                <div class="border-top pt-2"><div class="fs-2 fw-bold {{ $brecha > 0 ? 'text-success-emphasis' : ($brecha < 0 ? 'text-danger' : 'text-primary') }}">{{ $fmtProyeccion(abs($brecha)) }} <span class="fs-6">h</span></div><div class="small">{{ $brecha > 0 ? 'Horas por contratar' : ($brecha < 0 ? 'Horas de sobredotación' : 'Dotación cuadrada') }}</div></div>
            </div></div>
        @endforeach
    </div>
    <p class="small text-muted mt-2">La brecha compara las necesidades con el contrato proyectado, que incluye las vacantes conservadas. Una brecha cero puede tener vacantes pendientes de cubrir. Las funciones no normativas conservan su necesidad y se detallan en cobertura.</p>
</section>

<section aria-labelledby="proyeccion-coberturas" class="mb-4">
    <h2 id="proyeccion-coberturas" class="h5 fw-bold">Cobertura proyectada por necesidad</h2>
    <p class="text-muted small">Las asignaciones de quienes no continúan se omiten en {{ $destinoProyeccion }}. Cada necesidad conserva sus horas y la cobertura de las otras personas. Las horas pendientes se calculan por fila, sin compensarlas con excedentes de otra necesidad.</p>
    @foreach ($gruposCobertura as $grupo => $label)
        <details class="border rounded-3 bg-white mb-3" open>
            <summary class="p-3 fw-bold">{{ $label }} <span class="text-muted fw-normal">({{ count($proyeccion['coberturas'][$grupo] ?? []) }})</span></summary>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">{{ $label }}: cobertura {{ $baseProyeccion }} y proyección {{ $destinoProyeccion }}</caption>
                    <thead class="table-light"><tr><th scope="col">Necesidad / curso</th><th scope="col">Unidad</th><th scope="col" class="text-end">Necesarias</th><th scope="col" class="text-end">Asignadas {{ $baseProyeccion }}</th><th scope="col" class="text-end">Cobertura {{ $destinoProyeccion }}</th><th scope="col" class="text-end">Pendientes</th><th scope="col">Personas que cubren {{ $destinoProyeccion }}</th></tr></thead>
                    <tbody>
                    @forelse ($proyeccion['coberturas'][$grupo] ?? [] as $item)
                        <tr>
                            <th scope="row" class="fw-normal"><div class="fw-semibold">{{ $item['titulo'] }}</div><div class="small text-muted">{{ $item['curso'] }}</div></th>
                            <td class="small text-nowrap">{{ $item['unidad'] }}</td>
                            <td class="text-end">{{ $fmtProyeccion($item['requeridas']) }}</td>
                            <td class="text-end">{{ $fmtProyeccion($item['asignadas_base']) }}</td>
                            <td class="text-end">{{ $fmtProyeccion($item['asignadas_proyectadas']) }}@if ($item['cobertura_retirada'] > 0)<div class="small text-muted">−{{ $fmtProyeccion($item['cobertura_retirada']) }} por salida</div>@endif</td>
                            <td class="text-end fw-semibold {{ $item['pendientes'] > 0 ? 'text-danger' : '' }}">{{ $fmtProyeccion($item['pendientes']) }}@if ($item['excedidas'] > 0)<div class="small text-muted">Excede {{ $fmtProyeccion($item['excedidas']) }}</div>@endif</td>
                            <td class="small">{{ implode(', ', $item['personas']) ?: 'Sin cobertura' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">Sin necesidades registradas para este grupo en {{ $baseProyeccion }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    @endforeach
</section>

<section aria-labelledby="proyeccion-nomina" class="border rounded-3 bg-white mb-4">
    <h2 id="proyeccion-nomina" class="h5 fw-bold p-3 mb-0">Continuidad por docente</h2>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <caption class="visually-hidden">Participación docente en la proyección {{ $destinoProyeccion }}</caption>
        <thead class="table-light"><tr><th scope="col">Docente / función</th><th scope="col">Situación</th><th scope="col" class="text-end">Contrato original</th><th scope="col" class="text-end">Contrato considerado {{ $baseProyeccion }}</th><th scope="col" class="text-end">Aporte de la persona {{ $destinoProyeccion }}</th><th scope="col">Continuidad</th></tr></thead>
        <tbody>
        @forelse ($proyeccion['docentes'] as $docente)
            <tr><th scope="row" class="fw-normal"><div class="fw-semibold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['rut'] }} · {{ $docente['funcion'] }}</div></th><td>{{ $docente['motivo'] ?: 'Sin situación registrada' }}</td><td class="text-end">{{ $fmtProyeccion($docente['contrato_base']) }}</td><td class="text-end">{{ $fmtProyeccion($docente['contrato_considerado']) }}</td><td class="text-end fw-semibold">{{ $fmtProyeccion($docente['contrato_proyectado']) }}</td><td><span class="badge {{ $docente['continua'] ? 'text-bg-primary' : 'text-bg-danger' }}">{{ $docente['continua'] ? 'Se contempla' : 'No continúa' }}</span></td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Sin docentes en el padrón del año base.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
