<section class="dotacion-filter-panel mb-4" aria-labelledby="asignacion-filtros-titulo" data-dotacion-filters hidden>
    <h3 id="asignacion-filtros-titulo" class="h6 fw-bold mb-3"><i class="bi bi-funnel" aria-hidden="true"></i> Encontrar horas por asignar</h3>
    <div class="row g-3 align-items-end">
        <div class="col-lg-4">
            <label class="form-label fw-semibold" for="dotacion-asignacion-buscar">Curso, asignatura, función o persona asignada</label>
            <input id="dotacion-asignacion-buscar" type="search" class="form-control" placeholder="Buscar por nombre…" data-filter="q">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label fw-semibold" for="dotacion-asignacion-seccion">Sección de asignación</label>
            <select id="dotacion-asignacion-seccion" class="form-select" data-filter="section">
                <option value="">Todas las secciones</option>
                @foreach ($groups as $key => $meta)<option value="{{ $key }}">{{ $meta['title'] }}</option>@endforeach
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label fw-semibold" for="dotacion-asignacion-curso">Curso o grupo combinado</label>
            <select id="dotacion-asignacion-curso" class="form-select" data-filter="course"><option value="">Todos los cursos y ámbitos</option></select>
        </div>
        <div class="col-lg-2 d-flex flex-column gap-2">
            <div class="form-check"><input id="dotacion-asignacion-pendientes" type="checkbox" class="form-check-input" data-filter="pending"><label class="form-check-label" for="dotacion-asignacion-pendientes">Solo pendientes</label></div>
            <button type="button" class="btn btn-outline-secondary rounded-pill" data-filter-reset>Limpiar filtros</button>
        </div>
    </div>
    <p class="small text-muted mt-3 mb-0">Los filtros cambian las filas visibles; los totales corresponden al establecimiento completo.</p>
    <div class="small fw-semibold mt-2" role="status" aria-live="polite" data-filter-count></div>
</section>
<div class="alert alert-info rounded-4" role="status" data-filter-empty hidden>No hay necesidades que coincidan con los filtros. Quite «Solo pendientes» o limpie la búsqueda.</div>
