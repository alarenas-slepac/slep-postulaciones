@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-1">Nuevo Incumplimiento Laboral</h1>
            <p class="text-muted mb-0">Registra atrasos e inasistencias informadas sobre funcionarios del padrón del establecimiento.</p>
        </div>
        <a href="{{ route('incumplimientos.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ $action }}" novalidate>
                @csrf
                @include('incumplimientos._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar incumplimiento
                    </button>
                    <a href="{{ route('incumplimientos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
