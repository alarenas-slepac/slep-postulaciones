@extends('layouts.app')

@section('content')
    <div class="ur-page">
        <div class="ur-hero">
            <div class="ur-hero-main">
                <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
                <div>
                    <div class="ur-eyebrow">Administración · Roles</div>
                    <h1 class="ur-hero-title">Nuevo rol</h1>
                    <p class="ur-hero-subtitle">Define el nombre del rol y sus módulos visibles.</p>
                </div>
            </div>
            <div class="ur-hero-actions"><a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver</a></div>
        </div>

        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf

            @include('admin.roles._form', [
                'role' => $role ?? null,
                'modules' => $modules ?? collect(),
                'assigned' => $assigned ?? [],
            ])

            <div class="card ur-panel mt-4"><div class="ur-actionbar border-0">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Crear rol</button>
            </div></div>
        </form>
    </div>
@endsection
