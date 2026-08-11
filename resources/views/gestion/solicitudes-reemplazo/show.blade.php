@extends('layouts.app')

@section('content')
    @php
        use Illuminate\Support\Str;
        $returnTo = request('return_to');
        $backUrl =
            $returnTo && (Str::startsWith($returnTo, url('/')) || Str::startsWith($returnTo, '/'))
                ? $returnTo
                : route('gestion.solicitudes-reemplazo.index');
        $estatutoTitular = strtoupper(trim((string) ($s->funcionarioTitular?->estatuto ?? '')));
        $titularEsDocente = in_array($estatutoTitular, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($estatutoTitular, 'DOC');
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Solicitud de reemplazo #{{ $s->numero_solicitud }}</h1>
            <div class="text-muted">Estado: <span class="fw-semibold">{{ $s->estado }}</span></div>
        </div>
        <div class="d-flex gap-2">
            @if (!empty($lockedByOtContrato) && $s->estado === 'derivada_slep')
                <span class="badge bg-secondary align-self-center">Bloqueada: OT/Contrato generado</span>
            @endif

            @if (!empty($canInformarObservacion))
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalObservacionSlep">
                    {{ !empty($s->observacion_slep) ? 'Actualizar observación' : 'Informar observación' }}
                </button>
            @endif

            @if (!empty($canSlepActions))
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalReasignarPostulante">
                    Reasignar postulante / funcionario
                </button>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnularSolicitud">
                    Anular
                </button>
            @endif

            @if (!empty($canCerrarSolicitudDocente))
                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalCerrarSolicitudDocente">
                    Cerrar solicitud
                </button>
            @endif

            @if (!empty($canReabrirPlanificacion))
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalReabrirPlanificacion">
                    Reabrir validación Planificación
                </button>
            @endif

            @if (!empty($canReabrirUatp))
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalReabrirUatp">
                    Reabrir revisión UATP
                </button>
            @endif

            @if (!empty($s->contrato_trabajo_firmado_pdf_path))
                <a class="btn btn-outline-danger" href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo-firmado.download', $s) }}">
                    Descargar contrato firmado
                </a>
            @endif

            <a class="btn btn-outline-secondary" href="{{ $backUrl }}">Volver</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Revisa lo siguiente:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Modales SLEP: Reasignar postulante / funcionario / Anular
         derivada_slep: solo sin OT/Contrato
         aceptada: habilitados para corrección operativa --}}
    @if (!empty($canInformarObservacion))
        <div class="modal fade" id="modalObservacionSlep" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Informar observación SLEP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.observacion.store', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-info">
                                Esta observación se enviará por correo al funcionario del establecimiento que realizó la solicitud y quedará visible en el resumen de la solicitud.
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Observación <span class="text-danger">*</span></label>
                                <textarea name="observacion_slep" class="form-control" rows="5" required placeholder="Escribe la observación que debe conocer el establecimiento...">{{ old('observacion_slep', $s->observacion_slep) }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-warning">Enviar observación</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($canSlepActions))
        {{-- Reasignar postulante / funcionario --}}
        <div class="modal fade" id="modalReasignarPostulante" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reasignar postulante / funcionario</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.slep.reasignar-postulante', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="text-muted small">Postulante / funcionario actual</div>
                                <div class="fw-semibold">
                                    @if ($s->postulante && $s->postulante->user)
                                        {{ $s->postulante->user->rut ?? '—' }} — {{ $s->postulante->user->full_name ?? '—' }}
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Seleccionar postulante / funcionario <span class="text-danger">*</span></label>
                                <select id="reasignar_postulant_profile_id" name="postulant_profile_id" class="form-select" required>
                                    {{-- Select2 AJAX --}}
                                </select>
                                <div id="reasignacionRestrictionAlert" class="alert alert-warning d-none mt-2 mb-0">
                                    <div class="fw-semibold">Advertencia de restricción manual</div>
                                    <div data-restriction-text class="small mb-0"></div>
                                </div>
                                <div class="form-text">
                                    Solo se permite reasignar si el postulante o funcionario cumple documentación (100%) y no tiene conflicto de período.
                                    @if (in_array($s->estado, ['aceptada', 'cerrado'], true))
                                        Al guardar, se regenerará la Orden de Trabajo y, si existe contrato asociado, se limpiará para que puedas generarlo nuevamente.
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Motivo de reasignación <span class="text-danger">*</span></label>
                                <textarea name="reasignacion_postulante_motivo" class="form-control" rows="4" required
                                    placeholder="Indica el motivo por el cual se reasigna el postulante..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar reasignación</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Anular solicitud --}}
        <div class="modal fade" id="modalAnularSolicitud" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Anular solicitud</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.slep.anular', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <div class="fw-semibold">Esta acción cambiará el estado de la solicitud a <code>anulada</code>.</div>
                                <div>
                                    Debe registrar un motivo.
                                    @if ($s->estado === 'derivada_slep')
                                        No se permite anular si ya existe Orden y/o Contrato generado.
                                    @else
                                        La anulación dejará la solicitud en estado <code>anulada</code> aun cuando ya exista Orden de Trabajo creada.
                                    @endif
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Motivo de anulación <span class="text-danger">*</span></label>
                                <textarea name="anulada_motivo" class="form-control" rows="4" required
                                    placeholder="Indica el motivo por el cual se anula la solicitud..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Anular solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($canCerrarSolicitudDocente))
        <div class="modal fade" id="modalCerrarSolicitudDocente" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Cerrar solicitud docente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.slep.cerrar-docente', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-success">
                                <div class="fw-semibold">Esta acción cambiará el estado de la solicitud a <code>cerrado</code>.</div>
                                <div>
                                    Disponible sólo para solicitudes docentes en estado <code>aceptada</code> que no tienen contrato de trabajo asociado.
                                    Para asistentes de la educación se mantiene el cierre mediante el flujo de contrato firmado.
                                </div>
                            </div>
                            <p class="mb-0">Confirma que deseas cerrar la solicitud de reemplazo #{{ $s->numero_solicitud }}.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Cerrar solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($canUatp && $s->estado === 'pendiente_uatp')
        <div class="modal fade" id="modalUatpAprobar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Aprobar solicitud UATP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.uatp.aprobar', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-info">
                                La solicitud quedará en <strong>Pendiente de Validación</strong> para revisión de la Subdirección de Planificación y Control de Gestión antes de pasar a GDP.
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Justificación Técnica de la aprobación <span class="text-danger">*</span></label>
                                <textarea name="justificacion_tecnica_uatp" class="form-control" rows="5" required placeholder="Fundamente técnicamente la aprobación de la solicitud...">{{ old('justificacion_tecnica_uatp', $s->justificacion_tecnica_uatp) }}</textarea>
                                <div class="form-text">Esta justificación quedará visible para la validación de Planificación y en la trazabilidad de la solicitud.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Aprobar y enviar a Validación</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($canGestionarAutorizacionDocente) && $autorizacionDocente)
        <div class="modal fade" id="modalAutorizacionDocente" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar autorización docente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.autorizacion-docente.numero.update', [$s, $autorizacionDocente]) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <div class="fw-semibold">Expediente enviado</div>
                                <div>
                                    Destino: {{ $autorizacionDocente->correo_destino ?: '—' }}
                                    @if ($autorizacionDocente->correo_enviado_at)
                                        — {{ cl_datetime($autorizacionDocente->correo_enviado_at, 'd/m/Y H:i') }}
                                    @endif
                                </div>
                                <div class="small mt-1">La autorización se mantiene como seguimiento paralelo y no interrumpe el flujo del reemplazo.</div>
                            </div>

                            <div class="mb-3">
                                <label for="numero_autorizacion" class="form-label fw-semibold">
                                    Número de autorización <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="numero_autorizacion"
                                    name="numero_autorizacion"
                                    class="form-control @error('numero_autorizacion') is-invalid @enderror"
                                    value="{{ old('numero_autorizacion', $autorizacionDocente->numero_autorizacion) }}"
                                    maxlength="120"
                                    required
                                >
                                @error('numero_autorizacion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="small text-muted">
                                Estado actual: <strong>{{ $autorizacionDocente->estado_label }}</strong>.
                                El estado se administra desde la bandeja de autorizaciones docentes.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="{{ route('gestion.autorizaciones-docentes.index') }}" class="btn btn-outline-primary">Ir a autorizaciones</a>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-primary">Guardar número</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($canPlaniReview))
        <div class="modal fade" id="modalPlaniValidar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Validar solicitud</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.plani.validar', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <p class="mb-0">La solicitud pasará a <strong>Pendiente GDP</strong> para continuar su tramitación.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Validar solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalPlaniRechazar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Rechazar solicitud</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.plani.rechazar', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Motivo de rechazo <span class="text-danger">*</span></label>
                                <textarea name="plani_motivo_rechazo" rows="4" class="form-control" required placeholder="Indica el motivo del rechazo de la validación...">{{ old('plani_motivo_rechazo', $s->plani_motivo_rechazo) }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Rechazar solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($canReabrirPlanificacion))
        <div class="modal fade" id="modalReabrirPlanificacion" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reabrir solicitud rechazada por Planificación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.plani.reabrir', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <div class="fw-semibold">Reapertura administrativa</div>
                                <div>La solicitud volverá al estado <code>pendiente_validacion</code> para que Planificación pueda evaluarla nuevamente. El motivo de reapertura quedará trazado en la solicitud.</div>
                            </div>

                            @if (!empty($s->plani_motivo_rechazo))
                                <div class="mb-3">
                                    <div class="form-label fw-semibold">Motivo de rechazo vigente</div>
                                    <div class="border rounded p-2 bg-light" style="white-space: pre-line;">{{ $s->plani_motivo_rechazo }}</div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Motivo de reapertura <span class="text-danger">*</span></label>
                                <textarea name="plani_reapertura_motivo" rows="4" class="form-control" required minlength="10" placeholder="Indique el fundamento administrativo o antecedente corregido que justifica reabrir la solicitud...">{{ old('plani_reapertura_motivo') }}</textarea>
                                <div class="form-text">Mínimo 10 caracteres. No se permite reabrir sin fundamento.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Reabrir solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($canReabrirUatp))
        <div class="modal fade" id="modalReabrirUatp" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reabrir solicitud rechazada por UATP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.uatp.reabrir', $s) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <div class="fw-semibold">Reapertura administrativa</div>
                                <div>La solicitud volverá al estado <code>pendiente_uatp</code> para que UATP pueda evaluarla nuevamente. El motivo de reapertura quedará trazado en la solicitud.</div>
                            </div>

                            @if (!empty($s->motivo_rechazo))
                                <div class="mb-3">
                                    <div class="form-label fw-semibold">Motivo de rechazo vigente UATP</div>
                                    <div class="border rounded p-2 bg-light" style="white-space: pre-line;">{{ $s->motivo_rechazo }}</div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Motivo de reapertura <span class="text-danger">*</span></label>
                                <textarea name="uatp_reapertura_motivo" rows="4" class="form-control" required minlength="10" placeholder="Indique el fundamento administrativo o antecedente corregido que justifica reabrir la solicitud...">{{ old('uatp_reapertura_motivo') }}</textarea>
                                <div class="form-text">Mínimo 10 caracteres. No se permite reabrir sin fundamento.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Reabrir solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @php
        $estTxt = trim(
            ($s->establecimiento->rbd ?? '') .
                ' - ' .
                ($s->establecimiento->nombre_establecimiento ?? ($s->establecimiento->nombre ?? '')),
        );
        $tit = $s->funcionarioTitular;
        $post = $s->postulante;
        $postUser = $post?->user;

        $tb = 0;
        $tm = 0;
        $tt = 0;
        $rb = 0;
        $rm = 0;
        $rt = 0;

        foreach ($s->jornadas ?? collect() as $j) {
            $tb += (float) $j->titular_basica;
            $tm += (float) $j->titular_media;
            $tt += (float) $j->titular_total;

            $rb += (float) $j->reemplazo_basica;
            $rm += (float) $j->reemplazo_media;
            $rt += (float) $j->reemplazo_total;
        }

        $fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    @endphp

    <div class="card mb-3">
        <div class="card-header fw-semibold">Datos generales</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="text-muted small">Establecimiento</div>
                    <div class="fw-semibold">{{ $estTxt ?: '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Creada</div>
                    <div class="fw-semibold">{{ cl_datetime($s->created_at, 'd/m/Y H:i') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Contacto</div>
                    <div class="fw-semibold">{{ $s->contacto_nombre ?: '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Fono</div>
                    <div class="fw-semibold">{{ $s->contacto_fono ?: '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Email</div>
                    <div class="fw-semibold">{{ $s->contacto_email ?: '—' }}</div>
                </div>
            </div>

        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">Funcionario a reemplazar</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">RUT</div>
                    <div class="fw-semibold">{{ $tit?->rut ?: '—' }}</div>
                </div>
                <div class="col-md-8">
                    <div class="text-muted small">Nombre</div>
                    <div class="fw-semibold">{{ $tit?->nombre ?: '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Estatuto</div>
                    <div class="fw-semibold">{{ $tit?->estatuto ?: '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Escalafón</div>
                    <div class="fw-semibold">{{ $tit?->escalafon ?: '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Área desempeño</div>
                    <div class="fw-semibold">{{ $s->areaDesempeno?->nombre ?? '—' }}</div>
                </div>
            </div>

            <hr class="my-3">

            <div class="fw-semibold mb-2">Distribución de jornada (titular)</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Financiamiento</th>
                            <th class="text-end">HRS BÁSICA</th>
                            <th class="text-end">HRS MEDIA</th>
                            <th class="text-end">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s->jornadas ?? collect() as $j)
                            <tr>
                                <td>{{ $j->financiamiento }}</td>
                                <td class="text-end">{{ $fmt($j->titular_basica) }}</td>
                                <td class="text-end">{{ $fmt($j->titular_media) }}</td>
                                <td class="text-end fw-semibold">{{ $fmt($j->titular_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td class="text-end">TOTAL</td>
                            <td class="text-end">{{ $fmt($tb) }}</td>
                            <td class="text-end">{{ $fmt($tm) }}</td>
                            <td class="text-end">{{ $fmt($tt) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>


            @if ($titularEsDocente)
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="text-muted small">Horas Aula Cronológicas (titular)</div>
                        <div class="fw-semibold">{{ $fmt($s->horas_aula_cronologicas_titular) }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Horas Aula Pedagógicas (titular)</div>
                        <div class="fw-semibold">{{ $fmt($s->horas_aula_pedagogicas_titular) }}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">Reemplazo solicitado</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Tipo</div>
                    <div class="fw-semibold">
                        {{ $s->tipo_reemplazo }}
                        @if ($s->tipo_reemplazo === 'Otras' && $s->tipo_reemplazo_otro)
                            <div class="text-muted small">{{ $s->tipo_reemplazo_otro }}</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Periodo</div>
                    <div class="fw-semibold">{{ optional($s->fecha_inicio)->format('d/m/Y') }} -
                        {{ optional($s->fecha_termino)->format('d/m/Y') }}</div>
                </div>

                <div class="col-md-6">
                    <div class="text-muted small">¿Propone postulante?</div>
                    <div class="fw-semibold">{{ $s->propone_reemplazo ? 'Sí' : 'No' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Continuidad</div>
                    <div class="fw-semibold">
                        @if ($s->propone_reemplazo)
                            {{ $s->continuidad ? 'Sí' : 'No' }}
                        @else
                            —
                        @endif
                    </div>
                </div>

                @if ($s->propone_reemplazo && $post)
                    <div class="col-md-12">
                        <div class="text-muted small">Postulante propuesto</div>
                        <div class="fw-semibold">
                            {{ $postUser?->rut ?? '—' }} — {{ $postUser?->full_name ?? '—' }}
                        </div>

                        @if (!empty($canGestionarAutorizacionDocente))
                            @php
                                $tituloEsPdf = $documentoTituloPostulante
                                    && (
                                        str_contains(strtolower((string) $documentoTituloPostulante->mime), 'pdf')
                                        || Str::endsWith(strtolower((string) $documentoTituloPostulante->path), '.pdf')
                                    );
                            @endphp

                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    target="_blank"
                                    rel="noopener"
                                    href="{{ route('reemplazos.buscador-postulantes.perfil.view', $post) }}"
                                >
                                    <i class="bi bi-person-vcard"></i> Ver ficha del postulante
                                </a>

                                @if ($documentoTituloPostulante)
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        target="_blank"
                                        rel="noopener"
                                        href="{{ $tituloEsPdf
                                            ? route('reemplazos.documents.preview', $documentoTituloPostulante)
                                            : route('reemplazos.documents.download', $documentoTituloPostulante) }}"
                                    >
                                        <i class="bi bi-file-earmark-text"></i> Ver título
                                    </a>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Sin título cargado</button>
                                @endif

                                @if ($s->estado === 'pendiente_uatp')
                                    @if ($autorizacionDocente?->correo_enviado_at)
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAutorizacionDocente">
                                            <i class="bi bi-patch-check"></i>
                                            {{ $autorizacionDocente->numero_autorizacion ? 'Actualizar número de autorización' : 'Ingresar número de autorización' }}
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.autorizacion-docente.solicitar', $s) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Se enviará por correo el expediente documental requerido y se registrará la autorización En trámite. ¿Desea continuar?');">
                                                <i class="bi bi-send-check"></i> Enviar expediente y solicitar autorización
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </div>

                            <div class="form-text mt-2">
                                Incluye antecedentes especiales, título profesional o técnico y título con mención.
                                @if (!empty($autorizacionDocenteRequiereReligion))
                                    Para Religión también exige Inhabilidades para trabajar con menores e Idoneidad para Religión.
                                @endif
                            </div>

                            @if ($autorizacionDocente)
                                @php
                                    $autorizacionEstadoClase = match ($autorizacionDocente->estado) {
                                        'aprobada' => 'alert-success',
                                        'rechazada' => 'alert-danger',
                                        default => 'alert-warning',
                                    };
                                @endphp
                                <div class="alert {{ $autorizacionEstadoClase }} mt-3 mb-0 py-2">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <strong>Autorización docente: {{ $autorizacionDocente->estado_label }}</strong>
                                        <span>N.º {{ $autorizacionDocente->numero_autorizacion ?: 'pendiente de registro' }}</span>
                                    </div>
                                    @if ($autorizacionDocente->observacion_estado)
                                        <div class="small mt-1">{{ $autorizacionDocente->observacion_estado }}</div>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            @if (!empty($mostrarSolicitudesAnterioresRelacionadas))
                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <div class="fw-semibold">Solicitudes anteriores relacionadas</div>
                        <div class="text-muted small">Antecedentes aceptados o cerrados del mismo titular y reemplazo para apoyar revisión UATP/Planificación.</div>
                    </div>
                    <span class="badge bg-light text-dark border">{{ ($solicitudesAnterioresRelacionadas ?? collect())->count() }} antecedente(s)</span>
                </div>

                @if (($solicitudesAnterioresRelacionadas ?? collect())->isNotEmpty())
                    @php
                        $estadoRelacionadoLabels = [
                            'aceptada' => 'Aceptada',
                            'cerrado' => 'Cerrada',
                            'cerrada' => 'Cerrada',
                        ];
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>N° solicitud</th>
                                    <th>Estado</th>
                                    <th>Periodo</th>
                                    <th>Tipo</th>
                                    <th>Postulante / reemplazo</th>
                                    <th>Establecimiento</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($solicitudesAnterioresRelacionadas as $rel)
                                    @php
                                        $relPost = $rel->contratoPostulante ?: $rel->postulante;
                                        $relUser = $relPost?->user;
                                        $relNombre = trim((string) ($relUser?->full_name ?? ''));
                                        if ($relNombre === '') {
                                            $relNombre = trim(implode(' ', array_filter([
                                                $relUser?->nombres ?? null,
                                                $relUser?->apellido_paterno ?? null,
                                                $relUser?->apellido_materno ?? null,
                                            ])));
                                        }
                                        $relRutNombre = trim(($relUser?->rut ? $relUser->rut . ' — ' : '') . ($relNombre ?: '—'));
                                        $relEst = $rel->establecimiento;
                                    @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $rel->numero_solicitud ?: ('#' . $rel->id) }}</td>
                                        <td><span class="badge {{ in_array($rel->estado, ['cerrado', 'cerrada'], true) ? 'bg-secondary' : 'bg-success' }}">{{ $estadoRelacionadoLabels[$rel->estado] ?? $rel->estado }}</span></td>
                                        <td>{{ optional($rel->fecha_inicio)->format('d/m/Y') }} - {{ optional($rel->fecha_termino)->format('d/m/Y') }}</td>
                                        <td>
                                            {{ $rel->tipo_reemplazo ?: '—' }}
                                            @if ($rel->tipo_reemplazo === 'Otras' && $rel->tipo_reemplazo_otro)
                                                <div class="text-muted small">{{ $rel->tipo_reemplazo_otro }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $relRutNombre }}</td>
                                        <td>
                                            {{ $relEst?->nombre ?: '—' }}
                                            @if ($relEst?->rbd)
                                                <div class="text-muted small">RBD {{ $relEst->rbd }}</div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('gestion.solicitudes-reemplazo.show', $rel) }}" class="btn btn-outline-primary btn-sm">Ver</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-light border mb-0">No se encontraron solicitudes anteriores aceptadas o cerradas para este titular y reemplazo.</div>
                @endif
            @endif

            <hr class="my-3">

            <div class="fw-semibold mb-2">Distribución de jornada (reemplazo)</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Financiamiento</th>
                            <th class="text-end">HRS BÁSICA</th>
                            <th class="text-end">HRS MEDIA</th>
                            <th class="text-end">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s->jornadas ?? collect() as $j)
                            <tr>
                                <td>{{ $j->financiamiento }}</td>
                                <td class="text-end">{{ $fmt($j->reemplazo_basica) }}</td>
                                <td class="text-end">{{ $fmt($j->reemplazo_media) }}</td>
                                <td class="text-end fw-semibold">{{ $fmt($j->reemplazo_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td class="text-end">TOTAL</td>
                            <td class="text-end">{{ $fmt($rb) }}</td>
                            <td class="text-end">{{ $fmt($rm) }}</td>
                            <td class="text-end">{{ $fmt($rt) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row g-3 mt-1">
                @if ($titularEsDocente)
                    <div class="col-md-6">
                        <div class="text-muted small">Horas Aula Cronológicas (reemplazo)</div>
                        <div class="fw-semibold">{{ $fmt($s->horas_aula_cronologicas_reemplazo) }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Horas Aula Pedagógicas (reemplazo)</div>
                        <div class="fw-semibold">{{ $fmt($s->horas_aula_pedagogicas_reemplazo) }}</div>
                    </div>
                @endif
                <div class="col-12">
                    <div class="text-muted small">Declaración de responsabilidad</div>
                    <div class="fw-semibold">{{ $s->declaracion_responsabilidad_aceptada ? 'Aceptada' : 'No registrada' }}</div>
                </div>
            </div>

            @if (!empty($canAdjustReplacement))
                <hr class="my-3">
                <div class="alert alert-warning">
                    <div class="fw-semibold mb-1">Ajuste de jornada del reemplazo</div>
                    <div class="small mb-0">Puede redistribuir libremente las horas del reemplazo entre los financiamientos. La suma completa no puede superar las 44 horas semanales y no se alteran las horas del titular.</div>
                </div>

                <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.ajuste-reemplazo.update', $s) }}">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Financiamiento</th>
                                    <th class="text-end">HRS BÁSICA titular</th>
                                    <th class="text-end">HRS MEDIA titular</th>
                                    <th class="text-end">HRS BÁSICA reemplazo</th>
                                    <th class="text-end">HRS MEDIA reemplazo</th>
                                    <th class="text-end">TOTAL reemplazo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($s->jornadas ?? collect() as $j)
                                    @php
                                        $finKey = (string) $j->financiamiento;
                                        $oldBasica = old("jornadas.$finKey.basica");
                                        $oldMedia = old("jornadas.$finKey.media");
                                        $baseVal = $oldBasica !== null ? $oldBasica : $fmt($j->reemplazo_basica);
                                        $mediaVal = $oldMedia !== null ? $oldMedia : $fmt($j->reemplazo_media);
                                        $totalVal = $fmt((float) $baseVal + (float) $mediaVal);
                                    @endphp
                                    <tr>
                                        <td>{{ $j->financiamiento }}</td>
                                        <td class="text-end text-muted">{{ $fmt($j->titular_basica) }}</td>
                                        <td class="text-end text-muted">{{ $fmt($j->titular_media) }}</td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="jornadas[{{ $finKey }}][basica]" value="{{ $baseVal }}" class="form-control form-control-sm text-end" required>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" name="jornadas[{{ $finKey }}][media]" value="{{ $mediaVal }}" class="form-control form-control-sm text-end" required>
                                        </td>
                                        <td class="text-end fw-semibold">{{ $totalVal }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($titularEsDocente)
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Horas Aula Cronológicas (reemplazo) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="horas_aula_cronologicas_reemplazo" value="{{ old('horas_aula_cronologicas_reemplazo', $fmt($s->horas_aula_cronologicas_reemplazo)) }}" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Horas Aula Pedagógicas (reemplazo) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="horas_aula_pedagogicas_reemplazo" value="{{ old('horas_aula_pedagogicas_reemplazo', $fmt($s->horas_aula_pedagogicas_reemplazo)) }}" class="form-control" required>
                            </div>
                        </div>
                    @endif

                    <div class="mt-3">
                        <label class="form-label">Observación del ajuste <span class="text-danger">*</span></label>
                        <textarea name="reemplazo_ajuste_observacion" rows="3" class="form-control" required placeholder="Indique la justificación del ajuste realizado sobre la jornada del reemplazo...">{{ old('reemplazo_ajuste_observacion', $s->reemplazo_ajuste_observacion) }}</textarea>
                        <div class="form-text">Este ajuste quedará trazado en la solicitud con usuario, rol y fecha.</div>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Guardar ajuste de reemplazo</button>
                    </div>
                </form>
            @endif

            @if (!empty($s->reemplazo_ajuste_at) || !empty($s->reemplazo_ajuste_observacion))
                <hr class="my-3">
                <div class="alert alert-secondary mb-0">
                    <div class="fw-semibold">Último ajuste de jornada del reemplazo</div>
                    <div class="small text-muted mb-2">
                        @if ($s->reemplazoAjusteUser)
                            {{ $s->reemplazoAjusteUser->nombre_completo ?: $s->reemplazoAjusteUser->email }}
                        @else
                            Usuario de gestión
                        @endif
                        @if (!empty($s->reemplazo_ajuste_role))
                            — {{ $s->reemplazo_ajuste_role }}
                        @endif
                        @if ($s->reemplazo_ajuste_at)
                            — {{ cl_datetime($s->reemplazo_ajuste_at, 'd/m/Y H:i') }}
                        @endif
                    </div>
                    <div style="white-space: pre-line;">{{ $s->reemplazo_ajuste_observacion ?: '—' }}</div>
                </div>
            @endif

            @if (!empty($s->observaciones))
                <hr class="my-3">
                <div class="text-muted small">Observaciones</div>
                <div class="fw-semibold" style="white-space: pre-line;">{{ $s->observaciones }}</div>
            @endif

            @if ($s->estado === 'rechazada_uatp' && !empty($s->motivo_rechazo))
                <hr class="my-3">
                <div class="alert alert-danger mb-0">
                    <div class="fw-semibold">Motivo de rechazo (UATP)</div>
                    <div style="white-space: pre-line;">{{ $s->motivo_rechazo }}</div>
                </div>
            @endif

            @if (!empty($s->uatp_reapertura_at) || !empty($s->uatp_reapertura_motivo))
                <hr class="my-3">
                <div class="alert alert-primary mb-0">
                    <div class="fw-semibold">Reapertura administrativa de UATP</div>
                    <div class="small text-muted mb-2">
                        @if ($s->uatpReaperturaUser)
                            {{ $s->uatpReaperturaUser->nombre_completo ?: $s->uatpReaperturaUser->email }}
                        @else
                            Usuario autorizado
                        @endif
                        @if ($s->uatp_reapertura_at)
                            — {{ cl_datetime($s->uatp_reapertura_at, 'd/m/Y H:i') }}
                        @endif
                    </div>
                    @if (!empty($s->uatp_rechazo_reabierto_motivo))
                        <div class="fw-semibold small">Rechazo anterior</div>
                        <div class="mb-2" style="white-space: pre-line;">{{ $s->uatp_rechazo_reabierto_motivo }}</div>
                    @endif
                    <div class="fw-semibold small">Motivo de reapertura</div>
                    <div style="white-space: pre-line;">{{ $s->uatp_reapertura_motivo ?: '—' }}</div>
                </div>
            @endif

            @if (!empty($s->justificacion_tecnica_uatp) || !empty($s->uatp_decision_at) || !empty($s->uatpDecisionUser))
                <hr class="my-3">
                <div class="alert alert-info mb-0">
                    <div class="fw-semibold">Justificación técnica UATP</div>
                    <div class="small text-muted mb-2">
                        @if ($s->uatpDecisionUser)
                            {{ $s->uatpDecisionUser->nombre_completo ?: $s->uatpDecisionUser->email }}
                        @else
                            UATP
                        @endif
                        @if ($s->uatp_decision_at)
                            — {{ cl_datetime($s->uatp_decision_at, 'd/m/Y H:i') }}
                        @endif
                    </div>
                    <div style="white-space: pre-line;">{{ $s->justificacion_tecnica_uatp ?: '—' }}</div>
                </div>
            @endif

            @if (!empty($s->plani_decision_at) || !empty($s->planiDecisionUser) || !empty($s->plani_motivo_rechazo))
                <hr class="my-3">
                <div class="alert {{ $s->estado === 'rechazada_plani' ? 'alert-danger' : 'alert-success' }} mb-0">
                    <div class="fw-semibold">
                        {{ $s->estado === 'rechazada_plani' ? 'Resultado validación Planificación: Rechazada' : 'Resultado validación Planificación: Validada' }}
                    </div>
                    <div class="small text-muted mb-2">
                        @if ($s->planiDecisionUser)
                            {{ $s->planiDecisionUser->nombre_completo ?: $s->planiDecisionUser->email }}
                        @else
                            Subdirección de Planificación y Control de Gestión
                        @endif
                        @if ($s->plani_decision_at)
                            — {{ cl_datetime($s->plani_decision_at, 'd/m/Y H:i') }}
                        @endif
                    </div>
                    @if (!empty($s->plani_motivo_rechazo))
                        <div class="fw-semibold small">Motivo de rechazo</div>
                        <div style="white-space: pre-line;">{{ $s->plani_motivo_rechazo }}</div>
                    @else
                        <div>La solicitud fue validada y quedó habilitada para continuar a tramitación GDP.</div>
                    @endif
                </div>
            @endif

            @if (!empty($s->plani_reapertura_at) || !empty($s->plani_reapertura_motivo))
                <hr class="my-3">
                <div class="alert alert-primary mb-0">
                    <div class="fw-semibold">Reapertura administrativa de Planificación</div>
                    <div class="small text-muted mb-2">
                        @if ($s->planiReaperturaUser)
                            {{ $s->planiReaperturaUser->nombre_completo ?: $s->planiReaperturaUser->email }}
                        @else
                            Usuario autorizado
                        @endif
                        @if ($s->plani_reapertura_at)
                            — {{ cl_datetime($s->plani_reapertura_at, 'd/m/Y H:i') }}
                        @endif
                    </div>
                    @if (!empty($s->plani_rechazo_reabierto_motivo))
                        <div class="fw-semibold small">Rechazo anterior</div>
                        <div class="mb-2" style="white-space: pre-line;">{{ $s->plani_rechazo_reabierto_motivo }}</div>
                    @endif
                    <div class="fw-semibold small">Motivo de reapertura</div>
                    <div style="white-space: pre-line;">{{ $s->plani_reapertura_motivo ?: '—' }}</div>
                </div>
            @endif

            @if ($s->observacionesFlujo && $s->observacionesFlujo->count())
                <hr class="my-3">
                <div class="alert alert-light border mb-0">
                    <div class="fw-semibold mb-2">Historial de rechazos, devoluciones y correcciones</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Etapa</th>
                                    <th>Acción</th>
                                    <th>Estado</th>
                                    <th>Usuario</th>
                                    <th>Motivo / observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($s->observacionesFlujo as $obsFlujo)
                                    <tr>
                                        <td class="text-nowrap">{{ cl_datetime($obsFlujo->created_at, 'd/m/Y H:i') }}</td>
                                        <td>{{ strtoupper(str_replace('_', ' ', $obsFlujo->etapa ?? '')) }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $obsFlujo->accion ?? '')) }}</td>
                                        <td class="small">
                                            {{ $obsFlujo->estado_origen ?: '—' }}
                                            @if ($obsFlujo->estado_destino)
                                                → {{ $obsFlujo->estado_destino }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($obsFlujo->user)
                                                {{ $obsFlujo->user->nombre_completo ?: $obsFlujo->user->email }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td style="white-space: pre-line;">{{ $obsFlujo->observacion ?: $obsFlujo->motivo ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if (!empty($s->observacion_slep))
                <hr class="my-3">
                <div class="alert alert-warning mb-0">
                    <div class="fw-semibold">Observación informada por SLEP</div>
                    <div class="small text-muted mb-2">
                        @if ($s->observacionSlepUser)
                            {{ $s->observacionSlepUser->nombre_completo ?: $s->observacionSlepUser->email }}
                        @else
                            Usuario SLEP
                        @endif
                        @if ($s->observacion_slep_at)
                            — {{ cl_datetime($s->observacion_slep_at, 'd/m/Y H:i') }}
                        @endif
                    </div>
                    <div style="white-space: pre-line;">{{ $s->observacion_slep }}</div>
                </div>
            @endif
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header fw-semibold">Documentos adjuntos</div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if ($s->oficio_pdf_path)
                    <a class="btn btn-outline-primary btn-sm" target="_blank"
                        href="{{ route('gestion.solicitudes-reemplazo.oficio', $s) }}">
                        Ver Oficio (PDF)
                    </a>
                    <span class="small text-muted">{{ basename($s->oficio_pdf_path) }}</span>
                @else
                    <span class="text-muted">Sin Oficio</span>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                @if ($s->respaldo_pdf_path)
                    <a class="btn btn-outline-primary btn-sm" target="_blank"
                        href="{{ route('gestion.solicitudes-reemplazo.respaldo', $s) }}">
                        Ver Respaldo (PDF)
                    </a>
                    <span class="small text-muted">{{ basename($s->respaldo_pdf_path) }}</span>
                @else
                    <span class="text-muted">Sin Respaldo</span>
                @endif
            </div>

            @if ($titularEsDocente)
                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                    @if ($s->horario_titular_pdf_path)
                        <a class="btn btn-outline-primary btn-sm" target="_blank"
                            href="{{ route('gestion.solicitudes-reemplazo.horario-titular', $s) }}">
                            Ver Horario Titular (PDF)
                        </a>
                        <span class="small text-muted">{{ basename($s->horario_titular_pdf_path) }}</span>
                    @else
                        <span class="text-muted">Sin Horario Titular</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
    {{-- Orden de trabajo (Funcionario SLEP) --}}
    @if (($canCrearOt ?? false) || in_array($s->estado, ['aceptada', 'cerrado', 'cerrada'], true))
        <div class="card mb-4">
            <div class="card-header fw-semibold">Orden de trabajo</div>
            <div class="card-body">

                @if (in_array($s->estado, ['aceptada', 'cerrado', 'cerrada'], true))
                    <div class="alert alert-success mb-0">
                        <div class="fw-semibold">Orden de trabajo creada</div>
                        <div class="mt-2">
                            <div><strong>Inicio de trabajo:</strong>
                                {{ optional($s->fecha_inicio_trabajo)->format('d/m/Y') ?? '—' }}</div>
                            <div><strong>Creada por:</strong>
                                {{ $s->ordenTrabajoCreadaPor?->full_name ?? ($s->ordenTrabajoCreadaPor?->name ?? '—') }}
                            </div>
                            <div><strong>Fecha creación:</strong>
                                {{ cl_datetime($s->orden_trabajo_creada_at, 'd/m/Y H:i') }}</div>
                        </div>

                        @if (!empty($s->orden_trabajo_pdf_path))
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener"
                                    href="{{ route('gestion.solicitudes-reemplazo.ot', $s) }}">
                                    Ver Orden de Trabajo
                                </a>
                                <a class="btn btn-sm btn-success"
                                    href="{{ route('gestion.solicitudes-reemplazo.ot.download', $s) }}">
                                    Descargar Orden de Trabajo
                                </a>
                            </div>
                        @endif

                        @if ($titularEsDocente)
                            <hr class="my-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="small text-muted">Horas Aula Titular</div>
                                    <div><strong>Cronológicas:</strong> {{ $fmt($s->horas_aula_cronologicas_titular) }}</div>
                                    <div><strong>Pedagógicas:</strong> {{ $fmt($s->horas_aula_pedagogicas_titular) }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-muted">Horas Aula Reemplazo</div>
                                    <div><strong>Cronológicas:</strong> {{ $fmt($s->horas_aula_cronologicas_reemplazo) }}</div>
                                    <div><strong>Pedagógicas:</strong> {{ $fmt($s->horas_aula_pedagogicas_reemplazo) }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                @elseif ($canCrearOt ?? false)
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.orden-trabajo.store', $s) }}">
                        @csrf

                        @php
                            $estatuto = strtoupper(trim((string) ($s->funcionarioTitular?->estatuto ?? '')));
                            $requiresContrato = str_contains($estatuto, 'AAEE') || str_contains($estatuto, 'ASIST');

                            // La categoría AAEE aplica para contratos AAEE
                            $requiresCategoria = $requiresContrato;
                        @endphp

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha desde la cual puede comenzar <span
                                        class="text-danger">*</span></label>
                                <input type="date" name="fecha_inicio_trabajo" class="form-control" required
                                    min="{{ optional($s->fecha_inicio)->toDateString() }}"
                                    max="{{ optional($s->fecha_termino)->toDateString() }}"
                                    value="{{ old('fecha_inicio_trabajo', optional($s->fecha_inicio)->toDateString()) }}">
                                <div class="form-text">Debe estar dentro del período de la solicitud.</div>
                            </div>

                            <div class="col-md-6">
                                @if ($s->propone_reemplazo)
                                    <label class="form-label">Postulante / funcionario</label>
                                    <div class="form-control bg-light">
                                        {{ $postUser?->rut ?? '—' }} — {{ $postUser?->full_name ?? '—' }}
                                    </div>
                                @else
                                    <label class="form-label">Seleccionar postulante / funcionario <span
                                            class="text-danger">*</span></label>
                                    <select id="postulant_profile_id" name="postulant_profile_id" class="form-select" required>
                                        <option value="">Buscar postulante o funcionario…</option>
                                    </select>
                                    <div id="otRestrictionAlert" class="alert alert-warning d-none mt-2 mb-0">
                                        <div class="fw-semibold">Advertencia de restricción manual</div>
                                        <div data-restriction-text class="small mb-0"></div>
                                    </div>
                                    <div class="form-text">Busca por nombres/apellidos o RUT. Se prioriza el área del titular y se deshabilitan postulantes/funcionarios con documentos incompletos o con conflicto en el período.</div>
@endif
                            </div>
                        </div>

                        @if ($titularEsDocente)
                            <div class="alert alert-info mt-3 mb-0">
                                <div class="fw-semibold">Horas Aula informadas en la solicitud</div>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <div class="small text-muted">Titular</div>
                                        <div><strong>Cronológicas:</strong> {{ $fmt($s->horas_aula_cronologicas_titular) }}</div>
                                        <div><strong>Pedagógicas:</strong> {{ $fmt($s->horas_aula_pedagogicas_titular) }}</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted">Reemplazo</div>
                                        <div><strong>Cronológicas:</strong> {{ $fmt($s->horas_aula_cronologicas_reemplazo) }}</div>
                                        <div><strong>Pedagógicas:</strong> {{ $fmt($s->horas_aula_pedagogicas_reemplazo) }}</div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="row g-3 mt-1">
                            @if ($requiresContrato)
                                @if ($requiresCategoria)
                                    <div class="col-md-6">
                                        <label class="form-label">Categoría AAEE <span class="text-danger">*</span></label>
                                        <select name="aaee_categoria" class="form-select @error('aaee_categoria') is-invalid @enderror" required>
                                            <option value="">— Seleccione —</option>
                                            @foreach (\App\Models\AaeeValorHora::categorias() as $cat)
                                                <option value="{{ $cat }}" @selected(old('aaee_categoria', $s->aaee_categoria) === $cat)>{{ ucfirst($cat) }}</option>
                                            @endforeach
                                        </select>
                                        @error('aaee_categoria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">Obligatoria para calcular remuneración en áreas AAEE generales.</div>
                                    </div>
                                @else
                                    <div class="col-md-6">
                                        <label class="form-label">Categoría AAEE</label>
                                        <input type="text" class="form-control" value="No aplica (valor hora definido por establecimiento)" disabled>
                                        <div class="form-text">Para esta área de desempeño, el valor hora se obtiene desde el mantenedor por establecimiento.</div>
                                    </div>
                                @endif
                            @endif
                        </div>

                        @php
                            $disableByCandidates = false;
                            $hasContrato = !empty($s->contrato_trabajo_docx_path);
                            $contratoFinal = (bool) ($s->contrato_trabajo_is_final ?? false);
                        @endphp

                        @if ($requiresContrato)
                            <div class="alert alert-info mt-3 mb-0">
                                <div class="fw-semibold">Contrato de trabajo (AAEE)</div>
                                <ol class="mb-0">
                                    <li>Completa fecha de inicio, categoría AAEE y postulante (si corresponde) y presiona <strong>“Generar contrato (Word)”</strong>.</li>
                                    <li>Descarga el contrato, edítalo y súbelo como <strong>versión final</strong>.</li>
                                    <li>Luego presiona <strong>“Crear orden de trabajo y notificar”</strong>.</li>
                                </ol>
                            </div>

                            @if ($hasContrato)
                                @if ($contratoFinal)
                                    <div class="alert alert-success py-2 mt-2 mb-0">Contrato final cargado ✔</div>
                                @else
                                    <div class="alert alert-warning py-2 mt-2 mb-0">Contrato en borrador: falta subir versión final.</div>
                                @endif
                            @else
                                <div class="alert alert-warning py-2 mt-2 mb-0">Aún no hay contrato generado.</div>
                            @endif
                        @endif

                        <div class="mt-3 d-flex flex-wrap gap-2">
                            @if ($requiresContrato)
                                <button class="btn btn-outline-primary" type="submit"
                                    formaction="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo.generar', $s) }}"
                                    {{ $disableByCandidates ? 'disabled' : '' }}>
                                    Generar contrato (Word)
                                </button>
                            @endif

                            <button class="btn btn-success" type="submit"
                                {{ $disableByCandidates ? 'disabled' : '' }}
                                {{ ($requiresContrato && (!$hasContrato || !$contratoFinal)) ? 'disabled' : '' }}>
                                Crear orden de trabajo y notificar
                            </button>
                        </div>
                    </form>

                    @if ($requiresContrato && !empty($s->contrato_trabajo_docx_path))
                        <hr class="my-3">

                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                            <a class="btn btn-outline-primary btn-sm"
                                href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo.download', $s) }}">
                                Descargar contrato (DOCX)
                            </a>
                            <span class="small text-muted">{{ basename($s->contrato_trabajo_docx_path) }}</span>
                        </div>

                        <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo.upload', $s) }}"
                              enctype="multipart/form-data" class="mb-2">
                            @csrf
                            <div class="input-group">
                                <input type="file" name="contrato_docx" class="form-control" accept=".docx" required>
                                <button class="btn btn-outline-secondary" type="submit">Subir contrato (versión final)</button>
                            </div>
                            <div class="form-text">Sube el contrato editado en Word (.docx). Luego podrás crear la OT.</div>
                        </form>
                    @endif

                @endif
            </div>
        </div>
    @endif

    @php
        $hasContratoBase = !empty($s->contrato_trabajo_docx_path);
        $tieneContratoFirmado = !empty($s->contrato_trabajo_firmado_pdf_path);
        $firmadoPendienteEnvio = $tieneContratoFirmado && empty($s->contrato_trabajo_firmado_enviado_at);
    @endphp

    @if (($canGestionarContratoFirmado ?? false) || ($tieneContratoFirmado && in_array($s->estado, ['aceptada', 'cerrado'], true)))
        <div class="card mb-4">
            <div class="card-header fw-semibold">Contrato firmado por ambas partes</div>
            <div class="card-body">
                @if (!$hasContratoBase)
                    <div class="alert alert-warning mb-0">Esta solicitud aún no registra contrato base para continuar con el cierre administrativo.</div>
                @else
                    @if ($tieneContratoFirmado)
                        <div class="alert {{ $firmadoPendienteEnvio ? 'alert-warning' : 'alert-success' }}">
                            <div class="fw-semibold">{{ $firmadoPendienteEnvio ? 'Contrato firmado cargado, pendiente de notificación' : 'Contrato firmado cargado' }}</div>
                            <div class="mt-2">
                                <div><strong>Archivo:</strong> {{ basename($s->contrato_trabajo_firmado_pdf_path) }}</div>
                                <div><strong>Subido por:</strong> {{ $s->contratoTrabajoFirmadoSubidoPor?->full_name ?? '—' }}</div>
                                <div><strong>Fecha carga:</strong> {{ cl_datetime($s->contrato_trabajo_firmado_subido_at, 'd/m/Y H:i') }}</div>
                                <div><strong>Notificado por:</strong> {{ $s->contratoTrabajoFirmadoEnviadoPor?->full_name ?? '—' }}</div>
                                <div><strong>Fecha notificación:</strong> {{ cl_datetime($s->contrato_trabajo_firmado_enviado_at, 'd/m/Y H:i') }}</div>
                                <div><strong>Solicitud cerrada por:</strong> {{ $s->cerradoPor?->full_name ?? '—' }}</div>
                                <div><strong>Fecha cierre:</strong> {{ cl_datetime($s->cerrado_at, 'd/m/Y H:i') }}</div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <a class="btn btn-outline-danger" href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo-firmado.download', $s) }}">Descargar contrato firmado (PDF)</a>
                        </div>

                        @if ($firmadoPendienteEnvio && !empty($canGestionarContratoFirmado))
                            <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo-firmado.enviar', $s) }}" enctype="multipart/form-data" class="mt-3">
                                @csrf
                                <div class="input-group">
                                    <input type="file" name="contrato_firmado_pdf" class="form-control" accept="application/pdf,.pdf" required>
                                    <button class="btn btn-danger" type="submit">Reintentar envío con nuevo PDF y cerrar</button>
                                </div>
                                <div class="form-text">Si el envío anterior falló, puedes volver a cargar el PDF firmado para notificar y cerrar la solicitud.</div>
                            </form>
                        @endif
                    @elseif (!empty($canGestionarContratoFirmado))
                        <div class="alert alert-info">
                            <div class="fw-semibold">Paso final del flujo</div>
                            <div>Sube el contrato firmado por trabajador y Director Ejecutivo en formato PDF. El sistema notificará al establecimiento y al postulante asignado, y luego cerrará la solicitud.</div>
                        </div>

                        <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo-firmado.enviar', $s) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group">
                                <input type="file" name="contrato_firmado_pdf" class="form-control" accept="application/pdf,.pdf" required>
                                <button class="btn btn-danger" type="submit">Cargar contrato firmado, notificar y cerrar</button>
                            </div>
                            <div class="form-text">Destinatarios: contacto del establecimiento, correo del usuario postulante asignado y email de contacto del perfil postulante. Los correos duplicados se depuran automáticamente.</div>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    @endif

    @if ($canRetornarDerivadaSlep ?? false)
        <div class="card mb-4 border-danger">
            <div class="card-header fw-semibold text-danger">Reiniciar gestión SLEP</div>
            <div class="card-body">
                @if ($tieneFiniquitoAsociado ?? false)
                    <div class="alert alert-danger mb-0">
                        La solicitud tiene un finiquito generado, firmado o pagado. No puede devolverse a
                        <strong>Derivada SLEP</strong> hasta regularizar ese antecedente.
                    </div>
                @else
                    <div class="alert alert-warning">
                        Esta acción devuelve la solicitud desde <strong>{{ $s->estado === 'aceptada' ? 'Aceptada' : 'Cerrada' }}</strong>
                        a <strong>Derivada SLEP</strong>. Se eliminarán la Orden de Trabajo, el contrato base o final y el contrato
                        firmado asociados. El postulante y la derivación a GDP se conservarán para reiniciar la gestión.
                    </div>

                    <form method="POST"
                        action="{{ route('gestion.solicitudes-reemplazo.slep.retornar-derivada', $s) }}"
                        onsubmit="return confirm('¿Confirma devolver la solicitud a Derivada SLEP y eliminar la Orden de Trabajo y el Contrato asociados?');">
                        @csrf

                        <div class="mb-3">
                            <label for="retorno_derivada_slep_motivo" class="form-label">
                                Motivo administrativo del reinicio <span class="text-danger">*</span>
                            </label>
                            <textarea id="retorno_derivada_slep_motivo" name="retorno_derivada_slep_motivo"
                                class="form-control @error('retorno_derivada_slep_motivo') is-invalid @enderror"
                                rows="3" minlength="10" maxlength="5000" required>{{ old('retorno_derivada_slep_motivo') }}</textarea>
                            @error('retorno_derivada_slep_motivo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input @error('confirmar_reinicio_derivada_slep') is-invalid @enderror"
                                type="checkbox" value="1" id="confirmar_reinicio_derivada_slep"
                                name="confirmar_reinicio_derivada_slep" required>
                            <label class="form-check-label" for="confirmar_reinicio_derivada_slep">
                                Confirmo la eliminación de los documentos operativos asociados y el reinicio del flujo.
                            </label>
                            @error('confirmar_reinicio_derivada_slep')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-danger">
                            Devolver a Derivada SLEP
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    {{-- Acciones UATP / Planificación al final --}}
    @if ($canUatp && $s->estado === 'pendiente_uatp')
        <div class="card mb-4">
            <div class="card-header fw-semibold">Acciones UATP</div>
            <div class="card-body">
                <div class="alert alert-info">
                    Al aprobar, la solicitud pasará a <strong>Pendiente de Validación</strong> y se notificará al establecimiento que debe esperar la revisión de la Subdirección de Planificación y Control de Gestión antes de continuar con GDP.
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalUatpAprobar">
                        Aprobar y enviar a Validación
                    </button>

                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse"
                        data-bs-target="#rechazarBox">
                        Rechazar
                    </button>
                </div>

                <div class="collapse mt-3" id="rechazarBox">
                    <form method="POST" action="{{ route('gestion.solicitudes-reemplazo.uatp.rechazar', $s) }}">
                        @csrf
                        <label class="form-label">Motivo de rechazo <span class="text-danger">*</span></label>
                        <textarea name="motivo_rechazo" rows="3" class="form-control" required placeholder="Indica el motivo...">{{ old('motivo_rechazo') }}</textarea>
                        <div class="mt-2">
                            <button class="btn btn-danger" type="submit">Confirmar rechazo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($canPlaniReview))
        <div class="card mb-4">
            <div class="card-header fw-semibold">Validación Subdirección de Planificación y Control de Gestión</div>
            <div class="card-body">
                <div class="alert alert-info">
                    Revisa los antecedentes, adjuntos y la justificación técnica registrada por UATP antes de validar o rechazar la solicitud.
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPlaniValidar">
                        Validar solicitud
                    </button>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalPlaniRechazar">
                        Rechazar solicitud
                    </button>
                </div>
            </div>
        </div>
    @endif

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
    (function() {
        function clampPercent(p) {
            p = parseInt(p ?? 0, 10);
            if (isNaN(p)) p = 0;
            return Math.max(0, Math.min(100, p));
        }

        function formatResult(item) {
            if (!item || item.loading) return item?.text || '';
            if (item.children) return item.text;

            const label = item.label || item.text || '';
            const area = (item.area || '').toString().trim() || '—';
            const uploaded = parseInt(item.uploaded ?? 0, 10) || 0;
            const total = parseInt(item.total_required ?? 0, 10) || 0;
            const percent = clampPercent(item.percent ?? (total > 0 ? Math.round((uploaded * 100) / total) : 0));

            const disabled = !!item.disabled;
            const conflict = !!item.period_conflict;
            const manualRestriction = !!item.manual_restriction;

            const $wrap = $('<div class="d-flex flex-column gap-1"></div>');
            if (disabled) $wrap.addClass('opacity-50');
            if (conflict || manualRestriction) {
                $wrap.addClass('p-1 rounded');
            }
            if (conflict) {
                $wrap.css('background-color', 'rgba(255,193,7,.15)');
            } else if (manualRestriction) {
                $wrap.css({
                    backgroundColor: 'rgba(255,193,7,.10)',
                    border: '1px solid rgba(255,193,7,.35)'
                });
            }

            const $top = $('<div class="d-flex justify-content-between align-items-center gap-2"></div>');
            const $name = $('<div class="fw-semibold"></div>').text(label);

            let badgeClass = percent >= 100 ? 'bg-success' : 'bg-secondary';
            if (conflict || manualRestriction) badgeClass = 'bg-warning text-dark';
            const $right = $('<div class="d-flex align-items-center gap-2"></div>');
            const $badge = $('<span class="badge"></span>').addClass(badgeClass).text(percent + '%');
            $right.append($badge);
            if (manualRestriction) {
                $right.append($('<span class="badge bg-warning text-dark"></span>').text('Restricción manual'));
            }
            $top.append($name).append($right);

            const $meta = $('<div class="small text-muted"></div>').text(`Docs ${uploaded}/${total}`);
            const $area = $('<div class="small text-muted"></div>').text(`Área: ${area}`);

            const $progress = $('<div class="progress" style="height:6px;"></div>');
            const $bar = $('<div class="progress-bar" role="progressbar"></div>').css('width', percent + '%');
            $progress.append($bar);

            $wrap.append($top).append($meta).append($area);

            if (conflict) {
                const reason = (item.conflict_reason || 'Conflicto de período').toString();
                $wrap.append($('<div class="small fw-semibold"></div>').text('⚠️ ' + reason));
            }
            if (manualRestriction) {
                const reason = (item.manual_restriction_comment || '').toString().trim();
                if (reason) {
                    $wrap.append($('<div class="small fw-semibold text-warning-emphasis"></div>').text('Restricción manual: ' + reason));
                }
            }

            $wrap.append($progress);

            if (!conflict && disabled) {
                const missing = Array.isArray(item.missing_docs) ? item.missing_docs : [];
                if (missing.length) {
                    $wrap.append($('<div class="small"></div>').text('Faltan: ' + missing.join(', ')));
                }
            }

            return $wrap;
        }

        function formatSelection(item) {
            if (!item) return '';
            if (item.children) return item.text || '';
            const label = item.label || item.text || '';
            const area = (item.area || '').toString().trim();
            const uploaded = item.uploaded;
            const total = item.total_required;
            const percent = item.percent;
            const restrictionTag = item.manual_restriction ? ' — Restricción manual' : '';
            if (uploaded !== undefined && total !== undefined && percent !== undefined) {
                return `${label}${area ? ' — ' + area : ''} — Docs ${uploaded}/${total} (${percent}%)${restrictionTag}`;
            }
            return `${label}${area ? ' — ' + area : ''}${restrictionTag}`;
        }

        function renderManualRestrictionAlert($alert, item) {
            if (!$alert || !$alert.length) return;
            const $text = $alert.find('[data-restriction-text]');
            if (item && item.manual_restriction) {
                const parts = [];
                const comment = (item.manual_restriction_comment || '').toString().trim();
                if (comment) parts.push(`Motivo: ${comment}`);
                const from = item.manual_restriction_start || null;
                const to = item.manual_restriction_end || null;
                if (from || to) parts.push(`Vigencia: ${from || '—'} al ${to || '—'}`);
                $text.text(parts.join(' | ') || 'El postulante tiene una restricción manual vigente.');
                $alert.removeClass('d-none');
            } else {
                $text.text('');
                $alert.addClass('d-none');
            }
        }

        function initSelect($el, postulantesUrl, mode, dropdownParent, alertTarget, extraParams = null) {
            if (!$el || !$el.length) return;
            if ($el.hasClass('select2-hidden-accessible')) return;

            const cfg = {
                placeholder: 'Buscar por nombres/apellidos o RUT...',
                minimumInputLength: 2,
                templateResult: formatResult,
                templateSelection: formatSelection,
                escapeMarkup: m => m,
                ajax: {
                    url: postulantesUrl,
                    dataType: 'json',
                    delay: 250,
                    data: params => Object.assign({ term: params.term || '', mode: mode || 'ot' }, (typeof extraParams === 'function' ? (extraParams() || {}) : (extraParams || {}))),
                    processResults: data => data
                },
                width: '100%'
            };

            if (dropdownParent && dropdownParent.length) {
                cfg.dropdownParent = dropdownParent;
            }

            $el.select2(cfg);

            $el.on('select2:selecting', function(e) {
                const data = e?.params?.args?.data;
                if (data && data.disabled) {
                    e.preventDefault();
                }
            });

            if (alertTarget) {
                $el.on('select2:select', function(e) {
                    renderManualRestrictionAlert(alertTarget, e?.params?.data || null);
                });
                $el.on('change', function() {
                    if (!this.value) renderManualRestrictionAlert(alertTarget, null);
                });
            }
        }

        $(function() {
            const postulantesOtUrl = @json(route('gestion.solicitudes-reemplazo.ajax.postulantes', $s));

            // Select en la vista (OT)
            const $otAlert = $('#otRestrictionAlert');
            const $fechaInicioTrabajo = $('input[name="fecha_inicio_trabajo"]').first();
            initSelect(
                $('#postulant_profile_id'),
                postulantesOtUrl,
                'ot',
                null,
                $otAlert,
                () => ({ fecha_inicio_trabajo: ($fechaInicioTrabajo.val() || '') })
            );
            $fechaInicioTrabajo.on('change', function() {
                const $select = $('#postulant_profile_id');
                if ($select.val()) {
                    $select.val(null).trigger('change');
                }
            });

            // Select del modal Reasignar: inicializar cuando el modal se muestra
            const $modal = $('#modalReasignarPostulante');
            const $reasignAlert = $('#reasignacionRestrictionAlert');
            $modal.on('shown.bs.modal', function() {
                initSelect(
                    $('#reasignar_postulant_profile_id'),
                    postulantesOtUrl,
                    'reasignar',
                    $modal,
                    $reasignAlert,
                    () => ({ fecha_inicio_trabajo: @json(optional($s->fecha_inicio_trabajo)->toDateString() ?? optional($s->fecha_inicio)->toDateString()) })
                );
            });
            $modal.on('hidden.bs.modal', function() {
                renderManualRestrictionAlert($reasignAlert, null);
            });
        });
    })();
    </script>

    @if (request()->boolean('abrir_autorizacion_docente') && !empty($canGestionarAutorizacionDocente) && $autorizacionDocente)
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('modalAutorizacionDocente');
            if (modalElement && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
        </script>
    @endif
@endpush

@endsection
