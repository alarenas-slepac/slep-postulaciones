@php
    $id = 'mat-'.str_replace(['.', '_'], '-', $name);
    $tipo = $tipo ?? 'text';
    $valor = old($name, data_get($datos ?? [], $name, $defecto ?? ''));
    $nombreHtml = preg_replace('/\.([^.]+)/', '[$1]', $name);
@endphp
<div class="{{ $col ?? 'col-md-6 col-xl-4' }}">
    <label class="form-label" for="{{ $id }}">{{ $label }}@if ($obligatorio ?? false) <span class="text-danger" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>@endif</label>
    <input class="form-control @error($name) is-invalid @enderror" id="{{ $id }}" name="{{ $nombreHtml }}" type="{{ $tipo }}" value="{{ $valor }}"
           @if ($tipo === 'date') data-mat-date @endif @if ($obligatorio ?? false) required @endif @if ($soloLectura ?? false) readonly @endif
           maxlength="{{ $maximo ?? 255 }}" aria-describedby="{{ $id }}-ayuda {{ $id }}-error" @error($name) aria-invalid="true" @enderror>
    <div class="form-text" id="{{ $id }}-ayuda">{{ $ayuda ?? '' }}</div>
    <div class="invalid-feedback" id="{{ $id }}-error">@error($name){{ $message }}@enderror</div>
</div>
