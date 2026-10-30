@php
    $anioHorasDocente = (int) ($anio ?? $docente['anio']);
    $asignacionesDelDocente = collect($docente['asignaciones'] ?? [])
        ->filter(fn ($fila) => data_get($fila, 'estado', 'activa') === 'activa')->values();
    $rolHorasDocente = $activeRole ?? auth()->user()?->activeRoleName();
    $horasDocenteSuspendidas = \App\Support\DotacionAsignacionSuspension::bloqueada($anioHorasDocente, $rolHorasDocente);
    $puedeEliminarHorasDocente = isset($establecimiento, $anio)
        && in_array($rolHorasDocente, ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'], true)
        && ! $horasDocenteSuspendidas && Route::has('admin.dotacion-establecimiento.asignaciones.docente.destroy');
    $asignacionesEliminables = $asignacionesDelDocente->filter(fn ($fila) => (int) data_get($fila, 'id') > 0
        && ! data_get($fila, 'asignacion_automatica', false));
    $formHorasId = $collapseId.'-eliminar-horas';
    $errorAsignacionesDocente = $errorAsignacionesDocente ?? false;
    $seleccionHorasAnterior = $errorAsignacionesDocente ? collect(old('asignaciones', []))->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->all() : [];
@endphp
<section class="dotacion-docente-horas mt-3" aria-labelledby="{{ $collapseId }}-horas-titulo" data-docente-horas
    data-docente-nombre="{{ $docente['nombre'] }}" data-docente-anio="{{ $anioHorasDocente }}">
    <div class="dotacion-docente-horas__header">
        <div>
            <h3 id="{{ $collapseId }}-horas-titulo"><i class="bi bi-clock-history text-primary" aria-hidden="true"></i> Horas asignadas</h3>
            <p class="form-text mb-0">Detalle de las asignaciones activas de {{ $docente['nombre'] }} en {{ $anioHorasDocente }}. Las horas de contrato se recalculan al retirar una asignación.</p>
        </div>
        <span class="badge rounded-pill text-bg-light border">{{ $asignacionesDelDocente->count() }} asignación(es)</span>
    </div>
    <div class="dotacion-docente-horas__body">
        @if ($errorAsignacionesDocente)
            <div class="alert alert-danger" role="alert">
                <strong>No se pudieron eliminar las asignaciones.</strong>
                <ul class="mb-0">@foreach ($errors->all() as $mensajeHoras)<li>{{ $mensajeHoras }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($horasDocenteSuspendidas)
            <p class="alert alert-info small">{{ \App\Support\DotacionAsignacionSuspension::mensaje() }}</p>
        @endif
        @if ($asignacionesDelDocente->isNotEmpty())
            <div class="d-flex flex-wrap gap-3 small mb-3">
                <span>Aula: <strong>{{ $fmt($asignacionesDelDocente->sum('horas_plan_pedagogicas')) }} h</strong></span>
                <span>Contrato asignado, incluidas reservas: <strong>{{ $fmt($asignacionesDelDocente->sum('horas_contrato')) }} h</strong></span>
            </div>
            @if ($puedeEliminarHorasDocente && $asignacionesEliminables->isNotEmpty())
                <div class="dotacion-docente-horas__actions mb-3">
                    <label class="form-check d-none mb-0" data-horas-select-all-wrap>
                        <input type="checkbox" class="form-check-input" data-horas-select-all>
                        <span class="form-check-label">Seleccionar todas las asignaciones eliminables</span>
                    </label>
                    <form id="{{ $formHorasId }}" method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.docente.destroy', $establecimiento) }}" data-horas-delete="bulk">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="anio" value="{{ $anioHorasDocente }}">
                        <input type="hidden" name="docente_rut" value="{{ $docente['rut'] }}">
                        <input type="hidden" name="detalle_asignaciones" value="1">
                        <button type="submit" class="btn btn-outline-danger" data-horas-delete-selected><i class="bi bi-trash" aria-hidden="true"></i> <span data-horas-bulk-label>Eliminar seleccionadas</span></button>
                    </form>
                </div>
                <p class="form-text" data-horas-selection-status role="status" aria-live="polite">Marque las asignaciones que desea eliminar de este docente.</p>
            @endif
            <div class="dotacion-docente-horas__table-wrap">
                <table class="table dotacion-docente-horas__table align-middle mb-0">
                    <caption class="visually-hidden">Asignaciones de {{ $docente['nombre'] }} en {{ $anioHorasDocente }}</caption>
                    <thead><tr>
                        @if ($puedeEliminarHorasDocente && $asignacionesEliminables->isNotEmpty())<th scope="col">Seleccionar</th>@endif
                        <th scope="col">Asignatura o función</th><th scope="col">Curso / bloque</th>
                        <th scope="col">Subvención / proporción</th><th scope="col" class="text-end">Horas aula</th>
                        <th scope="col" class="text-end">Horas contrato</th><th scope="col">Acción</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($asignacionesDelDocente as $filaHoras)
                        @php
                            $idHoras = (int) data_get($filaHoras, 'id');
                            $tipoHoras = (string) data_get($filaHoras, 'tipo_asignacion');
                            $tituloHoras = data_get($filaHoras, 'asignatura_nombre') ?: (\App\Models\DotacionDocenteAsignacion::TIPOS[$tipoHoras] ?? 'Asignación');
                            $tipoHorasLabel = \App\Models\DotacionDocenteAsignacion::TIPOS[$tipoHoras] ?? 'Asignación';
                            $necesidadHoras = ($necesidadesAsignadas ?? collect())->get(data_get($filaHoras, 'necesidad_key'), []);
                            $cursoHoras = $filaHoras instanceof \App\Models\DotacionDocenteAsignacion && $filaHoras->relationLoaded('establecimientoCurso') ? $filaHoras->establecimientoCurso : null;
                            $cursoNombreHoras = data_get($necesidadHoras, 'curso_label') ?: ($cursoHoras?->nombre_seccion ?: ($cursoHoras && $cursoHoras->relationLoaded('curso') ? $cursoHoras->curso?->nombre : null));
                            if ($cursoNombreHoras && $cursoHoras?->letra && ! str_ends_with($cursoNombreHoras, ' '.$cursoHoras->letra)) $cursoNombreHoras .= ' '.$cursoHoras->letra;
                            $bloqueHoras = data_get($necesidadHoras, 'bloque') ?: str_replace('_', ' ', (string) data_get($filaHoras, 'subtipo_asignacion'));
                            $eliminableHoras = $idHoras > 0 && ! data_get($filaHoras, 'asignacion_automatica', false);
                            $descripcionHoras = $tituloHoras.($cursoNombreHoras ? ' · '.$cursoNombreHoras : '').' · '.$fmt(data_get($filaHoras, 'horas_contrato', 0)).' h contrato';
                        @endphp
                        <tr data-horas-row="{{ $idHoras }}" data-horas-description="{{ $descripcionHoras }}">
                            @if ($puedeEliminarHorasDocente && $asignacionesEliminables->isNotEmpty())
                                <td data-label="Seleccionar">
                                    @if ($eliminableHoras)
                                        <input class="form-check-input" type="checkbox" name="asignaciones[]" value="{{ $idHoras }}" id="{{ $formHorasId }}-{{ $idHoras }}" form="{{ $formHorasId }}" data-horas-selection @checked(in_array($idHoras, $seleccionHorasAnterior, true))>
                                        <label class="visually-hidden" for="{{ $formHorasId }}-{{ $idHoras }}">Seleccionar {{ $descripcionHoras }}</label>
                                    @else<span class="text-muted small">Automática</span>@endif
                                </td>
                            @endif
                            <td data-label="Asignatura o función"><strong>{{ $tituloHoras }}</strong><div class="small text-muted">{{ $tipoHorasLabel }}</div>@if (data_get($filaHoras, 'observacion'))<div class="small mt-1">{{ data_get($filaHoras, 'observacion') }}</div>@endif</td>
                            <td data-label="Curso / bloque">{{ $cursoNombreHoras ?: 'Sin curso asociado' }}@if ($bloqueHoras)<div class="small text-muted">{{ ucfirst($bloqueHoras) }}</div>@endif</td>
                            <td data-label="Subvención / proporción">{{ data_get($filaHoras, 'subvencion') ?: 'Sin informar' }}@if (data_get($filaHoras, 'proporcion_aplicada'))<div class="small text-muted">{{ data_get($filaHoras, 'proporcion_aplicada') }}</div>@endif</td>
                            <td class="text-end" data-label="Horas aula">{{ data_get($filaHoras, 'horas_plan_pedagogicas') !== null ? $fmt(data_get($filaHoras, 'horas_plan_pedagogicas')).' h' : '—' }}</td>
                            <td class="text-end fw-semibold" data-label="Horas contrato">{{ $fmt(data_get($filaHoras, 'horas_contrato', 0)) }} h</td>
                            <td data-label="Acción">
                                @if ($puedeEliminarHorasDocente && $eliminableHoras)
                                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.docente.destroy', $establecimiento) }}" data-horas-delete="single">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="anio" value="{{ $anioHorasDocente }}">
                                        <input type="hidden" name="docente_rut" value="{{ $docente['rut'] }}">
                                        <input type="hidden" name="detalle_asignaciones" value="1">
                                        <input type="hidden" name="asignaciones[]" value="{{ $idHoras }}">
                                        <button type="submit" class="btn btn-outline-danger" aria-label="Eliminar {{ $descripcionHoras }} de {{ $docente['nombre'] }}"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
                                    </form>
                                @else<span class="small text-muted">{{ $eliminableHoras ? 'Sólo consulta' : 'Asignación automática' }}</span>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0" role="status">Este docente no tiene asignaciones activas en {{ $anioHorasDocente }}.</p>
        @endif
    </div>
</section>

@once
    @push('styles')
        @vite('resources/js/dotacion-docente-asignaciones.js')
    @endpush
    @push('scripts')
        <div class="modal fade" id="confirmar-eliminar-horas-docente" tabindex="-1" aria-labelledby="confirmar-eliminar-horas-docente-titulo" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content dotacion-docente-horas__modal">
                <div class="modal-header"><h2 class="modal-title fs-5" id="confirmar-eliminar-horas-docente-titulo">Eliminar asignaciones de horas</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body"><p data-horas-confirm-summary></p><ul data-horas-confirm-list></ul><p class="mb-0">Se recalcularán las horas y el saldo del docente. Las situaciones docentes se conservan. Esta acción no se puede deshacer.</p></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-danger" data-horas-confirm-delete><i class="bi bi-trash" aria-hidden="true"></i> Eliminar asignaciones</button></div>
            </div></div>
        </div>
    @endpush
@endonce
