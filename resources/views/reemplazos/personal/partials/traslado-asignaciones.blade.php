<div class="border-top mt-3 pt-2">
    <strong>Liberación de asignaciones por traslado</strong>
    <div>El RUT tiene propuestas en el RBD {{ $traslado['destino_rbd'] ?? 'destino' }}. Esta autorización permite inactivar únicamente las asignaciones del establecimiento de origen al aplicar el padrón.</div>
    <div class="alert alert-info my-2">Alcance: {{ $traslado['cantidad'] }} asignaciones · {{ $traslado['horas'] }} h. La liberación no reasigna horas al destino ni borra documentos o historial. Los contratos se procesan según las correspondencias registradas.</div>
    @if ($traslado['nuevas_lineas'] ?? 0)
        <div class="alert alert-warning my-2">Hay {{ $traslado['nuevas_lineas'] }} línea(s) nueva(s) en el destino. Si corresponden a contratos existentes, seleccione sus IDs en «Ver filas del RUT» para conservarlos. Crear líneas nuevas y confirmar bajas no libera por sí solo las asignaciones anteriores.</div>
    @endif
    @if ($traslado['confirmada'])
        <div class="text-success fw-semibold">Liberación por traslado confirmada, pendiente de aplicar.</div>
        <div>Usuario #{{ $traslado['usuario_id'] }}: {{ $traslado['justificacion'] }}</div>
    @elseif ($traslado['desactualizada'])
        <div class="text-danger">El alcance cambió. Retire la confirmación anterior y registre nuevamente el traslado.</div>
    @endif
    @if ($traslado['confirmada'] || ! $traslado['elegible'])
        @if (! $traslado['elegible'])
            @foreach ($traslado['motivos'] ?? ['El traslado dejó de ser elegible; revise el RUT, el RBD de origen y las asignaciones activas.'] as $motivo)
                <div class="text-danger">{{ $motivo }}</div>
            @endforeach
        @endif
    @endif
    @if (($traslado['elegible'] || $traslado['autorizada']) && ! $obsoleta && ! $revision->errores && ! $revision->aplicada_at)
        <details class="mt-2">
            <summary>{{ $traslado['autorizada'] ? 'Retirar confirmación de traslado' : 'Confirmar traslado y liberar asignaciones al aplicar' }}</summary>
            <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" class="mt-2">
                @csrf
                <input type="hidden" name="accion" value="{{ $traslado['autorizada'] ? 'retirar_traslado_asignaciones' : 'confirmar_traslado_asignaciones' }}">
                <input type="hidden" name="revision" value="{{ $revision->id }}">
                <input type="hidden" name="rut" value="{{ $traslado['rut'] }}">
                <input type="hidden" name="origen" value="{{ $traslado['alcance']['origen'] }}">
                <input type="hidden" name="destino" value="{{ $traslado['alcance']['destino'] }}">
                <input type="hidden" name="alcance_hash" value="{{ $traslado['alcance_hash'] }}">
                <input type="hidden" name="decision_anterior" value="{{ $traslado['ultima_id'] }}">
                <input type="hidden" name="conflictos_page" value="{{ $conflictosPaginados->currentPage() }}">
                <label class="d-block">Justificación
                    <textarea name="justificacion" class="form-control" rows="2" minlength="10" maxlength="2000" required></textarea>
                </label>
                <label class="d-block my-2">
                    <input type="checkbox" name="confirmar_alcance" value="1" required>
                    {{ $traslado['autorizada'] ? 'Confirmo retirar esta autorización.' : 'Confirmo que el funcionario se trasladó al RBD de destino y autorizo liberar las asignaciones del origen solo al aplicar el padrón.' }}
                </label>
                <button class="btn btn-outline-danger btn-sm">{{ $traslado['autorizada'] ? 'Retirar confirmación' : 'Registrar liberación por traslado' }}</button>
            </form>
        </details>
    @endif
    <p class="small mb-0 mt-2">La liberación es diferida: mientras la revisión no se aplique, las asignaciones siguen activas y no se realizan cambios en Dotación.</p>
</div>
