<section class="dotacion-filter-panel mb-3" aria-labelledby="{{ $catalogoId }}-titulo" data-catalog-filters hidden>
    <h3 id="{{ $catalogoId }}-titulo" class="h6 fw-bold mb-3">{{ $catalogoTitulo }}</h3>
    <div class="row g-3 align-items-end">
        <div class="col-lg-6">
            <label for="{{ $catalogoId }}-q" class="form-label fw-semibold">{{ $catalogoBusqueda }}</label>
            <input id="{{ $catalogoId }}-q" type="search" class="form-control" data-catalog-filter="q">
        </div>
        <div class="col-lg-4">
            <label for="{{ $catalogoId }}-estado" class="form-label fw-semibold">Mostrar</label>
            <select id="{{ $catalogoId }}-estado" class="form-select" data-catalog-filter="state">
                <option value="">Todos los registros</option>
                @foreach ($catalogoEstados as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-2"><button class="btn btn-outline-secondary rounded-pill" type="button" data-catalog-reset>Limpiar</button></div>
    </div>
    <p class="small text-muted mt-3 mb-1">Los filtros sólo cambian los registros visibles. Los totales y las descargas conservan su alcance.</p>
    <div class="small fw-semibold" role="status" aria-live="polite" data-catalog-count></div>
</section>
<div class="alert alert-info rounded-4" data-catalog-empty role="status" hidden>No hay registros que coincidan. Cambie los filtros o limpie la búsqueda.</div>
