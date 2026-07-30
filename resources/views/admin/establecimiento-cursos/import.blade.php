@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Carga masiva de cursos por establecimiento</h1>
            <div class="text-muted small">Importa matrícula por curso/sección y asocia automáticamente el plan de estudio.</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-cursos.index') }}">Volver</a>
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
                La carga acepta la estructura del archivo <strong>MAT_JEC_2026</strong> con columnas RBD, ESTABLECIMIENTOS, CURSO, LETRA, MATRICULA_2026 y JEC. El sistema cruza por RBD, normaliza el curso, guarda la sección y busca el plan de estudio por curso + año + régimen.
            </div>
            <form method="POST" action="{{ route('admin.establecimiento-cursos.import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Año <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="anio" min="2020" max="2100" value="{{ old('anio', 2026) }}" required>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Archivo Excel (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="archivo" accept=".xlsx" required>
                        <div class="form-text">Tamaño máximo 10 MB. La clave de actualización es establecimiento + curso + año + letra.</div>
                    </div>
                </div>
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="reemplazar_existentes" name="reemplazar_existentes" value="1" @checked(old('reemplazar_existentes', true))>
                    <label class="form-check-label" for="reemplazar_existentes">
                        Reemplazar registros del año antes de importar
                    </label>
                    <div class="form-text">
                        Recomendado cuando la vista muestra registros incompletos o una carga previa quedó mal asociada. Elimina los registros del año seleccionado y vuelve a cargarlos desde el Excel.
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
                    <a class="btn btn-outline-success" href="{{ route('admin.establecimiento-cursos.template') }}">
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
