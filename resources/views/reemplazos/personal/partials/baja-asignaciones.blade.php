<div class="border-top mt-3 pt-2">
    <strong>Baja por ausencia del padrón completo</strong>
    <div>Este RUT no tiene filas en el archivo, en ningún establecimiento.</div>
    <div>Alcance del año {{ $revision->anio }}: {{ $baja['cantidad'] }} asignaciones · {{ $baja['horas'] }} h · {{ $baja['establecimientos'] }} establecimiento(s).</div>
    <div>IDs contractuales a dar de baja: {{ implode(', ', $baja['alcance']['bajas']) }}.</div>
    @if ($baja['confirmada'])
        <div class="text-success fw-semibold">Liberación confirmada, pendiente de aplicar.</div>
        <div>Usuario #{{ $baja['usuario_id'] }}: {{ $baja['justificacion'] }}</div>
    @elseif ($baja['desactualizada'])
        <div class="text-danger">El alcance cambió. La confirmación anterior no libera estas asignaciones; retírela y confirme nuevamente el alcance actualizado.</div>
    @endif
    @if (! $baja['elegible'])
        <div class="text-danger">Revise todas las ausencias del RUT y los vínculos de sus asignaciones. No se permite liberar IDs de otro funcionario, otro establecimiento contractual o de años anteriores.</div>
    @endif
    @if (($baja['elegible'] || $baja['autorizada']) && ! $obsoleta && ! $revision->errores && ! $revision->aplicada_at)
        <details class="mt-2">
            <summary>{{ $baja['autorizada'] ? 'Retirar confirmación de liberación' : 'Confirmar baja y liberar asignaciones al aplicar' }}</summary>
            <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" class="mt-2">
                @csrf
                <input type="hidden" name="accion" value="{{ $baja['autorizada'] ? 'retirar_baja_asignaciones' : 'confirmar_baja_asignaciones' }}">
                <input type="hidden" name="revision" value="{{ $revision->id }}">
                <input type="hidden" name="rut" value="{{ $baja['rut'] }}">
                <input type="hidden" name="alcance_hash" value="{{ $baja['alcance_hash'] }}">
                <input type="hidden" name="decision_anterior" value="{{ $baja['ultima_id'] }}">
                <input type="hidden" name="conflictos_page" value="{{ $conflictosPaginados->currentPage() }}">
                <label class="d-block">Justificación
                    <textarea name="justificacion" class="form-control" rows="2" minlength="10" maxlength="2000" required></textarea>
                </label>
                <label class="d-block my-2">
                    <input type="checkbox" name="confirmar_alcance" value="1" required>
                    {{ $baja['autorizada'] ? 'Confirmo retirar esta autorización; la baja seguirá bloqueada si mantiene asignaciones.' : 'Confirmo el retiro del funcionario y autorizo liberar este alcance solo al aplicar definitivamente el padrón.' }}
                </label>
                <button class="btn btn-outline-danger btn-sm">{{ $baja['autorizada'] ? 'Retirar confirmación' : 'Registrar baja con liberación diferida' }}</button>
            </form>
        </details>
    @endif
    <p class="small mb-0 mt-2">No se borran contratos, documentos, necesidades ni asignaciones históricas. Las horas dejan de cubrir necesidades al inactivar sus asignaciones; no se reasignan automáticamente.</p>
</div>
