@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 m-0">Roles</h1>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo rol
            </a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Rol</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                            <tr>
                                <td class="fw-semibold">{{ $role->name }}</td>
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
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">Sin roles.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
