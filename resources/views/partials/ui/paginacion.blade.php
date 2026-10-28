<footer class="slep-listing__footer">
    <p class="mb-0 slep-listing__muted">
        @if ($paginador->total())
            Mostrando <strong>{{ $paginador->firstItem() }}–{{ $paginador->lastItem() }}</strong> de <strong>{{ $paginador->total() }}</strong> resultados.
        @else
            No hay resultados para mostrar.
        @endif
    </p>
    @if ($paginador->hasPages())
        @php
            $paginaActual = $paginador->currentPage();
            $ultimaPagina = $paginador->lastPage();
            $paginas = collect([1, $ultimaPagina])->merge(range(max(1, $paginaActual - 2), min($ultimaPagina, $paginaActual + 2)))->unique()->sort()->values();
            $anterior = 0;
        @endphp
        <nav class="slep-pagination" aria-label="{{ $etiqueta ?? 'Páginas de resultados' }}">
            @if ($paginador->onFirstPage())
                <span class="slep-pagination__link is-disabled" aria-disabled="true"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Anterior</span></span>
            @else
                <a class="slep-pagination__link" href="{{ $paginador->previousPageUrl() }}" rel="prev" aria-label="Página anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
            @endif
            @foreach ($paginas as $pagina)
                @if ($anterior && $pagina > $anterior + 1)<span class="slep-pagination__gap" aria-hidden="true">…</span>@endif
                @if ($pagina === $paginaActual)
                    <span class="slep-pagination__link is-current" aria-current="page" aria-label="Página {{ $pagina }}">{{ $pagina }}</span>
                @else
                    <a class="slep-pagination__link" href="{{ $paginador->url($pagina) }}" aria-label="Ir a página {{ $pagina }}">{{ $pagina }}</a>
                @endif
                @php $anterior = $pagina; @endphp
            @endforeach
            @if ($paginador->hasMorePages())
                <a class="slep-pagination__link" href="{{ $paginador->nextPageUrl() }}" rel="next" aria-label="Página siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            @else
                <span class="slep-pagination__link is-disabled" aria-disabled="true"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Siguiente</span></span>
            @endif
        </nav>
    @endif
</footer>
