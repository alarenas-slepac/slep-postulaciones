@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Nuevo plan de estudio</h1>
            <div class="text-muted small">Registra horas del plan por curso, año y régimen JEC.</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.planes-estudio.index') }}">Volver</a>
    </div>

    <form method="POST" action="{{ route('admin.planes-estudio.store') }}" class="card card-body shadow-sm">
        @csrf
        @include('admin.planes-estudio._form')
    </form>
@endsection
