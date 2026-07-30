@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Registros MAE de endeudamiento</h1>
            <p class="text-muted mb-0">Consulta por período, dominio y versión vigente del MAE cargado.</p>
        </div>
        <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary">Volver a cargas</a>
    </div>

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
                    <label class="form-label">RUT-DV / nombre</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100">Filtrar</button>
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="solo_vigentes" value="1" id="solo_vigentes" @checked($soloVigentes)>
                        <label class="form-check-label" for="solo_vigentes">Solo versión vigente</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="con_otros" value="1" id="con_otros" @checked($conOtros)>
                        <label class="form-check-label" for="con_otros">Solo con otros descuentos</label>
                    </div>
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
                        <th>RUT-DV</th>
                        <th>Nombre</th>
                        <th>Imp.</th>
                        <th>Trib.</th>
                        <th>Desc. hom.</th>
                        <th>Aporte pat.</th>
                        <th>Otros</th>
                        <th>Carga</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ sprintf('%02d/%04d', $item->mes, $item->anio) }}</td>
                            <td>{{ $item->dominio }}</td>
                            <td>{{ $item->rut_dv }}</td>
                            <td>{{ $item->nombre_completo }}</td>
                            <td>{{ number_format((float) $item->monto_imponible, 0, ',', '.') }}</td>
                            <td>{{ number_format((float) $item->monto_tributable, 0, ',', '.') }}</td>
                            <td>{{ number_format((float) $item->total_descuentos_homologados, 0, ',', '.') }}</td>
                            <td>{{ number_format((float) $item->total_aportes_patronales, 0, ',', '.') }}</td>
                            <td>{{ number_format((float) $item->total_otros_descuentos, 0, ',', '.') }}</td>
                            <td>
                                <div class="small">v{{ $item->carga?->version }}</div>
                                @if ($item->carga?->es_vigente)
                                    <span class="badge text-bg-success">Vigente</span>
                                @endif
                            </td>
                            <td class="text-end"><a href="{{ route('endeudamiento.registros.show', $item) }}" class="btn btn-sm btn-outline-primary">Detalle</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">No se encontraron registros para los filtros aplicados.</td></tr>
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
