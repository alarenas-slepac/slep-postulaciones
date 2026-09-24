@extends('layouts.app')

@section('content')
<div class="ur-page">
    <div class="ur-hero">
        <div class="ur-hero-main">
            <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-person-vcard"></i></span>
            <div>
                <div class="ur-eyebrow">Administración · Usuarios</div>
                <h1 class="ur-hero-title">Ficha de usuario</h1>
                <p class="ur-hero-subtitle">{{ $user->nombre_completo ?: $user->email }}</p>
            </div>
        </div>
        <div class="ur-hero-actions">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver</a>
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">
                <i class="bi bi-pencil-square" aria-hidden="true"></i> Editar usuario
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card ur-panel h-100">
                <div class="card-header"><div class="ur-panel-kicker">Cuenta</div><h2 class="ur-panel-title">Información general</h2></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="ur-info-item"><div class="ur-info-label">RUT</div>
                            <div class="ur-info-value">{{ $user->rut }}</div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="ur-info-item"><div class="ur-info-label">Email</div>
                            <div class="ur-info-value">{{ $user->email }}</div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="ur-info-item"><div class="ur-info-label">Nombres</div>
                            <div class="ur-info-value">{{ $user->nombres }}</div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="ur-info-item"><div class="ur-info-label">Apellidos</div>
                            <div class="ur-info-value">{{ trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '')) }}</div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="ur-info-item"><div class="ur-info-label">Roles asignados</div>
                            <div class="ur-actions">
                                @forelse ($user->getRoleNames() as $role)
                                    <span class="ur-chip is-info">{{ $role }}</span>
                                @empty
                                    <span class="text-muted">Sin rol asignado</span>
                                @endforelse
                            </div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="ur-info-item"><div class="ur-info-label">Verificación</div>
                            <div>
                                @if ($user->email_verified_at)
                                    <span class="ur-chip is-success">Verificado</span>
                                    <div class="small text-muted mt-1">{{ cl_datetime($user->email_verified_at) }}</div>
                                @else
                                    <span class="ur-chip is-warning">Pendiente</span>
                                @endif
                            </div></div>
                        </div>
                        <div class="col-md-12">
                            <div class="ur-info-item"><div class="ur-info-label">Establecimiento</div>
                            <div class="ur-info-value">
                                @if ($user->establecimiento)
                                    {{ $user->establecimiento->rbd }} — {{ $user->establecimiento->nombre_establecimiento }}
                                    @if ($user->establecimiento->comuna)
                                        <span class="text-muted">({{ $user->establecimiento->comuna }})</span>
                                    @endif
                                @else
                                    <span class="text-muted">No aplica / sin asignación</span>
                                @endif
                            </div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card ur-panel mb-3">
                <div class="card-header"><div class="ur-panel-kicker">Seguimiento</div><h2 class="ur-panel-title">Auditoría</h2></div>
                <div class="card-body">
                    <div class="ur-info-item mb-3">
                        <div class="ur-info-label">Creado</div>
                        <div class="ur-info-value">{{ cl_datetime($user->created_at) }}</div>
                    </div>
                    <div class="ur-info-item mb-3">
                        <div class="ur-info-label">Actualizado</div>
                        <div class="ur-info-value">{{ cl_datetime($user->updated_at) }}</div>
                    </div>
                    <div class="ur-info-item">
                        <div class="ur-info-label">Última actividad</div>
                        <div class="ur-info-value">{{ cl_datetime($user->last_seen_at, 'd-m-Y H:i', 'Sin registro') }}</div>
                    </div>
                </div>
            </div>

            <div class="card ur-panel">
                <div class="card-header"><div class="ur-panel-kicker">Navegación</div><h2 class="ur-panel-title">Acciones</h2></div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil-square"></i> Editar usuario
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
