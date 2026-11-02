@php
    $campoInput = $definicion['campo'];
    $valorInput = old($campoInput, $definicion['valor'] ?? $item?->$campoInput);
    if (($definicion['fecha'] ?? false) && $valorInput instanceof \DateTimeInterface) { $valorInput = $valorInput->format('Y-m-d'); }
@endphp
<label for="{{ $campoInput }}" class="form-label">{{ $definicion['etiqueta'] }} <span class="text-danger" aria-hidden="true" data-padron-required="{{ $campoInput }}" @if(!($definicion['obligatorio'] ?? false)) hidden @endif>*</span></label>
<div @class(['slep-listing__input-unit' => !empty($definicion['unidad'])])>
    <input type="text" id="{{ $campoInput }}" name="{{ $campoInput }}" class="form-control @error($campoInput) is-invalid @enderror" value="{{ $valorInput }}" data-padron-field
        @required($definicion['obligatorio'] ?? false) aria-describedby="{{ $campoInput }}-help {{ $campoInput }}-error" @error($campoInput) aria-invalid="true" @enderror
        @if($definicion['fecha'] ?? false) data-padron-date placeholder="AAAA-MM-DD" autocomplete="off"
        @elseif($definicion['entero'] ?? false) inputmode="numeric" data-padron-integer data-min="{{ $definicion['minimo'] }}" data-max="{{ $definicion['maximo'] }}"
        @else maxlength="{{ $definicion['maximo'] ?? 255 }}" @endif>
    @if(!empty($definicion['unidad']))<span aria-hidden="true">{{ $definicion['unidad'] }}</span>@endif
</div>
<p class="form-text" id="{{ $campoInput }}-help" @if($definicion['fecha'] ?? false) data-padron-date-help @endif>@if($definicion['fecha'] ?? false)Formato AAAA-MM-DD si el calendario no está disponible.@elseif($definicion['entero'] ?? false){{ $definicion['minimo'] }} a {{ $definicion['maximo'] }} {{ empty($definicion['unidad']) ? 'bienios' : 'horas semanales' }}.@endif</p>
<div class="invalid-feedback" id="{{ $campoInput }}-error">@error($campoInput){{ $message }}@enderror</div>
