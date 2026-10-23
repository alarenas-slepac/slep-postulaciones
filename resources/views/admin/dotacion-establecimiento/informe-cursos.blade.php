@extends('layouts.app')

@push('styles')
    @vite('resources/css/dotacion-establecimiento.css')
@endpush

@section('content')
@php
    $nivelesFormulario = old('seleccion') ? old('niveles', []) : $seleccionados;
    $nivelesFormulario = is_array($nivelesFormulario)
        ? collect($nivelesFormulario)->filter(fn ($id) => is_scalar($id))->map(fn ($id) => (string) $id)->all() : [];
@endphp
<div class="dotacion-workspace">
    <div class="card shadow-sm mb-4">
        <div class="card-body p-4 d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="text-muted small mb-1"><i class="bi bi-building" aria-hidden="true"></i> Dotación establecimiento · {{ $anio }}</div>
                <h1 class="h2 fw-bold mb-2">Informe de cursos por nivel</h1>
                <p class="text-muted mb-0">Una fila por establecimiento, con los niveles seleccionados y los grupos combinados contados una sola vez.</p>
            </div>
            <div><a class="btn btn-outline-secondary rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', compact('anio', 'q', 'comuna')) }}">Volver a establecimientos</a></div>
        </div>
    </div>
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    <form method="GET" action="{{ route('admin.dotacion-establecimiento.index') }}" class="card shadow-sm mb-4" id="informe-cursos-filtros">
        <input type="hidden" name="informe_cursos" value="1">
        <input type="hidden" name="seleccion" value="1">
        <div class="card-header bg-white"><h2 class="h5 fw-bold mb-1"><i class="bi bi-funnel" aria-hidden="true"></i> Seleccionar niveles y establecimientos</h2><p class="small text-muted mb-0">Los cambios se aplican al presionar «Ver informe» o «Descargar Excel».</p></div>
        <div class="card-body p-4">
            <div class="row g-3 mb-4">
                <div class="col-md-2"><label class="form-label" for="cursos-anio">Año</label><input class="form-control" type="number" id="cursos-anio" name="anio" value="{{ old('anio', $anio) }}" min="2020" max="2100" required></div>
                <div class="col-md-6"><label class="form-label" for="cursos-q">Buscar establecimiento</label><input class="form-control" id="cursos-q" name="q" value="{{ old('q', $q) }}" maxlength="150" placeholder="RBD, nombre o comuna"></div>
                <div class="col-md-4"><label class="form-label" for="cursos-comuna">Comuna</label><select class="form-select" id="cursos-comuna" name="comuna"><option value="">Todas</option>@foreach($comunas as $opcion)<option @selected(old('comuna', $comuna) === $opcion)>{{ $opcion }}</option>@endforeach</select></div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3"><h3 class="h6 fw-bold mb-0 me-auto">Niveles a incluir</h3><button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-niveles-marcar="todos">Seleccionar todos</button><button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-niveles-marcar="ninguno">Desmarcar todos</button></div>
            <div class="row g-3">
                @forelse($catalogo->groupBy(fn ($curso) => trim((string) $curso->nivel_educativo) ?: 'Sin clasificación') as $ensenanza => $niveles)
                    <div class="col-lg-6">
                        <fieldset class="border rounded-4 p-3 h-100">
                            <legend class="float-none w-auto fs-6 fw-bold px-2">{{ $ensenanza }}</legend>
                            <div class="row g-2">
                                @foreach($niveles as $nivel)
                                    <div class="col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="niveles[]" id="cursos-nivel-{{ $nivel->id }}" value="{{ $nivel->id }}" @checked(in_array((string) $nivel->id, $nivelesFormulario, true))><label class="form-check-label" for="cursos-nivel-{{ $nivel->id }}">{{ $nivel->nombre }}</label></div></div>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>
                @empty
                    <div class="col-12"><p class="text-muted mb-0">No hay niveles disponibles en el catálogo para el año consultado.</p></div>
                @endforelse
            </div>
            <div class="alert alert-info mt-4 mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i> Se cuentan cursos activos con matrícula. Cada grupo combinado activo cuenta como <strong>1 curso</strong>, en una columna propia. Sus integrantes no se suman en las columnas individuales. Al seleccionar algún nivel integrante, se incluye el grupo completo una sola vez.</div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-table" aria-hidden="true"></i> Ver informe</button>
                <button class="btn btn-outline-primary rounded-pill px-4" type="submit" name="export_cursos" value="1"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> Descargar Excel</button>
                <a class="btn btn-outline-secondary rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', ['informe_cursos' => 1, 'anio' => $anio]) }}">Limpiar filtros</a>
            </div>
        </div>
    </form>
    <div class="row g-3 mb-4">
        @foreach(['Establecimientos' => count($informe['filas']), 'Cursos regulares' => $informe['regulares'], 'Grupos combinados' => $informe['combinados'], 'Total cursos' => $informe['total']] as $label => $valor)
            <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-3 fw-bold">{{ number_format($valor, 0, ',', '.') }}</div></div></div></div>
        @endforeach
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-white"><h2 class="h5 fw-bold mb-1">Cursos por establecimiento</h2><p class="small text-muted mb-0">Todos los establecimientos de los filtros actuales, incluidos los que tienen cero cursos en los niveles seleccionados. Las columnas combinadas indican los niveles que agrupa cada curso.</p></div>
        <div class="table-responsive" role="region" aria-label="Informe de cursos por establecimiento" tabindex="0">
            <table class="table table-striped align-middle mb-0">
                <caption class="visually-hidden">Cantidad de cursos del año {{ $anio }}. Cada grupo combinado se contabiliza una sola vez.</caption>
                <thead class="table-light"><tr><th scope="col">RBD</th><th scope="col">Establecimiento</th><th scope="col">Comuna</th>@foreach($informe['columnas'] as $columna)<th scope="col" class="text-end">{{ $columna['label'] }}</th>@endforeach<th scope="col" class="text-end">Total cursos</th><th scope="col">Detalle grupos combinados</th></tr></thead>
                <tbody>
                    @forelse($informe['filas'] as $fila)
                        <tr><td class="text-nowrap">{{ $fila['rbd'] }}</td><th scope="row" class="fw-semibold">{{ $fila['nombre'] }}</th><td>{{ $fila['comuna'] ?: '—' }}</td>@foreach($informe['columnas'] as $key => $columna)<td class="text-end">{{ $fila['conteos'][$key] }}</td>@endforeach<td class="text-end fw-bold">{{ $fila['total'] }}</td><td class="small">{{ $fila['detalle_combinados'] ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="{{ count($informe['columnas']) + 5 }}" class="text-center text-muted py-4">No se encontraron establecimientos para los filtros aplicados.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light"><tr><th scope="row" colspan="3">Total de los establecimientos filtrados</th>@foreach($informe['columnas'] as $key => $columna)<td class="text-end fw-bold">{{ $informe['totales'][$key] }}</td>@endforeach<td class="text-end fw-bold">{{ $informe['total'] }}</td><td></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('informe-cursos-filtros');
    if (!form) return;
    form.querySelectorAll('[data-niveles-marcar]').forEach(function (button) {
        button.addEventListener('click', function () {
            form.querySelectorAll('input[name="niveles[]"]').forEach(function (input) {
                input.checked = button.dataset.nivelesMarcar === 'todos';
            });
        });
    });
});
</script>
@endpush
