@extends('layouts.app')
@section('content')
<x-postulant-page title="Mis documentos" icon="bi-folder2-open" description="Guarda tus antecedentes una vez y reutilízalos al postular.">
    <x-slot:actions>
        @if ($context)
            <a href="{{ route('postulant.ofertas-laborales.show', ['oferta' => $context['offer'], 'cargo' => $context['key']]) }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a la oferta</a>
        @endif
        <a href="{{ route('postulant.profile.edit') }}" class="slep-listing__button slep-listing__button--secondary">Mi perfil</a>
        <a href="{{ route('postulant.profile.pdf') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Descargar mi perfil</a>
    </x-slot:actions>
    <section class="slep-listing__panel" aria-labelledby="document-library-heading">
        <div class="slep-listing__panel-header flex-wrap">
            <h2 id="document-library-heading">{{ $requestedType ? 'Documento solicitado' : 'Biblioteca de documentos' }}</h2>
            @if ($requestedType)
                <a href="{{ route('postulant.documents.index', $context ? ['oferta' => $context['offer']->id, 'cargo' => $context['key']] : []) }}" class="slep-listing__button slep-listing__button--secondary">Ver toda la biblioteca</a>
            @endif
        </div>
        <div class="px-3 px-md-4">
            @forelse ($documentTypes as $type)
                @php
                    $docs = $byTypeId->get($type->id, collect());
                    $canUpload = $visibleTypeIds->has($type->id);
                @endphp
                <section class="border-bottom py-3 py-md-4" aria-labelledby="document-type-{{ $type->id }}">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h3 id="document-type-{{ $type->id }}" class="h6 fw-bold mb-0">{{ $type->label }}</h3>
                        @if ($canUpload)
                            <span class="slep-status-pill slep-status-pill--{{ $requiredTypeIds->has($type->id) && $docs->isEmpty() ? 'warning' : 'neutral' }}">{{ $requiredTypeIds->has($type->id) ? 'Requerido' : 'Opcional' }}</span>
                        @endif
                    </div>
                    @if ($type->template_path)
                        <a class="d-inline-block mb-2" href="{{ route('postulant.documents.template', ['type' => $type, 'v' => $type->slug === 'declaracion_cargo_publico' ? '2026.8.12.378' : null]) }}"><i class="bi bi-download" aria-hidden="true"></i> Descargar plantilla</a>
                    @endif
                    @foreach ($docs as $doc)
                        @php
                            [$variant, $status] = match ($doc->status) {
                                'approved' => ['success', 'Aprobado'],
                                'rejected' => ['danger', 'Por corregir'],
                                default => ['info', 'Pendiente de revisión'],
                            };
                        @endphp
                        <div class="rounded-4 border bg-light p-3 mb-2">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="text-break">
                                    <div class="fw-semibold">{{ $doc->credential_label ?: ($doc->original_name ?: 'PDF cargado') }}</div>
                                    <p class="small slep-listing__muted mb-1">{{ $doc->issued_on ? 'Emisión '.$doc->issued_on->format('d-m-Y').' · ' : '' }}{{ cl_datetime($doc->updated_at) }}</p>
                                    <span class="slep-status-pill slep-status-pill--{{ $variant }}">{{ $status }}</span>
                                    @if ($doc->reviewer_comment)<p class="small mb-0 mt-2 text-break">{{ $doc->reviewer_comment }}</p>@endif
                                </div>
                                @if ($doc->path)
                                    <a href="{{ route('postulant.documents.download', $doc) }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-download" aria-hidden="true"></i> Descargar PDF</a>
                                @endif
                            </div>
                            @if ($context && ! $doc->issued_on && $doc->path && $doc->status !== 'rejected'
                                && collect($context['offer']->requisitos_documentales)->contains(fn ($rule) => (int) $rule['document_type_id'] === $type->id && ! empty($rule['vigencia_dias'])))
                                <details class="mt-3">
                                    <summary class="fw-semibold">Registrar fecha de emisión</summary>
                                    <form method="POST" action="{{ route('postulant.documents.issue-date', $doc) }}" class="mt-3">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="oferta" value="{{ $context['offer']->id }}">
                                        <input type="hidden" name="cargo" value="{{ $context['key'] }}">
                                        <label for="issue-{{ $doc->id }}" class="form-label">Fecha que aparece en el PDF</label>
                                        <div class="d-flex flex-wrap gap-2">
                                            <input id="issue-{{ $doc->id }}" type="text" name="issued_on" class="form-control w-auto mw-100" inputmode="numeric" placeholder="AAAA-MM-DD" maxlength="10" required>
                                            <button type="submit" class="slep-listing__button slep-listing__button--secondary">Guardar fecha</button>
                                        </div>
                                    </form>
                                </details>
                            @endif
                            @if ($canUpload)
                                <details class="mt-3" @if ((int) old('library_document_id') === $doc->id && $errors->any()) open @endif>
                                    <summary class="fw-semibold">Reemplazar archivo</summary>
                                    @include('postulant.documents.upload', ['document' => $doc, 'additional' => false])
                                </details>
                            @endif
                        </div>
                    @endforeach
                    @if ($docs->isEmpty() && $canUpload)
                        @include('postulant.documents.upload', ['document' => null, 'additional' => false])
                    @elseif ($canUpload && $type->supportsMultipleCredentials())
                        <details class="mt-3" @if ((int) old('type_id') === $type->id && old('additional') && $errors->any()) open @endif>
                            <summary class="fw-semibold">Agregar otro documento de este tipo</summary>
                            @include('postulant.documents.upload', ['document' => null, 'additional' => true])
                        </details>
                    @endif
                </section>
            @empty
                <div class="slep-listing__empty"><i class="bi bi-folder2-open" aria-hidden="true"></i><h3>No hay documentos disponibles para esta selección</h3></div>
            @endforelse
        </div>
    </section>
</x-postulant-page>
@endsection
