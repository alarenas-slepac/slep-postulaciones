@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Carga masiva de alumnos prioritarios</h1>
            <div class="text-muted small">Importa porcentajes por establecimiento y año mediante archivo Excel.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success" href="{{ route('admin.alumnos-prioritarios.template') }}">
                <i class="bi bi-file-earmark-excel"></i> Descargar plantilla
            </a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.alumnos-prioritarios.index') }}">
                Volver al listado
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">No fue posible procesar la carga.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('admin.alumnos-prioritarios.import.store') }}" enctype="multipart/form-data" class="card card-body shadow-sm">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Archivo Excel <span class="text-danger">*</span></label>
                    <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".xlsx" required>
                    @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Formato permitido: .xlsx. Tamaño máximo: 10 MB.</div>
                </div>

                <div class="alert alert-info mb-3">
                    <div class="fw-semibold">Comportamiento de la carga</div>
                    <ul class="mb-0 small">
                        <li>El sistema cruza cada fila por <strong>RBD</strong> contra la tabla de establecimientos.</li>
                        <li>Si ya existe un registro para el mismo establecimiento y año, será actualizado.</li>
                        <li>Si no existe, será creado.</li>
                        <li>Los RBD no encontrados serán omitidos e informados al finalizar.</li>
                    </ul>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit" onclick="return confirm('¿Procesar la carga masiva de alumnos prioritarios?')">
                        <i class="bi bi-upload"></i> Procesar carga
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.alumnos-prioritarios.index') }}">Cancelar</a>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">Columnas esperadas</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Columna</th>
                                    <th>Obligatoria</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>RBD</td><td>Sí</td></tr>
                                <tr><td>ANIO</td><td>Sí</td></tr>
                                <tr><td>PORCENTAJE</td><td>Sí</td></tr>
                                <tr><td>OBSERVACION</td><td>No</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-2">También se aceptan planillas externas con columnas <strong>ANIO_PROC</strong> y <strong>CONCENTRACION</strong>.</p>
                    <p class="small text-muted mb-0">El porcentaje puede venir con punto, coma o signo %, por ejemplo: 65.25, 65,25 o 65,25%.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
