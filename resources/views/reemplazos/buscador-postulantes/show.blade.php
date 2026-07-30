@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
            <h3 class="m-0">Ficha del usuario</h3>
            <div class="text-muted">
                {{ $user?->nombre_completo ?? '—' }}
                <span class="ms-2">({{ $user?->rut ?? '—' }})</span>
                @if ($user?->trabaja_en_otro_lugar)
                    <span class="badge bg-success ms-2"><i class="bi bi-telephone-outbound-fill"></i> Informó trabajo externo</span>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('reemplazos.buscador-postulantes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al buscador
            </a>

            <a href="{{ route('reemplazos.buscador-postulantes.perfil.view', $profile) }}" class="btn btn-outline-primary"
                target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-text"></i> Ver perfil (PDF)
            </a>

            <a href="{{ route('reemplazos.buscador-postulantes.perfil.pdf', $profile) }}" class="btn btn-primary">
                <i class="bi bi-file-earmark-arrow-down"></i> Descargar perfil
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-person-badge"></i> Datos principales</h5>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="text-muted small">Nombre completo</div>
                            <div class="fw-semibold">{{ $user?->nombre_completo ?? '—' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">RUT</div>
                            <div class="fw-semibold">{{ $user?->rut ?? '—' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Email</div>
                            <div class="fw-semibold">{{ $profile->email_contacto ?? ($user?->email ?? '—') }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Teléfono(s)</div>
                            <div class="fw-semibold">
                                {{ $profile->telefono1 ?? '—' }}
                                @if (!empty($profile->telefono2))
                                    <span class="text-muted">—</span> {{ $profile->telefono2 }}
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Comuna de desempeño</div>
                            <div class="fw-semibold">
                                @if ($user?->communes && $user->communes->count())
                                    {{ $user->communes->pluck('name')->implode(', ') }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Área de desempeño</div>
                            <div class="fw-semibold">
                                {{ $profile->areaDesempeno?->nombre ?? ($profile->area_desempeno_nombre ?? '—') }}
                                @if (!empty($profile->area_desempeno_id))
                                    <span class="text-muted small">(ID: {{ $profile->area_desempeno_id }})</span>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Estamento</div>
                            <div class="fw-semibold">{{ $profile->estamento ?? '—' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Años de experiencia</div>
                            <div class="fw-semibold">{{ $profile->anios_experiencia ?? '—' }}</div>
                        </div>
                    </div>

                    <hr>

                    <div class="row g-2">
                        <div class="col-md-12">
                            <div class="text-muted small">Dirección</div>
                            <div class="fw-semibold">
                                {{ $profile->direccion ?? '—' }}
                                @if ($profile->comuna)
                                    <span class="text-muted">·</span> {{ $profile->comuna->name }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="mt-3">
                        <h5 class="card-title mb-3"><i class="bi bi-shield-exclamation"></i> Restricciones para ejercer</h5>

                        @php
                            $restrictionContext = $restrictionContext ?? [];
                            $hasCourtRestriction = (bool) ($restrictionContext['court_active'] ?? false);
                            $hasManualRestriction = (bool) ($restrictionContext['manual_active'] ?? false);
                        @endphp

                        @if (!$hasCourtRestriction && !$hasManualRestriction)
                            <div class="alert alert-success mb-0">
                                <div class="fw-semibold">No posee restricciones</div>
                                <div class="small mb-0">Este usuario no registra restricciones judiciales ni manuales vigentes.</div>
                            </div>
                        @else
                            <div class="d-grid gap-3">
                                @if ($hasCourtRestriction)
                                    <div class="alert alert-danger mb-0">
                                        <div class="fw-semibold mb-2">Bloqueo judicial vigente</div>
                                        <div class="row g-2 small">
                                            <div class="col-md-6">
                                                <div class="text-muted">Nombre en nómina</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['court_name'] ?: '—' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted">RUN nómina</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['court_run'] ?: '—' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted">Juzgado origen</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['court_juzgado_origen'] ?: '—' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted">RIT</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['court_rit'] ?: '—' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted">Fecha fallo</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['court_fecha_fallo'] ?: '—' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted">Inhabilidad</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['court_inhabilidad'] ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if ($hasManualRestriction)
                                    <div class="alert alert-warning mb-0">
                                        <div class="fw-semibold mb-2">Bloqueo manual vigente</div>
                                        <div class="row g-2 small">
                                            <div class="col-md-6">
                                                <div class="text-muted">Fecha inicio</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['manual_start'] ?: '—' }}</div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="text-muted">Fecha término</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['manual_end'] ?: '—' }}</div>
                                            </div>
                                            <div class="col-12">
                                                <div class="text-muted">Motivo / comentario</div>
                                                <div class="fw-semibold text-dark">{{ $restrictionContext['manual_comment'] ?: '—' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="mt-3">
                        @if (\Illuminate\Support\Facades\Route::has('reemplazos.documents.forUser') && auth()->user()->can('viewAny', \App\Models\UserDocument::class))
                            <a href="{{ route('reemplazos.documents.forUser', ['user' => $user, 'return_to' => url()->full()]) }}"
                                class="btn btn-outline-secondary">
                                <i class="bi bi-folder2"></i> Ver documentos del usuario
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @php
                $contratosActivos = $profile->contratosLaboralesActivos ?? collect();
                $contratoActivo = $profile->contratoLaboralActivo;
                $ultimaVinculacionLaboral = $ultimaVinculacionLaboral ?? $profile->ultimaVinculacionLaboral;
            @endphp

            <div class="card mb-3 {{ $user?->trabaja_en_otro_lugar ? 'border-success' : '' }}">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <h5 class="card-title mb-0"><i class="bi bi-telephone-outbound"></i> Situación laboral informada</h5>
                        @if ($user?->trabaja_en_otro_lugar)
                            <span class="badge bg-success">Informó trabajo externo</span>
                        @else
                            <span class="badge bg-secondary">Sin marca</span>
                        @endif
                    </div>

                    <div class="small text-muted mb-2">
                        Este dato es administrativo y no reemplaza la vinculación laboral activa. Se usa cuando el postulante/funcionario informa por contacto que actualmente trabaja en otro lugar, aunque no indique institución.
                    </div>

                    @if ($user?->trabaja_en_otro_lugar)
                        <div class="border rounded p-2 bg-light small mb-2">
                            <div><span class="fw-semibold">Observación:</span> {{ $user->trabaja_en_otro_lugar_observacion ?: 'Sin observación registrada.' }}</div>
                            @if ($user->trabaja_en_otro_lugar_marcado_en)
                                <div class="text-muted mt-1">
                                    Marcado el {{ $user->trabaja_en_otro_lugar_marcado_en->format('d-m-Y H:i') }}
                                    @if ($user->trabajoExternoMarcadoPor)
                                        por {{ $user->trabajoExternoMarcadoPor->nombre_completo ?? 'usuario' }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($canManageTrabajoExterno ?? false)
                        <form method="POST" action="{{ route('reemplazos.buscador-postulantes.trabajo-externo.update', $profile) }}" class="mt-3">
                            @csrf
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="trabaja_en_otro_lugar" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="trabajaEnOtroLugar" name="trabaja_en_otro_lugar" value="1" @checked(old('trabaja_en_otro_lugar', $user?->trabaja_en_otro_lugar ? 1 : 0))>
                                <label class="form-check-label fw-semibold" for="trabajaEnOtroLugar">
                                    Informó que actualmente trabaja en otro lugar
                                </label>
                            </div>
                            <label class="form-label small mb-1" for="trabajaEnOtroLugarObservacion">Observación</label>
                            <textarea id="trabajaEnOtroLugarObservacion" name="trabaja_en_otro_lugar_observacion" class="form-control" rows="3" maxlength="1000" placeholder="Ej: Informó telefónicamente que se encuentra trabajando; no indica establecimiento.">{{ old('trabaja_en_otro_lugar_observacion', $user?->trabaja_en_otro_lugar_observacion) }}</textarea>
                            <div class="form-text">No es obligatorio indicar lugar de trabajo. Si se desmarca el check, la observación se limpiará.</div>
                            <button type="submit" class="btn btn-outline-success btn-sm mt-2">
                                <i class="bi bi-save"></i> Guardar situación informada
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card mb-3 {{ $contratosActivos->count() ? 'border-warning' : '' }}">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-briefcase"></i> Vinculación laboral activa
                        </h5>
                        @if ($contratosActivos->count())
                            <span class="badge bg-warning text-dark">Trabajando</span>
                        @else
                            <span class="badge bg-secondary">Sin registro activo</span>
                        @endif
                    </div>

                    @if ($contratosActivos->count())
                        <div class="small mb-2 text-muted">
                            Puede existir más de un establecimiento asociado. Las horas se registran por establecimiento.
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($contratosActivos as $contrato)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="fw-semibold">
                                                {{ $contrato->establecimiento?->nombre_establecimiento ?? 'Establecimiento no informado' }}
                                            </div>
                                            <div class="text-muted small">
                                                RBD {{ $contrato->establecimiento?->rbd ?? '—' }}
                                                @if ($contrato->establecimiento?->comuna)
                                                    · {{ $contrato->establecimiento->comuna }}
                                                @endif
                                            </div>
                                        </div>
                                        <span class="badge bg-light text-dark border align-self-start">
                                            {{ $contrato->cantidad_horas ?? '—' }} hrs.
                                        </span>
                                    </div>
                                    <div class="row g-2 small mt-1">
                                        <div class="col-md-6">
                                            <span class="text-muted">Tipo:</span>
                                            <span class="fw-semibold">{{ $contrato->tipo_contrato ?? '—' }}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <span class="text-muted">Término:</span>
                                            <span class="fw-semibold">{{ optional($contrato->fecha_termino)->format('d-m-Y') ?? '—' }}</span>
                                        </div>
                                        <div class="col-12 text-muted">
                                            Registrado el {{ optional($contrato->created_at)->format('d-m-Y H:i') ?? '—' }}
                                            @if ($contrato->registradoPor)
                                                por {{ $contrato->registradoPor->nombre_completo ?? 'usuario' }}
                                            @endif
                                        </div>
                                    </div>

                                    @if ($canManageContratoLaboral ?? false)
                                        <button type="button" class="btn btn-outline-danger btn-sm mt-2" data-bs-toggle="modal"
                                            data-bs-target="#desactivarContratoLaboralModal{{ $contrato->id }}">
                                            <i class="bi bi-x-circle"></i> Marcar este establecimiento como no vigente
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-muted small mb-2">
                            No existe registro vigente de contrato, contrata, plazo fijo u honorarios para este usuario.
                        </div>

                        @if ($ultimaVinculacionLaboral)
                            <div class="border rounded p-2 bg-light small">
                                <div class="fw-semibold mb-1">Última vinculación registrada</div>
                                <div>{{ $ultimaVinculacionLaboral->resumen }}</div>
                                @if ($ultimaVinculacionLaboral->fecha_termino && $ultimaVinculacionLaboral->fecha_termino->isPast())
                                    <div class="text-muted mt-1">Finalizada automáticamente por fecha de término.</div>
                                @endif
                                @if (!$ultimaVinculacionLaboral->activo && $ultimaVinculacionLaboral->motivo_desactivacion)
                                    <div class="text-muted mt-1"><span class="fw-semibold">Motivo de desactivación:</span> {{ $ultimaVinculacionLaboral->motivo_desactivacion }}</div>
                                    @if ($ultimaVinculacionLaboral->desactivado_at)
                                        <div class="text-muted">
                                            Desactivada el {{ $ultimaVinculacionLaboral->desactivado_at->format('d-m-Y H:i') }}
                                            @if ($ultimaVinculacionLaboral->desactivadoPor)
                                                por {{ $ultimaVinculacionLaboral->desactivadoPor->nombre_completo ?? 'usuario' }}
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif
                    @endif

                    @if ($canManageContratoLaboral ?? false)
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                                data-bs-target="#contratoLaboralModal">
                                <i class="bi bi-pencil-square"></i>
                                {{ $contratosActivos->count() ? 'Actualizar información' : 'Agregar información' }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            @if (($reemplazosActivos ?? collect())->count())
                <div class="card mb-3 border-info">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <h5 class="card-title mb-0"><i class="bi bi-person-check"></i> Reemplazo activo</h5>
                            <span class="badge bg-info text-dark">En reemplazo</span>
                        </div>
                        <div class="list-group list-group-flush small">
                            @foreach ($reemplazosActivos as $s)
                                <div class="list-group-item px-0">
                                    <div class="fw-semibold">{{ $s->establecimiento?->nombre_establecimiento ?? 'Establecimiento no informado' }}</div>
                                    <div class="text-muted">
                                        {{ $s->numero_solicitud ?? ('#' . $s->id) }} ·
                                        Término: {{ optional($s->fecha_termino)->format('d-m-Y') ?? '—' }} ·
                                        Estado: {{ str_replace('_', ' ', $s->estado ?? '—') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3"><i class="bi bi-clipboard2-check"></i> Solicitudes asociadas</h5>

                    @if (($solicitudes ?? collect())->count() === 0)
                        <div class="text-muted">Este usuario no registra solicitudes asociadas (o no ha sido propuesto
                            en solicitudes).</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>N°</th>
                                        <th>Establecimiento</th>
                                        <th>Área</th>
                                        <th class="text-nowrap">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($solicitudes as $s)
                                        <tr>
                                            <td class="text-nowrap">
                                                @if (auth()->user()->canModule('gestion.solicitudes-reemplazo') &&
                                                        \Illuminate\Support\Facades\Route::has('gestion.solicitudes-reemplazo.show'))
                                                    <a
                                                        href="{{ route('gestion.solicitudes-reemplazo.show', ['solicitud' => $s, 'return_to' => url()->full()]) }}">
                                                        {{ $s->numero_solicitud ?? '#' . $s->id }}
                                                    </a>
                                                @else
                                                    {{ $s->numero_solicitud ?? '#' . $s->id }}
                                                @endif
                                            </td>
                                            <td>{{ $s->establecimiento?->nombre_establecimiento ?? '—' }}</td>
                                            <td>{{ $s->areaDesempeno?->nombre ?? '—' }}</td>
                                            <td class="text-nowrap">
                                                <span class="badge bg-secondary">{{ $s->estado ?? '—' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted mt-2">
                            Se muestran las últimas {{ ($solicitudes ?? collect())->count() }} solicitudes.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($canManageContratoLaboral ?? false)
        @php
            $contratosActivos = $profile->contratosLaboralesActivos ?? collect();
            $contratoActivo = $profile->contratoLaboralActivo;
            $vinculacionesOld = old('vinculaciones');
            if (!is_array($vinculacionesOld)) {
                $vinculacionesOld = [];
                if ($contratosActivos->count()) {
                    foreach ($contratosActivos as $contratoLaboralActivoItem) {
                        $vinculacionesOld[] = [
                            'establecimiento_id' => $contratoLaboralActivoItem->establecimiento_id,
                            'cantidad_horas' => $contratoLaboralActivoItem->cantidad_horas,
                        ];
                    }
                } else {
                    $vinculacionesOld[] = ['establecimiento_id' => '', 'cantidad_horas' => ''];
                }
            }
        @endphp
        <div class="modal fade" id="contratoLaboralModal" tabindex="-1" aria-labelledby="contratoLaboralModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="{{ route('reemplazos.buscador-postulantes.contrato.store', $profile) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="contratoLaboralModalLabel">
                                <i class="bi bi-briefcase"></i> Registrar vinculación laboral activa
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info small">
                                Esta información marca visualmente al usuario como actualmente trabajando. Puedes registrar más de un establecimiento, indicando horas por cada uno.
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tipo Contrato</label>
                                    <select name="tipo_contrato" id="tipoContratoLaboral" class="form-select" required>
                                        @foreach (['Contrata', 'Plazo Fijo', 'Honorarios', 'Indefinido'] as $tipoContrato)
                                            <option value="{{ $tipoContrato }}" {{ old('tipo_contrato', $contratoActivo?->tipo_contrato) === $tipoContrato ? 'selected' : '' }}>
                                                {{ $tipoContrato }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Fecha de Término</label>
                                    <input type="date" name="fecha_termino" id="fechaTerminoLaboral" class="form-control"
                                        value="{{ old('fecha_termino', optional($contratoActivo?->fecha_termino)->format('Y-m-d')) }}" required>
                                    <div class="form-text" id="fechaTerminoLaboralHelp">Para contrato Indefinido, la fecha de término queda deshabilitada.</div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="fw-semibold">Establecimientos y horas</div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="addVinculacionRow">
                                    <i class="bi bi-plus-circle"></i> Agregar establecimiento
                                </button>
                            </div>

                            <div id="vinculacionesContainer" class="d-flex flex-column gap-2">
                                @foreach ($vinculacionesOld as $idx => $vinculacion)
                                    <div class="border rounded p-2 vinculacion-row">
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-8">
                                                <label class="form-label small mb-1">Establecimiento</label>
                                                <select name="vinculaciones[{{ $idx }}][establecimiento_id]" class="form-select vinculacion-establecimiento" required>
                                                    <option value="">Seleccione establecimiento</option>
                                                    @foreach (($establecimientosPorComuna ?? collect()) as $comuna => $establecimientos)
                                                        <optgroup label="{{ $comuna }}">
                                                            @foreach ($establecimientos as $establecimiento)
                                                                <option value="{{ $establecimiento->id }}" {{ (string) ($vinculacion['establecimiento_id'] ?? '') === (string) $establecimiento->id ? 'selected' : '' }}>
                                                                    RBD {{ $establecimiento->rbd }} · {{ $establecimiento->nombre_establecimiento }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Horas</label>
                                                <input type="number" name="vinculaciones[{{ $idx }}][cantidad_horas]" class="form-control vinculacion-horas" min="1" max="60" step="1"
                                                    value="{{ $vinculacion['cantidad_horas'] ?? '' }}" required>
                                            </div>
                                            <div class="col-md-1 d-grid">
                                                <button type="button" class="btn btn-outline-danger remove-vinculacion-row" title="Quitar fila">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text mt-2">Si actualizas la información, las vinculaciones activas anteriores quedarán cerradas y serán reemplazadas por este nuevo conjunto.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar información
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @foreach ($contratosActivos as $contrato)
            <div class="modal fade" id="desactivarContratoLaboralModal{{ $contrato->id }}" tabindex="-1" aria-labelledby="desactivarContratoLaboralModalLabel{{ $contrato->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('reemplazos.buscador-postulantes.contrato.destroy', [$profile, $contrato]) }}">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header">
                                <h5 class="modal-title" id="desactivarContratoLaboralModalLabel{{ $contrato->id }}">
                                    <i class="bi bi-x-circle"></i> Desactivar vinculación laboral
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning small">
                                    Esta acción marcará como no vigente la vinculación con {{ $contrato->establecimiento?->nombre_establecimiento ?? 'el establecimiento seleccionado' }}. Debes indicar el motivo.
                                </div>
                                <label class="form-label">Motivo de desactivación</label>
                                <textarea name="motivo_desactivacion" class="form-control" rows="4" minlength="5" maxlength="500" required>{{ old('motivo_desactivacion') }}</textarea>
                                <div class="form-text">Ejemplo: término anticipado, renuncia, cambio de contrato, error de registro u otro antecedente administrativo.</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-x-circle"></i> Desactivar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const container = document.getElementById('vinculacionesContainer');
                const addButton = document.getElementById('addVinculacionRow');
                if (!container || !addButton) return;

                const reindexRows = () => {
                    container.querySelectorAll('.vinculacion-row').forEach((row, index) => {
                        const establecimiento = row.querySelector('.vinculacion-establecimiento');
                        const horas = row.querySelector('.vinculacion-horas');

                        if (establecimiento) {
                            establecimiento.name = `vinculaciones[${index}][establecimiento_id]`;
                        }

                        if (horas) {
                            horas.name = `vinculaciones[${index}][cantidad_horas]`;
                        }
                    });
                };

                addButton.addEventListener('click', function () {
                    const first = container.querySelector('.vinculacion-row');
                    if (!first) return;

                    const clone = first.cloneNode(true);
                    const establecimiento = clone.querySelector('.vinculacion-establecimiento');
                    const horas = clone.querySelector('.vinculacion-horas');

                    if (establecimiento) {
                        establecimiento.selectedIndex = 0;
                        establecimiento.value = '';
                        establecimiento.querySelectorAll('option').forEach((option) => option.removeAttribute('selected'));
                    }

                    if (horas) {
                        horas.value = '';
                    }

                    container.appendChild(clone);
                    reindexRows();
                    if (establecimiento) establecimiento.focus();
                });

                const tipoContrato = document.getElementById('tipoContratoLaboral');
                const fechaTermino = document.getElementById('fechaTerminoLaboral');

                function syncFechaTerminoContrato() {
                    if (!tipoContrato || !fechaTermino) {
                        return;
                    }

                    const esIndefinido = tipoContrato.value === 'Indefinido';
                    fechaTermino.disabled = esIndefinido;
                    fechaTermino.required = !esIndefinido;

                    if (esIndefinido) {
                        fechaTermino.value = '';
                    }
                }

                if (tipoContrato) {
                    tipoContrato.addEventListener('change', syncFechaTerminoContrato);
                    syncFechaTerminoContrato();
                }

                container.addEventListener('click', function (event) {
                    const button = event.target.closest('.remove-vinculacion-row');
                    if (!button) return;
                    if (container.querySelectorAll('.vinculacion-row').length <= 1) return;
                    button.closest('.vinculacion-row').remove();
                    reindexRows();
                });
            });
        </script>
    @endif
@endsection
