@php
    $valorMedidor = max(0, (float) $valor);
    $maximoMedidor = max(0, (float) $maximo);
    $porcentajeMedidor = $maximoMedidor > 0 ? round($valorMedidor / $maximoMedidor * 100, 1) : null;
    $anchoMedidor = min(100, $porcentajeMedidor ?? 0);
    $valorTextoMedidor = number_format($valorMedidor, 0, ',', '.');
    $sufijoTextoMedidor = $sufijoValor ?? '';
    $porcentajeTextoMedidor = $porcentajeMedidor !== null ? number_format($porcentajeMedidor, 1, ',', '.').' %' : '—';
@endphp
@once
    @push('styles')
        @vite('resources/css/progreso-animado.css')
    @endpush
    @push('scripts')
        @vite('resources/js/progreso-animado.js')
    @endpush
@endonce
<div class="slep-meter" data-slep-reveal>
    <div class="slep-meter__heading">
        <span class="slep-meter__label" id="{{ $id }}">{{ $etiqueta }}</span>
        <strong class="slep-meter__value">
            <span aria-hidden="true" data-slep-count="{{ $valorMedidor }}" data-slep-suffix="{{ $sufijoTextoMedidor }}">{{ $valorTextoMedidor }}{{ $sufijoTextoMedidor }}</span><span class="visually-hidden">{{ $valorTextoMedidor }}{{ $sufijoTextoMedidor }}</span>
            <small class="slep-meter__percent"><span aria-hidden="true" @if($porcentajeMedidor !== null) data-slep-count="{{ $porcentajeMedidor }}" data-slep-decimals="1" data-slep-suffix=" %" @endif>{{ $porcentajeTextoMedidor }}</span><span class="visually-hidden">{{ $porcentajeTextoMedidor }}</span></small>
        </strong>
    </div>
    <div class="slep-meter__track" @if($porcentajeMedidor !== null) role="progressbar" aria-labelledby="{{ $id }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $anchoMedidor }}" aria-valuetext="{{ $valorTextoMedidor }} de {{ number_format($maximoMedidor, 0, ',', '.') }} · {{ $porcentajeTextoMedidor }}" @else aria-hidden="true" @endif>
        <span class="slep-meter__fill" aria-hidden="true" data-slep-fill="{{ $anchoMedidor }}" style="width: {{ $anchoMedidor }}%"></span>
    </div>
    @if(!empty($detalle))<p class="slep-meter__detail">{{ $detalle }}</p>@endif
</div>
