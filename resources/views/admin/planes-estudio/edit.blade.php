@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Editar plan de estudio</h1>
            <div class="text-muted small">{{ $plan->curso?->nombre }} · {{ $plan->anio }} · {{ $plan->regimen_jec }}</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.planes-estudio.index') }}">Volver</a>
    </div>

    <form method="POST" action="{{ route('admin.planes-estudio.update', $plan) }}" class="card card-body shadow-sm">
        @csrf
        @method('PUT')
        @include('admin.planes-estudio._form')
    </form>
@endsection
