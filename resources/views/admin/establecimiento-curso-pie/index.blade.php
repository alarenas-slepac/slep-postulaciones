@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Estudiantes PIE por curso</h1>
            <div class="text-muted small">Registro de estudiantes con NEET y NEEP por establecimiento, año y curso/sección.</div>
        </div>
        @if ($canEditPie)
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-success" href="{{ route('admin.establecimiento-curso-pie.template') }}">
                    <i class="bi bi-file-earmark-excel"></i> Plantilla
                </a>
                <a class="btn btn-outline-primary" href="{{ route('admin.establecimiento-curso-pie.import') }}">
                    <i class="bi bi-upload"></i> Carga masiva
                </a>
                <a class="btn btn-primary" href="{{ route('admin.establecimiento-curso-pie.create') }}">
                    <i class="bi bi-plus-circle"></i> Nuevo registro
                </a>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Observaciones de carga</div>
            <ul class="mb-0 small">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">Registros PIE</div>
                    <div class="fs-3 fw-bold">{{ number_format((int) $summary['registros'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">NEET</div>
                    <div class="fs-3 fw-bold text-primary">{{ number_format((int) $summary['neet'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">NEEP</div>
                    <div class="fs-3 fw-bold text-danger">{{ number_format((int) $summary['neep'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">Total estudiantes PIE</div>
                    <div class="fs-3 fw-bold text-success">{{ number_format((int) $summary['total'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">TOTAL CRONO</div>
                    <div class="fs-3 fw-bold text-success">{{ $formatMinutes((int) ($summary['total_crono_minutos'] ?? 0)) }}</div>
                    <div class="text-muted small">Suma de horas cronológicas calculadas.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">PROF EDUC. DIF</div>
                    <div class="fs-3 fw-bold text-primary">{{ $formatMinutes((int) ($summary['prof_educ_dif_minutos'] ?? 0)) }}</div>
                    <div class="fw-semibold text-primary">{{ number_format((float) ($summary['educadores_equivalentes'] ?? 0), 2, ',', '.') }} educadores diferenciales equivalentes</div>
                    <div class="fw-semibold text-primary">{{ (int) ($summary['educadores_redondeados'] ?? 0) }} educadores diferenciales redondeados</div>

                    <div class="border rounded-3 bg-light p-2 mt-2">
                        <div class="small text-muted">Horas de contrato asociadas</div>
                        <div class="h5 fw-bold text-info mb-0">{{ $summary['horas_contrato_label'] ?? '00:00' }}</div>
                        <div class="small fw-semibold text-info">Bolsa para Dotación Establecimiento: {{ (int) ($summary['horas_contrato_bolsa'] ?? 0) }} horas</div>
                    </div>

                    @if (!empty($summary['educadores_por_proporcion']))
                        <div class="mt-2 small">
                            @foreach ($summary['educadores_por_proporcion'] as $proporcionLabel => $detalleProporcion)
                                <div class="text-muted mb-1">
                                    <strong>{{ $proporcionLabel }}</strong>:
                                    {{ $formatMinutes((int) ($detalleProporcion['minutos'] ?? 0)) }} PROF EDUC. DIF
                                    <span class="text-nowrap">/ base {{ $detalleProporcion['base_label'] ?? '—' }}</span>
                                    <span class="text-nowrap">/ {{ number_format((float) ($detalleProporcion['equivalentes'] ?? 0), 2, ',', '.') }} equiv.</span>
                                    <span class="text-nowrap">/ {{ $detalleProporcion['contrato_label'] ?? '00:00' }} contrato</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="text-muted small">Las horas PROF EDUC. DIF se convierten a contrato de 44 horas según la proporción aplicada a cada curso. La bolsa contractual suma los valores exactos y redondea una sola vez hacia arriba.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">PAEC</div>
                    <div class="fs-3 fw-bold text-danger">{{ $formatMinutes((int) ($summary['pae_minutos'] ?? 0)) }}</div>
                    <div class="text-muted small">Profesionales Asistentes de la Educación.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info shadow-sm mb-3">
        <div class="fw-semibold"><i class="bi bi-clock-history"></i> Proporción lectiva/no lectiva referencial</div>
        <div class="small">
            Se aplica <strong>60/40</strong> sólo a cursos de <strong>1° a 4° Básico</strong> cuyo establecimiento tenga <strong>80% o más de alumnos prioritarios</strong> en el año filtrado. El resto de cursos aplica <strong>65/35</strong>. La referencia visible usa jornada de contrato de <strong>44 horas cronológicas</strong>.
        </div>
    </div>

    @php
        $establecimientosPlanos = collect($establecimientos)->flatMap(function ($itemsEstablecimientos, $comuna) {
            return collect($itemsEstablecimientos)->map(function ($establecimiento) use ($comuna) {
                $label = trim(($establecimiento->rbd ?? '') . ' — ' . ($establecimiento->nombre_establecimiento ?? '') . (($comuna ?? '') ? ' (' . $comuna . ')' : ''));

                return [
                    'id' => (string) $establecimiento->id,
                    'label' => $label,
                    'search' => mb_strtolower(trim(($establecimiento->rbd ?? '') . ' ' . ($establecimiento->nombre_establecimiento ?? '') . ' ' . ($comuna ?? '')), 'UTF-8'),
                ];
            });
        })->values();
        $establecimientoSeleccionado = $establecimientosPlanos->firstWhere('id', (string) $establecimientoId);
    @endphp

    <form method="GET" class="card card-body shadow-sm mb-3 filtros-pie-card">
        <div class="row g-3 align-items-start">
            <div class="col-xl-3 col-lg-4 col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="RBD, establecimiento o curso">
            </div>
            <div class="col-xl-2 col-lg-2 col-md-3">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="anio" value="{{ $anio }}" placeholder="2026">
            </div>
            <div class="col-xl-4 col-lg-6 col-md-12">
                <label class="form-label">Establecimiento</label>
                <div class="position-relative">
                    <i class="bi bi-search establecimiento-filter-icon text-muted"></i>
                    <input
                        type="text"
                        id="establecimiento-filter-search"
                        class="form-control establecimiento-filter-input"
                        list="establecimientos-registrados-list"
                        value="{{ $establecimientoSeleccionado['label'] ?? '' }}"
                        placeholder="Todos los establecimientos registrados"
                        autocomplete="off"
                        data-default-label="{{ $establecimientoSeleccionado['label'] ?? '' }}"
                        @disabled($activeRole === 'funcionario_directivo_estab')
                    >
                    <input type="hidden" id="establecimiento-filter-id" name="establecimiento_id" value="{{ $establecimientoId }}">
                    <datalist id="establecimientos-registrados-list">
                        @foreach ($establecimientosPlanos as $establecimientoOpcion)
                            <option value="{{ $establecimientoOpcion['label'] }}" data-id="{{ $establecimientoOpcion['id'] }}" data-search="{{ $establecimientoOpcion['search'] }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="form-text small">Sólo se listan establecimientos con registros PIE para el año filtrado.</div>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-4">
                <label class="form-label">Curso</label>
                <select class="form-select" name="curso_id">
                    <option value="">Todos</option>
                    @foreach ($cursos as $curso)
                        <option value="{{ $curso->id }}" @selected((string) $cursoId === (string) $curso->id)>{{ $curso->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-4">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    @foreach ($estados as $key => $label)
                        <option value="{{ $key }}" @selected($estado === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-4">
                <label class="form-label">Registro</label>
                <select class="form-select" name="registro">
                    <option value="">Todos</option>
                    <option value="con" @selected($registro === 'con')>Con PIE</option>
                    <option value="sin" @selected($registro === 'sin')>Sin PIE</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-curso-pie.index') }}">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>RBD</th>
                        <th>Establecimiento</th>
                        <th>Curso/sección</th>
                        <th class="text-end">Matrícula</th>
                        <th class="text-end">NEET</th>
                        <th class="text-end">NEEP</th>
                        <th class="text-end">Total PIE</th>
                        <th class="text-end">% Prioritarios</th>
                        <th>Prop. doc.</th>
                        <th class="text-end">Aula ref. 44h</th>
                        <th class="text-end">No lect. ref. 44h</th>
                        <th>Régimen</th>
                        <th class="text-end">Total crono</th>
                        <th class="text-end">Prof. Educ. Dif.</th>
                        <th class="text-end">PAEC</th>
                        <th>Estado</th>
                        <th class="text-end" style="width: 230px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $neet = (int) ($item->necesidades_transitorias ?? 0);
                            $neep = (int) ($item->necesidades_permanentes ?? 0);
                            $total = (int) ($item->total_pie ?? 0);
                            $estadoActual = $item->estado ?: 'sin_registro';
                            $regimenCalculo = match ($item->regimen_calculo ?? null) {
                                'con_jec' => 'CON JEC',
                                'sin_jec' => 'SIN JEC',
                                default => '—',
                            };
                            $horasDoc = $item->horas_no_lectivas ?? [];
                            $prioritarios = $horasDoc['porcentaje_prioritarios'] ?? null;
                            $badgeProp = ($horasDoc['proporcion'] ?? null) === '60_40' ? 'text-bg-success' : 'text-bg-light';
                            $badge = match ($estadoActual) {
                                'validado' => 'text-bg-success',
                                'en_revision' => 'text-bg-info',
                                'observado' => 'text-bg-warning',
                                'borrador' => 'text-bg-secondary',
                                default => 'text-bg-light',
                            };
                        @endphp
                        <tr>
                            <td>{{ $item->rbd ?: $item->establecimiento_rbd }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->establecimiento_nombre }}</div>
                                <div class="text-muted small">{{ $item->establecimiento_comuna ?: 'Sin comuna' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $item->nombre_seccion ?: trim(($item->curso_nombre ?? '').' '.($item->letra ?? '')) }}</div>
                                <div class="text-muted small">{{ $item->plan_nombre ?: 'Sin plan asociado' }}</div>
                            </td>
                            <td class="text-end">{{ number_format((int) ($item->matricula ?? 0), 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ number_format($neet, 0, ',', '.') }}</td>
                            <td class="text-end fw-semibold">{{ number_format($neep, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">{{ number_format($total, 0, ',', '.') }}</td>
                            <td class="text-end">
                                {{ $prioritarios !== null ? number_format((float) $prioritarios, 2, ',', '.') . '%' : '—' }}
                            </td>
                            <td>
                                <span class="badge {{ $badgeProp }} border">{{ $horasDoc['proporcion_label'] ?? '—' }}</span>
                                <div class="text-muted small">{{ $horasDoc['motivo'] ?? '' }}</div>
                            </td>
                            <td class="text-end">
                                <div class="fw-semibold">{{ $formatDocenteMinutes($horasDoc['horas_aula_cronologicas_minutos'] ?? null) }}</div>
                                <div class="text-muted small">{{ $horasDoc['horas_aula_pedagogicas'] ?? '—' }} HA</div>
                            </td>
                            <td class="text-end fw-semibold">{{ $formatDocenteMinutes($horasDoc['horas_no_lectivas_minutos'] ?? null) }}</td>
                            <td>
                                <span class="badge text-bg-light border">{{ $item->pie_id ? $regimenCalculo : '—' }}</span>
                                @if ($item->pie_id && $item->calculo_observacion)
                                    <div class="text-muted small">{{ $item->calculo_observacion }}</div>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">{{ $item->pie_id ? $formatMinutes($item->total_crono_minutos) : '—' }}</td>
                            <td class="text-end">{{ $item->pie_id ? $formatMinutes($item->prof_educ_dif_minutos) : '—' }}</td>
                            <td class="text-end">{{ $item->pie_id ? $formatMinutes($item->pae_minutos) : '—' }}</td>
                            <td>
                                <span class="badge {{ $badge }}">{{ $item->pie_id ? ($estados[$estadoActual] ?? $estadoActual) : 'Sin registro' }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    @if ($item->pie_id)
                                        <a class="btn btn-sm btn-outline-info" href="{{ route('admin.establecimiento-curso-pie.show', $item->pie_id) }}">Ver</a>
                                        @if ($canEditPie)
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.establecimiento-curso-pie.edit', $item->pie_id) }}">Editar</a>
                                        @endif
                                        @if ($activeRole === 'admin')
                                            <form method="POST" action="{{ route('admin.establecimiento-curso-pie.destroy', $item->pie_id) }}" onsubmit="return confirm('¿Eliminar este registro PIE?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                            </form>
                                        @endif
                                    @elseif ($canEditPie)
                                        <a class="btn btn-sm btn-primary" href="{{ route('admin.establecimiento-curso-pie.create', ['establecimiento_curso_id' => $item->establecimiento_curso_id]) }}">Registrar PIE</a>
                                    @else
                                        <span class="text-muted small">Sin acciones</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="17" class="text-center text-muted py-4">No hay cursos/secciones para mostrar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($items, 'links'))
            <div class="card-body">
                {{ $items->links() }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .filtros-pie-card .form-label {
        margin-bottom: .35rem;
    }

    .establecimiento-filter-input {
        padding-left: 2.25rem;
    }

    .establecimiento-filter-icon {
        left: .85rem;
        pointer-events: none;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 3;
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('establecimiento-filter-search');
        const hidden = document.getElementById('establecimiento-filter-id');
        const dataList = document.getElementById('establecimientos-registrados-list');

        if (!input || !hidden || !dataList) {
            return;
        }

        const options = Array.from(dataList.options).map(function (option) {
            return {
                id: option.dataset.id || '',
                label: option.value || '',
            };
        });

        const syncEstablecimiento = function () {
            const typed = (input.value || '').trim();

            if (!typed) {
                hidden.value = '';
                return;
            }

            const selected = options.find(function (option) {
                return option.label === typed;
            });

            hidden.value = selected ? selected.id : '';
        };

        input.addEventListener('input', syncEstablecimiento);
        input.addEventListener('change', syncEstablecimiento);
    });
</script>
@endpush
