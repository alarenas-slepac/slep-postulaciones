@if (($canManageJustificacionesSobredotacion ?? false) && isset($registro) && $registro->exists && $registro->getKey())
    <form method="POST" action="{{ route('admin.dotacion-establecimiento.sobredotacion.justificaciones.destroy', [$establecimiento, $registro->getKey()]) }}" class="mt-3" onsubmit="return confirm('¿Eliminar esta justificación? Si no quedan otras, el docente podrá volver a seleccionarse según su saldo y los requisitos de la función.');">
        @csrf
        @method('DELETE')
        <input type="hidden" name="anio" value="{{ $anio }}">
        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar justificación</button>
    </form>
@endif
