<tr data-asignacion-referencia>
    <td class="small">{{ $detalle['tipo'] }}</td>
    <th scope="row" class="fw-normal">
        <div class="fw-semibold">{{ $detalle['titulo'] }}</div>
        @if ($detalle['fuente'])<div class="small text-muted">{{ $detalle['fuente'] }}</div>@endif
        @if ($detalle['sin_necesidad_vigente'])<div class="small text-muted">Sin necesidad vigente vinculada</div>@endif
        @if ($detalle['observacion'])<div class="small text-muted">Observación: {{ $detalle['observacion'] }}</div>@endif
    </th>
    <td class="small">{{ $detalle['curso'] }}</td>
    <td class="small">{{ $detalle['subvencion'] }}</td>
    <td class="text-end">{{ $detalle['horas_pedagogicas'] === null ? '—' : $fmtProyeccion($detalle['horas_pedagogicas']) }}@if ($detalle['proporcion'])<div class="small text-muted">{{ $detalle['proporcion'] }}</div>@endif</td>
    <td class="text-end fw-semibold">{{ $convertido ? '—' : $fmtProyeccion($detalle['horas_contrato']) }}</td>
</tr>
