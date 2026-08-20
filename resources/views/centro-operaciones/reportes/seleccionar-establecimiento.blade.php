@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
<div class="co-shell co-report-shell">
    <header class="co-hero co-hero--report">
        <div class="co-module-identity">
            <div class="co-module-icon co-module-icon--risk"><i class="bi bi-building-check"></i></div>
            <div>
                <div class="co-eyebrow">Centro de Operaciones SLEP</div>
                <h1>Reporte diario territorial</h1>
                <p>Selecciona el establecimiento cuyo reporte será registrado o actualizado.</p>
            </div>
        </div>
        <div class="co-hero-actions">
            <a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.reportes.history') }}"><i class="bi bi-arrow-left"></i> Historial</a>
        </div>
    </header>

    <section class="co-card co-form-section">
        <div class="co-card-head">
            <div><span class="co-eyebrow">Gestión de Gabinete</span><h2>Establecimiento a reportar</h2></div>
        </div>
        <form method="GET" action="{{ route('centro-operaciones.reportes.create') }}" class="row g-3 align-items-end">
            <div class="col-lg-9">
                <label class="form-label fw-semibold" for="establecimiento">Establecimiento</label>
                <select id="establecimiento" name="establecimiento" class="form-select" required>
                    <option value="">Selecciona un establecimiento</option>
                    @foreach($establecimientos as $establecimiento)
                        <option value="{{ $establecimiento->id }}">
                            {{ $establecimiento->nombre_establecimiento }} · {{ $establecimiento->comuna ?: 'Sin comuna' }} · RBD {{ $establecimiento->rbd ?: '-' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 d-grid">
                <button class="btn btn-primary" type="submit"><i class="bi bi-arrow-right-circle"></i> Continuar</button>
            </div>
        </form>
    </section>
</div>
@endsection
