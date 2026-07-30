@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="m-0">Buscador de Postulantes y Funcionarios</h3>
            <div class="text-muted">Búsqueda avanzada de postulantes y funcionarios registrados (filtros combinables).</div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('reemplazos.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Reemplazos
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold">Revisa los filtros ingresados</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filtros avanzados --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('reemplazos.buscador-postulantes.index') }}"
                class="row g-2 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">RUT</label>
                    <input type="text" name="rut" value="{{ $filters['rut'] ?? '' }}" class="form-control"
                        placeholder="Ej: 12.345.678-9">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Nombre completo</label>
                    <input type="text" name="nombre" value="{{ $filters['nombre'] ?? '' }}" class="form-control"
                        placeholder="Nombres o apellidos">
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Teléfono</label>
                    <input type="text" name="telefono" value="{{ $filters['telefono'] ?? '' }}" class="form-control"
                        placeholder="9xxxxxxx">
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Comuna de desempeño</label>
                    <select name="commune_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($communes as $c)
                            <option value="{{ $c->id }}" @selected((string) ($filters['commune_id'] ?? '') === (string) $c->id)>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Área de desempeño</label>
                    <select name="area_desempeno_id" id="area_desempeno_id" class="form-select">
                        <option value="">Todas</option>

                        @if (($areasDocente ?? collect())->count())
                            <optgroup label="Docente">
                                @foreach ($areasDocente as $a)
                                    <option value="{{ $a->id }}"
                                        data-docente="1"
                                        data-tp="{{ ($areaTpIds ?? collect())->contains((int) $a->id) ? '1' : '0' }}"
                                        @selected((string) ($filters['area_desempeno_id'] ?? '') === (string) $a->id)>
                                        {{ $a->nombre }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif

                        @if (($areasAsistente ?? collect())->count())
                            <optgroup label="Asistente">
                                @foreach ($areasAsistente as $a)
                                    <option value="{{ $a->id }}" data-docente="0" data-tp="0" @selected((string) ($filters['area_desempeno_id'] ?? '') === (string) $a->id)>
                                        {{ $a->nombre }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                </div>

                <div class="col-12 col-md-3 {{ ($isAreaTpSelected ?? false) ? '' : 'd-none' }}" id="wrap-especialidad-tp-filter">
                    <label class="form-label mb-1">Especialidad TP</label>
                    <select name="especialidad_tp" id="especialidad_tp_filter" class="form-select" {{ ($isAreaTpSelected ?? false) ? '' : 'disabled' }}>
                        <option value="">Todas</option>
                        @foreach (($especialidadesTp ?? collect()) as $especialidadTp)
                            <option value="{{ $especialidadTp }}" @selected((string) ($filters['especialidad_tp'] ?? '') === (string) $especialidadTp)>
                                {{ $especialidadTp }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Se muestra sólo para Docente Técnico Profesional.</div>
                </div>



                <div class="col-12 col-md-3 {{ ($isAreaDocenteSelected ?? false) ? '' : 'd-none' }}" id="wrap-mencion-filter">
                    <label class="form-label mb-1">Mención</label>
                    <input type="text"
                        name="mencion"
                        id="mencion_filter"
                        value="{{ $filters['mencion'] ?? '' }}"
                        class="form-control"
                        list="menciones-docentes-list"
                        placeholder="Buscar o seleccionar mención"
                        {{ ($isAreaDocenteSelected ?? false) ? '' : 'disabled' }}>
                    <datalist id="menciones-docentes-list">
                        @foreach (($mencionesDocentes ?? collect()) as $mencionDocente)
                            <option value="{{ $mencionDocente }}"></option>
                        @endforeach
                    </datalist>
                    <div class="form-text">Se muestra sólo para áreas de desempeño docente.</div>
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label mb-1">Exp. mín</label>
                    <input type="number" min="0" max="80" step="1" name="exp_min"
                        value="{{ $filters['exp_min'] ?? '' }}" class="form-control" placeholder="0">
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label mb-1">Exp. máx</label>
                    <input type="number" min="0" max="80" step="1" name="exp_max"
                        value="{{ $filters['exp_max'] ?? '' }}" class="form-control" placeholder="80">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1">Por página</label>
                    <select name="per_page" class="form-select">
                        @foreach ([10, 15, 25, 50] as $n)
                            <option value="{{ $n }}" @selected((int) ($filters['per_page'] ?? 15) === $n)>
                                {{ $n }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3 d-flex gap-2 justify-content-md-end">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search"></i> Buscar
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('reemplazos.buscador-postulantes.index') }}">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </form>

            <div class="small text-muted mt-2">
                Los filtros se pueden combinar. Por ejemplo: <em>Comuna = Coronel</em> + <em>Área = Docente Técnico Profesional</em> +
                <em>Especialidad TP = Electricidad</em> + <em>Mención = Matemática</em> + <em>Exp. mín = 3</em>.
            </div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>RUT</th>
                            <th>Nombre completo</th>
                            <th>Teléfono</th>
                            <th>Comuna de desempeño</th>
                            <th>Área de desempeño</th>
                            <th class="text-end">Años exp.</th>
                            <th class="text-nowrap">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($profiles as $p)
                            @php
                                $u = $p->user;
                                $name = $u?->nombre_completo ?? '—';
                                $tel = $p->telefono1 ?: ($p->telefono2 ?: '—');
                                $communesTxt =
                                    $u && $u->communes && $u->communes->count()
                                        ? $u->communes->pluck('name')->implode(', ')
                                        : '—';
                                $areaTxt = $p->areaDesempeno?->nombre ?? ($p->area_desempeno_nombre ?? '—');
                                $contratosActivos = $p->contratosLaboralesActivos ?? collect();
                                $informaTrabajoExterno = (bool) ($u?->trabaja_en_otro_lugar ?? false);
                                $tieneContratoActivo = $contratosActivos->count() > 0;
                                $reemplazosActivos = $p->getRelation('reemplazosActivosActuales') ?? collect();
                                $tieneReemplazoActivo = $reemplazosActivos->count() > 0;
                                $contratoTooltip = $tieneContratoActivo
                                    ? $contratosActivos->map(function ($contrato) {
                                        return trim(implode(' | ', array_filter([
                                            'Tipo: ' . ($contrato->tipo_contrato ?? '—'),
                                            'Horas: ' . ($contrato->cantidad_horas ?? '—'),
                                            'Término: ' . optional($contrato->fecha_termino)->format('d-m-Y'),
                                            'Establecimiento: ' . ($contrato->establecimiento?->nombre_establecimiento ?? '—'),
                                        ])));
                                    })->implode(' / ')
                                    : '';
                                $trabajoExternoTooltip = $informaTrabajoExterno
                                    ? trim((string) ($u?->trabaja_en_otro_lugar_observacion ?: 'Informó que actualmente trabaja en otro lugar. No necesariamente indica institución.'))
                                    : '';
                                $reemplazoTooltip = $tieneReemplazoActivo
                                    ? $reemplazosActivos->map(function ($solicitud) {
                                        return trim(implode(' | ', array_filter([
                                            'Solicitud: ' . ($solicitud->numero_solicitud ?? ('#' . $solicitud->id)),
                                            'Establecimiento: ' . ($solicitud->establecimiento?->nombre_establecimiento ?? '—'),
                                            'Término: ' . optional($solicitud->fecha_termino)->format('d-m-Y'),
                                        ])));
                                    })->implode(' / ')
                                    : '';
                                $documentosEstado = $p->documentos_obligatorios_estado ?? [
                                    'total' => 0,
                                    'uploaded' => 0,
                                    'missing' => 0,
                                    'missing_labels' => [],
                                    'is_complete' => null,
                                    'tooltip' => 'No se detectaron documentos obligatorios para este perfil.',
                                ];
                                $docsComplete = $documentosEstado['is_complete'] ?? null;
                                $docsMissing = (int) ($documentosEstado['missing'] ?? 0);
                                $docsTooltip = $documentosEstado['tooltip'] ?? '';
                                $rowClass = $informaTrabajoExterno
                                    ? 'table-success'
                                    : ($tieneContratoActivo ? 'table-warning' : ($tieneReemplazoActivo ? 'table-info' : ''));
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td class="text-nowrap">{{ $u?->rut ?? '—' }}</td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <span>{{ $name }}</span>
                                        @if ($informaTrabajoExterno)
                                            <span class="badge align-self-start"
                                                style="background-color: #0f5132; color: #ffffff;"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="{{ $trabajoExternoTooltip }}">
                                                <i class="bi bi-telephone-outbound-fill"></i> Informó trabajo externo
                                            </span>
                                        @endif
                                        @if ($tieneContratoActivo)
                                            <span class="badge bg-warning text-dark align-self-start"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="{{ $contratoTooltip }}">
                                                <i class="bi bi-briefcase-fill"></i> Trabajando
                                            </span>
                                        @endif
                                        @if ($tieneReemplazoActivo)
                                            <span class="badge bg-info text-dark align-self-start"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="{{ $reemplazoTooltip }}">
                                                <i class="bi bi-person-check-fill"></i> En reemplazo
                                            </span>
                                        @endif
                                        @if ($docsComplete === true)
                                            <span class="badge align-self-start text-white"
                                                style="background-color: #0f5132;"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="{{ $docsTooltip }}">
                                                <i class="bi bi-check-circle-fill"></i> Docs completos
                                            </span>
                                        @elseif ($docsComplete === false)
                                            <span class="badge bg-danger align-self-start"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="{{ $docsTooltip }}">
                                                <i class="bi bi-exclamation-triangle-fill"></i> Faltan {{ $docsMissing }} doc{{ $docsMissing === 1 ? '' : 's' }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary align-self-start"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="{{ $docsTooltip }}">
                                                <i class="bi bi-info-circle-fill"></i> Sin docs oblig.
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-nowrap">{{ $tel }}</td>
                                <td>{{ $communesTxt }}</td>
                                <td>
                                    {{ $areaTxt }}
                                    @if (!empty($p->area_desempeno_id))
                                        <span class="text-muted small">(#{{ $p->area_desempeno_id }})</span>
                                    @endif
                                    @if (!empty($p->especialidad_tp))
                                        <div class="text-muted small">Especialidad TP: {{ $p->especialidad_tp }}</div>
                                    @endif
                                    @if (!empty($p->mencion))
                                        <div class="text-muted small">Mención: {{ $p->mencion }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ $p->anios_experiencia ?? '—' }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('reemplazos.buscador-postulantes.show', $p) }}"
                                        class="btn btn-sm btn-outline-primary" title="Ver ficha"
                                        data-bs-toggle="tooltip">
                                        <i class="bi bi-eye"></i>
                                        <span class="visually-hidden">Ver ficha</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay usuarios para los
                                    filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer d-flex align-items-center justify-content-between">
            <div class="small text-muted">
                Mostrando {{ $profiles->count() }} de {{ $profiles->total() }} resultado(s).
            </div>
            <div>
                {{ $profiles->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const areaSelect = document.getElementById('area_desempeno_id');
            const especialidadWrap = document.getElementById('wrap-especialidad-tp-filter');
            const especialidadSelect = document.getElementById('especialidad_tp_filter');
            const mencionWrap = document.getElementById('wrap-mencion-filter');
            const mencionInput = document.getElementById('mencion_filter');

            if (!areaSelect || !especialidadWrap || !especialidadSelect || !mencionWrap || !mencionInput) {
                return;
            }

            const syncEspecialidadTpFilter = function () {
                const selectedOption = areaSelect.options[areaSelect.selectedIndex];
                const isDocente = selectedOption && selectedOption.dataset.docente === '1';
                const isTp = selectedOption && selectedOption.dataset.tp === '1';

                especialidadWrap.classList.toggle('d-none', !isTp);
                especialidadSelect.disabled = !isTp;
                mencionWrap.classList.toggle('d-none', !isDocente);
                mencionInput.disabled = !isDocente;

                if (!isTp) {
                    especialidadSelect.value = '';
                }

                if (!isDocente) {
                    mencionInput.value = '';
                }
            };

            areaSelect.addEventListener('change', syncEspecialidadTpFilter);
            syncEspecialidadTpFilter();
        });
    </script>
@endpush
