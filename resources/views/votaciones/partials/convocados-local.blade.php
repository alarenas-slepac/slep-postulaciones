@php
    $listaConvocados = $convocatoria['establecimientos'];
    $puedeEditarConvocados = ($editableConvocados ?? false) && ($edicionConvocadosDisponible ?? false);
@endphp
<details @if($listaConvocados->isEmpty()) open @endif>
    <summary class="fw-bold text-primary"><i class="bi bi-buildings me-1" aria-hidden="true"></i> Establecimientos convocados ({{ $listaConvocados->count() }})</summary>
    <div class="mt-3">
        <p class="small text-muted">{{ $convocatoria['personalizada'] ? 'Selección ajustada para este local y grupo.' : 'Precargados desde Padrón y locales de esta jornada, para los días de funcionamiento del grupo.' }}</p>
        @if($listaConvocados->isEmpty())
            <p class="small mb-3">No hay establecimientos convocados asociados a este local para el período del grupo.</p>
        @else
            <div class="table-responsive"><table class="table table-sm align-middle">
                <caption class="visually-hidden">Establecimientos que se acercan a votar al local {{ $ruta->establecimiento->nombre_establecimiento }}</caption>
                <thead><tr><th scope="col">RBD</th><th scope="col">Establecimiento convocado</th><th scope="col">Día / inicio<br><span class="small text-muted">según padrón</span></th></tr></thead>
                <tbody>@foreach($listaConvocados as $convocado)<tr><td class="text-nowrap">{{ $convocado['rbd'] }}</td><td>{{ $convocado['nombre'] }}</td><td>@if($convocado['dia'])<span class="d-block text-nowrap">{{ $convocado['dia'] }}</span><span class="d-block text-nowrap">{{ $convocado['hora'] }} hrs.</span>@else Por coordinar @endif</td></tr>@endforeach</tbody>
            </table></div>
        @endif
        @if($puedeEditarConvocados)
            @php
                $opcionesLocal = clone $opcionesConvocados;
                foreach ($listaConvocados as $convocado) {
                    if (! $opcionesLocal->has($convocado['rbd'])) $opcionesLocal->put($convocado['rbd'], $convocado['nombre']);
                }
                $seleccionConvocados = old('_local_convocados') == $ruta->id ? array_map('strval', old('establecimientos', []) ?? []) : $convocatoria['rbds'];
            @endphp
            <form method="POST" action="{{ route('votaciones.admin.rutas.convocados.update', $ruta) }}" data-disable-on-submit>
                @csrf @method('PUT')
                <input type="hidden" name="modo" value="manual">
                <input type="hidden" name="_local_convocados" value="{{ $ruta->id }}">
                <label class="form-label" for="convocados-{{ $ruta->id }}">Establecimientos que acudirán a este local</label>
                <select class="form-select js-votacion-convocados" id="convocados-{{ $ruta->id }}" name="establecimientos[]" multiple size="5" aria-describedby="ayuda-convocados-{{ $ruta->id }}">
                    @foreach($opcionesLocal->sort() as $rbdOpcion => $nombreOpcion)<option value="{{ $rbdOpcion }}" @selected(in_array((string) $rbdOpcion, $seleccionConvocados, true))>RBD {{ $rbdOpcion }} · {{ $nombreOpcion }}</option>@endforeach
                </select>
                <div class="form-text" id="ayuda-convocados-{{ $ruta->id }}">Busque por RBD o nombre. Puede agregar o quitar establecimientos; una selección vacía deja este local sin convocados. Sin buscador, mantenga Ctrl o Cmd para seleccionar varios.</div>
                @if(old('_local_convocados') == $ruta->id)
                    @error('establecimientos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @foreach($errors->get('establecimientos.*') as $mensajes)@foreach($mensajes as $mensaje)<div class="invalid-feedback d-block">{{ $mensaje }}</div>@endforeach @endforeach
                @endif
                <div class="alert alert-info small mt-3">Este ajuste cambia la planificación del grupo. Para cambiar el lugar informado a las personas en la consulta por RUT, actualice también Padrón y locales.</div>
                <button class="btn btn-sm btn-primary"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Guardar establecimientos convocados</button>
            </form>
            @if($convocatoria['personalizada'])
                <form class="mt-2" method="POST" action="{{ route('votaciones.admin.rutas.convocados.update', $ruta) }}" data-disable-on-submit onsubmit="return confirm('¿Reemplazar la selección manual por los establecimientos del padrón actual de esta jornada?')">
                    @csrf @method('PUT')<input type="hidden" name="modo" value="padron">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> Restablecer desde padrón</button>
                </form>
            @endif
        @elseif(($editableConvocados ?? false) && ! ($edicionConvocadosDisponible ?? false))
            <p class="small text-muted mb-0">Los ajustes manuales estarán disponibles al completar la actualización del módulo.</p>
        @endif
    </div>
</details>
