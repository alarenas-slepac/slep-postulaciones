<div class="card mt-4">
    <div class="card-header"><i class="bi bi-diagram-3 me-2 text-primary" aria-hidden="true"></i>Avance del cronograma</div>
    <div class="card-body">
        @if ($estado === 'ingresado')
            @php $cargadas = $descuentoCgr->archivos->where('tipo', 'liquidacion')->pluck('numero_cuota')->unique()->count(); @endphp
            <p class="mb-3">Liquidaciones cargadas: <strong>{{ $cargadas }} de {{ $descuentoCgr->numero_cuotas }}</strong>.</p>
            @if ($puedeRegistrar)
                <form method="POST" action="{{ route('descuentos-cgr.enviar-finanzas', $descuentoCgr) }}">@csrf<button class="btn btn-primary" @disabled(! $completos('liquidacion'))><i class="bi bi-send me-1"></i>Enviar a Finanzas</button></form>
                @unless ($completos('liquidacion')) <div class="form-text">Completa las liquidaciones de todas las cuotas para habilitar el envío.</div> @endunless
            @endif
        @elseif ($estado === 'descuentos_realizados')
            <p class="mb-3">Finanzas debe registrar un comprobante SIGFE y uno TGR para cada cuota. Un mismo comprobante puede asociarse a varias cuotas.</p>
            @if ($puedeFinanzas)
                <form method="POST" action="{{ route('descuentos-cgr.enviar-auditoria', $descuentoCgr) }}">@csrf<button class="btn btn-primary" @disabled(! ($completos('sigfe') && $completos('tgr')))><i class="bi bi-send me-1"></i>Enviar a Auditoría Interna</button></form>
                @unless ($completos('sigfe') && $completos('tgr')) <div class="form-text">Completa los comprobantes SIGFE y TGR de todas las cuotas.</div> @endunless
            @endif
        @elseif ($estado === 'en_auditoria')
            @php $validadas = $descuentoCgr->archivos->where('tipo', 'liquidacion_validada')->pluck('numero_cuota')->unique()->count(); @endphp
            <p class="mb-3">Liquidaciones validadas: <strong>{{ $validadas }} de {{ $descuentoCgr->numero_cuotas }}</strong>.</p>
            @if ($puedeAuditoria)
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    @if ($completos('liquidacion_validada') && $calculo['utm_faltantes'] === [] && $calculo['saldo_final_utm'] <= 0.00005) <a href="{{ route('descuentos-cgr.auditoria.certificado', $descuentoCgr) }}" class="btn btn-primary"><i class="bi bi-file-earmark-word me-1"></i>Generar certificado Word</a>
                    @else <button type="button" class="btn btn-primary" disabled>Generar certificado Word</button> @endif
                    @if ($descuentoCgr->certificado_firmado_path) <a href="{{ route('descuentos-cgr.certificado-firmado.show', $descuentoCgr) }}" target="_blank" rel="noopener" class="btn btn-outline-danger">Ver certificado firmado</a><span class="small text-muted">Cargado: {{ $descuentoCgr->certificado_firmado_en?->format('d-m-Y H:i') }}</span> @endif
                </div>
                @if ($completos('liquidacion_validada') && ($calculo['utm_faltantes'] !== [] || $calculo['saldo_final_utm'] > 0.00005)) <div class="alert alert-warning">Completa los valores UTM y verifica que las cuotas extingan la deuda para generar el certificado.</div> @endif
                @if ($completos('liquidacion_validada'))
                    <p class="form-text">Descarga primero el certificado Word, complétalo con la firma electrónica y carga aquí el PDF firmado.</p>
                    <form method="POST" action="{{ route('descuentos-cgr.auditoria.certificado-firmado', $descuentoCgr) }}" enctype="multipart/form-data" class="row g-3 align-items-end mb-3">
                        @csrf
                        <div class="col-md-7"><label for="certificado_pdf" class="form-label">Certificado firmado electrónicamente (PDF)</label><input id="certificado_pdf" type="file" name="certificado_pdf" accept="application/pdf" class="form-control @error('certificado_pdf') is-invalid @enderror" required>@error('certificado_pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-auto"><button class="btn btn-outline-primary"><i class="bi bi-upload me-1"></i>Subir certificado firmado</button></div>
                    </form>
                @endif
                @if ($descuentoCgr->certificado_firmado_path)
                    <form method="POST" action="{{ route('descuentos-cgr.auditoria.cerrar', $descuentoCgr) }}" onsubmit="return confirm('Se cerrará el registro CGR. ¿Deseas realizar el alzamiento?');">@csrf<button class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Realizar Alzamiento de Reintegro</button></form>
                @endif
            @endif
        @else
            <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>Registro cerrado el {{ $descuentoCgr->cerrado_en?->format('d-m-Y H:i') ?? 'día no informado' }}.
                @if ($descuentoCgr->certificado_firmado_path) <a href="{{ route('descuentos-cgr.certificado-firmado.show', $descuentoCgr) }}" target="_blank" rel="noopener">Ver certificado firmado</a> (cargado: {{ $descuentoCgr->certificado_firmado_en?->format('d-m-Y H:i') }}) @endif
            </div>
        @endif
    </div>
</div>

@foreach (['liquidacion' => ['modalLiquidaciones', 'Liquidaciones de sueldo', 'descuentos-cgr.liquidaciones', $puedeRegistrar], 'liquidacion_validada' => ['modalValidadas', 'Liquidaciones validadas', 'descuentos-cgr.auditoria.liquidaciones', $puedeAuditoria]] as $tipo => [$modalId, $titulo, $ruta, $visible])
    @if ($visible)
        <div class="modal fade cgr-document-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Titulo" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <form method="POST" action="{{ route($ruta, $descuentoCgr) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header"><h2 class="modal-title fs-5" id="{{ $modalId }}Titulo">{{ $titulo }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                    <div class="modal-body"><p class="text-muted">Carga una, varias o todas las cuotas. Los archivos ya cargados se conservan si dejas su campo vacío.</p>
                        @foreach ($calculo['filas'] as $fila)
                            @php $actual = $archivosPorCuota->get($fila['numero'], collect())->get($tipo); @endphp
                            <div class="cgr-upload-row row g-2 align-items-center py-2 border-bottom" data-cuota="{{ $fila['numero'] }}">
                                <div class="col-md-4"><label for="{{ $modalId }}-{{ $fila['numero'] }}" class="form-label mb-0 fw-semibold">Cuota {{ $fila['numero'] }} · {{ $fila['periodo']->format('m-Y') }}</label>@if ($actual)<div class="small"><a href="{{ route('descuentos-cgr.archivos.show', [$descuentoCgr, $actual]) }}" target="_blank" rel="noopener">{{ $actual->nombre_original }}</a> · {{ $actual->updated_at?->format('d-m-Y H:i') }}</div>@else<div class="small text-muted">Sin liquidación</div>@endif</div>
                                <div class="col-md-8"><input id="{{ $modalId }}-{{ $fila['numero'] }}" type="file" name="liquidaciones[{{ $fila['numero'] }}]" accept="application/pdf" class="form-control" aria-label="Cargar {{ strtolower($titulo) }} para cuota {{ $fila['numero'] }}"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Guardar liquidaciones seleccionadas</button></div>
                </form>
            </div></div>
        </div>
    @endif
@endforeach

@if ($puedeFinanzas)
    @foreach (['sigfe' => ['modalSigfe', 'Comprobante de reintegro SIGFE'], 'tgr' => ['modalTgr', 'Comprobante de reintegro a TGR']] as $tipo => [$modalId, $titulo])
        <div class="modal fade cgr-document-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Titulo" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('descuentos-cgr.comprobantes', [$descuentoCgr, $tipo]) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header"><h2 class="modal-title fs-5" id="{{ $modalId }}Titulo">{{ $titulo }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body"><p class="text-muted">Selecciona las cuotas cubiertas por este comprobante. Se reemplazará el documento anterior sólo para las cuotas seleccionadas.</p>
                    <div class="row g-3 mb-3"><div class="col-md-6"><label for="{{ $modalId }}-archivo" class="form-label">Archivo PDF <span class="text-danger">*</span></label><input id="{{ $modalId }}-archivo" type="file" name="archivo" accept="application/pdf" class="form-control" required></div><div class="col-md-6"><label for="{{ $modalId }}-folio" class="form-label">Número de reintegro o folio <span class="text-danger">*</span></label><input id="{{ $modalId }}-folio" name="folio" class="form-control" maxlength="100" required></div><div class="col-md-6"><label for="{{ $modalId }}-fecha" class="form-label">Fecha de reintegro <span class="text-danger">*</span></label><input id="{{ $modalId }}-fecha" type="date" name="fecha_reintegro" class="form-control" required></div><div class="col-md-6"><label for="{{ $modalId }}-monto" class="form-label">Monto total del comprobante en pesos <span class="text-danger">*</span></label><input id="{{ $modalId }}-monto" type="number" name="monto_reintegro_pesos" min="1" step="1" class="form-control" required></div></div>
                    <fieldset><legend class="fs-6 fw-semibold">Cuotas asociadas <span class="text-danger">*</span></legend><div class="row g-2">@foreach ($calculo['filas'] as $fila)<div class="col-sm-6 col-lg-4"><label class="form-check cgr-quota-choice"><input type="checkbox" name="cuotas[]" value="{{ $fila['numero'] }}" class="form-check-input"><span class="form-check-label">Cuota {{ $fila['numero'] }} · {{ $fila['periodo']->format('m-Y') }} @if ($archivosPorCuota->get($fila['numero'], collect())->has($tipo)) <span class="text-success">Cargado</span> @endif</span></label></div>@endforeach</div></fieldset>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Guardar comprobante</button></div>
            </form></div></div>
        </div>
    @endforeach
@endif

@push('scripts')
<script>
document.querySelectorAll('.cgr-document-modal').forEach((modal) => {
    modal.addEventListener('show.bs.modal', (event) => {
        const cuota = event.relatedTarget?.dataset?.cuota;
        const checks = modal.querySelectorAll('input[name="cuotas[]"]');
        if (checks.length) checks.forEach((check) => { check.checked = cuota ? check.value === cuota : false; });
        if (!cuota) return;
        const fila = modal.querySelector('[data-cuota="' + cuota + '"]');
        if (fila) modal.addEventListener('shown.bs.modal', () => fila.scrollIntoView({block: 'center'}), {once: true});
    });
});
</script>
@endpush
