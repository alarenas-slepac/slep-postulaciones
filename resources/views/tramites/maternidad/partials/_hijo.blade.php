<fieldset class="mat-hijo border rounded-4 p-3 mb-3" data-mat-hijo>
    <legend class="float-none w-auto px-2 h6">Hijo/a <span data-mat-numero-hijo>{{ is_numeric($indice) ? $indice + 1 : '' }}</span></legend>
    <div class="row g-3">
        @include('tramites.maternidad.partials._campo', ['name' => 'hijos.'.$indice.'.nombre_completo', 'label' => 'Nombre completo del hijo/a', 'col' => 'col-md-6', 'tipo' => 'text', 'maximo' => 255])
        @include('tramites.maternidad.partials._campo', ['name' => 'hijos.'.$indice.'.fecha_nacimiento', 'label' => 'Fecha de nacimiento del hijo/a', 'tipo' => 'date', 'col' => 'col-md-3'])
        @include('tramites.maternidad.partials._campo', ['name' => 'hijos.'.$indice.'.rut', 'label' => 'RUT del hijo/a', 'tipo' => 'text', 'col' => 'col-md-3', 'maximo' => 32])
    </div>
    <button class="slep-listing__button slep-listing__button--secondary mt-3" type="button" data-mat-quitar-hijo>Quitar hijo/a del formulario</button>
</fieldset>
