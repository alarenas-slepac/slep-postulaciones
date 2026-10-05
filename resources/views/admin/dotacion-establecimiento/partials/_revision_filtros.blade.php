<section class="dotacion-filter-panel mb-4" aria-labelledby="{{ $revisionId }}-titulo" data-revision-filters hidden>
    <h3 id="{{ $revisionId }}-titulo" class="h6 fw-bold mb-3"><i class="bi bi-funnel" aria-hidden="true"></i> {{ $revisionTitulo }}</h3>
    <div class="row g-3 align-items-end">
        <div class="col-lg-5">
            <label class="form-label fw-semibold" for="{{ $revisionId }}-buscar">Nombre, RUT, título o función</label>
            <input id="{{ $revisionId }}-buscar" type="search" class="form-control" placeholder="Buscar docente…" data-revision-filter="q">
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label fw-semibold" for="{{ $revisionId }}-bloque">Bloque contractual</label>
            <select id="{{ $revisionId }}-bloque" class="form-select" data-revision-filter="block">
                <option value="">Todos los bloques</option>
                <option value="plan_estudio">Plan general y funciones</option>
                <option value="parvularia">Educación Parvularia</option>
                <option value="pie">PIE especializado</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label fw-semibold" for="{{ $revisionId }}-estado">Mostrar</label>
            <select id="{{ $revisionId }}-estado" class="form-select" data-revision-filter="state">
                <option value="">Todos los estados</option>
                <option value="saldo">Con saldo sin asignar</option>
                <option value="sobrecarga">Con sobreasignación</option>
                @if ($revisionId === 'revision-docentes')
                    <option value="reserva">Con horas reservadas</option>
                    <option value="cuadra">Contrato cuadrado</option>
                @else
                    <option value="justificacion">Justificación pendiente o desactualizada</option>
                    <option value="ajuste">Funciones revisables</option>
                @endif
            </select>
        </div>
        <div class="col-lg-1"><button type="button" class="btn btn-outline-secondary rounded-pill" data-revision-reset>Limpiar</button></div>
    </div>
    <p class="small text-muted mt-3 mb-1">Los filtros afectan la nómina; los resúmenes y totales conservan los valores del establecimiento completo.</p>
    <div class="small fw-semibold" role="status" aria-live="polite" data-revision-count></div>
</section>
<div class="alert alert-info rounded-4" role="status" data-revision-empty hidden>No hay docentes que coincidan con los filtros. Pruebe otro bloque o limpie la búsqueda.</div>
