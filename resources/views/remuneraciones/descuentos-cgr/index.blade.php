@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-bank me-2"></i>Descuentos CGR</h1>
                <p class="text-muted mb-0">Resoluciones de Contraloría y cronogramas de descuento.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('descuentos-cgr.utm.index') }}" class="btn btn-outline-primary"><i class="bi bi-currency-exchange me-1"></i>Valores UTM</a>
                <a href="{{ route('descuentos-cgr.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nuevo descuento</a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="buscar" class="form-label">Buscar</label>
                        <input id="buscar" name="buscar" class="form-control" value="{{ $buscar }}" placeholder="Nombre o RUT">
                    </div>
                    <div class="col-md-3">
                        <label for="origen" class="form-label">Tipo de funcionario</label>
                        <select id="origen" name="origen" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($origenes as $valorOrigen => $etiquetaOrigen)
                                <option value="{{ $valorOrigen }}" @selected($origen === $valorOrigen)>{{ $etiquetaOrigen }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="anio" class="form-label">Año primer descuento</label>
                        <select id="anio" name="anio" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($anios as $opcion)
                                <option value="{{ $opcion }}" @selected($anio === (int) $opcion)>{{ $opcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                    </div>
                </form>

                <hr class="my-4">

                <form method="GET" action="{{ route('descuentos-cgr.index') }}" class="row g-3 align-items-end">
                    <input type="hidden" name="exportar" value="1">
                    <div class="col-md-4">
                        <label for="mes_exportacion" class="form-label">Mes de descuentos a exportar</label>
                        <input id="mes_exportacion" type="month" name="mes_exportacion" class="form-control {{ isset($errors) && $errors->has('mes_exportacion') ? 'is-invalid' : '' }}" value="{{ old('mes_exportacion', now()->format('Y-m')) }}" required>
                        @if (isset($errors) && $errors->has('mes_exportacion'))
                            <div class="invalid-feedback">{{ $errors->first('mes_exportacion') }}</div>
                        @endif
                    </div>
                    <div class="col-md-5">
                        <div class="form-text mb-2">Incluye todos los descuentos con una cuota aplicada durante el mes seleccionado.</div>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel mensual</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Persona</th>
                            <th>Tipo de funcionario</th>
                            <th>Resolución</th>
                            <th class="text-end">Deuda</th>
                            <th class="text-end">Cuota UTM</th>
                            <th>Primer descuento</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($descuentos as $descuento)
                            <tr>
                                <td><strong>{{ $descuento->nombre }}</strong><br><span class="text-muted small">{{ \App\Support\Rut::format($descuento->rut) }}</span></td>
                                <td>
                                    @php
                                        $etiquetaOrigen = $origenes[$descuento->origen_funcionario] ?? 'Sin clasificar';
                                        $claseOrigen = match ($descuento->origen_funcionario) {
                                            \App\Services\Remuneraciones\ReemplazoPersonalRutService::ORIGEN_ADMINISTRACION_CENTRAL => 'text-bg-primary',
                                            \App\Services\Remuneraciones\ReemplazoPersonalRutService::ORIGEN_ESTABLECIMIENTO => 'text-bg-success',
                                            default => 'text-bg-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $claseOrigen }}">{{ $etiquetaOrigen }}</span>
                                </td>
                                <td>{{ $descuento->numero_resolucion }}<br><span class="text-muted small">{{ $descuento->fecha_resolucion?->format('d-m-Y') ?? 'Sin fecha' }}</span></td>
                                <td class="text-end">${{ number_format($descuento->deuda_definitiva_pesos, 0, ',', '.') }}<br><span class="text-muted small">{{ number_format((float) $descuento->deuda_equivalente_utm, 4, ',', '.') }} UTM</span></td>
                                <td class="text-end">{{ number_format((float) $descuento->cuota_utm, 4, ',', '.') }}<br><span class="text-muted small">{{ $descuento->numero_cuotas }} cuotas</span></td>
                                <td>{{ $descuento->fecha_primer_descuento->translatedFormat('m-Y') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('descuentos-cgr.show', $descuento) }}" class="btn btn-sm btn-outline-primary" title="Ver cronograma"><i class="bi bi-calendar3"></i></a>
                                    <a href="{{ route('descuentos-cgr.edit', $descuento) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('descuentos-cgr.destroy', $descuento) }}" class="d-inline" onsubmit="return confirm('Se eliminará el descuento CGR, su cronograma y la resolución PDF asociada. Esta acción no se puede deshacer. ¿Deseas continuar?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">No hay descuentos CGR registrados para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($descuentos->hasPages())
                <div class="card-footer">{{ $descuentos->links() }}</div>
            @endif
        </div>
    </div>
@endsection
