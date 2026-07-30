@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 m-0">Nuevo rol</h1>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf

            @include('admin.roles._form', [
                'role' => $role ?? null,
                'modules' => $modules ?? collect(),
                'assigned' => $assigned ?? [],
            ])

            <div class="mt-3">
                <button class="btn btn-primary"><i class="bi bi-check2"></i> Crear</button>
            </div>
        </form>
    </div>
@endsection
