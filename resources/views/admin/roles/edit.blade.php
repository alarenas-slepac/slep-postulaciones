@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 m-0">Editar rol: {{ $role->name }}</h1>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf @method('PUT')

        @include('admin.roles._form', [
            'role' => $role,
            'modules' => $modules,      {{-- viene groupBy('section') --}}
            'assigned' => $assigned,    {{-- array de module_id --}}
        ])

        <div class="mt-3">
            <button class="btn btn-primary"><i class="bi bi-check2"></i> Guardar</button>
        </div>
    </form>
</div>
@endsection
