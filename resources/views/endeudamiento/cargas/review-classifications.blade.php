@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Revisión de categorías MAE</h1>
            <p class="text-muted mb-0">{{ $maeCarga->nombre_archivo }} · {{ sprintf('%02d/%04d', $maeCarga->mes, $maeCarga->anio) }} · {{ $maeCarga->dominio }}</p>
        </div>
        <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary">Revisar más tarde</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5">Archivo preparado para revisión</h2>
            <p class="text-muted mb-3">La importación todavía no ha comenzado. Confirma una categoría para cada columna detectada.</p>
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#maeClassificationModal">
                <i class="bi bi-tags"></i> Abrir revisión de categorías
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="maeClassificationModal" tabindex="-1" aria-labelledby="maeClassificationModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="{{ route('endeudamiento.cargas.clasificaciones.confirmar', $maeCarga) }}" class="modal-content">
            @csrf
            <div class="modal-header bg-warning-subtle">
                <div>
                    <h2 class="modal-title fs-5" id="maeClassificationModalLabel">Clasificaciones de descuentos</h2>
                    <div class="small text-muted">Revisa {{ $classifications->count() }} {{ $classifications->count() === 1 ? 'columna detectada' : 'columnas detectadas' }} antes de encolar el MAE.</div>
                </div>
            </div>
            <div class="modal-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <div class="fw-semibold">No fue posible confirmar la revisión.</div>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-info small">
                    Los cambios manuales quedarán registrados en esta carga y actualizarán la homologación general para futuras propuestas. La clasificación normativa se administra por separado.
                </div>

                @if ($classifications->isEmpty())
                    <div class="alert alert-warning mb-0">No se detectaron columnas de descuento clasificables después de MONTO TRIBUTABLE. Puedes confirmar para continuar con la importación.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 260px;">Columna del MAE</th>
                                    <th style="min-width: 170px;">Detección</th>
                                    <th style="min-width: 300px;">Categoría confirmada</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($classifications as $classification)
                                    @php
                                        $selected = old('clasificaciones.' . $classification->id, $classification->categoria_seleccionada);
                                    @endphp
                                    <tr class="{{ $classification->categoria_detectada === 'otros' ? 'table-warning' : '' }}">
                                        <td>
                                            <div class="fw-semibold">{{ $classification->columna_origen }}</div>
                                            <div class="small text-muted">Columna {{ $classification->orden_columna }} · {{ $classification->columna_normalizada }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $classification->fuente_deteccion === 'homologacion' ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                                {{ $classification->fuente_deteccion === 'homologacion' ? 'Homologación' : 'Automática' }}
                                            </span>
                                            @if ($classification->categoria_detectada === 'otros')
                                                <div class="small text-warning-emphasis mt-1">Requiere especial revisión.</div>
                                            @endif
                                        </td>
                                        <td>
                                            <select name="clasificaciones[{{ $classification->id }}]" class="form-select form-select-sm" required>
                                                @foreach ($categoryOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected($selected === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-text">Detectada: {{ $categoryOptions[$classification->categoria_detectada] ?? $classification->categoria_detectada }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="modal-footer justify-content-between">
                <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary">Revisar más tarde</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle"></i> Confirmar y encolar MAE
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('maeClassificationModal');
    if (modalElement && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
@endpush
