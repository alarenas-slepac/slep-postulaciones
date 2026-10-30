@if (is_array($valor))
    @forelse ($valor as $elemento)
        @if (is_array($elemento))<span class="d-block">{{ $elemento['nombre_completo'] ?? $elemento['nombre_original'] ?? 'Sin nombre' }}@if (!empty($elemento['rut'])) · {{ $elemento['rut'] }}@endif @if (!empty($elemento['fecha_nacimiento'])) · {{ \Carbon\Carbon::parse($elemento['fecha_nacimiento'])->format('d-m-Y') }}@endif</span>@endif
    @empty Sin registros @endforelse
@elseif (is_bool($valor)){{ $valor ? 'Sí' : 'No' }}
@elseif ($campo === 'regla_fuero_dominga'){{ $valor === 'anio_84_dias' ? 'Un año y 84 días · ampliación GDP' : '30 días' }}
@elseif ($campo === 'causal_ampliacion_dominga'){{ config('maternidad.causales_ampliacion_dominga.'.$valor) ?? 'Sin ampliación' }}
@elseif (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)){{ \Carbon\Carbon::parse($valor)->format('d-m-Y') }}
@else {{ filled($valor) ? $valor : 'Sin dato' }} @endif
