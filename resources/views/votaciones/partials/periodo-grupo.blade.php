@php
    $inicioGrupo = isset($grupoPeriodo) ? $grupoPeriodo->inicioFuncionamiento() : $jornada->fecha;
    $finGrupo = isset($grupoPeriodo) ? $grupoPeriodo->finFuncionamiento() : $jornada->finFuncionamiento();
@endphp
<input type="hidden" name="_grupo_form" value="{{ $prefijo }}">
<div class="col-sm-6">
    <label class="form-label" for="{{ $prefijo }}-inicio">Fecha de inicio del grupo *</label>
    <input class="form-control" type="date" id="{{ $prefijo }}-inicio" name="fecha_inicio" required min="{{ $jornada->fecha->toDateString() }}" max="{{ $jornada->finFuncionamiento()->toDateString() }}" value="{{ old('_grupo_form') === $prefijo ? old('fecha_inicio') : $inicioGrupo->toDateString() }}">
    @if(old('_grupo_form') === $prefijo)@error('fecha_inicio')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
</div>
<div class="col-sm-6">
    <label class="form-label" for="{{ $prefijo }}-fin">Fecha de término del grupo *</label>
    <input class="form-control" type="date" id="{{ $prefijo }}-fin" name="fecha_fin" required min="{{ $jornada->fecha->toDateString() }}" max="{{ $jornada->finFuncionamiento()->toDateString() }}" value="{{ old('_grupo_form') === $prefijo ? old('fecha_fin') : $finGrupo->toDateString() }}">
    @if(old('_grupo_form') === $prefijo)@error('fecha_fin')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
</div>
<div class="col-12"><p class="form-text mb-0">Seleccione días dentro de la jornada: {{ $jornada->periodo_funcionamiento }}. Ambos extremos están incluidos; hora de Chile.</p></div>
