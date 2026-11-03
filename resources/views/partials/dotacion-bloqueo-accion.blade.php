@php
    $prefijoAccion = 'dotacion-lock-'.$ambitoAccion;
    $esFormularioActivo = old('bloqueo_ambito') === $ambitoAccion;
    $errorMotivo = $esFormularioActivo ? $errors->first('motivo') : '';
    $errorFecha = $esFormularioActivo ? $errors->first('desbloqueo_hasta') : '';
    $habilitadoAccion = $estadoBloqueo['disponible'] && (!$temporalAccion || $estadoBloqueo['desbloqueo_disponible']);
@endphp
<form method="POST" action="{{ $ambitoAccion === 'general' ? route('admin.dotacion-establecimiento.bloqueos.general.update') : route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', $establecimientoBloqueo) }}" data-dotacion-lock-form>
    @csrf
    @method('PUT')
    <input type="hidden" name="bloqueado" value="{{ $bloquearAccion ? 1 : 0 }}">
    <input type="hidden" name="version" value="{{ $configBloqueo['version'] }}">
    <input type="hidden" name="anio" value="{{ $panelBloqueo['anio'] }}">
    <input type="hidden" name="bloqueo_ambito" value="{{ $ambitoAccion }}">
    <div class="mb-3">
        <label class="form-label" for="{{ $prefijoAccion }}-motivo">{{ $temporalAccion ? 'Motivo de la autorización (obligatorio)' : 'Motivo del cambio (opcional)' }}</label>
        <textarea id="{{ $prefijoAccion }}-motivo" name="motivo" class="form-control {{ $errorMotivo ? 'is-invalid' : '' }}" rows="2" maxlength="1000" aria-describedby="{{ $prefijoAccion }}-ayuda {{ $prefijoAccion }}-error" @if($errorMotivo) aria-invalid="true" @endif @required($temporalAccion) @disabled(!$habilitadoAccion)>{{ $esFormularioActivo ? old('motivo') : '' }}</textarea>
        <p class="form-text" id="{{ $prefijoAccion }}-ayuda">Hasta 1.000 caracteres. Se registra usuario, rol y fecha del cambio.</p>
        <div class="invalid-feedback" id="{{ $prefijoAccion }}-error">{{ $errorMotivo }}</div>
    </div>
    @if($temporalAccion)
        <div class="mb-3">
            <label class="form-label" for="{{ $prefijoAccion }}-hasta">Fecha y hora del próximo bloqueo (obligatorio)</label>
            <input type="text" class="form-control {{ $errorFecha ? 'is-invalid' : '' }}" id="{{ $prefijoAccion }}-hasta" name="desbloqueo_hasta"
                value="{{ $esFormularioActivo ? old('desbloqueo_hasta') : ($estadoBloqueo['desbloqueo_activo'] ? $estadoBloqueo['desbloqueo_hasta_formulario'] : '') }}"
                placeholder="AAAA-MM-DD HH:MM" autocomplete="off" required data-dotacion-lock-until
                data-now-local="{{ now($estadoBloqueo['zona_horaria'])->format('Y-m-d H:i') }}"
                aria-describedby="{{ $prefijoAccion }}-hasta-ayuda {{ $prefijoAccion }}-hasta-error" @if($errorFecha) aria-invalid="true" @endif @disabled(!$habilitadoAccion)>
            <p class="form-text" id="{{ $prefijoAccion }}-hasta-ayuda">Zona horaria: {{ $estadoBloqueo['zona_horaria'] }}. Debe ser posterior al momento actual. Sin calendario, ingresa AAAA-MM-DD HH:MM.</p>
            <div class="invalid-feedback" id="{{ $prefijoAccion }}-hasta-error">{{ $errorFecha }}</div>
        </div>
    @endif
    <button type="submit" class="slep-listing__button {{ $temporalAccion || $bloquearAccion ? 'slep-listing__button--primary' : 'slep-listing__button--secondary' }}" @disabled(!$habilitadoAccion)><i class="bi bi-{{ $bloquearAccion ? 'lock' : 'unlock' }}" aria-hidden="true"></i>{{ $tituloAccion }}</button>
</form>
