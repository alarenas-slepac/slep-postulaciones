@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-1">Editar Incumplimiento Laboral #{{ $item->id }}</h1>
            <p class="text-muted mb-0">Como administrador puedes corregir el registro informado.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('incumplimientos.show', $item) }}" class="btn btn-outline-primary">Ver detalle</a>
            <a href="{{ route('incumplimientos.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ $action }}" novalidate>
                @csrf
                @method($method)
                @include('incumplimientos._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar cambios
                    </button>
                    <a href="{{ route('incumplimientos.show', $item) }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
