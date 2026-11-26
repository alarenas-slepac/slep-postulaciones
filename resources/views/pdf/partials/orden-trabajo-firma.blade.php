@php
    // Mantiene el respaldo de firma y timbre en una imagen combinada.
    $firmaSoloFile  = public_path('branding/firma.png');
    $timbreFile     = public_path('branding/timbre.png');
    $firmaComboFile = public_path('branding/firma-makarena.png');

    $firmaSoloData = is_file($firmaSoloFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($firmaSoloFile)) : null;
    $timbreData    = is_file($timbreFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($timbreFile)) : null;
    $firmaComboData = (!$firmaSoloData && is_file($firmaComboFile))
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($firmaComboFile))
        : null;
@endphp

<div class="firma-section">

    @if($firmaSoloData || $timbreData)
        <div class="firma-layer">
            @if($timbreData)
                <img class="timbre-img" src="{{ $timbreData }}" alt="Timbre">
            @endif

            @if($firmaSoloData)
                <img class="firma-img" src="{{ $firmaSoloData }}" alt="Firma">
            @endif
        </div>
    @elseif($firmaComboData)
        <div class="firma-combo">
            <img class="firma-img" src="{{ $firmaComboData }}" alt="Firma y timbre">
        </div>
    @endif

    <div class="firma-text">
        <strong>MAKARENA PAREDES AGUILERA</strong><br>
        <strong>SUBDIRECTORA DE GESTION Y DESARROLLO DE LAS PERSONAS</strong><br>
        “POR ORDEN DEL DIRECTOR EJECUTIVO”<br>
        SERVICIO LOCAL DE EDUCACIÓN PUBLICA ANDALIEN COSTA
    </div>
</div>
