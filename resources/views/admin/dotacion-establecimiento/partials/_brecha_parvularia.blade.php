@php
    $brechaParvularia = round($contratoEducacionParvulariaMasPie - $horasContratoParvularia, 2);
    $tonoParvularia = $brechaParvularia < 0 ? 'danger' : ($brechaParvularia > 0 ? 'success' : 'primary');
    $estadoParvularia = $brechaParvularia < 0 ? 'Horas de sobredotación' : ($brechaParvularia > 0 ? 'Horas por contratar' : 'Dotación cuadrada');
@endphp
<div class="card dotacion-kpi border-0" data-brecha="parvularia">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <div>
                <div class="text-muted small fw-semibold">Sobredotación Parvularia</div>
                <div class="small text-muted">{{ $estadoParvularia }}</div>
            </div>
            <span class="kpi-icon text-{{ $tonoParvularia }}"><i class="bi bi-people-fill"></i></span>
        </div>
        <div class="fs-2 fw-bold text-{{ $tonoParvularia }}">{{ $fmt(abs($brechaParvularia)) }}</div>
        <div class="small text-muted mt-1">
            Contrato Educación Parvularia + PIE − Horas contrato parvularia.<br>
            {{ $fmt($contratoEducacionParvulariaMasPie) }} − {{ $fmt($horasContratoParvularia) }}
            @if ($brechaParvularia > 0)
                <div>Se requiere contratación adicional de Educadora de Párvulos por {{ $fmt($brechaParvularia) }} horas.</div>
            @endif
        </div>
    </div>
</div>
