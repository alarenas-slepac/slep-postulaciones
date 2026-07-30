@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Nueva carga de liquidaciones</h1>
            <p class="text-muted mb-0">Puedes subir el PDF completo si el servidor tiene Poppler, o importar un ZIP procesado localmente.</p>
        </div>
        <a href="{{ route('liquidaciones.cargas.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Reglas de publicación</h2>
                    <ul class="small text-muted ps-3 mb-0">
                        <li>El RUT se normaliza sin puntos ni guion para cruzarlo con <code>users.rut</code>.</li>
                        <li>Se publican sólo liquidaciones marcadas como reemplazo/suplencia.</li>
                        <li>El PDF original o paquete queda privado; cada usuario accede sólo a su PDF individual.</li>
                        <li>La descarga valida que el RUT de la liquidación coincida con el RUT del usuario autenticado.</li>
                    </ul>
                </div>
            </div>

            <div class="alert alert-info small mb-0">
                <strong>Recomendado para cPanel:</strong> usa la opción <strong>Importar ZIP procesado</strong>. No requiere <code>pdfinfo</code>, <code>pdftotext</code> ni <code>pdfseparate</code> en el servidor.
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Opción A: importar ZIP procesado localmente</div>
                    <div class="small text-muted">Usa el ZIP generado con el script local: contiene <code>manifest.csv</code> y carpeta <code>pdfs/</code>.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('liquidaciones.cargas.paquete.store') }}" enctype="multipart/form-data" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Mes</label>
                            <select name="mes" class="form-select @error('mes') is-invalid @enderror" required>
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected((int) old('mes', (int) now()->format('m')) === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                            @error('mes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Año</label>
                            <input type="number" name="anio" min="2024" max="2100" value="{{ old('anio', now()->format('Y')) }}" class="form-control @error('anio') is-invalid @enderror" required>
                            @error('anio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dominio</label>
                            <select name="dominio" class="form-select @error('dominio') is-invalid @enderror" required>
                                @foreach ($dominios as $dominio)
                                    <option value="{{ $dominio }}" @selected(old('dominio') === $dominio)>{{ $dominio }}</option>
                                @endforeach
                            </select>
                            @error('dominio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Paquete ZIP procesado</label>
                            <input type="file" name="paquete" class="form-control @error('paquete') is-invalid @enderror" accept="application/zip,.zip" required>
                            @error('paquete')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Estructura esperada: <code>manifest.csv</code> en la raíz y archivos PDF dentro de <code>pdfs/</code>.</div>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-upload"></i> Importar ZIP</button>
                            <a href="{{ route('liquidaciones.cargas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <div class="fw-semibold">Opción B: subir PDF completo</div>
                    <div class="small text-muted">Sólo usar cuando el servidor tenga Poppler disponible.</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('liquidaciones.cargas.store') }}" enctype="multipart/form-data" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Mes</label>
                            <select name="mes" class="form-select @error('mes') is-invalid @enderror" required>
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected((int) old('mes', (int) now()->format('m')) === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Año</label>
                            <input type="number" name="anio" min="2024" max="2100" value="{{ old('anio', now()->format('Y')) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Dominio</label>
                            <select name="dominio" class="form-select" required>
                                @foreach ($dominios as $dominio)
                                    <option value="{{ $dominio }}" @selected(old('dominio') === $dominio)>{{ $dominio }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">PDF completo de liquidaciones</label>
                            <input type="file" name="pdf" class="form-control @error('pdf') is-invalid @enderror" accept="application/pdf,.pdf">
                            @error('pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">Requiere <code>pdfinfo</code>, <code>pdftotext</code> y <code>pdfseparate</code>. La carga se procesa en cola.</div>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> Subir PDF completo</button>
                            <a href="{{ route('liquidaciones.cargas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
