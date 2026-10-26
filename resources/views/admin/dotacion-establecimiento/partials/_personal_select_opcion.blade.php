@php
    $saldoFasePersonal = $fasePersonal === null ? $opcionPersonal['saldo'] : \App\Support\DotacionPlanTitularPrimero::saldoAsignable([
        'prioridad_2027' => $opcionPersonal['prioridad'],
        'horas_disponibles' => $opcionPersonal['saldo'],
        'horas_titulares_disponibles' => $opcionPersonal['titular_disponible'],
        'horas_contrata_disponibles' => $opcionPersonal['contrata_disponible'],
    ], $fasePersonal);
@endphp
<option value="{{ $opcionPersonal['rut'] }}" @selected($opcionSeleccionada) data-estamento="{{ $opcionPersonal['estamento'] }}" data-fase-plan="{{ $fasePersonal }}" data-fase-disponible="{{ $fmt($saldoFasePersonal) }}" data-nombre="{{ $opcionPersonal['nombre'] }}" data-rut="{{ $opcionPersonal['rut'] }}" data-titulo="{{ $opcionPersonal['titulo'] }}" data-funcion="{{ $opcionPersonal['funcion'] }}" data-prioridad="{{ $opcionPersonal['prioridad'] }}" data-prioridad-label="{{ $opcionPersonal['prioridad_label'] }}" data-antiguedad="{{ $opcionPersonal['antiguedad'] }}" data-titular-disponible="{{ $fmt($opcionPersonal['titular_disponible']) }}" data-contrata-disponible="{{ $fmt($opcionPersonal['contrata_disponible']) }}">{{ $fasePersonal === null ? $opcionPersonal['label'] : $opcionPersonal['nombre'].' · '.$opcionPersonal['rut'].' · Título: '.$opcionPersonal['titulo'].' · '.$opcionPersonal['prioridad_label'].' · Disponible '.($fasePersonal === 'contrata' ? 'a contrata: ' : 'para asignar: ').$fmt($saldoFasePersonal).' h'.(in_array($fasePersonal, ['titular', 'preferente'], true) ? ' · '.$fmt($opcionPersonal['titular_disponible']).' titular + '.$fmt($opcionPersonal['contrata_disponible']).' contrata' : '') }}</option>
