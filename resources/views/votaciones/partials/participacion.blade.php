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
            @if($padronDefinitivo ?? null)
                <p>La información corresponde al padrón definitivo de personas habilitadas para votar en esta jornada{{ $padronDefinitivo['revision'] ? ', pendiente de publicación' : ', publicado el '.$padronDefinitivo['fecha'] }}, con un total de <strong>{{ number_format($padronDefinitivo['personas'], 0, ',', '.') }} personas</strong>. Los porcentajes de participación diaria y acumulada se calculan respecto de este padrón definitivo.</p>
                @if($padronDefinitivo['ver_certificado'])
                    <p>El certificado de dotación asociado a este padrón se encuentra disponible para su revisión y descarga.</p>
                    <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn cv-secondary" href="{{ $padronDefinitivo['ver_certificado'] }}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Ver certificado</a><a class="btn cv-secondary" href="{{ $padronDefinitivo['descargar_certificado'] }}"><i class="bi bi-download" aria-hidden="true"></i> Descargar certificado</a></div>
                @endif
            @else
                <p>El indicador de participación tiene carácter referencial y se calcula sobre el padrón administrativo cargado para esta jornada. No constituye una certificación del padrón habilitado ni del cumplimiento del quórum legal.</p>
            @endif
            <p>Para la afiliación o desafiliación de CCAF, la entidad empleadora deberá verificar la nómina de trabajadores habilitados. Para determinar el quórum, se excluirá a quienes, en las fechas de votación correspondientes, se encuentren con <strong>licencia médica, feriado, permiso administrativo o cometido funcionario fuera de la región donde se efectúa la votación</strong>, conforme a las instrucciones de la SUSESO y con los antecedentes que acrediten cada situación. <a class="text-reset fw-semibold text-decoration-underline" href="https://www.suseso.gob.cl/620/w3-propertyvalue-605095.html" target="_blank" rel="noopener noreferrer">Compendio SUSESO</a>.</p>
            <p>También deberán revisarse las ausencias por <strong>permiso postnatal parental, permiso sin goce de remuneraciones o días compensatorios</strong>, cuando hayan impedido concurrir a votar, conforme al criterio establecido por la SUSESO. <a class="text-reset fw-semibold text-decoration-underline" href="https://www.suseso.gob.cl/612/w3-article-39031.html" target="_blank" rel="noopener noreferrer">Dictamen 45.774-2015</a>.</p>
            <p>La consulta sobre mutualidades tiene carácter consultivo y no vinculante para la jefatura. Sus participantes y procedimiento se determinarán según la convocatoria correspondiente y la normativa aplicable, considerando la consulta previa a las asociaciones de funcionarios prevista en la Ley N.º 19.345. Las exclusiones de CCAF no se trasladan automáticamente a esta consulta. <a class="text-reset fw-semibold text-decoration-underline" href="https://www.suseso.gob.cl/613/w3-propertyvalue-135966.html" target="_blank" rel="noopener noreferrer">Normativa SUSESO para entidades públicas</a>.</p>
        </div>
    </aside>
    @if($participacion['padron'] === 0)
        <p class="cv-inline-note mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>No hay padrón cargado para calcular el porcentaje de participación.</p>
    @elseif($participacion['porcentaje'] > 100)
        <p class="cv-inline-note mb-0"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>El total de votantes supera el padrón vigente. Revise los escrutinios y la carga del padrón. La barra llega al 100 % y la cifra conserva el porcentaje calculado.</p>
    @endif
</section>
