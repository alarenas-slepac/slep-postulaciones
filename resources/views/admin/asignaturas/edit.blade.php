@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Editar asignatura</h1>
            <div class="text-muted small">{{ $asignatura->nombre }}</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.asignaturas.index') }}">Volver</a>
    </div>

    <form method="POST" action="{{ route('admin.asignaturas.update', $asignatura) }}" class="card card-body shadow-sm">
        @method('PUT')
        @include('admin.asignaturas._form')
    </form>
@endsection
