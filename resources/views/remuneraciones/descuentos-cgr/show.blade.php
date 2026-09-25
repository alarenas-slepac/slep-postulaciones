@extends('layouts.app')

@section('content')
    @include('remuneraciones.descuentos-cgr._styles')
    @php
        $pesos = fn ($valor) => $valor === null ? '—' : '$' . number_format((float) $valor, 0, ',', '.');
        $utm = fn ($valor) => number_format((float) $valor, 4, ',', '.');
        $tasaEsperada = round((float) $descuentoCgr->tasa_interes_anual / 12, 4);
    @endphp
    <div class="cgr-page">
        <div class="cgr-page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <div class="cgr-page-header__eyebrow"><span class="cgr-page-header__icon"><i class="bi bi-bank" aria-hidden="true"></i></span> Remuneraciones · Descuentos CGR</div>
                <h1 class="mb-2">Descuento CGR: {{ $descuentoCgr->nombre }}</h1>
                <p class="mb-0">{{ \App\Support\Rut::format($descuentoCgr->rut) }} · Resolución {{ $descuentoCgr->numero_resolucion }}</p>
            </div>
            <div class="cgr-page-actions d-flex flex-wrap gap-2">
                <a href="{{ route('descuentos-cgr.informe.pdf', $descuentoCgr) }}" class="btn btn-primary"><i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>Exportar informe PDF</a>
                <a href="{{ route('descuentos-cgr.pdf', $descuentoCgr) }}" target="_blank" rel="noopener" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Ver resolución</a>
                <a href="{{ route('descuentos-cgr.edit', $descuentoCgr) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
                <form method="POST" action="{{ route('descuentos-cgr.destroy', $descuentoCgr) }}" onsubmit="return confirm('Se eliminará el descuento CGR, su cronograma y la resolución PDF asociada. Esta acción no se puede deshacer. ¿Deseas continuar?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
                <a href="{{ route('descuentos-cgr.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver</a>
            </div>
        </div>

        @if (session('status')) <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div> @endif

        @if ($calculo['utm_faltantes'] !== [])
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i><strong>Cronograma pendiente:</strong> faltan valores UTM para {{ implode(', ', $calculo['utm_faltantes']) }}. Las columnas en pesos se completarán automáticamente al registrar esos periodos en el <a href="{{ route('descuentos-cgr.utm.index') }}" class="alert-link">mantenedor UTM</a>.</div>
        @endif
        @if (abs((float) $descuentoCgr->tasa_interes_mensual - $tasaEsperada) > 0.0001)
            <div class="alert alert-info"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>La tasa mensual informada ({{ number_format((float) $descuentoCgr->tasa_interes_mensual, 4, ',', '.') }}%) difiere de la tasa anual dividida por 12 ({{ number_format($tasaEsperada, 4, ',', '.') }}%). El cronograma respeta la tasa mensual de la resolución.</div>
        @endif
        @if ($calculo['saldo_final_utm'] > 0.00005)
            <div class="alert alert-danger"><i class="bi bi-exclamation-octagon me-2" aria-hidden="true"></i>Las {{ $descuentoCgr->numero_cuotas }} cuotas indicadas no extinguen la deuda: queda un saldo de {{ $utm($calculo['saldo_final_utm']) }} UTM. Revisa los datos de la resolución.</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3"><div class="card cgr-kpi h-100"><div class="card-body"><div class="cgr-kpi__label">Deuda definitiva</div><div class="cgr-kpi__value">{{ $pesos($descuentoCgr->deuda_definitiva_pesos) }}</div><div class="small text-muted">{{ $utm($descuentoCgr->deuda_equivalente_utm) }} UTM</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card cgr-kpi h-100"><div class="card-body"><div class="cgr-kpi__label">Cuota según resolución</div><div class="cgr-kpi__value">{{ $utm($descuentoCgr->cuota_utm) }} UTM</div><div class="small text-muted">{{ $descuentoCgr->numero_cuotas }} cuotas</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card cgr-kpi h-100"><div class="card-body"><div class="cgr-kpi__label">Interés</div><div class="cgr-kpi__value">{{ number_format((float) $descuentoCgr->tasa_interes_mensual, 4, ',', '.') }}% mensual</div><div class="small text-muted">{{ number_format((float) $descuentoCgr->tasa_interes_anual, 4, ',', '.') }}% anual</div></div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card cgr-kpi h-100"><div class="card-body"><div class="cgr-kpi__label">Primer descuento</div><div class="cgr-kpi__value">{{ $descuentoCgr->fecha_primer_descuento->format('m-Y') }}</div><div class="small text-muted">Cronograma mensual</div></div></div></div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap"><span><i class="bi bi-calendar3 me-2 text-primary" aria-hidden="true"></i>Cronograma de descuentos</span><span class="text-muted small">Montos en pesos redondeados visualmente</span></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-center align-middle">
                        <tr><th>N°</th><th>Mes</th><th>Valor UTM</th><th>Saldo inicial UTM</th><th>Capital UTM</th><th>Saldo final UTM</th><th>Saldo inicial $</th><th>Capital $</th><th>Interés mes $</th><th>Descuento total $</th><th>Documento</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($calculo['filas'] as $fila)
                            <tr class="{{ $fila['pendiente_utm'] ? 'table-warning' : '' }}">
                                <td class="text-center">{{ $fila['numero'] }}</td>
                                <td class="text-center text-nowrap">{{ $fila['periodo']->format('m-Y') }}</td>
                                <td class="text-end">{{ $fila['valor_utm'] === null ? 'Pendiente' : $pesos($fila['valor_utm']) }}</td>
                                <td class="text-end">{{ $utm($fila['saldo_inicial_utm']) }}</td>
                                <td class="text-end">{{ $utm($fila['capital_utm']) }}</td>
                                <td class="text-end">{{ $utm($fila['saldo_final_utm']) }}</td>
                                <td class="text-end">{{ $pesos($fila['saldo_inicial_pesos']) }}</td>
                                <td class="text-end">{{ $pesos($fila['capital_pesos']) }}</td>
                                <td class="text-end">{{ $pesos($fila['interes_pesos']) }}</td>
                                <td class="text-end fw-semibold">{{ $pesos($fila['descuento_pesos']) }}</td>
                                <td class="text-center"><a href="{{ route('descuentos-cgr.cronograma.pdf', [$descuentoCgr, $fila['numero']]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-file-earmark-pdf me-1"></i>Ver PDF</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr><td colspan="7" class="text-end">Totales calculados</td><td class="text-end">{{ $pesos($calculo['totales']['capital_pesos']) }}</td><td class="text-end">{{ $pesos($calculo['totales']['interes_pesos']) }}</td><td class="text-end">{{ $pesos($calculo['totales']['descuento_pesos']) }}</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($descuentoCgr->observaciones)
            <div class="card mt-4"><div class="card-header"><i class="bi bi-chat-left-text me-2 text-primary" aria-hidden="true"></i>Observaciones</div><div class="card-body">{!! nl2br(e($descuentoCgr->observaciones)) !!}</div></div>
        @endif
    </div>
@endsection
