@extends('layouts.app')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1">Editar curso</h1>
        <div class="text-muted small">Actualiza los datos del curso seleccionado.</div>
    </div>

    <form method="POST" action="{{ route('admin.cursos.update', $curso) }}" class="card card-body shadow-sm">
        @method('PUT')
        @include('admin.cursos._form')
    </form>
@endsection
