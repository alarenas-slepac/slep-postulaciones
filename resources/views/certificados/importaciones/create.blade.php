@extends('layouts.app')

@section('content')
<div class="cl-page py-4">
    <div class="cl-hero">
        <div class="cl-hero-main">
            <span class="cl-hero-icon" aria-hidden="true"><i class="bi bi-upload"></i></span>
            <div>
                <div class="cl-eyebrow">Certificados laborales · Bases históricas</div>
                <h1 class="cl-hero-title">Importar historial de contratos</h1>
                <p class="cl-hero-subtitle">
                    La carga queda pendiente hasta que finalice el procesamiento y sea activada.
                </p>
            </div>
        </div>
        <div class="cl-hero-actions">
            <a href="{{ route('certificados.importaciones.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card cl-panel h-100">
                <div class="card-header">
                    <div class="cl-panel-kicker">Formato</div>
                    <h2 class="cl-panel-title">Columnas requeridas</h2>
                </div>
                <div class="card-body">
                    <ul class="cl-help-list mb-3">
                        <li>Rut y Nombre</li>
                        <li>Establecimiento y Comuna</li>
                        <li>Fec.Ing. y Fec.Finiq</li>
                        <li>Calidad Jurídica y Régimen Jurídico</li>
                    </ul>
                    <p class="cl-section-help mb-0">
                        Fec.Finiq puede contener una fecha válida o el valor “Indefinido”.
                        Las filas inválidas se omiten y quedan registradas como observaciones.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card cl-panel">
                <div class="card-header">
                    <div class="cl-panel-kicker">Carga</div>
                    <h2 class="cl-panel-title">Seleccionar archivo</h2>
                </div>
                <div class="card-body">
                    <form
                        method="POST"
                        action="{{ route('certificados.importaciones.store') }}"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        <div class="cl-section">
                            <label for="excel" class="form-label">Archivo Excel <span class="text-danger">*</span></label>
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
                            <div class="form-text">Formatos .xlsx y .xls · Tamaño máximo: 50 MB.</div>
                        </div>

                        <div class="cl-actionbar mt-4">
                            <a
                                href="{{ route('certificados.importaciones.index') }}"
                                class="btn btn-outline-secondary"
                            >Cancelar</a>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-upload" aria-hidden="true"></i> Subir y procesar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
