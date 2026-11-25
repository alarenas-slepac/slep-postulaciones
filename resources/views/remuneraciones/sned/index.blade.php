@extends('layouts.app')

@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush

@section('content')
<div class="container py-4 slep-listing" data-slep-listing>
    <header class="slep-listing__header">
        <div class="slep-listing__heading">
            <span class="slep-listing__icon"><i class="bi bi-award" aria-hidden="true"></i></span>
            <div>
                <p class="slep-listing__eyebrow">Remuneraciones</p>
                <h1>Cuotas SNED</h1>
                <p class="slep-listing__muted mb-0">Prepare la nómina trimestral de docentes y asistentes de la educación por comuna.</p>
            </div>
        </div>
    </header>

    @if ($errors->any())
        <div class="slep-listing__notice slep-listing__notice--danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="slep-listing__panel slep-listing__filters" aria-labelledby="nueva-cuota">
        <h2 id="nueva-cuota" class="h5 mb-3">Abrir cuota</h2>
        <form method="POST" action="{{ route('sned.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-sm-4 col-lg-2">
                <label for="anio" class="form-label">Año del trimestre</label>
                <input id="anio" name="anio" type="number" class="form-control" min="2008" max="2100" value="{{ old('anio', now()->year) }}" required>
            </div>
            <div class="col-sm-8 col-lg-4">
                <label for="numero" class="form-label">Cuota</label>
                <select id="numero" name="numero" class="form-select" required>
                    @foreach (['Enero a marzo', 'Abril a junio', 'Julio a septiembre', 'Octubre a diciembre'] as $trimestre)
                        <option value="{{ $loop->iteration }}" @selected((int) old('numero', now()->quarter) === $loop->iteration)>Cuota {{ $loop->iteration }} · {{ $trimestre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-8 col-lg-4">
                <label for="comuna" class="form-label">Comuna</label>
                <select id="comuna" name="comuna" class="form-select" data-slep-select="search" required>
                    <option value="">Seleccione una comuna</option>
                    @foreach ($comunas as $clave => $comuna)
                        <option value="{{ $clave }}" @selected(old('comuna') === $clave)>{{ $comuna }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-lg-2"><button class="slep-listing__button slep-listing__button--primary w-100">Abrir cuota</button></div>
        </form>
    </section>

    <section class="slep-listing__panel" aria-labelledby="historial-cuotas">
        <div class="slep-listing__panel-header"><h2 id="historial-cuotas">Historial de cuotas</h2></div>
        <div class="slep-listing__table-wrap">
            <table class="slep-listing__table" role="table" aria-labelledby="historial-cuotas">
                <thead class="table-light"><tr><th scope="col">Cuota</th><th scope="col">Comuna</th><th scope="col">Versión</th><th scope="col">Estado</th><th scope="col"><span class="visually-hidden">Detalle</span></th></tr></thead>
                <tbody>
                    @forelse ($cuotas as $cuota)
                        <tr>
                            <td data-label="Cuota"><strong>Cuota {{ $cuota->numero }} · {{ $cuota->anio }}</strong></td>
                            <td data-label="Comuna">{{ $cuota->comuna }}</td>
                            <td data-label="Versión">{{ $cuota->version }}</td>
                            <td data-label="Estado"><span class="slep-status-pill slep-status-pill--{{ $cuota->cerrado_at ? 'success' : 'neutral' }}">{{ $cuota->cerrado_at ? 'Cerrada' : 'Borrador' }}</span></td>
                            <td><a class="slep-listing__button slep-listing__button--secondary slep-listing__button--compact" href="{{ route('sned.show', $cuota) }}">Ver cuota</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="slep-listing__empty">Aún no hay cuotas registradas. Abra una cuota para comenzar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($cuotas->total())
            @include('partials.ui.paginacion', ['paginador' => $cuotas, 'etiqueta' => 'Páginas de cuotas SNED'])
        @endif
    </section>
</div>
@endsection
