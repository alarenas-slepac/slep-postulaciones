@extends('layouts.app')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1">Nuevo curso</h1>
        <div class="text-muted small">Registra un curso o nivel para planes de estudio y planta docente/asistentes.</div>
    </div>

    <form method="POST" action="{{ route('admin.cursos.store') }}" class="card card-body shadow-sm">
        @include('admin.cursos._form')
    </form>
@endsection
