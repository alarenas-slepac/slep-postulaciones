@extends('layouts.app')

@section('content')
    @include('remuneraciones.descuentos-cgr._styles')
    @php
        $pesos = fn ($valor) => $valor === null ? '—' : '$' . number_format((float) $valor, 0, ',', '.');
        $utm = fn ($valor) => number_format((float) $valor, 4, ',', '.');
        $tasaEsperada = round((float) $descuentoCgr->tasa_interes_anual / 12, 4);
        $estado = $descuentoCgr->estadoActual();
        $puedeRegistrar = auth()->user()?->hasAnyRole(['admin', 'funcionario_slep']) && $estado === 'ingresado';
        $puedeFinanzas = auth()->user()?->hasAnyRole(['admin', 'funcionario_daf']) && $estado === 'descuentos_realizados';
        $puedeAuditoria = auth()->user()?->hasAnyRole(['admin', 'auditoria_slep']) && $estado === 'en_auditoria';
        $archivosPorCuota = $descuentoCgr->archivos->groupBy('numero_cuota')->map(fn ($archivos) => $archivos->keyBy('tipo'));
        $completos = fn ($tipo) => $descuentoCgr->archivos->where('tipo', $tipo)->pluck('numero_cuota')->unique()->count() === (int) $descuentoCgr->numero_cuotas;
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
                @if ($puedeRegistrar)
                <a href="{{ route('descuentos-cgr.edit', $descuentoCgr) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
                <form method="POST" action="{{ route('descuentos-cgr.destroy', $descuentoCgr) }}" onsubmit="return confirm('Se eliminará el descuento CGR, su cronograma y la resolución PDF asociada. Esta acción no se puede deshacer. ¿Deseas continuar?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </form>
                @endif
                <a href="{{ route('descuentos-cgr.index', ['estado' => $estado]) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver</a>
            </div>
        </div>

        @if (session('status')) <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div> @endif
        @if (isset($errors) && $errors->any()) <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i><strong>Revisa la acción:</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

        <div class="card mb-4"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><span class="badge {{ match($estado) { 'cerrado' => 'text-bg-success', 'en_auditoria' => 'text-bg-info', 'descuentos_realizados' => 'text-bg-warning', default => 'text-bg-primary' } }}">{{ $descuentoCgr->etiquetaEstado() }}</span><div class="small text-muted mt-2">Registrado: {{ $descuentoCgr->created_at?->format('d-m-Y H:i') ?? 'Sin fecha' }} · Finanzas: {{ $descuentoCgr->enviado_finanzas_en?->format('d-m-Y H:i') ?? 'Pendiente' }} · Auditoría: {{ $descuentoCgr->enviado_auditoria_en?->format('d-m-Y H:i') ?? 'Pendiente' }} · Cierre: {{ $descuentoCgr->cerrado_en?->format('d-m-Y H:i') ?? 'Pendiente' }}</div></div>
            <div class="d-flex flex-wrap gap-2">
                @if ($puedeRegistrar) <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalLiquidaciones"><i class="bi bi-upload me-1"></i>Cargar liquidaciones</button> @endif
                @if ($puedeFinanzas)
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalSigfe">Cargar comprobante SIGFE</button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTgr">Cargar comprobante TGR</button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTransferenciaInstitucion"><i class="bi bi-upload me-1" aria-hidden="true"></i>Cargar transferencia a otra institución</button>
                @endif
                @if ($puedeAuditoria) <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalValidadas">Cargar liquidaciones validadas</button> @endif
            </div>
        </div></div>

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
        <div class="card mb-4"><div class="card-header"><i class="bi bi-building me-2 text-primary"></i>Reintegro y antecedentes</div><div class="card-body row g-3"><div class="col-md-6"><span class="small text-muted">Institución de reintegro</span><div class="fw-semibold">{{ $descuentoCgr->institucion_reintegro ?: 'No informada' }}</div></div><div class="col-md-6"><span class="small text-muted">Estamento o escalafón</span><div class="fw-semibold">{{ $descuentoCgr->estamento_funcionario ?: 'No informado' }}</div></div></div></div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span><i class="bi bi-calendar3 me-2 text-primary" aria-hidden="true"></i>Cronograma de descuentos</span>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="text-muted small">Montos en pesos redondeados visualmente</span>
                    @if ($puedeAuditoria || $estado === 'cerrado')
                        <a href="{{ route('descuentos-cgr.expediente.zip', $descuentoCgr) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar respaldos ZIP</a>
                    @endif
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light text-center align-middle">
                        <tr><th>N°</th><th>Mes</th><th>Valor UTM</th><th>Saldo inicial UTM</th><th>Capital UTM</th><th>Saldo final UTM</th><th>Saldo inicial $</th><th>Capital $</th><th>Interés mes $</th><th>Descuento total $</th><th>Documento</th><th>Respaldos y acciones</th></tr>
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
                                <td class="cgr-respaldos">
                                    @php $archivosCuota = $archivosPorCuota->get($fila['numero'], collect()); @endphp
                                    @foreach (['liquidacion' => 'Liquidación', 'sigfe' => 'SIGFE', 'tgr' => 'TGR', 'transferencia_institucion' => 'Transferencia a otra institución', 'liquidacion_validada' => 'Validada'] as $tipoArchivo => $etiquetaArchivo)
                                        @php $respaldo = $archivosCuota->get($tipoArchivo); @endphp
                                        <div class="mb-2"><span class="small fw-semibold">{{ $etiquetaArchivo }}:</span>
                                            @if ($respaldo)
                                                <a href="{{ route('descuentos-cgr.archivos.show', [$descuentoCgr, $respaldo]) }}" target="_blank" rel="noopener" class="small">{{ $respaldo->nombre_original }}</a>
                                                <div class="small text-muted">Última carga: {{ $respaldo->updated_at?->format('d-m-Y H:i') }} · {{ $respaldo->cargadoPor?->nombre_completo ?: 'Usuario' }}@if ($respaldo->folio) · Folio {{ $respaldo->folio }} · {{ $tipoArchivo === 'transferencia_institucion' ? 'Transferencia' : 'Reintegro' }} {{ $respaldo->fecha_reintegro?->format('d-m-Y') }} · Monto comprobante {{ $pesos($respaldo->monto_reintegro_pesos) }} @endif</div>
                                            @else <span class="small text-muted">{{ $tipoArchivo === 'transferencia_institucion' ? 'No cargado (opcional)' : 'Pendiente' }}</span> @endif
                                        </div>
                                    @endforeach
                                    @if ($puedeRegistrar) <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#modalLiquidaciones" data-cuota="{{ $fila['numero'] }}">{{ $archivosCuota->has('liquidacion') ? 'Corregir' : 'Cargar' }} liquidación</button> @endif
                                    @if ($puedeFinanzas)
                                        <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#modalSigfe" data-cuota="{{ $fila['numero'] }}">{{ $archivosCuota->has('sigfe') ? 'Corregir' : 'Cargar' }} SIGFE</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#modalTgr" data-cuota="{{ $fila['numero'] }}">{{ $archivosCuota->has('tgr') ? 'Corregir' : 'Cargar' }} TGR</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#modalTransferenciaInstitucion" data-cuota="{{ $fila['numero'] }}">{{ $archivosCuota->has('transferencia_institucion') ? 'Corregir' : 'Cargar' }} transferencia</button>
                                    @endif
                                    @if ($puedeAuditoria) <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#modalValidadas" data-cuota="{{ $fila['numero'] }}">{{ $archivosCuota->has('liquidacion_validada') ? 'Corregir' : 'Cargar' }} validada</button> @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr><td colspan="7" class="text-end">Totales calculados</td><td class="text-end">{{ $pesos($calculo['totales']['capital_pesos']) }}</td><td class="text-end">{{ $pesos($calculo['totales']['interes_pesos']) }}</td><td class="text-end">{{ $pesos($calculo['totales']['descuento_pesos']) }}</td><td colspan="2"></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @include('remuneraciones.descuentos-cgr._workflow')

        @if ($descuentoCgr->observaciones)
            <div class="card mt-4"><div class="card-header"><i class="bi bi-chat-left-text me-2 text-primary" aria-hidden="true"></i>Observaciones</div><div class="card-body">{!! nl2br(e($descuentoCgr->observaciones)) !!}</div></div>
        @endif
    </div>
@endsection
