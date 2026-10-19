@if (!empty($subject['curso_combinado_libre_disposicion']))
    <div><span class="badge rounded-pill text-bg-light border">Libre disposición</span></div>
    @if (!empty($subject['plan_comun_asociado']))
        <div class="small text-muted">Plan común asociado: {{ $subject['plan_comun_asociado'] }}</div>
    @endif
@elseif (count($subject['curso_combinado_desglose'] ?? []) > 1)
    @foreach ($subject['curso_combinado_desglose'] as $part)
        <div class="small text-muted">{{ $part['nombre'] }} · {{ $part['bloque'] }}</div>
    @endforeach
@endif
