@php
    $estadoBloqueo = $panelBloqueo['estado'];
    $establecimientoBloqueo = $panelBloqueo['establecimiento'];
    $erroresBloqueo = $errors->hasAny(['dotacion_bloqueo', 'motivo', 'desbloqueo_hasta']);
    $directivoTemporal = ($panelBloqueo['esDirectivo'] ?? false) && $estadoBloqueo['desbloqueo_activo'];
@endphp
<div class="slep-listing dotacion-locks" data-slep-listing
    @if($panelBloqueo['soloConsulta']) data-dotacion-solo-consulta @endif
    @if($directivoTemporal) data-dotacion-unlock-seconds="{{ $estadoBloqueo['segundos_restantes'] }}" @endif>
    @if(session('dotacion_bloqueo_status'))
        <div class="slep-record-state slep-record-state--success mb-4" role="status"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('dotacion_bloqueo_status') }}</div>
    @endif
    @error('dotacion_bloqueo')
        <div class="slep-listing__notice slep-listing__notice--danger" role="alert" tabindex="-1" data-dotacion-lock-error>{{ $message }}</div>
    @enderror
    @if($panelBloqueo['soloConsulta'])
        <div class="slep-record-state slep-record-state--warning mb-4" role="status">
            <strong><i class="bi bi-lock me-2" aria-hidden="true"></i>Edición bloqueada</strong>
            <p class="mb-0 mt-2">{{ $estadoBloqueo['mensaje'] }} Puedes consultar los datos, cambiar de año, filtrar y descargar los informes disponibles.</p>
        </div>
    @elseif($directivoTemporal)
        <div class="slep-record-state slep-record-state--info mb-4" role="status" aria-live="polite" data-dotacion-unlock-status>
            <strong data-dotacion-unlock-title>Edición autorizada temporalmente</strong>
            <p class="mb-0 mt-2" data-dotacion-unlock-message>La autorización corresponde sólo a tu establecimiento, en todos sus años. La edición se bloqueará el {{ $estadoBloqueo['desbloqueo_hasta_texto'] }} ({{ $estadoBloqueo['zona_horaria'] }}).</p>
        </div>
    @endif
    @if($panelBloqueo['gestiona'])
        <details class="slep-listing__panel dotacion-locks-panel" @if($erroresBloqueo) open @endif>
            <summary class="dotacion-locks-summary">
                <div><strong><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Control de edición de Dotación</strong><span class="dotacion-locks-help">Sólo para Funcionario Directivo de Establecimiento · Todos los años</span></div>
                <span class="slep-status-pill slep-status-pill--{{ $estadoBloqueo['bloqueado'] ? 'warning' : 'success' }}">{{ $estadoBloqueo['desbloqueo_activo'] ? 'Autorización temporal vigente' : ($estadoBloqueo['bloqueado'] ? 'Bloqueo activo' : 'Edición habilitada') }}</span>
                <i class="bi bi-chevron-down dotacion-locks-chevron" aria-hidden="true"></i>
            </summary>
            <div class="dotacion-locks-body">
                <p class="slep-listing__muted">Los demás roles conservan sus permisos actuales. Con el bloqueo general activo, sólo los establecimientos con un desbloqueo temporal autorizado pueden editar. Cada autorización vence en su fecha y hora, incluso si se desactiva el bloqueo general.</p>
                @if(!$estadoBloqueo['disponible'])<p class="slep-record-state slep-record-state--warning">Instala la migración de bloqueos de edición para habilitar estos controles.</p>@endif
                <div class="dotacion-locks-grid">
                    @foreach(['general', ...($establecimientoBloqueo ? ['individual'] : [])] as $ambitoBloqueo)
                        @php
                            $configBloqueo = $estadoBloqueo[$ambitoBloqueo];
                            $esGeneral = $ambitoBloqueo === 'general';
                            $activoBloqueo = $esGeneral ? $configBloqueo['bloqueado'] : $configBloqueo['bloqueado_efectivo'];
                        @endphp
                        <section class="dotacion-locks-card" aria-labelledby="dotacion-lock-{{ $ambitoBloqueo }}-titulo">
                            <h2 id="dotacion-lock-{{ $ambitoBloqueo }}-titulo">{{ $esGeneral ? 'Bloqueo general' : 'Bloqueo de este establecimiento' }}</h2>
                            <p class="dotacion-locks-help">{{ $esGeneral ? 'Todos los establecimientos y años, incluidos los módulos asociados.' : 'RBD '.$establecimientoBloqueo->rbd.' · '.$establecimientoBloqueo->nombre_establecimiento.' · Todos sus años y módulos asociados.' }}</p>
                            @if(!$esGeneral && $estadoBloqueo['desbloqueo_activo'])
                                <p class="slep-record-state slep-record-state--success">Autorización vigente hasta el <strong>{{ $estadoBloqueo['desbloqueo_hasta_texto'] }}</strong> ({{ $estadoBloqueo['zona_horaria'] }}). Sólo este establecimiento puede editar durante el plazo.</p>
                            @elseif(!$esGeneral && $estadoBloqueo['desbloqueo_vencido'])
                                <p class="slep-record-state slep-record-state--warning">Autorización vencida el <strong>{{ $estadoBloqueo['desbloqueo_hasta_texto'] }}</strong> ({{ $estadoBloqueo['zona_horaria'] }}). La edición está bloqueada.</p>
                            @else
                                <p><span class="slep-status-pill slep-status-pill--{{ ($activoBloqueo || (!$esGeneral && $estadoBloqueo['general']['bloqueado'])) ? 'warning' : 'success' }}">{{ !$esGeneral && $estadoBloqueo['general']['bloqueado'] ? 'Bloqueado por control general' : ($activoBloqueo ? 'Activado' : 'Desactivado') }}</span></p>
                            @endif
                            @if($esGeneral)
                                @include('partials.dotacion-bloqueo-accion', ['ambitoAccion' => 'general', 'bloquearAccion' => !$configBloqueo['bloqueado'], 'temporalAccion' => false, 'tituloAccion' => ($configBloqueo['bloqueado'] ? 'Desactivar' : 'Activar').' bloqueo general'])
                            @else
                                @if(!$estadoBloqueo['bloqueado'] || $estadoBloqueo['desbloqueo_activo'])
                                    @include('partials.dotacion-bloqueo-accion', ['ambitoAccion' => 'individual', 'bloquearAccion' => true, 'temporalAccion' => false, 'tituloAccion' => $estadoBloqueo['desbloqueo_activo'] ? 'Revocar autorización y bloquear' : 'Activar bloqueo individual'])
                                @endif
                                @if($estadoBloqueo['bloqueado'] || $estadoBloqueo['desbloqueo_activo'])
                                    <fieldset class="dotacion-locks-authorization mt-3">
                                        <legend>Autorización de desbloqueo temporal</legend>
                                        <p class="dotacion-locks-help">Indica el motivo y cuándo debe volver a bloquearse. La autorización permite editar únicamente este establecimiento y queda registrada con tu usuario y rol.</p>
                                        @if(!$estadoBloqueo['desbloqueo_disponible'])<p class="slep-record-state slep-record-state--warning">Instala la actualización de desbloqueos temporales para autorizar la reapertura.</p>@endif
                                        @include('partials.dotacion-bloqueo-accion', ['ambitoAccion' => 'desbloqueo', 'bloquearAccion' => false, 'temporalAccion' => true, 'tituloAccion' => $estadoBloqueo['desbloqueo_activo'] ? 'Renovar autorización' : 'Autorizar desbloqueo temporal'])
                                    </fieldset>
                                @endif
                            @endif
                            @if($configBloqueo['updated_by'])<p class="dotacion-locks-help mt-3 mb-0">Último cambio: {{ \Illuminate\Support\Carbon::parse($configBloqueo['updated_at'])->timezone($estadoBloqueo['zona_horaria'])->format('d/m/Y H:i') }} · Usuario #{{ $configBloqueo['updated_by'] }} · {{ $configBloqueo['rol_actualizador'] }}.</p>@endif
                            @if($configBloqueo['motivo'])<p class="dotacion-locks-help mt-2 mb-0">Motivo registrado: {{ $configBloqueo['motivo'] }}</p>@endif
                        </section>
                    @endforeach
                </div>
            </div>
        </details>
    @endif
</div>
