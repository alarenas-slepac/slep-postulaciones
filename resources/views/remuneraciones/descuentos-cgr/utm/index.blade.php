@extends('layouts.app')

@section('content')
    @php $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']; @endphp
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div><h1 class="h3 mb-1"><i class="bi bi-currency-exchange me-2"></i>Valores UTM</h1><p class="text-muted mb-0">Un único valor por mes y año para calcular cronogramas CGR.</p></div>
            <a href="{{ route('descuentos-cgr.index') }}" class="btn btn-outline-secondary">Volver a Descuentos CGR</a>
        </div>

        @if (session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger"><strong>No fue posible guardar:</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Ingreso individual</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('descuentos-cgr.utm.store') }}" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-sm-4"><label class="form-label" for="nuevo_anio">Año</label><input id="nuevo_anio" type="number" name="anio" min="2000" max="2100" class="form-control" value="{{ old('anio', now()->year) }}" required></div>
                            <div class="col-sm-4"><label class="form-label" for="nuevo_mes">Mes</label><select id="nuevo_mes" name="mes" class="form-select" required>@foreach ($meses as $numero => $nombre)<option value="{{ $numero }}" @selected((int) old('mes', now()->month) === $numero)>{{ $nombre }}</option>@endforeach</select></div>
                            <div class="col-sm-4"><label class="form-label" for="nuevo_valor">Valor 1 UTM</label><div class="input-group"><span class="input-group-text">$</span><input id="nuevo_valor" type="number" name="valor" min="0.01" step="0.01" class="form-control" value="{{ old('valor') }}" required></div></div>
                            <div class="col-12 d-grid"><button class="btn btn-primary">Guardar valor UTM</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Ingreso masivo</div>
                    <div class="card-body">
                        <p class="small text-muted">La importación es completa o no registra nada. Rechaza periodos duplicados en el archivo o ya existentes.</p>
                        <form method="POST" action="{{ route('descuentos-cgr.utm.importar') }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-12"><label for="archivo" class="form-label">Planilla Excel o CSV</label><input id="archivo" type="file" name="archivo" accept=".xlsx,.xls,.csv" class="form-control" required></div>
                            <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importar</button><a href="{{ route('descuentos-cgr.utm.plantilla') }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Descargar plantilla</a></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-sm-4"><label for="filtro_anio" class="form-label">Filtrar por año</label><select id="filtro_anio" name="anio" class="form-select"><option value="">Todos</option>@foreach ($anios as $opcion)<option value="{{ $opcion }}" @selected($anio === (int) $opcion)>{{ $opcion }}</option>@endforeach</select></div>
                    <div class="col-sm-auto"><button class="btn btn-outline-primary">Filtrar</button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Periodo</th><th>Valor UTM</th><th>Actualizado</th><th>Editar</th></tr></thead>
                    <tbody>
                        @forelse ($valores as $utmValor)
                            <tr>
                                <td class="fw-semibold">{{ $meses[$utmValor->mes] }} {{ $utmValor->anio }}</td>
                                <td>${{ number_format((float) $utmValor->valor, 2, ',', '.') }}</td>
                                <td class="small text-muted">{{ $utmValor->updated_at->format('d-m-Y H:i') }}{{ $utmValor->actualizadoPor ? ' · ' . $utmValor->actualizadoPor->name : '' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('descuentos-cgr.utm.update', $utmValor) }}" class="row g-2 align-items-center">
                                        @csrf @method('PUT')
                                        <div class="col-auto"><input type="number" name="anio" min="2000" max="2100" class="form-control form-control-sm" value="{{ $utmValor->anio }}" aria-label="Año" style="width: 6.5rem" required></div>
                                        <div class="col-auto"><select name="mes" class="form-select form-select-sm" aria-label="Mes" required>@foreach ($meses as $numero => $nombre)<option value="{{ $numero }}" @selected($utmValor->mes === $numero)>{{ $nombre }}</option>@endforeach</select></div>
                                        <div class="col-auto"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" name="valor" min="0.01" step="0.01" class="form-control" value="{{ $utmValor->valor }}" aria-label="Nuevo valor UTM para {{ $meses[$utmValor->mes] }} de {{ $utmValor->anio }}" required></div></div>
                                        <div class="col-auto"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Guardar</button></div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-5">No hay valores UTM registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($valores->hasPages()) <div class="card-footer">{{ $valores->links() }}</div> @endif
        </div>
    </div>
@endsection
