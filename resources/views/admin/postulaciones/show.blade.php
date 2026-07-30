@extends('layouts.app')

@section('content')
    @php
        $comunasPostula = $user->communes->pluck('name')->filter()->values();
        $docs = $user->documents ?? collect();
        $fotoUrl = !empty($profile->foto_thumb_path) ? asset('storage/' . $profile->foto_thumb_path) : null;
        $hasRestriction = (bool) ($restriction['blocked'] ?? false);
        $hasCourtRestriction = (bool) ($restriction['court_active'] ?? false);
        $hasManualRestriction = (bool) ($restriction['manual_active'] ?? false);
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0">Detalle de postulación</h1>
            <div class="text-muted small">{{ $user->nombre_completo ?: $user->email }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if (Route::has('admin.documents.forUser'))
                <a href="{{ route('admin.documents.forUser', $user) }}" class="btn btn-outline-primary">Documentos</a>
            @endif
            @if (Route::has('admin.documents.user.profile.view'))
                <a href="{{ route('admin.documents.user.profile.view', $user) }}" target="_blank" class="btn btn-outline-secondary">Ver perfil PDF</a>
            @endif
            <a href="{{ route('admin.postulaciones.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-body d-flex gap-3 align-items-start flex-wrap">
                    <div>
                        @if ($fotoUrl)
                            <img src="{{ $fotoUrl }}" alt="Foto postulante" class="rounded border" width="90" height="90">
                        @else
                            <div class="rounded border d-flex align-items-center justify-content-center bg-light text-muted"
                                style="width:90px;height:90px;">
                                <i class="bi bi-person fs-2"></i>
                            </div>
                        @endif
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h2 class="h5 mb-0">{{ $user->nombre_completo ?: 'Postulante' }}</h2>
                            @if ($user->email_verified_at)
                                    <span class="badge text-bg-success">Email verificado</span>
                            @else
                                <span class="badge bg-warning text-dark">Email pendiente</span>
                            @endif
                            @if ($check['ok'])
                                    <span class="badge text-bg-success">Perfil completo</span>
                            @else
                                <span class="badge bg-warning text-dark">Perfil {{ $check['percent'] }}%</span>
                            @endif
                            @if ($hasRestriction)
                                <span class="badge bg-danger">Con restricciones vigentes</span>
                            @else
                                <span class="badge bg-light text-dark border">Sin restricciones vigentes</span>
                            @endif
                        </div>

                        <div class="row g-3 small">
                            <div class="col-12 col-md-6">
                                <div class="text-muted">RUT normalizado</div>
                                <div class="fw-semibold">{{ $rutFmt }}</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted">Email</div>
                                <div class="fw-semibold">{{ $user->email }}</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted">Email de contacto</div>
                                <div class="fw-semibold">{{ $profile->email_contacto ?: '—' }}</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="text-muted">Teléfonos</div>
                                <div class="fw-semibold">
                                    {{ $profile->telefono1 ?: '—' }}
                                    @if ($profile->telefono2)
                                        <span class="text-muted">/</span> {{ $profile->telefono2 }}
                                    @endif
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted">Comuna(s) que postula</div>
                                <div class="fw-semibold">{{ $comunasPostula->isNotEmpty() ? $comunasPostula->join(', ') : '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Auditoría</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-6">Usuario creado</dt>
                        <dd class="col-6 text-end">{{ cl_datetime($user->created_at) }}</dd>
                        <dt class="col-6">Perfil actualizado</dt>
                        <dd class="col-6 text-end">{{ cl_datetime($profile->updated_at) }}</dd>
                        <dt class="col-6">Última actividad</dt>
                        <dd class="col-6 text-end">{{ $user->last_seen_at ? cl_datetime($user->last_seen_at) : '—' }}</dd>
                        <dt class="col-6">Completitud</dt>
                        <dd class="col-6 text-end">{{ $check['complete'] }}/{{ $check['total'] }} ({{ $check['percent'] }}%)</dd>
                    </dl>
                    @if (!$check['ok'] && !empty($check['missing']))
                        <hr>
                        <div class="small text-muted mb-2">Campos faltantes</div>
                        <ul class="small mb-0 ps-3">
                            @foreach ($check['missing'] as $missing)
                                <li>{{ $missing['label'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100 border-{{ $hasRestriction ? 'danger' : 'success' }}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Restricciones</span>
                    @if ($hasRestriction)
                        <span class="badge bg-danger">Vigentes</span>
                    @else
                                    <span class="badge text-bg-success">Sin bloqueo</span>
                    @endif
                </div>
                <div class="card-body small">
                    @if (!$hasRestriction)
                        <div class="text-muted">El postulante no registra restricciones judiciales ni manuales activas.</div>
                    @else
                        <div class="row g-3">
                            @if ($hasCourtRestriction)
                                <div class="col-12">
                                    <div class="alert alert-danger mb-0">
                                        <div class="fw-semibold mb-2"><i class="bi bi-shield-exclamation me-1"></i> Restricción judicial activa</div>
                                        <div><strong>Nombre:</strong> {{ $restriction['court_name'] ?: '—' }}</div>
                                        <div><strong>RUN:</strong> {{ $restriction['court_run'] ?: '—' }}</div>
                                        <div><strong>Juzgado:</strong> {{ $restriction['court_juzgado_origen'] ?: '—' }}</div>
                                        <div><strong>RIT:</strong> {{ $restriction['court_rit'] ?: '—' }}</div>
                                        <div><strong>Fecha fallo:</strong> {{ $restriction['court_fecha_fallo'] ?: '—' }}</div>
                                        <div><strong>Inhabilidad:</strong> {{ $restriction['court_inhabilidad'] ?: '—' }}</div>
                                    </div>
                                </div>
                            @endif
                            @if ($hasManualRestriction)
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0">
                                        <div class="fw-semibold mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Restricción manual activa</div>
                                        <div><strong>Desde:</strong> {{ $restriction['manual_start'] ?: '—' }}</div>
                                        <div><strong>Hasta:</strong> {{ $restriction['manual_end'] ?: '—' }}</div>
                                        <div><strong>Comentario:</strong> {{ $restriction['manual_comment'] ?: '—' }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold">Solicitudes asociadas (aceptadas y cerradas)</span>
                    <span class="badge bg-primary">{{ $relatedSolicitudes->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>N° solicitud</th>
                                    <th>Establecimiento</th>
                                    <th>Período</th>
                                    <th>Área</th>
                                    <th>Estado</th>
                                    <th>Documentos</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($relatedSolicitudes as $solicitud)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $solicitud->numero_solicitud ?: ('#' . $solicitud->id) }}</div>
                                            <div class="text-muted small">OT: {{ $solicitud->orden_trabajo_creada_at ? cl_datetime($solicitud->orden_trabajo_creada_at) : '—' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $solicitud->establecimiento?->nombre_establecimiento ?? '—' }}</div>
                                            <div class="text-muted small">RBD {{ $solicitud->establecimiento?->rbd ?? '—' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $solicitud->fecha_inicio ? $solicitud->fecha_inicio->format('d-m-Y') : '—' }}</div>
                                            <div class="text-muted small">al {{ $solicitud->fecha_termino ? $solicitud->fecha_termino->format('d-m-Y') : '—' }}</div>
                                        </td>
                                        <td>{{ $solicitud->areaDesempeno?->nombre ?? '—' }}</td>
                                        <td>
                                            @php
                                                $estadoLabel = match ($solicitud->estado) {
                                                    'aceptada' => 'Aceptada',
                                                    'cerrado' => 'Cerrado',
                                                    default => ucfirst(str_replace('_', ' ', (string) $solicitud->estado)),
                                                };
                                                $estadoClass = match ($solicitud->estado) {
                                                    'aceptada' => 'text-bg-success',
                                                    'cerrado' => 'text-bg-secondary',
                                                    default => 'text-bg-light',
                                                };
                                            @endphp
                                            <span class="badge {{ $estadoClass }}">{{ $estadoLabel }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <a href="{{ route('gestion.solicitudes-reemplazo.show', $solicitud) }}" class="btn btn-sm btn-outline-secondary">Ver solicitud</a>
                                                @if (!empty($solicitud->orden_trabajo_pdf_path) || !empty($solicitud->orden_trabajo_creada_at))
                                                    <a href="{{ route('gestion.solicitudes-reemplazo.ot', $solicitud) }}" target="_blank" class="btn btn-sm btn-outline-primary">Ver OT</a>
                                                @endif
                                                @if (!empty($solicitud->contrato_trabajo_docx_path))
                                <a href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo.download', $solicitud) }}" class="btn btn-sm btn-outline-success-dark">Contrato</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Este postulante no tiene solicitudes asociadas en estado aceptada o cerrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Datos personales</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Fecha de nacimiento</dt>
                        <dd class="col-sm-7">{{ $profile->fecha_nacimiento ? $profile->fecha_nacimiento->format('d-m-Y') : '—' }}</dd>
                        <dt class="col-sm-5">Dirección</dt>
                        <dd class="col-sm-7">{{ $profile->direccion ?: '—' }}</dd>
                        <dt class="col-sm-5">Región</dt>
                        <dd class="col-sm-7">{{ $regionName }}</dd>
                        <dt class="col-sm-5">Comuna domicilio</dt>
                        <dd class="col-sm-7">{{ $profile->comuna?->name ?? '—' }}</dd>
                        <dt class="col-sm-5">Nacionalidad</dt>
                        <dd class="col-sm-7">{{ $profile->nacionalidad ?: '—' }}</dd>
                        <dt class="col-sm-5">Género</dt>
                        <dd class="col-sm-7">{{ $profile->genero ?: '—' }}</dd>
                        <dt class="col-sm-5">Pronombres</dt>
                        <dd class="col-sm-7">{{ $profile->pronombres ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Antecedentes académicos y laborales</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Estamento</dt>
                        <dd class="col-sm-7">{{ $profile->estamento ? ucfirst($profile->estamento) : '—' }}</dd>
                        <dt class="col-sm-5">Área de desempeño</dt>
                        <dd class="col-sm-7">{{ $profile->areaDesempeno?->nombre ?? '—' }}</dd>
                        <dt class="col-sm-5">Mención</dt>
                        <dd class="col-sm-7">{{ $profile->mencion ?: '—' }}</dd>
                        <dt class="col-sm-5">Especialidad TP</dt>
                        <dd class="col-sm-7">{{ $profile->especialidad_tp ?: '—' }}</dd>
                        <dt class="col-sm-5">Nivel de estudios</dt>
                        <dd class="col-sm-7">{{ $profile->nivel_estudios ?: '—' }}</dd>
                        <dt class="col-sm-5">Institución</dt>
                        <dd class="col-sm-7">{{ $profile->institucion_titulo ?: '—' }}</dd>
                        <dt class="col-sm-5">Fecha de titulación</dt>
                        <dd class="col-sm-7">{{ $profile->fecha_titulacion ? $profile->fecha_titulacion->format('d-m-Y') : '—' }}</dd>
                        <dt class="col-sm-5">Semestres</dt>
                        <dd class="col-sm-7">{{ $profile->semestres !== null ? $profile->semestres : '—' }}</dd>
                        <dt class="col-sm-5">Horas totales</dt>
                        <dd class="col-sm-7">{{ $profile->horas_totales !== null ? $profile->horas_totales : '—' }}</dd>
                        <dt class="col-sm-5">Años de experiencia</dt>
                        <dd class="col-sm-7">{{ $profile->anios_experiencia !== null ? $profile->anios_experiencia : '—' }}</dd>
                        <dt class="col-sm-5">Cargos / función</dt>
                        <dd class="col-sm-7">{{ $profile->cargos_funcion ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Datos previsionales y bancarios</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">AFP</dt>
                        <dd class="col-sm-7">{{ $profile->prevision_afp ?: '—' }}</dd>
                        <dt class="col-sm-5">Salud</dt>
                        <dd class="col-sm-7">{{ $profile->salud_institucion ?: '—' }}</dd>
                        <dt class="col-sm-5">Banco</dt>
                        <dd class="col-sm-7">{{ $profile->banco ?: '—' }}</dd>
                        <dt class="col-sm-5">Tipo de cuenta</dt>
                        <dd class="col-sm-7">{{ $profile->tipo_cuenta ?: '—' }}</dd>
                        <dt class="col-sm-5">Número de cuenta</dt>
                        <dd class="col-sm-7">{{ $profile->numero_cuenta ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="fw-semibold">Documentos de la postulación</div>
                        <div class="small text-muted">
                            Avance de carga: {{ $docMetrics['uploaded'] }}/{{ $docMetrics['total_required'] }} ({{ $docMetrics['percent_uploaded'] }}%)
                            · Revisados: {{ $docMetrics['reviewed'] }}
                            / Aprobados: {{ $docMetrics['approved'] }}
                        </div>
                    </div>
                    @if (Route::has('admin.documents.forUser'))
                        <a href="{{ route('admin.documents.forUser', $user) }}" class="btn btn-sm btn-outline-primary">Abrir revisión</a>
                    @endif
                </div>
                <div class="card-body border-bottom">
                    <div class="progress" role="progressbar" aria-label="Avance documentos" aria-valuenow="{{ $docMetrics['percent_uploaded'] }}"
                        aria-valuemin="0" aria-valuemax="100" style="height: 10px;">
                        <div class="progress-bar" style="width: {{ $docMetrics['percent_uploaded'] }}%"></div>
                    </div>
                    <div class="row g-2 mt-3 small">
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted">Requeridos</div>
                                <div class="fs-5 fw-semibold">{{ $docMetrics['total_required'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted">Cargados</div>
                                <div class="fs-5 fw-semibold">{{ $docMetrics['uploaded'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted">Revisados</div>
                                <div class="fs-5 fw-semibold">{{ $docMetrics['reviewed'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-2 h-100">
                                <div class="text-muted">Aprobados</div>
                                <div class="fs-5 fw-semibold text-success">{{ $docMetrics['approved'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Documento requerido</th>
                                    <th>Estado</th>
                                    <th>Revisión</th>
                                    <th>Actualizado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($requiredDocItems as $item)
                                    @php
                                        $doc = $item['doc'];
                                        $status = (string) ($doc->status ?? 'missing');
                                        $badge = match ($status) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'pending' => 'warning text-dark',
                                            'missing' => 'secondary',
                                            default => 'secondary',
                                        };
                                        $label = match ($status) {
                                            'approved' => 'Aprobado',
                                            'rejected' => 'Rechazado',
                                            'pending' => 'Pendiente',
                                            'missing' => 'No cargado',
                                            default => ucfirst($status),
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $item['type']->label }}</td>
                                        <td><span class="badge bg-{{ $badge }}">{{ $label }}</span></td>
                                        <td>
                                            @if ($doc && $doc->reviewed_at)
                                                <div>{{ cl_datetime($doc->reviewed_at) }}</div>
                                                <div class="text-muted small">{{ $doc->reviewer?->nombre_completo ?? $doc->reviewer?->email ?? '—' }}</div>
                                            @elseif($doc)
                                                <span class="text-muted">Sin revisión</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $doc ? cl_datetime($doc->updated_at) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No hay documentos requeridos configurados para este postulante.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
