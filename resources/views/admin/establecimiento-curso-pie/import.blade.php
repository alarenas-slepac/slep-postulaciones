@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Carga masiva Estudiantes PIE por curso</h1>
            <div class="text-muted small">Actualiza NEET y NEEP cruzando RBD, curso, letra y año contra los registros PIE existentes.</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-curso-pie.index') }}">Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @isset($importResult)
        <div class="alert {{ $importResult['not_updated'] > 0 ? 'alert-warning' : 'alert-success' }} shadow-sm">
            <div class="fw-semibold">Proceso de actualización finalizado</div>
            <div>
                Se leyeron <strong>{{ number_format($importResult['read'], 0, ',', '.') }}</strong> filas:
                <strong>{{ number_format($importResult['updated'], 0, ',', '.') }}</strong> actualizadas y
                <strong>{{ number_format($importResult['not_updated'], 0, ',', '.') }}</strong> no actualizadas.
            </div>
        </div>

        @if (! empty($importResult['errors']))
            <div class="card border-warning shadow-sm mb-3">
                <div class="card-header bg-warning-subtle fw-semibold">Registros no actualizados y motivo</div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fila</th>
                                <th>RBD</th>
                                <th>Curso</th>
                                <th>Letra</th>
                                <th>Año</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($importResult['errors'] as $error)
                                <tr>
                                    <td>{{ $error['fila'] }}</td>
                                    <td>{{ $error['rbd'] ?: '—' }}</td>
                                    <td>{{ $error['curso'] ?: '—' }}</td>
                                    <td>{{ $error['letra'] ?: 'Sin letra' }}</td>
                                    <td>{{ $error['anio'] ?: '—' }}</td>
                                    <td>{{ $error['motivo'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endisset

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="alert alert-info">
                La planilla debe incluir RBD, CURSO, LETRA, ANIO, NEET, NEEP y OBSERVACION. La carga solo actualiza registros PIE existentes; no crea cursos ni registros nuevos y conserva el estado actual. También valida que NEET + NEEP no supere la matrícula del curso/sección.
            </div>
            <form method="POST" action="{{ route('admin.establecimiento-curso-pie.import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Año por defecto <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="anio" min="2020" max="2100" value="{{ old('anio', $importResult['anio'] ?? now()->year) }}" required>
                        <div class="form-text">Se usa si la fila no trae ANIO.</div>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Archivo Excel (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="archivo" accept=".xlsx" required>
                        <div class="form-text">Tamaño máximo 10 MB.</div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                    <a class="btn btn-outline-success" href="{{ route('admin.establecimiento-curso-pie.template') }}">
                        <i class="bi bi-file-earmark-excel"></i> Descargar plantilla
                    </a>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-upload"></i> Actualizar registros
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
