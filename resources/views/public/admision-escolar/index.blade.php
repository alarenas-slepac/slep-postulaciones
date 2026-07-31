@php
    $title = config('admision.titulo', 'Admisión Escolar') . ' ' . config('admision.anio', 2027);
    $metaDescription = config('admision.descripcion');
    $levelOptions = [
        'sala_cuna' => 'Sala cuna',
        'pre_escolar' => 'Educación parvularia',
        'basica' => 'Educación básica',
        'media' => 'Educación media',
        'tecnico_profesional' => 'Técnico profesional',
        'adultos' => 'Educación de adultos',
        'especial' => 'Educación especial',
    ];
@endphp
@extends('layouts.admision-public')

@section('content')
<section class="ae-hero">
    <div class="ae-container ae-hero__grid">
        <div>
            <span class="ae-eyebrow">Admisión Escolar {{ config('admision.anio', 2027) }}</span>
            <h1>Explora y encuentra una comunidad educativa para cada trayectoria.</h1>
            <p class="ae-hero__lead">Conoce los establecimientos del SLEP Andalién Costa, revisa sus sellos educativos, niveles de enseñanza, equipos directivos y espacios formativos.</p>
            <div class="ae-hero__actions">
                <a class="ae-button ae-button--primary" href="#establecimientos">Explorar establecimientos <span aria-hidden="true">→</span></a>
                <a class="ae-button ae-button--outline" href="{{ config('admision.sae_url') }}" target="_blank" rel="noopener noreferrer">¿Cómo postular? <span aria-hidden="true">↗</span></a>
            </div>
            <div class="ae-hero__stats" aria-label="Resumen de la vitrina">
                <div class="ae-hero__stat"><strong>{{ number_format($summary['establecimientos'], 0, ',', '.') }}</strong><span>establecimientos publicados</span></div>
                <div class="ae-hero__stat"><strong>{{ number_format($summary['comunas'], 0, ',', '.') }}</strong><span>comunas del territorio</span></div>
                <div class="ae-hero__stat"><strong>1</strong><span>servicio local de educación</span></div>
            </div>
        </div>
        <div class="ae-hero__visual" aria-label="Imágenes de establecimientos publicados">
            @forelse ($heroImages as $heroImage)
                <div class="ae-hero__photo"><img src="{{ $heroImage->url() }}" alt="{{ $heroImage->texto_alternativo }}"></div>
            @empty
                <div class="ae-hero__photo"><div class="ae-hero__placeholder">Próximamente, imágenes de nuestras comunidades educativas.</div></div>
                <div class="ae-hero__photo"><div class="ae-hero__placeholder">Coronel</div></div>
                <div class="ae-hero__photo"><div class="ae-hero__placeholder">Lota · San Pedro de la Paz · Santa Juana</div></div>
            @endforelse
        </div>
    </div>
</section>

<section class="ae-filter-wrap" id="como-explorar" aria-label="Buscar y filtrar establecimientos">
    <div class="ae-container">
        <form class="ae-filter-card" method="GET" action="{{ route('public.admision-escolar.index') }}">
            <div class="ae-filter-grid">
                <div class="ae-field">
                    <label for="q">Buscar</label>
                    <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Nombre, RBD o sello educativo">
                </div>
                <div class="ae-field">
                    <label for="comuna">Comuna</label>
                    <select id="comuna" name="comuna">
                        <option value="">Todas</option>
                        @foreach ($comunas as $itemComuna)
                            <option value="{{ $itemComuna }}" @selected($comuna === $itemComuna)>{{ $itemComuna }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ae-field">
                    <label for="nivel">Nivel educativo</label>
                    <select id="nivel" name="nivel">
                        <option value="">Todos</option>
                        @foreach ($levelOptions as $levelKey => $levelLabel)
                            <option value="{{ $levelKey }}" @selected($nivel === $levelKey)>{{ $levelLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ae-field">
                    <label for="tipo">Tipo de establecimiento</label>
                    <select id="tipo" name="tipo">
                        <option value="">Todos</option>
                        @foreach ($tipos as $itemTipo)
                            <option value="{{ $itemTipo }}" @selected($tipo === $itemTipo)>{{ $itemTipo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ae-field">
                    <label for="sector">Sector</label>
                    <select id="sector" name="sector">
                        <option value="">Todos</option>
                        <option value="Urbano" @selected($sector === 'Urbano')>Urbano</option>
                        <option value="Rural" @selected($sector === 'Rural')>Rural</option>
                    </select>
                </div>
            </div>
            <div class="ae-filter-actions" style="margin-top:14px;max-width:360px;margin-left:auto;">
                <button class="ae-button ae-button--primary" type="submit">Aplicar filtros</button>
                <a class="ae-button ae-button--outline" href="{{ route('public.admision-escolar.index') }}">Limpiar</a>
            </div>
            <div class="ae-commune-pills" aria-label="Filtrar rápidamente por comuna">
                @php
                    $basePillParams = array_filter([
                        'q' => $q,
                        'nivel' => $nivel,
                        'tipo' => $tipo,
                        'sector' => $sector,
                        'orden' => $orden !== 'destacados' ? $orden : null,
                    ], fn ($value) => $value !== null && $value !== '');
                @endphp
                <a class="ae-pill {{ $comuna === '' ? 'is-active' : '' }}" href="{{ route('public.admision-escolar.index', $basePillParams) }}">Todas las comunas</a>
                @foreach ($comunas as $itemComuna)
                    <a class="ae-pill {{ $comuna === $itemComuna ? 'is-active' : '' }}" href="{{ route('public.admision-escolar.index', array_merge($basePillParams, ['comuna' => $itemComuna])) }}">
                        <span aria-hidden="true">⌖</span> {{ $itemComuna }}
                    </a>
                @endforeach
            </div>
        </form>
    </div>
</section>

<section class="ae-section ae-section--compact" id="establecimientos">
    <div class="ae-container">
        <div class="ae-results-bar">
            <div><strong>{{ number_format($items->total(), 0, ',', '.') }}</strong> establecimiento(s) encontrado(s)</div>
            <form method="GET" action="{{ route('public.admision-escolar.index') }}">
                @foreach (['q' => $q, 'comuna' => $comuna, 'nivel' => $nivel, 'tipo' => $tipo, 'sector' => $sector] as $key => $value)
                    @if ($value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <label class="ae-sr-only" for="orden">Ordenar resultados</label>
                <select id="orden" name="orden" onchange="this.form.submit()" style="height:42px;border:1px solid var(--ae-line);border-radius:11px;background:#fff;padding:0 12px;">
                    <option value="destacados" @selected($orden === 'destacados')>Destacados</option>
                    <option value="nombre" @selected($orden === 'nombre')>Nombre</option>
                    <option value="comuna" @selected($orden === 'comuna')>Comuna</option>
                </select>
            </form>
        </div>

        @if ($items->isEmpty())
            <div class="ae-empty">
                <strong>No encontramos establecimientos con esos criterios.</strong>
                <p>Prueba quitando uno o más filtros o realiza una búsqueda más amplia.</p>
                <a class="ae-button ae-button--outline" href="{{ route('public.admision-escolar.index') }}">Ver todos</a>
            </div>
        @else
            <div class="ae-card-grid">
                @foreach ($items as $perfil)
                    @php
                        $item = $perfil->establecimiento;
                        $cover = $perfil->portada;
                        $description = $perfil->descripcion_corta ?: $perfil->sello_educativo;
                    @endphp
                    <article class="ae-school-card">
                        <div class="ae-school-card__media">
                            <div class="ae-school-card__cover">
                                @if ($cover)
                                    <img src="{{ $cover->url() }}" alt="{{ $cover->texto_alternativo }}" loading="lazy">
                                @else
                                    <div class="ae-school-card__placeholder">{{ $item->nombre_establecimiento }}</div>
                                @endif
                            </div>
                            <span class="ae-school-card__commune">{{ $item->comuna ?: 'SLEP Andalién Costa' }}</span>
                            <div class="ae-school-card__logo">
                                @if ($perfil->logoUrl())
                                    <img src="{{ $perfil->logoUrl() }}" alt="Logo de {{ $item->nombre_establecimiento }}" loading="lazy">
                                @else
                                    <span>Logo<br>establecimiento</span>
                                @endif
                            </div>
                        </div>
                        <div class="ae-school-card__body">
                            <h3>{{ $item->nombre_establecimiento }}</h3>
                            <div class="ae-school-card__meta">RBD {{ $item->rbd }}{{ $item->dv ? '-' . $item->dv : '' }}{{ $perfil->sector ? ' · ' . $perfil->sector : '' }}</div>
                            <div class="ae-tags">
                                @foreach (array_slice($item->nivelesEducativos(), 0, 4) as $levelLabel)
                                    <span class="ae-tag">{{ $levelLabel }}</span>
                                @endforeach
                            </div>
                            <div class="ae-school-card__seal">{{ \Illuminate\Support\Str::limit($description, 190) }}</div>
                            <div class="ae-school-card__footer">
                                <small>{{ $item->tipo_estab ?: 'Establecimiento público' }}</small>
                                <a class="ae-button ae-button--outline ae-button--small" href="{{ route('public.admision-escolar.show', $perfil->slug) }}">Ver ficha <span aria-hidden="true">→</span></a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="ae-pagination">{{ $items->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
</section>

<section class="ae-section ae-section--white">
    <div class="ae-container">
        <div class="ae-info-band">
            <h2>¿Qué es un sello educativo?</h2>
            <p>Es el rasgo distintivo del Proyecto Educativo Institucional de cada establecimiento. Expresa sus énfasis formativos, su visión de estudiante y su vínculo con la comunidad y el territorio.</p>
        </div>
    </div>
</section>
@endsection
