<div class="vr-ballots">
    @foreach($resumen as $clave => $papeleta)
        <section class="cv-panel vr-ballot" aria-labelledby="{{ $prefijo }}-{{ $clave }}">
            <div class="vr-ballot-heading"><span class="cv-icon" aria-hidden="true"><i class="bi bi-ui-checks"></i></span><span class="cv-eyebrow mb-0">Papeleta {{ $loop->iteration }}</span></div>
            <h3 id="{{ $prefijo }}-{{ $clave }}">{{ $papeleta['titulo'] }}</h3><p class="text-secondary vr-question">{{ $papeleta['pregunta'] }}</p>
            @foreach($papeleta['opciones'] as $opcion => $label)
                @php($cantidad = $papeleta['conteos'][$opcion])
                @include('partials.progreso-animado', ['id' => $prefijo.'-'.$clave.'-'.$opcion, 'etiqueta' => $label, 'valor' => $cantidad, 'maximo' => max(1, $papeleta['validos']), 'sufijoValor' => '', 'detalle' => null])
            @endforeach
            <dl class="vr-totals mt-auto mb-0" data-slep-reveal>
                @foreach(['validos' => 'Votos válidos', 'nulos' => 'Nulos', 'blancos' => 'Blancos', 'emitidos' => 'Total de papeletas'] as $totalClave => $totalLabel)
                    @php($totalCantidad = $papeleta[$totalClave] ?? $papeleta['conteos'][$totalClave])
                    <div><dt>{{ $totalLabel }}</dt><dd class="slep-counter"><span aria-hidden="true" data-slep-count="{{ $totalCantidad }}">{{ number_format($totalCantidad, 0, ',', '.') }}</span><span class="visually-hidden">{{ number_format($totalCantidad, 0, ',', '.') }}</span></dd></div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>
