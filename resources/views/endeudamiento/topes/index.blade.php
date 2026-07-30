@extends('layouts.app')

@section('content')
<div class="container py-4">
    @if ($errors->has('general'))
        <div class="alert alert-danger shadow-sm">{{ $errors->first('general') }}</div>
    @endif

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Topes normativos de endeudamiento</h1>
            <p class="text-muted mb-0">Análisis operativo por 45% del total haberes, con prelación de descuentos y exportación Excel de columnas aplicables y eliminables.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary">Cargas</a>
            <a href="{{ route('endeudamiento.registros.index') }}" class="btn btn-outline-secondary">Registros</a>
            <a href="{{ route('endeudamiento.cuotas.index') }}" class="btn btn-outline-success">Cuotas</a>
            <a href="{{ route('endeudamiento.normativa.index') }}" class="btn btn-outline-secondary">Topes normativos</a>
        </div>
    </div>

    <div class="alert alert-warning shadow-sm">
        <div class="fw-semibold mb-1">Criterio operativo actual</div>
        <div class="small">
            Base de cálculo: <strong>TOTAL HABERES</strong>. Se calcula el <strong>45%</strong> y se compara contra la suma de todos los descuentos,
            excluyendo solo los <strong>patronales</strong>. Si el total supera ese máximo, se mantiene la prelación:
            <strong>imposiciones, salud, impuesto, cesantía, judiciales, administrativo, reintegros, APV, sindical, ahorro, créditos</strong>
            (Ahorrocoop, Coopeuch, Oriencoop, Préstamos Fonasa, Caja 18 y Caja Los Andes) y luego el resto.
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Año</label>
                    <select name="anio" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($options['anios'] as $anio)
                            <option value="{{ $anio }}" @selected((string) $filters['anio'] === (string) $anio)>{{ $anio }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mes</label>
                    <select name="mes" class="form-select">
                        <option value="">Todos</option>
                        @for ($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) $filters['mes'] === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Dominio</label>
                    <select name="dominio" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($options['dominios'] as $dominio)
                            <option value="{{ $dominio }}" @selected($filters['dominio'] === $dominio)>{{ $dominio }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Carga específica</label>
                    <select name="carga_id" class="form-select">
                        <option value="">Usar vigentes / filtros</option>
                        @foreach ($options['cargas'] as $carga)
                            <option value="{{ $carga->id }}" @selected((int) $filters['carga_id'] === (int) $carga->id)>
                                {{ sprintf('%02d/%04d', $carga->mes, $carga->anio) }} - {{ $carga->dominio }} - v{{ $carga->version }}{{ $carga->es_vigente ? ' (vigente)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="dentro_tope" @selected($filters['estado'] === 'dentro_tope')>Dentro de tope</option>
                        <option value="con_exceso" @selected($filters['estado'] === 'con_exceso')>Con exceso</option>
                        <option value="revision" @selected($filters['estado'] === 'revision')>Revisión</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">RUT-DV</label>
                    <input type="text" name="rut" value="{{ $filters['rut'] }}" class="form-control" placeholder="Ej. 15881749">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" value="{{ $filters['nombre'] }}" class="form-control" placeholder="Buscar nombre...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Filtro libre</label>
                    <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Opcional">
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4 pt-2">
                        <input class="form-check-input" type="checkbox" name="solo_vigentes" value="1" id="solo_vigentes" @checked($filters['solo_vigentes'])>
                        <label class="form-check-label" for="solo_vigentes">Solo cargas vigentes cuando no se selecciona carga</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end align-items-end">
                    <a href="{{ route('endeudamiento.topes.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    <button class="btn btn-primary">Calcular</button>
                    @if ($result)
                        <a href="{{ route('endeudamiento.topes.export', request()->query()) }}" class="btn btn-success">Exportar Excel</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($result)
        <div class="alert alert-info shadow-sm">
            <strong>Resumen completo del filtro:</strong> los totales superiores se calculan sobre todos los registros que cumplen el filtro aplicado, no solo sobre la página visible. El detalle ahora se revisa por registro en la acción <strong>Ver</strong>.
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Registros</div><div class="h4 mb-0">{{ number_format($result['summary']['registros'], 0, ',', '.') }}</div></div></div></div>
            <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Total haberes</div><div class="h6 mb-0">${{ number_format($result['summary']['base_calculo'], 0, ',', '.') }}</div></div></div></div>
            <div class="col-md-2"><div class="card shadow-sm border-primary"><div class="card-body"><div class="text-muted small">Máximo 45%</div><div class="h6 mb-0 text-primary">${{ number_format($result['summary']['monto_maximo_endeudamiento'], 0, ',', '.') }}</div></div></div></div>
            <div class="col-md-2"><div class="card shadow-sm border-dark"><div class="card-body"><div class="text-muted small">Total descuentos</div><div class="h6 mb-0">${{ number_format($result['summary']['total_descuentos'], 0, ',', '.') }}</div></div></div></div>
            <div class="col-md-2"><div class="card shadow-sm border-danger"><div class="card-body"><div class="text-muted small">Monto excedido</div><div class="h6 mb-0 text-danger">${{ number_format($result['summary']['monto_excedido'], 0, ',', '.') }}</div></div></div></div>
            <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">% prom. descuento</div><div class="h6 mb-0">{{ number_format($result['summary']['porcentaje_total_descuento_promedio'], 2, ',', '.') }}%</div></div></div></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card shadow-sm border-secondary"><div class="card-body"><div class="text-muted small">Patronales excluidos</div><div class="h5 mb-0">${{ number_format($result['summary']['patronal'], 0, ',', '.') }}</div></div></div></div>
            <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Con exceso</div><div class="h5 mb-0">{{ $result['summary']['con_exceso'] }}</div></div></div></div>
            <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Con revisión</div><div class="h5 mb-0">{{ $result['summary']['con_revision'] }}</div></div></div></div>
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
                            <th>Total haberes</th>
                            <th>Máx. 45%</th>
                            <th>Total descuentos</th>
                            <th>% descuento</th>
                            <th>Monto excedido</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result['paginator'] as $item)
                            <tr>
                                <td>{{ sprintf('%02d/%04d', $item['registro']->mes, $item['registro']->anio) }}</td>
                                <td>{{ $item['registro']->dominio }}</td>
                                <td>{{ $item['registro']->rut_dv }}</td>
                                <td>
                                    <div>{{ $item['registro']->nombre_completo }}</div>
                                    <div class="small text-muted">v{{ $item['registro']->carga?->version }}{{ $item['registro']->carga?->es_vigente ? ' vigente' : '' }}</div>
                                </td>
                                <td>${{ number_format($item['base_calculo'], 0, ',', '.') }}</td>
                                <td class="text-primary">${{ number_format($item['monto_maximo_endeudamiento'], 0, ',', '.') }}</td>
                                <td>${{ number_format($item['total_descuentos'], 0, ',', '.') }}</td>
                                <td>{{ number_format($item['porcentaje_total_descuento'], 2, ',', '.') }}%</td>
                                <td class="text-danger">${{ number_format($item['monto_excedido'], 0, ',', '.') }}</td>
                                <td>
                                    @if ($item['estado'] === 'cumple')
                                        <span class="badge text-bg-success">Dentro de tope</span>
                                    @elseif ($item['estado'] === 'excede_tope')
                                        <span class="badge text-bg-danger">Con exceso</span>
                                    @else
                                        <span class="badge text-bg-warning">Revisión</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('endeudamiento.topes.show', array_merge(request()->query(), ['maeRegistro' => $item['registro']->id])) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted py-4">No hay registros para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($result['paginator']->hasPages())
                <div class="card-body border-top">{{ $result['paginator']->links() }}</div>
            @endif
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-muted">
                Selecciona al menos un filtro y presiona <strong>Calcular</strong> para generar el análisis del 45% del total haberes. La vista ahora permite filtrar por <strong>estado</strong>, <strong>RUT-DV</strong> y <strong>nombre</strong>, y revisar el detalle en la acción <strong>Ver</strong>.
            </div>
        </div>
    @endif
</div>
@endsection
