@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $totalContrato = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato'] ?? 0));
    $totalAula = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_aula'] ?? 0));
    $totalAula6535 = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_aula_65_35'] ?? 0));
    $totalAula6040 = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_aula_60_40'] ?? 0));
    $totalContrato6535 = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato_65_35'] ?? 0));
    $totalContrato6040 = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato_60_40'] ?? 0));
    $totalContratoEspecial = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato_especial'] ?? 0));
    $totalFunciones = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_funciones_total'] ?? 0));
    $totalAsignadas = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_asignadas_total'] ?? 0));
    $totalDiferencia = round($totalContrato - $totalAsignadas, 2);
    $mostrarEspecial = $totalContratoEspecial > 0.01;
    $tableColspan = $mostrarEspecial ? 13 : 12;
    $countCuadra = $docentes->filter(fn ($docente) => ($docente['estado_cuadratura']['key'] ?? null) === 'cuadra')->count();
    $countPendiente = $docentes->filter(fn ($docente) => ($docente['estado_cuadratura']['key'] ?? null) === 'pendiente_asignacion')->count();
    $countFaltan = $docentes->filter(fn ($docente) => ($docente['estado_cuadratura']['key'] ?? null) === 'faltan_horas')->count();
    $countSobrecarga = $docentes->filter(fn ($docente) => ($docente['estado_cuadratura']['key'] ?? null) === 'sobrecarga')->count();
    $countSinInfo = $docentes->filter(fn ($docente) => in_array(($docente['estado_cuadratura']['key'] ?? null), ['sin_declaracion', 'sin_horas_contrato'], true))->count();
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start gap-3">
            <span class="dotacion-icon" style="width:40px;height:40px;background:#0d6efd;"><i class="bi bi-person-workspace"></i></span>
            <div>
                <div class="dotacion-eyebrow">Base docente contractual</div>
                <h2 class="h5 fw-bold mb-1">Docentes vigentes del establecimiento</h2>
                <div class="text-muted small">La nómina considera todos los registros vigentes del último mes cargado para el establecimiento y año. Las horas de contrato se separan por Planta y Contrata, y las horas aula se calculan de forma consolidada usando las proporciones 65/35 y 60/40.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes</div><div class="h4 fw-bold mb-0">{{ $docentes->count() }}</div><div class="small text-muted">Vigentes</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Contrato base</div><div class="h4 fw-bold mb-0">{{ $fmt($totalContrato) }}</div><div class="small text-muted">Horas registradas</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Aula asignada</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($totalAula) }}</div><div class="small text-muted">Valor real</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Contrato 65/35</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($totalContrato6535) }}</div><div class="small text-muted">Calculado</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Contrato 60/40</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($totalContrato6040) }}</div><div class="small text-muted">Calculado</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Diferencia</div>@if ($totalDiferencia > 0.01)<div class="h4 fw-bold text-warning mb-0">{{ $fmt($totalDiferencia) }}</div><div class="small text-muted">Pendiente asignación</div>@elseif ($totalDiferencia < -0.01)<div class="h4 fw-bold text-danger mb-0">{{ $fmt(abs($totalDiferencia)) }}</div><div class="small text-muted">Sobrecarga</div>@else<div class="h4 fw-bold text-success mb-0">0</div><div class="small text-muted">Cuadrado</div>@endif</div></div>
        </div>
        <div class="d-flex flex-wrap gap-1 mt-3">
            <span class="badge text-bg-success">Cuadra: {{ $countCuadra }}</span>
            <span class="badge text-bg-secondary">Pendiente: {{ $countPendiente }}</span>
            <span class="badge text-bg-info">Faltan: {{ $countFaltan }}</span>
            <span class="badge text-bg-danger">Sobrecarga: {{ $countSobrecarga }}</span>
            <span class="badge text-bg-warning">Sin info: {{ $countSinInfo }}</span>
        </div>
    </div>
</div>

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="dotacion-eyebrow">Conversión consolidada</div>
            <h2 class="h5 fw-bold mb-1">Horas aula y contrato calculado</h2>
            <div class="text-muted small">Cada proporción se calcula sobre la suma de horas aula del docente, evitando redondear asignaturas individualmente. Las funciones se agregan como horas contrato directas.</div>
        </div>
        <span class="badge rounded-pill text-bg-primary">Asignación activa</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md"><div class="p-3 rounded-4 border h-100"><div class="small text-muted">Aula 65/35</div><div class="h5 fw-bold text-primary mb-0">{{ $fmt($totalAula6535) }}</div></div></div>
            <div class="col-md"><div class="p-3 rounded-4 border h-100"><div class="small text-muted">Contrato 65/35</div><div class="h5 fw-bold text-info mb-0">{{ $fmt($totalContrato6535) }}</div></div></div>
            <div class="col-md"><div class="p-3 rounded-4 border h-100"><div class="small text-muted">Aula 60/40</div><div class="h5 fw-bold text-primary mb-0">{{ $fmt($totalAula6040) }}</div></div></div>
            <div class="col-md"><div class="p-3 rounded-4 border h-100"><div class="small text-muted">Contrato 60/40</div><div class="h5 fw-bold text-info mb-0">{{ $fmt($totalContrato6040) }}</div></div></div>
            @if ($mostrarEspecial)
                <div class="col-md"><div class="p-3 rounded-4 border h-100"><div class="small text-muted">Contrato regla especial</div><div class="h5 fw-bold mb-0">{{ $fmt($totalContratoEspecial) }}</div></div></div>
            @endif
            <div class="col-md"><div class="p-3 rounded-4 border h-100"><div class="small text-muted">Funciones contrato</div><div class="h5 fw-bold mb-0">{{ $fmt($totalFunciones) }}</div></div></div>
            <div class="col-md"><div class="p-3 rounded-4 border h-100"><div class="small text-muted">Total contrato calculado</div><div class="h5 fw-bold text-success mb-0">{{ $fmt($totalAsignadas) }}</div></div></div>
        </div>
    </div>
</div>

<div class="card dotacion-section">
    <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="dotacion-eyebrow">Listado docente</div>
            <h2 class="h5 fw-bold mb-1">Base contractual y cálculo por proporción</h2>
            <div class="text-muted small">Muestra primero las horas aula realmente asignadas y luego su equivalencia contractual 65/35 o 60/40.</div>
        </div>
        <span class="badge rounded-pill text-bg-light border">{{ $docentes->count() }} registro(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 44px;">#</th>
                    <th>RUT</th>
                    <th>Docente</th>
                    <th>Título / función</th>
                    <th class="text-end">Contrato base</th>
                    <th class="text-end">Aula asignada</th>
                    <th class="text-end">Hrs. contrato 65/35</th>
                    <th class="text-end">Hrs. contrato 60/40</th>
                    @if ($mostrarEspecial)<th class="text-end">Regla especial</th>@endif
                    <th class="text-end">Funciones</th>
                    <th class="text-end">Total contrato calc.</th>
                    <th class="text-end">Dif.</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($docentes as $docente)
                    @php
                        $collapseId = 'docente-detalle-'.$loop->iteration;
                        $diferencia = $docente['diferencia'];
                        $estado = $docente['estado_cuadratura'] ?? ['label' => 'Sin estado', 'class' => 'text-bg-secondary', 'detalle' => ''];
                        $funcionesDocente = (float) ($docente['horas_funciones_total'] ?? 0);
                        $horasPlanta = (float) ($docente['horas_planta'] ?? 0);
                        $horasContrata = (float) ($docente['horas_contrata'] ?? 0);
                        $otrasFuncionesDetalle = collect($docente['otras_funciones_detalle'] ?? []);
                    @endphp
                    <tr>
                        <td><button class="btn btn-sm btn-outline-primary rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}"><i class="bi bi-chevron-down"></i></button></td>
                        <td class="text-nowrap fw-semibold">{{ $docente['rut'] }}</td>
                        <td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="text-muted small">{{ $docente['niveles_declarados'] }}</div></td>
                        <td><div class="fw-semibold">{{ $docente['funcion'] }}</div><div class="text-muted small">{{ $docente['titulo'] }}</div></td>
                        <td class="text-end">
                            <div class="fw-bold">{{ $fmt($docente['horas_contrato']) }}</div>
                            @if (($docente['registros_contrato'] ?? 1) > 1)
                                <div class="small text-muted text-nowrap">{{ $docente['registros_contrato'] ?? 1 }} registros</div>
                            @endif
                            @if ($horasPlanta > 0.01 || $horasContrata > 0.01)
                                <div class="d-flex flex-wrap justify-content-end gap-1 mt-1">
                                    @if ($horasPlanta > 0.01)
                                        <span class="badge text-bg-primary">Planta {{ $fmt($horasPlanta) }} h</span>
                                    @endif
                                    @if ($horasContrata > 0.01)
                                        <span class="badge text-bg-info">Contrata {{ $fmt($horasContrata) }} h</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_aula']) }}</td>
                        <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_contrato_65_35'] ?? 0) }}</td>
                        <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_contrato_60_40'] ?? 0) }}</td>
                        @if ($mostrarEspecial)<td class="text-end">{{ $fmt($docente['horas_contrato_especial'] ?? 0) }}</td>@endif
                        <td class="text-end">{{ $fmt($funcionesDocente) }}</td>
                        <td class="text-end fw-bold text-success">{{ $fmt($docente['horas_asignadas_total']) }}</td>
                        <td class="text-end">@if ($diferencia === null)—@elseif ($diferencia < -0.01)<span class="text-danger fw-semibold">-{{ $fmt(abs($diferencia)) }}</span>@elseif ($diferencia > 0.01)<span class="text-warning fw-semibold">{{ $fmt($diferencia) }}</span>@else<span class="text-success fw-semibold">0</span>@endif</td>
                        <td><span class="badge rounded-pill {{ $estado['class'] ?? 'text-bg-secondary' }}">{{ $estado['label'] ?? 'Sin estado' }}</span></td>
                    </tr>
                    <tr class="collapse" id="{{ $collapseId }}">
                        <td colspan="{{ $tableColspan }}" class="bg-light">
                            <div class="p-3">
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body">
                                                <div class="fw-semibold mb-2">Datos contractuales</div>
                                                <div class="small text-muted">Horas contrato registradas</div><div class="fw-bold">{{ $fmt($docente['horas_contrato']) }}</div>
                                                @if (!empty($docente['horas_contrato_detalle']))
                                                    <div class="small text-primary mb-2">{{ $docente['horas_contrato_detalle'] }}</div>
                                                @else
                                                    <div class="mb-2"></div>
                                                @endif
                                                @if ($horasPlanta > 0.01 || $horasContrata > 0.01)
                                                    <div class="small text-muted">Horas por calidad jurídica</div>
                                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                                        @if ($horasPlanta > 0.01)
                                                            <span class="badge text-bg-primary">Planta: {{ $fmt($horasPlanta) }} h</span>
                                                        @endif
                                                        @if ($horasContrata > 0.01)
                                                            <span class="badge text-bg-info">Contrata: {{ $fmt($horasContrata) }} h</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="small text-muted">Tipo contrato</div><div class="mb-2">{{ $docente['tipo_contrato'] }}</div>
                                                <div class="small text-muted">Financiamiento</div><div class="mb-2">{{ $docente['financiamiento'] }}</div>
                                                <div class="small text-muted">Fuente contractual</div>
                                                <div>{{ ($docente['fuente_contrato'] ?? null) === 'declaracion_sostenedor' ? 'Declaración sostenedor' : 'Reemplazos personal' }}</div>
                                                <div class="small text-muted mt-2">Período considerado</div>
                                                <div>{{ str_pad((string) $docente['mes'], 2, '0', STR_PAD_LEFT) }}/{{ $docente['anio'] }}@if (($docente['registros_contrato'] ?? 1) > 1) · {{ $docente['registros_contrato'] }} líneas vigentes @endif</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body">
                                                <div class="fw-semibold mb-2">Cálculo de asignación</div>
                                                <div class="d-flex justify-content-between"><span>Aula asignada real</span><strong class="text-primary">{{ $fmt($docente['horas_aula']) }}</strong></div>
                                                <div class="d-flex justify-content-between"><span>Aula 65/35</span><strong>{{ $fmt($docente['horas_aula_65_35'] ?? 0) }}</strong></div>
                                                <div class="d-flex justify-content-between"><span>Contrato 65/35</span><strong class="text-info">{{ $fmt($docente['horas_contrato_65_35'] ?? 0) }}</strong></div>
                                                <div class="d-flex justify-content-between"><span>Aula 60/40</span><strong>{{ $fmt($docente['horas_aula_60_40'] ?? 0) }}</strong></div>
                                                <div class="d-flex justify-content-between"><span>Contrato 60/40</span><strong class="text-info">{{ $fmt($docente['horas_contrato_60_40'] ?? 0) }}</strong></div>
                                                @if (($docente['horas_contrato_especial'] ?? 0) > 0.01)
                                                    <div class="d-flex justify-content-between"><span>Contrato regla especial</span><strong>{{ $fmt($docente['horas_contrato_especial']) }}</strong></div>
                                                @endif
                                                <div class="d-flex justify-content-between"><span>Funciones asignadas</span><strong>{{ $fmt($funcionesDocente) }}</strong></div>
                                                <hr class="my-2">
                                                <div class="d-flex justify-content-between"><span>Total contrato calculado</span><strong class="text-success">{{ $fmt($docente['horas_asignadas_total']) }}</strong></div>
                                                <div class="d-flex justify-content-between"><span>Diferencia</span><strong>{{ $fmt($docente['diferencia']) }}</strong></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body">
                                                <div class="fw-semibold mb-2">Función declarada</div>
                                                <div class="small text-muted">Estamento</div><div class="mb-2">{{ $docente['estamento'] }}</div>
                                                <div class="small text-muted">Función / cargo</div><div class="mb-2">{{ $docente['funcion'] }}</div>
                                                <div class="small text-muted">Título</div><div>{{ $docente['titulo'] }}</div>
                                                <hr class="my-2">
                                                <div class="small text-muted">Desglose de funciones asignadas</div>
                                                <div class="small">Directivas: {{ $fmt($docente['horas_directivas'] ?? 0) }} · Téc. ped.: {{ $fmt($docente['horas_tecnico_pedagogicas'] ?? 0) }} · PIE: {{ $fmt($docente['horas_pie'] ?? 0) }} · Planes: {{ $fmt($docente['horas_planes'] ?? 0) }} · Otras: {{ $fmt($docente['horas_otras_funciones'] ?? 0) }}</div>
                                                @if ($otrasFuncionesDetalle->isNotEmpty())
                                                    <div class="small text-muted mt-3">Detalle de otras horas</div>
                                                    <div class="vstack gap-1 mt-1">
                                                        @foreach ($otrasFuncionesDetalle as $otraFuncion)
                                                            <div class="d-flex justify-content-between gap-3 small border-bottom pb-1">
                                                                <span>{{ $otraFuncion['nombre'] }}</span>
                                                                <strong class="text-nowrap">{{ $fmt($otraFuncion['horas']) }} h</strong>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light border mt-3 mb-0 small"><strong>Estado:</strong> {{ $estado['detalle'] ?? 'Sin detalle de estado.' }}</div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $tableColspan }}" class="text-center text-muted py-4">No se encontraron docentes vigentes para este establecimiento y año.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
