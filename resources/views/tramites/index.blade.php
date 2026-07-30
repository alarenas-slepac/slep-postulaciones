@extends('layouts.app')

@section('content')
    @php
        $activeRole = auth()->user()?->activeRoleName();
        $isTramiteReviewer = in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp'], true);
        $cargasSolicitanteRoles = (array) config('cargas_familiares.acceso_solicitantes.roles_habilitados', ['funcionario_ac']);
        $isCargasApplicant = in_array($activeRole, $cargasSolicitanteRoles, true);
        $canCreateGenericTramite = in_array($activeRole, ['postulante', 'funcionario'], true);
    @endphp
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Mis trámites</h1>
            <div class="text-muted small">Revisa el historial de trámites enviados y sus documentos adjuntos.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($isTramiteReviewer && Route::has('tramites.cargas-familiares.admin.index'))
                <a href="{{ route('tramites.cargas-familiares.admin.index') }}" class="btn btn-outline-dark">
                    <i class="bi bi-clipboard-data"></i> Administrar cargas familiares
                </a>
            @endif
            @if ($isCargasApplicant && Route::has('tramites.cargas-familiares.index'))
                <a href="{{ route('tramites.cargas-familiares.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-people"></i> Mis Cargas Familiares
                </a>
            @endif
            @if ($canCreateGenericTramite && Route::has('tramites.create'))
                <a href="{{ route('tramites.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuevo tramite
                </a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if ($tramites->count())
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Establecimiento</th>
                                <th>Enviado</th>
                                <th>Documentos</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tramites as $tramite)
                                <tr>
                                    <td class="fw-semibold">{{ $tramite->id }}</td>
                                    <td>{{ $tramite->tipo_label }}</td>
                                    <td>
                                        <span class="badge {{ $tramite->estado_badge_class }}">{{ $tramite->estado_label }}</span>
                                    </td>
                                    <td>{{ $tramite->establecimiento_nombre_snapshot ?: '—' }}</td>
                                    <td>{{ optional($tramite->enviado_at)->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') ?: '—' }}</td>
                                    <td>{{ $tramite->documentos_count }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a href="{{ route('tramites.show', $tramite) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Ver
                                            </a>
                                            @if (in_array($tramite->estado, ['enviado', 'en_revision'], true))
                                                <a href="{{ route('tramites.edit', $tramite) }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i> Editar
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted">
                    Aún no has enviado trámites.
                </div>
            @endif
        </div>
    </div>

    @if ($tramites->hasPages())
        <div class="mt-3">
            {{ $tramites->links() }}
        </div>
    @endif
@endsection
