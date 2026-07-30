@extends('layouts.app')
@push('styles')
<style>
    .btn-docs-approved-dark {
        color: #0f5132 !important;
        border-color: #0f5132 !important;
        background-color: transparent !important;
    }

    .btn-docs-approved-dark:hover,
    .btn-docs-approved-dark:focus,
    .btn-docs-approved-dark:active,
    .btn-docs-approved-dark.active,
    .show > .btn-docs-approved-dark.dropdown-toggle {
        color: #ffffff !important;
        background-color: #0f5132 !important;
        border-color: #0f5132 !important;
    }

    .btn-docs-approved-dark:focus {
        box-shadow: 0 0 0 0.2rem rgba(15, 81, 50, 0.25) !important;
    }
</style>
@endpush

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <h2 class="h4 mb-0">Detalle de oferta laboral</h2>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('gestion.bolsa-trabajo.index') }}">Volver</a>
            <a class="btn btn-primary" href="{{ route('gestion.bolsa-trabajo.edit', $item) }}">Editar</a>
        </div>
    </div>

    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">No fue posible aplicar el cambio de etapa.</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Establecimiento</dt>
                        <dd class="col-sm-8">{{ $item->establecimientos_display ?: '—' }}</dd>
                        <dt class="col-sm-4">Comuna</dt>
                        <dd class="col-sm-8">{{ $item->comuna }}</dd>
                        <dt class="col-sm-4">Estamento</dt>
                        <dd class="col-sm-8">{{ $item->estamento_label }}</dd>
                        <dt class="col-sm-4">Área de desempeño</dt>
                        <dd class="col-sm-8">{{ optional($item->areaDesempeno)->nombre ?? '—' }}</dd>
                        <dt class="col-sm-4">Calidad contractual</dt>
                        <dd class="col-sm-8">{{ $item->calidad_contractual_label }}</dd>
                        <dt class="col-sm-4">Cantidad de horas</dt>
                        <dd class="col-sm-8">{{ $item->cantidad_horas }}</dd>
                        <dt class="col-sm-4">Remuneración bruta</dt>
                        <dd class="col-sm-8">{{ $item->remuneracion_bruta_formatted }}</dd>
                        <dt class="col-sm-4">Inicio trabajo aproximado</dt>
                        <dd class="col-sm-8">{{ optional($item->inicio_trabajo_aproximado)->format('d/m/Y') }}</dd>
                        <dt class="col-sm-4">Ventana postulación</dt>
                        <dd class="col-sm-8">{{ optional($item->fecha_inicio_postulaciones)->format('d/m/Y') }} {{ $item->hora_inicio_postulaciones }} hasta {{ optional($item->fecha_termino_postulaciones)->format('d/m/Y') }} {{ $item->hora_termino_postulaciones }}</dd>
                        <dt class="col-sm-4">Correo de contacto</dt>
                        <dd class="col-sm-8">{{ $item->correo_contacto }}</dd>
                        <dt class="col-sm-4">Bases</dt>
                        <dd class="col-sm-8">
                            @if (!empty($item->bases_pdf_path))
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.bolsa-trabajo.bases', $item) }}">
                                    <i class="bi bi-file-earmark-pdf"></i> Descargar bases PDF
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4">Creada por</dt>
                        <dd class="col-sm-8">{{ optional($item->creador)->display_name ?? optional($item->creador)->full_name ?? '—' }}</dd>
                        <dt class="col-sm-4">Postulaciones</dt>
                        <dd class="col-sm-8">{{ $item->postulaciones->count() }}</dd>
                    </dl>

                    <hr>

                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="zip_scope" class="form-label small text-muted mb-1">Alcance del ZIP</label>
                            <select id="zip_scope" class="form-select form-select-sm">
                                <option value="stage">Según etapa actual ({{ $exportStageCount }})</option>
                                <option value="all">Todos los postulantes ({{ $exportAllCount }})</option>
                            </select>
                            <div class="form-text">En Recepción incluye a todos; en etapas posteriores puedes limitar el ZIP a quienes siguen vigentes en el proceso.</div>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex gap-2 flex-wrap">
                                <a
                                    id="btn_documentos_aprobados_zip"
                                    class="btn btn-sm btn-docs-approved-dark"
                                    data-base-href="{{ route('gestion.bolsa-trabajo.documentos-aprobados-zip', $item) }}"
                                    href="{{ route('gestion.bolsa-trabajo.documentos-aprobados-zip', ['bolsa_trabajo' => $item, 'scope' => 'stage']) }}"
                                >
                                    <i class="bi bi-file-earmark-zip"></i> Descargar documentos aprobados
                                </a>
                                <a
                                    id="btn_cvs_zip"
                                    class="btn btn-sm btn-outline-primary"
                                    data-base-href="{{ route('gestion.bolsa-trabajo.cvs-zip', $item) }}"
                                    href="{{ route('gestion.bolsa-trabajo.cvs-zip', ['bolsa_trabajo' => $item, 'scope' => 'stage']) }}"
                                >
                                    <i class="bi bi-file-earmark-person"></i> Descargar solo CV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100 border-primary-subtle">
                <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Etapa actual del proceso</span>
                    <span class="badge text-bg-primary">{{ $item->etapa_label }}</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Al cambiar la etapa se enviarán correos automáticos a los postulantes, según si avanzan o no en el proceso. Para etapas intermedias, marca previamente en la tabla a quienes continúan.
                    </p>
                    @if ($item->currentEtapaKey() === \App\Models\BolsaTrabajoOferta::ETAPA_CERRADO && $item->selected_postulante_name)
                        <div class="alert alert-success py-2 small">
                            Persona seleccionada: <strong>{{ $item->selected_postulante_name }}</strong>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('gestion.bolsa-trabajo.update-etapa', $item) }}" id="form-etapa-oferta">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">Nueva etapa / estado</label>
                            <select class="form-select @error('etapa_estado') is-invalid @enderror" name="etapa_estado" id="etapa_estado" required>
                                @foreach ($etapaOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('etapa_estado', $item->currentEtapaKey()) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('etapa_estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3 d-none" id="selected-postulacion-wrapper">
                            <label class="form-label">Persona seleccionada</label>
                            <select class="form-select @error('selected_postulacion_id') is-invalid @enderror" name="selected_postulacion_id" id="selected_postulacion_id">
                                <option value="">Seleccione</option>
                                @foreach ($item->postulaciones as $postulacion)
                                    <option value="{{ $postulacion->id }}" @selected((string) old('selected_postulacion_id', $item->selected_postulacion_id) === (string) $postulacion->id)>
                                        {{ optional($postulacion->user)->display_name ?? optional($postulacion->user)->full_name ?? ('Postulante #' . $postulacion->id) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('selected_postulacion_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="form-text">Este dato se usa para notificar a todos los postulantes cuando la oferta pasa a estado Cerrado.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-arrow-repeat"></i> Aplicar cambio de etapa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <span>Usuarios postulados</span>
            <small class="text-muted">Marca “Avanza etapa” en quienes siguen en el proceso antes de cambiar a evaluación o entrevistas.</small>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Avanza etapa</th>
                        <th>Estado</th>
                        <th>RUT</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Fecha postulación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($item->postulaciones as $postulacion)
                        @php
                            $estadoClass = match((string) $postulacion->estado) {
                                'seleccionado' => 'text-bg-success',
                                'no_avanza' => 'text-bg-danger',
                                'proceso_desierto' => 'text-bg-dark',
                                'cerrado_no_seleccionado' => 'text-bg-secondary',
                                'en_proceso' => 'text-bg-primary',
                                default => 'text-bg-light',
                            };
                            $canParticipate = $postulacion->canParticipateInStageSelection();
                            $profileId = $postulacion->postulantProfile?->id ?? optional($postulacion->user)->postulantProfile?->id;
                        @endphp
                        <tr>
                            <td>
                                <input
                                    class="form-check-input selection-checkbox"
                                    type="checkbox"
                                    name="avanza_postulaciones[]"
                                    value="{{ $postulacion->id }}"
                                    form="form-etapa-oferta"
                                    @checked((bool) old('avanza_postulaciones') ? in_array($postulacion->id, old('avanza_postulaciones', [])) : $postulacion->avanza_etapa)
                                    @disabled(!$canParticipate)
                                >
                            </td>
                            <td><span class="badge {{ $estadoClass }}">{{ $postulacion->estado_label }}</span></td>
                            <td>{{ optional($postulacion->user)->rut }}</td>
                            <td>{{ optional($postulacion->user)->display_name ?? optional($postulacion->user)->full_name }}</td>
                            <td>{{ optional($postulacion->user)->email }}</td>
                            <td>{{ optional($postulacion->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($profileId)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('reemplazos.buscador-postulantes.show', $profileId) }}" title="Ver detalle del postulante">
                                        <i class="bi bi-person-vcard"></i>
                                    </a>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Sin perfil vinculado">
                                        <i class="bi bi-person-vcard"></i>
                                    </button>
                                @endif
                                @if ($postulacion->user)
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('reemplazos.documents.forUser', $postulacion->user) }}" title="Ver documentos">
                                        <i class="bi bi-folder2-open"></i>
                                    </a>
                                @else
                                    <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Sin usuario vinculado">
                                        <i class="bi bi-folder2-open"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay postulaciones registradas en esta oferta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const etapa = document.getElementById('etapa_estado');
    const selectedWrapper = document.getElementById('selected-postulacion-wrapper');
    const selectedField = document.getElementById('selected_postulacion_id');
    const checkboxes = Array.from(document.querySelectorAll('.selection-checkbox'));
    const zipScope = document.getElementById('zip_scope');
    const zipLinks = Array.from(document.querySelectorAll('[data-base-href]'));

    function syncZipLinks() {
        if (!zipScope) return;
        const scope = zipScope.value || 'stage';
        zipLinks.forEach(function (link) {
            const baseHref = link.getAttribute('data-base-href');
            if (!baseHref) return;
            link.setAttribute('href', baseHref + '?scope=' + encodeURIComponent(scope));
        });
    }

    function syncStageUi() {
        const value = etapa ? etapa.value : '';
        const isCerrado = value === '{{ \App\Models\BolsaTrabajoOferta::ETAPA_CERRADO }}';
        const isEvaluable = [
            '{{ \App\Models\BolsaTrabajoOferta::ETAPA_EVALUACION_ANTECEDENTES }}',
            '{{ \App\Models\BolsaTrabajoOferta::ETAPA_ENTREVISTA_PSICOLABORAL }}',
            '{{ \App\Models\BolsaTrabajoOferta::ETAPA_ENTREVISTA_FINAL }}'
        ].includes(value);

        if (selectedWrapper) {
            selectedWrapper.classList.toggle('d-none', !isCerrado);
            if (!isCerrado && selectedField) {
                selectedField.value = '';
            }
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.disabled = checkbox.dataset.locked === '1' || !isEvaluable;
        });
    }

    checkboxes.forEach(function (checkbox) {
        if (checkbox.disabled) {
            checkbox.dataset.locked = '1';
        }
    });

    if (etapa) {
        etapa.addEventListener('change', syncStageUi);
        syncStageUi();
    }

    if (zipScope) {
        zipScope.addEventListener('change', syncZipLinks);
        syncZipLinks();
    }
})();
</script>
@endsection
