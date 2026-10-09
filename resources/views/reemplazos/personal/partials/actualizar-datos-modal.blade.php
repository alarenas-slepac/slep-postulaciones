@php
    $camposActualizacion = \App\Services\Padron\PadronDatosExcel::CAMPOS;
    $camposSeleccionados = (array) old('campos', ['fecha_antiguedad']);
    $periodoActualizacion = \Illuminate\Support\Facades\Schema::hasTable('reemplazos_personal')
        ? app(\App\Services\Padron\PadronDatosActualizacionService::class)->periodo() : 0;
@endphp
<div class="modal fade" id="actualizar-datos-personal" tabindex="-1" aria-labelledby="actualizar-datos-personal-titulo" aria-hidden="true" data-padron-datos-modal>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header p-4">
                <div>
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Padrón de personal · Administrador</div>
                    <h2 id="actualizar-datos-personal-titulo" class="h5 fw-bold mb-0"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>Actualizar datos por Excel</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="POST" action="{{ route('reemplazos.personal.datos.actualizar') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="actualizar_datos" value="1">
                <input type="hidden" name="periodo" value="{{ $periodoActualizacion }}">
                <div class="modal-body p-4">
                    <div class="alert alert-info rounded-4" role="note">
                        <strong>Último mes cargado: {{ $periodoActualizacion ? sprintf('%02d/%d', $periodoActualizacion % 100, intdiv($periodoActualizacion, 100)) : 'Sin padrón cargado' }}.</strong>
                        Sólo se actualizarán los registros de ese mes. Si un RUT tiene varios contratos, se actualizarán todas sus líneas del mes; los otros períodos se conservan.
                    </div>
                    @if (old('actualizar_datos') && $errors->any())
                        <div class="alert alert-danger rounded-4" role="alert"><strong>No se aplicaron cambios.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif
                    <fieldset class="border rounded-4 p-3 mb-4">
                        <legend class="float-none w-auto px-2 h6 fw-bold">1. Seleccione los campos a actualizar <span class="text-danger" aria-hidden="true">*</span></legend>
                        <div class="row g-3">
                            @foreach ($camposActualizacion as $campo => $info)
                                <div class="col-sm-6"><div class="form-check">
                                    <input id="actualizar-{{ $campo }}" class="form-check-input" type="checkbox" name="campos[]" value="{{ $campo }}" @checked(in_array($campo, $camposSeleccionados, true))>
                                    <label class="form-check-label" for="actualizar-{{ $campo }}">{{ $info['titulo'] }} <span class="d-block small text-muted">Columna: {{ $info['columna'] }}</span></label>
                                </div></div>
                            @endforeach
                        </div>
                        <div class="form-text mt-3">Seleccione al menos un campo para habilitar la plantilla y la actualización.</div>
                    </fieldset>
                    <section class="mb-4" aria-labelledby="actualizar-plantilla-titulo">
                        <h3 id="actualizar-plantilla-titulo" class="h6 fw-bold">2. Descargue y complete la plantilla</h3>
                        <p class="small text-muted mb-2">La plantilla incluirá RUT y sólo los campos seleccionados. Si un RUT se repite, se aplicará la fecha más antigua de cada campo de fecha seleccionado. Tramo y Bienios deben coincidir entre sus filas. Las celdas vacías conservan los valores actuales; Bienios admite cero.</p>
                        <a class="btn btn-outline-primary rounded-pill" href="{{ route('reemplazos.personal.datos.plantilla', ['campos' => $camposSeleccionados]) }}" data-template-url="{{ route('reemplazos.personal.datos.plantilla') }}" data-padron-template>
                            <i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Descargar plantilla seleccionada
                        </a>
                        <div class="form-text mt-2">Para antigüedad también se acepta el encabezado <strong>fechaing</strong>. Fechas: YYYY-MM-DD, DD/MM/YYYY o fecha Excel. No use fórmulas.</div>
                    </section>
                    <div>
                        <label class="form-label fw-semibold" for="excel-actualizacion">3. Cargue el Excel completado <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="excel-actualizacion" type="file" name="excel_actualizacion" class="form-control @error('excel_actualizacion') is-invalid @enderror" accept=".xlsx,.xls" required aria-describedby="excel-actualizacion-ayuda">
                        @error('excel_actualizacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div id="excel-actualizacion-ayuda" class="form-text">Hasta 10 MB y 10.000 filas. Se procesa la primera hoja. Los RUT no encontrados se omiten. Al terminar podrá descargar un informe con RUT únicos modificados, sin cambios y no encontrados, y un resumen por RBD. Las fechas iguales a las registradas se informan sin modificarlas. Los datos inválidos de personas encontradas impiden aplicar el archivo.</div>
                    </div>
                </div>
                <div class="modal-footer px-4 pb-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill" @disabled(!$periodoActualizacion) data-padron-actualizar><i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Actualizar datos</button>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('actualizar-datos-personal');
    if (!modal) return;
    const form = modal.querySelector('form');
    const campos = Array.from(form.querySelectorAll('input[name="campos[]"]'));
    const plantilla = form.querySelector('[data-padron-template]');
    const actualizar = form.querySelector('[data-padron-actualizar]');
    const sinPadron = actualizar.disabled;
    const refrescar = () => {
        const seleccion = campos.some(campo => campo.checked);
        const url = new URL(plantilla.dataset.templateUrl, window.location.origin);
        campos.filter(campo => campo.checked).forEach(campo => url.searchParams.append('campos[]', campo.value));
        plantilla.href = url.toString();
        plantilla.classList.toggle('disabled', !seleccion);
        plantilla.setAttribute('aria-disabled', String(!seleccion));
        plantilla.tabIndex = seleccion ? 0 : -1;
        actualizar.disabled = sinPadron || !seleccion;
    };
    campos.forEach(campo => campo.addEventListener('change', refrescar));
    refrescar();
    form.addEventListener('submit', () => {
        actualizar.disabled = true;
        actualizar.textContent = 'Actualizando datos…';
    });
    @if (old('actualizar_datos') && $errors->any())
        if (window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
});
</script>
@endpush
