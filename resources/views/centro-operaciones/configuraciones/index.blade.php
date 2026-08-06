@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
<div class="co-shell co-config-shell">
    <header class="co-hero">
        <div class="co-module-identity">
            <div class="co-module-icon co-module-icon--settings">
                <i class="bi bi-sliders" aria-hidden="true"></i>
            </div>
            <div>
                <div class="co-eyebrow">Centro de Operaciones</div>
                <h1>Mantenedor de incidencias</h1>
                <p>Define el catálogo, la subdirección responsable y los plazos de atención.</p>
            </div>
        </div>
        <div class="co-hero-actions">
            <button
                class="btn btn-primary"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#nueva-incidencia"
                aria-expanded="{{ old('form_context') === 'create' ? 'true' : 'false' }}"
                aria-controls="nueva-incidencia"
            >
                <i class="bi bi-plus-circle me-1"></i> Nueva incidencia
            </button>
            <a href="{{ route('centro-operaciones.tickets.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-ticket-detailed me-1"></i> Ver tickets
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="alert alert-success co-flash-message">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger co-flash-message align-items-start">
            <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
            <div><strong>Revise la información ingresada:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul></div>
        </div>
    @endif

    <div id="nueva-incidencia" class="collapse {{ old('form_context') === 'create' ? 'show' : '' }} mb-4">
        <form
            method="POST"
            action="{{ route('centro-operaciones.configuraciones.store') }}"
            class="co-card co-create-incident"
            data-incidencia-form
        >
            @csrf
            <input type="hidden" name="form_context" value="create">
            <div class="co-card-head">
                <div><span class="co-eyebrow">Nuevo elemento del catálogo</span><h2>Crear nueva incidencia</h2></div>
                <span class="co-date-chip"><i class="bi bi-lightning-charge"></i> Disponible al activar</span>
            </div>
            <div class="co-config-form-body">
                <p class="co-form-intro">Completa la identificación y luego asigna la ruta institucional de atención.</p>
                <div class="co-create-grid">
                    <div class="co-field co-field--name">
                        <label class="form-label fw-semibold" for="nueva-incidencia-nombre">Nombre de la incidencia</label>
                        <input
                            id="nueva-incidencia-nombre"
                            name="nombre"
                            type="text"
                            maxlength="120"
                            value="{{ old('form_context') === 'create' ? old('nombre') : '' }}"
                            class="form-control"
                            placeholder="Ej.: Fuga de gas"
                            required
                        >
                    </div>
                    <div class="co-field co-field--level">
                        <label class="form-label fw-semibold" for="nueva-incidencia-severidad">Nivel</label>
                        <select id="nueva-incidencia-severidad" name="severidad" class="form-select" required>
                            <option value="alerta" @selected(old('severidad', 'alerta') === 'alerta')>Alerta</option>
                            <option value="critico" @selected(old('severidad') === 'critico')>Crítico</option>
                        </select>
                    </div>
                    <div class="co-field co-field--subdirection">
                        <label class="form-label fw-semibold" for="nueva-incidencia-subdireccion"><span class="co-step-number">1</span> Subdirección</label>
                        <select id="nueva-incidencia-subdireccion" name="subdireccion_dependencia" class="form-select" data-subdireccion required>
                            <option value="">Seleccione una subdirección…</option>
                            @foreach($subdirecciones as $subdireccion)
                                <option value="{{ $subdireccion }}" @selected(old('form_context') === 'create' && old('subdireccion_dependencia') === $subdireccion)>
                                    {{ $subdireccion }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="co-field co-field--responsible">
                        <label class="form-label fw-semibold" for="nueva-incidencia-responsable"><span class="co-step-number">2</span> Responsable de subdirección</label>
                        <select id="nueva-incidencia-responsable" name="responsable_funcionario_ac_id" class="form-select" data-responsable required>
                            <option value="">Seleccione primero una subdirección…</option>
                            @foreach($funcionarios as $persona)
                                <option
                                    value="{{ $persona->id }}"
                                    data-subdireccion="{{ $persona->subdireccion_dependencia }}"
                                    data-unidad="{{ $persona->unidad_departamento }}"
                                    @selected(old('form_context') === 'create' && (int) old('responsable_funcionario_ac_id') === $persona->id)
                                >
                                    {{ $persona->nombre_completo }} — {{ $persona->jefatura ? 'Subdirector(a) (Jefatura)' : ($persona->cargo_funcion ?: $persona->unidad_departamento) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text" data-unidad-label>La unidad se completará desde el responsable.</div>
                    </div>
                    <div class="co-field co-field--deadline">
                        <label class="form-label fw-semibold" for="nueva-incidencia-plazo">Plazo (días)</label>
                        <input id="nueva-incidencia-plazo" name="plazo_dias" type="number" min="1" max="365" value="{{ old('form_context') === 'create' ? old('plazo_dias', 4) : 4 }}" class="form-control" required>
                    </div>
                    <div class="co-field co-field--active">
                        <div class="form-check form-switch co-active-switch">
                            <input type="hidden" name="activo" value="0">
                            <input id="nueva-incidencia-activa" class="form-check-input" type="checkbox" name="activo" value="1" @checked(old('form_context') !== 'create' || old('activo'))>
                            <label class="form-check-label" for="nueva-incidencia-activa">Activa</label>
                        </div>
                    </div>
                    <div class="co-field co-field--submit">
                        <button class="btn btn-primary w-100 text-nowrap co-save-button">
                            <i class="bi bi-plus-lg me-1"></i> Crear incidencia
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="co-section-heading">
        <div>
            <span class="co-eyebrow">Catálogo operativo</span>
            <h2>Incidencias configuradas</h2>
            <p>Actualiza la ruta de atención o desactiva temporalmente una incidencia.</p>
        </div>
        <span class="co-count">{{ $configuraciones->count() }}</span>
    </div>

    <div class="co-config-list">
        @forelse($configuraciones as $configuracion)
            @php
                $contextoFormulario = 'update-'.$configuracion->id;
                $reintentando = old('form_context') === $contextoFormulario;
                $subdireccionSeleccionada = $reintentando
                    ? old('subdireccion_dependencia')
                    : $configuracion->subdireccion_dependencia;
                $responsableSeleccionado = $reintentando
                    ? (int) old('responsable_funcionario_ac_id')
                    : $configuracion->responsable_funcionario_ac_id;
                $nombreIncidencia = $configuracion->nombre
                    ?: config("centro_operaciones.incidencias.{$configuracion->tipo}.label", $configuracion->tipo);
                $severidad = $configuracion->severidad
                    ?: config("centro_operaciones.incidencias.{$configuracion->tipo}.severity", 'alerta');
            @endphp
            <form
                    method="POST"
                    action="{{ route('centro-operaciones.configuraciones.update', $configuracion) }}"
                    class="co-card co-config-item co-config-item--{{ $severidad }} {{ $configuracion->activo ? '' : 'co-config-item--inactive' }}"
                    data-incidencia-form
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_context" value="{{ $contextoFormulario }}">
                    <div class="co-config-item-body">
                        <div class="co-config-grid">
                            <div class="co-config-identity">
                                <span class="co-config-icon"><i class="bi {{ $severidad === 'critico' ? 'bi-exclamation-octagon' : 'bi-exclamation-triangle' }}"></i></span>
                                <div>
                                    <span class="co-config-label">Incidencia</span>
                                    <strong>{{ $nombreIncidencia }}</strong>
                                    <div class="co-config-badges">
                                        <span class="co-badge co-badge--{{ $severidad }}">{{ $severidad === 'critico' ? 'Crítica' : 'Alerta' }}</span>
                                        <span class="co-badge {{ $configuracion->activo ? 'co-badge--operativo' : '' }}">{{ $configuracion->activo ? 'Activa' : 'Inactiva' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="co-field co-field--subdirection">
                                <label class="form-label fw-semibold" for="subdireccion-{{ $configuracion->id }}"><span class="co-step-number">1</span> Subdirección</label>
                                <select id="subdireccion-{{ $configuracion->id }}" name="subdireccion_dependencia" class="form-select" data-subdireccion required>
                                    <option value="">Seleccione una subdirección…</option>
                                    @foreach($subdirecciones as $subdireccion)
                                        <option value="{{ $subdireccion }}" @selected($subdireccionSeleccionada === $subdireccion)>
                                            {{ $subdireccion }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="co-field co-field--responsible">
                                <label class="form-label fw-semibold" for="responsable-{{ $configuracion->id }}"><span class="co-step-number">2</span> Responsable de subdirección</label>
                                <select id="responsable-{{ $configuracion->id }}" name="responsable_funcionario_ac_id" class="form-select" data-responsable required>
                                    <option value="">Seleccione primero una subdirección…</option>
                                    @foreach($funcionarios as $persona)
                                        <option
                                            value="{{ $persona->id }}"
                                            data-subdireccion="{{ $persona->subdireccion_dependencia }}"
                                            data-unidad="{{ $persona->unidad_departamento }}"
                                            @selected((int) $responsableSeleccionado === $persona->id)
                                        >
                                            {{ $persona->nombre_completo }} — {{ $persona->jefatura ? 'Subdirector(a) (Jefatura)' : ($persona->cargo_funcion ?: $persona->unidad_departamento) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text" data-unidad-label>La unidad se completará desde el responsable.</div>
                            </div>
                            <div class="co-field co-field--deadline">
                                <label class="form-label fw-semibold" for="plazo-{{ $configuracion->id }}">Plazo</label>
                                <div class="co-input-suffix"><input id="plazo-{{ $configuracion->id }}" name="plazo_dias" type="number" min="1" max="365" value="{{ $reintentando ? old('plazo_dias') : $configuracion->plazo_dias }}" class="form-control" required><span>días</span></div>
                            </div>
                            <div class="co-field co-field--active">
                                <div class="form-check form-switch co-active-switch">
                                    <input type="hidden" name="activo" value="0">
                                    <input id="activo-{{ $configuracion->id }}" class="form-check-input" type="checkbox" name="activo" value="1" @checked($reintentando ? old('activo') : $configuracion->activo)>
                                    <label class="form-check-label" for="activo-{{ $configuracion->id }}">Activo</label>
                                </div>
                            </div>
                            <div class="co-field co-field--submit">
                                <button class="btn btn-primary w-100 text-nowrap co-save-button">
                                    <i class="bi bi-floppy me-1"></i> Guardar
                                </button>
                            </div>
                        </div>
                    </div>
            </form>
        @empty
            <div class="co-card">
                <div class="co-empty co-empty--large">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                    <div><strong>No hay incidencias configuradas</strong><span>Cree la primera con el botón “Nueva incidencia”.</span></div>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-incidencia-form]').forEach((formulario) => {
        const subdireccion = formulario.querySelector('[data-subdireccion]');
        const responsable = formulario.querySelector('[data-responsable]');
        const unidadLabel = formulario.querySelector('[data-unidad-label]');

        if (!subdireccion || !responsable) {
            return;
        }

        const sincronizarUnidad = () => {
            const opcion = responsable.selectedOptions[0];
            unidadLabel.textContent = opcion?.dataset.unidad
                ? `Unidad: ${opcion.dataset.unidad}`
                : 'La unidad se completará desde el responsable.';
        };

        const filtrarResponsables = (mantenerSeleccion = false) => {
            const seleccionada = subdireccion.value;

            responsable.querySelectorAll('option[data-subdireccion]').forEach((opcion) => {
                const coincide = seleccionada !== '' && opcion.dataset.subdireccion === seleccionada;
                opcion.hidden = !coincide;
                opcion.disabled = !coincide;

                if (!coincide && opcion.selected && !mantenerSeleccion) {
                    responsable.value = '';
                }
            });

            responsable.disabled = seleccionada === '';
            sincronizarUnidad();
        };

        subdireccion.addEventListener('change', () => filtrarResponsables(false));
        responsable.addEventListener('change', sincronizarUnidad);
        filtrarResponsables(true);
    });
});
</script>
@endpush
