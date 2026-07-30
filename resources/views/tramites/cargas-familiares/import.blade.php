@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Carga masiva · Cargas Familiares Vigentes</h1>
        <div class="text-muted small">Importa el padrón de cargas vigentes y vincula automáticamente por RUN + DV del beneficiario contra users.rut.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('tramites.cargas-familiares.template') }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Descargar plantilla</a>
        <a href="{{ route('tramites.cargas-familiares.review.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>
</div>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Registros</div><div class="h3 mb-0">{{ number_format($stats['total'],0,',','.') }}</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Asociadas a users</div><div class="h3 mb-0">{{ number_format($stats['asociadas'],0,',','.') }}</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Sin asociar</div><div class="h3 mb-0">{{ number_format($stats['sin_asociar'],0,',','.') }}</div></div></div></div>
    <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Beneficiarios únicos</div><div class="h3 mb-0">{{ number_format($stats['beneficiarios'],0,',','.') }}</div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Reglas de importación</div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li>El importador acepta la plantilla normalizada y también hojas por comuna.</li>
                    <li>El vínculo se realiza con <strong>beneficiario_run + beneficiario_dv</strong> normalizado contra <strong>users.rut</strong>.</li>
                    <li>Si el usuario aún no existe, la carga queda con <strong>user_id NULL</strong> y se vinculará cuando el usuario ingrese al módulo.</li>
                    <li>Si el mismo registro se vuelve a cargar para igual período, comuna, beneficiario y causante, se actualiza.</li>
                    <li>Las hojas Resumen, Instrucciones, Validaciones y Códigos se omiten automáticamente.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Subir archivo Excel</div>
            <div class="card-body">
                <form method="POST" action="{{ route('tramites.cargas-familiares.import.store') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">Período de carga</label>
                        <input type="month" name="periodo_carga" class="form-control @error('periodo_carga') is-invalid @enderror" value="{{ old('periodo_carga', now()->format('Y-m')) }}" required>
                        <div class="form-text">Ejemplo: 2026-04 para ABRIL 2026.</div>
                        @error('periodo_carga')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Archivo de cargas vigentes (.xlsx o .xls)</label>
                        <input type="file" name="excel" class="form-control @error('excel') is-invalid @enderror" accept=".xlsx,.xls" required>
                        @error('excel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary"><i class="bi bi-upload"></i> Importar cargas vigentes</button>
                        <a href="{{ route('tramites.cargas-familiares.review.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>

        @if (session('import_summary'))
            @php($summary = session('import_summary'))
            <div class="card shadow-sm mt-3">
                <div class="card-header fw-semibold">Resultado de la última importación</div>
                <div class="card-body small">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3"><strong>Total filas:</strong> {{ number_format($summary['total_filas'] ?? 0,0,',','.') }}</div>
                        <div class="col-md-3"><strong>Nuevas:</strong> {{ number_format($summary['importadas'] ?? 0,0,',','.') }}</div>
                        <div class="col-md-3"><strong>Actualizadas:</strong> {{ number_format($summary['actualizadas'] ?? 0,0,',','.') }}</div>
                        <div class="col-md-3"><strong>Asociadas:</strong> {{ number_format($summary['asociadas_user'] ?? 0,0,',','.') }}</div>
                    </div>
                    @if (!empty($summary['hojas']))
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>Hoja</th><th>Filas</th><th>Nuevas</th><th>Actualizadas</th><th>Omitidas</th><th>Asociadas</th></tr></thead>
                                <tbody>
                                    @foreach ($summary['hojas'] as $hoja => $item)
                                        <tr><td>{{ $hoja }}</td><td>{{ $item['filas'] }}</td><td>{{ $item['importadas'] }}</td><td>{{ $item['actualizadas'] }}</td><td>{{ $item['omitidas'] }}</td><td>{{ $item['asociadas_user'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if (!empty($summary['observaciones']))
                        <div class="fw-semibold">Observaciones</div>
                        <ul class="mb-0">
                            @foreach (array_slice($summary['observaciones'], 0, 30) as $obs)
                                <li>{{ $obs }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
