@extends('layouts.app')

@section('content')
    @php
        $editando = $descuentoCgr->exists;
        $valor = fn (string $campo, mixed $predeterminado = '') => old($campo, $descuentoCgr->{$campo} ?? $predeterminado);
        $periodoPrimerDescuento = old('fecha_primer_descuento', $descuentoCgr->fecha_primer_descuento?->format('Y-m'));
        $periodoPrimerDescuento = $periodoPrimerDescuento ? substr((string) $periodoPrimerDescuento, 0, 7) : '';
    @endphp
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">{{ $editando ? 'Editar descuento CGR' : 'Nuevo descuento CGR' }}</h1>
                <p class="text-muted mb-0">Ingresa los datos exactamente como figuran en el dictamen o resolución.</p>
            </div>
            <a href="{{ $editando ? route('descuentos-cgr.show', $descuentoCgr) : route('descuentos-cgr.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger"><strong>Revisa los datos ingresados.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ $editando ? route('descuentos-cgr.update', $descuentoCgr) : route('descuentos-cgr.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($editando) @method('PUT') @endif

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Identificación y resolución</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                        <input id="rut" name="rut" class="form-control @error('rut') is-invalid @enderror" value="{{ $valor('rut') }}" required maxlength="12" placeholder="12.345.678-5">
                    </div>
                    <div class="col-md-8">
                        <label for="nombre" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input id="nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ $valor('nombre') }}" required maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label for="numero_resolucion" class="form-label">N° dictamen o resolución <span class="text-danger">*</span></label>
                        <input id="numero_resolucion" name="numero_resolucion" class="form-control" value="{{ $valor('numero_resolucion') }}" required maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label for="fecha_resolucion" class="form-label">Fecha resolución</label>
                        <input id="fecha_resolucion" type="date" name="fecha_resolucion" class="form-control" value="{{ old('fecha_resolucion', $descuentoCgr->fecha_resolucion?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-4">
                        <label for="resolucion_pdf" class="form-label">Resolución PDF {{ $editando ? '' : '*' }}</label>
                        <input id="resolucion_pdf" type="file" name="resolucion_pdf" class="form-control" accept="application/pdf" @required(! $editando)>
                        <div class="form-text">Máximo 20 MB. @if ($editando)Deja vacío para conservar el documento actual.@endif</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Parámetros del descuento</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label for="deuda_definitiva_pesos" class="form-label">Deuda definitiva (pesos) <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text">$</span><input id="deuda_definitiva_pesos" type="number" name="deuda_definitiva_pesos" class="form-control" min="1" step="1" value="{{ $valor('deuda_definitiva_pesos') }}" required></div>
                    </div>
                    <div class="col-md-4">
                        <label for="deuda_equivalente_utm" class="form-label">Deuda equivalente (UTM) <span class="text-danger">*</span></label>
                        <input id="deuda_equivalente_utm" type="number" name="deuda_equivalente_utm" class="form-control" min="0.0001" step="0.0001" value="{{ $valor('deuda_equivalente_utm') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label for="cuota_utm" class="form-label">Cuota (UTM) según resolución <span class="text-danger">*</span></label>
                        <input id="cuota_utm" type="number" name="cuota_utm" class="form-control" min="0.0001" step="0.0001" value="{{ $valor('cuota_utm') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="numero_cuotas" class="form-label">N° de cuotas <span class="text-danger">*</span></label>
                        <input id="numero_cuotas" type="number" name="numero_cuotas" class="form-control" min="1" max="600" step="1" value="{{ $valor('numero_cuotas') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label for="tasa_interes_anual" class="form-label">Tasa interés anual <span class="text-danger">*</span></label>
                        <div class="input-group"><input id="tasa_interes_anual" type="number" name="tasa_interes_anual" class="form-control" min="0" max="100" step="0.0001" value="{{ $valor('tasa_interes_anual') }}" required><span class="input-group-text">%</span></div>
                    </div>
                    <div class="col-md-3">
                        <label for="tasa_interes_mensual" class="form-label">Tasa interés mensual <span class="text-danger">*</span></label>
                        <div class="input-group"><input id="tasa_interes_mensual" type="number" name="tasa_interes_mensual" class="form-control" min="0" max="100" step="0.0001" value="{{ $valor('tasa_interes_mensual') }}" required><span class="input-group-text">%</span></div>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_primer_descuento" class="form-label">Primer descuento <span class="text-danger">*</span></label>
                        <input id="fecha_primer_descuento" type="month" name="fecha_primer_descuento" class="form-control" value="{{ $periodoPrimerDescuento }}" required>
                    </div>
                    <div class="col-12">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="3" maxlength="5000">{{ $valor('observaciones') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('descuentos-cgr.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button class="btn btn-primary"><i class="bi bi-calculator me-1"></i>{{ $editando ? 'Guardar y recalcular' : 'Registrar y calcular' }}</button>
            </div>
        </form>
    </div>
@endsection
