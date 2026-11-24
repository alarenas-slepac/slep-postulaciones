@php
    $formId = 'upload-'.$type->id.'-'.($document?->id ?? ($additional ? 'additional' : 'new'));
    $failed = (int) old('type_id') === $type->id && (int) old('library_document_id') === (int) $document?->id && (bool) old('additional') === $additional;
@endphp
<form method="POST" action="{{ route('postulant.documents.store', $type) }}" enctype="multipart/form-data" class="mt-3" data-slep-saving="Subiendo…">
    @csrf
    <input type="hidden" name="type_id" value="{{ $type->id }}">
    @if ($document)<input type="hidden" name="library_document_id" value="{{ $document->id }}">@endif
    @if ($additional)<input type="hidden" name="additional" value="1">@endif
    @if ($context)
        <input type="hidden" name="oferta" value="{{ $context['offer']->id }}">
        <input type="hidden" name="cargo" value="{{ $context['key'] }}">
    @endif
    <div class="row g-3 align-items-start">
        @if ($type->supportsMultipleCredentials())
            <div class="col-12 col-md-5">
                <label for="{{ $formId }}-label" class="form-label">Nombre para identificar el documento{{ $additional ? '' : ' (opcional)' }}</label>
                <input id="{{ $formId }}-label" type="text" name="credential_label" maxlength="150" class="form-control @if ($failed && $errors->has('credential_label')) is-invalid @endif" value="{{ $failed ? old('credential_label') : $document?->credential_label }}" @required($additional)>
                @if ($failed) @error('credential_label')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
            </div>
        @endif
        <div class="col-12 col-sm-6 col-md-4">
            <label for="{{ $formId }}-date" class="form-label">Fecha de emisión (opcional)</label>
            <input id="{{ $formId }}-date" type="text" inputmode="numeric" autocomplete="off" name="issued_on" placeholder="AAAA-MM-DD" maxlength="10" class="form-control @if ($failed && $errors->has('issued_on')) is-invalid @endif" value="{{ $failed ? old('issued_on') : '' }}" aria-describedby="{{ $formId }}-date-help">
            <div class="form-text" id="{{ $formId }}-date-help">Úsala cuando la oferta exija vigencia.</div>
            @if ($failed) @error('issued_on')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        </div>
        <div class="col-12 col-md">
            <label for="{{ $formId }}-file" class="form-label">Archivo PDF</label>
            <input id="{{ $formId }}-file" type="file" name="file" class="form-control @if ($failed && $errors->has('file')) is-invalid @endif" accept="application/pdf,.pdf" aria-describedby="{{ $formId }}-file-help" required>
            <div class="form-text" id="{{ $formId }}-file-help">Máximo 10 MB.</div>
            @if ($failed) @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        </div>
    </div>
    <button type="submit" class="slep-listing__button slep-listing__button--primary mt-3"><i class="bi bi-upload" aria-hidden="true"></i> <span data-slep-save-label>{{ $document ? 'Reemplazar PDF' : 'Subir PDF' }}</span></button>
</form>
