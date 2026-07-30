@extends('layouts.app')

@push('styles')
<style>
    .cf-page-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);overflow:hidden}.cf-page-header__top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1.5rem 1.75rem}.cf-page-header__eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.45rem}.cf-page-header__eyebrow-icon{width:2.75rem;height:2.75rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 100%);color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.28);font-size:1.2rem}.cf-page-header__title{font-size:clamp(1.7rem,2vw,2.2rem);line-height:1.1;font-weight:800;color:#0f172a;margin-bottom:.4rem}.cf-page-header__subtitle{color:#475569;font-size:1rem;margin-bottom:0;max-width:60rem}.cf-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.cf-section-title{font-weight:900;color:#0f172a;display:flex;align-items:center;gap:.5rem;margin-bottom:1rem}.cf-btn-primary,.cf-btn-secondary,.cf-btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.cf-btn-primary{background:#2563eb;color:#fff}.cf-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.cf-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.cf-choice{border:1px solid #dbeafe;border-radius:18px;background:#fff;padding:1rem;height:100%;transition:.15s}.cf-choice:hover,.cf-choice.is-active{border-color:#2563eb;box-shadow:0 14px 32px rgba(37,99,235,.13)}.cf-choice-title{font-weight:900;color:#0f172a}.cf-muted{color:#64748b}.cf-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.35rem .65rem;font-size:.78rem;font-weight:800;background:#eef2ff;color:#3730a3}.form-label{font-weight:800;color:#334155}.form-control,.form-select{border-radius:12px;border-color:#cbd5e1}.invalid-feedback.d-block{font-weight:700}
</style>
@endpush

@php
    $datos = $extracted['datos'] ?? [];
    $tipoDocumento = $tipoDocumento ?: old('tipo_documento_ingreso');
    $isDigital = $tipoDocumento === 'digital';
    $isEscaneada = $tipoDocumento === 'escaneada';
@endphp

@section('content')
<div class="container-fluid py-4">
    <div class="cf-page-header mb-4">
        <div class="cf-page-header__top">
            <div>
                <div class="cf-page-header__eyebrow"><span class="cf-page-header__eyebrow-icon"><i class="bi bi-heart-pulse"></i></span> Nueva licencia médica</div>
                <h1 class="cf-page-header__title">Ingreso de licencia médica</h1>
                <p class="cf-page-header__subtitle">Seleccione si la licencia es digital para extraer datos desde el PDF o escaneada para registro manual con respaldo adjunto.</p>
            </div>
            <a href="{{ route('tramites.licencias-medicas.index') }}" class="cf-btn-outline"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 shadow-sm">
            <strong>Revise los datos antes de guardar:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="cf-panel mb-4">
        <div class="cf-section-title"><i class="bi bi-ui-checks-grid"></i> 1. Tipo de documento</div>
        <div class="row g-3">
            <div class="col-md-6">
                <a class="text-decoration-none" href="{{ route('tramites.licencias-medicas.create', ['tipo_documento' => 'digital']) }}">
                    <div class="cf-choice {{ $isDigital ? 'is-active' : '' }}">
                        <div class="cf-choice-title"><i class="bi bi-filetype-pdf text-primary"></i> Licencia médica digital</div>
                        <div class="cf-muted mt-1">El sistema intenta extraer folio, RUT, fechas, días y datos principales desde texto embebido en PDF. El PDF digital queda almacenado como respaldo.</div>
                    </div>
                </a>
            </div>
            <div class="col-md-6">
                <a class="text-decoration-none" href="{{ route('tramites.licencias-medicas.create', ['tipo_documento' => 'escaneada']) }}">
                    <div class="cf-choice {{ $isEscaneada ? 'is-active' : '' }}">
                        <div class="cf-choice-title"><i class="bi bi-file-earmark-image text-warning"></i> Licencia médica escaneada</div>
                        <div class="cf-muted mt-1">El ingreso será manual. El archivo escaneado se guarda como respaldo documental, sin OCR externo ni instalación adicional en cPanel.</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    @if($isDigital && empty($archivoTemporal))
        <div class="cf-panel mb-4">
            <div class="cf-section-title"><i class="bi bi-magic"></i> 2. Cargar PDF digital para extracción</div>
            <form method="POST" action="{{ route('tramites.licencias-medicas.extraer-digital') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-8"><label class="form-label">PDF licencia médica digital</label><input type="file" name="archivo_licencia" accept="application/pdf" class="form-control" required></div>
                <div class="col-md-4"><button type="submit" class="cf-btn-primary w-100"><i class="bi bi-magic"></i> Extraer datos</button></div>
            </form>
        </div>
    @endif

    @if($isDigital && !empty($archivoTemporal))
        <div class="alert alert-info rounded-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <div class="fw-bold mb-1"><i class="bi bi-magic"></i> Extracción PDF digital</div>
                    <div>Estado: <strong>{{ $extracted['estado'] ?? 'no informado' }}</strong> · Confianza: <strong>{{ $extracted['confianza'] ?? 'no informada' }}</strong>.</div>
                    <div class="small text-muted mt-1">Revise visualmente los campos precargados. El PDF digital quedará almacenado como respaldo documental al guardar.</div>
                </div>
                <form method="POST" action="{{ route('tramites.licencias-medicas.descartar-carga') }}" class="m-0">
                    @csrf
                    <input type="hidden" name="tipo_documento" value="digital">
                    <button type="submit" class="cf-btn-outline" onclick="return confirm('¿Descartar la carga actual y limpiar los datos extraídos?');">
                        <i class="bi bi-arrow-counterclockwise"></i> Descartar carga
                    </button>
                </form>
            </div>
            @if(!empty($extracted['advertencias']))
                <ul class="mb-0 mt-2 small">
                    @foreach($extracted['advertencias'] as $advertencia)
                        <li>{{ $advertencia }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if($isDigital && !empty($archivoTemporal))
        <form id="form-descartar-carga-licencia" method="POST" action="{{ route('tramites.licencias-medicas.descartar-carga') }}" class="d-none">
            @csrf
            <input type="hidden" name="tipo_documento" value="digital">
        </form>
    @endif

    @if($isDigital || $isEscaneada)
        <form method="POST" action="{{ route('tramites.licencias-medicas.store') }}" enctype="multipart/form-data" class="cf-panel">
            @csrf
            <input type="hidden" name="tipo_documento_ingreso" value="{{ $isDigital ? 'digital' : 'escaneada' }}">
            @if(!empty($archivoTemporal))<input type="hidden" name="archivo_temporal_path" value="{{ $archivoTemporal['path'] ?? '' }}">@endif

            <div class="cf-section-title"><i class="bi bi-clipboard2-pulse"></i> 2. Datos administrativos de la licencia</div>
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label">Tipo ingreso</label><select name="tipo_ingreso_licencia" class="form-select" required><option value="">Seleccione</option>@foreach(['1','2','3','4'] as $t)<option value="{{ $t }}" @selected(old('tipo_ingreso_licencia', $datos['tipo_ingreso_licencia'] ?? '')==$t)>{{ $t }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Cuerpo licencia</label><input type="text" name="cuerpo_licencia" class="form-control" value="{{ old('cuerpo_licencia', $datos['cuerpo_licencia'] ?? '') }}" required></div>
                <div class="col-md-2"><label class="form-label">DV licencia</label><input type="text" name="dv_licencia" maxlength="1" class="form-control text-uppercase" value="{{ old('dv_licencia', $datos['dv_licencia'] ?? '') }}" required></div>
                <div class="col-md-4"><label class="form-label">Estado inicial</label><input type="text" name="estado_actual" class="form-control" value="{{ old('estado_actual', $datos['estado_actual'] ?? 'Ingresada') }}"></div>

                <div class="col-md-4"><label class="form-label">RUT funcionario</label><input type="text" name="rut_funcionario_input" class="form-control" value="{{ old('rut_funcionario_input', $datos['rut_formateado'] ?? '') }}" required></div>
                <div class="col-md-8"><label class="form-label">Nombre funcionario</label><input type="text" name="nombre_funcionario" class="form-control" value="{{ old('nombre_funcionario', $datos['nombre_funcionario'] ?? '') }}" required></div>

                <div class="col-md-3"><label class="form-label">Sexo</label><input type="text" name="sexo" class="form-control" value="{{ old('sexo', $datos['sexo'] ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label">Edad</label><input type="number" name="edad" class="form-control" value="{{ old('edad', $datos['edad'] ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label">Fecha emisión</label><input type="date" name="fecha_emision" class="form-control" value="{{ old('fecha_emision', $datos['fecha_emision'] ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label">Fecha recepción</label><input type="date" name="fecha_recepcion" class="form-control" value="{{ old('fecha_recepcion', $datos['fecha_recepcion'] ?? '') }}"></div>

                <div class="col-md-3"><label class="form-label">Fecha inicio</label><input type="date" name="fecha_inicio" class="form-control" value="{{ old('fecha_inicio', $datos['fecha_inicio'] ?? '') }}" required></div>
                <div class="col-md-3"><label class="form-label">Fecha término</label><input type="date" name="fecha_termino" class="form-control" value="{{ old('fecha_termino', $datos['fecha_termino'] ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label">Días solicitados</label><input type="number" name="dias_solicitados" min="1" max="365" class="form-control" value="{{ old('dias_solicitados', $datos['dias_solicitados'] ?? '') }}"></div>
                <div class="col-md-3"><label class="form-label">Tipo licencia</label><select name="tipo_licencia" class="form-select"><option value="">Seleccione</option>@foreach(['1'=>'Enf. común','2'=>'Med. preventiva','3'=>'Maternal','4'=>'Hijo menor 1 año','5'=>'Acc. trabajo/trayecto','6'=>'Enf. profesional','7'=>'Patología embarazo'] as $k=>$v)<option value="{{ $k }}" @selected(old('tipo_licencia', $datos['tipo_licencia'] ?? '')==$k)>{{ $k }} - {{ $v }}</option>@endforeach</select></div>
                <input type="hidden" name="tipo_licencia_glosa" value="{{ old('tipo_licencia_glosa', $datos['tipo_licencia_glosa'] ?? '') }}">

                <div class="col-md-4">
                    <label class="form-label">Sistema de salud</label>
                    <select name="sistema_salud" id="sistema_salud" class="form-select">
                        <option value="">Seleccione</option>
                        <option value="FONASA" @selected(old('sistema_salud', $datos['sistema_salud'] ?? '') === 'FONASA')>FONASA</option>
                        <option value="ISAPRE" @selected(old('sistema_salud', $datos['sistema_salud'] ?? '') === 'ISAPRE')>ISAPRE</option>
                    </select>
                    <div class="form-text">Para ISAPRE se debe indicar la institución de salud.</div>
                </div>
                <div class="col-md-4" id="institucion_salud_wrap">
                    <label class="form-label">Institución de salud / ISAPRE</label>
                    <input type="text" name="institucion_salud" id="institucion_salud" class="form-control" value="{{ old('institucion_salud', $datos['institucion_salud'] ?? '') }}" placeholder="Ej.: FONASA, Consalud, Banmédica, Cruz Blanca">
                </div>

                <div class="col-md-4"><label class="form-label">Tipo reposo</label><input type="text" name="tipo_reposo" class="form-control" value="{{ old('tipo_reposo', $datos['tipo_reposo'] ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Lugar reposo</label><input type="text" name="lugar_reposo" class="form-control" value="{{ old('lugar_reposo', $datos['lugar_reposo'] ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Teléfono</label><input type="text" name="telefono" class="form-control" value="{{ old('telefono', $datos['telefono'] ?? '') }}"></div>
                <div class="col-md-8"><label class="form-label">Dirección reposo</label><input type="text" name="direccion_reposo" class="form-control" value="{{ old('direccion_reposo', $datos['direccion_reposo'] ?? '') }}"></div>
                <div class="col-md-4"><label class="form-label">Correo trabajador</label><input type="email" name="correo_trabajador" class="form-control" value="{{ old('correo_trabajador', $datos['correo_trabajador'] ?? '') }}"></div>

                <div class="col-md-4"><label class="form-label">Establecimiento/dependencia manual si no cruza AC ni padrón</label><input type="text" name="establecimiento_nombre" class="form-control" value="{{ old('establecimiento_nombre') }}"></div>
                <div class="col-md-4"><label class="form-label">Comuna manual si no cruza padrón</label><input type="text" name="comuna" class="form-control" value="{{ old('comuna') }}"></div>
                <div class="col-md-4"><label class="form-label">Estamento/escalafón manual</label><input type="text" name="estamento" class="form-control" value="{{ old('estamento') }}"></div>

                @if($isEscaneada)
                    <div class="col-12"><label class="form-label">Archivo escaneado de respaldo</label><input type="file" name="archivo_licencia" accept="application/pdf,image/jpeg,image/png" class="form-control" required><div class="form-text">Se guarda como respaldo obligatorio. No se ejecuta OCR ni herramientas externas en cPanel.</div></div>
                @endif
                @if($isDigital && !empty($archivoTemporal))
                    <div class="col-12"><span class="cf-pill"><i class="bi bi-paperclip"></i> PDF digital cargado: {{ $archivoTemporal['nombre'] ?? 'licencia.pdf' }}</span></div>
                @endif

                <div class="col-12"><label class="form-label">Observaciones</label><textarea name="observaciones" rows="3" class="form-control">{{ old('observaciones') }}</textarea></div>
            </div>

            <div class="d-flex justify-content-between gap-2 mt-4 flex-wrap">
                <div>
                    @if($isDigital && !empty($archivoTemporal))
                        <button type="submit"
                                form="form-descartar-carga-licencia"
                                class="cf-btn-outline"
                                onclick="return confirm('¿Descartar la carga actual y limpiar el formulario?');">
                            <i class="bi bi-arrow-counterclockwise"></i> Descartar carga y rehacer
                        </button>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('tramites.licencias-medicas.index') }}" class="cf-btn-outline">Cancelar</a>
                    <button class="cf-btn-primary" type="submit"><i class="bi bi-save"></i> Guardar ingreso</button>
                </div>
            </div>
        </form>
    @endif
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sistema = document.getElementById('sistema_salud');
    const wrap = document.getElementById('institucion_salud_wrap');
    const institucion = document.getElementById('institucion_salud');

    function syncInstitucionSalud() {
        if (!sistema || !wrap || !institucion) return;

        if (sistema.value === 'ISAPRE') {
            wrap.style.display = '';
            institucion.required = true;
            institucion.placeholder = 'Ej.: Consalud, Banmédica, Cruz Blanca, Colmena';
        } else if (sistema.value === 'FONASA') {
            wrap.style.display = '';
            institucion.required = false;
            if (!institucion.value) institucion.value = 'FONASA';
            institucion.placeholder = 'FONASA';
        } else {
            wrap.style.display = '';
            institucion.required = false;
            institucion.placeholder = 'Ej.: FONASA, Consalud, Banmédica, Cruz Blanca';
        }
    }

    if (sistema) {
        sistema.addEventListener('change', function () {
            if (sistema.value === 'ISAPRE' && institucion.value === 'FONASA') {
                institucion.value = '';
            }
            syncInstitucionSalud();
        });
        syncInstitucionSalud();
    }
});
</script>
@endpush

@endsection
