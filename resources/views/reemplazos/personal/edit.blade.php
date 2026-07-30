@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-9">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h3 class="m-0">Editar registro del padrón</h3>
                    <div class="text-muted small">Reasigna el establecimiento del funcionario para el período {{ str_pad((string) $item->mes, 2, '0', STR_PAD_LEFT) }}/{{ $item->anio }}.</div>
                </div>
                <a href="{{ route('reemplazos.index', array_filter($returnFilters, fn($value) => $value !== null && $value !== '')) }}" class="btn btn-outline-secondary">
                    Volver al padrón
                </a>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Funcionario</div>
                            <div class="fw-semibold">{{ $item->nombre }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">RUT</div>
                            <div class="fw-semibold">{{ $item->rut }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">RBD actual</div>
                            <div class="fw-semibold">{{ $item->rbd }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Establecimiento actual</div>
                            <div class="fw-semibold">{{ $item->establecimiento?->nombre_establecimiento ?? 'Sin establecimiento asignado' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Fecha ingreso</div>
                            <div class="fw-semibold">{{ optional($item->fecha_ingreso)->format('d/m/Y') ?: '—' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Tipo contrato</div>
                            <div class="fw-semibold">{{ $item->tipocontrato ?: '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('reemplazos.personal.update', $item) }}">
                        @csrf
                        @method('PUT')

                        @foreach ($returnFilters as $key => $value)
                            @if ($value !== null && $value !== '')
                                <input type="hidden" name="return[{{ $key }}]" value="{{ $value }}">
                            @endif
                        @endforeach

                        <div class="mb-3">
                            <label for="establecimiento_id" class="form-label">Nuevo establecimiento</label>
                            <select name="establecimiento_id" id="establecimiento_id" class="form-select @error('establecimiento_id') is-invalid @enderror" required>
                                <option value="">Selecciona un establecimiento</option>
                                @foreach ($establecimientos as $establecimiento)
                                    <option value="{{ $establecimiento->id }}" @selected((int) old('establecimiento_id', $item->establecimiento_id) === (int) $establecimiento->id)>
                                        {{ $establecimiento->nombre_establecimiento }} (RBD {{ $establecimiento->rbd }})
                                    </option>
                                @endforeach
                            </select>
                            @error('establecimiento_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Al guardar se actualizará también el RBD del registro para mantener la coherencia del padrón.
                            </div>
                        </div>

                        <div class="alert alert-light border small mb-4">
                            Este cambio modifica solo el registro seleccionado del padrón mensual. No altera otros funcionarios ni la estructura del módulo.
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar cambio de establecimiento
                            </button>
                            <a href="{{ route('reemplazos.index', array_filter($returnFilters, fn($value) => $value !== null && $value !== '')) }}" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
