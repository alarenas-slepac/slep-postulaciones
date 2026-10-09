@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4 p-4 bg-white border rounded-4 shadow-sm">
        <div>
            <h1 class="h3 fw-bold m-0">Reemplazos</h1>
            <div class="text-muted">Carga masiva de personal por Excel (solo admin)</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (auth()->user()?->activeRoleName() === 'admin')
                <button type="button" class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#actualizar-datos-personal"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i> Actualizar datos</button>
            @endif
            <a href="{{ route('reemplazos.personal.import', ['descargar_plantilla' => 1]) }}" class="btn btn-outline-primary rounded-pill">
                <i class="bi bi-file-earmark-excel"></i> Descargar plantilla
            </a>
            <a href="{{ route('reemplazos.index') }}" class="btn btn-outline-secondary rounded-pill">Volver</a>
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('reemplazos.index') }}">Inicio</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('reemplazos.personal.import') }}">Carga masiva</a>
        </li>
    </ul>

    @if (session('actualizacion_datos'))
        @php($actualizacion = session('actualizacion_datos'))
        <div class="alert alert-success rounded-4" role="status">
            <h2 class="h6 fw-bold">Actualización de datos finalizada · {{ sprintf('%02d/%d', $actualizacion['periodo'] % 100, intdiv($actualizacion['periodo'], 100)) }}</h2>
            <div>Filas leídas: <strong>{{ $actualizacion['filas'] }}</strong> · Personas actualizadas: <strong>{{ $actualizacion['ruts_actualizados'] }}</strong> · Líneas contractuales actualizadas: <strong>{{ $actualizacion['registros_actualizados'] }}</strong> · Líneas sin cambios: <strong>{{ $actualizacion['sin_cambios'] }}</strong> · Filas omitidas: <strong>{{ $actualizacion['omitidos'] }}</strong>.</div>
            <div class="small mt-2">Campos: {{ collect($actualizacion['campos'])->map(fn ($campo) => \App\Services\Padron\PadronDatosExcel::CAMPOS[$campo]['titulo'])->implode(', ') }}. Los contratos, jornadas, asignaciones y otros meses se conservaron.</div>
            @if ($actualizacion['reporte'])
                <a class="btn btn-outline-primary rounded-pill mt-3" href="{{ route('reemplazos.personal.datos.omitidos', $actualizacion['reporte']) }}"><i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i> Descargar registros omitidos</a>
            @endif
        </div>
    @endif

    @if (session('import_summary'))
        @php($s = session('import_summary'))
        <div class="alert alert-success rounded-4">
            <div class="fw-semibold mb-1">Importación finalizada</div>
            <div class="small">
                <div><strong>Archivo:</strong> {{ $s['archivo'] }}</div>
                <div><strong>Período:</strong> {{ $s['periodo'] }}</div>
                <hr class="my-2">
                <div><strong>Filas leídas:</strong> {{ $s['leidas'] }}</div>
                <div><strong>Filas candidatas:</strong> {{ $s['candidatas'] }}</div>
                <div><strong>Insertadas:</strong> {{ $s['insertadas'] }}</div>
                <div><strong>Actualizadas (idempotente):</strong> {{ $s['actualizadas'] }}</div>
                <div><strong>Omitidas (vacías/incompletas):</strong> {{ $s['omitidas_vacias'] }}</div>
                <div><strong>Omitidas (RBD sin establecimiento):</strong> {{ $s['omitidas_sin_estab'] }}</div>
                <div><strong>Omitidas (duplicadas dentro del archivo):</strong> {{ $s['omitidas_duplicadas'] }}</div>

                @if (!empty($s['rbds_faltantes']))
                    <div class="mt-2">
                        <strong>RBD sin establecimiento (muestra):</strong>
                        <span class="font-monospace">{{ implode(', ', array_slice($s['rbds_faltantes'], 0, 30)) }}{{ count($s['rbds_faltantes']) > 30 ? '…' : '' }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="card border rounded-4 shadow-sm">
        <div class="card-body p-4">
            <h2 class="h5 fw-bold card-title">Subir archivo Excel del padrón completo</h2>
            <p class="text-muted mb-3">
                Primero se genera una <strong>previsualización del padrón completo</strong>, sin modificar personal.
                Puede resolver coincidencias ambiguas, revisar vínculos históricos y autorizar excesos con justificación.
                En esta etapa la aplicación definitiva está bloqueada: no se actualizan contratos ni vigencias,
                ni se eliminan registros o asignaciones.
            </p>

            <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="accion" value="previsualizar">
                @if ($errors->any() && ! old('actualizar_datos'))
                    <div class="alert alert-danger rounded-4" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="mb-3">
                    <label class="form-label" for="excel">Archivo (.xlsx o .xls)</label>
                    <input id="excel" type="file" name="excel" class="form-control @error('excel') is-invalid @enderror" accept=".xlsx,.xls" required>
                    @error('excel')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Tamaño máximo: 50MB. Se procesará la primera hoja del archivo.
                    </div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold">Columnas requeridas (encabezados exactos)</div>
                    <div class="small text-muted">
                        rut, nombre, FECHA_NACIMIENTO, Fecha_Ingreso, Fecha_Termino, tipocontrato, FINANCIAMIENTO,
                        estatuto, escalafon, anio, mes, jornada, Jornada_Basica, Jornada_Media, RBD, Bienios
                    </div>
                    <div class="fw-semibold mt-2">Columna adicional para docentes</div>
                    <div class="small text-muted">
                        Tramo. También se aceptan encabezados TRAMO, tramo, Tramo Docente, TRAMO DOCENTE o TRAMO_DOCENTE.
                        Si no se informa, la carga mantiene el comportamiento anterior.
                    </div>
                    <div class="small text-muted mt-2">
                        Nueva columna opcional: <strong>fecha_antiguedad</strong> (YYYY-MM-DD, DD/MM/YYYY o fecha Excel).
                        Si está vacía o no viene en la plantilla, no se propone borrar la fecha existente.
                    </div>
                    <div class="mt-2">
                        <a href="{{ route('reemplazos.personal.import', ['descargar_plantilla' => 1]) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="bi bi-download"></i> Descargar plantilla oficial
                        </a>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="padron_completo" value="1" id="padron-completo" required>
                    <label class="form-check-label" for="padron-completo">Confirmo que el archivo contiene el padrón completo de todos los establecimientos para un único año y mes.</label>
                </div>
                <button class="btn btn-primary rounded-pill">
                    <i class="bi bi-search"></i> Analizar y previsualizar
                </button>
            </form>
        </div>
    </div>
    @if (auth()->user()?->activeRoleName() === 'admin')
        @include('reemplazos.personal.partials.actualizar-datos-modal')
    @endif
@endsection
