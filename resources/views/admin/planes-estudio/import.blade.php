@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Carga masiva de planes de estudio</h1>
            <div class="text-muted small">Importa o actualiza planes por curso, año y régimen JEC.</div>
        </div>
        <a class="btn btn-outline-danger" href="{{ route('admin.planes-estudio.index') }}">Volver</a>
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
                La plantilla contiene tres hojas: <strong>Resumen planes</strong>, <strong>Bloques</strong> y <strong>Detalle asignaturas</strong>. Las hojas Bloques y Detalle asignaturas son opcionales; si se informan, reemplazan los bloques o el detalle del plan correspondiente.
            </div>
            <form method="POST" action="{{ route('admin.planes-estudio.import.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Archivo Excel (.xlsx) <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" name="archivo" accept=".xlsx" required>
                    <div class="form-text">Tamaño máximo 10 MB. La clave de actualización es curso + año + régimen JEC.</div>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a class="btn btn-outline-success" href="{{ route('admin.planes-estudio.template') }}">
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
