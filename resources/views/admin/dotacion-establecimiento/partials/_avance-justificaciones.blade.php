@if (data_get($avance, 'proceso_2027.aplica', false))
    <div class="dotacion-workspace border rounded-4 p-3 mt-3 small">
        <div class="fw-semibold mb-1">Etapa 7 · Horas por justificar</div>
        @if (data_get($avance, 'proceso_2027.justificaciones') !== null)
            <div class="fw-bold">{{ data_get($avance, 'proceso_2027.justificaciones.detalle') }}</div>
            <div class="text-muted mt-1">{{ \App\Support\DotacionEstablecimientoCalculator::formatHoras(data_get($avance, 'proceso_2027.justificaciones.horas_justificadas', 0)) }} h con justificación vigente.</div>
            <span class="badge rounded-pill {{ data_get($avance, 'proceso_2027.justificaciones.completo', false) ? 'text-bg-success' : 'text-bg-warning' }} mt-2">{{ data_get($avance, 'proceso_2027.justificaciones.completo', false) ? 'Completada' : 'Pendiente' }}</span>
        @else
            <div class="text-muted">No fue posible calcular las horas por justificar.</div>
        @endif
    </div>
@endif
