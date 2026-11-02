@php
    $estadoBloqueo = $panelBloqueo['estado'];
    $establecimientoBloqueo = $panelBloqueo['establecimiento'];
@endphp
<div class="slep-listing dotacion-locks" data-slep-listing @if($panelBloqueo['soloConsulta']) data-dotacion-solo-consulta @endif>
    @if(session('dotacion_bloqueo_status'))<div class="slep-record-state slep-record-state--success mb-4" role="status"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('dotacion_bloqueo_status') }}</div>@endif
    @error('dotacion_bloqueo')<div class="slep-listing__notice slep-listing__notice--danger" role="alert" tabindex="-1" data-dotacion-lock-error>{{ $message }}</div>@enderror
    @if($panelBloqueo['soloConsulta'])<div class="slep-record-state slep-record-state--warning mb-4" role="status"><strong><i class="bi bi-lock me-2" aria-hidden="true"></i>Edición bloqueada</strong><p class="mb-0 mt-2">{{ $estadoBloqueo['mensaje'] }} Puedes consultar los datos, cambiar de año, filtrar y descargar los informes disponibles.</p></div>@endif
    @if($panelBloqueo['gestiona'])
        <details class="slep-listing__panel dotacion-locks-panel" @if($errors->has('dotacion_bloqueo') || $errors->has('motivo')) open @endif>
            <summary class="dotacion-locks-summary"><div><strong><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Control de edición de Dotación</strong><span class="dotacion-locks-help">Sólo para Funcionario Directivo de Establecimiento · Todos los años</span></div><span class="slep-status-pill slep-status-pill--{{ $estadoBloqueo['bloqueado'] ? 'warning' : 'success' }}">{{ $estadoBloqueo['bloqueado'] ? 'Bloqueo activo' : 'Edición habilitada' }}</span><i class="bi bi-chevron-down dotacion-locks-chevron" aria-hidden="true"></i></summary>
            <div class="dotacion-locks-body">
                <p class="slep-listing__muted">Los demás roles conservan sus permisos actuales. El bloqueo general prevalece sobre los individuales; al retirarlo, los bloqueos de cada establecimiento siguen vigentes.</p>
                @if(!$estadoBloqueo['disponible'])<p class="slep-record-state slep-record-state--warning">Instala la migración de bloqueos de edición para habilitar estos controles.</p>@endif
                <div class="dotacion-locks-grid">
                    @foreach(['general', ...($establecimientoBloqueo ? ['individual'] : [])] as $ambitoBloqueo)
                        @php($configBloqueo = $estadoBloqueo[$ambitoBloqueo])
                        <section class="dotacion-locks-card" aria-labelledby="dotacion-lock-{{ $ambitoBloqueo }}-titulo">
                            <h2 id="dotacion-lock-{{ $ambitoBloqueo }}-titulo">{{ $ambitoBloqueo === 'general' ? 'Bloqueo general' : 'Bloqueo de este establecimiento' }}</h2>
                            <p class="dotacion-locks-help">{{ $ambitoBloqueo === 'general' ? 'Todos los establecimientos y años, incluidos los módulos asociados.' : 'RBD '.$establecimientoBloqueo->rbd.' · '.$establecimientoBloqueo->nombre_establecimiento.' · Todos sus años y módulos asociados.' }}</p>
                            <p><span class="slep-status-pill slep-status-pill--{{ $configBloqueo['bloqueado'] ? 'warning' : 'success' }}">{{ $configBloqueo['bloqueado'] ? 'Activado' : 'Desactivado' }}</span></p>
                            @if($ambitoBloqueo === 'individual' && $estadoBloqueo['general']['bloqueado'])<p class="slep-record-state slep-record-state--info">El bloqueo general está activo. Retirar el individual no habilitará la edición mientras continúe el general.</p>@endif
                            <form method="POST" action="{{ $ambitoBloqueo === 'general' ? route('admin.dotacion-establecimiento.bloqueos.general.update') : route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', $establecimientoBloqueo) }}" data-dotacion-lock-form novalidate>
                                @csrf @method('PUT')
                                <input type="hidden" name="bloqueado" value="{{ $configBloqueo['bloqueado'] ? 0 : 1 }}"><input type="hidden" name="version" value="{{ $configBloqueo['version'] }}"><input type="hidden" name="anio" value="{{ $panelBloqueo['anio'] }}">
                                <label class="form-label" for="dotacion-lock-{{ $ambitoBloqueo }}-motivo">Motivo del cambio <span class="slep-listing__muted">(opcional)</span></label><textarea id="dotacion-lock-{{ $ambitoBloqueo }}-motivo" name="motivo" class="form-control" rows="2" maxlength="1000" aria-describedby="dotacion-lock-{{ $ambitoBloqueo }}-ayuda dotacion-lock-{{ $ambitoBloqueo }}-error" @disabled(!$estadoBloqueo['disponible'])>{{ old('bloqueo_ambito') === $ambitoBloqueo ? old('motivo') : '' }}</textarea><input type="hidden" name="bloqueo_ambito" value="{{ $ambitoBloqueo }}"><p class="form-text" id="dotacion-lock-{{ $ambitoBloqueo }}-ayuda">Hasta 1.000 caracteres. Se registra usuario, rol y fecha del cambio.</p><div class="invalid-feedback" id="dotacion-lock-{{ $ambitoBloqueo }}-error">@if(old('bloqueo_ambito') === $ambitoBloqueo)@error('motivo'){{ $message }}@enderror @endif</div>
                                <button type="submit" class="slep-listing__button {{ $configBloqueo['bloqueado'] ? 'slep-listing__button--secondary' : 'slep-listing__button--primary' }} mt-3" @disabled(!$estadoBloqueo['disponible'])><i class="bi bi-{{ $configBloqueo['bloqueado'] ? 'unlock' : 'lock' }}" aria-hidden="true"></i>{{ $configBloqueo['bloqueado'] ? 'Desactivar' : 'Activar' }} bloqueo {{ $ambitoBloqueo === 'general' ? 'general' : 'individual' }}</button>
                            </form>
                            @if($configBloqueo['updated_by'])<p class="dotacion-locks-help mt-3 mb-0">Último cambio: {{ \Illuminate\Support\Carbon::parse($configBloqueo['updated_at'])->timezone(config('app.display_timezone', 'America/Santiago'))->format('d/m/Y H:i') }} · Usuario #{{ $configBloqueo['updated_by'] }} · {{ $configBloqueo['rol_actualizador'] }}.</p>@endif
                            @if($configBloqueo['motivo'])<p class="dotacion-locks-help mt-2 mb-0">Motivo registrado: {{ $configBloqueo['motivo'] }}</p>@endif
                        </section>
                    @endforeach
                </div>
            </div>
        </details>
    @endif
</div>
