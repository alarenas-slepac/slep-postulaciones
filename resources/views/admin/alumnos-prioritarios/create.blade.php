@extends('layouts.app')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1">Nuevo porcentaje de alumnos prioritarios</h1>
        <div class="text-muted small">Registra el porcentaje anual asociado a un establecimiento.</div>
    </div>

    <form method="POST" action="{{ route('admin.alumnos-prioritarios.store') }}" class="card card-body shadow-sm">
        @include('admin.alumnos-prioritarios._form')
    </form>
@endsection
