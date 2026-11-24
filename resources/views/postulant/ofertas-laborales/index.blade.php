@extends('layouts.app')

@section('content')
<x-postulant-page title="Ofertas laborales" icon="bi-briefcase" description="Revisa las convocatorias y prepara los documentos del cargo con el que quieres postular.">
    <x-slot:actions>
        <a href="{{ route('postulant.profile.cargos.index') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-person-vcard" aria-hidden="true"></i> Mis cargos</a>
        <a href="{{ route('postulant.ofertas-laborales.history') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-send-check" aria-hidden="true"></i> Mis postulaciones</a>
    </x-slot:actions>
    <div class="row g-3">
        @forelse ($items as $item)
            @php
                [$variant, $status] = match (true) {
                    $applied->has($item->id) => ['success', $applied->get($item->id)->estado_label],
                    ! $item->isPostulacionAbierta($now) => ['neutral', 'Postulaciones cerradas'],
                    ! ($compatible[$item->id] ?? false) => ['warning', 'Requiere un cargo compatible'],
                    default => ['info', 'Postulaciones abiertas'],
                };
            @endphp
            <div class="col-12 col-xl-6">
                <article class="slep-listing__panel h-100 d-flex flex-column">
                    <div class="slep-listing__panel-header flex-wrap">
                        <h2 class="text-break">{{ $item->areaDesempeno?->nombre ?: 'Oferta laboral' }}</h2>
                        <span class="slep-status-pill slep-status-pill--{{ $variant }}">{{ $status }}</span>
                    </div>
                    <div class="p-3 p-md-4 flex-grow-1">
                        @include('postulant.ofertas-laborales.details', ['oferta' => $item])
                    </div>
                    <div class="slep-listing__footer">
                        <a href="{{ route('postulant.ofertas-laborales.show', $item) }}" class="slep-listing__button slep-listing__button--primary">{{ $applied->has($item->id) ? 'Ver mi postulación' : 'Ver oferta y requisitos' }}</a>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12">
                <div class="slep-listing__panel slep-listing__empty"><i class="bi bi-inbox" aria-hidden="true"></i><h2>No hay ofertas publicadas por el momento</h2></div>
            </div>
        @endforelse
    </div>
    <div class="mt-3">@include('partials.ui.paginacion', ['paginador' => $items, 'etiqueta' => 'Páginas de ofertas laborales'])</div>
</x-postulant-page>
@endsection
