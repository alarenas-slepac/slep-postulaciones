@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Nuevo registro PIE por curso</h1>
            <div class="text-muted small">Registra estudiantes NEET y NEEP sobre cursos/secciones ya cargados.</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-curso-pie.index') }}">Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.establecimiento-curso-pie.store') }}" class="card card-body shadow-sm">
        @csrf
        @include('admin.establecimiento-curso-pie._form')
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-curso-pie.index') }}">Cancelar</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
        </div>
    </form>
@endsection
