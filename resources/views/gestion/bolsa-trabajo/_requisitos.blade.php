@php
    $savedRules = collect($item->requisitos_documentales ?? [])->map(fn ($rule) => $rule + ['incluir' => true]);
    $rules = collect(old('requisitos_documentales', $savedRules))->whereNotNull('document_type_id')->keyBy('document_type_id');
@endphp
<details class="mt-4 border rounded-4 p-3" @if ($rules->contains(fn ($rule) => ! empty($rule['incluir'])) || $errors->has('requisitos_documentales.*')) open @endif>
    <summary class="fw-semibold"><i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i> Requisitos documentales de la oferta</summary>
    <p class="form-text mt-2">Marca los documentos adicionales o indica si uno de los requisitos del cargo necesita aprobación previa o vigencia. La vigencia se cuenta desde la fecha de emisión.</p>
    <div class="row g-3 mt-1">
        @foreach ($documentTypes as $type)
            @php $rule = $rules->get($type->id, []); @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <fieldset class="rounded-4 border bg-light p-3 h-100">
                    <legend class="h6 fw-semibold text-break">{{ $type->label }}</legend>
                    <input type="hidden" name="requisitos_documentales[{{ $type->id }}][document_type_id]" value="{{ $type->id }}">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="require-{{ $type->id }}" name="requisitos_documentales[{{ $type->id }}][incluir]" value="1" @checked($rule['incluir'] ?? false)>
                        <label class="form-check-label" for="require-{{ $type->id }}">Exigir en esta oferta</label>
                    </div>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" id="approve-{{ $type->id }}" name="requisitos_documentales[{{ $type->id }}][aprobado]" value="1" @checked($rule['aprobado'] ?? false)>
                        <label class="form-check-label" for="approve-{{ $type->id }}">Exigir aprobación previa</label>
                    </div>
                    <label class="form-label small mt-2" for="days-{{ $type->id }}">Vigencia en días (opcional)</label>
                    <input id="days-{{ $type->id }}" class="form-control @error('requisitos_documentales.'.$type->id.'.vigencia_dias') is-invalid @enderror" type="number" min="1" max="3650" name="requisitos_documentales[{{ $type->id }}][vigencia_dias]" value="{{ $rule['vigencia_dias'] ?? '' }}">
                    @error('requisitos_documentales.'.$type->id.'.vigencia_dias')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </fieldset>
            </div>
        @endforeach
    </div>
</details>
