<div class="vr-ballots">
    @foreach($resumen as $clave => $papeleta)
        <section class="cv-panel vr-ballot" aria-labelledby="{{ $prefijo }}-{{ $clave }}">
            <div class="vr-ballot-heading"><span class="cv-icon" aria-hidden="true"><i class="bi bi-ui-checks"></i></span><span class="cv-eyebrow mb-0">Papeleta {{ $loop->iteration }}</span></div>
            <h3 id="{{ $prefijo }}-{{ $clave }}">{{ $papeleta['titulo'] }}</h3><p class="text-secondary vr-question">{{ $papeleta['pregunta'] }}</p>
            @foreach($papeleta['opciones'] as $opcion => $label)
                @php($cantidad = $papeleta['conteos'][$opcion])
                <div class="vr-option"><div><span id="{{ $prefijo }}-{{ $clave }}-{{ $opcion }}">{{ $label }}</span><strong>{{ number_format($cantidad, 0, ',', '.') }} <small>{{ number_format($papeleta['validos'] ? $cantidad / $papeleta['validos'] * 100 : 0, 1, ',', '.') }} %</small></strong></div><progress class="vr-bar" value="{{ $cantidad }}" max="{{ max(1, $papeleta['validos']) }}" aria-labelledby="{{ $prefijo }}-{{ $clave }}-{{ $opcion }}">{{ $cantidad }}</progress></div>
            @endforeach
            <dl class="vr-totals mt-auto mb-0"><div><dt>Votos válidos</dt><dd>{{ number_format($papeleta['validos'], 0, ',', '.') }}</dd></div><div><dt>Nulos</dt><dd>{{ number_format($papeleta['conteos']['nulos'], 0, ',', '.') }}</dd></div><div><dt>Blancos</dt><dd>{{ number_format($papeleta['conteos']['blancos'], 0, ',', '.') }}</dd></div><div><dt>Total de papeletas</dt><dd>{{ number_format($papeleta['emitidos'], 0, ',', '.') }}</dd></div></dl>
        </section>
    @endforeach
</div>
