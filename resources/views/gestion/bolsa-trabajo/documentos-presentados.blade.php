@extends('layouts.app')

@section('content')
<x-postulant-page title="Documentos presentados" icon="bi-folder2-open" :description="$application->user?->display_name">
    <x-slot:actions>
        <a href="{{ route('gestion.bolsa-trabajo.show', $offer) }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a la oferta</a>
    </x-slot:actions>
    <section class="slep-listing__panel" aria-labelledby="submitted-heading">
        <div class="slep-listing__panel-header">
            <div><h2 id="submitted-heading">Antecedentes de la postulación</h2><p class="slep-listing__muted mb-0 text-break">{{ $application->cargo_presentado['etiqueta'] }}</p></div>
        </div>
        <ul class="list-group list-group-flush mb-0">
            @forelse ($application->submittedDocuments as $document)
                <li class="list-group-item px-3 px-md-4 py-3 d-flex justify-content-between flex-wrap align-items-center gap-3">
                    <div class="text-break">
                        <div class="fw-semibold">{{ $document->type?->label }}</div>
                        <div class="small slep-listing__muted mb-2">{{ $document->credential_label ?: $document->original_name }}</div>
                        @if ($document->issued_on)<div class="small slep-listing__muted mb-2">Emisión informada: {{ $document->issued_on->format('d-m-Y') }}</div>@endif
                        <span class="slep-status-pill slep-status-pill--{{ match ($document->status) { 'approved' => 'success', 'rejected' => 'danger', default => 'info' } }}">{{ match ($document->status) { 'approved' => 'Aprobado', 'rejected' => 'Rechazado', default => 'Pendiente de revisión' } }}</span>
                    </div>
                    <div class="slep-listing__actions">
                        @can('review', $document)
                            <a href="{{ route('reemplazos.documents.show', $document) }}" class="slep-listing__button slep-listing__button--secondary">Revisar PDF</a>
                        @endcan
                        <a href="{{ route('reemplazos.documents.download', $document) }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-download" aria-hidden="true"></i> Descargar PDF</a>
                    </div>
                </li>
            @empty
                <li class="list-group-item slep-listing__empty"><i class="bi bi-inbox" aria-hidden="true"></i><h3>Esta postulación no contiene documentos</h3></li>
            @endforelse
        </ul>
    </section>
</x-postulant-page>
@endsection
