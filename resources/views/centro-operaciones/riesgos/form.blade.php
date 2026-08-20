@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
@php
    $respuestasBorrador = $borrador?->respuestas->pluck('opcion_id', 'dimension_id') ?? collect();
    $observacionesBorrador = $borrador?->respuestas->pluck('observacion', 'dimension_id') ?? collect();
@endphp
<div class="co-shell co-risk-shell">
    <header class="co-hero">
        <div class="co-module-identity">
            <div class="co-module-icon co-module-icon--risk"><i class="bi bi-clipboard2-pulse"></i></div>
            <div>
                <div class="co-eyebrow">Evaluación de riesgo institucional</div>
                <h1>{{ $establecimiento->nombre_establecimiento }}</h1>
                <p>{{ $establecimiento->comuna }} · RBD {{ $establecimiento->rbd }} · Matrícula {{ number_format($establecimiento->matricula_total ?? 0, 0, ',', '.') }}</p>
            </div>
        </div>
        <div class="co-hero-actions"><span class="co-date-chip">Modelo IRTE {{ $modelo->version }}</span><a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.riesgos.index') }}"><i class="bi bi-arrow-left"></i> Volver</a></div>
    </header>

    @if(session('success'))<div class="alert alert-success co-flash-message"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if($errors->any())<div class="alert alert-danger co-flash-message align-items-start"><i class="bi bi-exclamation-octagon-fill"></i><div><strong>Revisa la evaluación:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <form method="POST" action="{{ route('centro-operaciones.riesgos.evaluaciones.store', $establecimiento) }}">
        @csrf
        <input type="hidden" name="modelo_id" value="{{ $modelo->id }}">
        <section class="co-card mb-3">
            <div class="co-card-head"><div><span class="co-eyebrow">Antecedentes</span><h2>Fecha y propósito</h2></div><span class="co-count">10 variables</span></div>
            <div class="co-risk-meta-form">
                <div><label for="fecha_evaluacion">Fecha de evaluación</label><input id="fecha_evaluacion" type="date" name="fecha_evaluacion" class="form-control" max="{{ now(config('centro_operaciones.timezone'))->toDateString() }}" value="{{ old('fecha_evaluacion', $borrador?->fecha_evaluacion?->toDateString() ?? now(config('centro_operaciones.timezone'))->toDateString()) }}" required></div>
                <div class="co-risk-form-note"><i class="bi bi-info-circle"></i><span>El IRTE se calcula al publicar. Puede guardar un borrador con respuestas incompletas.</span></div>
            </div>
        </section>

        <div class="co-risk-dimensions">
            @foreach($modelo->dimensiones as $dimension)
                @php($seleccion = old("respuestas.{$dimension->id}", $respuestasBorrador->get($dimension->id)))
                <section class="co-card co-risk-dimension">
                    <div class="co-risk-dimension-head"><span class="co-step-number">{{ $dimension->orden }}</span><div><h2>{{ $dimension->nombre }}</h2><p>{{ $dimension->pregunta }}</p></div><span class="co-weight-chip">Peso {{ $dimension->peso }}%</span></div>
                    <div class="co-risk-options">
                        @foreach($dimension->opciones as $opcion)
                            <label class="co-risk-option">
                                <input type="radio" name="respuestas[{{ $dimension->id }}]" value="{{ $opcion->id }}" @checked((int) $seleccion === $opcion->id)>
                                <span><b>{{ $opcion->score }}</b><strong>{{ $opcion->nombre }}</strong></span>
                            </label>
                        @endforeach
                    </div>
                    <div class="co-risk-observation"><label for="observacion-{{ $dimension->id }}">Antecedente o evidencia opcional</label><input id="observacion-{{ $dimension->id }}" class="form-control" name="observaciones_dimension[{{ $dimension->id }}]" maxlength="1000" value="{{ old("observaciones_dimension.{$dimension->id}", $observacionesBorrador->get($dimension->id)) }}" placeholder="Registre brevemente la fuente o situación observada"></div>
                </section>
            @endforeach
        </div>

        <section class="co-card mt-3">
            <div class="co-card-head"><div><span class="co-eyebrow">Síntesis</span><h2>Observaciones generales</h2></div></div>
            <div class="p-3"><textarea class="form-control" name="observaciones" rows="4" maxlength="3000" placeholder="Contexto general de la evaluación">{{ old('observaciones', $borrador?->observaciones) }}</textarea></div>
            <div class="co-risk-submit"><button class="btn btn-outline-primary" name="estado" value="borrador"><i class="bi bi-save"></i> Guardar borrador</button><button class="btn btn-primary" name="estado" value="publicado"><i class="bi bi-check2-circle"></i> Calcular y publicar IRTE</button></div>
        </section>
    </form>

    <section class="co-card mt-3">
        <div class="co-card-head"><div><span class="co-eyebrow">Trazabilidad</span><h2>Últimas evaluaciones</h2></div></div>
        <div class="table-responsive"><table class="table co-table mb-0"><thead><tr><th>Fecha</th><th>Estado</th><th>IRTE</th><th>Categoría</th><th>Modelo</th><th>Evaluador</th></tr></thead><tbody>@forelse($historial as $item)<tr><td>{{ $item->fecha_evaluacion->format('d/m/Y') }}</td><td>{{ ucfirst($item->estado) }}</td><td>{{ $item->irte ?? '—' }}</td><td>{{ $item->categoria_label }}</td><td>{{ $item->modelo->version }}</td><td>{{ $item->evaluadoPor?->display_name ?? 'Usuario no disponible' }}</td></tr>@empty<tr><td colspan="6"><div class="co-empty">Este establecimiento aún no tiene evaluaciones.</div></td></tr>@endforelse</tbody></table></div>
    </section>
</div>
@endsection
