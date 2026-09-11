<section aria-label="{{ $scope === 'history' ? 'Historial de cambios anteriores' : 'Novedades actuales' }}">
    <h6>{{ $scope === 'history' ? 'Historial de cambios anteriores' : 'Novedades de la versión '.$current }}</h6>
    <p class="small text-muted">Solo se muestran cambios visibles para tus roles. {{ $entries->total() }} registros.</p>
    @forelse ($entries as $entry)
        <article class="card mb-3"><div class="card-body">
            <h6>{{ $entry['title'] ?? 'Actualización' }} <span class="badge text-bg-secondary">v{{ $entry['version'] ?? '' }}</span></h6>
            @if (!empty($entry['published_at']))<div class="small text-muted">{{ $entry['published_at'] }}</div>@endif
            @if (!empty($entry['summary']))<p>{{ $entry['summary'] }}</p>@endif
            <ul class="mb-2">
                @foreach ((array) ($entry['items'] ?? []) as $item)<li>{{ $item }}</li>@endforeach
            </ul>
            @if (!empty($entry['roles']))<div class="small text-muted">Roles impactados: {{ implode(', ', (array) $entry['roles']) }}</div>@endif
        </div></article>
    @empty
        <p>No hay cambios visibles en esta sección.</p>
    @endforelse
    @if ($entries->hasPages())
        <nav class="d-flex justify-content-between align-items-center gap-2" aria-label="Páginas del registro de cambios">
            <span>Página {{ $entries->currentPage() }} de {{ $entries->lastPage() }}</span>
            <div class="d-flex gap-2">
                @if ($entries->previousPageUrl())<a data-changelog-page class="btn btn-outline-primary btn-sm" href="{{ $entries->previousPageUrl() }}">Anterior</a>@endif
                @if ($entries->nextPageUrl())<a data-changelog-page class="btn btn-outline-primary btn-sm" href="{{ $entries->nextPageUrl() }}">Siguiente</a>@endif
            </div>
        </nav>
    @endif
</section>
