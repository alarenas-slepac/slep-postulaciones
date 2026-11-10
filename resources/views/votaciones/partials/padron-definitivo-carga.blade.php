@if($definitivoDisponible)
<section class="cv-panel mt-4" aria-labelledby="padron-definitivo-titulo">
    <span class="cv-eyebrow">Revisión y publicación</span><h2 id="padron-definitivo-titulo">Padrón definitivo</h2>
    <p>Carga el Excel definitivo y, si corresponde, el certificado de dotación en PDF. Podrás comparar los porcentajes diarios y acumulados antes de publicar. Preparar esta revisión conserva la información pública vigente.</p>
    @if($definitivoPublicado)<p><span class="cv-badge">Padrón definitivo publicado</span> <strong>{{ number_format($definitivoPublicado->personas, 0, ',', '.') }} personas</strong> · {{ $definitivoPublicado->publicado_at->timezone(config('votaciones.timezone'))->format('d-m-Y H:i') }}</p>@endif
    @if($definitivoPendiente)<p><a class="btn cv-secondary" href="{{ route('votaciones.admin.padrones.definitivo.revision', [$jornada, $definitivoPendiente]) }}"><i class="bi bi-eye" aria-hidden="true"></i> Revisar carga pendiente · {{ number_format($definitivoPendiente->personas, 0, ',', '.') }} personas</a></p>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ route('votaciones.admin.padrones.definitivo.preparar', $jornada) }}">
        @csrf
        <div class="row g-3"><div class="col-lg-6"><label class="form-label" for="archivo-definitivo">Padrón definitivo Excel <span class="text-secondary">(obligatorio)</span></label><input class="form-control" id="archivo-definitivo" type="file" name="archivo_definitivo" accept=".xlsx" required aria-describedby="archivo-definitivo-ayuda"><div class="form-text" id="archivo-definitivo-ayuda">Hasta 10 MB. Debe incluir las hojas PADRON VOTACIÓN y LOCALES VOTACION.</div>@error('archivo_definitivo')<p class="text-danger mt-2" role="alert">{{ $message }}</p>@enderror</div><div class="col-lg-6"><label class="form-label" for="certificado-dotacion">Certificado de dotación <span class="text-secondary">(opcional)</span></label><input class="form-control" id="certificado-dotacion" type="file" name="certificado_dotacion" accept=".pdf" aria-describedby="certificado-dotacion-ayuda"><div class="form-text" id="certificado-dotacion-ayuda">PDF de hasta 10 MB. Se podrá revisar y descargar públicamente al publicar este padrón definitivo.</div>@error('certificado_dotacion')<p class="text-danger mt-2" role="alert">{{ $message }}</p>@enderror</div></div>
        <div class="mt-3"><button class="btn cv-primary" type="submit"><i class="bi bi-eye" aria-hidden="true"></i> Preparar revisión privada</button></div>
    </form>
</section>
@endif
