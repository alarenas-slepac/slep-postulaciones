@extends('layouts.app')

@section('content')
    @include('remuneraciones.descuentos-cgr._styles')
    @php $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']; @endphp
    <div class="cgr-page">
        <div class="cgr-page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div><div class="cgr-page-header__eyebrow"><span class="cgr-page-header__icon"><i class="bi bi-currency-exchange" aria-hidden="true"></i></span> Remuneraciones · Descuentos CGR</div><h1 class="mb-2">Valores UTM</h1><p class="mb-0">Un único valor por mes y año para calcular cronogramas CGR.</p></div>
            <a href="{{ route('descuentos-cgr.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a Descuentos CGR</a>
        </div>

        @if (session('status')) <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger"><strong><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>No fue posible guardar:</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-pencil-square me-2 text-primary" aria-hidden="true"></i>Ingreso individual</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('descuentos-cgr.utm.store') }}" class="row g-3 align-items-end">
                            @csrf
                            <div class="col-sm-4"><label class="form-label" for="nuevo_anio">Año</label><input id="nuevo_anio" type="number" name="anio" min="2000" max="2100" class="form-control @error('anio') is-invalid @enderror" value="{{ old('anio', now()->year) }}" required>@error('anio') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-sm-4"><label class="form-label" for="nuevo_mes">Mes</label><select id="nuevo_mes" name="mes" class="form-select @error('mes') is-invalid @enderror" required>@foreach ($meses as $numero => $nombre)<option value="{{ $numero }}" @selected((int) old('mes', now()->month) === $numero)>{{ $nombre }}</option>@endforeach</select>@error('mes') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-sm-4"><label class="form-label" for="nuevo_valor">Valor 1 UTM</label><div class="input-group"><span class="input-group-text">$</span><input id="nuevo_valor" type="number" name="valor" min="0.01" step="0.01" class="form-control @error('valor') is-invalid @enderror" value="{{ old('valor') }}" required></div>@error('valor') <div class="text-danger small mt-1">{{ $message }}</div> @enderror</div>
                            <div class="col-12 d-grid"><button class="btn btn-primary">Guardar valor UTM</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-upload me-2 text-primary" aria-hidden="true"></i>Ingreso masivo</div>
                    <div class="card-body">
                        <p class="small text-muted">La importación es completa o no registra nada. Rechaza periodos duplicados en el archivo o ya existentes.</p>
                        <form method="POST" action="{{ route('descuentos-cgr.utm.importar') }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-12"><label for="archivo" class="form-label">Planilla Excel o CSV</label><input id="archivo" type="file" name="archivo" accept=".xlsx,.xls,.csv" class="form-control @error('archivo') is-invalid @enderror" required>@error('archivo') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importar</button><a href="{{ route('descuentos-cgr.utm.plantilla') }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Descargar plantilla</a></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="mb-2"><i class="bi bi-list-ul me-2 text-primary" aria-hidden="true"></i>Valores registrados</div>
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-sm-4"><label for="filtro_anio" class="form-label">Filtrar por año</label><select id="filtro_anio" name="anio" class="form-select"><option value="">Todos</option>@foreach ($anios as $opcion)<option value="{{ $opcion }}" @selected($anio === (int) $opcion)>{{ $opcion }}</option>@endforeach</select></div>
                    <div class="col-sm-auto"><button class="btn btn-outline-primary">Filtrar</button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Periodo</th><th>Valor UTM</th><th>Actualizado</th><th>Editar valor</th></tr></thead>
                    <tbody>
                        @forelse ($valores as $utmValor)
                            <tr>
                                <td class="fw-semibold">{{ $meses[$utmValor->mes] }} {{ $utmValor->anio }}</td>
                                <td>${{ number_format((float) $utmValor->valor, 2, ',', '.') }}</td>
                                <td class="small text-muted">{{ $utmValor->updated_at->format('d-m-Y H:i') }}{{ $utmValor->actualizadoPor ? ' · ' . $utmValor->actualizadoPor->name : '' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('descuentos-cgr.utm.update', $utmValor) }}" class="row g-2 align-items-end">
                                        @csrf @method('PUT')
                                        <div class="col-auto"><label class="form-label small" for="utm-anio-{{ $utmValor->id }}">Año</label><input id="utm-anio-{{ $utmValor->id }}" type="number" name="anio" min="2000" max="2100" class="form-control form-control-sm" value="{{ $utmValor->anio }}" style="width: 6.5rem" required></div>
                                        <div class="col-auto"><label class="form-label small" for="utm-mes-{{ $utmValor->id }}">Mes</label><select id="utm-mes-{{ $utmValor->id }}" name="mes" class="form-select form-select-sm" required>@foreach ($meses as $numero => $nombre)<option value="{{ $numero }}" @selected($utmValor->mes === $numero)>{{ $nombre }}</option>@endforeach</select></div>
                                        <div class="col-auto"><label class="form-label small" for="utm-valor-{{ $utmValor->id }}">Valor UTM</label><div class="input-group input-group-sm"><span class="input-group-text">$</span><input id="utm-valor-{{ $utmValor->id }}" type="number" name="valor" min="0.01" step="0.01" class="form-control" value="{{ $utmValor->valor }}" required></div></div>
                                        <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Guardar</button></div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="cgr-empty"><i class="bi bi-inbox" aria-hidden="true"></i>No hay valores UTM registrados.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($valores->hasPages()) <div class="card-footer">{{ $valores->links() }}</div> @endif
        </div>
    </div>
@endsection
