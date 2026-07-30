@extends('layouts.app')

@section('content')
@php
    $tipoLabel = match ($configuracion->tipo_destinatario) {
        'rol_configurable' => 'Rol configurable',
        'correo_configurable' => 'Correo configurable',
        'dinamico_con_copia_configurable' => 'Dinámico con copia configurable',
        'dinamico_establecimiento_con_copia_configurable' => 'Establecimiento con copia configurable',
        default => ucfirst(str_replace('_', ' ', (string) $configuracion->tipo_destinatario)),
    };

    $esSoloCorreoConfigurable = $configuracion->tipo_destinatario === 'correo_configurable';
    $rolesDisponibles = collect($rolesSistema ?? [])->map(fn ($rol) => (string) $rol)->filter()->values();
    $rolesSeleccionadosVista = collect($rolesSeleccionados ?? [])
        ->map(fn ($rol) => strtolower(trim((string) $rol)))
        ->filter()
        ->values()
        ->all();
    $correosConfigurados = \App\Models\CometidoNotificacionConfiguracion::parseCorreos(old('correos', $configuracion->correos));
@endphp

<div class="container py-4 cometidos-notificaciones-edit-view">
    <style>
        .cometidos-notificaciones-edit-view .module-shell { border: 1px solid #d7dee8; border-radius: 1.15rem; background: #fff; box-shadow: 0 .55rem 1.6rem rgba(15,23,42,.06); overflow: hidden; }
        .cometidos-notificaciones-edit-view .module-hero { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1.35rem 1.45rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .cometidos-notificaciones-edit-view .module-title-wrap { display: flex; align-items: flex-start; gap: .95rem; min-width: 0; }
        .cometidos-notificaciones-edit-view .module-icon { width: 2.85rem; height: 2.85rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); box-shadow: 0 .4rem 1rem rgba(13,110,253,.25); flex: 0 0 auto; }
        .cometidos-notificaciones-edit-view .module-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .12rem; }
        .cometidos-notificaciones-edit-view .module-title { color: #0f172a; font-size: clamp(1.45rem, 2vw, 2rem); font-weight: 850; line-height: 1.1; margin-bottom: .35rem; }
        .cometidos-notificaciones-edit-view .module-help { max-width: 780px; color: #475569; line-height: 1.45; margin-bottom: 0; }
        .cometidos-notificaciones-edit-view .module-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .48rem .75rem; border-radius: 999px; background: #eef6ff; border: 1px solid #b9d9ff; color: #0d47a1; font-size: .8rem; font-weight: 800; }

        .cometidos-notificaciones-edit-view .stage-panel-card { border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15,23,42,.045); overflow: hidden; }
        .cometidos-notificaciones-edit-view .stage-panel-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .9rem; padding: 1rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .cometidos-notificaciones-edit-view .stage-panel-title-wrap { display: flex; align-items: flex-start; gap: .8rem; min-width: 0; }
        .cometidos-notificaciones-edit-view .stage-panel-icon { width: 2.55rem; height: 2.55rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; box-shadow: 0 .35rem .8rem rgba(15,23,42,.12); flex: 0 0 auto; }
        .cometidos-notificaciones-edit-view .stage-panel-icon.is-summary { background: #0d6efd; }
        .cometidos-notificaciones-edit-view .stage-panel-icon.is-roles { background: #7c3aed; }
        .cometidos-notificaciones-edit-view .stage-panel-icon.is-mail { background: #0f8f4d; }
        .cometidos-notificaciones-edit-view .stage-panel-icon.is-state { background: #475569; }
        .cometidos-notificaciones-edit-view .stage-panel-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .1rem; }
        .cometidos-notificaciones-edit-view .stage-panel-help { color: #64748b; font-size: .84rem; margin-top: .18rem; line-height: 1.35; }
        .cometidos-notificaciones-edit-view .stage-panel-body { padding: 1rem; }

        .cometidos-notificaciones-edit-view .info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .cometidos-notificaciones-edit-view .info-item { border: 1px solid #e3eaf3; border-radius: .85rem; padding: .85rem; background: #f8fafc; min-height: 100%; }
        .cometidos-notificaciones-edit-view .info-item.is-wide { grid-column: 1 / -1; }
        .cometidos-notificaciones-edit-view .info-label { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .025em; margin-bottom: .28rem; }
        .cometidos-notificaciones-edit-view .info-value { color: #0f172a; font-weight: 750; line-height: 1.38; word-break: break-word; }
        .cometidos-notificaciones-edit-view .info-value.is-muted { color: #64748b; font-weight: 650; }

        .cometidos-notificaciones-edit-view .info-chip { display: inline-flex; align-items: center; gap: .32rem; padding: .28rem .58rem; border-radius: 999px; background: #f1f5f9; border: 1px solid #dbe4f0; color: #475569; font-size: .78rem; font-weight: 800; word-break: break-word; }
        .cometidos-notificaciones-edit-view .info-chip.is-primary { background: #eef6ff; border-color: #b9d9ff; color: #0d47a1; }
        .cometidos-notificaciones-edit-view .info-chip.is-success { background: #ecfdf3; border-color: #bcebd0; color: #0f5132; }
        .cometidos-notificaciones-edit-view .info-chip.is-warning { background: #fff8e1; border-color: #f5d58b; color: #8a4b00; }

        .cometidos-notificaciones-edit-view .roles-selector-card { border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; padding: 1rem; box-shadow: 0 .25rem .9rem rgba(15,23,42,.04); }
        .cometidos-notificaciones-edit-view .roles-selector-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; padding-bottom: .85rem; margin-bottom: .85rem; border-bottom: 1px solid #e5edf6; }
        .cometidos-notificaciones-edit-view .roles-checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: .65rem; }
        .cometidos-notificaciones-edit-view .role-checkbox-item { display: flex; align-items: flex-start; gap: .6rem; min-height: 3rem; padding: .75rem .85rem; border: 1px solid #e3eaf3; border-radius: .85rem; background: #f8fafc; cursor: pointer; transition: all .15s ease; }
        .cometidos-notificaciones-edit-view .role-checkbox-item:hover { border-color: #b9d9ff; background: #f5f9ff; }
        .cometidos-notificaciones-edit-view .role-checkbox-item.is-selected { border-color: #0d6efd; background: #eef6ff; box-shadow: 0 0 0 .12rem rgba(13,110,253,.08); }
        .cometidos-notificaciones-edit-view .role-checkbox-title { display: block; color: #0f172a; font-weight: 750; line-height: 1.25; word-break: break-word; }
        .cometidos-notificaciones-edit-view .role-checkbox-input { flex: 0 0 auto; margin-top: .13rem; }

        .cometidos-notificaciones-edit-view .form-label { color: #0f172a; font-weight: 750; }
        .cometidos-notificaciones-edit-view .form-control { border-radius: .85rem; border-color: #d7dee8; padding: .75rem .9rem; box-shadow: none; }
        .cometidos-notificaciones-edit-view .form-control:focus { border-color: #93c5fd; box-shadow: 0 0 0 .18rem rgba(13,110,253,.12); }
        .cometidos-notificaciones-edit-view textarea.form-control { resize: vertical; }

        .cometidos-notificaciones-edit-view .cometido-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border-radius: .85rem; padding: .72rem 1rem; font-weight: 800; border: 1px solid transparent; text-decoration: none; transition: all .18s ease; }
        .cometidos-notificaciones-edit-view .cometido-btn.is-primary { background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); color: #fff; }
        .cometidos-notificaciones-edit-view .cometido-btn.is-secondary { background: #fff; color: #334155; border-color: #d7dee8; }
        .cometidos-notificaciones-edit-view .cometido-btn.is-danger { background: #fff; color: #dc3545; border-color: #ffc9cf; }
        .cometidos-notificaciones-edit-view .cometido-btn.is-primary:hover { box-shadow: 0 .45rem 1rem rgba(13,110,253,.2); }
        .cometidos-notificaciones-edit-view .cometido-btn.is-secondary:hover { background: #f8fafc; color: #0f172a; }
        .cometidos-notificaciones-edit-view .cometido-btn.is-danger:hover { background: #fff8f8; }

        .cometidos-notificaciones-edit-view .alert-inline { border-radius: .95rem; border: 1px solid #cfe1ff; background: #f8fbff; color: #0f3d91; padding: .95rem 1rem; display: flex; align-items: flex-start; gap: .75rem; font-size: .9rem; line-height: 1.45; }
        .cometidos-notificaciones-edit-view .error-panel { border: 1px solid #fecdd3; background: #fff8f8; border-radius: 1rem; padding: 1rem 1.1rem; color: #7f1d1d; box-shadow: 0 .25rem .8rem rgba(127,29,29,.05); }
        .cometidos-notificaciones-edit-view .error-panel-title { display: flex; align-items: center; gap: .5rem; font-weight: 850; margin-bottom: .5rem; color: #991b1b; }
        .cometidos-notificaciones-edit-view .form-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .75rem; padding: 1rem 1.15rem 1.15rem; border-top: 1px solid #e5edf6; background: #fbfdff; }

        @media (max-width: 991.98px) {
            .cometidos-notificaciones-edit-view .info-grid { grid-template-columns: 1fr; }
            .cometidos-notificaciones-edit-view .info-item.is-wide { grid-column: auto; }
            .cometidos-notificaciones-edit-view .form-actions .cometido-btn { width: 100%; }
        }
    </style>

    <div class="module-shell mb-4">
        <div class="module-hero">
            <div class="module-title-wrap">
                <span class="module-icon"><i class="bi bi-bell"></i></span>
                <div>
                    <div class="module-kicker">Administración · Cometidos funcionarios</div>
                    <h1 class="module-title">Editar notificación</h1>
                    <p class="module-help">{{ $configuracion->nombre }}</p>
                </div>
            </div>
            <a href="{{ route('admin.cometidos-notificaciones.index') }}" class="cometido-btn is-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="error-panel mb-4">
            <div class="error-panel-title"><i class="bi bi-exclamation-triangle-fill"></i> Revise la información ingresada</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.cometidos-notificaciones.update', $configuracion) }}" class="stage-panel-card">
        @csrf
        @method('PUT')

        <div class="stage-panel-header">
            <div class="stage-panel-title-wrap">
                <span class="stage-panel-icon is-summary"><i class="bi bi-info-circle"></i></span>
                <div>
                    <div class="stage-panel-kicker">Datos de la notificación</div>
                    <h2 class="h5 mb-0">Configuración del evento</h2>
                    <div class="stage-panel-help">Revise la clave técnica, categoría y modalidad de destinatarios antes de modificar la configuración.</div>
                </div>
            </div>
            <span class="info-chip {{ $configuracion->activo ? 'is-success' : '' }}">
                <i class="bi {{ $configuracion->activo ? 'bi-check-circle' : 'bi-slash-circle' }}"></i>
                {{ $configuracion->activo ? 'Activa' : 'Inactiva' }}
            </span>
        </div>

        <div class="stage-panel-body">
            <div class="info-grid mb-4">
                <div class="info-item is-wide">
                    <div class="info-label">Nombre</div>
                    <div class="info-value">{{ $configuracion->nombre }}</div>
                    <div class="info-value is-muted mt-1">{{ $configuracion->descripcion }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tipo de destinatario</div>
                    <div class="info-value"><span class="info-chip is-primary"><i class="bi bi-diagram-3"></i> {{ $tipoLabel }}</span></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Categoría</div>
                    <div class="info-value">{{ $configuracion->categoria ?: 'Sin categoría' }}</div>
                </div>
                <div class="info-item is-wide">
                    <div class="info-label">Clave técnica</div>
                    <div class="info-value"><code>{{ $configuracion->clave }}</code></div>
                </div>
            </div>

            <div class="stage-panel-card mb-4">
                <div class="stage-panel-header">
                    <div class="stage-panel-title-wrap">
                        <span class="stage-panel-icon is-roles"><i class="bi bi-people"></i></span>
                        <div>
                            <div class="stage-panel-kicker">Destinatarios por rol</div>
                            <h3 class="h6 mb-0">Roles destinatarios</h3>
                            <div class="stage-panel-help">Seleccione los roles que recibirán esta notificación. Para notificaciones dinámicas puede dejar roles sin marcar y usar sólo copias adicionales.</div>
                        </div>
                    </div>
                    @unless($esSoloCorreoConfigurable)
                        <span class="info-chip is-primary" id="rolesCounter"><i class="bi bi-check2-square"></i> {{ count($rolesSeleccionadosVista) }} seleccionado(s)</span>
                    @endunless
                </div>

                <div class="stage-panel-body">
                    @if($esSoloCorreoConfigurable)
                        <div class="alert-inline">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>
                                <strong>Notificación sólo por correo configurable.</strong><br>
                                Esta notificación no usa roles destinatarios. Mantenga la configuración mediante correos directos o copias adicionales.
                            </div>
                        </div>
                    @else
                        <div class="roles-selector-card">
                            <div class="roles-selector-toolbar">
                                <div class="small text-muted">Roles cargados desde Spatie Permission. Marque uno o varios destinatarios.</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-role-action="select-all">
                                        <i class="bi bi-check2-all me-1"></i> Marcar todos
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-role-action="clear-all">
                                        <i class="bi bi-x-circle me-1"></i> Limpiar roles
                                    </button>
                                </div>
                            </div>

                            <div class="roles-checkbox-grid">
                                @forelse($rolesDisponibles as $rolSistema)
                                    @php
                                        $rolValue = strtolower(trim((string) $rolSistema));
                                        $roleId = 'rol_' . \Illuminate\Support\Str::slug($rolValue, '_');
                                    @endphp
                                    <label class="role-checkbox-item {{ in_array($rolValue, $rolesSeleccionadosVista, true) ? 'is-selected' : '' }}" for="{{ $roleId }}">
                                        <input
                                            class="form-check-input role-checkbox-input"
                                            type="checkbox"
                                            name="roles[]"
                                            id="{{ $roleId }}"
                                            value="{{ $rolValue }}"
                                            @checked(in_array($rolValue, $rolesSeleccionadosVista, true))
                                        >
                                        <span>
                                            <span class="role-checkbox-title">{{ $rolSistema }}</span>
                                            <span class="small text-muted">Rol del sistema</span>
                                        </span>
                                    </label>
                                @empty
                                    <div class="alert alert-warning mb-0">
                                        No se encontraron roles registrados en el sistema. Ejecute los seeders o revise la tabla de roles.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="stage-panel-card mb-4">
                <div class="stage-panel-header">
                    <div class="stage-panel-title-wrap">
                        <span class="stage-panel-icon is-mail"><i class="bi bi-envelope-check"></i></span>
                        <div>
                            <div class="stage-panel-kicker">Correos y copias</div>
                            <h3 class="h6 mb-0">Correos configurados / copias adicionales</h3>
                            <div class="stage-panel-help">Use este campo para correos directos, fallback o copias adicionales según el tipo de notificación.</div>
                        </div>
                    </div>
                    <span class="info-chip"><i class="bi bi-envelope"></i> {{ count($correosConfigurados) }} correo(s)</span>
                </div>
                <div class="stage-panel-body">
                    <label for="correos" class="form-label">Correos</label>
                    <textarea name="correos" id="correos" class="form-control" rows="5" placeholder="correo1@slepandaliencosta.gob.cl&#10;correo2@slepandaliencosta.gob.cl">{{ old('correos', $configuracion->correos) }}</textarea>
                    <div class="form-text">
                        Puede ingresar uno o varios correos separados por coma, punto y coma o salto de línea.
                    </div>
                </div>
            </div>

            <div class="stage-panel-card">
                <div class="stage-panel-header">
                    <div class="stage-panel-title-wrap">
                        <span class="stage-panel-icon is-state"><i class="bi bi-toggle-on"></i></span>
                        <div>
                            <div class="stage-panel-kicker">Estado de envío</div>
                            <h3 class="h6 mb-0">Activación de la notificación</h3>
                            <div class="stage-panel-help">Desactive temporalmente si el evento no debe generar correo.</div>
                        </div>
                    </div>
                </div>
                <div class="stage-panel-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="activo" id="activo" value="1" @checked(old('activo', $configuracion->activo))>
                        <label class="form-check-label fw-semibold" for="activo">Notificación activa</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('admin.cometidos-notificaciones.index') }}" class="cometido-btn is-secondary">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
            <button type="submit" class="cometido-btn is-primary">
                <i class="bi bi-save"></i> Guardar cambios
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('.roles-selector-card');
        if (! root) {
            return;
        }

        const checkboxes = Array.from(root.querySelectorAll('.role-checkbox-input'));
        const counter = document.getElementById('rolesCounter');

        function refreshRolesState() {
            let selected = 0;

            checkboxes.forEach(function (checkbox) {
                const item = checkbox.closest('.role-checkbox-item');
                if (checkbox.checked) {
                    selected++;
                    item?.classList.add('is-selected');
                } else {
                    item?.classList.remove('is-selected');
                }
            });

            if (counter) {
                counter.innerHTML = '<i class="bi bi-check2-square"></i> ' + selected + ' seleccionado(s)';
            }
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', refreshRolesState);
        });

        document.querySelectorAll('[data-role-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                const checked = button.dataset.roleAction === 'select-all';
                checkboxes.forEach(function (checkbox) {
                    checkbox.checked = checked;
                });
                refreshRolesState();
            });
        });

        refreshRolesState();
    });
</script>
@endsection
