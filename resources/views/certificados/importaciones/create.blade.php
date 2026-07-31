@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Importar historial de contratos</h1>
            <p class="text-muted mb-0">
                La carga queda pendiente hasta que finalice el procesamiento y sea activada.
            </p>
        </div>
        <a href="{{ route('certificados.importaciones.index') }}" class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Columnas requeridas</h2>
                    <ul class="small text-muted ps-3 mb-3">
                        <li>Rut y Nombre</li>
                        <li>Establecimiento y Comuna</li>
                        <li>Fec.Ing. y Fec.Finiq</li>
                        <li>Calidad Jurídica y Régimen Jurídico</li>
                    </ul>
                    <p class="small text-muted mb-0">
                        Fec.Finiq puede contener una fecha válida o el valor “Indefinido”.
                        Las filas inválidas se omiten y quedan registradas como observaciones.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form
                        method="POST"
                        action="{{ route('certificados.importaciones.store') }}"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        <label for="excel" class="form-label">Archivo Excel</label>
                        <input
                            id="excel"
                            type="file"
                            name="excel"
                            accept=".xlsx,.xls"
                            class="form-control @error('excel') is-invalid @enderror"
                            required
                        >
                        @error('excel')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Tamaño máximo: 50 MB.</div>

                        <div class="d-flex gap-2 mt-4">
                            <button class="btn btn-primary">
                                <i class="bi bi-upload"></i> Subir y procesar
                            </button>
                            <a
                                href="{{ route('certificados.importaciones.index') }}"
                                class="btn btn-outline-secondary"
                            >Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
