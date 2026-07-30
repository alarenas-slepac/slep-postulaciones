@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Topes normativos de endeudamiento</h1>
            <p class="text-muted mb-0">Define la regla normativa que usará el cálculo de topes para cada descuento homologado y si debe considerarse en detalle/resumen.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endeudamiento.topes.index') }}" class="btn btn-outline-secondary">Ver cálculo de topes</a>
            <a href="{{ route('endeudamiento.registros.index') }}" class="btn btn-outline-secondary">Registros</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info shadow-sm">
        <div class="fw-semibold mb-1">Cómo se usa</div>
        <div class="small mb-0">
            Cada fila corresponde a una columna homologada del MAE. Si activas una regla normativa, el módulo de <strong>Cálculo de topes</strong>
            usará esta definición por sobre la configuración automática basada en subgrupos. Desde esta misma vista también puedes corregir si la columna
            debe guardarse en <strong>detalle</strong> y/o incorporarse en <strong>resumen</strong>, evitando inconsistencias como descuentos obligatorios excluidos del cálculo.
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Columna, canonico u observaciones">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Grupo</label>
                    <select name="grupo" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($grupos as $item)
                            <option value="{{ $item }}" @selected($grupo === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subgrupo</label>
                    <select name="subgrupo" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($subgrupos as $item)
                            <option value="{{ $item }}" @selected($subgrupo === $item)>{{ $item }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bucket normativo</label>
                    <select name="bucket" class="form-select">
                        <option value="">Todos</option>
                        <option value="__sin_regla__" @selected($bucket === '__sin_regla__')>Sin regla definida</option>
                        @foreach ($bucketOptions as $value => $label)
                            <option value="{{ $value }}" @selected($bucket === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('endeudamiento.normativa.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    <button class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 220px;">Descuento / columna</th>
                        <th>Grupo</th>
                        <th>Subgrupo</th>
                        <th style="min-width: 420px;">Regla normativa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $row)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row->columna_origen }}</div>
                                @if ($row->campo_canonico)
                                    <div class="small text-muted">Canónico: {{ $row->campo_canonico }}</div>
                                @endif
                                <div class="small text-muted">Detalle: {{ $row->guardar_en_detalle ? 'sí' : 'no' }} · Resumen: {{ $row->guardar_en_resumen ? 'sí' : 'no' }}</div>
                                @if ($row->normativa_activa && $row->normativa_bucket === 'obligatorio' && (!$row->guardar_en_detalle || !$row->guardar_en_resumen))
                                    <div class="small text-danger">Advertencia: la regla normativa está en Obligatorio, pero la columna sigue excluida de detalle y/o resumen.</div>
                                @endif
                            </td>
                            <td>{{ $row->grupo ?: '—' }}</td>
                            <td>{{ $row->subgrupo ?: '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('endeudamiento.normativa.update', $row) }}" class="row g-2">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="q" value="{{ $q }}">
                                    <input type="hidden" name="grupo" value="{{ $grupo }}">
                                    <input type="hidden" name="subgrupo" value="{{ $subgrupo }}">
                                    <input type="hidden" name="bucket" value="{{ $bucket }}">
                                    <input type="hidden" name="page" value="{{ $items->currentPage() }}">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Bucket</label>
                                        <select name="normativa_bucket" class="form-select form-select-sm">
                                            <option value="">Usar regla automática</option>
                                            @foreach ($bucketOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($row->normativa_bucket === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Etiqueta</label>
                                        <input type="text" name="normativa_label" value="{{ $row->normativa_label }}" class="form-control form-control-sm" placeholder="Ej. Facultativo 15%">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Prioridad</label>
                                        <input type="number" min="0" max="9999" name="normativa_prioridad" value="{{ $row->normativa_prioridad }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="normativa_activa" value="1" id="normativa_activa_{{ $row->id }}" @checked($row->normativa_activa)>
                                            <label class="form-check-label small" for="normativa_activa_{{ $row->id }}">Activa</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="form-check me-3">
                                            <input class="form-check-input" type="checkbox" name="guardar_en_detalle" value="1" id="guardar_en_detalle_{{ $row->id }}" @checked($row->guardar_en_detalle)>
                                            <label class="form-check-label small" for="guardar_en_detalle_{{ $row->id }}">Incluir en detalle</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="guardar_en_resumen" value="1" id="guardar_en_resumen_{{ $row->id }}" @checked($row->guardar_en_resumen)>
                                            <label class="form-check-label small" for="guardar_en_resumen_{{ $row->id }}">Incluir en resumen</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small mb-1">Regla / observación operativa</label>
                                        <textarea name="normativa_regla" rows="2" class="form-control form-control-sm" placeholder="Motivo o criterio de cálculo">{{ $row->normativa_regla }}</textarea>
                                    </div>
                                    <div class="col-12 d-flex justify-content-between align-items-center">
                                        <div class="small text-muted">
                                            @if ($row->normativa_activa && $row->normativa_bucket)
                                                Vigente: <strong>{{ $bucketOptions[$row->normativa_bucket] ?? $row->normativa_bucket }}</strong>
                                            @else
                                                Sin regla persistida; usa clasificación automática del módulo.
                                            @endif
                                        </div>
                                        <button class="btn btn-sm btn-primary">Guardar regla</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No hay descuentos homologados para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="card-body border-top">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
