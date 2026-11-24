@extends('layouts.app')

@section('content')
<x-postulant-page title="Mis postulaciones" icon="bi-send-check" description="Consulta el cargo presentado y el estado de cada postulación.">
    <x-slot:actions>
        <a href="{{ route('postulant.ofertas-laborales.index') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Ver ofertas</a>
    </x-slot:actions>
    <section class="slep-listing__panel" aria-labelledby="history-heading">
        <div class="slep-listing__panel-header"><h2 id="history-heading">Postulaciones registradas</h2></div>
        <ul class="list-group list-group-flush mb-0">
            @forelse ($applications as $application)
                <li class="list-group-item px-3 px-md-4 py-3 d-flex flex-wrap justify-content-between gap-3 align-items-center">
                    <div class="text-break">
                        <span class="slep-status-pill slep-status-pill--info">{{ $application->estado_label }}</span>
                        <div class="fw-semibold mt-2">{{ $application->cargo_presentado['etiqueta'] ?? $application->oferta?->areaDesempeno?->nombre ?? 'Oferta laboral' }}</div>
                        <div class="small slep-listing__muted">{{ $application->oferta?->establecimientos_display }} · {{ $application->created_at?->format('d-m-Y') }}</div>
                    </div>
                    @if ($application->oferta)
                        <a href="{{ route('postulant.ofertas-laborales.show', $application->oferta) }}" class="slep-listing__button slep-listing__button--secondary">Ver postulación</a>
                    @endif
                </li>
            @empty
                <li class="list-group-item slep-listing__empty"><i class="bi bi-inbox" aria-hidden="true"></i><h3>Aún no has postulado a una oferta</h3></li>
            @endforelse
        </ul>
        <div class="slep-listing__footer">@include('partials.ui.paginacion', ['paginador' => $applications, 'etiqueta' => 'Páginas de mis postulaciones'])</div>
    </section>
</x-postulant-page>
@endsection
