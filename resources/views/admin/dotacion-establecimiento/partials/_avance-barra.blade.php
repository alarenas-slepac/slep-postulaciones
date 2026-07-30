@php
    $valor = min(100, max(0, (float) ($porcentaje ?? 0)));
    $clase = $valor >= 100 ? 'bg-success' : ($valor >= 80 ? 'bg-primary' : ($valor >= 50 ? 'bg-warning' : ($valor > 0 ? 'bg-info' : 'bg-secondary')));
@endphp
<div class="avance-barra-wrap">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
        <span class="small fw-semibold">{{ $label ?? 'Avance' }}</span>
        <span class="small fw-bold">{{ number_format($valor, 1, ',', '.') }}%</span>
    </div>
    <div class="progress avance-progress" role="progressbar" aria-valuenow="{{ $valor }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar {{ $clase }}" style="width: {{ $valor }}%"></div>
    </div>
    @isset($detalle)
        <div class="text-muted small mt-1">{{ $detalle }}</div>
    @endisset
</div>
