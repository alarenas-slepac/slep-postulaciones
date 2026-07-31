@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
@php
    $editando = isset($reporte) && $reporte;
    $servicioValores = old('servicios', $editando ? $reporte->servicios->pluck('estado', 'servicio')->all() : array_fill_keys(array_keys(config('centro_operaciones.servicios')), 'operativo'));
    $afectacionValores = old('afectaciones', $editando ? $reporte->afectaciones->pluck('tipo')->all() : []);
    $incidenciaValores = old('incidencias', $editando ? $reporte->incidencias->pluck('tipo')->all() : []);
    $funcionamiento = old('funcionamiento', $editando ? $reporte->funcionamiento : 'si');
    $prioridad = old('prioridad', $editando ? $reporte->prioridad : 'sin_novedad');
    $estadoClases = ['operativo' => 'success', 'alerta' => 'warning', 'critico' => 'danger'];
@endphp
<div class="co-shell co-report-shell" data-co-report-form>
    <header class="co-hero co-hero--report">
        <div class="co-establishment-identity">
            <div class="co-establishment-logo">
                @if($perfilAdmision?->logoUrl())
                    <img src="{{ $perfilAdmision->logoUrl() }}" alt="Logo de {{ $establecimiento->nombre_establecimiento }}">
                @else
                    <i class="bi bi-building" aria-hidden="true"></i>
                @endif
            </div>
            <div class="co-establishment-copy">
                <div class="co-eyebrow">Centro de Operaciones SLEP</div>
                <h1>{{ $editando ? 'Editar reporte diario' : 'Reporte diario del establecimiento' }}</h1>
                <p>La información enviada alimentará inmediatamente el consolidado territorial.</p>
                <div class="co-establishment-director">
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                    <span>Director/a</span>
                    <strong>{{ $perfilAdmision?->director_nombre ?: 'No informado en Admisión Escolar' }}</strong>
                </div>
            </div>
        </div>
        <div class="co-report-context">
            <span><i class="bi bi-calendar3"></i>{{ $hoy->translatedFormat('d \d\e F \d\e Y') }}</span>
            <span><i class="bi bi-building"></i>{{ $establecimiento->nombre_establecimiento }}</span>
            <span><i class="bi bi-geo-alt"></i>{{ $establecimiento->comuna ?: 'Sin comuna' }}</span>
            <span><i class="bi bi-person-badge"></i>{{ auth()->user()->nombre_completo ?? auth()->user()->name }}</span>
        </div>
    </header>

    @if($errors->any())
        <div class="alert alert-danger"><strong>Revisa la información antes de enviar.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $editando ? route('centro-operaciones.reportes.update', $reporte) : route('centro-operaciones.reportes.store') }}">
        @csrf
        @if($editando) @method('PUT') @endif

        <section class="co-card co-form-section">
            <div class="co-section-title"><span>1</span><div><h2>Estado operacional</h2><p>Selecciona el estado actual de cada servicio.</p></div></div>
            <div class="co-service-cards">
            @foreach(config('centro_operaciones.servicios') as $codigo => $servicio)
                <fieldset class="co-service-card">
                    <legend><i class="bi {{ $servicio['icon'] }}"></i>{{ $servicio['label'] }}</legend>
                    <div class="co-segmented co-segmented--service">
                    @foreach(config('centro_operaciones.estados_servicio') as $estado => $label)
                        <label class="co-choice co-choice--{{ $estado }}">
                            <input type="radio" name="servicios[{{ $codigo }}]" value="{{ $estado }}" @checked(($servicioValores[$codigo] ?? null) === $estado) required>
                            <span><i></i>{{ $label }}</span>
                        </label>
                    @endforeach
                    </div>
                    <input class="form-control form-control-sm mt-2" name="servicio_observaciones[{{ $codigo }}]" value="{{ old("servicio_observaciones.$codigo", $editando ? optional($reporte->servicios->firstWhere('servicio', $codigo))->observacion : '') }}" placeholder="Observación opcional">
                </fieldset>
            @endforeach
            </div>
        </section>

        <section class="co-card co-form-section">
            <div class="co-section-title"><span>2</span><div><h2>Funcionamiento</h2><p>Indica la condición general y las afectaciones vigentes.</p></div></div>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="co-option-grid co-option-grid--3">
                    @foreach(config('centro_operaciones.funcionamientos') as $codigo => $opcion)
                        <label class="co-big-choice co-big-choice--{{ $opcion['severity'] }}">
                            <input type="radio" name="funcionamiento" value="{{ $codigo }}" @checked($funcionamiento === $codigo) required>
                            <span><i class="bi {{ $codigo === 'si' ? 'bi-check-circle' : ($codigo === 'no' ? 'bi-x-circle' : 'bi-exclamation-circle') }}"></i><strong>{{ $opcion['label'] }}</strong><small>{{ $opcion['description'] }}</small></span>
                        </label>
                    @endforeach
                    </div>
                </div>
                <div class="col-lg-5">
                    <label class="form-label fw-semibold">Afectaciones (selecciona las que correspondan)</label>
                    <div class="co-check-grid">
                    @foreach(config('centro_operaciones.afectaciones') as $codigo => $opcion)
                        <label class="form-check"><input class="form-check-input" type="checkbox" name="afectaciones[]" value="{{ $codigo }}" @checked(in_array($codigo, $afectacionValores, true))><span class="form-check-label">{{ $opcion['label'] }}</span></label>
                    @endforeach
                    </div>
                    <input class="form-control mt-2" name="afectacion_otro" value="{{ old('afectacion_otro', $editando ? optional($reporte->afectaciones->firstWhere('tipo', 'otro'))->detalle : '') }}" placeholder="Describe otra afectación">
                </div>
            </div>
        </section>

        <section class="co-card co-form-section">
            <div class="co-section-title"><span>3</span><div><h2>Asistencia</h2><p>Registra las personas presentes; los totales provienen de los padrones del sistema.</p></div></div>
            <div class="co-attendance-grid">
                <div class="co-attendance-card">
                    <i class="bi bi-mortarboard"></i><h3>Estudiantes</h3>
                    <div class="co-total"><span>Matrícula total</span><strong>{{ number_format($datosBase['matricula']['total'], 0, ',', '.') }}</strong></div>
                    <label>Presentes<input type="number" name="estudiantes_presentes" min="0" max="{{ $datosBase['matricula']['total'] }}" value="{{ old('estudiantes_presentes', $editando ? $reporte->estudiantes_presentes : '') }}" required data-attendance data-total="{{ $datosBase['matricula']['total'] }}"></label>
                    <small>Asistencia: <b data-attendance-result>—</b></small>
                </div>
                <div class="co-attendance-card">
                    <i class="bi bi-person-video3"></i><h3>Docentes</h3>
                    <div class="co-total"><span>Dotación total</span><strong>{{ number_format($datosBase['dotacion']['docentes'], 0, ',', '.') }}</strong></div>
                    <label>Presentes<input type="number" name="docentes_presentes" min="0" max="{{ $datosBase['dotacion']['docentes'] }}" value="{{ old('docentes_presentes', $editando ? $reporte->docentes_presentes : '') }}" required data-attendance data-total="{{ $datosBase['dotacion']['docentes'] }}"></label>
                    <small>Asistencia: <b data-attendance-result>—</b></small>
                </div>
                <div class="co-attendance-card">
                    <i class="bi bi-people"></i><h3>Asistentes de la educación</h3>
                    <div class="co-total"><span>Dotación total</span><strong>{{ number_format($datosBase['dotacion']['asistentes'], 0, ',', '.') }}</strong></div>
                    <label>Presentes<input type="number" name="asistentes_presentes" min="0" max="{{ $datosBase['dotacion']['asistentes'] }}" value="{{ old('asistentes_presentes', $editando ? $reporte->asistentes_presentes : '') }}" required data-attendance data-total="{{ $datosBase['dotacion']['asistentes'] }}"></label>
                    <small>Asistencia: <b data-attendance-result>—</b></small>
                </div>
            </div>
            <div class="co-source-note"><i class="bi bi-info-circle"></i>Matrícula: {{ $datosBase['matricula']['fuente'] === 'establecimientos.matricula_total' ? 'ficha del establecimiento' : 'suma de cursos activos del año' }}. Dotación: padrón {{ $datosBase['dotacion']['periodo'] ?: 'sin período disponible' }}.</div>
        </section>

        <section class="co-card co-form-section">
            <div class="co-section-title"><span>4</span><div><h2>Incidentes del día</h2><p>Cada reporte agrega nuevas incidencias al consolidado.</p></div></div>
            <div class="co-incident-grid">
            @foreach(config('centro_operaciones.incidencias') as $codigo => $incidencia)
                <div class="co-incident-option">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="incidencias[]" value="{{ $codigo }}" @checked(in_array($codigo, $incidenciaValores, true))><span class="form-check-label">{{ $incidencia['label'] }}</span></label>
                    <input class="form-control form-control-sm" name="incidencia_detalles[{{ $codigo }}]" value="{{ old("incidencia_detalles.$codigo", $editando ? optional($reporte->incidencias->firstWhere('tipo', $codigo))->descripcion : '') }}" placeholder="Detalle opcional">
                </div>
            @endforeach
            </div>

            @if($incidenciasActivas->isNotEmpty())
                <div class="co-resolution-box">
                    <h3><i class="bi bi-check2-circle"></i> Resolver incidencias anteriores</h3>
                    <p>Marca sólo las incidencias que ya se encuentren solucionadas. Permanecerán en el historial.</p>
                    @foreach($incidenciasActivas as $incidencia)
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="incidencias_resueltas[]" value="{{ $incidencia->id }}" @checked(in_array($incidencia->id, old('incidencias_resueltas', [])))>
                            <span class="form-check-label"><strong>{{ config("centro_operaciones.incidencias.{$incidencia->tipo}.label", $incidencia->tipo) }}</strong> · reportada {{ $incidencia->created_at?->diffForHumans() }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="co-grid co-grid--form-bottom">
            <section class="co-card co-form-section">
                <div class="co-section-title"><span>5</span><div><h2>Observaciones</h2><p>Información relevante para contextualizar el reporte.</p></div></div>
                <textarea class="form-control" name="observaciones" rows="6" maxlength="5000" placeholder="Describe antecedentes adicionales...">{{ old('observaciones', $editando ? $reporte->observaciones : '') }}</textarea>
            </section>
            <section class="co-card co-form-section">
                <div class="co-section-title"><span>6</span><div><h2>Apoyo del SLEP</h2><p>Las áreas responsables se incorporarán en una mejora futura.</p></div></div>
                <label class="co-support-toggle"><input type="checkbox" name="necesita_apoyo" value="1" @checked(old('necesita_apoyo', $editando ? $reporte->necesita_apoyo : false))><span><i class="bi bi-life-preserver"></i> Este establecimiento necesita apoyo del SLEP</span></label>
                <textarea class="form-control mt-3" name="apoyo_detalle" rows="4" maxlength="2000" placeholder="Describe el apoyo requerido...">{{ old('apoyo_detalle', $editando ? $reporte->apoyo_detalle : '') }}</textarea>
            </section>
        </div>

        <section class="co-card co-form-section">
            <div class="co-section-title"><span>7</span><div><h2>Nivel de prioridad</h2><p>Define el tiempo de respuesta esperado.</p></div></div>
            <div class="co-option-grid co-option-grid--4">
            @foreach(config('centro_operaciones.prioridades') as $codigo => $opcion)
                <label class="co-big-choice co-big-choice--{{ $opcion['severity'] }}">
                    <input type="radio" name="prioridad" value="{{ $codigo }}" @checked($prioridad === $codigo) required>
                    <span><strong>{{ $opcion['label'] }}</strong></span>
                </label>
            @endforeach
            </div>
        </section>

        <div class="co-submit-bar">
            <div><strong>{{ $editando ? 'Versión '.$reporte->version : 'Nuevo reporte del día' }}</strong><small>Podrás editarlo nuevamente durante el día actual.</small></div>
            <div class="d-flex gap-2">
                <a href="{{ route('centro-operaciones.reportes.history') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-send"></i>{{ $editando ? 'Guardar actualización' : 'Enviar reporte' }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
    @vite('resources/js/centro-operaciones.js')
@endpush
