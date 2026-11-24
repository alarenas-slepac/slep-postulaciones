<dl class="row small mb-3">
    @foreach ([
        'Establecimiento' => $oferta->establecimientos_display ?: 'Por informar',
        'RBD' => $oferta->rbds_display && $oferta->rbds_display !== 'sinrbd' ? $oferta->rbds_display : 'Por informar',
        'Comuna' => $oferta->comuna,
        'Estamento' => $oferta->estamento_label,
        'Área de desempeño' => $oferta->areaDesempeno?->nombre ?: 'Por informar',
        'Calidad contractual' => $oferta->calidad_contractual_label,
        'Horas' => $oferta->cantidad_horas,
        'Remuneración bruta' => $oferta->remuneracion_bruta_formatted,
        'Inicio aproximado' => $oferta->inicio_trabajo_aproximado?->format('d-m-Y') ?: 'Por informar',
        'Inicio de postulaciones' => $oferta->fecha_inicio_postulaciones?->format('d-m-Y').' '.$oferta->hora_inicio_postulaciones,
        'Cierre de postulaciones' => $oferta->fecha_termino_postulaciones?->format('d-m-Y').' '.$oferta->hora_termino_postulaciones,
        'Correo de contacto' => $oferta->correo_contacto,
        'Postulaciones registradas' => $oferta->postulaciones_count,
    ] as $label => $value)
        <dt class="col-sm-5">{{ $label }}</dt><dd class="col-sm-7 text-break">{{ $value }}</dd>
    @endforeach
</dl>
@if ($oferta->bases_pdf_path)
    <a class="slep-listing__button slep-listing__button--secondary" href="{{ route('postulant.ofertas-laborales.bases', $oferta) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Descargar bases</a>
@endif
