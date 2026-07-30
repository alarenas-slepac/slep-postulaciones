@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Carga masiva Estudiantes PIE por curso</h1>
            <div class="text-muted small">Importa NEET y NEEP cruzando RBD, curso, letra y año contra establecimiento_cursos.</div>
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

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="alert alert-info">
                La planilla debe incluir RBD, CURSO, LETRA, ANIO, NEET, NEEP y OBSERVACION. El sistema actualiza o crea el registro PIE y valida que NEET + NEEP no supere la matrícula del curso/sección ya cargado.
            </div>
            <form method="POST" action="{{ route('admin.establecimiento-curso-pie.import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Año por defecto <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="anio" min="2020" max="2100" value="{{ old('anio', 2026) }}" required>
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
                        <i class="bi bi-upload"></i> Procesar carga
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
