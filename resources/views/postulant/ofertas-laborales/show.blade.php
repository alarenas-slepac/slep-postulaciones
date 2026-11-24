@extends('layouts.app')

@section('content')
<x-postulant-page :title="$oferta->areaDesempeno?->nombre ?: 'Detalle de la oferta'" icon="bi-briefcase" :description="$oferta->establecimientos_display">
    <x-slot:actions>
        <a href="{{ route('postulant.ofertas-laborales.index') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Ver ofertas</a>
        <a href="{{ route('postulant.ofertas-laborales.history') }}" class="slep-listing__button slep-listing__button--secondary">Mis postulaciones</a>
    </x-slot:actions>
    <div class="row g-3 align-items-start">
        <div class="col-12 col-xl-8">
            <section class="slep-listing__panel" aria-labelledby="oferta-requisitos">
                <div class="slep-listing__panel-header"><h2 id="oferta-requisitos">{{ $application ? 'Mi postulación' : 'Prepara tu postulación' }}</h2></div>
                <div class="p-3 p-md-4">
                    @if ($application)
                        <span class="slep-status-pill slep-status-pill--info">{{ $application->estado_label }}</span>
                        @if ($application->cargo_presentado)
                            <p class="fw-semibold mt-3 text-break">{{ $application->cargo_presentado['etiqueta'] }}</p>
                            <ul class="list-group list-group-flush">
                                @foreach ($application->submittedDocuments as $doc)
                                    <li class="list-group-item px-0 py-2 text-break">
                                        <a href="{{ route('postulant.documents.download', $doc) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> {{ $doc->type?->label }}{{ $doc->credential_label ? ' · '.$doc->credential_label : '' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @elseif (! $open)
                        <span class="slep-status-pill slep-status-pill--neutral">Postulaciones cerradas</span>
                    @elseif ($profiles->isEmpty())
                        <p>Agrega un cargo que coincida con el estamento y el área de esta oferta.</p>
                        <a href="{{ route('postulant.profile.cargos.index') }}" class="slep-listing__button slep-listing__button--primary">Administrar mis cargos</a>
                    @else
                        @if ($profiles->count() > 1)
                            <form method="GET" action="{{ route('postulant.ofertas-laborales.show', $oferta) }}" class="mb-3">
                                <label for="oferta-cargo" class="form-label">Cargo con el que quieres postular</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <select id="oferta-cargo" name="cargo" class="form-select w-auto mw-100">
                                        @foreach ($profiles as $key => $job)
                                            <option value="{{ $key }}" @selected($key === $profileKey)>{{ \App\Support\PostulantJobProfiles::label($job) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="slep-listing__button slep-listing__button--secondary" type="submit">Ver requisitos</button>
                                </div>
                            </form>
                        @else
                            <p class="fw-semibold text-break">{{ \App\Support\PostulantJobProfiles::label($profiles->get($profileKey)) }}</p>
                        @endif
                        <form method="POST" action="{{ route('postulant.ofertas-laborales.store', $oferta) }}" data-slep-saving="Enviando…">
                            @csrf
                            <input type="hidden" name="perfil_laboral" value="{{ $profileKey }}">
                            <ul class="list-group list-group-flush">
                                @foreach ($requirements as $row)
                                    @php
                                        $type = $row['type'];
                                        [$kind, $label] = match ($row['status']) {
                                            'available' => ['success', 'Se reutiliza'],
                                            'choose' => ['info', 'Selecciona un archivo'],
                                            'review' => ['warning', 'Requiere atención'],
                                            default => ['danger', 'Falta cargar'],
                                        };
                                        $libraryLink = route('postulant.documents.index', ['oferta' => $oferta->id, 'cargo' => $profileKey, 'tipo' => $type->id]);
                                    @endphp
                                    <li class="list-group-item px-0 py-3">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                            <span class="fw-semibold">{{ $type->label }}</span>
                                            <span class="slep-status-pill slep-status-pill--{{ $kind }}">{{ $label }}</span>
                                        </div>
                                        @if (($row['rule']['aprobado'] ?? false) || ($row['rule']['vigencia_dias'] ?? false))
                                            <p class="small slep-listing__muted mt-2 mb-0">
                                                @if ($row['rule']['aprobado'] ?? false) Requiere aprobación previa. @endif
                                                @if ($row['rule']['vigencia_dias'] ?? false) Emisión dentro de los últimos {{ $row['rule']['vigencia_dias'] }} días. @endif
                                            </p>
                                        @endif
                                        @if ($row['status'] === 'choose' || ($row['document'] && $type->supportsMultipleCredentials() && $row['available']->count() > 1))
                                            <label for="elegir-documento-{{ $type->id }}" class="form-label small mt-2">Archivo de {{ $type->label }}</label>
                                            <select class="form-select" id="elegir-documento-{{ $type->id }}" name="documentos[{{ $type->id }}]" required>
                                                <option value="">Selecciona el archivo que acredita este cargo</option>
                                                @foreach ($row['available'] as $doc)
                                                    <option value="{{ $doc->id }}" @selected($row['document']?->id === $doc->id)>{{ $doc->credential_label ?: ($doc->original_name ?: 'Archivo '.$doc->id) }} · {{ $doc->status === 'approved' ? 'Aprobado' : 'Pendiente de revisión' }}</option>
                                                @endforeach
                                            </select>
                                        @elseif ($row['document'])
                                            <input type="hidden" name="documentos[{{ $type->id }}]" value="{{ $row['document']->id }}">
                                            <p class="small slep-listing__muted mt-2 mb-0 text-break">{{ $row['document']->credential_label ?: $row['document']->original_name }} · {{ $row['document']->status === 'approved' ? 'Aprobado' : 'Pendiente de revisión' }}</p>
                                        @endif
                                        @if (! $row['document'] || $type->supportsMultipleCredentials())
                                            <a class="d-inline-block mt-2" href="{{ $libraryLink }}">{{ $row['status'] === 'missing' ? 'Cargar documento' : 'Abrir este documento en la biblioteca' }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            <div class="slep-listing__filter-actions">
                                @if ($requirements->contains(fn ($row) => in_array($row['status'], ['missing', 'review'], true)))
                                    <p class="slep-listing__muted mb-0">Resuelve los documentos pendientes para enviar tu postulación.</p>
                                @else
                                    <button class="slep-listing__button slep-listing__button--primary" type="submit"><i class="bi bi-send" aria-hidden="true"></i> <span data-slep-save-label>Confirmar postulación</span></button>
                                @endif
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-4">
            <section class="slep-listing__panel" aria-labelledby="oferta-detalle">
                <div class="slep-listing__panel-header"><h2 id="oferta-detalle">Detalle de la oferta</h2></div>
                <div class="p-3 p-md-4">@include('postulant.ofertas-laborales.details')</div>
            </section>
        </div>
    </div>
</x-postulant-page>
@endsection
