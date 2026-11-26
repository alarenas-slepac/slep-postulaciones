<div class="firma-section{{ $firmaOt['personalizada'] ? ' firma-section--personalizada' : '' }}">
    @if ($firmaOt['combinada'])
        <div class="firma-combo">
            <img class="firma-img" src="{{ $firmaOt['combinada'] }}" alt="Firma y timbre">
        </div>
    @elseif ($firmaOt['firma'] || $firmaOt['timbre'])
        <div class="firma-layer">
            @if ($firmaOt['timbrePersonalizado'])
                <div class="timbre-cargado">
                    <img src="{{ $firmaOt['timbre'] }}" alt="Timbre">
                </div>
            @elseif ($firmaOt['timbre'])
                <img class="timbre-img" src="{{ $firmaOt['timbre'] }}" alt="Timbre">
            @endif

            @if ($firmaOt['firma'])
                <img class="firma-img" src="{{ $firmaOt['firma'] }}" alt="Firma">
            @endif
        </div>
    @endif

    <div class="firma-text">
        <strong>{{ mb_strtoupper($firmaOt['nombre']) }}</strong><br>
        <strong>{{ mb_strtoupper($firmaOt['cargo']) }}</strong><br>
        “POR ORDEN DEL DIRECTOR EJECUTIVO”<br>
        SERVICIO LOCAL DE EDUCACIÓN PUBLICA ANDALIEN COSTA
    </div>
</div>
