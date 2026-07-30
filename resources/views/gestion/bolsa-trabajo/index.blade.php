@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1">Bolsa de Trabajo</h2>
            <p class="text-muted mb-0">Publicación, seguimiento por etapas y mantención de ofertas laborales visibles para postulantes y funcionarios.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('gestion.bolsa-trabajo.create') }}">
            <i class="bi bi-plus-circle"></i> Nueva oferta laboral
        </a>
    </div>

    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if (session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Establecimiento, RBD, área o correo">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estamento</label>
                    <select name="estamento" class="form-select">
                        <option value="">Todos</option>
                        <option value="docente" @selected($estamento === 'docente')>Docente</option>
                        <option value="asistente" @selected($estamento === 'asistente')>Asistente</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Comuna</label>
                    <select name="comuna" class="form-select">
                        <option value="">Todas</option>
                        @foreach ($comunas as $comunaItem)
                            <option value="{{ $comunaItem }}" @selected($comuna === $comunaItem)>{{ $comunaItem }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button class="btn btn-outline-primary" type="submit">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Establecimiento</th>
                        <th>Comuna</th>
                        <th>Estamento</th>
                        <th>Área</th>
                        <th>Etapa</th>
                        <th>Calidad</th>
                        <th>Horas</th>
                        <th>Remuneración bruta</th>
                        <th>Ventana postulación</th>
                        <th>Bases</th>
                        <th>Postulaciones</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $badgeClass = match($item->currentEtapaKey()) {
                                \App\Models\BolsaTrabajoOferta::ETAPA_RECEPCION_ANTECEDENTES => 'text-bg-primary',
                                \App\Models\BolsaTrabajoOferta::ETAPA_EVALUACION_ANTECEDENTES => 'text-bg-info',
                                \App\Models\BolsaTrabajoOferta::ETAPA_ENTREVISTA_PSICOLABORAL => 'text-bg-warning',
                                \App\Models\BolsaTrabajoOferta::ETAPA_ENTREVISTA_FINAL => 'text-bg-secondary',
                                \App\Models\BolsaTrabajoOferta::ETAPA_CERRADO => 'text-bg-success',
                                \App\Models\BolsaTrabajoOferta::ETAPA_DESIERTO => 'text-bg-dark',
                                default => 'text-bg-light',
                            };
                        @endphp
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>
                                @php
                                    $establecimientosTooltip = $item->establecimientos_seleccionados
                                        ->map(function ($establecimiento) {
                                            $nombre = trim((string) ($establecimiento->nombre_establecimiento ?? ''));
                                            $comuna = trim((string) ($establecimiento->comuna ?? ''));

                                            return $comuna !== '' ? ($nombre . ' (' . $comuna . ')') : $nombre;
                                        })
                                        ->filter()
                                        ->implode(' • ');
                                @endphp
                                @if ($item->rbds_display && $item->rbds_display !== 'sin-rbd')
                                    <span
                                        class="badge rounded-pill text-bg-light border text-dark fw-semibold"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="{{ $establecimientosTooltip ?: 'Sin nombre de establecimiento disponible' }}"
                                        style="cursor: help;"
                                    >
                                        {{ str_replace('_', ', ', $item->rbds_display) }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $item->comuna }}</td>
                            <td>{{ $item->estamento_label }}</td>
                            <td>{{ optional($item->areaDesempeno)->nombre }}</td>
                            <td>
                                <span class="badge {{ $badgeClass }}">{{ $item->etapa_label }}</span>
                                @if ($item->currentEtapaKey() === \App\Models\BolsaTrabajoOferta::ETAPA_CERRADO && $item->selected_postulante_name)
                                    <div class="small text-muted mt-1">Seleccionado/a: {{ $item->selected_postulante_name }}</div>
                                @endif
                            </td>
                            <td>{{ $item->calidad_contractual_label }}</td>
                            <td>{{ $item->cantidad_horas }}</td>
                            <td>{{ $item->remuneracion_bruta_formatted }}</td>
                            <td>
                                <div>{{ optional($item->fecha_inicio_postulaciones)->format('d/m/Y') }} {{ $item->hora_inicio_postulaciones }}</div>
                                <div class="small text-muted">hasta {{ optional($item->fecha_termino_postulaciones)->format('d/m/Y') }} {{ $item->hora_termino_postulaciones }}</div>
                            </td>
                            <td>
                                @if (!empty($item->bases_pdf_path))
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.bolsa-trabajo.bases', $item) }}">PDF</a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $item->postulaciones_count }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('gestion.bolsa-trabajo.show', $item) }}" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('gestion.bolsa-trabajo.edit', $item) }}" title="Editar"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('gestion.bolsa-trabajo.destroy', $item) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta oferta laboral?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-4">No hay ofertas laborales registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
