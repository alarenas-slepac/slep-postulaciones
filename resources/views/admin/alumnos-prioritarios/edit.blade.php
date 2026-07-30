@extends('layouts.app')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1">Editar porcentaje de alumnos prioritarios</h1>
        <div class="text-muted small">Actualiza el establecimiento, año o porcentaje registrado.</div>
    </div>

    <form method="POST" action="{{ route('admin.alumnos-prioritarios.update', $item) }}" class="card card-body shadow-sm">
        @method('PUT')
        @include('admin.alumnos-prioritarios._form')
    </form>
@endsection
