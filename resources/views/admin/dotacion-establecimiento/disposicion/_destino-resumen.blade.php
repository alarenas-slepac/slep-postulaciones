@php($seleccionado = $destinos->firstWhere('id', (int) old('destino_id', $corregir?->destino_id)))
<section class="slep-listing__panel mt-3 mb-0" data-disposicion-resumen @if(!$seleccionado) hidden @endif aria-labelledby="dis-resumen-destino" aria-live="polite">
    <div class="slep-listing__panel-header"><div><h3 id="dis-resumen-destino">Horas del establecimiento de destino</h3><p class="mb-0 slep-listing__muted" data-disposicion-nombre>{{ $seleccionado ? $seleccionado['rbd'].' · '.$seleccionado['nombre'] : '' }}</p></div></div>
    <div class="slep-listing__table-wrap"><table class="slep-listing__table">
        <caption class="visually-hidden">Horas y cantidad de cupos por contratar y contratas por renovar, por bloque.</caption>
        <thead><tr><th scope="col">Bloque</th><th scope="col">Docentes por contratar</th><th scope="col">Docentes a contrata por renovar</th></tr></thead>
        <tbody>@foreach($bloques as $key => $label)
            @php($ref = $seleccionado['bloques'][$key] ?? [])
            <tr><th scope="row">{{ $label }}</th>
                <td data-label="Docentes por contratar"><strong><span data-disposicion-valor="{{ $key }}.cupos">{{ $fmt($ref['cupos'] ?? 0) }}</span> h asignadas</strong><div><span data-disposicion-valor="{{ $key }}.personas_cupos">{{ $ref['personas_cupos'] ?? 0 }}</span> cupos</div></td>
                <td data-label="Contratas por renovar"><strong><span data-disposicion-valor="{{ $key }}.contrata">{{ $fmt($ref['contrata'] ?? 0) }}</span> h renovables</strong><div><span data-disposicion-valor="{{ $key }}.personas_contrata">{{ $ref['personas_contrata'] ?? 0 }}</span> contratas</div></td>
            </tr>
        @endforeach</tbody>
    </table></div>
    <p class="small slep-listing__muted px-3 pb-3 mb-0">Se suman sólo asignaciones activas de {{ $anio }} que coinciden con los filtros. Cada cupo o contrata se cuenta una vez en cada bloque; puede participar en más de uno.</p>
    <div class="border-top p-3">
        <h3>Cupos y destinaciones registradas</h3>
        <p class="small slep-listing__muted">Revise las horas requeridas, ya destinadas y disponibles de cada cupo. Seleccione el cupo en el campo anterior.</p>
        <div data-disposicion-cupos-lista>
            @include('admin.dotacion-establecimiento.disposicion._cupos-destino', ['cupos' => $seleccionado['cupos_destino'] ?? []])
        </div>
    </div>
</section>
<noscript>
    <details class="mt-3"><summary>Consultar referencias por bloque</summary>
        <div class="slep-listing__table-wrap mt-2"><table class="slep-listing__table">
            <thead><tr><th scope="col">Destino y bloque</th><th scope="col">Por contratar</th><th scope="col">Contratas por renovar</th></tr></thead>
            <tbody>@foreach($destinos as $destino)@foreach($bloques as $key => $label)
                @php($ref = $destino['bloques'][$key] ?? [])
                <tr><th scope="row">{{ $destino['rbd'] }} · {{ $destino['nombre'] }}<div>{{ $label }}</div></th>
                    <td data-label="Por contratar">{{ $fmt($ref['cupos'] ?? 0) }} h asignadas · {{ $ref['personas_cupos'] ?? 0 }} cupos</td>
                    <td data-label="Contratas por renovar">{{ $fmt($ref['contrata'] ?? 0) }} h renovables · {{ $ref['personas_contrata'] ?? 0 }} contratas</td></tr>
            @endforeach @endforeach</tbody>
        </table></div>
        @foreach($destinos as $destino)
            <h3 class="mt-3">{{ $destino['rbd'] }} · {{ $destino['nombre'] }}</h3>
            @include('admin.dotacion-establecimiento.disposicion._cupos-destino', ['cupos' => $destino['cupos_destino']])
        @endforeach
    </details>
</noscript>
