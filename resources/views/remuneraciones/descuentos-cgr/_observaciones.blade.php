@inject('observacionesCgr', 'App\Services\Remuneraciones\DescuentoCgrObservacionService')

@if ($puedeObservar)
    @php
        $etapasDestino = $observacionesCgr::ETAPAS;
        $elementosSeleccionados = (array) old('elementos', []);
    @endphp
    <section class="card mb-4" id="cgr-observar" aria-labelledby="cgr-observar-titulo">
        <div class="card-header">
            <h2 class="h5 mb-0" id="cgr-observar-titulo"><i class="bi bi-arrow-return-left me-2 text-primary" aria-hidden="true"></i>Observar y devolver el registro</h2>
        </div>
        <form class="card-body" method="POST" action="{{ route('descuentos-cgr.auditoria.observar', $descuentoCgr) }}" id="cgr-observacion-form">
            @csrf
            <div class="row g-3">
                <div class="col-md-5">
                    <label for="etapa_destino" class="form-label">Etapa de destino</label>
                    <select id="etapa_destino" name="etapa_destino" class="form-select" required>
                        <option value="">Selecciona la etapa</option>
                        @foreach ($etapasDestino as $etapa => $etiqueta)
                            <option value="{{ $etapa }}" @selected(old('etapa_destino') === $etapa)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-7">
                    <label for="observacion_auditoria" class="form-label">Observación de Auditoría</label>
                    <textarea id="observacion_auditoria" name="observacion" class="form-control" rows="3" maxlength="5000" required>{{ old('observacion') }}</textarea>
                </div>
            </div>
            <p class="form-text mt-3 mb-2">Selecciona los campos o archivos que la etapa de destino debe corregir.</p>
            @foreach ($etapasDestino as $etapa => $etiquetaEtapa)
                @php
                    $grupos = collect($observacionesCgr->opciones($descuentoCgr, $etapa))->groupBy(
                        fn ($etiqueta, $clave) => strstr($clave, ':', true) ?: 'campos',
                        preserveKeys: true
                    );
                @endphp
                <fieldset class="mb-3" data-cgr-etapa="{{ $etapa }}">
                    <legend class="fs-6 fw-semibold">{{ $etiquetaEtapa }}</legend>
                    @foreach ($grupos as $grupo => $opciones)
                        <details class="border rounded p-2 mb-2" @if ($grupo === 'campos' || $opciones->keys()->intersect($elementosSeleccionados)->isNotEmpty()) open @endif>
                            <summary class="fw-semibold">{{ $grupo === 'campos' ? 'Datos y resolución' : $observacionesCgr::DOCUMENTOS[$etapa][$grupo] }}</summary>
                            <div class="row g-2 mt-1">
                                @foreach ($opciones as $clave => $etiqueta)
                                    <div class="col-md-6">
                                        <label class="form-check mb-0">
                                            <input type="checkbox" name="elementos[]" class="form-check-input" value="{{ $clave }}" @checked(in_array($clave, $elementosSeleccionados, true))>
                                            <span class="form-check-label">{{ $etiqueta }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </fieldset>
            @endforeach
            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>Registrar observación y devolver</button>
        </form>
    </section>
    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('cgr-observacion-form');
                const destino = form.elements.etapa_destino;
                const seleccionarEtapa = () => {
                    form.querySelectorAll('fieldset[data-cgr-etapa]').forEach((grupo) => {
                        grupo.hidden = grupo.dataset.cgrEtapa !== destino.value;
                        grupo.disabled = grupo.hidden;
                    });
                };
                destino.addEventListener('change', seleccionarEtapa);
                seleccionarEtapa();
                form.addEventListener('formdata', (event) => {
                    const elementos = event.formData.getAll('elementos[]');
                    event.formData.delete('elementos[]');
                    event.formData.set('elementos_json', JSON.stringify(elementos));
                });
            })();
        </script>
    @endpush
@endif

@if ($descuentoCgr->observacionesAuditoria->isNotEmpty())
    @php
        $pendientes = $observacionActiva ? $observacionesCgr->pendientes($descuentoCgr, $observacionActiva) : [];
        $puedeReenviar = $observacionActiva && in_array($rolActivo, ['admin', $observacionesCgr::ROLES_CORRECCION[$estado] ?? null], true) && ($puedeRegistrar || $puedeFinanzas);
        $faltanLiquidaciones = $observacionActiva?->etapa_destino === 'ingresado' && ! $completos('liquidacion');
        $comprobantesCompletos = $completos('sigfe') && $completos('tgr');
        $requiereFinanzas = $puedeRegistrar && ! $comprobantesCompletos;
    @endphp
    <section class="card mb-4" aria-labelledby="cgr-historial-titulo">
        <div class="card-header">
            <h2 class="h5 mb-0" id="cgr-historial-titulo"><i class="bi bi-chat-left-text me-2 text-primary" aria-hidden="true"></i>Observaciones de Auditoría</h2>
        </div>
        <div class="card-body">
            @foreach ($descuentoCgr->observacionesAuditoria as $observacion)
                <details class="border rounded p-3 mb-2" @if (! $observacion->reenviada_en) open @endif>
                    <summary>
                        <span class="fw-semibold">{{ $observacion->created_at?->format('d-m-Y H:i') }} · {{ $observacionesCgr::ETAPAS[$observacion->etapa_destino] }}</span>
                        <span class="badge {{ $observacion->reenviada_en ? 'text-bg-success' : 'text-bg-warning' }} ms-1">{{ $observacion->reenviada_en ? 'Reenviada' : 'Pendiente de corrección' }}</span>
                    </summary>
                    <p class="text-muted small mt-2 mb-1">Observado por {{ $observacion->observadaPor?->nombre_completo ?: 'Auditoría' }}</p>
                    <p class="mb-2 text-break">{!! nl2br(e($observacion->observacion)) !!}</p>
                    <ul class="mb-2">
                        @foreach ($observacion->elementos as $clave => $huella)
                            <li>
                                {{ $observacionesCgr->etiqueta($clave) }}
                                @unless ($observacion->reenviada_en)
                                    <span class="small fw-semibold {{ in_array($clave, $pendientes, true) ? 'text-warning-emphasis' : 'text-success' }}">
                                        {{ in_array($clave, $pendientes, true) ? 'Pendiente' : 'Corregido' }}
                                    </span>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                    @if ($observacion->reenviada_en)
                        <p class="text-muted small mb-0">Reenviado a Auditoría el {{ $observacion->reenviada_en->format('d-m-Y H:i') }} por {{ $observacion->reenviadaPor?->nombre_completo ?: 'Usuario de la etapa' }}.</p>
                    @endif
                </details>
            @endforeach
            @if ($puedeReenviar)
                <form method="POST" action="{{ route('descuentos-cgr.reenviar-auditoria', $descuentoCgr) }}" class="mt-3">
                    @csrf
                    <button class="btn btn-primary" @disabled($pendientes !== [] || $faltanLiquidaciones || ($puedeFinanzas && ! $comprobantesCompletos))>
                        <i class="bi bi-send me-1" aria-hidden="true"></i>{{ $requiereFinanzas ? 'Continuar a Finanzas' : 'Reenviar a Auditoría' }}
                    </button>
                    @if ($pendientes !== [])
                        <p class="form-text mb-0">Corrige todos los elementos marcados como pendientes para continuar.</p>
                    @elseif ($faltanLiquidaciones)
                        <p class="form-text mb-0">Carga las liquidaciones de todas las cuotas para continuar.</p>
                    @elseif (! $comprobantesCompletos)
                        <p class="form-text mb-0">{{ $requiereFinanzas ? 'Finanzas debe completar los comprobantes que faltan antes de que el registro vuelva a Auditoría.' : 'Completa los comprobantes SIGFE y TGR de todas las cuotas para reenviar.' }}</p>
                    @endif
                </form>
            @endif
        </div>
    </section>
@endif
