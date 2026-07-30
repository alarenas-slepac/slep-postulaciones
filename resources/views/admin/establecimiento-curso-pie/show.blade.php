@extends('layouts.app')

@section('content')
    @php
        $curso = $pie->establecimientoCurso;
        $estadoClass = match ($pie->estado) {
            'validado' => 'text-bg-success',
            'en_revision' => 'text-bg-info',
            'observado' => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
        $regimenOriginal = $curso?->regimen_jec ?: 'Sin dato';
    @endphp

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Detalle registro PIE</h1>
            <div class="text-muted small">{{ $pie->establecimiento?->rbd }} — {{ $pie->establecimiento?->nombre_establecimiento }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($canEditPie)
                <a class="btn btn-primary" href="{{ route('admin.establecimiento-curso-pie.edit', $pie) }}"><i class="bi bi-pencil"></i> Editar</a>
            @endif
            <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-curso-pie.index', ['anio' => $pie->anio]) }}">Volver</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div class="fw-semibold">Información del curso/sección</div>
                    <span class="badge {{ $estadoClass }}">{{ $pie->estadoLabel() }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Establecimiento</dt>
                        <dd class="col-sm-8">{{ $pie->establecimiento?->nombre_establecimiento }} · RBD {{ $pie->rbd }}</dd>
                        <dt class="col-sm-4">Curso/sección</dt>
                        <dd class="col-sm-8">{{ $curso?->nombre_seccion ?: trim(($curso?->curso?->nombre ?? '').' '.($curso?->letra ?? '')) }}</dd>
                        <dt class="col-sm-4">Año</dt>
                        <dd class="col-sm-8">{{ $pie->anio }}</dd>
                        <dt class="col-sm-4">Matrícula curso</dt>
                        <dd class="col-sm-8">{{ number_format((int) ($curso?->matricula ?? 0), 0, ',', '.') }}</dd>
                        <dt class="col-sm-4">Plan asociado</dt>
                        <dd class="col-sm-8">{{ $pie->planEstudio?->nombre_plan ?: $curso?->planEstudio?->nombre_plan ?: 'Sin plan asociado' }}</dd>
                        <dt class="col-sm-4">Observación</dt>
                        <dd class="col-sm-8">{{ $pie->observacion ?: 'Sin observación registrada.' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Resumen PIE</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>NEET</span>
                        <strong class="text-primary">{{ number_format((int) $pie->necesidades_transitorias, 0, ',', '.') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>NEEP</span>
                        <strong class="text-danger">{{ number_format((int) $pie->necesidades_permanentes, 0, ',', '.') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-3 fs-5">
                        <span>Total PIE</span>
                        <strong class="text-success">{{ number_format((int) $pie->total_pie, 0, ',', '.') }}</strong>
                    </div>
                    <div class="text-muted small">
                        Creado por: {{ $pie->creador?->nombre_completo ?: 'No registrado' }}<br>
                        Última actualización: {{ optional($pie->updated_at)->format('d-m-Y H:i') ?: '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card shadow-sm border-success">
                <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">Horas cronológicas PIE calculadas</div>
                        <div class="text-muted small">Cálculo según tabla de apoyo mínimo CON JEC/SIN JEC; EPJA aplica regla SIN JEC.</div>
                    </div>
                    <span class="badge text-bg-light border align-self-lg-center">Régimen aplicado: {{ $pie->regimenCalculoLabel() }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">Régimen curso</div>
                                <div class="fw-semibold">{{ $regimenOriginal }}</div>
                                <div class="text-muted small mt-1">NEET cálculo: {{ $pie->neet_calculo }} · NEEP cálculo: {{ $pie->neep_calculo }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">TOTAL CRONO</div>
                                <div class="fs-3 fw-bold text-success">{{ $pie->totalCronoLabel() }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">PROF EDUC. DIF</div>
                                <div class="fs-3 fw-bold text-primary">{{ $pie->profEducDifLabel() }}</div>
                                @php
                                    $baseEducadorDif = (int) (($horasNoLectivas['horas_aula_cronologicas_minutos'] ?? 1710) ?: 1710);
                                @endphp
                                <div class="fw-semibold text-primary">{{ $formatEducadoresDiferenciales($pie->prof_educ_dif_minutos, $baseEducadorDif) }} educadores diferenciales equivalentes</div>
                                <div class="fw-semibold text-primary">{{ $educadoresDiferencialesRedondeados($pie->prof_educ_dif_minutos, $baseEducadorDif) }} educadores diferenciales redondeados</div>
                                <div class="border rounded-3 bg-light p-2 mt-2">
                                    <div class="small text-muted">Horas de contrato asociadas</div>
                                    <div class="fw-bold text-info">{{ $contratoEducadorDif['horas_contrato_label'] ?? '00:00' }}</div>
                                    <div class="small text-info">Bolsa contractual del curso: {{ (int) ($contratoEducadorDif['horas_contrato_bolsa'] ?? 0) }} horas</div>
                                </div>
                                <div class="small text-muted mt-2"><strong>{{ $horasNoLectivas['proporcion_label'] ?? '65/35' }}</strong>: {{ $pie->profEducDifLabel() }} PROF EDUC. DIF / base {{ $formatDocenteMinutes($baseEducadorDif) }} / contrato referencial 44:00.</div>
                                <div class="text-muted small">La conversión contractual se calcula con la proporción del curso. En Dotación Establecimiento se suman los valores exactos de todos los cursos y se redondea una sola vez hacia arriba.</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">PAEC</div>
                                <div class="fs-3 fw-bold text-danger">{{ $pie->paeLabel() }}</div>
                            </div>
                        </div>
                    </div>
                    @if ($pie->calculo_observacion)
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle"></i> {{ $pie->calculo_observacion }}
                        </div>
                    @endif
                    <div class="text-muted small mt-3">
                        Último cálculo: {{ optional($pie->calculado_at)->format('d-m-Y H:i') ?: '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-white d-flex flex-column flex-lg-row justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">Horas docentes lectivas / no lectivas referenciales</div>
                        <div class="text-muted small">Regla basada en porcentaje de alumnos prioritarios del establecimiento y curso de 1° a 4° Básico.</div>
                    </div>
                    <span class="badge text-bg-light border align-self-lg-center">Proporción aplicada: {{ $horasNoLectivas['proporcion_label'] ?? '—' }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">% alumnos prioritarios</div>
                                <div class="fs-4 fw-bold text-primary">
                                    {{ ($horasNoLectivas['porcentaje_prioritarios'] ?? null) !== null ? number_format((float) $horasNoLectivas['porcentaje_prioritarios'], 2, ',', '.') . '%' : '—' }}
                                </div>
                                <div class="text-muted small">Año {{ $pie->anio }}</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">Referencia contrato</div>
                                <div class="fs-4 fw-bold">{{ $horasNoLectivas['horas_contrato'] ?? 44 }} horas</div>
                                <div class="text-muted small">Jornada cronológica semanal.</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">Horas aula</div>
                                <div class="fs-4 fw-bold text-success">{{ $formatDocenteMinutes($horasNoLectivas['horas_aula_cronologicas_minutos'] ?? null) }}</div>
                                <div class="text-muted small">{{ $horasNoLectivas['horas_aula_pedagogicas'] ?? '—' }} horas pedagógicas de aula.</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="text-muted small">Horas no lectivas</div>
                                <div class="fs-4 fw-bold text-danger">{{ $formatDocenteMinutes($horasNoLectivas['horas_no_lectivas_minutos'] ?? null) }}</div>
                                <div class="text-muted small">Recreo: {{ $formatDocenteMinutes($horasNoLectivas['recreo_minutos'] ?? null) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-3">
                        <i class="bi bi-info-circle"></i> {{ $horasNoLectivas['motivo'] ?? 'Sin regla disponible.' }}
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-end">Contrato</th>
                                    <th class="text-end">HA aula</th>
                                    <th class="text-end">HC aula</th>
                                    <th class="text-end">Recreo</th>
                                    <th class="text-end">No lectivas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tablaHorasNoLectivas as $fila)
                                    <tr>
                                        <td class="text-end">{{ $fila->horas_contrato }}</td>
                                        <td class="text-end">{{ $fila->horas_aula_pedagogicas }}</td>
                                        <td class="text-end">{{ $formatDocenteMinutes($fila->horas_aula_cronologicas_minutos) }}</td>
                                        <td class="text-end">{{ $formatDocenteMinutes($fila->recreo_minutos) }}</td>
                                        <td class="text-end fw-semibold">{{ $formatDocenteMinutes($fila->horas_no_lectivas_minutos) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
