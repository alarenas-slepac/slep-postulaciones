@extends('layouts.app')

@section('content')
    @php
        $isEdit = $cometido->exists;
        $origenAc = (bool) ($origenAc ?? false);
        $funcionarioAc = $funcionarioAc ?? null;
        $funcionarioAcGradoGlobal = trim((string) ($funcionarioAc->grado ?? $cometido->grado ?? ''));
        $funcionarioAcGradoNormalizado = mb_strtoupper($funcionarioAcGradoGlobal);
        $funcionarioAcSinGrado = $origenAc && (
            $funcionarioAcGradoGlobal === ''
            || in_array($funcionarioAcGradoNormalizado, ['-', 'SIN GRADO', 'SIN GRADO REGISTRADO', 'S/G', 'NO APLICA', 'N/A'], true)
            || str_contains($funcionarioAcGradoNormalizado, 'SIN GRADO')
            || ! preg_match('/\d+/', $funcionarioAcGradoGlobal)
        );
        $comunasSinDerechoViaticoAc = ['CONCEPCION', 'SAN PEDRO DE LA PAZ', 'CHIGUAYANTE', 'PENCO', 'TALCAHUANO', 'HUALPEN', 'CORONEL', 'ISLA SANTA MARIA', 'LOTA'];
        $fundamentoSinViaticoConglomeradoAc = 'No corresponde viático por tratarse de un cometido dentro del conglomerado de comunas sin derecho a viático, conforme Decreto Exento N° 90/2018 del Ministerio de Hacienda.';
        $enRevisionUatpSinIntervencion = $isEdit
            && $cometido->estado === 'en_revision_uatp'
            && is_null($cometido->uatp_decision)
            && is_null($cometido->uatp_revisado_at);
        $action = $isEdit ? route('tramites.cometidos-funcionarios.update', $cometido) : route('tramites.cometidos-funcionarios.store');
        $selectedFuncionario = old('reemplazo_personal_id', $cometido->reemplazo_personal_id);
        $selectedMedios = old('medios_transporte', $cometido->medios_transporte ?? []);
        $selectedMedios = is_array($selectedMedios) ? $selectedMedios : [];
        $tiposPasajeAereo = [
            'solo_ida' => 'Solo ida',
            'solo_regreso' => 'Solo regreso',
            'ida_y_regreso' => 'Ida y regreso',
        ];
        $tipoPasajeAereoSel = old('tipo_pasaje_aereo', $cometido->tipo_pasaje_aereo ?? '');
        $bancoPagoSel = old('banco_pago', $cometido->banco_pago ?? '');
        $tipoCuentaPagoSel = old('tipo_cuenta_pago', $cometido->tipo_cuenta_pago ?? '');
        $numeroCuentaPagoSel = old('numero_cuenta_pago', $cometido->numero_cuenta_pago ?? '');
        $mostrarDatosBancariosInicial = (!$funcionarioAcSinGrado && old('solicita_viatico', $cometido->solicita_viatico) == 1) || old('solicita_reembolso', $cometido->solicita_reembolso) == 1;
        $mostrarAnticipoInicial = old('solicita_anticipo_viatico', $cometido->solicita_anticipo_viatico ?? false) == 1;
        $regionSel = old('region_destino', $cometido->region_destino ?? '');
        $comunaSel = old('comuna_destino_id', $cometido->comuna_destino_id ?? '');
        $regionOrigenSel = old('region_origen', $cometido->region_origen ?? '');
        $comunaOrigenSel = old('comuna_origen_id', $cometido->comuna_origen_id ?? '');
        $esExtranjeroSel = old('es_destino_extranjero', $cometido->es_destino_extranjero ?? false);
        $documentos = $cometido->exists ? $cometido->documentos : collect();
        $oficioActual = $documentos->where('tipo', 'oficio')->sortByDesc('id')->first();
        $formularioActual = $documentos->where('tipo', 'formulario_cometido')->sortByDesc('id')->first();
        $citacionActual = $documentos->where('tipo', 'citacion_invitacion')->sortByDesc('id')->first();
        $viaticosAnexoRutBodies = collect($viaticosAnexoRutBodies ?? [])->map(fn($rut) => (string) $rut)->all();
        $rutBodyFromRut = function ($rut) {
            $clean = preg_replace('/[^0-9kK]/', '', (string) $rut);
            if ($clean === '') {
                return null;
            }
            return strlen($clean) > 1 ? substr($clean, 0, -1) : $clean;
        };
        $funcionariosJson = $funcionarios->map(function ($f) use ($viaticosAnexoRutBodies, $rutBodyFromRut) {
            $rutBody = $rutBodyFromRut($f->rut ?? null);
            return [
                'id' => $f->id,
                'label' => trim(($f->rut ?? '') . ' | ' . ($f->nombre ?? '') . ' | ' . ($f->estatuto ?? '') . ' | ' . ($f->escalafon ?? '') . ' | ' . ($f->tipocontrato ?? '')),
                'rut' => $f->rut,
                'rut_body' => $rutBody,
                'nombre' => $f->nombre,
                'calidad_juridica' => $f->tipocontrato,
                'estamento' => $f->estatuto,
                'cargo_funcion' => $f->escalafon,
                'viatico_anexo_habilitado' => $rutBody && in_array((string) $rutBody, $viaticosAnexoRutBodies, true),
            ];
        })->values();
    @endphp

    <div class="container py-4 cometido-form-page">
        <style>
            .cometido-form-page { color: #0f172a; }
            .cometido-form-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; }
            .cometido-form-actions { display: flex; flex-wrap: wrap; gap: .65rem; justify-content: flex-end; }
            .cometido-form-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border-radius: .78rem; font-weight: 800; line-height: 1.2; padding: .58rem .9rem; border-width: 1px; box-shadow: 0 .28rem .8rem rgba(15,23,42,.06); transition: transform .12s ease, box-shadow .12s ease, background-color .12s ease, border-color .12s ease; }
            .cometido-form-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 .42rem 1rem rgba(15,23,42,.1); }
            .cometido-form-btn:disabled { opacity: .58; cursor: not-allowed; box-shadow: none; transform: none; }
            .cometido-form-btn.is-primary { background: #0d6efd; border-color: #0d6efd; color: #fff; }
            .cometido-form-btn.is-primary:hover:not(:disabled) { background: #0b5ed7; border-color: #0b5ed7; color: #fff; }
            .cometido-form-btn.is-secondary { background: #fff; border-color: #cbd5e1; color: #334155; }
            .cometido-form-btn.is-secondary:hover:not(:disabled) { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
            .cometido-form-btn.is-success { background: #0f8f4d; border-color: #0f8f4d; color: #fff; }
            .cometido-form-btn.is-danger { background: #fff; border-color: #ef4444; color: #dc2626; }
            .cometido-form-info { display: flex; gap: .8rem; align-items: flex-start; padding: .95rem 1rem; border: 1px solid #b9d9ff; border-radius: .95rem; background: #eff7ff; color: #1e3a8a; box-shadow: 0 .22rem .8rem rgba(13,110,253,.05); }
            .cometido-form-info.is-warning { border-color: #f5d58b; background: #fffdf3; color: #6f3c00; }
            .cometido-form-info-icon { flex: 0 0 auto; width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #0d6efd; color: #fff; }
            .cometido-form-info.is-warning .cometido-form-info-icon { background: #f59f00; color: #1f2937; }
            .cometido-form-stepper { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .75rem; align-items: stretch; margin: 1.35rem 0 1.25rem; }
            .cometido-form-step { position: relative; border: 1px solid #e3eaf3; border-radius: 1rem; background: #fff; padding: .9rem; min-height: 6.25rem; box-shadow: 0 .22rem .75rem rgba(15,23,42,.035); }
            .cometido-form-step:not(:last-child)::after { content: ''; position: absolute; top: 1.55rem; left: calc(100% + .05rem); width: .65rem; height: 2px; background: #cbd5e1; }
            .cometido-form-step.is-active { border-color: #b9d9ff; background: linear-gradient(180deg, #f7fbff 0%, #fff 100%); box-shadow: 0 0 0 .14rem rgba(13,110,253,.07); }
            .cometido-form-step-number { width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #e2e8f0; color: #475569; font-weight: 900; margin-bottom: .45rem; }
            .cometido-form-step.is-active .cometido-form-step-number { background: #0d6efd; color: #fff; }
            .cometido-form-step-title { color: #0f172a; font-weight: 900; font-size: .86rem; line-height: 1.2; }
            .cometido-form-step-subtitle { color: #64748b; font-size: .73rem; line-height: 1.25; margin-top: .18rem; }
            .cometido-form-layout { display: grid; grid-template-columns: 15rem minmax(0, 1fr); gap: 1rem; align-items: start; }
            .cometido-form-sidebar { position: sticky; top: 1rem; display: grid; gap: .85rem; }
            .cometido-form-sidecard { border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; padding: .9rem; box-shadow: 0 .22rem .75rem rgba(15,23,42,.035); }
            .cometido-form-side-title { color: #64748b; font-size: .74rem; font-weight: 900; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .55rem; }
            .cometido-form-nav { display: grid; gap: .28rem; }
            .cometido-form-nav a { display: flex; align-items: center; gap: .55rem; padding: .55rem .62rem; border-radius: .7rem; color: #334155; text-decoration: none; font-size: .86rem; font-weight: 700; }
            .cometido-form-nav a:hover, .cometido-form-nav a.is-active { background: #eef6ff; color: #0d47a1; }
            .cometido-form-help { border: 1px solid #b9d9ff; background: #f8fbff; color: #1e3a8a; }
            .cometido-form-help.is-success { border-color: #bcebd0; background: #f8fdf9; color: #0f5132; }
            .cometido-form-card { border: 1px solid #d7dee8; border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15,23,42,.045); }
            .cometido-form-card .card-header { padding: 1rem 1.1rem; border-bottom: 1px solid #e5edf6; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%) !important; }
            .cometido-form-card .card-body { padding: 1.1rem; }
            .cometido-form-section-title { display: flex; align-items: center; gap: .65rem; font-weight: 900; color: #0f172a; }
            .cometido-form-section-icon { width: 2.25rem; height: 2.25rem; border-radius: .78rem; display: inline-flex; align-items: center; justify-content: center; background: #eef6ff; color: #0d6efd; font-size: 1rem; box-shadow: 0 .2rem .55rem rgba(13,110,253,.09); }
            .cometido-form-card .form-label { color: #334155; font-size: .83rem; font-weight: 800; margin-bottom: .35rem; }
            .cometido-form-card .form-control, .cometido-form-card .form-select { border-color: #dbe4f0; border-radius: .72rem; min-height: 2.55rem; box-shadow: none; }
            .cometido-form-card .form-control:focus, .cometido-form-card .form-select:focus { border-color: #8ec1ff; box-shadow: 0 0 0 .18rem rgba(13,110,253,.08); }
            .cometido-form-card .form-control[readonly] { background: #f8fafc; color: #334155; }
            .cometido-form-card .form-text { color: #64748b; font-size: .78rem; }
            .required::after { content: ' *'; color: #dc2626; font-weight: 900; }
            .cometido-readonly-strip { background: #f8fbff; border: 1px solid #cfe1ff; color: #1e3a8a; border-radius: .75rem; padding: .55rem .75rem; font-size: .82rem; }
            .cometido-form-card select[size] { min-height: 12rem; border-radius: .85rem; }
            .cometido-form-card select[size] option { padding: .38rem .55rem; }
            .cometido-form-chip-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .55rem; }
            .cometido-form-card .medio-check { margin-top: .15rem; }
            .cometido-form-card .form-check { border: 1px solid #e3eaf3; border-radius: .72rem; padding: .55rem .6rem .55rem 2.05rem; background: #fff; min-height: 2.55rem; display: flex; align-items: center; }
            .cometido-form-card .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
            .cometido-form-doc-current { border: 1px solid #dbe4f0; border-radius: .75rem; background: #f8fafc; padding: .65rem .75rem; }
            .cometido-form-doc-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; }
            .cometido-form-bank-note { border: 1px solid #bcebd0; background: #f8fdf9; color: #0f5132; border-radius: .85rem; padding: .75rem .85rem; }
            .cometido-form-declaration { border-color: #f5d58b !important; background: #fffdf7; }
            .cometido-form-declaration .card-header { background: linear-gradient(135deg, #fff8e1 0%, #fffdf7 100%) !important; }
            .cometido-form-actionbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding: 1rem; border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15,23,42,.045); margin-bottom: 3rem; }
            .cometido-form-actionbar .right-actions { display: flex; flex-wrap: wrap; gap: .65rem; justify-content: flex-end; margin-left: auto; }
            @media (max-width: 1199.98px) {
                .cometido-form-layout { grid-template-columns: 1fr; }
                .cometido-form-sidebar { position: static; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            }
            @media (max-width: 991.98px) {
                .cometido-form-stepper { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .cometido-form-step:not(:last-child)::after { display: none; }
                .cometido-form-chip-grid, .cometido-form-doc-grid { grid-template-columns: 1fr; }
            }
            @media (max-width: 575.98px) {
                .cometido-form-sidebar { grid-template-columns: 1fr; }
                .cometido-form-stepper { grid-template-columns: 1fr; }
                .cometido-form-actionbar .right-actions, .cometido-form-actionbar .btn { width: 100%; }
            }
        </style>

        <div class="cometido-form-header">
            <div>
                <h1 class="h3 mb-1">{{ $isEdit ? 'Editar cometido funcionario' : 'Nueva solicitud de cometido funcionario' }}</h1>
                <p class="text-muted mb-0">Complete los antecedentes del cometido. Los campos marcados con <span class="text-danger fw-bold">*</span> son obligatorios para enviar.</p>
            </div>
            <div class="cometido-form-actions">
                <a href="{{ route('tramites.cometidos-funcionarios.index') }}" class="btn cometido-form-btn is-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                @unless ($enRevisionUatpSinIntervencion)
                    <button type="submit" form="cometidoForm" name="accion" value="borrador" class="btn cometido-form-btn is-secondary">
                        <i class="bi bi-save"></i> Guardar borrador
                    </button>
                @endunless
                <button type="submit" form="cometidoForm" name="accion" value="enviar" class="btn cometido-form-btn is-primary" id="btnEnviarSolicitudTop" disabled>
                    <i class="bi bi-send"></i> {{ $enRevisionUatpSinIntervencion ? 'Guardar cambios' : 'Enviar solicitud' }}
                </button>
            </div>
        </div>

        {{-- Aviso de fase de desarrollo retirado para funcionario_estab. --}}

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Revisa los datos ingresados:</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$origenAc && !$periodo)
            <div class="alert alert-warning">
                No se encontró un padrón activo/cargado para tu establecimiento. Debe existir al menos un padrón mensual para poder seleccionar funcionarios.
            </div>
        @endif

        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" id="cometidoForm">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif
            @if ($cometido->archivo_citacion_invitacion_path)
                <input type="hidden" name="archivo_citacion_invitacion_existente" value="1">
            @endif
            @if ($oficioActual)
                <input type="hidden" name="archivo_oficio_existente" value="1">
            @endif
            @if ($formularioActual)
                <input type="hidden" name="archivo_formulario_cometido_existente" value="1">
            @endif

            <div class="cometido-form-info is-warning mb-4">
                <span class="cometido-form-info-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                <div>
                    <div class="fw-semibold mb-1">Importante: plazo recomendado de solicitud</div>
                    <div>
                        La solicitud de Cometido funcionario debe ingresarse con una anticipación mínima recomendada de
                        <strong>10 a 15 días hábiles</strong> respecto de la fecha de inicio del cometido, especialmente si requiere
                        compra de pasajes en bus o avión, viático o devolución de gastos. Las solicitudes fuera de plazo podrían no
                        ser gestionadas oportunamente.
                    </div>
                </div>
            </div>

            <div class="cometido-form-stepper" aria-label="Etapas del formulario">
                <div class="cometido-form-step is-active"><div class="cometido-form-step-number">1</div><div class="cometido-form-step-title">Datos generales</div><div class="cometido-form-step-subtitle">Contexto de solicitud</div></div>
                <div class="cometido-form-step"><div class="cometido-form-step-number">2</div><div class="cometido-form-step-title">Funcionario</div><div class="cometido-form-step-subtitle">Selección desde padrón</div></div>
                <div class="cometido-form-step"><div class="cometido-form-step-number">3</div><div class="cometido-form-step-title">Viaje</div><div class="cometido-form-step-subtitle">Destino y fechas</div></div>
                <div class="cometido-form-step"><div class="cometido-form-step-number">4</div><div class="cometido-form-step-title">Transporte y gasto</div><div class="cometido-form-step-subtitle">Motivo, viático/reembolso</div></div>
                <div class="cometido-form-step"><div class="cometido-form-step-number">5</div><div class="cometido-form-step-title">Documentos</div><div class="cometido-form-step-subtitle">Respaldos obligatorios</div></div>
                <div class="cometido-form-step"><div class="cometido-form-step-number">6</div><div class="cometido-form-step-title">Declaración</div><div class="cometido-form-step-subtitle">Veracidad y envío</div></div>
            </div>

            <div class="cometido-form-layout">
                <aside class="cometido-form-sidebar">
                    <div class="cometido-form-sidecard">
                        <div class="cometido-form-side-title">Secciones</div>
                        <nav class="cometido-form-nav">
                            <a href="#section-datos" class="is-active"><i class="bi bi-file-earmark-text"></i> Datos generales</a>
                            <a href="#section-funcionario"><i class="bi bi-person-badge"></i> Funcionario</a>
                            <a href="#section-viaje"><i class="bi bi-airplane"></i> Detalle del viaje</a>
                            <a href="#section-transporte"><i class="bi bi-bus-front"></i> Transporte y motivo</a>
                            <a href="#section-citacion"><i class="bi bi-cash-coin"></i> Citación y gasto</a>
                            <a href="#section-datos-bancarios"><i class="bi bi-bank"></i> Datos bancarios</a>
                            <a href="#section-documentos"><i class="bi bi-paperclip"></i> Documentos</a>
                            <a href="#section-declaracion"><i class="bi bi-shield-check"></i> Declaración</a>
                        </nav>
                    </div>
                    <div class="cometido-form-sidecard cometido-form-help">
                        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i> Información importante</div>
                        <div class="small">La solicitud será enviada a la unidad revisora correspondiente una vez completados los campos obligatorios y documentos requeridos.</div>
                    </div>
                    <div class="cometido-form-sidecard cometido-form-help is-success">
                        <div class="fw-semibold mb-1"><i class="bi bi-check-circle me-1"></i> Borrador disponible</div>
                        <div class="small">Puedes guardar avances aunque aún falten documentos o confirmación de respaldo.</div>
                    </div>
                </aside>
                <main class="cometido-form-main">

            <div id="section-datos" class="card shadow-sm mb-4 cometido-form-card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-file-earmark-text"></i></span>Datos generales</h2>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Fecha de solicitud</label>
                        <input type="text" class="form-control" value="{{ optional($cometido->fecha_solicitud)->format('d-m-Y') ?? now()->format('d-m-Y') }}" readonly>
                    </div>
                    @if($origenAc)
                        <div class="col-md-5">
                            <label class="form-label">Origen institucional</label>
                            <input type="text" class="form-control" value="Administración Central" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control" value="Funcionario AC" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">N° interno</label>
                            <input type="text" class="form-control" value="{{ $cometido->numero_cometido_interno ?: 'Se asigna al enviar' }}" readonly>
                        </div>
                    @else
                        <div class="col-md-5">
                            <label class="form-label">Establecimiento</label>
                            <input type="text" class="form-control" value="{{ $establecimiento->nombre_establecimiento ?? '—' }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">RBD</label>
                            <input type="text" class="form-control" value="{{ $establecimiento->rbd ?? $establecimiento->cod_estab ?? '—' }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Padrón</label>
                            <input type="text" class="form-control" value="{{ $periodo ? sprintf('%02d/%04d', $periodo['mes'], $periodo['anio']) : 'Sin padrón' }}" readonly>
                        </div>
                    @endif
                </div>
            </div>

            <div id="section-funcionario" class="card shadow-sm mb-4 cometido-form-card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-person-badge"></i></span>1. Funcionario solicitante</h2>
                </div>
                <div class="card-body row g-3">
                    @if($origenAc)
                        @php
                            $funcionarioAcEscalafon = trim((string) ($funcionarioAc->escalafon ?? $cometido->estamento ?? ''));
                            if ($funcionarioAcEscalafon === '' && ! empty($funcionarioAc->observaciones)) {
                                if (preg_match('/Escalaf[oó]n:\s*(.*?)(?:\s+Calidad jur[ií]dica:|$)/iu', (string) $funcionarioAc->observaciones, $coincidenciasEscalafon)) {
                                    $funcionarioAcEscalafon = trim($coincidenciasEscalafon[1] ?? '');
                                }
                            }
                            $funcionarioAcGrado = $funcionarioAcGradoGlobal;
                            $funcionarioAcTelefono = trim((string) ($funcionarioAc->telefono ?? ''));
                            $funcionarioAcEmail = trim((string) ($funcionarioAc->email ?? $funcionarioAc->registeredUser?->email ?? auth()->user()?->email ?? ''));
                            $funcionarioAcFechaNacimiento = ! empty($funcionarioAc->fecha_nacimiento)
                                ? \Illuminate\Support\Carbon::parse($funcionarioAc->fecha_nacimiento)->format('d-m-Y')
                                : '';
                        @endphp
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" id="funcionario_nombre" class="form-control" value="{{ $funcionarioAc->nombre_completo ?? $cometido->funcionario_nombre }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">RUN</label>
                            <input type="text" id="funcionario_rut" class="form-control" value="{{ $funcionarioAc->rut_completo ?? $cometido->funcionario_rut }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Calidad jurídica</label>
                            <input type="text" id="calidad_juridica" class="form-control" value="{{ $funcionarioAc->calidad_juridica ?? $cometido->calidad_juridica }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Escalafón</label>
                            <input type="text" id="estamento" class="form-control" value="{{ $funcionarioAcEscalafon }}" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Grado</label>
                            <input type="text" id="grado_ac" class="form-control" value="{{ $funcionarioAcGrado !== '' ? $funcionarioAcGrado : 'Sin grado registrado' }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unidad</label>
                            <input type="text" class="form-control" value="{{ $funcionarioAc->unidad_departamento ?? $cometido->unidad_departamento_ac }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subdirección dependencia</label>
                            <input type="text" class="form-control" value="{{ $funcionarioAc->subdireccion_dependencia ?? $cometido->subdireccion_dependencia_ac }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" value="{{ $funcionarioAcTelefono !== '' ? $funcionarioAcTelefono : 'Sin teléfono registrado' }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Correo electrónico</label>
                            <input type="text" class="form-control" value="{{ $funcionarioAcEmail !== '' ? $funcionarioAcEmail : 'Sin correo registrado' }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="text" class="form-control" value="{{ $funcionarioAcFechaNacimiento !== '' ? $funcionarioAcFechaNacimiento : 'Sin fecha registrada' }}" readonly>
                            <div class="form-text">Dato requerido para compra de pasajes aéreos.</div>
                        </div>
                    @else
                        <div class="col-12">
                            <label for="funcionarioSearch" class="form-label required">Buscar funcionario del establecimiento</label>
                            <input type="search" class="form-control mb-2" id="funcionarioSearch" placeholder="Filtrar por RUT, nombre, estamento, cargo o contrato...">
                            <select name="reemplazo_personal_id" id="reemplazo_personal_id" class="form-select @error('reemplazo_personal_id') is-invalid @enderror" size="8" required>
                                @foreach ($funcionarios as $f)
                                    <option value="{{ $f->id }}" @selected((string) $selectedFuncionario === (string) $f->id)>
                                        {{ $f->rut }} | {{ $f->nombre }} | {{ $f->estatuto ?: 'Sin estamento' }} | {{ $f->escalafon ?: 'Sin cargo' }} | {{ $f->tipocontrato ?: 'Sin contrato' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">El listado usa el último padrón activo/cargado del establecimiento.</div>
                            @error('reemplazo_personal_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" id="funcionario_nombre" class="form-control" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">RUT</label>
                            <input type="text" id="funcionario_rut" class="form-control" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Calidad jurídica</label>
                            <input type="text" id="calidad_juridica" class="form-control" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Estamento</label>
                            <input type="text" id="estamento" class="form-control" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cargo / función</label>
                            <input type="text" id="cargo_funcion" class="form-control" readonly>
                        </div>
                    @endif
                </div>
            </div>

            <div id="section-viaje" class="card shadow-sm mb-4 cometido-form-card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-airplane"></i></span>2. Detalle del viaje</h2>
                </div>
                <div class="card-body row g-3">
                    @if($origenAc)
                        <div class="col-md-4">
                            <label class="form-label required" for="region_origen">Región origen</label>
                            <select name="region_origen" id="region_origen" class="form-select" required>
                                <option value="">Seleccione…</option>
                                @foreach ($regiones as $code => $nombre)
                                    <option value="{{ $code }}" @selected((string) $regionOrigenSel === (string) $code)>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="comuna_origen_id">Comuna origen</label>
                            <select name="comuna_origen_id" id="comuna_origen_id" class="form-select" required>
                                <option value="">Seleccione…</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="es_destino_extranjero" value="1" id="es_destino_extranjero" @checked($esExtranjeroSel)>
                                <label class="form-check-label fw-semibold" for="es_destino_extranjero">Cometido al extranjero</label>
                            </div>
                        </div>
                    @endif
                    <div class="col-md-4 destino-nacional-wrap">
                        <label class="form-label required" for="region_destino">Región destino</label>
                        <select name="region_destino" id="region_destino" class="form-select" required>
                            <option value="">Seleccione…</option>
                            @foreach ($regiones as $code => $nombre)
                                <option value="{{ $code }}" @selected((string) $regionSel === (string) $code)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 destino-nacional-wrap">
                        <label class="form-label required" for="comuna_destino_id">Comuna / ciudad destino</label>
                        <select name="comuna_destino_id" id="comuna_destino_id" class="form-select" required>
                            <option value="">Seleccione…</option>
                        </select>
                    </div>
                    @if($origenAc)
                        <div class="col-md-4 destino-extranjero-wrap d-none">
                            <label class="form-label required" for="pais_destino">País destino</label>
                            <input type="text" name="pais_destino" id="pais_destino" class="form-control" value="{{ old('pais_destino', $cometido->pais_destino) }}" maxlength="120">
                        </div>
                        <div class="col-md-4 destino-extranjero-wrap d-none">
                            <label class="form-label required" for="ciudad_destino_extranjero">Ciudad destino extranjero</label>
                            <input type="text" name="ciudad_destino_extranjero" id="ciudad_destino_extranjero" class="form-control" value="{{ old('ciudad_destino_extranjero', $cometido->ciudad_destino_extranjero) }}" maxlength="160">
                        </div>
                    @endif
                    <div class="col-md-4">
                        <label class="form-label required" for="institucion_destino">Institución destino</label>
                        <input type="text" name="institucion_destino" id="institucion_destino" class="form-control" value="{{ old('institucion_destino', $cometido->institucion_destino) }}" maxlength="255" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label required" for="destino">Dirección / destino específico</label>
                        <input type="text" name="destino" id="destino" class="form-control" value="{{ old('destino', $cometido->destino) }}" maxlength="255" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required" for="fecha_desde">Fecha desde</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="{{ old('fecha_desde', optional($cometido->fecha_desde)->format('Y-m-d')) }}" required>
                        <div class="alert alert-warning py-2 px-3 mt-2 mb-0 d-none small" id="fechaDesdePlazoAlert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            La solicitud contempla compra de pasajes aéreos y fue ingresada con menos de 7 días hábiles de anticipación. Debe justificar la urgencia o excepcionalidad del cometido.
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required" for="fecha_hasta">Fecha hasta</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="{{ old('fecha_hasta', optional($cometido->fecha_hasta)->format('Y-m-d')) }}" min="{{ old('fecha_desde', optional($cometido->fecha_desde)->format('Y-m-d')) }}" required>
                        <div class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none small" id="fechaHastaOrdenAlert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            La fecha hasta no puede ser anterior a la fecha desde. Selecciona una fecha igual o posterior al inicio del cometido.
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required" for="hora_salida">Hora salida</label>
                        <input type="time" name="hora_salida" id="hora_salida" class="form-control" value="{{ old('hora_salida', $cometido->hora_salida ? substr((string) $cometido->hora_salida, 0, 5) : '') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required" for="hora_regreso">Hora regreso</label>
                        <input type="time" name="hora_regreso" id="hora_regreso" class="form-control" value="{{ old('hora_regreso', $cometido->hora_regreso ? substr((string) $cometido->hora_regreso, 0, 5) : '') }}" required>
                    </div>
                </div>
            </div>

            <div id="section-transporte" class="card shadow-sm mb-4 cometido-form-card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-bus-front"></i></span>3. Transporte y motivo</h2>
                </div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label required">Medios de transporte</label>
                        <div class="row g-2">
                            @foreach ($mediosTransporte as $medio)
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input medio-check" type="checkbox" name="medios_transporte[]" id="medio_{{ $loop->index }}" value="{{ $medio }}" @checked(in_array($medio, $selectedMedios, true))>
                                        <label class="form-check-label" for="medio_{{ $loop->index }}">{{ $medio }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @if($origenAc)
                        <div class="col-md-6 d-none" id="tipoPasajeAereoWrap">
                            <label class="form-label required" for="tipo_pasaje_aereo">Tipo de pasaje aéreo requerido</label>
                            <select name="tipo_pasaje_aereo" id="tipo_pasaje_aereo" class="form-select @error('tipo_pasaje_aereo') is-invalid @enderror">
                                <option value="">Seleccione...</option>
                                @foreach($tiposPasajeAereo as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($tipoPasajeAereoSel === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Este campo es obligatorio sólo cuando se selecciona Avión como medio de transporte.</div>
                            @error('tipo_pasaje_aereo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-none" id="justificacionMenor7Wrap">
                            <div class="alert alert-warning py-2 px-3 small mb-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                La solicitud contempla compra de pasajes aéreos y fue ingresada con menos de 7 días hábiles de anticipación. Debe justificar la urgencia o excepcionalidad del cometido.
                            </div>
                            <label class="form-label required" for="justificacion_menor_7_dias">Justificación por solicitud fuera de plazo</label>
                            <textarea name="justificacion_menor_7_dias" id="justificacion_menor_7_dias" class="form-control @error('justificacion_menor_7_dias') is-invalid @enderror" rows="3" maxlength="5000">{{ old('justificacion_menor_7_dias', $cometido->justificacion_menor_7_dias) }}</textarea>
                            <div class="form-text">Este campo sólo es obligatorio cuando se selecciona Avión y el cometido inicia con menos de 7 días hábiles de anticipación.</div>
                            @error('justificacion_menor_7_dias')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    <div class="col-md-6 d-none" id="medioOtroWrap">
                        <label class="form-label" for="medio_transporte_otro">Especifique otro medio</label>
                        <input type="text" name="medio_transporte_otro" id="medio_transporte_otro" class="form-control" value="{{ old('medio_transporte_otro', $cometido->medio_transporte_otro) }}" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="motivo">Propósito / motivo</label>
                        <select name="motivo" id="motivo" class="form-select" required>
                            <option value="">Seleccione…</option>
                            @foreach ($motivos as $motivo)
                                <option value="{{ $motivo }}" @selected(old('motivo', $cometido->motivo) === $motivo)>{{ $motivo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="motivoOtroWrap">
                        <label class="form-label" for="motivo_otro">Especifique motivo</label>
                        <input type="text" name="motivo_otro" id="motivo_otro" class="form-control" value="{{ old('motivo_otro', $cometido->motivo_otro) }}" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="descripcion_actividades">Descripción de actividades a realizar</label>
                        <textarea name="descripcion_actividades" id="descripcion_actividades" class="form-control" rows="5" minlength="20" maxlength="3000" required>{{ old('descripcion_actividades', $cometido->descripcion_actividades) }}</textarea>
                    </div>
                </div>
            </div>

            <div id="section-citacion" class="card shadow-sm mb-4 cometido-form-card">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-cash-coin"></i></span>4. Citación y solicitud de gasto</h2>
                </div>
                <div class="card-body row g-3">
                    <input type="hidden" name="existe_citacion_invitacion" id="existe_citacion_invitacion" value="1">
                    <div class="col-md-6" id="archivoCitacionWrap">
                        <label class="form-label required" for="archivo_citacion_invitacion">Archivo de citación o invitación</label>
                        <input type="file" name="archivo_citacion_invitacion" id="archivo_citacion_invitacion" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="form-text">La citación o invitación se considera obligatoria para respaldar el cometido funcionario.</div>
                        @if ($cometido->archivo_citacion_invitacion_nombre)
                            <div class="form-text d-flex flex-wrap align-items-center gap-2">
                                <span>Actual: {{ $cometido->archivo_citacion_invitacion_nombre }}</span>
                                @if ($citacionActual)
                                    <a href="{{ route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $citacionActual]) }}" target="_blank" class="btn cometido-form-btn is-secondary btn-sm">
                                        <i class="bi bi-eye"></i> Previsualizar
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6 d-none" id="viaticoWrap">
                        <label class="form-label required">¿Corresponde viático?</label>
                        @if($funcionarioAcSinGrado)
                            <input type="hidden" name="solicita_viatico" value="0">
                            <div class="alert alert-warning border-0 py-2 px-3 mb-0" id="viaticoHelpText">
                                <div class="fw-bold"><i class="bi bi-info-circle me-1"></i> No corresponde viático</div>
                                <div class="small">Funcionario de Administración Central sin grado registrado. Sólo puede solicitar devolución de gastos / reembolso.</div>
                            </div>
                        @else
                            <input type="hidden" name="solicita_viatico" value="0" id="viatico_forzado_no" disabled>
                            <div class="alert alert-warning border-0 py-2 px-3 mb-0 d-none" id="viaticoBloqueadoConglomerado">
                                <div class="fw-bold"><i class="bi bi-info-circle me-1"></i> No corresponde viático</div>
                                <div class="small">{{ $fundamentoSinViaticoConglomeradoAc }}</div>
                            </div>
                            <div class="d-flex gap-3" id="viaticoSelectorRadios">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="solicita_viatico" id="viatico_si" value="1" @checked(old('solicita_viatico', $cometido->solicita_viatico) == 1)>
                                    <label class="form-check-label" for="viatico_si">Sí</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="solicita_viatico" id="viatico_no" value="0" @checked((string) old('solicita_viatico', $cometido->exists ? (int) $cometido->solicita_viatico : '') === '0')>
                                    <label class="form-check-label" for="viatico_no">No</label>
                                </div>
                            </div>
                            <div class="form-text" id="viaticoHelpText">Sólo se habilita para funcionarios registrados con viático por anexo de contrato y cuando la comuna de destino es distinta a la comuna de origen.</div>
                        @endif
                    </div>
                    <div class="col-md-6 d-none" id="alojamientoWrap">
                        <label class="form-label required">¿El servicio o invitación contempla alojamiento?</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contempla_alojamiento" id="alojamiento_si" value="1" @checked(old('contempla_alojamiento', $cometido->contempla_alojamiento) == 1)>
                                <label class="form-check-label" for="alojamiento_si">Sí</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contempla_alojamiento" id="alojamiento_no" value="0" @checked((string) old('contempla_alojamiento', $cometido->exists ? (int) $cometido->contempla_alojamiento : '0') === '0')>
                                <label class="form-check-label" for="alojamiento_no">No</label>
                            </div>
                        </div>
                        <div class="form-text">Si el cometido dura más de un día y la respuesta es Sí, el viático se calculará al 40% por cada día. Si es No, se mantiene el cálculo normal.</div>
                    </div>
                    <div class="col-md-6 d-none" id="colacionWrap">
                        <label class="form-label required">¿El servicio contempla colación?</label>
                        <div class="d-flex gap-3 flex-wrap">
                            @foreach(['si' => 'Sí', 'no' => 'No', 'no_informado' => 'No informado'] as $valorColacion => $labelColacion)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="servicio_contempla_colacion" id="colacion_{{ $valorColacion }}" value="{{ $valorColacion }}" @checked(old('servicio_contempla_colacion', $cometido->servicio_contempla_colacion ?? 'no_informado') === $valorColacion)>
                                    <label class="form-check-label" for="colacion_{{ $valorColacion }}">{{ $labelColacion }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text">Si el servicio contempla colación, el cálculo de viático utilizará el valor 60% parametrizado en el mantenedor de Viáticos y Reembolsos.</div>
                    </div>

                    <div class="col-md-6 d-none" id="anticipoWrap">
                        <label class="form-label required">¿Solicita anticipo de viático?</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="solicita_anticipo_viatico" id="anticipo_si" value="1" @checked(old('solicita_anticipo_viatico', $cometido->solicita_anticipo_viatico ?? false) == 1)>
                                <label class="form-check-label" for="anticipo_si">Sí</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="solicita_anticipo_viatico" id="anticipo_no" value="0" @checked((string) old('solicita_anticipo_viatico', $cometido->exists ? (int) ($cometido->solicita_anticipo_viatico ?? false) : '0') === '0')>
                                <label class="form-check-label" for="anticipo_no">No</label>
                            </div>
                        </div>
                        <div class="form-text">Disponible sólo para cometidos con derecho a viático de 3 días o más. El anticipo equivale al 60% del viático calculado.</div>
                    </div>

                    <div class="col-md-6" id="reembolsoWrap">
                        <label class="form-label required">¿Solicita devolución de gastos / reembolso?</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="solicita_reembolso" id="reembolso_si" value="1" @checked(old('solicita_reembolso', $cometido->solicita_reembolso) == 1) required>
                                <label class="form-check-label" for="reembolso_si">Sí</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="solicita_reembolso" id="reembolso_no" value="0" @checked((string) old('solicita_reembolso', $cometido->exists ? (int) $cometido->solicita_reembolso : '') === '0') required>
                                <label class="form-check-label" for="reembolso_no">No</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="section-datos-bancarios" class="card shadow-sm mb-4 cometido-form-card {{ $mostrarDatosBancariosInicial ? '' : 'd-none' }}">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-bank"></i></span>5. Datos bancarios para pago</h2>
                </div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <div class="cometido-form-bank-note small">
                            <strong>Información requerida para pago:</strong> selecciona o ingresa estos datos cuando la solicitud considere viático y/o reembolso. No se precargan datos del usuario solicitante; deben corresponder a la cuenta de pago informada para este cometido. Serán usados por DAF/Finanzas para gestionar el pago correspondiente.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="banco_pago">Banco</label>
                        <select name="banco_pago" id="banco_pago" class="form-select @error('banco_pago') is-invalid @enderror" data-bank-required>
                            <option value="">Seleccione…</option>
                            @foreach ($bancosPago as $banco)
                                <option value="{{ $banco }}" @selected($bancoPagoSel === $banco)>{{ $banco }}</option>
                            @endforeach
                        </select>
                        @error('banco_pago')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="tipo_cuenta_pago">Tipo de cuenta</label>
                        <select name="tipo_cuenta_pago" id="tipo_cuenta_pago" class="form-select @error('tipo_cuenta_pago') is-invalid @enderror" data-bank-required>
                            <option value="">Seleccione…</option>
                            @foreach ($tiposCuentaPago as $tipoCuenta)
                                <option value="{{ $tipoCuenta }}" @selected($tipoCuentaPagoSel === $tipoCuenta)>{{ $tipoCuenta }}</option>
                            @endforeach
                        </select>
                        @error('tipo_cuenta_pago')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required" for="numero_cuenta_pago">Número de cuenta</label>
                        <input type="text" name="numero_cuenta_pago" id="numero_cuenta_pago" class="form-control @error('numero_cuenta_pago') is-invalid @enderror" value="{{ $numeroCuentaPagoSel }}" maxlength="40" autocomplete="off" data-bank-required>
                        @error('numero_cuenta_pago')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div id="section-documentos" class="card shadow-sm mb-4 cometido-form-card">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-paperclip"></i></span>6. Documentos del cometido</h2>
                        <div class="small text-muted">Para Administración Central el sistema genera el formulario y mantiene la citación/invitación como respaldo obligatorio.</div>
                    </div>
                    @unless($origenAc)
                        <a href="{{ route('tramites.cometidos-funcionarios.plantilla-formulario') }}" class="btn cometido-form-btn is-secondary btn-sm">
                            <i class="bi bi-download"></i> Descargar plantilla formulario 2026
                        </a>
                    @endunless
                </div>
                <div class="card-body row g-3">
                    @if($origenAc)
                        <div class="col-12">
                            <div class="alert alert-info mb-0">El Formulario de Cometido y el Oficio ya no se adjuntan manualmente para Administración Central. El sistema generará automáticamente la Solicitud de Cometido Funcionario en PDF con firma electrónica interna y código de validación.</div>
                        </div>
                    @else
                    <div class="col-md-6">
                        <label class="form-label" for="archivo_oficio">Oficio <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="file" name="archivo_oficio" id="archivo_oficio" class="form-control @error('archivo_oficio') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="form-text">Opcional. Formatos permitidos: PDF, imágenes, DOC o DOCX.</div>
                        @error('archivo_oficio')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if ($oficioActual)
                            <div class="mt-2 cometido-form-doc-current small d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span><i class="bi bi-paperclip"></i> {{ $oficioActual->nombre_original }}</span>
                                <a href="{{ route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $oficioActual]) }}" target="_blank" class="btn cometido-form-btn is-secondary btn-sm">
                                    <i class="bi bi-eye"></i> Previsualizar
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="archivo_formulario_cometido">Formulario de Cometido <span class="text-danger">*</span></label>
                        <input type="file" name="archivo_formulario_cometido" id="archivo_formulario_cometido" class="form-control @error('archivo_formulario_cometido') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="form-text">Obligatorio para enviar. Puedes descargar la plantilla oficial, completarla y adjuntarla aquí.</div>
                        @error('archivo_formulario_cometido')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if ($formularioActual)
                            <div class="mt-2 cometido-form-doc-current small d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span><i class="bi bi-paperclip"></i> {{ $formularioActual->nombre_original }}</span>
                                <a href="{{ route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $formularioActual]) }}" target="_blank" class="btn cometido-form-btn is-secondary btn-sm">
                                    <i class="bi bi-eye"></i> Previsualizar
                                </a>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <div id="section-declaracion" class="card shadow-sm mb-4 border-warning cometido-form-card cometido-form-declaration">
                <div class="card-header bg-warning-subtle">
                    <h2 class="h5 mb-0 cometido-form-section-title"><span class="cometido-form-section-icon"><i class="bi bi-shield-check"></i></span>7. Confirmación de datos y respaldos</h2>
                </div>
                <div class="card-body">
                    <p class="mb-3">
                        Confirmo que los datos ingresados en esta solicitud de cometido funcionario corresponden a la información respaldada en los documentos adjuntos;
                        que dichos antecedentes son coincidentes con los documentos subidos al sistema y que la solicitud se presenta con base en esos respaldos.
                    </p>
                    <div class="form-check">
                        <input class="form-check-input @error('declaracion_aceptada') is-invalid @enderror" type="checkbox" name="declaracion_aceptada" value="1" id="declaracion_aceptada" @checked(old('declaracion_aceptada', $cometido->declaracion_aceptada ?? false))>
                        <label class="form-check-label fw-semibold" for="declaracion_aceptada">
                            Confirmo que los datos ingresados corresponden y son coincidentes con los documentos de respaldo subidos.
                        </label>
                        @error('declaracion_aceptada')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text mt-2">
                        La confirmación es obligatoria para enviar la solicitud a revisión UATP. Puedes guardar un borrador sin marcarla.
                    </div>
                </div>
            </div>

            <div class="cometido-form-info mb-4" id="sendHelpAlert">
                <span class="cometido-form-info-icon"><i class="bi bi-info-circle"></i></span>
                <div>
                    El botón <strong>Enviar solicitud</strong> se habilitará cuando completes los campos obligatorios, adjuntes los documentos requeridos para envío y confirmes que los datos coinciden con los respaldos subidos.
                </div>
            </div>

            <div class="cometido-form-actionbar">
                <a href="{{ route('tramites.cometidos-funcionarios.index') }}" class="btn cometido-form-btn is-secondary">
                    <i class="bi bi-arrow-left"></i> Volver / cancelar
                </a>
                <div class="right-actions">
                    @unless ($enRevisionUatpSinIntervencion)
                        <button type="submit" name="accion" value="borrador" class="btn cometido-form-btn is-secondary">
                            <i class="bi bi-save"></i> Guardar borrador
                        </button>
                    @endunless
                    <button type="submit" name="accion" value="enviar" class="btn cometido-form-btn is-primary" id="btnEnviarSolicitud" disabled>
                        <i class="bi bi-send"></i> {{ $enRevisionUatpSinIntervencion ? 'Guardar cambios' : 'Enviar solicitud' }}
                    </button>
                </div>
            </div>
                </main>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const funcionarios = @json($funcionariosJson);
            const select = document.getElementById('reemplazo_personal_id');
            const search = document.getElementById('funcionarioSearch');
            const comunasByRegion = @json($communesByRegion);
            const oldComuna = @json((string) $comunaSel);
            const oldComunaOrigen = @json((string) $comunaOrigenSel);
            const origenAc = @json($origenAc);
            const funcionarioAcSinGrado = @json($funcionarioAcSinGrado);
            const comunasSinDerechoViaticoAc = @json($comunasSinDerechoViaticoAc);
            const fundamentoSinViaticoConglomeradoAc = @json($fundamentoSinViaticoConglomeradoAc);
            const comunaOrigenEstablecimiento = @json($establecimiento->comuna ?? '');
            const region = document.getElementById('region_destino');
            const comuna = document.getElementById('comuna_destino_id');
            const regionOrigen = document.getElementById('region_origen');
            const comunaOrigen = document.getElementById('comuna_origen_id');
            const esDestinoExtranjero = document.getElementById('es_destino_extranjero');
            const form = document.getElementById('cometidoForm');
            const btnEnviar = document.getElementById('btnEnviarSolicitud');
            const btnEnviarTop = document.getElementById('btnEnviarSolicitudTop');
            const declaracion = document.getElementById('declaracion_aceptada');
            const formularioInput = document.getElementById('archivo_formulario_cometido');
            const formularioExistente = @json((bool) $formularioActual);
            const fechaDesde = document.getElementById('fecha_desde');
            const fechaHasta = document.getElementById('fecha_hasta');
            const fechaDesdePlazoAlert = document.getElementById('fechaDesdePlazoAlert');
            const fechaHastaOrdenAlert = document.getElementById('fechaHastaOrdenAlert');
            const justificacionMenor7Wrap = document.getElementById('justificacionMenor7Wrap');
            const justificacionMenor7Input = document.getElementById('justificacion_menor_7_dias');
            const tipoPasajeAereoWrap = document.getElementById('tipoPasajeAereoWrap');
            const tipoPasajeAereoInput = document.getElementById('tipo_pasaje_aereo');

            function fillFuncionario() {
                if (!select) return;
                const data = funcionarios.find(f => String(f.id) === String(select.value));
                document.getElementById('funcionario_nombre').value = data?.nombre || '';
                document.getElementById('funcionario_rut').value = data?.rut || '';
                document.getElementById('calidad_juridica').value = data?.calidad_juridica || '';
                document.getElementById('estamento').value = data?.estamento || '';
                document.getElementById('cargo_funcion').value = data?.cargo_funcion || '';
                toggleGastoByEstamento(data || null);
            }

            function norm(text) {
                return String(text || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
            }

            function comunaKey(text) {
                const value = norm(text).replace(/[.\-_,;:]+/g, ' ').replace(/\s+/g, ' ').trim();

                if (value === 'STA JUANA' || value === 'SANTA JUANA') {
                    return 'SANTA JUANA';
                }
                if (['ISLA SANTA MARIA', 'ISLA STA MARIA', 'SANTA MARIA', 'STA MARIA'].includes(value)) {
                    return 'ISLA SANTA MARIA';
                }

                return value;
            }

            function isAaee(estamento, cargo) {
                const text = norm(estamento + ' ' + cargo);
                return text.includes('AAEE') || text.includes('ASISTENTE') || text.includes('PARADOCENTE') || text.includes('ADMINISTRATIVO') || text.includes('AUXILIAR');
            }

            function comunaDestinoNombre() {
                const selected = comuna?.selectedOptions?.[0];
                return selected && selected.value ? selected.textContent : '';
            }

            function comunaOrigenNombre() {
                if (origenAc) {
                    const selected = comunaOrigen?.selectedOptions?.[0];
                    return selected && selected.value ? selected.textContent : '';
                }

                return comunaOrigenEstablecimiento;
            }

            function esConglomeradoSinViaticoAc() {
                if (!origenAc || !!esDestinoExtranjero?.checked) return false;
                const origen = comunaKey(comunaOrigenNombre());
                const destino = comunaKey(comunaDestinoNombre());

                return origen !== ''
                    && destino !== ''
                    && comunasSinDerechoViaticoAc.includes(origen)
                    && comunasSinDerechoViaticoAc.includes(destino);
            }

            function puedeSolicitarViatico(data) {
                if (origenAc) return !funcionarioAcSinGrado && !esConglomeradoSinViaticoAc();
                const origen = comunaKey(comunaOrigenEstablecimiento);
                const destino = comunaKey(comunaDestinoNombre());

                return !!data?.viatico_anexo_habilitado
                    && origen !== ''
                    && destino !== ''
                    && destino !== origen;
            }

            function viaticoSeleccionado() {
                return !!document.querySelector('input[name="solicita_viatico"][value="1"]:checked');
            }

            function reembolsoSeleccionado() {
                return !!document.querySelector('input[name="solicita_reembolso"][value="1"]:checked');
            }

            function diasCometidoSeleccionados() {
                const desde = parseLocalDate(document.getElementById('fecha_desde')?.value || '');
                const hasta = parseLocalDate(document.getElementById('fecha_hasta')?.value || '');
                if (!desde || !hasta || hasta < desde) return 0;
                return Math.round((hasta - desde) / 86400000) + 1;
            }

            function toggleAnticipo() {
                const wrap = document.getElementById('anticipoWrap');
                const inputs = wrap?.querySelectorAll('input[name="solicita_anticipo_viatico"]') || [];
                const mostrar = viaticoSeleccionado() && diasCometidoSeleccionados() >= 3;
                wrap?.classList.toggle('d-none', !mostrar);
                inputs.forEach(input => {
                    input.disabled = !mostrar;
                    input.required = mostrar;
                    if (!mostrar) input.checked = input.value === '0';
                });
            }

            function gastoSeleccionado() {
                return viaticoSeleccionado() || reembolsoSeleccionado();
            }

            function avionSeleccionado() {
                return Array.from(document.querySelectorAll('.medio-check')).some(input => input.checked && input.value === 'Avión');
            }

            function toggleDatosBancarios() {
                const section = document.getElementById('section-datos-bancarios');
                const inputs = section?.querySelectorAll('[data-bank-required]') || [];
                const mostrar = gastoSeleccionado();
                section?.classList.toggle('d-none', !mostrar);
                inputs.forEach(input => {
                    input.required = mostrar;
                    input.disabled = !mostrar;
                });
            }

            function toggleAlojamiento(showViatico) {
                const alojamientoWrap = document.getElementById('alojamientoWrap');
                const alojamientoInputs = alojamientoWrap?.querySelectorAll('input[name="contempla_alojamiento"]') || [];
                const colacionWrap = document.getElementById('colacionWrap');
                const colacionInputs = colacionWrap?.querySelectorAll('input[name="servicio_contempla_colacion"]') || [];
                const mostrarAlojamiento = !!showViatico && viaticoSeleccionado();
                alojamientoWrap?.classList.toggle('d-none', !mostrarAlojamiento);
                alojamientoInputs.forEach(input => {
                    input.required = mostrarAlojamiento;
                    input.disabled = !mostrarAlojamiento;
                    if (!mostrarAlojamiento) input.checked = input.value === '0';
                });
                colacionWrap?.classList.toggle('d-none', !mostrarAlojamiento);
                colacionInputs.forEach(input => {
                    input.required = mostrarAlojamiento;
                    input.disabled = !mostrarAlojamiento;
                    if (!mostrarAlojamiento) input.checked = input.value === 'no_informado';
                });
                toggleAnticipo();
                toggleDatosBancarios();
            }

            function toggleGastoByEstamento(data) {
                const viaticoWrap = document.getElementById('viaticoWrap');
                const viaticoInputs = viaticoWrap?.querySelectorAll('input[name="solicita_viatico"]') || [];
                const viaticoForzadoNo = document.getElementById('viatico_forzado_no');
                const alertaConglomerado = document.getElementById('viaticoBloqueadoConglomerado');
                const viaticoSelectorRadios = document.getElementById('viaticoSelectorRadios');
                const bloqueadoPorConglomerado = esConglomeradoSinViaticoAc();
                const showViatico = origenAc ? true : puedeSolicitarViatico(data);
                viaticoWrap?.classList.toggle('d-none', !showViatico);
                alertaConglomerado?.classList.toggle('d-none', !bloqueadoPorConglomerado);
                viaticoSelectorRadios?.classList.toggle('d-none', bloqueadoPorConglomerado);
                if (viaticoForzadoNo) {
                    viaticoForzadoNo.disabled = !bloqueadoPorConglomerado;
                }
                viaticoInputs.forEach(input => {
                    const esRadio = input.type !== 'hidden';
                    input.required = showViatico && !funcionarioAcSinGrado && !bloqueadoPorConglomerado && esRadio;
                    input.disabled = (funcionarioAcSinGrado || bloqueadoPorConglomerado) && esRadio;
                    if (!showViatico || funcionarioAcSinGrado || bloqueadoPorConglomerado) {
                        input.checked = esRadio && input.value === '0';
                    }
                });
                const help = document.getElementById('viaticoHelpText');
                if (help) {
                    if (origenAc && funcionarioAcSinGrado) {
                        help.textContent = 'Funcionario de Administración Central sin grado registrado: no corresponde viático. Sólo puede solicitar devolución de gastos / reembolso.';
                    } else if (origenAc && bloqueadoPorConglomerado) {
                        help.textContent = fundamentoSinViaticoConglomeradoAc;
                    } else if (origenAc) {
                        help.textContent = 'Funcionario de Administración Central con grado registrado: puede corresponder viático según cálculo automático y revisión presupuestaria.';
                    } else {
                        help.textContent = data?.viatico_anexo_habilitado
                            ? 'Funcionario habilitado por anexo de contrato. La casilla se muestra cuando la comuna de destino es distinta a la comuna de origen.'
                            : 'Este funcionario no está registrado como habilitado para viático por anexo de contrato.';
                    }
                }
                toggleAlojamiento(showViatico && !bloqueadoPorConglomerado);
            }

            function filterFuncionarios() {
                if (!search || !select) return;
                const q = norm(search.value);
                Array.from(select.options).forEach(opt => {
                    opt.hidden = q !== '' && !norm(opt.textContent).includes(q);
                });
            }

            function fillComunaSelect(regionEl, comunaEl, selectedValue) {
                if (!regionEl || !comunaEl) return;
                const code = regionEl.value;
                const list = comunasByRegion[code] || [];
                const current = comunaEl.value || selectedValue;
                comunaEl.innerHTML = '<option value="">Seleccione…</option>';
                list.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    if (String(current) === String(c.id)) opt.selected = true;
                    comunaEl.appendChild(opt);
                });
            }

            function rebuildComunas() {
                fillComunaSelect(region, comuna, oldComuna);
                fillComunaSelect(regionOrigen, comunaOrigen, oldComunaOrigen);
                if (!select) return;
                const data = funcionarios.find(f => String(f.id) === String(select.value));
                toggleGastoByEstamento(data || null);
            }

            function toggleDestinoExtranjero() {
                const extranjero = !!esDestinoExtranjero?.checked;
                document.querySelectorAll('.destino-nacional-wrap').forEach(el => el.classList.toggle('d-none', extranjero));
                document.querySelectorAll('.destino-extranjero-wrap').forEach(el => el.classList.toggle('d-none', !extranjero));
                if (region) region.required = !extranjero;
                if (comuna) comuna.required = !extranjero;
                const pais = document.getElementById('pais_destino');
                const ciudad = document.getElementById('ciudad_destino_extranjero');
                if (pais) pais.required = extranjero;
                if (ciudad) ciudad.required = extranjero;
            }

            function toggleOtros() {
                const medioOtro = Array.from(document.querySelectorAll('.medio-check')).some(input => input.checked && input.value === 'Otro');
                document.getElementById('medioOtroWrap')?.classList.toggle('d-none', !medioOtro);
                const medioOtroInput = document.getElementById('medio_transporte_otro');
                if (medioOtroInput) medioOtroInput.required = medioOtro;
                const motivoOtro = document.getElementById('motivo')?.value === 'Otras';
                document.getElementById('motivoOtroWrap')?.classList.toggle('d-none', !motivoOtro);
                const motivoOtroInput = document.getElementById('motivo_otro');
                if (motivoOtroInput) motivoOtroInput.required = motivoOtro;
            }

            function toggleCitacion() {
                const wrap = document.getElementById('archivoCitacionWrap');
                const file = document.getElementById('archivo_citacion_invitacion');
                wrap?.classList.remove('d-none');
                if (file) file.required = !@json((bool) $cometido->archivo_citacion_invitacion_path);
            }

            function documentosEnvioCompletos() {
                if (origenAc) return true;
                const formularioOk = formularioExistente || (formularioInput && formularioInput.files.length > 0);
                return formularioOk;
            }

            function parseLocalDate(value) {
                if (!value) return null;
                const parts = value.split('-').map(Number);
                if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }

            function businessDaysBetween(start, end) {
                if (!start || !end || end <= start) return 0;
                let count = 0;
                const cursor = new Date(start.getFullYear(), start.getMonth(), start.getDate());
                cursor.setDate(cursor.getDate() + 1);
                while (cursor <= end) {
                    const day = cursor.getDay();
                    if (day !== 0 && day !== 6) count++;
                    cursor.setDate(cursor.getDate() + 1);
                }
                return count;
            }

            function actualizarAdvertenciaPlazo() {
                const avion = avionSeleccionado();
                tipoPasajeAereoWrap?.classList.toggle('d-none', !(origenAc && avion));
                if (tipoPasajeAereoInput) {
                    tipoPasajeAereoInput.required = origenAc && avion;
                    tipoPasajeAereoInput.disabled = !(origenAc && avion);
                    if (!(origenAc && avion)) tipoPasajeAereoInput.value = '';
                }
                if (!fechaDesde) return;
                const destino = parseLocalDate(fechaDesde.value);
                const hoy = new Date();
                const inicio = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
                const diasHabiles = businessDaysBetween(inicio, destino);
                const mostrar = origenAc && avion && !!destino && destino >= inicio && diasHabiles < 7;
                fechaDesdePlazoAlert?.classList.toggle('d-none', !mostrar);
                justificacionMenor7Wrap?.classList.toggle('d-none', !mostrar);
                if (justificacionMenor7Input) {
                    justificacionMenor7Input.required = mostrar;
                    justificacionMenor7Input.disabled = !mostrar;
                    if (!mostrar) justificacionMenor7Input.setCustomValidity('');
                }
            }

            function actualizarFechaHastaMinima() {
                if (!fechaDesde || !fechaHasta) return;

                fechaHasta.min = fechaDesde.value || '';

                const desde = parseLocalDate(fechaDesde.value);
                const hasta = parseLocalDate(fechaHasta.value);
                const fechaHastaInvalida = !!desde && !!hasta && hasta < desde;

                if (fechaHastaInvalida) {
                    fechaHasta.value = '';
                    fechaHasta.setCustomValidity('La fecha hasta no puede ser anterior a la fecha desde.');
                    fechaHastaOrdenAlert?.classList.remove('d-none');
                } else {
                    fechaHasta.setCustomValidity('');
                    fechaHastaOrdenAlert?.classList.add('d-none');
                }
            }

            function actualizarBotonEnviar() {
                toggleOtros();
                toggleCitacion();
                const dataGasto = select ? funcionarios.find(f => String(f.id) === String(select.value)) : null;
                toggleGastoByEstamento(dataGasto || null);
                toggleDatosBancarios();
                actualizarAdvertenciaPlazo();
                actualizarFechaHastaMinima();

                const formOk = form ? form.checkValidity() : false;
                const docsOk = documentosEnvioCompletos();
                const declaracionOk = !!declaracion?.checked;
                const puedeEnviar = formOk && docsOk && declaracionOk;

                [btnEnviar, btnEnviarTop].forEach((button) => {
                    if (!button) return;
                    button.disabled = !puedeEnviar;
                    button.title = puedeEnviar ? '' : 'Completa los campos obligatorios, adjunta los documentos requeridos y confirma que los datos coinciden con los respaldos subidos.';
                });
            }

            form?.addEventListener('input', actualizarBotonEnviar);
            form?.addEventListener('change', actualizarBotonEnviar);
            formularioInput?.addEventListener('change', actualizarBotonEnviar);
            declaracion?.addEventListener('change', actualizarBotonEnviar);
            fechaDesde?.addEventListener('change', actualizarBotonEnviar);
            fechaHasta?.addEventListener('change', actualizarBotonEnviar);

            search?.addEventListener('input', filterFuncionarios);
            select?.addEventListener('change', fillFuncionario);
            region?.addEventListener('change', rebuildComunas);
            regionOrigen?.addEventListener('change', rebuildComunas);
            esDestinoExtranjero?.addEventListener('change', () => { toggleDestinoExtranjero(); actualizarBotonEnviar(); });
            comuna?.addEventListener('change', fillFuncionario);
            comunaOrigen?.addEventListener('change', fillFuncionario);
            document.querySelectorAll('.medio-check').forEach(input => input.addEventListener('change', actualizarBotonEnviar));
            document.getElementById('motivo')?.addEventListener('change', toggleOtros);

            rebuildComunas();
            fillFuncionario();
            toggleOtros();
            toggleCitacion();
            toggleDatosBancarios();
            toggleDestinoExtranjero();
            actualizarBotonEnviar();
        });
    </script>
@endsection
