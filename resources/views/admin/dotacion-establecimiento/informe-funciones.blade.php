@extends('layouts.app')

@push('styles')
    @vite(['resources/css/dotacion-establecimiento.css'])
@endpush

@section('content')
@php $fmt = fn ($valor) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($valor); @endphp
<div class="dotacion-workspace">
    <header class="card shadow-sm mb-4">
        <div class="card-body p-4 d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="small text-muted fw-semibold mb-2"><i class="bi bi-table text-primary me-1" aria-hidden="true"></i> Dotación establecimiento · Informe territorial</div>
                <h1 class="h2 fw-bold mb-2">Dotación por funciones {{ $anio }}</h1>
                <p class="text-muted mb-0">Docentes y horas de contrato por establecimiento, con reservas y horas sin asignar identificadas.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'export_excel' => 1, 'anio' => $anio, 'q' => $q, 'comuna' => $comuna]) }}" aria-describedby="informe-excel-ayuda"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Descargar Excel</a>
                <a class="btn btn-outline-primary rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'export_planes' => 1, 'anio' => $anio, 'q' => $q, 'comuna' => $comuna]) }}" aria-describedby="informe-planes-ayuda"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Excel planes de estudio</a>
                <a class="btn btn-outline-secondary rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', ['anio' => $anio, 'q' => $q, 'comuna' => $comuna]) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver a dotación</a>
            </div>
        </div>
    </header>

    <p id="informe-excel-ayuda" class="small text-muted mb-4">El Excel incluye todos los establecimientos de los filtros aplicados, con las hojas «Dotación por funciones» y «Conciliación», aunque no estén en esta página.</p>
    <p id="informe-planes-ayuda" class="small text-muted mb-4">«Excel planes de estudio» consolida una fila por establecimiento de los filtros aplicados: matrícula, cursos, horas plan, contrato equivalente, trabajo colaborativo PIE y modalidad JEC (ambas cuando corresponda).</p>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert"><strong>Revise los filtros:</strong><ul class="mb-0">@foreach ($errors->all() as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul></div>
    @endif

    <section class="card shadow-sm mb-4" aria-labelledby="informe-filtros-titulo">
        <div class="card-header bg-white"><h2 id="informe-filtros-titulo" class="h5 fw-bold mb-0"><i class="bi bi-funnel me-1" aria-hidden="true"></i> Consultar informe</h2></div>
        <form method="GET" action="{{ route('admin.dotacion-establecimiento.index') }}" class="card-body p-4">
            <input type="hidden" name="informe_funciones" value="1">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold" for="informe-funciones-anio">Año</label>
                    <select id="informe-funciones-anio" name="anio" class="form-select">
                        @foreach ([2026, 2027] as $opcion)<option value="{{ $opcion }}" @selected($anio === $opcion)>{{ $opcion }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="informe-funciones-q">Buscar establecimiento</label>
                    <input id="informe-funciones-q" name="q" class="form-control" value="{{ $q }}" maxlength="150" placeholder="RBD, nombre o comuna">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="informe-funciones-comuna">Comuna</label>
                    <select id="informe-funciones-comuna" name="comuna" class="form-select">
                        <option value="">Todas las comunas</option>
                        @foreach ($comunas as $opcion)<option value="{{ $opcion }}" @selected($comuna === $opcion)>{{ $opcion }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-search me-1" aria-hidden="true"></i> Consultar</button>
                    <a class="btn btn-outline-secondary rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'anio' => $anio]) }}">Limpiar filtros</a>
                </div>
            </div>
        </form>
    </section>

    @if ($errores > 0)
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i> No fue posible calcular {{ $errores }} establecimiento(s). Se identifican en la tabla y quedan fuera de los totales de esta página.</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([['Contrato completo', 'contrato_total', 'Contrato original de la nómina; incluye reservas, saldos y horas excluidas por situaciones.'], ['Horas asignadas', 'asignadas', 'Incluye otras funciones y el redondeo de Parvularia.'], ['Horas reservadas', 'reservadas', 'Pendientes de vincular a una función.'], ['Horas sin asignar', 'sin_asignar', 'Saldo por docente, sin compensar sobreasignaciones.']] as [$label, $key, $ayuda])
            <div class="col-sm-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body p-4"><div class="fw-semibold text-muted small">{{ $label }} · página</div><div class="fs-3 fw-bold my-2">{{ $fmt($totales[$key]) }} h</div><p class="small text-muted mb-0">{{ $ayuda }}</p></div></div></div>
        @endforeach
    </div>

    <section class="card shadow-sm mb-4" aria-labelledby="informe-tabla-titulo">
        <div class="card-header bg-white d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div><h2 id="informe-tabla-titulo" class="h5 fw-bold mb-1">Distribución por establecimiento</h2><p class="small text-muted mb-0">{{ $establecimientos->count() }} de {{ $establecimientos->total() }} establecimientos. Cada consulta muestra hasta 10 registros.</p></div>
            <span class="badge rounded-pill text-bg-light border">Consulta: {{ $generadoEn->format('d/m/Y H:i') }}</span>
        </div>
        <div class="table-responsive" tabindex="0" role="region" aria-labelledby="informe-tabla-titulo">
            <table class="table table-bordered align-middle mb-0 small">
                <caption class="px-3">Horas de contrato asignadas por función. El contrato original completo y sus ajustes se concilian en «Ver conciliación».</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col" rowspan="2">RBD</th><th scope="col" rowspan="2">Establecimiento educacional</th>
                        @foreach ($grupos as $label)<th scope="colgroup" colspan="2" class="text-center">{{ $label }}</th>@endforeach
                        <th scope="col" rowspan="2" class="text-end">N.º total docentes</th>
                        <th scope="col" rowspan="2" class="text-end">Total horas docentes<br><span class="fw-normal">Contrato completo</span></th>
                        <th scope="col" rowspan="2" class="text-end">Horas SEP {{ $anio }}<br><span class="fw-normal">Complementarias</span></th>
                    </tr>
                    <tr>@foreach ($grupos as $label)<th scope="col" class="text-end">N.º docentes</th><th scope="col" class="text-end">Horas contrato</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($establecimientos as $fila)
                        <tr data-informe-rbd="{{ $fila['rbd'] }}">
                            <th scope="row">{{ $fila['rbd'] }}</th>
                            <td><a class="fw-semibold" href="{{ route('admin.dotacion-establecimiento.show', [$fila['establecimiento_id'], 'anio' => $anio]) }}">{{ $fila['nombre'] }}</a><div class="text-muted">{{ $fila['comuna'] ?: 'Sin comuna' }}</div>@unless ($fila['error'])<div class="text-muted mt-1">Base contractual: {{ $fila['periodos'] ? implode(', ', $fila['periodos']) : 'Sin contratos vigentes' }}</div>@endunless</td>
                            @if ($fila['error'])
                                <td colspan="11"><span class="badge text-bg-danger">Cálculo no disponible</span> Revise la dotación del establecimiento y vuelva a consultar.</td>
                            @else
                                @foreach ($grupos as $key => $label)<td class="text-end">{{ $fila['grupos'][$key]['docentes'] }}</td><td class="text-end">{{ $fmt($fila['grupos'][$key]['horas']) }}</td>@endforeach
                                <td class="text-end fw-bold">{{ $fila['docentes_total'] }}</td><td class="text-end fw-bold">{{ $fmt($fila['contrato_total']) }}</td>
                                <td class="text-end">{{ $fmt($fila['sep']) }}@if ($fila['sep_sin_desglose'] > 0)<div class="small text-warning">Revisar {{ $fmt($fila['sep_sin_desglose']) }} h sin desglose de financiamiento</div>@endif</td>
                            @endif
                        </tr>
                        @unless ($fila['error'])
                            <tr><td colspan="13" class="bg-light">
                                <details class="p-2">
                                    <summary class="fw-semibold text-primary">Ver conciliación · RBD {{ $fila['rbd'] }} · {{ $fmt($fila['reservadas']) }} h reservadas · {{ $fmt($fila['sin_asignar']) }} h sin asignar</summary>
                                    <div class="pt-3">
                                        <div class="row g-3 mb-3">
                                            @foreach ([['Asignadas en las cuatro funciones', $fila['asignadas'] - $fila['otras']], ['Otras funciones asignadas', $fila['otras']], ['Reservadas sin función', $fila['reservadas']], ['Sin asignar', $fila['sin_asignar']], ['Sobreasignadas', $fila['sobreasignadas']], ['Redondeo incluido en Aula', $fila['redondeo']], ['Excluidas por situaciones docentes', $fila['horas_excluidas']], ['Contrato considerado por dotación', $fila['contrato_considerado']]] as [$label, $valor])
                                                <div class="col-sm-6 col-lg-4"><div class="small text-muted">{{ $label }}</div><strong>{{ $fmt($valor) }} h</strong></div>
                                            @endforeach
                                        </div>
                                        <p class="mb-2"><strong>Contrato completo:</strong> {{ $fmt($fila['asignadas']) }} asignadas + {{ $fmt($fila['reservadas']) }} reservadas + {{ $fmt($fila['sin_asignar']) }} sin asignar + {{ $fmt($fila['horas_excluidas']) }} excluidas por situaciones − {{ $fmt($fila['sobreasignadas']) }} sobreasignadas = <strong>{{ $fmt($fila['contrato_total']) }} h</strong>.</p>
                                        <p class="small text-muted mb-0">Base contractual: {{ $fila['periodos'] ? implode(', ', $fila['periodos']) : 'Sin contratos docentes vigentes' }}. @if (collect($fila['periodos'])->contains(fn ($periodo) => !str_ends_with($periodo, '/'.$anio))) Para {{ $anio }} se utiliza el padrón disponible indicado como respaldo; las asignaciones corresponden a {{ $anio }}. @endif Contrato original: {{ $fmt($fila['contrato_original']) }} h; horas excluidas por situaciones del año: {{ $fmt($fila['horas_excluidas']) }} h.</p>
                                    </div>
                                </details>
                            </td></tr>
                        @endunless
                    @empty
                        <tr><td colspan="13" class="text-center p-4"><i class="bi bi-search text-muted me-1" aria-hidden="true"></i> No se encontraron establecimientos. Ajuste la búsqueda o limpie los filtros.</td></tr>
                    @endforelse
                </tbody>
                @if ($establecimientos->isNotEmpty())
                    <tfoot class="table-light fw-bold"><tr><th scope="row" colspan="2">Total de esta página{{ $errores ? ' (parcial)' : '' }}</th>@foreach ($grupos as $key => $label)<td class="text-end">{{ $totales['grupos'][$key]['docentes'] }}</td><td class="text-end">{{ $fmt($totales['grupos'][$key]['horas']) }}</td>@endforeach<td class="text-end">{{ (int) $totales['docentes_total'] }}</td><td class="text-end">{{ $fmt($totales['contrato_total']) }}</td><td class="text-end">{{ $fmt($totales['sep']) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
        @if ($establecimientos->hasPages())<div class="card-footer bg-white">{{ $establecimientos->links() }}</div>@endif
    </section>

    <section class="card shadow-sm" aria-labelledby="informe-criterios-titulo"><div class="card-body p-4">
        <h2 id="informe-criterios-titulo" class="h5 fw-bold mb-3"><i class="bi bi-info-circle me-1 text-primary" aria-hidden="true"></i> Cómo leer el informe</h2>
        <ul class="small mb-0">
            <li class="mb-2">Cada docente se cuenta una vez en el total del establecimiento. Puede aparecer en varias funciones; sus cantidades por función no se suman para obtener personas únicas. Los totales de página suman las nóminas de los establecimientos y pueden repetir docentes que trabajan en más de uno.</li>
            <li class="mb-2">Se utilizan las asignaciones del año consultado y la nómina de dotación, respetando su continuidad y retiros. El total mantiene el contrato original completo de esa nómina; las horas excluidas por situaciones se identifican aparte y no se presentan como disponibles para asignar. Los asistentes, cupos por contratar y asignaciones sin docente vigente quedan fuera de este informe docente.</li>
            <li class="mb-2">Inspectoría General, jefaturas UTP y Convivencia Educativa se presentan en función técnico-pedagógica. Aula incluye Educación Parvularia, acompañamiento, trabajo colaborativo PIE y planes normativos (CRA, PISE, Formación Ciudadana y otros). Coordinación PIE y la bolsa de Educación Diferencial se muestran en otras funciones PIE.</li>
            <li class="mb-2">Las horas de aula se muestran convertidas a contrato con las reglas de consolidación vigentes. El redondeo de Parvularia se incluye una sola vez en Aula y se identifica en la conciliación. Las reservas no se cuentan como asignaciones a funciones ni como saldo libre.</li>
            <li>SEP es información complementaria del padrón de respaldo de cada establecimiento: ya forma parte del contrato y no se suma nuevamente. Sólo se cuantifican líneas con financiamiento SEP explícito; los financiamientos mixtos sin desglose se señalan para revisión.</li>
        </ul>
    </div></section>
</div>
@endsection
