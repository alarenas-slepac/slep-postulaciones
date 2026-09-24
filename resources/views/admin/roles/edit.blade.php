@extends('layouts.app')

@section('content')
<div class="ur-page">
    <div class="ur-hero">
        <div class="ur-hero-main">
            <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-person-gear"></i></span>
            <div>
                <div class="ur-eyebrow">Administración · Roles</div>
                <h1 class="ur-hero-title">Editar rol</h1>
                <p class="ur-hero-subtitle">{{ $role->name }}</p>
            </div>
        </div>
        <div class="ur-hero-actions"><a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver</a></div>
    </div>

    @if (session('status'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf @method('PUT')

        @include('admin.roles._form', [
            'role' => $role,
            'modules' => $modules,      {{-- viene groupBy('section') --}}
            'assigned' => $assigned,    {{-- array de module_id --}}
        ])

        <div class="card ur-panel mt-4"><div class="ur-actionbar border-0">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Guardar cambios</button>
        </div></div>
    </form>
</div>
@endsection
