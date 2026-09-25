@extends('layouts.app')

@section('content')
    @include('remuneraciones.descuentos-cgr._styles')
    @php $puedeRegistrar = auth()->user()?->hasAnyRole(['admin', 'funcionario_slep']) ?? false; @endphp
    <div class="cgr-page">
        <div class="cgr-page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <div class="cgr-page-header__eyebrow"><span class="cgr-page-header__icon"><i class="bi bi-bank" aria-hidden="true"></i></span> Remuneraciones · Contraloría</div>
                <h1 class="mb-2">Descuentos CGR</h1>
                <p class="mb-0">Resoluciones de Contraloría y cronogramas de descuento.</p>
            </div>
            <div class="cgr-page-actions d-flex flex-wrap gap-2">
                @if (auth()->user()?->hasRole('admin')) <a href="{{ route('descuentos-cgr.notificaciones.index') }}" class="btn btn-outline-primary"><i class="bi bi-bell me-1"></i>Notificaciones</a> @endif
                @if ($puedeRegistrar)
                    <a href="{{ route('descuentos-cgr.utm.index') }}" class="btn btn-outline-primary"><i class="bi bi-currency-exchange me-1"></i>Valores UTM</a>
                    <a href="{{ route('descuentos-cgr.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nuevo descuento</a>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success d-flex gap-2"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
        @endif

        @php
            $pestanas = [
                'ingresado' => 'Registros CGR: Ingresados',
                'descuentos_realizados' => 'Registros CGR: En registro de Finanzas',
                'en_auditoria' => 'Registros CGR: En gestión de Auditoría',
                'cerrado' => 'Registros CGR: Finalizados',
            ];
        @endphp
        <nav class="nav nav-pills cgr-tabs flex-wrap gap-2 mb-4" aria-label="Etapas de Descuentos CGR">
            @foreach ($pestanas as $clave => $etiqueta)
                <a class="nav-link {{ $estado === $clave ? 'active' : '' }}" href="{{ route('descuentos-cgr.index', ['estado' => $clave]) }}" @if ($estado === $clave) aria-current="page" @endif>{{ $etiqueta }} <span class="badge {{ $estado === $clave ? 'text-bg-light' : 'text-bg-secondary' }} ms-1">{{ $conteos[$clave] ?? 0 }}</span></a>
            @endforeach
        </nav>

        @php
            $tarjetas = [
                ['icono' => 'bi-files', 'etiqueta' => 'Registros de la etapa', 'valor' => number_format($indicadores['registros'], 0, ',', '.'), 'ayuda' => 'Total de registros según los filtros de esta pestaña.'],
                ['icono' => 'bi-cash-stack', 'etiqueta' => 'Deuda definitiva original', 'valor' => '$'.number_format($indicadores['deuda_pesos'], 0, ',', '.'), 'ayuda' => 'Suma nominal de la deuda registrada en estos expedientes.'],
            ];
            if ($estado === 'ingresado') {
                $tarjetas[] = ['icono' => 'bi-file-earmark-pdf', 'etiqueta' => 'Liquidaciones cargadas', 'valor' => number_format($indicadores['documentos'], 0, ',', '.').' / '.number_format($indicadores['cuotas'], 0, ',', '.'), 'ayuda' => 'Cuotas con liquidación respecto de las cuotas programadas.'];
                $tarjetas[] = ['icono' => 'bi-send', 'etiqueta' => 'Listos para Finanzas', 'valor' => number_format($indicadores['listos'], 0, ',', '.'), 'ayuda' => 'Registros con liquidación en todas sus cuotas.'];
            } elseif ($estado === 'descuentos_realizados') {
                $tarjetas[] = ['icono' => 'bi-receipt', 'etiqueta' => 'Comprobantes asociados', 'valor' => number_format($indicadores['documentos'], 0, ',', '.').' / '.number_format($indicadores['cuotas'] * 2, 0, ',', '.'), 'ayuda' => 'Una asociación SIGFE y una TGR por cuota.'];
                $tarjetas[] = ['icono' => 'bi-send', 'etiqueta' => 'Listos para Auditoría', 'valor' => number_format($indicadores['listos'], 0, ',', '.'), 'ayuda' => 'Registros con ambos comprobantes en todas sus cuotas.'];
            } elseif ($estado === 'en_auditoria') {
                $tarjetas[] = ['icono' => 'bi-file-earmark-check', 'etiqueta' => 'Liquidaciones validadas', 'valor' => number_format($indicadores['documentos'], 0, ',', '.').' / '.number_format($indicadores['cuotas'], 0, ',', '.'), 'ayuda' => 'Cuotas con liquidación validada.'];
                $tarjetas[] = ['icono' => 'bi-patch-check', 'etiqueta' => 'Certificados firmados', 'valor' => number_format($indicadores['firmados'], 0, ',', '.'), 'ayuda' => 'Registros con certificado PDF firmado cargado.'];
            } else {
                $tarjetas[] = ['icono' => 'bi-calendar-check', 'etiqueta' => 'Cuotas finalizadas', 'valor' => number_format($indicadores['cuotas'], 0, ',', '.'), 'ayuda' => 'Cuotas programadas en los registros cerrados.'];
                $tarjetas[] = ['icono' => 'bi-check-circle', 'etiqueta' => 'Cerrados este mes', 'valor' => number_format($indicadores['cierres_mes'], 0, ',', '.'), 'ayuda' => 'Cierres registrados durante '.now()->format('m-Y').'.'];
            }
        @endphp
        <section class="row g-3 mb-4" aria-label="Indicadores de {{ $pestanas[$estado] }}">
            @foreach ($tarjetas as $tarjeta)
                <div class="col-sm-6 col-xl-3">
                    <div class="card cgr-kpi h-100">
                        <div class="card-body">
                            <div class="cgr-kpi__label"><i class="bi {{ $tarjeta['icono'] }} me-1" aria-hidden="true"></i>{{ $tarjeta['etiqueta'] }}</div>
                            <div class="cgr-kpi__value text-break">{{ $tarjeta['valor'] }}</div>
                            <p class="small text-muted mb-0">{{ $tarjeta['ayuda'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-funnel me-2 text-primary" aria-hidden="true"></i>Filtros de {{ $pestanas[$estado] }}</div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="estado" value="{{ $estado }}">
                    <input type="hidden" name="filtrar" value="1">
                    <div class="col-md-4">
                        <label for="buscar" class="form-label">Buscar</label>
                        <input id="buscar" name="buscar" class="form-control" value="{{ $buscar }}" placeholder="Nombre o RUT">
                    </div>
                    <div class="col-md-4">
                        <label for="origen" class="form-label">Tipo de funcionario</label>
                        <select id="origen" name="origen" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($origenes as $valorOrigen => $etiquetaOrigen)
                                <option value="{{ $valorOrigen }}" @selected($origen === $valorOrigen)>{{ $etiquetaOrigen }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="anio" class="form-label">Año primer descuento</label>
                        <select id="anio" name="anio" class="form-select">
                            <option value="">Todos</option>
                            @foreach ($anios as $opcion)
                                <option value="{{ $opcion }}" @selected($anio === (int) $opcion)>{{ $opcion }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="ultimo_mes" class="form-label">Último mes de descuento</label>
                        <input id="ultimo_mes" type="month" name="ultimo_mes" class="form-control {{ isset($errors) && $errors->has('ultimo_mes') ? 'is-invalid' : '' }}" value="{{ $ultimoMes }}">
                        @if (isset($errors) && $errors->has('ultimo_mes')) <div class="invalid-feedback">{{ $errors->first('ultimo_mes') }}</div> @endif
                        <div class="form-text">Se calcula desde el primer mes y el número de cuotas.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="mes_descuento" class="form-label">Mes de descuento</label>
                        <input id="mes_descuento" type="month" name="mes_descuento" class="form-control {{ isset($errors) && $errors->has('mes_descuento') ? 'is-invalid' : '' }}" value="{{ $mesDescuento }}">
                        @if (isset($errors) && $errors->has('mes_descuento')) <div class="invalid-feedback">{{ $errors->first('mes_descuento') }}</div> @endif
                        <div class="form-text">Incluye registros con una cuota programada en ese mes.</div>
                    </div>
                    <div class="col-md-4 d-flex flex-wrap gap-2 align-items-start">
                        <button class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                        @if ($filtrosActivos)
                            <a href="{{ route('descuentos-cgr.index', ['estado' => $estado, 'limpiar' => 1]) }}" class="btn btn-outline-secondary">Limpiar filtros</a>
                        @endif
                    </div>
                </form>

                @if ($puedeRegistrar)
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
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap"><span><i class="bi bi-list-ul me-2 text-primary" aria-hidden="true"></i>{{ $pestanas[$estado] }}</span><span class="small text-muted">{{ $descuentos->total() }} registro(s)</span></div>
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
                            <th>Último descuento</th>
                            <th>Estado</th>
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
                                <td>{{ $descuento->fecha_primer_descuento->copy()->addMonthsNoOverflow(max(0, $descuento->numero_cuotas - 1))->format('m-Y') }}</td>
                                <td><span class="badge {{ match($descuento->estadoActual()) { 'cerrado' => 'text-bg-success', 'en_auditoria' => 'text-bg-info', 'descuentos_realizados' => 'text-bg-warning', default => 'text-bg-primary' } }}">{{ $descuento->etiquetaEstado() }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <a href="{{ route('descuentos-cgr.show', $descuento) }}" class="btn btn-sm btn-outline-primary" aria-label="Ver cronograma de {{ $descuento->nombre }}"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>Ver</a>
                                    @if ($puedeRegistrar && $descuento->estadoActual() === 'ingresado')
                                    <a href="{{ route('descuentos-cgr.edit', $descuento) }}" class="btn btn-sm btn-outline-secondary" aria-label="Editar descuento de {{ $descuento->nombre }}"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Editar</a>
                                    <form method="POST" action="{{ route('descuentos-cgr.destroy', $descuento) }}" class="d-inline" onsubmit="return confirm('Se eliminará el descuento CGR, su cronograma y la resolución PDF asociada. Esta acción no se puede deshacer. ¿Deseas continuar?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Eliminar descuento de {{ $descuento->nombre }}"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                                    </form>
                                    @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="cgr-empty"><i class="bi bi-inbox" aria-hidden="true"></i>No hay descuentos CGR registrados para los filtros seleccionados.</div></td></tr>
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
