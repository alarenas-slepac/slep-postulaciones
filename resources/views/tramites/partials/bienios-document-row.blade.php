@php
    $selectedDocType = (string) ($row['tipo_documento'] ?? '');
    $selectedDocConfig = (array) ($documentsConfig[$selectedDocType] ?? []);
    $requiresPeriod = (bool) ($selectedDocConfig['requires_period'] ?? false);
    $isMultiple = (bool) ($selectedDocConfig['multiple'] ?? false);
    $isFixedRow = (bool) ($selectedDocConfig['fixed_row'] ?? false);
    $lockedType = (bool) ($row['locked_type'] ?? false);
    $replacementLabel = (string) ($row['replacement_label'] ?? '');
@endphp
<div class="tramite-doc-row border rounded p-3 mb-3">
    <div class="row g-3 align-items-start">
        <div class="col-md-4">
            <label class="form-label">Tipo de documento <span class="text-danger">*</span></label>
            @if ($isFixedRow || $lockedType)
                <input type="text" class="form-control" value="{{ $selectedDocConfig['label'] ?? 'Documento' }}" readonly>
                <input type="hidden" name="documentos[{{ $index }}][tipo_documento]" value="{{ $selectedDocType }}">
                @if ($lockedType)
                    <input type="hidden" name="documentos[{{ $index }}][locked_type]" value="1">
                @endif
            @else
                <select name="documentos[{{ $index }}][tipo_documento]" class="form-select js-doc-type @error('documentos.' . $index . '.tipo_documento') is-invalid @enderror">
                    <option value="">Seleccione...</option>
                    @foreach ($documentsConfig as $docKey => $docConfig)
                        @continue(($docConfig['selectable_in_dynamic_rows'] ?? true) === false && $selectedDocType !== $docKey)
                        <option value="{{ $docKey }}" data-requires-period="{{ !empty($docConfig['requires_period']) ? '1' : '0' }}" data-multiple="{{ !empty($docConfig['multiple']) ? '1' : '0' }}" @selected($selectedDocType === $docKey)>
                            {{ $docConfig['label'] }}
                        </option>
                    @endforeach
                </select>
            @endif
            @error('documentos.' . $index . '.tipo_documento')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @if ($replacementLabel !== '')
                <div class="form-text text-danger">{{ $replacementLabel }}</div>
            @endif
            @if (!empty($selectedDocConfig['template_download']))
                <div class="form-text">
                    Este documento tiene plantilla descargable desde el botón superior.
                </div>
            @endif
        </div>
        <div class="col-md-2">
            <label class="form-label">Tipo de archivo</label>
            <select class="form-select" disabled>
                <option selected>PDF</option>
            </select>
            <input type="hidden" name="documentos[{{ $index }}][formato]" value="pdf">
        </div>
        <div class="col-md-4">
            <label class="form-label">Archivo <span class="text-danger">*</span></label>
            <input type="file" name="documentos[{{ $index }}][archivo]" accept="application/pdf,.pdf" class="form-control @error('documentos.' . $index . '.archivo') is-invalid @enderror">
            <div class="form-text">Solo PDF, máximo 100 MB.</div>
            @error('documentos.' . $index . '.archivo')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-2 d-flex align-items-start justify-content-md-end">
            @unless($isFixedRow)
                <button type="button" class="btn btn-outline-danger js-remove-row">
                    <i class="bi bi-trash"></i> Borrar
                </button>
            @endunless
        </div>

        @if ($requiresPeriod)
            <div class="col-md-3 js-date-start-wrap">
                <label class="form-label">Fecha inicio</label>
                <input type="date" name="documentos[{{ $index }}][fecha_inicio]" value="{{ $row['fecha_inicio'] ?? '' }}" class="form-control js-date-start @error('documentos.' . $index . '.fecha_inicio') is-invalid @enderror">
                @error('documentos.' . $index . '.fecha_inicio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3 js-date-end-wrap">
                <label class="form-label">Fecha término</label>
                <input type="date" name="documentos[{{ $index }}][fecha_termino]" value="{{ $row['fecha_termino'] ?? '' }}" class="form-control js-date-end @error('documentos.' . $index . '.fecha_termino') is-invalid @enderror">
                @error('documentos.' . $index . '.fecha_termino')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif
    </div>
</div>
