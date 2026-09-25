@extends('layouts.app')

@section('content')
    @include('remuneraciones.descuentos-cgr._styles')
    <div class="cgr-page">
        <div class="cgr-page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <div class="cgr-page-header__eyebrow"><span class="cgr-page-header__icon"><i class="bi bi-bank" aria-hidden="true"></i></span> Remuneraciones · Contraloría</div>
                <h1 class="mb-2">Descuentos CGR</h1>
                <p class="mb-0">Resoluciones de Contraloría y cronogramas de descuento.</p>
            </div>
            <div class="cgr-page-actions d-flex flex-wrap gap-2">
                <a href="{{ route('descuentos-cgr.utm.index') }}" class="btn btn-outline-primary"><i class="bi bi-currency-exchange me-1"></i>Valores UTM</a>
                <a href="{{ route('descuentos-cgr.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nuevo descuento</a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success d-flex gap-2"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
        @endif

        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-funnel me-2 text-primary" aria-hidden="true"></i>Buscar descuentos</div>
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
                <div class="fw-semibold mb-3"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary" aria-hidden="true"></i>Exportación mensual</div>

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

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap"><span><i class="bi bi-list-ul me-2 text-primary" aria-hidden="true"></i>Registros CGR</span><span class="small text-muted">{{ $descuentos->total() }} registro(s)</span></div>
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
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <a href="{{ route('descuentos-cgr.show', $descuento) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver cronograma de {{ $descuento->nombre }}"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Ver</a>
                                    <a href="{{ route('descuentos-cgr.edit', $descuento) }}" class="btn btn-sm btn-outline-secondary" aria-label="Editar descuento de {{ $descuento->nombre }}"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar</a>
                                    <form method="POST" action="{{ route('descuentos-cgr.destroy', $descuento) }}" class="d-inline" onsubmit="return confirm('Se eliminará el descuento CGR, su cronograma y la resolución PDF asociada. Esta acción no se puede deshacer. ¿Deseas continuar?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Eliminar descuento de {{ $descuento->nombre }}"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="cgr-empty"><i class="bi bi-inbox" aria-hidden="true"></i>No hay descuentos CGR registrados para los filtros seleccionados.</div></td></tr>
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
