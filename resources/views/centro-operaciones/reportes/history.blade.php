@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
<div class="co-shell">
    <header class="co-hero">
        <div><div class="co-eyebrow">Centro de Operaciones</div><h1>Historial de reportes</h1><p>Consulta todos los envíos y sus versiones registradas.</p></div>
        <div class="co-hero-actions">
            @role('funcionario_directivo_estab')<a class="btn btn-primary" href="{{ route('centro-operaciones.reportes.create') }}"><i class="bi bi-plus-circle"></i> Reportar hoy</a>@endrole
            @hasanyrole('admin|director_ejecutivo|funcionario_slep|coordinador_gdp|coordinador_uatp|comunicaciones|gabinete_slep')<a class="btn btn-outline-primary" href="{{ route('centro-operaciones.index') }}"><i class="bi bi-speedometer2"></i> Panel</a>@endhasanyrole
        </div>
    </header>
    <section class="co-card">
        <form method="GET" class="co-history-filter"><div><label for="fecha">Fecha del reporte</label><input class="form-control" id="fecha" type="date" name="fecha" value="{{ request('fecha') }}" max="{{ now(config('centro_operaciones.timezone'))->toDateString() }}"></div><button class="btn btn-primary">Filtrar</button>@if(request('fecha'))<a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.reportes.history') }}">Limpiar</a>@endif</form>
        <div class="table-responsive">
            <table class="table co-table align-middle mb-0">
                <thead><tr><th>Fecha y hora</th><th>Establecimiento</th><th>Comuna</th><th>Responsable</th><th>Estado</th><th>Versión</th><th></th></tr></thead>
                <tbody>
                @forelse($reportes as $reporte)
                    <tr><td><strong>{{ $reporte->fecha_reporte->format('d-m-Y') }}</strong><small class="d-block text-muted">{{ $reporte->reportado_en->format('H:i') }} hrs.</small></td><td>{{ $reporte->establecimiento_nombre }}</td><td>{{ $reporte->establecimiento_comuna ?: '-' }}</td><td>{{ $reporte->reportadoPor?->name ?? 'Usuario no disponible' }}</td><td><span class="co-badge co-badge--{{ $reporte->estado_general }}">{{ ucfirst($reporte->estado_general) }}</span></td><td>v{{ $reporte->version }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('centro-operaciones.reportes.show', $reporte) }}">Ver detalle</a></td></tr>
                @empty<tr><td colspan="7"><div class="co-empty py-5"><i class="bi bi-inbox"></i> No hay reportes para los filtros seleccionados.</div></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $reportes->links() }}</div>
    </section>
</div>
@endsection
