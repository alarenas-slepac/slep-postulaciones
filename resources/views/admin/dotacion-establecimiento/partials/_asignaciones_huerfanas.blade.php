@if ($asignacionesHuerfanas->isNotEmpty())
    @php
        $horasFantasma = (float) ($resumenAsignacion['horas_fantasma'] ?? $asignacionesHuerfanas->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)));
        $docentesAfectados = (int) ($resumenAsignacion['docentes_horas_fantasma'] ?? $asignacionesHuerfanas
            ->pluck('docente_rut_normalizado')
            ->filter()
            ->unique()
            ->count());

        $planesNormativosFantasma = $asignacionesHuerfanas->filter(fn ($row) => (bool) ($row->es_plan_huerfano ?? false));
        $cantidadPlanesNormativosFantasma = (int) ($resumenAsignacion['planes_fantasma'] ?? $planesNormativosFantasma->count());
        $horasPlanesNormativosFantasma = (float) ($resumenAsignacion['horas_planes_fantasma'] ?? $planesNormativosFantasma->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)));

        $planesEstudioFantasma = $asignacionesHuerfanas->filter(fn ($row) => (bool) ($row->es_plan_estudio_huerfano ?? false));
        $cantidadPlanesEstudioFantasma = (int) ($resumenAsignacion['plan_estudio_fantasma'] ?? $planesEstudioFantasma->count());
        $horasAulaPlanesEstudioFantasma = (float) ($resumenAsignacion['horas_aula_plan_estudio_fantasma'] ?? $planesEstudioFantasma->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0)));
        $horasContratoPlanesEstudioFantasma = (float) ($resumenAsignacion['horas_contrato_plan_estudio_fantasma'] ?? $planesEstudioFantasma->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)));
    @endphp

    <div class="card dotacion-section mb-4 border-danger" id="horas-fantasma">
        <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-3" style="background:#fff7f7;">
            <div class="d-flex align-items-start gap-3">
                <span class="dotacion-icon" style="width:40px;height:40px;background:#dc3545;"><i class="bi bi-exclamation-octagon"></i></span>
                <div>
                    <div class="dotacion-eyebrow text-danger">Revisión manual requerida</div>
                    <h2 class="h5 fw-bold mb-1 text-danger">Horas fantasmas</h2>
                    <div class="text-muted small">
                        Son asignaciones activas cuya función, plan normativo, asignatura o componente del plan de estudio ya no existe. Continúan sumando horas hasta que se eliminen manualmente.
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <span class="badge rounded-pill text-bg-danger">{{ $asignacionesHuerfanas->count() }} asignación(es)</span>
                <span class="badge rounded-pill text-bg-light border text-danger">{{ $fmt($horasFantasma) }} h contrato</span>

                @if ($cantidadPlanesEstudioFantasma > 0)
                    <span class="badge rounded-pill text-bg-danger">
                        <i class="bi bi-book-half"></i>
                        {{ $cantidadPlanesEstudioFantasma }} plan estudio · {{ $fmt($horasAulaPlanesEstudioFantasma) }} h aula
                    </span>
                @endif

                @if ($cantidadPlanesNormativosFantasma > 0)
                    <span class="badge rounded-pill text-bg-warning">
                        <i class="bi bi-journal-x"></i>
                        {{ $cantidadPlanesNormativosFantasma }} plan normativo · {{ $fmt($horasPlanesNormativosFantasma) }} h contrato
                    </span>
                @endif

                <span class="badge rounded-pill text-bg-light border">{{ $docentesAfectados }} docente(s)</span>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-danger rounded-4 d-flex gap-2 align-items-start">
                <i class="bi bi-info-circle mt-1"></i>
                <div>
                    <strong>Estas horas no se eliminan automáticamente.</strong>
                    El bloque detecta cualquier registro con <code>tipo_asignacion = plan_estudio</code>, sin limitar el subtipo. Esto incluye <code>plan_comun_formacion_general</code> y los demás subtipos curriculares. Revisa cada fila antes de eliminarla.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Docente</th>
                            <th>Asignatura, función o plan eliminado</th>
                            <th>Tipo / subtipo</th>
                            <th>Subvención</th>
                            <th class="text-end">Horas aula</th>
                            <th class="text-end">Horas contrato</th>
                            <th>Motivo detectado</th>
                            <th class="text-end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asignacionesHuerfanas as $asig)
                            @php
                                $esPlanEstudio = (bool) ($asig->es_plan_estudio_huerfano ?? false);
                                $esPlanNormativo = (bool) ($asig->es_plan_huerfano ?? false);
                                $subtipoLabel = trim((string) ($asig->subtipo_asignacion ?? '')) !== ''
                                    ? str_replace('_', ' ', (string) $asig->subtipo_asignacion)
                                    : 'Sin subtipo';
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $asig->docente_nombre ?: 'Docente sin nombre' }}</div>
                                    <div class="small text-muted">{{ $asig->docente_rut }}</div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="fw-semibold">{{ $asig->asignatura_nombre ?: $asig->tipo_label }}</div>

                                        @if ($esPlanEstudio)
                                            <span class="badge rounded-pill text-bg-danger">
                                                <i class="bi bi-book-half"></i> Plan de estudio eliminado o inexistente
                                            </span>
                                        @elseif ($esPlanNormativo)
                                            <span class="badge rounded-pill text-bg-warning">
                                                <i class="bi bi-journal-x"></i> Plan normativo eliminado o inexistente
                                            </span>
                                        @endif
                                    </div>

                                    @if ($asig->establecimiento_curso_id)
                                        <div class="small text-muted">Curso interno: {{ $asig->establecimiento_curso_id }}</div>
                                    @endif
                                    @if ($asig->observacion)
                                        <div class="small text-muted">{{ $asig->observacion }}</div>
                                    @endif
                                    <div class="small text-muted">Registrada: {{ optional($asig->created_at)->format('d/m/Y H:i') ?: 'Sin fecha' }}</div>
                                </td>
                                <td>
                                    @if ($esPlanEstudio)
                                        <span class="badge rounded-pill text-bg-danger">Plan de estudio</span>
                                        <div class="small text-muted mt-1">{{ $subtipoLabel }}</div>
                                    @elseif ($esPlanNormativo)
                                        <span class="badge rounded-pill text-bg-warning">Plan normativo</span>
                                        <div class="small text-muted mt-1">{{ $subtipoLabel }}</div>
                                    @else
                                        <span class="badge rounded-pill text-bg-light border">{{ $asig->tipo_label }}</span>
                                        <div class="small text-muted mt-1">{{ $subtipoLabel }}</div>
                                    @endif
                                </td>
                                <td>{{ $asig->subvencion ?: 'Sin clasificar' }}</td>
                                <td class="text-end fw-bold {{ $esPlanEstudio ? 'text-danger' : 'text-muted' }}">
                                    {{ $esPlanEstudio ? $fmt($asig->horas_plan_pedagogicas) : '—' }}
                                </td>
                                <td class="text-end fw-bold text-danger">{{ $fmt($asig->horas_contrato) }}</td>
                                <td class="small text-danger">{{ $asig->motivo_huerfana }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.destroy', [$establecimiento, $asig]) }}" onsubmit="return confirm('¿Eliminar definitivamente esta asignación fantasma? Las horas se descontarán de la carga del docente.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">
                                            <i class="bi bi-trash"></i> Eliminar horas
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
