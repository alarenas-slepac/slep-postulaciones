@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Cargas MAE de endeudamiento</h1>
            <p class="text-muted mb-0">Versiones cargadas por mes, año y dominio. Solo una versión queda vigente por período/dominio.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endeudamiento.cuotas.index') }}" class="btn btn-outline-success"><i class="bi bi-list-ol"></i> Complementar cuotas</a>
            <a href="{{ route('endeudamiento.topes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-calculator"></i> Cálculo de topes</a>
            <a href="{{ route('endeudamiento.cargas.create') }}" class="btn btn-primary"><i class="bi bi-upload"></i> Nueva carga MAE</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="alert alert-info">Cada archivo MAE requiere confirmar las categorías de descuento antes de entrar a la cola de procesamiento. Luego, asegúrate de mantener activo el worker de Laravel.</div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Año</label>
                    <select name="anio" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($anios as $anioOpt)
                            <option value="{{ $anioOpt }}" @selected((string) $anio === (string) $anioOpt)>{{ $anioOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mes</label>
                    <select name="mes" class="form-select">
                        <option value="">Todos</option>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected($mes === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Dominio</label>
                    <select name="dominio" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($dominios as $dominioOpt)
                            <option value="{{ $dominioOpt }}" @selected($dominio === $dominioOpt)>{{ $dominioOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($estados as $estadoOpt)
                            <option value="{{ $estadoOpt }}" @selected($estado === $estadoOpt)>{{ $estadoOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Período</th>
                        <th>Dominio</th>
                        <th>Comuna origen</th>
                        <th>Versión</th>
                        <th>Vigente</th>
                        <th>Estado</th>
                        <th>Archivo</th>
                        <th>Resumen</th>
                        <th>Usuario</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ sprintf('%02d/%04d', $item->mes, $item->anio) }}</td>
                            <td>{{ $item->dominio }}</td>
                            <td>{{ $item->comuna_origen ?: '—' }}</td>
                            <td>v{{ $item->version }}</td>
                            <td>
                                @if ($item->es_vigente)
                                    <span class="badge text-bg-success">Sí</span>
                                @else
                                    <span class="badge text-bg-secondary">Histórica</span>
                                @endif
                            </td>
                            <td><span class="badge text-bg-light border">{{ $item->estado }}</span></td>
                            <td>
                                <div class="small fw-semibold">{{ $item->nombre_archivo }}</div>
                                <div class="text-muted small">{{ $item->created_at?->format('d-m-Y H:i') }}</div>
                            </td>
                            <td class="small">
                                <div>Filas: {{ number_format($item->total_filas, 0, ',', '.') }}</div>
                                <div>Válidas: {{ number_format($item->filas_validas, 0, ',', '.') }}</div>
                                <div>Obs.: {{ number_format($item->filas_observadas, 0, ',', '.') }}</div>
                            </td>
                            <td>{{ $item->subidaPor?->display_name ?? $item->subidaPor?->nombre_completo ?? '—' }}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    @if ($item->estado === 'pendiente_revision')
                                        <a href="{{ route('endeudamiento.cargas.clasificaciones', $item) }}" class="btn btn-sm btn-warning">Revisar categorías</a>
                                    @endif
                                    @if (in_array($item->estado, ['procesado', 'procesado_con_observaciones']))
                                        <a href="{{ route('endeudamiento.cuotas.create', ['carga_id' => $item->id]) }}" class="btn btn-sm btn-outline-success">Cuotas</a>
                                    @endif
                                    <a href="{{ route('endeudamiento.cargas.show', $item) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Aún no existen cargas MAE de endeudamiento.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="card-body border-top">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
