@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Nueva carga MAE de endeudamiento</h1>
            <p class="text-muted mb-0">El formulario solicita mes-año y dominio. El dominio debe coincidir con la comuna interna del MAE.</p>
        </div>
        <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Reglas de importación</h2>
                    <ul class="small text-muted ps-3 mb-0">
                        <li>Se procesa exclusivamente Hoja1.</li>
                        <li>El formulario pide mes, año y dominio antes de subir el archivo.</li>
                        <li>Si ya existe una versión para el mismo período y dominio, la nueva queda como versión vigente y la anterior pasa a histórica.</li>
                        <li>Se valida que la comuna del archivo coincida con el dominio seleccionado.</li>
                        <li>Las columnas entre Dias_Trab y TOTAL HABERES no se guardan.</li>
                        <li>Aporte Adicional AFP no se guarda.</li>
                        <li>Cualquier columna nueva posterior a MONTO TRIBUTABLE se registra en otros descuentos.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('endeudamiento.cargas.store') }}" enctype="multipart/form-data" class="row g-3">
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
                            <label class="form-label">Motivo de reemplazo / actualización</label>
                            <textarea name="motivo_reemplazo" rows="2" class="form-control @error('motivo_reemplazo') is-invalid @enderror" placeholder="Opcional. Úsalo cuando subas una nueva versión corregida.">{{ old('motivo_reemplazo') }}</textarea>
                            @error('motivo_reemplazo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Archivo MAE (.xlsx o .xls)</label>
                            <input type="file" name="excel" class="form-control @error('excel') is-invalid @enderror" accept=".xlsx,.xls" required>
                            @error('excel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">La carga se encola para procesamiento en segundo plano. Debes tener el worker de Laravel activo para que el archivo avance desde pendiente a procesado.</div>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-upload"></i> Subir y encolar MAE</button>
                            <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
