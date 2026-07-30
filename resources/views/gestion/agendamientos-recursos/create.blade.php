@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 p-4">
            <h1 class="h4 mb-1">Nuevo agendamiento</h1>
            <p class="text-muted mb-0">Registre una solicitud de sala, proyector o recurso institucional.</p>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="{{ route('gestion.agendamientos-recursos.store') }}">
                @include('gestion.agendamientos-recursos.partials.form')
            </form>
        </div>
    </div>
</div>
@endsection
