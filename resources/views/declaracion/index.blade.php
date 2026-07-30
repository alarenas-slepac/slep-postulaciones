@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<style>
.js-nombre-titulo-select + .select2-container,
.js-institucion-select + .select2-container,
.select2-container {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
}

.select2-container--default .select2-selection--single {
    min-height: 31px;
    border: 1px solid #ced4da;
    border-radius: .25rem;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    display: block;
    line-height: 29px;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding-right: 24px;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 29px;
}

.select2-container .select2-search__field,
.select2-container .select2-results__option {
    font-size: 12px;
}

.export-status-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #f8fafc;
    padding: 12px;
}

.export-status-table td,
.export-status-table th {
    font-size: 12px;
    vertical-align: middle;
}

.export-status-pill {
    font-size: 11px;
    font-weight: 600;
    border-radius: 999px;
    padding: .25rem .6rem;
}
</style>
@endpush

@section('content')

<style>
.declaracion-container {
    font-size: 12px;
}

.declaracion-page {
    width: calc(100vw - 1rem);
    max-width: none;
    margin-left: calc(50% - 50vw + .5rem);
    margin-right: auto;
}

@media (max-width: 991.98px) {
    .declaracion-page {
        width: 100%;
        margin-left: 0;
        max-width: 100%;
    }
}

.header-declaracion {
    text-align: center;
    margin-bottom: 16px;
}

.header-declaracion h2 {
    font-weight: 600;
    margin-bottom: 0;
}

.card-ui {
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    padding: 12px;
    background: white;
}

.tabla-scroll {
    overflow-x: auto;
    overflow-y: visible;
    position: relative;
}

.tabla-declaracion {
    width: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 10.5px;
}

.tabla-declaracion th {
    background: #0f172a;
    color: #fff;
    font-weight: 500;
    text-align: center;
    vertical-align: middle;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 6;
    padding: 6px 4px;
}

.tabla-declaracion td {
    vertical-align: middle;
    padding: 4px;
    white-space: nowrap;
    background: #fff;
}

.tabla-declaracion tbody tr:hover td {
    background: #f1f5f9;
}

.tabla-declaracion tbody tr.fila-confirmada td {
    background: #dcfce7;
}

.tabla-declaracion tbody tr.fila-confirmada:hover td {
    background: #c7f2d6;
}

.tabla-declaracion th,
.tabla-declaracion td {
    border: 1px solid #dee2e6;
}

.col-n {
    width: 38px;
    min-width: 38px;
    max-width: 38px;
    text-align: center;
}

.col-rbd {
    width: 56px;
    min-width: 56px;
    max-width: 56px;
    text-align: center;
}

.col-rut {
    width: 84px;
    min-width: 84px;
    max-width: 84px;
}

.col-horas,
.col-parv,
.col-basica,
.col-media {
    width: 52px;
    min-width: 52px;
    max-width: 52px;
    text-align: center;
}

.col-nombres {
    min-width: 115px;
    max-width: 115px;
}

.col-apellido {
    min-width: 100px;
    max-width: 100px;
}

.col-cert {
    width: 92px;
    min-width: 92px;
    max-width: 92px;
    text-align: center;
}

.col-funcion {
    min-width: 180px;
    max-width: 180px;
}

.col-tipo-titulo {
    min-width: 120px;
    max-width: 120px;
}

.col-titulo {
    width: 190px;
    min-width: 190px;
    max-width: 190px;
}

.col-inst {
    width: 200px;
    min-width: 200px;
    max-width: 200px;
}

.col-fecha {
    width: 98px;
    min-width: 98px;
    max-width: 98px;
}

.col-pais {
    width: 92px;
    min-width: 92px;
    max-width: 92px;
}

.col-acciones {
    width: 92px;
    min-width: 92px;
    max-width: 92px;
    text-align: center;
    vertical-align: top;
}

.wrap-text {
    white-space: normal !important;
    word-break: break-word;
    line-height: 1.2;
}

.sticky-col {
    position: sticky;
    background: #fff;
    z-index: 3;
    box-shadow: 1px 0 0 #dee2e6;
}

thead .sticky-col {
    background: #0f172a !important;
    color: #fff !important;
    z-index: 7;
}

tbody tr:hover .sticky-col {
    background: #f1f5f9 !important;
}

tbody tr.fila-confirmada .sticky-col {
    background: #dcfce7 !important;
}

tbody tr.fila-confirmada:hover .sticky-col {
    background: #c7f2d6 !important;
}

.sticky-1 { left: 0; }
.sticky-2 { left: 38px; }
.sticky-3 { left: 94px; }

.paginacion-declaracion {
    gap: 10px;
}

.selector-pagina {
    min-width: 220px;
}

.dropzone {
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
}

.dropzone:hover {
    background: #f8fafc;
}

.btn-excel-dark {
    background: #166534;
    border-color: #166534;
    color: #fff;
}

.btn-excel-dark:hover,
.btn-excel-dark:focus,
.btn-excel-dark:active {
    background: #14532d;
    border-color: #14532d;
    color: #fff;
}

.cert-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}

.link-ver-min {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    height: 22px;
    padding: 0 6px;
    border: 1px solid #dbe3ef;
    border-radius: 999px;
    background: #fff;
    color: #334155;
    font-size: 9px;
    text-decoration: none;
    transition: all .15s ease;
}

.link-ver-min:hover {
    background: #f8fafc;
    color: #0f172a;
    text-decoration: none;
}

.btn-cert-min,
.btn-delete-min,
.btn-rbd-toggle,
.btn-save-rbd,
.btn-confirm-min {
    min-width: 72px;
    height: 22px;
    border: 0;
    border-radius: 999px;
    font-size: 9px;
    font-weight: 600;
    padding: 0 6px;
    transition: all .15s ease;
}

.btn-cert-min {
    background: #e2e8f0;
    color: #0f172a;
}

.btn-cert-min:hover {
    background: #cbd5e1;
}

.btn-cert-min.btn-upload {
    background: #e8f7ee;
    color: #166534;
}

.btn-cert-min.btn-upload:hover {
    background: #d6f0df;
}

.btn-cert-min.btn-replace {
    background: #eef2ff;
    color: #3730a3;
}

.btn-cert-min.btn-replace:hover {
    background: #e0e7ff;
}

.btn-delete-min {
    background: #fee2e2;
    color: #991b1b;
}

.btn-delete-min:hover {
    background: #fecaca;
}

.btn-rbd-toggle {
    background: #f8fafc;
    color: #0f172a;
    border: 1px solid #cbd5e1;
}

.btn-rbd-toggle:hover {
    background: #eef2f7;
}

.rbd-panel {
    margin-top: 6px;
    padding: 6px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #f8fafc;
}

.rbd-panel .form-select-sm {
    font-size: 9px;
}

.btn-save-rbd {
    width: 100%;
    margin-top: 5px;
    background: #dbeafe;
    color: #1d4ed8;
}

.btn-save-rbd:hover {
    background: #bfdbfe;
}

.btn-confirm-min {
    min-width: 28px;
    width: 28px;
    padding: 0;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
    font-size: 12px;
    line-height: 1;
}

.btn-confirm-min:hover {
    background: #bbf7d0;
}

.btn-confirm-min.is-confirmed {
    background: #22c55e;
    color: #fff;
    border-color: #16a34a;
    cursor: default;
}

.btn-confirm-min.is-blocked,
.btn-confirm-min.is-blocked:disabled {
    background: #e2e8f0;
    color: #64748b;
    border-color: #cbd5e1;
    cursor: not-allowed;
}

.confirm-note {
    display: block;
    margin-top: 4px;
    font-size: 9px;
    line-height: 1.15;
    color: #b45309;
    white-space: normal;
}

.form-control-sm,
.form-select-sm {
    font-size: 10px;
    padding: 2px 4px;
    min-height: 24px;
}

textarea.form-control {
    font-size: 10px !important;
    min-height: 54px;
}

.tabla-declaracion td.col-titulo,
.tabla-declaracion td.col-inst {
    overflow: hidden;
}

.tabla-declaracion td.col-titulo > form,
.tabla-declaracion td.col-inst > form,
.tabla-declaracion td.col-titulo .form-control,
.tabla-declaracion td.col-inst .form-control,
.tabla-declaracion td.col-titulo .form-select,
.tabla-declaracion td.col-inst .form-select {
    width: 100%;
    max-width: 100%;
}

.tabla-declaracion td.col-inst .mt-2 {
    max-width: 100%;
}

.small-note {
    font-size: 11px;
    color: #64748b;
}

.tabla-declaracion td > form,
.tabla-declaracion td .js-institucion-form,
.tabla-declaracion td .js-funcion-form {
    width: 100%;
    margin-bottom: 0;
}

.tabla-declaracion td .select2-container {
    display: block;
    width: 100% !important;
    max-width: 100% !important;
    overflow: hidden;
}

.tabla-declaracion td .select2-selection--single {
    width: 100%;
    overflow: hidden;
}

.tabla-declaracion td .select2-selection__rendered {
    max-width: 100%;
}

.instructivo-note {
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e3a8a;
    border-radius: 12px;
    padding: 10px 12px;
    font-size: 11px;
}

.instructivo-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    background: #fff;
}

.instructivo-card h6 {
    color: #1d4ed8;
    font-weight: 700;
}

.instructivo-card ul {
    padding-left: 18px;
    margin-bottom: 0;
}

.instructivo-card li {
    margin-bottom: 6px;
    white-space: normal;
}

.stats-panel {
    border: 1px solid #dbeafe;
    background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
    border-radius: 14px;
    padding: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(180px, 1fr));
    gap: 12px;
}

.stats-card {
    background: #fff;
    border: 1px solid #dbe7f3;
    border-radius: 12px;
    padding: 12px;
}

.stats-label {
    font-size: 11px;
    color: #475569;
    margin-bottom: 4px;
}

.stats-value {
    font-size: 21px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.1;
}

.stats-value small {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
}

.stats-percent {
    font-size: 11px;
    color: #2563eb;
    font-weight: 600;
    margin-top: 4px;
}

.stats-progress-wrap {
    margin-top: 12px;
}

.stats-progress-label {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 11px;
    color: #334155;
    margin-bottom: 6px;
    font-weight: 600;
}

.stats-progress {
    width: 100%;
    height: 12px;
    border-radius: 999px;
    background: #dbeafe;
    overflow: hidden;
}

.stats-progress-bar {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #2563eb 0%, #22c55e 100%);
    transition: width .2s ease;
}

.btn-inline-save {
    display: block;
    width: 100%;
    max-width: 100%;
    margin-top: 6px;
    padding: 4px 6px;
    border-radius: 6px;
    font-size: 10px;
    line-height: 1.15;
    white-space: normal;
}


.reporte-establecimientos-table th,
.reporte-establecimientos-table td {
    vertical-align: middle;
    white-space: nowrap;
}
.reporte-establecimientos-table td.col-nombre-establecimiento {
    min-width: 260px;
    white-space: normal;
}
.reporte-establecimientos-progress {
    min-width: 180px;
}
.reporte-establecimientos-progress .progress {
    height: 12px;
    background: #eaf2ff;
}
.reporte-establecimientos-progress .progress-bar {
    background: linear-gradient(90deg, #0d6efd 0%, #198754 100%);
}

@media (max-width: 767.98px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="declaracion-page declaracion-container">
    @php
        $user = auth()->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $puedeAdministrarDeclaracion = (bool) ($isDeclaracionAdmin ?? false);
        $esFuncionarioEstab = (bool) ($isFuncionarioEstab ?? ($activeRole === 'funcionario_estab'));
        $puedeEditarDeclaracion = $puedeAdministrarDeclaracion || $esFuncionarioEstab;
        $mostrarColumnaAcciones = $puedeAdministrarDeclaracion || $esFuncionarioEstab;
    @endphp

    <div class="header-declaracion">
                <h2>{{ $tituloVista ?? 'Declaración Establecimiento 2026' }}</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @include('declaracion.partials.buscador')

    @php
        $activeTab = $tab ?? request('tab', 'docentes');
        $tabCounts = $counts ?? ['docentes' => 0, 'asistentes' => 0];
        $baseQuery = request()->except('page', 'tab');
        $docUrl = route('declaracion.index', array_merge($baseQuery, ['tab' => 'docentes']));
        $asiUrl = route('declaracion.index', array_merge($baseQuery, ['tab' => 'asistentes']));
    @endphp

    <div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
        <a href="{{ $docUrl }}" class="btn btn-sm {{ $activeTab === 'docentes' ? 'btn-primary' : 'btn-outline-primary' }}">
            Docentes <span class="badge bg-light text-dark ms-1">{{ $tabCounts['docentes'] ?? 0 }}</span>
        </a>
        <a href="{{ $asiUrl }}" class="btn btn-sm {{ $activeTab === 'asistentes' ? 'btn-primary' : 'btn-outline-primary' }}">
            Asistentes <span class="badge bg-light text-dark ms-1">{{ $tabCounts['asistentes'] ?? 0 }}</span>
        </a>
    </div>

    @php
        $estadisticasTab = $stats ?? [
            'total_funcionarios' => 0,
            'confirmados' => 0,
            'documentos_requeridos' => 0,
            'documentos_cargados' => 0,
            'porcentaje_confirmados' => 0,
            'porcentaje_documentos' => 0,
            'porcentaje_general' => 0,
            'unidades_completadas' => 0,
            'unidades_totales' => 0,
        ];
    @endphp


    @php
        $reporteAvance = collect($reporteEstablecimientos ?? []);
        $reporteCollapseId = 'reporte-establecimientos-' . $activeTab;
        $reporteExportUrl = route('declaracion.exportarReporteEstablecimientos', array_merge(request()->query(), ['tab' => $activeTab]));
    @endphp

    @if($reporteAvance->isNotEmpty())
    <div class="stats-panel mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <strong>Informe de avance por establecimiento</strong>
                <div class="small-note">Porcentaje de avance por RBD y nombre de establecimiento para la pestaña actual.</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="small-note">{{ $reporteAvance->count() }} establecimiento(s)</div>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $reporteCollapseId }}" aria-expanded="true" aria-controls="{{ $reporteCollapseId }}">
                    Mostrar / ocultar informe
                </button>
                <a href="{{ $reporteExportUrl }}" class="btn btn-sm btn-excel-dark">
                    <i class="bi bi-file-earmark-excel"></i> Descargar Excel
                </a>
            </div>
        </div>
        <div class="collapse show" id="{{ $reporteCollapseId }}">
            <div class="table-responsive">
                <table class="table table-sm reporte-establecimientos-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>RBD</th>
                            <th class="col-nombre-establecimiento">Nombre establecimiento</th>
                            <th>Confirmados / Total</th>
                            <th>Documentos / Total</th>
                            <th>Avance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reporteAvance as $filaReporte)
                            <tr>
                                <td>{{ $filaReporte['rbd'] ?? '' }}</td>
                                <td class="col-nombre-establecimiento">{{ $filaReporte['nombre_establecimiento'] ?? 'Sin nombre de establecimiento' }}</td>
                                <td>{{ $filaReporte['confirmados'] ?? 0 }}/{{ $filaReporte['total_funcionarios'] ?? 0 }} <span class="text-muted">({{ $filaReporte['porcentaje_confirmados'] ?? 0 }}%)</span></td>
                                <td>{{ $filaReporte['documentos_cargados'] ?? 0 }}/{{ $filaReporte['documentos_requeridos'] ?? 0 }} <span class="text-muted">({{ $filaReporte['porcentaje_documentos'] ?? 0 }}%)</span></td>
                                <td class="reporte-establecimientos-progress">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>{{ $filaReporte['porcentaje_general'] ?? 0 }}%</span>
                                        <span class="text-muted">avance general</span>
                                    </div>
                                    <div class="progress" role="progressbar" aria-valuenow="{{ $filaReporte['porcentaje_general'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" style="width: {{ $filaReporte['porcentaje_general'] ?? 0 }}%;"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="stats-panel mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <strong>Estadísticas {{ ucfirst($activeTab) }}</strong>
                <div class="small-note">Resumen del avance de confirmación y carga documental para la pestaña actual.</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-label">Funcionarios confirmados / totales</div>
                <div class="stats-value">
                    {{ $estadisticasTab['confirmados'] ?? 0 }}/{{ $estadisticasTab['total_funcionarios'] ?? 0 }}
                    <small>funcionarios</small>
                </div>
                <div class="stats-percent">{{ $estadisticasTab['porcentaje_confirmados'] ?? 0 }}% confirmado</div>
            </div>

            <div class="stats-card">
                <div class="stats-label">Documentos cargados / documentos totales</div>
                <div class="stats-value">
                    {{ $estadisticasTab['documentos_cargados'] ?? 0 }}/{{ $estadisticasTab['documentos_requeridos'] ?? 0 }}
                    <small>documentos</small>
                </div>
                <div class="stats-percent">{{ $estadisticasTab['porcentaje_documentos'] ?? 0 }}% documental</div>
            </div>
        </div>

        <div class="stats-progress-wrap">
            <div class="stats-progress-label">
                <span>Avance general</span>
                <span>{{ $estadisticasTab['porcentaje_general'] ?? 0 }}%</span>
            </div>
            <div class="stats-progress" role="progressbar" aria-valuenow="{{ $estadisticasTab['porcentaje_general'] ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
                <div class="stats-progress-bar" style="width: {{ $estadisticasTab['porcentaje_general'] ?? 0 }}%;"></div>
            </div>
            <div class="small-note mt-2">
                Progreso calculado sobre registros confirmados y documentos requeridos/cargados de la pestaña actual.
            </div>
        </div>
    </div>

    <div class="mb-3 d-flex flex-column flex-lg-row gap-2 justify-content-between align-items-lg-center">
        <div class="instructivo-note flex-grow-1">
            <strong>Archivos permitidos:</strong> cada certificado debe cargarse en formato <strong>PDF</strong> y con un tamaño máximo de <strong>10 MB</strong> por archivo.
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalInstructivoDeclaracion">
                <i class="bi bi-info-circle"></i> Instrucciones {{ ucfirst($activeTab) }}
            </button>
            @if(Route::has('declaracion.instructivo.pdf'))
                <a href="{{ route('declaracion.instructivo.pdf', ['tab' => $activeTab]) }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener">
                    <i class="bi bi-filetype-pdf"></i> PDF imprimible
                </a>
            @endif
        </div>
    </div>

    @if($puedeAdministrarDeclaracion)
    <div class="card-ui mb-3 d-flex justify-content-between align-items-center gap-3">
        <form action="{{ route('declaracion.importar') }}" method="POST" enctype="multipart/form-data" class="mb-0">
            @csrf
            <div class="dropzone" onclick="document.getElementById('excelInput').click()">
                📂 Subir Excel
                <input type="file" id="excelInput" name="archivo" hidden onchange="this.form.submit()">
            </div>
        </form>

        @if(Route::has('declaracion.exportar'))
            <a href="{{ route('declaracion.exportar', request()->query()) }}" class="dropzone text-decoration-none text-dark d-flex align-items-center justify-content-center">
                ⬇ Descargar Excel
            </a>
        @endif

        @if(Route::has('declaracion.exportarPendientesDocumentos'))
            <a href="{{ route('declaracion.exportarPendientesDocumentos', array_merge(request()->query(), ['tab' => $activeTab])) }}" class="dropzone text-decoration-none text-dark d-flex align-items-center justify-content-center">
                Excel sin documentos {{ ucfirst($activeTab) }}
            </a>
        @endif

        @if(Route::has('declaracion.exportarDocumentos'))
            <form action="{{ route('declaracion.exportarDocumentos') }}" method="POST" class="mb-0">
                @csrf
                <input type="hidden" name="tab_export" value="{{ $activeTab }}">
                @foreach(request()->except('tab') as $queryKey => $queryValue)
                    @if(is_array($queryValue))
                        @foreach($queryValue as $itemQueryValue)
                            <input type="hidden" name="{{ $queryKey }}[]" value="{{ $itemQueryValue }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                    @endif
                @endforeach
                <button type="submit" class="dropzone border-0 bg-transparent text-dark d-flex align-items-center justify-content-center">
                    🗂 Generar exportación documentos {{ ucfirst($activeTab) }}
                </button>
            </form>
        @endif
    </div>
        <div class="export-status-card mb-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-2">
                <div>
                    <div class="fw-semibold">Exportaciones de documentos</div>
                    <div class="text-muted small">La generación del ZIP se procesa en segundo plano para evitar caídas por volumen de archivos.</div>
                </div>
                <a href="{{ route('declaracion.index', request()->query()) }}" class="btn btn-sm btn-outline-secondary">Actualizar estado</a>
            </div>

            @if(($exportacionesDocumentos ?? collect())->isEmpty())
                <div class="text-muted small">Aún no hay exportaciones solicitadas por tu usuario.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm export-status-table mb-0">
                        <thead>
                            <tr>
                                <th>Solicitada</th>
                                <th>Pestaña</th>
                                <th>Filtro</th>
                                <th>Estado</th>
                                <th>Archivos</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($exportacionesDocumentos as $exportacionDocumento)
                                @php
                                    $filtrosExportacion = $exportacionDocumento->filtros_json ?? [];
                                    $resumenFiltros = collect([
                                        !empty($filtrosExportacion['rut'] ?? null) ? 'RUT: ' . $filtrosExportacion['rut'] : null,
                                        !empty($filtrosExportacion['nombre'] ?? null) ? 'Nombre: ' . $filtrosExportacion['nombre'] : null,
                                        !empty($filtrosExportacion['establecimiento'] ?? null) ? 'RBD: ' . $filtrosExportacion['establecimiento'] : null,
                                    ])->filter()->values();

                                    $statusMap = [
                                        'pending' => ['label' => 'Pendiente', 'class' => 'bg-secondary text-white'],
                                        'processing' => ['label' => 'Procesando', 'class' => 'bg-warning text-dark'],
                                        'completed' => ['label' => 'Completado', 'class' => 'bg-success text-white'],
                                        'error' => ['label' => 'Error', 'class' => 'bg-danger text-white'],
                                    ];
                                    $statusUi = $statusMap[$exportacionDocumento->status] ?? ['label' => ucfirst((string) $exportacionDocumento->status), 'class' => 'bg-secondary text-white'];
                                @endphp
                                <tr>
                                    <td>{{ optional($exportacionDocumento->created_at)->format('d-m-Y H:i') }}</td>
                                    <td>{{ ucfirst($exportacionDocumento->tab ?? 'docentes') }}</td>
                                    <td>
                                        @if($resumenFiltros->isEmpty())
                                            <span class="text-muted">Sin filtro</span>
                                        @else
                                            <span title="{{ $resumenFiltros->implode(' | ') }}">{{ $resumenFiltros->implode(' · ') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="export-status-pill {{ $statusUi['class'] }}">{{ $statusUi['label'] }}</span>
                                        @if($exportacionDocumento->status === 'error' && !empty($exportacionDocumento->error_message))
                                            <div class="text-danger small mt-1">{{ $exportacionDocumento->error_message }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $exportacionDocumento->files_count ?? 0 }}</td>
                                    <td>
                                        @if($exportacionDocumento->status === 'completed')
                                            <a href="{{ route('declaracion.descargarExportacionDocumentos', $exportacionDocumento->id) }}" class="btn btn-sm btn-success">Descargar ZIP</a>
                                        @else
                                            <span class="text-muted small">No disponible</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif


    <div class="modal fade" id="modalInstructivoDeclaracion" tabindex="-1" aria-labelledby="modalInstructivoDeclaracionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalInstructivoDeclaracionLabel">{{ $instructivoActual['titulo'] ?? ('Instrucciones ' . ucfirst($activeTab)) }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="instructivo-note mb-3">
                        <strong>Importante:</strong> solo se aceptan archivos <strong>PDF</strong> y cada documento puede pesar como máximo <strong>10 MB</strong>.
                    </div>
                    @if(!empty($instructivoActual['subtitulo']))
                        <p class="text-muted">{{ $instructivoActual['subtitulo'] }}</p>
                    @endif
                    <div class="row g-3">
                        @foreach(($instructivoActual['secciones'] ?? []) as $seccion)
                            <div class="col-12">
                                <div class="instructivo-card">
                                    <h6 class="mb-2">{{ $seccion['titulo'] ?? '' }}</h6>
                                    <ul>
                                        @foreach(($seccion['items'] ?? []) as $itemInstructivo)
                                            <li>{{ $itemInstructivo }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    @if(Route::has('declaracion.instructivo.pdf'))
                        <a href="{{ route('declaracion.instructivo.pdf', ['tab' => $activeTab]) }}" class="btn btn-danger" target="_blank" rel="noopener">
                            <i class="bi bi-filetype-pdf"></i> Descargar PDF imprimible
                        </a>
                    @endif
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card-ui">
        <div class="tabla-scroll">
            <table class="table tabla-declaracion">
                <thead>
                    <tr>
                        <th class="col-n sticky-col sticky-1">N°</th>
                        <th class="col-rbd sticky-col sticky-2">RBD</th>
                        <th class="col-rut sticky-col sticky-3">RUT</th>
                        <th class="col-nombres">Nombres</th>
                        <th class="col-apellido">Apellido Paterno</th>
                        <th class="col-apellido">Apellido Materno</th>
                        <th class="col-horas">HORAS</th>
                        <th class="col-parv">PARV</th>
                        <th class="col-basica">BÁSICA</th>
                        <th class="col-media">MEDIA</th>
                        <th class="col-cert">Cert. Título</th>
                        <th class="col-cert">Cert. Antecedentes</th>
                        @if($activeTab === 'asistentes')
                        <th class="col-funcion">Función</th>
                        <th class="col-tipo-titulo">Tipo Título</th>
                        @endif
                        <th class="col-titulo">Nombre Título</th>
                        <th class="col-inst">Institución</th>
                        <th class="col-fecha">Fecha Titulación</th>
                        <th class="col-pais">País</th>
                        @if($mostrarColumnaAcciones)
                        <th class="col-acciones">Acciones</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                @forelse($registros as $item)
                @php
                    $formLaboralId = 'laboral_form_' . $item->id;
                    $formFuncionId = 'funcion_form_' . $item->id;
                    $formTipoTituloId = 'tipo_titulo_form_' . $item->id;
                    $funcionNombreActual = trim((string) ($item->nombre_funcion ?: $item->funcionCatalogo?->nombre ?: ''));
                    $funcionEsOtro = (int) ($item->funcion_catalogo_id ?? 0) > 0
                        && \Illuminate\Support\Str::of((string) ($item->funcionCatalogo?->nombre ?? ''))
                            ->lower()->ascii()->replace(['_', '-', '.', 'º', '°', '#'], ' ')->squish()->value() === 'otro';
                    $funcionOtroTexto = $funcionEsOtro && \Illuminate\Support\Str::of($funcionNombreActual)
                            ->lower()->ascii()->replace(['_', '-', '.', 'º', '°', '#'], ' ')->squish()->value() !== 'otro'
                        ? $funcionNombreActual
                        : '';
                    $tipoTituloEsNinguno = $activeTab === 'asistentes' && (string) ($item->tipo_titulo ?? '') === 'Ninguno';
                    $confirmErrors = [];
                    if (!$item->hasNivelSeleccionado()) {
                        $confirmErrors[] = 'Marque al menos uno de PARV, BÁSICA o MEDIA.';
                    }
                    if ($item->isDocente()) {
                        if (blank($item->certificado_titulo)) {
                            $confirmErrors[] = 'Suba Cert. Título.';
                        }
                        if (blank($item->certificado_antecedentes)) {
                            $confirmErrors[] = 'Suba Cert. Antecedentes.';
                        }
                        if ($item->horas_contratadas === null || $item->horas_contratadas === '') {
                            $confirmErrors[] = 'Complete HORAS.';
                        }
                        if (blank($item->nombre_titulo)) {
                            $confirmErrors[] = 'Complete Nombre Título.';
                        }
                        if (blank($item->institucion_educacional)) {
                            $confirmErrors[] = 'Complete Institución.';
                        }
                        if (blank($item->fecha_titulacion)) {
                            $confirmErrors[] = 'Complete Fecha Titulación.';
                        }
                        if (blank($item->pais_titulo)) {
                            $confirmErrors[] = 'Seleccione País.';
                        }
                    } elseif ($item->isAsistente()) {
                        if ($item->requiereCertificadoAntecedentesParaConfirmacion() && blank($item->certificado_antecedentes)) {
                            $confirmErrors[] = 'Suba Cert. Antecedentes.';
                        }
                        if ($item->requiereCertificadoTituloParaConfirmacion() && blank($item->certificado_titulo)) {
                            $confirmErrors[] = 'Suba Cert. Título.';
                        }
                        if (!$item->tieneFuncionSeleccionada()) {
                            $confirmErrors[] = 'Seleccione Función.';
                        } elseif ($item->funcionEsOtro() && !$item->tieneTextoFuncionOtro()) {
                            $confirmErrors[] = 'Ingrese la función de Otro.';
                        }
                    }
                    $canConfirmRegistro = empty($confirmErrors);
                    $confirmDisabled = !empty($item->confirma_registro) || !$canConfirmRegistro;
                    $confirmTitle = !empty($item->confirma_registro)
                        ? 'Registro confirmado'
                        : ($canConfirmRegistro ? 'Confirmar registro' : 'No puede confirmar: ' . implode(' | ', $confirmErrors));
                @endphp
                <tr class="{{ !empty($item->confirma_registro) ? 'fila-confirmada' : '' }}">
                    <td class="col-n sticky-col sticky-1">{{ ($registros->firstItem() ?? 0) + $loop->index }}</td>
                    <td class="col-rbd sticky-col sticky-2">{{ $item->rbd }}</td>
                    <td class="col-rut sticky-col sticky-3">{{ $item->rut }}</td>
                    <td class="col-nombres wrap-text">{{ $item->nombres }}</td>
                    <td class="col-apellido wrap-text">{{ $item->apellido_paterno }}</td>
                    <td class="col-apellido wrap-text">{{ $item->apellido_materno }}</td>

                    <td class="col-horas">
                        @if($puedeEditarDeclaracion)
                            <form id="{{ $formLaboralId }}" action="{{ route('declaracion.actualizarDatosLaborales', $item->id) }}" method="POST" class="mb-0">
                                @csrf
                                @method('PUT')
                            </form>
                            <input type="number"
                                   name="horas_contratadas"
                                   form="{{ $formLaboralId }}"
                                   class="form-control form-control-sm"
                                   min="0"
                                   step="1"
                                   value="{{ $item->horas_contratadas }}"
                                   onblur="this.form.requestSubmit()"
                                   onkeydown="if(event.key === 'Enter'){ event.preventDefault(); this.form.requestSubmit(); }">
                        @else
                            {{ $item->horas_contratadas }}
                        @endif
                    </td>
                    <td class="col-parv">
                        @if($puedeEditarDeclaracion)
                            <input type="hidden" name="educacion_parvularia" value="0" form="{{ $formLaboralId }}">
                            <input type="checkbox"
                                   name="educacion_parvularia"
                                   value="1"
                                   form="{{ $formLaboralId }}"
                                   {{ $item->educacion_parvularia ? 'checked' : '' }}
                                   onchange="this.form.requestSubmit()">
                        @else
                            {{ $item->educacion_parvularia ? '✔' : '' }}
                        @endif
                    </td>
                    <td class="col-basica">
                        @if($puedeEditarDeclaracion)
                            <input type="hidden" name="ensenanza_basica" value="0" form="{{ $formLaboralId }}">
                            <input type="checkbox"
                                   name="ensenanza_basica"
                                   value="1"
                                   form="{{ $formLaboralId }}"
                                   {{ $item->ensenanza_basica ? 'checked' : '' }}
                                   onchange="this.form.requestSubmit()">
                        @else
                            {{ $item->ensenanza_basica ? '✔' : '' }}
                        @endif
                    </td>
                    <td class="col-media">
                        @if($puedeEditarDeclaracion)
                            <input type="hidden" name="ensenanza_media" value="0" form="{{ $formLaboralId }}">
                            <input type="checkbox"
                                   name="ensenanza_media"
                                   value="1"
                                   form="{{ $formLaboralId }}"
                                   {{ $item->ensenanza_media ? 'checked' : '' }}
                                   onchange="this.form.requestSubmit()">
                        @else
                            {{ $item->ensenanza_media ? '✔' : '' }}
                        @endif
                    </td>

                    <td class="col-cert">
                        <div class="cert-box">
                            @if($item->certificado_titulo)
                                <a class="link-ver-min"
                                   href="{{ route('declaracion.certificado.ver', [$item->id, 'titulo']) }}"
                                   target="_blank">Ver</a>
                            @endif

                            @if($puedeEditarDeclaracion)
                            <form action="{{ route('declaracion.certificado.subir', [$item->id, 'titulo']) }}"
                                  method="POST"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="file"
                                       name="certificado"
                                       hidden
                                       id="file_titulo_{{ $item->id }}"
                                       accept="application/pdf"
                                       {{ $tipoTituloEsNinguno ? 'disabled' : '' }}
                                       onchange="this.form.submit()">
                                <button type="button"
                                        id="btn_titulo_{{ $item->id }}"
                                        data-role="titulo-upload-button"
                                        data-row-id="{{ $item->id }}"
                                        onclick="document.getElementById('file_titulo_{{ $item->id }}').click()"
                                        class="btn-cert-min {{ $item->certificado_titulo ? 'btn-replace' : 'btn-upload' }}"
                                        {{ $tipoTituloEsNinguno ? 'disabled' : '' }}>
                                    {{ $item->certificado_titulo ? 'Reemplazar' : 'Subir' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>

                    <td class="col-cert">
                        <div class="cert-box">
                            @if($item->certificado_antecedentes)
                                <a class="link-ver-min"
                                   href="{{ route('declaracion.certificado.ver', [$item->id, 'antecedentes']) }}"
                                   target="_blank">Ver</a>
                            @endif

                            @if($puedeEditarDeclaracion)
                            <form action="{{ route('declaracion.certificado.subir', [$item->id, 'antecedentes']) }}"
                                  method="POST"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="file"
                                       name="certificado"
                                       hidden
                                       id="file_ant_{{ $item->id }}"
                                       accept="application/pdf"
                                       onchange="this.form.submit()">
                                <button type="button"
                                        onclick="document.getElementById('file_ant_{{ $item->id }}').click()"
                                        class="btn-cert-min {{ $item->certificado_antecedentes ? 'btn-replace' : 'btn-upload' }}">
                                    {{ $item->certificado_antecedentes ? 'Reemplazar' : 'Subir' }}
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>

                    @if($activeTab === 'asistentes')
                    <td class="col-funcion">
                        @if($puedeEditarDeclaracion)
                        <form id="{{ $formFuncionId }}" action="{{ route('declaracion.actualizarFuncion', $item->id) }}" method="POST" class="mb-0 js-funcion-form">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="tab" value="{{ $activeTab }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                            @foreach(request()->except('page', 'tab') as $hiddenKey => $hiddenValue)
                                <input type="hidden" name="{{ $hiddenKey }}" value="{{ $hiddenValue }}">
                            @endforeach
                            <select name="funcion_catalogo_id"
                                    class="form-select form-select-sm js-funcion-select"
                                    data-other-target="funcion_otro_wrap_{{ $item->id }}"
                                    >
                                <option value="">Seleccione</option>
                                @foreach(($funcionesAsistente ?? collect()) as $funcion)
                                    @php
                                        $funcionNormalizada = \Illuminate\Support\Str::of((string) $funcion->nombre)
                                            ->lower()->ascii()->replace(['_', '-', '.', 'º', '°', '#'], ' ')->squish()->value();
                                    @endphp
                                    <option value="{{ $funcion->id }}"
                                            data-is-other="{{ $funcionNormalizada === 'otro' ? '1' : '0' }}"
                                            {{ (int) ($item->funcion_catalogo_id ?? 0) === (int) $funcion->id ? 'selected' : '' }}>
                                        {{ $funcion->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <div id="funcion_otro_wrap_{{ $item->id }}" class="mt-2" style="display: {{ $funcionEsOtro ? 'block' : 'none' }};">
                                <input type="text"
                                       name="nombre_funcion_otro"
                                       class="form-control form-control-sm"
                                       maxlength="255"
                                       placeholder="Ingrese la función"
                                       value="{{ $funcionOtroTexto }}"
                                       onkeydown="if(event.key === 'Enter'){ event.preventDefault(); this.form.requestSubmit(); }">
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary btn-inline-save">Guardar función</button>
                        </form>
                        @else
                            <div class="wrap-text">{{ $item->nombre_funcion ?: $item->funcionCatalogo?->nombre ?: '—' }}</div>
                        @endif
                    </td>
                    <td class="col-tipo-titulo">
                        @if($puedeEditarDeclaracion)
                        <form id="{{ $formTipoTituloId }}" action="{{ route('declaracion.actualizarTipoTitulo', $item->id) }}" method="POST" class="mb-0">
                            @csrf
                            @method('PUT')
                            <select name="tipo_titulo"
                                    class="form-select form-select-sm js-tipo-titulo-select"
                                    data-row-id="{{ $item->id }}"
                                    >
                                @foreach(($tiposTituloAsistente ?? []) as $valorTipoTitulo => $etiquetaTipoTitulo)
                                    <option value="{{ $valorTipoTitulo }}" {{ (string) ($item->tipo_titulo ?? '') === (string) $valorTipoTitulo ? 'selected' : '' }}>
                                        {{ $etiquetaTipoTitulo }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        @else
                            <div class="wrap-text">{{ $item->tipo_titulo ?: '—' }}</div>
                        @endif
                    </td>
                    @endif

                    <td class="col-titulo">
                        @if($puedeEditarDeclaracion)
                        @php
                            $nombreTituloActual = old('nombre_titulo', $tipoTituloEsNinguno ? '' : $item->nombre_titulo);
                            $titulosDisponibles = collect($titulos ?? []);
                            if ($nombreTituloActual && !$titulosDisponibles->contains($nombreTituloActual)) {
                                $titulosDisponibles = $titulosDisponibles->push($nombreTituloActual);
                            }
                            $titulosDisponibles = $titulosDisponibles->filter()->unique()->sort()->values();
                        @endphp
                        <form action="{{ route('declaracion.actualizarTitulo', $item->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <select
                                name="nombre_titulo"
                                id="nombre_titulo_{{ $item->id }}"
                                data-role="nombre-titulo"
                                data-row-id="{{ $item->id }}"
                                class="form-select form-select-sm wrap-text js-nombre-titulo-select"
                                data-placeholder="Seleccione título"
                                {{ $tipoTituloEsNinguno ? 'disabled' : '' }}>
                                <option value=""></option>
                                @foreach($titulosDisponibles as $tituloOpcion)
                                    <option value="{{ $tituloOpcion }}" {{ (string) $nombreTituloActual === (string) $tituloOpcion ? 'selected' : '' }}>
                                        {{ $tituloOpcion }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        @else
                            <div class="wrap-text">{{ $tipoTituloEsNinguno ? '—' : ($item->nombre_titulo ?: '—') }}</div>
                        @endif
                    </td>

                    <td class="col-inst">
                        @if($puedeEditarDeclaracion)
                        @php
                            $institucionesDisponibles = collect($institucionesCatalogo ?? [])->filter();
                            $institucionActualTexto = old('institucion_educacional_otra', $tipoTituloEsNinguno ? '' : $item->institucion_educacional);
                            $institucionCatalogoActual = $item->institucion_catalogo_id
                                ? $item->institucion_catalogo_id
                                : optional($institucionesDisponibles->firstWhere('nombre', $institucionActualTexto))->id;
                            $institucionSeleccionActual = $institucionCatalogoActual ? (string) $institucionCatalogoActual : ($institucionActualTexto ? '__otro__' : '');
                        @endphp
                        <form action="{{ route('declaracion.actualizarInstitucion', $item->id) }}" method="POST" class="mb-0 js-institucion-form">
                            @csrf
                            @method('PUT')
                            <select
                                name="institucion_catalogo_selector"
                                id="institucion_educacional_{{ $item->id }}"
                                data-role="institucion-titulo"
                                data-row-id="{{ $item->id }}"
                                class="form-select form-select-sm wrap-text js-institucion-select"
                                data-placeholder="Seleccione institución"
                                data-other-target="institucion_otro_wrap_{{ $item->id }}"
                                {{ $tipoTituloEsNinguno ? 'disabled' : '' }}>
                                <option value=""></option>
                                @foreach($institucionesDisponibles as $institucionOpcion)
                                    <option value="{{ $institucionOpcion->id }}" {{ (string) $institucionSeleccionActual === (string) $institucionOpcion->id ? 'selected' : '' }}>
                                        {{ $institucionOpcion->nombre }}
                                    </option>
                                @endforeach
                                <option value="__otro__" {{ $institucionSeleccionActual === '__otro__' ? 'selected' : '' }}>Otro</option>
                            </select>
                            <div id="institucion_otro_wrap_{{ $item->id }}" class="mt-2" style="display: {{ $institucionSeleccionActual === '__otro__' ? 'block' : 'none' }};">
                                <input type="text"
                                       name="institucion_educacional_otra"
                                       id="institucion_educacional_otra_{{ $item->id }}"
                                       class="form-control form-control-sm"
                                       maxlength="255"
                                       placeholder="Ingrese la institución"
                                       value="{{ $institucionSeleccionActual === '__otro__' ? $institucionActualTexto : '' }}"
                                       onkeydown="if(event.key === 'Enter'){ event.preventDefault(); this.form.requestSubmit(); }"
                                       {{ $tipoTituloEsNinguno ? 'disabled' : '' }}>
                                <button type="submit"
                                        id="btn_guardar_institucion_{{ $item->id }}"
                                        class="btn btn-sm btn-outline-primary btn-inline-save"
                                        {{ $tipoTituloEsNinguno ? 'disabled' : '' }}>Guardar institución</button>
                            </div>
                        </form>
                        @else
                            <div class="wrap-text">{{ $tipoTituloEsNinguno ? '—' : ($item->institucion_educacional ?: '—') }}</div>
                        @endif
                    </td>

                    <td class="col-fecha">
                        @if($puedeEditarDeclaracion)
                        <form action="{{ route('declaracion.actualizarFecha', $item->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="date"
                                   name="fecha_titulacion"
                                   id="fecha_titulacion_{{ $item->id }}"
                                   data-role="fecha-titulo"
                                   data-row-id="{{ $item->id }}"
                                   value="{{ $tipoTituloEsNinguno ? '' : $item->fecha_titulacion }}"
                                   class="form-control form-control-sm"
                                   onchange="this.form.submit()"
                                   {{ $tipoTituloEsNinguno ? 'disabled' : '' }}>
                        </form>
                        @else
                            {{ $tipoTituloEsNinguno ? '—' : ($item->fecha_titulacion ?: '—') }}
                        @endif
                    </td>

                    <td class="col-pais">
                        @if($puedeEditarDeclaracion)
                        <form action="{{ route('declaracion.actualizarPais', $item->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <select name="pais_titulo"
                                    class="form-select form-select-sm"
                                    onchange="this.form.submit()">
                                <option value="">Seleccione</option>
                                @foreach(($paises ?? []) as $key => $pais)
                                    <option value="{{ $key }}" {{ $item->pais_titulo == $key ? 'selected' : '' }}>
                                        {{ $pais }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                        @else
                            <div class="wrap-text">{{ $paises[$item->pais_titulo] ?? $item->pais_titulo ?? '—' }}</div>
                        @endif
                    </td>

                    @if($mostrarColumnaAcciones)
                    <td class="col-acciones">
                        @if($esFuncionarioEstab)
                        <div class="d-flex flex-column align-items-center" title="{{ $confirmTitle }}">
                            <form action="{{ route('declaracion.confirmarRegistro', $item->id) }}" method="POST" class="mb-1 d-flex justify-content-center">
                                @csrf
                                @method('PUT')
                                <button type="submit"
                                        class="btn-confirm-min {{ !empty($item->confirma_registro) ? 'is-confirmed' : ($canConfirmRegistro ? '' : 'is-blocked') }}"
                                        aria-label="{{ $confirmTitle }}"
                                        {{ $confirmDisabled ? 'disabled' : '' }}>
                                    &#10003;
                                </button>
                            </form>
                            @if(empty($item->confirma_registro) && !$canConfirmRegistro)
                                <span class="confirm-note">{{ $confirmErrors[0] }}</span>
                            @endif
                        </div>
                        @endif

                        @if($puedeAdministrarDeclaracion)
                        <form action="{{ route('declaracion.destroy', $item->id) }}" method="POST" class="mb-1">
                            @csrf
                            @method('DELETE')
                            <button class="btn-delete-min">Eliminar</button>
                        </form>

                        @if(Route::has('declaracion.actualizarRbd'))
                        <button type="button"
                                class="btn-rbd-toggle"
                                onclick="toggleRbdPanel('{{ $item->id }}')">
                            Modificar RBD
                        </button>

                        <div id="rbd-panel-{{ $item->id }}" class="rbd-panel" style="display: none;">
                            <form action="{{ route('declaracion.actualizarRbd', $item->id) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <select name="rbd" class="form-select form-select-sm" required>
                                    <option value="">Establecimiento</option>
                                    @foreach(($establecimientos ?? []) as $establecimiento)
                                        <option value="{{ $establecimiento->cod_estab }}"
                                            {{ (string) $item->rbd === (string) $establecimiento->cod_estab ? 'selected' : '' }}>
                                            {{ $establecimiento->cod_estab }} - {{ $establecimiento->nombre_establecimiento }}
                                        </option>
                                    @endforeach
                                </select>

                                <button type="submit" class="btn-save-rbd">
                                    Guardar
                                </button>
                            </form>
                        </div>
                        @endif
                        @endif
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $mostrarColumnaAcciones ? ($activeTab === 'asistentes' ? 19 : 17) : ($activeTab === 'asistentes' ? 18 : 16) }}" class="text-center">No hay registros</td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3 d-flex flex-column flex-lg-row justify-content-between align-items-center paginacion-declaracion">
            <div class="small-note">
                Mostrando {{ $registros->firstItem() ?? 0 }} a {{ $registros->lastItem() ?? 0 }} de {{ $registros->total() }} registros · 50 por página
            </div>

            @if($registros->lastPage() > 1)
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                @foreach(request()->except('page', 'tab') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="selectorPaginaDeclaracion" class="mb-0 small fw-semibold">Página</label>
                <select id="selectorPaginaDeclaracion"
                        name="page"
                        class="form-select form-select-sm selector-pagina"
                        onchange="this.form.submit()">
                    @for($pagina = 1; $pagina <= $registros->lastPage(); $pagina++)
                        <option value="{{ $pagina }}" {{ $registros->currentPage() == $pagina ? 'selected' : '' }}>
                            Página {{ $pagina }} de {{ $registros->lastPage() }}
                        </option>
                    @endfor
                </select>
            </form>
            @endif
        </div>

        <div class="mt-3 d-flex justify-content-center">
            {{ $registros->onEachSide(1)->links() }}
        </div>
    </div>
</div>

<script>
function toggleRbdPanel(id) {
    const panel = document.getElementById('rbd-panel-' + id);
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

window.declaracionSuspendAutoSubmit = function(field, callback) {
    if (!field) {
        return;
    }

    field.dataset.suspendSubmit = '1';
    try {
        callback();
    } finally {
        window.setTimeout(function () {
            delete field.dataset.suspendSubmit;
        }, 0);
    }
};

window.declaracionSetTituloFieldsState = function(rowId, lockFields) {
    const nombreInput = document.getElementById('nombre_titulo_' + rowId);
    const institucionSelect = document.getElementById('institucion_educacional_' + rowId);
    const institucionOtroInput = document.getElementById('institucion_educacional_otra_' + rowId);
    const institucionOtroWrap = document.getElementById('institucion_otro_wrap_' + rowId);
    const institucionOtroButton = document.getElementById('btn_guardar_institucion_' + rowId);
    const fechaInput = document.getElementById('fecha_titulacion_' + rowId);
    const uploadButton = document.getElementById('btn_titulo_' + rowId);
    const fileInput = document.getElementById('file_titulo_' + rowId);

    [nombreInput, institucionSelect, fechaInput].forEach(function(field) {
        if (!field) {
            return;
        }

        if (lockFields) {
            window.declaracionSuspendAutoSubmit(field, function () {
                field.value = '';
                if (window.jQuery && window.jQuery(field).hasClass('select2-hidden-accessible')) {
                    window.jQuery(field).val('').trigger('change.select2');
                }
            });
        }

        field.disabled = !!lockFields;

        if (window.jQuery && window.jQuery(field).hasClass('select2-hidden-accessible')) {
            window.declaracionSuspendAutoSubmit(field, function () {
                window.jQuery(field).prop('disabled', !!lockFields).trigger('change.select2');
            });
        }
    });

    if (institucionOtroInput) {
        if (lockFields) {
            institucionOtroInput.value = '';
        }
        institucionOtroInput.disabled = !!lockFields;
    }

    if (institucionOtroButton) {
        institucionOtroButton.disabled = !!lockFields;
    }

    if (institucionOtroWrap && lockFields) {
        institucionOtroWrap.style.display = 'none';
    }

    if (fileInput) {
        if (lockFields) {
            fileInput.value = '';
        }
        fileInput.disabled = !!lockFields;
    }

    if (uploadButton) {
        uploadButton.disabled = !!lockFields;
        uploadButton.title = lockFields ? 'Deshabilitado cuando Tipo Título es Ninguno.' : '';
    }
};

window.declaracionInitEnhancedSelects = function() {
    if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
        return;
    }

    window.jQuery('.js-nombre-titulo-select, .js-institucion-select').each(function () {
        const $el = window.jQuery(this);
        if ($el.hasClass('select2-hidden-accessible')) {
            return;
        }

        this.dataset.ready = '0';
        $el.select2({
            width: '100%',
            placeholder: $el.data('placeholder') || 'Seleccione',
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownAutoWidth: false,
            dropdownParent: window.jQuery(document.body)
        });
        $el.off('.declaracionSelect2');
        if ($el.hasClass('js-nombre-titulo-select')) {
            $el.on('select2:select.declaracionSelect2 select2:clear.declaracionSelect2', function () {
                window.declaracionHandleSelectChange(this, () => {
                    if (window.declaracionCanAutoSubmit(this)) {
                        this.form.requestSubmit();
                    }
                });
            });
        }
        if ($el.hasClass('js-institucion-select')) {
            $el.on('select2:select.declaracionSelect2 select2:clear.declaracionSelect2', function () {
                window.declaracionHandleSelectChange(this, () => {
                    window.declaracionToggleInstitucionOtro(this, true);
                });
            });
        }
        window.setTimeout(() => {
            this.dataset.ready = '1';
        }, 0);
    });
};

window.declaracionCanAutoSubmit = function(selectEl) {
    return !!(selectEl && selectEl.dataset && selectEl.dataset.ready === '1' && selectEl.dataset.suspendSubmit !== '1');
};

window.declaracionHandleSelectChange = function(selectEl, handler) {
    if (!selectEl || typeof handler !== 'function') {
        return;
    }

    const now = Date.now();
    const lastHandledAt = parseInt(selectEl.dataset.lastHandledAt || '0', 10);
    if (lastHandledAt && (now - lastHandledAt) < 250) {
        return;
    }

    selectEl.dataset.lastHandledAt = String(now);
    handler();
};

window.declaracionHandleTipoTitulo = function(selectEl) {
    const rowId = selectEl.getAttribute('data-row-id');
    const lockFields = (selectEl.value || '') === 'Ninguno';
    if (rowId) {
        window.declaracionSetTituloFieldsState(rowId, lockFields);
    }
    if (window.declaracionCanAutoSubmit(selectEl)) {
        selectEl.form.requestSubmit();
    }
};

window.declaracionToggleFuncionOtro = function(selectEl, shouldSubmit) {
    const selected = selectEl.options[selectEl.selectedIndex];
    const wrapId = selectEl.getAttribute('data-other-target');
    const wrap = document.getElementById(wrapId);
    const canSubmit = shouldSubmit !== false && window.declaracionCanAutoSubmit(selectEl);
    if (!wrap) {
        if (canSubmit) {
            selectEl.form.requestSubmit();
        }
        return;
    }
    const isOther = selected && selected.getAttribute('data-is-other') === '1';
    wrap.style.display = isOther ? 'block' : 'none';
    const input = wrap.querySelector('input[name="nombre_funcion_otro"]');
    if (isOther) {
        if (input) {
            input.focus();
        }
        return;
    }
    if (input) {
        input.value = '';
    }
    if (canSubmit) {
        selectEl.form.requestSubmit();
    }
};

window.declaracionToggleInstitucionOtro = function(selectEl, shouldSubmit) {
    const wrapId = selectEl.getAttribute('data-other-target');
    const wrap = document.getElementById(wrapId);
    const isOther = (selectEl.value || '') === '__otro__';
    const canSubmit = shouldSubmit !== false && window.declaracionCanAutoSubmit(selectEl);

    if (wrap) {
        wrap.style.display = isOther ? 'block' : 'none';
    }

    const input = wrap ? wrap.querySelector('input[name="institucion_educacional_otra"]') : null;
    if (isOther) {
        if (input) {
            input.focus();
        }
        return;
    }

    if (input) {
        input.value = '';
    }

    if (canSubmit) {
        selectEl.form.requestSubmit();
    }
};

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-funcion-select').forEach(function (selectEl) {
        selectEl.dataset.ready = '1';
        selectEl.addEventListener('change', function () {
            window.declaracionToggleFuncionOtro(selectEl, true);
        });
        window.declaracionToggleFuncionOtro(selectEl, false);
    });

    document.querySelectorAll('.js-tipo-titulo-select').forEach(function (selectEl) {
        selectEl.dataset.ready = '1';
        selectEl.addEventListener('change', function () {
            window.declaracionHandleTipoTitulo(selectEl);
        });
        const rowId = selectEl.getAttribute('data-row-id');
        if (!rowId) {
            return;
        }
        window.declaracionSetTituloFieldsState(rowId, (selectEl.value || '') === 'Ninguno');
    });

    document.querySelectorAll('.js-nombre-titulo-select').forEach(function (selectEl) {
        selectEl.addEventListener('change', function () {
            window.declaracionHandleSelectChange(selectEl, function () {
                if (window.declaracionCanAutoSubmit(selectEl)) {
                    selectEl.form.requestSubmit();
                }
            });
        });
    });

    document.querySelectorAll('.js-institucion-select').forEach(function (selectEl) {
        selectEl.addEventListener('change', function () {
            window.declaracionHandleSelectChange(selectEl, function () {
                window.declaracionToggleInstitucionOtro(selectEl, true);
            });
        });
        window.declaracionToggleInstitucionOtro(selectEl, false);
    });

    window.declaracionInitEnhancedSelects();
});
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


@endsection

