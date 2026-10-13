@php
    $catalogoIdPersonal = $registrarCatalogoPersonal($opcionesPersonal, $grupoPersonal, $fasePersonal);
    $opcionAnteriorPersonal = collect($opcionesPersonal)->firstWhere('rut', (string) $seleccionPersonal);
@endphp
<optgroup label="{{ $grupoPersonal }}" data-personal-catalogo="{{ $catalogoIdPersonal }}">
    @if ($opcionAnteriorPersonal)
        @include('admin.dotacion-establecimiento.partials._personal_select_opcion', ['opcionPersonal' => $opcionAnteriorPersonal, 'opcionSeleccionada' => true])
    @endif
</optgroup>
