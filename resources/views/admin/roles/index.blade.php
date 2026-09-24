@extends('layouts.app')

@section('content')
    <div class="ur-page">
        <div class="ur-hero">
            <div class="ur-hero-main">
                <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-person-gear"></i></span>
                <div>
                    <div class="ur-eyebrow">Administración · Accesos</div>
                    <h1 class="ur-hero-title">Roles</h1>
                    <p class="ur-hero-subtitle">Gestiona los roles y los módulos visibles para cada uno.</p>
                </div>
            </div>
            <div class="ur-hero-actions">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-people" aria-hidden="true"></i> Usuarios</a>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> Nuevo rol
            </a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div>
        @endif

        <div class="card ur-panel">
            <div class="card-header"><div class="ur-panel-kicker">Configuración</div><h2 class="ur-panel-title">Roles disponibles</h2></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle ur-page-table">
                    <thead>
                        <tr>
                            <th>Rol</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            <tr>
                                <td><span class="ur-table-primary">{{ $role->name }}</span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary"
                                        href="{{ route('admin.roles.edit', $role) }}">
                                        <i class="bi bi-sliders"></i> Módulos
                                    </a>

                                    @if ($role->name !== 'admin')
                                        <form class="d-inline" method="POST"
                                            action="{{ route('admin.roles.destroy', $role) }}"
                                            onsubmit="return confirm('¿Eliminar rol {{ $role->name }}?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm ur-danger" type="submit" aria-label="Eliminar rol {{ $role->name }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i> Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="ur-empty"><i class="bi bi-person-gear" aria-hidden="true"></i>Sin roles.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
