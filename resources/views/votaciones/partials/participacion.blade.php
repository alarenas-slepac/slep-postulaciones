@php($participacionId = $prefijoParticipacion ?? 'participacion')
<section class="cv-panel vr-participation mb-4" aria-labelledby="{{ $participacionId }}-titulo">
    <div class="vr-participation__intro">
        <span class="cv-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
        <div><span class="cv-eyebrow">Avance de participación</span><h2 id="{{ $participacionId }}-titulo">{{ $tituloParticipacion }}</h2></div>
    </div>
    @include('partials.progreso-animado', [
        'id' => $participacionId.'-padron', 'etiqueta' => 'Votantes registrados',
        'sufijoValor' => $participacion['padron'] > 0 ? ' de '.number_format($participacion['padron'], 0, ',', '.') : '',
        'valor' => $participacion['votantes'], 'maximo' => $participacion['padron'],
    ])
    <aside class="vr-participation__notice" role="note" aria-labelledby="{{ $participacionId }}-aviso-titulo">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        <div>
            <h3 id="{{ $participacionId }}-aviso-titulo">Información sobre el padrón</h3>
            <p>El avance mostrado es referencial y utiliza el padrón cargado para esta jornada.</p>
            <p>Para la votación de CCAF, el padrón habilitado y el quórum se verificarán conforme a las instrucciones de la SUSESO, considerando las exclusiones debidamente acreditadas para las fechas de votación.</p>
            <p>La consulta sobre mutualidades tiene carácter consultivo y sus participantes se determinan según la convocatoria correspondiente.</p>
        </div>
    </aside>
    @if($participacion['padron'] === 0)
        <p class="cv-inline-note mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>No hay padrón cargado para calcular el porcentaje de participación.</p>
    @elseif($participacion['porcentaje'] > 100)
        <p class="cv-inline-note mb-0"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>El total de votantes supera el padrón vigente. Revise los escrutinios y la carga del padrón. La barra llega al 100 % y la cifra conserva el porcentaje calculado.</p>
    @endif
</section>
