@extends('layouts.app')

@section('content')
@php
    $activeRole = auth()->user()?->activeRoleName();
    $puedeCargaMasiva = in_array($activeRole, ['admin', 'funcionario_slep'], true);
    $puedeVerUsuarios = $activeRole === 'admin';
@endphp
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h4 mb-0">Detalle carga familiar importada</h1>
        <div class="text-muted small">Registro proveniente de carga masiva. Permite revisar beneficiario, causante, asociacion con usuario y datos originales importados.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('tramites.cargas-familiares.admin.index') }}" class="btn btn-outline-secondary btn-sm">Volver a administracion</a>
        @if ($puedeCargaMasiva && Route::has('tramites.cargas-familiares.import'))
            <a href="{{ route('tramites.cargas-familiares.import') }}" class="btn btn-outline-primary btn-sm">Carga masiva</a>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Estado carga</div>
                <span class="badge {{ $carga->estado_carga_badge_class ?? 'text-bg-light border text-dark' }} mt-1">{{ $carga->estado_carga_label ?? ucfirst((string) $carga->estado_carga) }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Periodo</div>
                <div class="h6 mb-0">{{ $carga->periodo_carga ?: '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Comuna origen</div>
                <div class="h6 mb-0">{{ $carga->comuna_origen ?: '-' }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Vinculacion</div>
                @if ($carga->user)
                    <span class="badge text-bg-success mt-1">Asociada a usuario</span>
                @else
                    <span class="badge text-bg-warning mt-1">Sin asociar</span>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Beneficiario</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8">{{ $carga->beneficiario_nombre_completo ?: '-' }}</dd>

                    <dt class="col-sm-4">RUN</dt>
                    <dd class="col-sm-8">{{ $carga->beneficiario_rut_completo ?: $carga->beneficiario_run_normalizado ?: '-' }}</dd>

                    <dt class="col-sm-4">RUN normalizado</dt>
                    <dd class="col-sm-8"><code>{{ $carga->beneficiario_run_normalizado ?: '-' }}</code></dd>

                    <dt class="col-sm-4">Correo importado</dt>
                    <dd class="col-sm-8">{{ $carga->beneficiario_email ?: '-' }}</dd>

                    <dt class="col-sm-4">Usuario asociado</dt>
                    <dd class="col-sm-8">
                        @if ($carga->user)
                            <div class="fw-semibold">{{ $carga->user->nombre_completo ?? $carga->user->name ?? 'Usuario' }}</div>
                            <div class="small text-muted">{{ $carga->user->rut ?? '' }} · {{ $carga->user->email ?? '' }}</div>
                            @if ($puedeVerUsuarios && Route::has('admin.users.show'))
                                <a href="{{ route('admin.users.show', $carga->user) }}" class="btn btn-sm btn-outline-secondary mt-2">Ver usuario</a>
                            @endif
                        @else
                            <span class="text-muted">No hay usuario registrado con este RUN/DV al momento de la importacion o ultima asociacion.</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Causante</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8">{{ $carga->causante_nombre_completo ?: '-' }}</dd>

                    <dt class="col-sm-4">RUN</dt>
                    <dd class="col-sm-8">{{ $carga->causante_rut_completo ?: $carga->causante_run_normalizado ?: '-' }}</dd>

                    <dt class="col-sm-4">RUN normalizado</dt>
                    <dd class="col-sm-8"><code>{{ $carga->causante_run_normalizado ?: '-' }}</code></dd>

                    <dt class="col-sm-4">Sexo</dt>
                    <dd class="col-sm-8">{{ $carga->sexo ?: '-' }}</dd>

                    <dt class="col-sm-4">Parentesco</dt>
                    <dd class="col-sm-8">{{ $carga->parentesco ?: '-' }}</dd>

                    <dt class="col-sm-4">Fecha nacimiento</dt>
                    <dd class="col-sm-8">{{ optional($carga->fecha_nacimiento)->format('d/m/Y') ?: '-' }} @if(!is_null($carga->edad)) <span class="text-muted small">({{ $carga->edad }} años)</span> @endif</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Datos SIAGF / resolucion</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Codigo tipo causante</dt>
                    <dd class="col-sm-7">{{ $carga->codigo_tipo_causante ?: '-' }}</dd>

                    <dt class="col-sm-5">Codigo SIAGF</dt>
                    <dd class="col-sm-7">{{ $carga->codigo_siagf ?: '-' }}</dd>

                    <dt class="col-sm-5">Tipo beneficio</dt>
                    <dd class="col-sm-7">{{ $carga->tipo_beneficio ?: '-' }}</dd>

                    <dt class="col-sm-5">Fecha resolucion</dt>
                    <dd class="col-sm-7">{{ optional($carga->fecha_resolucion)->format('d/m/Y') ?: '-' }}</dd>

                    <dt class="col-sm-5">Numero resolucion</dt>
                    <dd class="col-sm-7">{{ $carga->numero_resolucion ?: '-' }}</dd>

                    <dt class="col-sm-5">Fecha inicio</dt>
                    <dd class="col-sm-7">{{ optional($carga->fecha_inicio)->format('d/m/Y') ?: '-' }}</dd>

                    <dt class="col-sm-5">Fecha termino</dt>
                    <dd class="col-sm-7">{{ optional($carga->fecha_termino)->format('d/m/Y') ?: '-' }}</dd>

                    <dt class="col-sm-5">Tipo / tramo / monto</dt>
                    <dd class="col-sm-7">{{ $carga->tipo ?: '-' }} / {{ $carga->tramo ?: '-' }} / {{ is_null($carga->monto) ? '-' : '$'.number_format((float) $carga->monto, 0, ',', '.') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Documentacion esperada por codigo</div>
            <div class="card-body">
                @if ($codigo !== '' && $configCodigo)
                    <div class="mb-2">
                        <span class="badge text-bg-primary">Codigo {{ $codigo }}</span>
                        <span class="fw-semibold ms-1">{{ $configCodigo['nombre'] ?? 'Tipo de causante' }}</span>
                    </div>
                    <div class="small text-muted mb-3">Esta es la documentacion de referencia para una nueva acreditacion asociada a este tipo de causante. La carga masiva solo registra la carga vigente importada.</div>

                    <div class="fw-semibold small mb-1">Obligatorios</div>
                    @if ($documentosObligatorios->isNotEmpty())
                        <ul class="mb-3">
                            @foreach ($documentosObligatorios as $documento)
                                <li>{{ $documento }}</li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-muted small mb-3">Sin documentos obligatorios configurados para este codigo.</div>
                    @endif

                    <div class="fw-semibold small mb-1">Condicionales</div>
                    @if ($documentosCondicionales->isNotEmpty())
                        <ul class="mb-0">
                            @foreach ($documentosCondicionales as $condicional)
                                <li>
                                    <span class="fw-semibold">{{ $condicional['label'] ?: $condicional['documento'] }}</span>
                                    <div class="small text-muted">{{ $condicional['pregunta'] }}</div>
                                    @if ($condicional['ayuda'])
                                        <div class="small text-muted">{{ $condicional['ayuda'] }}</div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-muted small">Sin documentos condicionales configurados para este codigo.</div>
                    @endif
                @else
                    <div class="alert alert-warning mb-0 small">Este registro no tiene codigo de causante o el codigo no esta configurado en la matriz documental.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Datos de importacion</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Fuente archivo</dt>
                    <dd class="col-sm-8">{{ $carga->fuente_archivo ?: '-' }}</dd>

                    <dt class="col-sm-4">Importado por</dt>
                    <dd class="col-sm-8">
                        @if ($carga->importedBy)
                            {{ $carga->importedBy->nombre_completo ?? $carga->importedBy->name ?? 'Usuario' }}
                            <div class="small text-muted">{{ $carga->importedBy->rut ?? '' }} · {{ $carga->importedBy->email ?? '' }}</div>
                        @else
                            -
                        @endif
                    </dd>

                    <dt class="col-sm-4">Fecha importacion</dt>
                    <dd class="col-sm-8">{{ optional($carga->imported_at)->format('d/m/Y H:i') ?: optional($carga->created_at)->format('d/m/Y H:i') ?: '-' }}</dd>

                    <dt class="col-sm-4">Ultima actualizacion</dt>
                    <dd class="col-sm-8">{{ optional($carga->updated_at)->format('d/m/Y H:i') ?: '-' }}</dd>

                    <dt class="col-sm-4">Observaciones</dt>
                    <dd class="col-sm-8">{{ $carga->observaciones ?: '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Acreditaciones asociadas a esta carga</div>
            <div class="card-body">
                @if ($carga->causantesSolicitados->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Solicitud</th>
                                    <th>Accion</th>
                                    <th>Estado</th>
                                    <th class="text-end">Ver</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($carga->causantesSolicitados as $causante)
                                    <tr>
                                        <td>
                                            #{{ $causante->solicitud_id }}
                                            <div class="small text-muted">{{ optional($causante->solicitud?->fecha_envio)->format('d/m/Y H:i') ?: '-' }}</div>
                                        </td>
                                        <td>{{ ucfirst((string) $causante->accion) }}</td>
                                        <td><span class="badge {{ $causante->estado_revision_badge_class ?? 'text-bg-light border text-dark' }}">{{ $causante->estado_revision_label ?? 'Pendiente' }}</span></td>
                                        <td class="text-end">
                                            @if ($causante->solicitud)
                                                <a href="{{ route('tramites.cargas-familiares.review.show', $causante->solicitud) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted small">No hay nuevas acreditaciones o actualizaciones vinculadas a esta carga importada.</div>
                @endif
            </div>
        </div>
    </div>
</div>

@if (is_array($carga->raw_row) && count($carga->raw_row) > 0)
    <div class="card shadow-sm mb-4">
        <div class="card-header fw-semibold">Fila original importada</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 35%">Campo original</th>
                            <th>Valor importado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($carga->raw_row as $campo => $valor)
                            <tr>
                                <td class="fw-semibold">{{ $campo }}</td>
                                <td>{{ is_scalar($valor) || is_null($valor) ? ($valor === null || $valor === '' ? '-' : $valor) : json_encode($valor, JSON_UNESCAPED_UNICODE) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@endsection
