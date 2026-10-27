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
        'detalle' => 'Se utiliza el mayor total de las papeletas del período mostrado, incluidos nulos y blancos. Cada persona del padrón se cuenta una vez por RUT. El padrón corresponde a la carga vigente de esta jornada.',
    ])
    @if($participacion['padron'] === 0)
        <p class="cv-inline-note mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>No hay padrón cargado para calcular el porcentaje de participación.</p>
    @elseif($participacion['porcentaje'] > 100)
        <p class="cv-inline-note mb-0"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>El total de votantes supera el padrón vigente. Revise los escrutinios y la carga del padrón. La barra llega al 100 % y la cifra conserva el porcentaje calculado.</p>
    @endif
</section>
