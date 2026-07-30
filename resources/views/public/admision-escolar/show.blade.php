@php
    $cover = $perfil->imagenes->firstWhere('es_portada', true) ?? $perfil->imagenes->first();
    $title = $establecimiento->nombre_establecimiento . ' · Admisión Escolar';
    $metaDescription = \Illuminate\Support\Str::limit($perfil->descripcion_corta ?: $perfil->sello_educativo, 155);
    $ogImage = $cover?->url() ?: ($perfil->logoUrl() ?: null);
    $lat = $establecimiento->latitud !== null ? (float) $establecimiento->latitud : null;
    $lng = $establecimiento->longitud !== null ? (float) $establecimiento->longitud : null;
    $mapEmbed = null;
    if ($lat !== null && $lng !== null) {
        $delta = 0.005;
        $mapEmbed = 'https://www.openstreetmap.org/export/embed.html?bbox=' . urlencode(($lng - $delta) . ',' . ($lat - $delta) . ',' . ($lng + $delta) . ',' . ($lat + $delta)) . '&layer=mapnik&marker=' . urlencode($lat . ',' . $lng);
    }
@endphp
@extends('layouts.admision-public')

@section('content')
<div class="ae-detail-top">
    <div class="ae-container"><a class="ae-back-link" href="{{ route('public.admision-escolar.index') }}"><span aria-hidden="true">←</span> Volver al listado de establecimientos</a></div>
</div>

<section class="ae-detail-hero">
    <div class="ae-container">
        <article class="ae-detail-hero__card">
            <div class="ae-detail-hero__content">
                <div class="ae-detail-logo">
                    @if ($perfil->logoUrl())
                        <img src="{{ $perfil->logoUrl() }}" alt="Logo de {{ $establecimiento->nombre_establecimiento }}">
                    @else
                        <span>Logo del establecimiento</span>
                    @endif
                </div>
                <div>
                    <span class="ae-eyebrow">Establecimiento SLEP Andalién Costa</span>
                    <h1>{{ $establecimiento->nombre_establecimiento }}</h1>
                    <div class="ae-detail-meta">
                        <span>⌖ {{ $establecimiento->comuna ?: 'Comuna no informada' }}</span>
                        <span>RBD {{ $establecimiento->rbd }}{{ $establecimiento->dv ? '-' . $establecimiento->dv : '' }}</span>
                        @if ($perfil->sector)<span>{{ $perfil->sector }}</span>@endif
                    </div>
                    <div class="ae-tags">
                        @foreach ($establecimiento->nivelesEducativos() as $levelLabel)
                            <span class="ae-tag">{{ $levelLabel }}</span>
                        @endforeach
                    </div>
                    <div class="ae-detail-seal">
                        <span class="ae-detail-seal__icon" aria-hidden="true">✦</span>
                        <div><strong>Sello educativo</strong><br>{{ $perfil->sello_educativo }}</div>
                    </div>
                    <div class="ae-detail-actions">
                        @if ($perfil->sitio_web_url)<a class="ae-button ae-button--outline ae-button--small" href="{{ $perfil->sitio_web_url }}" target="_blank" rel="noopener noreferrer">Sitio web ↗</a>@endif
                        @if ($perfil->facebook_url)<a class="ae-button ae-button--outline ae-button--small" href="{{ $perfil->facebook_url }}" target="_blank" rel="noopener noreferrer">Facebook ↗</a>@endif
                        @if ($perfil->instagram_url)<a class="ae-button ae-button--outline ae-button--small" href="{{ $perfil->instagram_url }}" target="_blank" rel="noopener noreferrer">Instagram ↗</a>@endif
                        <a class="ae-button ae-button--primary ae-button--small" href="{{ config('admision.sae_url') }}" target="_blank" rel="noopener noreferrer">Ir al SAE ↗</a>
                    </div>
                </div>
            </div>
            <div class="ae-detail-cover">
                @if ($cover)
                    <img src="{{ $cover->url() }}" alt="{{ $cover->texto_alternativo }}">
                @else
                    <div class="ae-detail-cover__placeholder">Imagen institucional pendiente</div>
                @endif
            </div>
        </article>
    </div>
</section>

<section class="ae-section ae-section--compact">
    <div class="ae-container ae-detail-grid">
        <div style="display:grid;gap:24px;">
            @if ($perfil->descripcion_corta)
                <article class="ae-panel">
                    <h2>Sobre nuestro establecimiento</h2>
                    <p style="margin:0;color:var(--ae-muted);font-size:1rem;">{{ $perfil->descripcion_corta }}</p>
                </article>
            @endif

            <article class="ae-panel">
                <h2>Equipo directivo</h2>
                <div class="ae-director">
                    <div class="ae-director__photo">
                        @if ($perfil->directorFotoUrl())
                            <img src="{{ $perfil->directorFotoUrl() }}" alt="Fotografía de {{ $perfil->director_nombre }}">
                        @else
                            <span>Foto<br>director/a</span>
                        @endif
                    </div>
                    <div>
                        <small style="color:var(--ae-blue-dark);font-weight:900;text-transform:uppercase;letter-spacing:.06em;">Director/a</small>
                        <h3>{{ $perfil->director_nombre }}</h3>
                        @if ($perfil->director_resena)<p>{{ $perfil->director_resena }}</p>@endif
                    </div>
                </div>
            </article>

            <article class="ae-panel">
                <div class="ae-section-heading" style="margin-bottom:18px;">
                    <div><h2 style="font-size:1.2rem;margin:0;">Galería</h2></div>
                    <span style="color:var(--ae-muted);font-size:.82rem;">{{ $perfil->imagenes->count() }} imagen(es)</span>
                </div>
                @if ($perfil->imagenes->isNotEmpty())
                    <div class="ae-gallery">
                        @foreach ($perfil->imagenes as $imagen)
                            <button class="ae-gallery__item" type="button" data-ae-gallery-image data-src="{{ $imagen->url() }}" data-alt="{{ $imagen->texto_alternativo }}" data-caption="{{ $imagen->titulo ?: $imagen->descripcion }}">
                                <img src="{{ $imagen->url() }}" alt="{{ $imagen->texto_alternativo }}" loading="lazy">
                                @if ($imagen->titulo)<span class="ae-gallery__caption">{{ $imagen->titulo }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                @else
                    <div class="ae-empty" style="padding:35px 20px;">Galería pendiente de publicación.</div>
                @endif
            </article>
        </div>

        <aside style="display:grid;gap:24px;">
            <section class="ae-panel">
                <h2>Información rápida</h2>
                <div class="ae-facts">
                    <div class="ae-fact"><strong>Dependencia</strong><span>SLEP Andalién Costa</span></div>
                    <div class="ae-fact"><strong>Comuna</strong><span>{{ $establecimiento->comuna ?: '—' }}</span></div>
                    <div class="ae-fact"><strong>RBD</strong><span>{{ $establecimiento->rbd }}{{ $establecimiento->dv ? '-' . $establecimiento->dv : '' }}</span></div>
                    <div class="ae-fact"><strong>Tipo</strong><span>{{ $establecimiento->tipo_estab ?: '—' }}</span></div>
                    @if ($establecimiento->clasificacion)<div class="ae-fact"><strong>Clasificación</strong><span>{{ $establecimiento->clasificacion }}</span></div>@endif
                    @if ($perfil->sector)<div class="ae-fact"><strong>Sector</strong><span>{{ $perfil->sector }}</span></div>@endif
                </div>
            </section>

            <section class="ae-panel">
                <h2>Ubicación y contacto</h2>
                @if ($mapEmbed)
                    <div class="ae-map"><iframe src="{{ $mapEmbed }}" title="Mapa de ubicación de {{ $establecimiento->nombre_establecimiento }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
                @endif
                <div class="ae-contact-list">
                    @if ($perfil->direccion_publica)<div><strong>Dirección:</strong> {{ $perfil->direccion_publica }}</div>@endif
                    @if ($perfil->telefono_publico)<div><strong>Teléfono:</strong> <a href="tel:{{ preg_replace('/[^0-9+]/', '', $perfil->telefono_publico) }}">{{ $perfil->telefono_publico }}</a></div>@endif
                    @if ($perfil->email_publico)<div><strong>Correo:</strong> <a href="mailto:{{ $perfil->email_publico }}">{{ $perfil->email_publico }}</a></div>@endif
                    @if (! $perfil->direccion_publica && ! $perfil->telefono_publico && ! $perfil->email_publico)
                        <div>Los datos de contacto serán informados por el establecimiento.</div>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</section>

@if ($perfil->imagenes->isNotEmpty())
<dialog class="ae-lightbox" data-ae-lightbox>
    <div class="ae-lightbox__bar"><span data-ae-lightbox-caption></span><button type="button" data-ae-lightbox-close>Cerrar ×</button></div>
    <img class="ae-lightbox__image" data-ae-lightbox-image src="" alt="">
</dialog>
@endif
@endsection
