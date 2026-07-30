@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Carga judicial masiva</h1>
            <p class="text-muted mb-0">Importa nóminas judiciales con estructura oficial.</p>
        </div>
        <a href="{{ route('admin.restricted-ruts.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @php($importErrors = session('import_errors', []))
    @if (!empty($importErrors))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-2">Filas con observaciones</div>
            <ul class="mb-0 ps-3">
                @foreach ($importErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Plantilla</h2>
                    <p class="text-muted">Descarga una plantilla CSV con las columnas esperadas del tribunal.</p>
                    <a href="{{ route('admin.restricted-ruts.template') }}" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Descargar plantilla
                    </a>
                    <hr>
                    <div class="small text-muted">
                        <div><strong>Columnas requeridas:</strong></div>
                        <div>NOMBRE, RUN, JUZGADO ORIGEN, RIT, FECHA FALLO, INHABILIDAD</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Subir archivo</h2>
                    <form method="POST" action="{{ route('admin.restricted-ruts.import.store') }}" enctype="multipart/form-data" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Archivo Excel o CSV</label>
                            <input type="file" name="excel" class="form-control @error('excel') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                            @error('excel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="bi bi-upload"></i> Importar nómina</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
