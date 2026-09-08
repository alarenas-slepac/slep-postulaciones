<div class="alert alert-info mb-2">
    <strong>Posible redistribución de financiamiento · requiere confirmación</strong>
    <div>Conservar ID {{ $propuesta['receptor_id'] }} · {{ $propuesta['financiamiento_destino'] }}:
        {{ $propuesta['horas_antes'] }} → {{ $propuesta['horas_despues'] }} h.</div>
    <div>Proponer baja del ID {{ $propuesta['absorbido_id'] }} · {{ $propuesta['financiamiento_origen'] }}:
        {{ $propuesta['horas_absorbidas'] }} h absorbidas. No se elimina el registro.</div>
    <div>Total del RUT: {{ $propuesta['total_antes'] }} → {{ $propuesta['total_despues'] }} h.</div>
    <ol class="mb-1 mt-2">
        <li>En «Resolver correspondencia», seleccione el ID {{ $propuesta['receptor_id'] }} y justifique la redistribución.</li>
        <li>En la fila «Ausente» del ID {{ $propuesta['absorbido_id'] }}, confirme la baja con su justificación.</li>
    </ol>
    <div class="small">Sugerencia del análisis original, no una decisión registrada. Revise el historial de decisiones.
        No se trasladan asignaciones ni documentos. Una asignación activa vinculada directamente al ID absorbido
        mantiene el bloqueo hasta su revisión en Dotación. Confirmar estas decisiones no aplica el padrón.</div>
</div>
