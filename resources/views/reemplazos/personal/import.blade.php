@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="m-0">Reemplazos</h3>
            <div class="text-muted">Carga masiva de personal por Excel (solo admin)</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reemplazos.personal.import', ['descargar_plantilla' => 1]) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel"></i> Descargar plantilla
            </a>
            <a href="{{ route('reemplazos.index') }}" class="btn btn-outline-secondary">Volver</a>
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

    @if (session('import_summary'))
        @php($s = session('import_summary'))
        <div class="alert alert-success">
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

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Subir archivo Excel</h5>
            <p class="text-muted mb-3">
                El import es <strong>idempotente</strong>: si subes el mismo archivo (o uno con las mismas filas),
                no se duplicará información; se actualizarán los registros existentes según la clave de la fila.
            </p>

            <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Archivo (.xlsx)</label>
                    <input type="file" name="excel" class="form-control @error('excel') is-invalid @enderror" accept=".xlsx" required>
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
                    <div class="mt-2">
                        <a href="{{ route('reemplazos.personal.import', ['descargar_plantilla' => 1]) }}" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-download"></i> Descargar plantilla oficial
                        </a>
                    </div>
                </div>

                <button class="btn btn-primary">
                    <i class="bi bi-upload"></i> Importar
                </button>
            </form>
        </div>
    </div>
@endsection
