@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
<div class="co-shell co-risk-shell">
    <header class="co-hero">
        <div class="co-module-identity"><div class="co-module-icon co-module-icon--settings"><i class="bi bi-sliders"></i></div><div><div class="co-eyebrow">Centro de Operaciones</div><h1>Mantenedor de riesgo IRTE</h1><p>Versiona pesos, umbrales, preguntas y alternativas sin alterar evaluaciones históricas.</p></div></div>
        <div class="co-hero-actions"><a class="btn btn-outline-primary" href="{{ route('centro-operaciones.riesgos.index') }}"><i class="bi bi-shield-check"></i> Evaluaciones</a><a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.configuraciones.index') }}">Incidencias</a></div>
    </header>
    @if(session('success'))<div class="alert alert-success co-flash-message"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if($errors->any())<div class="alert alert-danger co-flash-message align-items-start"><i class="bi bi-exclamation-octagon-fill"></i><div><strong>Revisa la configuración:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <section class="co-card mb-3">
        <div class="co-card-head"><div><span class="co-eyebrow">Versionado</span><h2>Modelos disponibles</h2></div>@if($modelo->estado === 'publicado')<form method="POST" action="{{ route('centro-operaciones.riesgos.configuracion.versiones.store') }}">@csrf<button class="btn btn-primary"><i class="bi bi-files"></i> Crear nueva versión</button></form>@endif</div>
        <div class="co-risk-version-list">@foreach($modelos as $item)<a href="{{ route('centro-operaciones.riesgos.configuracion', ['modelo' => $item->id]) }}" class="{{ $item->id === $modelo->id ? 'is-active' : '' }}"><strong>v{{ $item->version }}</strong><span>{{ ucfirst($item->estado) }} · {{ $item->evaluaciones_count }} evaluaciones</span></a>@endforeach</div>
    </section>

    @if($modelo->estado !== 'borrador')
        <div class="alert alert-info"><i class="bi bi-lock me-1"></i> Esta versión está {{ $modelo->estado }} y es de solo lectura. Cree una nueva versión para modificarla.</div>
    @endif

    <form method="POST" action="{{ route('centro-operaciones.riesgos.configuracion.update', $modelo) }}">
        @csrf @method('PUT')
        <section class="co-card mb-3">
            <div class="co-card-head"><div><span class="co-eyebrow">Reglas generales</span><h2>Modelo {{ $modelo->version }}</h2></div><span class="co-risk-badge co-risk-badge--{{ $modelo->estado === 'publicado' ? 'estable' : 'monitoreo' }}">{{ ucfirst($modelo->estado) }}</span></div>
            <fieldset class="co-risk-config-grid" @disabled($modelo->estado !== 'borrador')>
                <div class="co-span-2"><label>Nombre</label><input class="form-control" name="nombre" value="{{ old('nombre', $modelo->nombre) }}" required></div>
                <div><label>Umbral monitoreo</label><input class="form-control" type="number" name="umbral_monitoreo" min="20" max="100" value="{{ old('umbral_monitoreo', $modelo->umbral_monitoreo) }}" required></div>
                <div><label>Umbral atención</label><input class="form-control" type="number" name="umbral_atencion" min="20" max="100" value="{{ old('umbral_atencion', $modelo->umbral_atencion) }}" required></div>
                <div><label>Umbral crítico</label><input class="form-control" type="number" name="umbral_critico" min="20" max="100" value="{{ old('umbral_critico', $modelo->umbral_critico) }}" required></div>
                <div><label>Score de alerta roja</label><input class="form-control" type="number" name="score_alerta_roja" min="1" max="5" value="{{ old('score_alerta_roja', $modelo->score_alerta_roja) }}" required></div>
                <div><label>Vigencia (días)</label><input class="form-control" type="number" name="vigencia_dias" min="1" max="730" value="{{ old('vigencia_dias', $modelo->vigencia_dias) }}" required></div>
                @foreach(['accion_estable' => 'Acción estable', 'accion_monitoreo' => 'Acción monitoreo', 'accion_atencion' => 'Acción atención', 'accion_critica' => 'Acción crítica', 'accion_factor_critico' => 'Acción por factor crítico'] as $campo => $label)<div class="co-span-2"><label>{{ $label }}</label><input class="form-control" name="{{ $campo }}" value="{{ old($campo, $modelo->{$campo}) }}" required></div>@endforeach
            </fieldset>
        </section>

        @foreach($modelo->dimensiones as $dimension)
            <section class="co-card co-risk-config-dimension mb-3">
                <div class="co-card-head"><div><span class="co-eyebrow">Dimensión {{ $dimension->orden }}</span><h2>{{ $dimension->nombre }}</h2></div><span class="co-weight-chip">{{ $dimension->peso }}%</span></div>
                <fieldset @disabled($modelo->estado !== 'borrador')>
                    <div class="co-risk-config-grid">
                        <div><label>Peso</label><input class="form-control" type="number" name="dimensiones[{{ $dimension->id }}][peso]" min="1" max="100" value="{{ old("dimensiones.{$dimension->id}.peso", $dimension->peso) }}" required></div>
                        <div><label>Nombre</label><input class="form-control" name="dimensiones[{{ $dimension->id }}][nombre]" value="{{ old("dimensiones.{$dimension->id}.nombre", $dimension->nombre) }}" required></div>
                        <div class="co-span-2"><label>Pregunta</label><input class="form-control" name="dimensiones[{{ $dimension->id }}][pregunta]" value="{{ old("dimensiones.{$dimension->id}.pregunta", $dimension->pregunta) }}" required></div>
                    </div>
                    <div class="co-risk-option-config">@foreach($dimension->opciones as $opcion)<div><span>Score {{ $opcion->score }}</span><input class="form-control" name="opciones[{{ $dimension->id }}][{{ $opcion->id }}][nombre]" value="{{ old("opciones.{$dimension->id}.{$opcion->id}.nombre", $opcion->nombre) }}" required><input type="hidden" name="opciones[{{ $dimension->id }}][{{ $opcion->id }}][score]" value="{{ $opcion->score }}"></div>@endforeach</div>
                </fieldset>
            </section>
        @endforeach

        @if($modelo->estado === 'borrador')<div class="co-risk-submit mb-3"><button class="btn btn-primary"><i class="bi bi-floppy"></i> Guardar configuración</button></div>@endif
    </form>
    @if($modelo->estado === 'borrador')<form method="POST" action="{{ route('centro-operaciones.riesgos.configuracion.publicar', $modelo) }}" class="co-card co-publish-risk">@csrf @method('PATCH')<div><strong>Publicar versión {{ $modelo->version }}</strong><p>Las nuevas evaluaciones usarán esta configuración; las anteriores conservarán su snapshot.</p></div><button class="btn btn-success"><i class="bi bi-check2-circle"></i> Publicar versión</button></form>@endif
</div>
@endsection
