<li class="mt-1">
    @if (!empty($pendiente['filtros']))
        <a data-dotacion-contexto-salida href="{{ route('admin.dotacion-establecimiento.show', array_merge([$establecimiento, 'anio' => $anio, 'tab' => 'asignacion', 'asig_pendientes' => 1], $pendiente['filtros'])).'#dotacion-asignacion' }}">{{ $pendiente['texto'] }}</a>
    @else
        {{ $pendiente['texto'] }}
    @endif
</li>
