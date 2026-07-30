@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Editar registro PIE por curso</h1>
            <div class="text-muted small">Actualiza NEET, NEEP, observación y estado del registro.</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-curso-pie.index', ['anio' => $pie->anio]) }}">Volver</a>
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

    <form method="POST" action="{{ route('admin.establecimiento-curso-pie.update', $pie) }}" class="card card-body shadow-sm">
        @csrf
        @method('PUT')
        @include('admin.establecimiento-curso-pie._form')
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-curso-pie.index', ['anio' => $pie->anio]) }}">Cancelar</a>
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar cambios</button>
        </div>
    </form>
@endsection
