@php
    $conciliacionGeneral = \App\Support\DotacionConciliacionGeneral::build(
        $docentes ?? [], $resumen ?? [], $proceso2027 ?? [], $sobredotacion ?? []
    );
    $fmtConciliacion = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $terminoConciliacion = fn ($value) => ($value < 0 ? '− ' : '+ ').$fmtConciliacion(abs($value));
@endphp

@if ($conciliacionGeneral)
    <section class="card dotacion-section mb-4" aria-labelledby="conciliacion-general-titulo">
        <div class="dotacion-section-header">
            <div class="dotacion-eyebrow">Plan general y funciones</div>
            <h2 id="conciliacion-general-titulo" class="h5 fw-bold mb-1"><i class="bi bi-calculator me-1" aria-hidden="true"></i>Conciliación de horas de contrato</h2>
            <p class="small text-muted mb-0">Compara el contrato docente vigente, su ocupación efectiva y el máximo autorizado del bloque general.</p>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach ([
                    'contrato' => 'Contrato docente vigente',
                    'asignadas' => 'Asignadas a docentes',
                    'reservadas' => 'Reservadas para otras funciones',
                    'saldo_neto' => 'Saldo contractual neto',
                ] as $campo => $etiqueta)
                    <div class="col-md-6 col-xl-3">
                        <div class="bg-light border rounded-4 p-3 h-100">
                            <div class="small text-muted">{{ $etiqueta }}</div>
                            <div class="fs-4 fw-bold">{{ $fmtConciliacion($conciliacionGeneral[$campo]) }} h</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 mb-0"><strong>Cuadratura contractual:</strong> {{ $fmtConciliacion($conciliacionGeneral['contrato']) }} = {{ $fmtConciliacion($conciliacionGeneral['asignadas']) }} asignadas + {{ $fmtConciliacion($conciliacionGeneral['reservadas']) }} reservadas {{ $terminoConciliacion($conciliacionGeneral['saldo_neto']) }} de saldo.</p>
            <p class="small text-muted mt-1 mb-0">Las asignadas incluyen plan convertido por docente, trabajo colaborativo PIE del bloque general y funciones normativas o declaradas. Las reservas se descuentan una sola vez.</p>
            @if (abs($conciliacionGeneral['saldo_nomina'] - $conciliacionGeneral['saldo_neto']) > 0.01)
                <p class="small text-muted mt-2 mb-0">La nómina revisable muestra {{ $fmtConciliacion($conciliacionGeneral['saldo_nomina']) }} h. Puede diferir del saldo neto porque excluye contratos protegidos y no compensa vacantes de un docente con sobreasignaciones de otro.</p>
            @endif
            @if ($conciliacionGeneral['maximo'] !== null)
                <div class="border rounded-4 p-3 mt-3">
                    <div class="fw-semibold">{{ $conciliacionGeneral['diferencia_maximo'] >= 0 ? 'Exceso contractual respecto del máximo autorizado' : 'Contrato vigente por debajo del máximo autorizado' }}</div>
                    <div class="fs-4 fw-bold">{{ $fmtConciliacion(abs($conciliacionGeneral['diferencia_maximo'])) }} h</div>
                    <div class="small text-muted">{{ $fmtConciliacion($conciliacionGeneral['contrato']) }} h contratadas − {{ $fmtConciliacion($conciliacionGeneral['maximo']) }} h autorizadas = {{ $fmtConciliacion($conciliacionGeneral['diferencia_maximo']) }} h.</div>
                </div>
            @else
                <p class="small text-muted mt-3 mb-0">Máximo autorizado pendiente de configuración.</p>
            @endif
            <div class="alert alert-info rounded-4 mt-3 mb-0" role="note">
                <div class="fw-semibold"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Cobertura por asistentes de la educación (AAEE): {{ $fmtConciliacion($conciliacionGeneral['cobertura_aaee']) }} h</div>
                <p class="small mb-0 mt-1">Estas horas completan necesidades obligatorias del establecimiento y se incluyen en su cobertura. Se imputan al asistente que las realiza y no consumen horas del contrato de ningún docente. Por eso pueden quedar horas docentes sin asignar aunque la necesidad esté cubierta.</p>
            </div>
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <caption class="caption-top text-body fw-semibold">Plan y trabajo colaborativo PIE del bloque general</caption>
                    <thead class="table-light"><tr><th scope="col">Base de cálculo</th><th scope="col" class="text-end">Horas contrato</th></tr></thead>
                    <tbody>
                        <tr><th scope="row" class="fw-normal">Asignado y convertido por docente</th><td class="text-end">{{ $fmtConciliacion($conciliacionGeneral['plan_individual']) }}</td></tr>
                        <tr><th scope="row" class="fw-normal">Necesidad institucional consolidada por curso</th><td class="text-end">{{ $fmtConciliacion($conciliacionGeneral['plan_institucional']) }}</td></tr>
                        <tr class="table-light"><th scope="row">Diferencia entre ambas bases</th><td class="text-end fw-bold">{{ $fmtConciliacion($conciliacionGeneral['diferencia_plan']) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0">La conversión 65/35 o 60/40 se aplica al total aula de cada docente. Su suma puede diferir de la conversión por curso según la distribución de las asignaturas. Si el plan aún no está completo, la diferencia también incluye horas pendientes de cobertura.</p>
            @if ($conciliacionGeneral['maximo'] !== null)
                <div class="bg-light border rounded-4 p-3 mt-3">
                    <div class="fw-semibold">Conciliación del saldo con el máximo</div>
                    <div class="mt-1">{{ $fmtConciliacion($conciliacionGeneral['saldo_neto']) }} h de saldo = {{ $fmtConciliacion($conciliacionGeneral['diferencia_maximo']) }} h de diferencia contractual {{ $terminoConciliacion($conciliacionGeneral['cobertura_aaee']) }} h AAEE {{ $terminoConciliacion(-$conciliacionGeneral['diferencia_plan']) }} h de diferencia del plan{{ abs($conciliacionGeneral['otros_ajustes']) > 0.01 ? ' '.$terminoConciliacion($conciliacionGeneral['otros_ajustes']).' h de otros ajustes de cobertura y margen autorizado' : '' }}.</div>
                </div>
            @endif
        </div>
    </section>
@endif
