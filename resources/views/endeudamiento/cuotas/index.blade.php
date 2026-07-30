@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Complementación de cuotas</h1>
            <p class="text-muted mb-0">Carga nóminas por período, dominio, versión MAE y descuento para incorporar el número de cuota al cálculo de endeudamiento.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cargas MAE</a>
            <a href="{{ route('endeudamiento.cuotas.create') }}" class="btn btn-primary"><i class="bi bi-file-earmark-arrow-up"></i> Complementar descuento</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

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
                            <option value="{{ $estadoOpt }}" @selected($estado === $estadoOpt)>{{ str_replace('_', ' ', $estadoOpt) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
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
                        <th>Versión</th>
                        <th>Descuento</th>
                        <th>Archivo</th>
                        <th>Resultado</th>
                        <th>Estado</th>
                        <th>Usuario</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ sprintf('%02d/%04d', $item->carga?->mes, $item->carga?->anio) }}</td>
                            <td>{{ $item->carga?->dominio ?: '—' }}</td>
                            <td>v{{ $item->carga?->version }}{{ $item->carga?->es_vigente ? ' vigente' : '' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->columna_origen }}</div>
                                <div class="small text-muted">{{ $item->columna_normalizada }}</div>
                            </td>
                            <td>
                                <div class="small fw-semibold">{{ $item->nombre_archivo }}</div>
                                <div class="small text-muted">{{ $item->created_at?->format('d-m-Y H:i') }}</div>
                            </td>
                            <td class="small">
                                <div>Filas: {{ number_format($item->total_filas, 0, ',', '.') }}</div>
                                <div class="text-success">Asociadas: {{ number_format($item->total_asociadas, 0, ',', '.') }}</div>
                                <div class="text-danger">Errores: {{ number_format($item->total_errores, 0, ',', '.') }}</div>
                            </td>
                            <td>
                                @if ($item->estado === 'procesado')
                                    <span class="badge text-bg-success">Procesado</span>
                                @elseif ($item->estado === 'procesado_con_errores')
                                    <span class="badge text-bg-warning">Con errores</span>
                                @elseif ($item->estado === 'fallido')
                                    <span class="badge text-bg-danger">Fallido</span>
                                @else
                                    <span class="badge text-bg-info">{{ $item->estado }}</span>
                                @endif
                            </td>
                            <td>{{ $item->creadoPor?->display_name ?? $item->creadoPor?->nombre_completo ?? '—' }}</td>
                            <td class="text-end"><a href="{{ route('endeudamiento.cuotas.show', $item) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">Todavía no existen complementaciones de cuotas.</td></tr>
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
