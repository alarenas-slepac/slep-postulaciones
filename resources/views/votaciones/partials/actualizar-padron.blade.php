@php
    $camposActualizacion = old('modo') === 'actualizacion' ? old('campos', []) : array_keys(\App\Services\Votaciones\ActualizarVotantesExcel::CAMPOS);
    $camposActualizacion = is_array($camposActualizacion) ? $camposActualizacion : [];
@endphp
<details class="mt-4 border-top pt-4" @if($errors->any() || session('resumen_actualizacion_padron')) open @endif>
    <summary class="fw-bold">Actualizar o agregar algunos votantes mediante Excel</summary>
    <div class="mt-3">
        <p class="text-secondary">Elija los campos, descargue la plantilla y conserve sólo las filas que desea tratar. Los demás votantes y los locales se conservan.</p>
        @if(($resultado = session('resumen_actualizacion_padron')) && $resultado['jornada_id'] === $jornada->id)
            <div class="alert alert-success" role="status">
                <strong>Resultado de la actualización:</strong> {{ $resultado['actualizados'] }} actualizados · {{ $resultado['agregados'] }} agregados · {{ $resultado['sin_cambios'] }} sin cambios · {{ $resultado['omitidos'] }} omitidos.
                @if($resultado['actualizados'] + $resultado['agregados'] > 0)<span class="d-block mt-2">La consulta quedó sin publicar. Revise la información y vuelva a publicarla.</span>@endif
            </div>
        @endif
        <form method="POST" enctype="multipart/form-data" action="{{ route('votaciones.admin.padrones.actualizar', $jornada) }}" data-actualizar-padron data-return-url="{{ route('votaciones.admin.padrones.index', ['jornada' => $jornada->slug]) }}">
            @csrf
            <input type="hidden" name="modo" value="actualizacion">
            <fieldset class="mb-3">
                <legend class="fs-6 fw-bold">1. Campos que desea actualizar</legend>
                @foreach(\App\Services\Votaciones\ActualizarVotantesExcel::CAMPOS as $campo => $meta)
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="campos[]" value="{{ $campo }}" id="actualizar-padron-{{ $campo }}" @checked(in_array($campo, $camposActualizacion, true))><label class="form-check-label" for="actualizar-padron-{{ $campo }}">{{ $meta['label'] }}</label></div>
                @endforeach
                <p class="form-text mb-0">Un grupo vacío quita el grupo de ese registro. Los otros campos seleccionados deben contener datos. Para nuevos votantes, seleccione Nombre, Tipo de contrato y Establecimiento/local.</p>
            </fieldset>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="precargar" value="1" id="precargar-padron" checked><label class="form-check-label" for="precargar-padron">Precargar la plantilla con los votantes de esta jornada</label></div>
            <button class="btn cv-secondary mb-3" type="submit" formaction="{{ route('votaciones.admin.padrones.plantilla', $jornada) }}" formnovalidate><i class="bi bi-download" aria-hidden="true"></i> Descargar plantilla de actualización</button>
            <p class="small text-secondary">Conserve el ID_REGISTRO y RUT de las filas existentes. Para agregar un RUT nuevo deje el ID vacío. El origen/local debe existir en esta jornada; su día y hora se toman de los locales cargados.</p>
            <div class="mb-3"><label class="form-label" for="archivo-actualizacion">2. Archivo con los votantes que desea tratar</label><input class="form-control" id="archivo-actualizacion" type="file" name="archivo_actualizacion" accept=".xlsx" required aria-describedby="actualizacion-ayuda"><div class="form-text" id="actualizacion-ayuda">Hasta 10 MB y 20.000 filas, sin fórmulas. La hoja LOCALES VOTACION de esta plantilla es sólo una referencia.</div></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="agregar" value="1" id="agregar-votantes" checked><label class="form-check-label" for="agregar-votantes">Agregar también RUT nuevos al padrón de esta jornada</label></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirmar_actualizacion" value="1" id="confirmar-actualizacion" required><label class="form-check-label" for="confirmar-actualizacion">Confirmo aplicar los cambios. Si se modifica o agrega un votante, la consulta quedará sin publicar hasta que la revise.</label></div>
            <div class="alert alert-danger" data-actualizacion-error role="alert" hidden></div>
            <button class="btn cv-primary" type="submit" data-aplicar-padron><i class="bi bi-upload" aria-hidden="true"></i> Aplicar cambios y descargar reporte</button>
            <p class="form-text mt-2 mb-0">El reporte indica actualizados, agregados, sin cambios y omitidos por fila. Si esta pantalla no se actualiza al descargarlo, <a href="{{ route('votaciones.admin.padrones.index', ['jornada' => $jornada->slug]) }}">revise el estado de la consulta</a>.</p>
        </form>
    </div>
</details>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-actualizar-padron]');
    if (!form || !window.fetch) return;
    form.addEventListener('submit', async function (event) {
        if (!event.submitter || !event.submitter.matches('[data-aplicar-padron]')) return;
        event.preventDefault();
        const button = event.submitter;
        const message = form.querySelector('[data-actualizacion-error]');
        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Procesando actualización…';
        message.hidden = true;
        try {
            const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : 'No fue posible procesar el archivo. Revise su sesión y vuelva a intentarlo.');
            }
            if (!(response.headers.get('Content-Type') || '').includes('spreadsheetml')) throw new Error('No se recibió el reporte. Verifique su sesión antes de volver a cargar el archivo.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = 'resultado_actualizacion_votantes.xlsx';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => { URL.revokeObjectURL(url); window.location.assign(form.dataset.returnUrl); }, 750);
        } catch (error) {
            message.textContent = error.message;
            message.hidden = false;
            message.scrollIntoView({block: 'nearest'});
        } finally {
            button.disabled = false;
            button.textContent = original;
        }
    });
});
</script>
@endpush
