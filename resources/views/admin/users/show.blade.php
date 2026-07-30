@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Ficha de usuario</h1>
            <div class="text-muted small">{{ $user->nombre_completo ?: $user->email }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
                <i class="bi bi-pencil-square"></i> Editar
            </a>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><strong>Información general</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">RUT</div>
                            <div class="fw-semibold">{{ $user->rut }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Email</div>
                            <div class="fw-semibold">{{ $user->email }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Nombres</div>
                            <div class="fw-semibold">{{ $user->nombres }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Apellidos</div>
                            <div class="fw-semibold">{{ trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '')) }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Rol actual</div>
                            <div>
                                @forelse ($user->getRoleNames() as $role)
                                    <span class="badge bg-secondary">{{ $role }}</span>
                                @empty
                                    <span class="text-muted">Sin rol asignado</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Verificación</div>
                            <div>
                                @if ($user->email_verified_at)
                                    <span class="badge text-bg-success">Verificado</span>
                                    <div class="small text-muted mt-1">{{ cl_datetime($user->email_verified_at) }}</div>
                                @else
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="text-muted small">Establecimiento</div>
                            <div class="fw-semibold">
                                @if ($user->establecimiento)
                                    {{ $user->establecimiento->rbd }} — {{ $user->establecimiento->nombre_establecimiento }}
                                    @if ($user->establecimiento->comuna)
                                        <span class="text-muted">({{ $user->establecimiento->comuna }})</span>
                                    @endif
                                @else
                                    <span class="text-muted">No aplica / sin asignación</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><strong>Auditoría</strong></div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Creado</div>
                        <div class="fw-semibold">{{ cl_datetime($user->created_at) }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Actualizado</div>
                        <div class="fw-semibold">{{ cl_datetime($user->updated_at) }}</div>
                    </div>
                    <div>
                        <div class="text-muted small">Última actividad</div>
                        <div class="fw-semibold">{{ cl_datetime($user->last_seen_at, 'd-m-Y H:i', 'Sin registro') }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Acciones</strong></div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil-square"></i> Editar usuario
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
                </div>
            </div>
        </div>
    </div>
@endsection
