@extends('layouts.app')

@section('content')
    @php
        $mapsUrl = function ($establecimiento) {
            if (!$establecimiento) return null;
            if ($establecimiento->latitud !== null && $establecimiento->longitud !== null) {
                return 'https://www.google.com/maps?q=' . urlencode($establecimiento->latitud . ',' . $establecimiento->longitud);
            }
            $query = trim(collect([$establecimiento->nombre_establecimiento ?: null, $establecimiento->comuna ?: null])->filter()->implode(', '));
            return $query !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query) : null;
        };
        $mapsEmbedUrl = function ($establecimiento) {
            if (!$establecimiento || $establecimiento->latitud === null || $establecimiento->longitud === null) return null;
            $lat = (float) $establecimiento->latitud;
            $lng = (float) $establecimiento->longitud;
            $delta = 0.0045;
            $left = $lng - $delta;
            $right = $lng + $delta;
            $bottom = $lat - $delta;
            $top = $lat + $delta;
            return 'https://www.openstreetmap.org/export/embed.html?bbox='
                . urlencode($left . ',' . $bottom . ',' . $right . ',' . $top)
                . '&layer=mapnik&marker=' . urlencode($lat . ',' . $lng);
        };
    @endphp
    <div class="container">

        <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
            <h4 class="mb-0">Establecimientos</h4>

            @if(auth()->user()?->hasRole('admin'))
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('admin.establecimientos.template') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download"></i> Plantilla
                    </a>
                    <a href="{{ route('admin.establecimientos.import') }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload"></i> Carga masiva
                    </a>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-body">

                

                <form method="GET" action="{{ route('admin.establecimientos.index') }}" class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1">Buscar</label>
                        <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control form-control-sm"
                            placeholder="Nombre, RBD, código, tipo, comuna...">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1">Comuna</label>
                        <select name="comuna" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach (($comunas ?? collect()) as $c)
                                <option value="{{ $c }}" @selected(($comuna ?? '') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label mb-1">Sala cuna</label>
                        <select name="sala_cuna" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            <option value="1" @selected((string)($sala_cuna ?? '') === '1')>SI</option>
                            <option value="2" @selected((string)($sala_cuna ?? '') === '2')>NO</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm" type="submit">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <a href="{{ route('admin.establecimientos.index') }}" class="btn btn-outline-secondary btn-sm">
                            Limpiar
                        </a>
                    </div>
                </form>

<div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Establecimiento</th>
                                <th>RBD</th>
                                <th>Comuna</th>
                                <th>Latitud</th>
                                <th>Longitud</th>
                                <th>Sala cuna</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($items as $e)
                                <tr>
                                    <td>{{ $e->id }}</td>
                                    <td>{{ $e->nombre_establecimiento ?? ($e->nombre ?? '-') }}</td>
                                    <td>{{ $e->rbd ?? '-' }}</td>
                                    <td>{{ $e->comuna ?? '-' }}</td>
                                    <td>{{ $e->latitud ?? '-' }}</td>
                                    <td>{{ $e->longitud ?? '-' }}</td>
                                    <td>
                                        @php $rawSalaCuna = $e->getRawOriginal('sala_cuna'); @endphp
                                        {{ is_null($rawSalaCuna) ? '-' : ((int) $rawSalaCuna === 1 ? 'SI' : 'NO') }}
                                    </td>

                                    <td class="text-end">
                                        {{-- ✅ VER --}}
                                        <a href="{{ route('admin.establecimientos.show', $e) }}"
                                            class="btn btn-sm btn-outline-secondary" title="Ver">
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        <button type="button" class="btn btn-sm btn-outline-info" title="Mapa"
                                            data-bs-toggle="modal" data-bs-target="#establecimientoMapModal"
                                            data-nombre="{{ $e->nombre_establecimiento ?? ($e->nombre ?? 'Establecimiento') }}"
                                            data-comuna="{{ $e->comuna ?? 'Sin comuna registrada' }}"
                                            data-rbd="{{ $e->rbd ?? '—' }}"
                                            data-direccion="{{ $e->clasificacion ?? '' }}"
                                            data-latitud="{{ $e->latitud }}"
                                            data-longitud="{{ $e->longitud }}"
                                            data-maps-url="{{ $mapsUrl($e) }}"
                                            data-embed-url="{{ $mapsEmbedUrl($e) }}">
                                            <i class="bi bi-geo-alt"></i>
                                        </button>

                                        {{-- EDITAR (tu botón existente) --}}
                                        <a href="{{ route('admin.establecimientos.edit', $e) }}"
                                            class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        {{-- ELIMINAR (tu botón existente) --}}
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar"
                                            data-bs-toggle="modal" data-bs-target="#confirmDeleteEstabModal"
                                            data-delete-url="{{ route('admin.establecimientos.destroy', $e) }}"
                                            data-name="{{ $e->nombre_establecimiento ?? ($e->nombre ?? 'Establecimiento') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No hay establecimientos.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Paginación si aplica --}}
                @if (method_exists($items, 'links'))
                    <div class="mt-3">
                        {{ $items->links() }}
                    </div>
                @endif

            </div>
        </div>

        {{-- Modal confirm delete (si ya lo tienes, deja el tuyo) --}}
        <div class="modal fade" id="confirmDeleteEstabModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Confirmar eliminación</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        ¿Eliminar <strong id="deleteEstabName">este establecimiento</strong>?
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>

                        <form id="deleteEstabForm" method="POST" action="#">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Sí, eliminar</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>


        <div class="modal fade" id="establecimientoMapModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ubicación del establecimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2"><strong id="establecimientoMapNombre">—</strong></div>
                        <div class="text-muted mb-2">Dirección: <span id="establecimientoMapDireccion">Sin dirección registrada</span></div>
                        <div class="text-muted">Comuna: <span id="establecimientoMapComuna">—</span></div>
                        <div class="text-muted">RBD: <span id="establecimientoMapRbd">—</span></div>
                        <div class="text-muted mb-3">Coordenadas: <span id="establecimientoMapCoords">Sin coordenadas registradas</span></div>
                        <div id="establecimientoMapWrap" class="rounded overflow-hidden border d-none" style="height: 320px;">
                            <iframe id="establecimientoMapFrame" title="Mapa del establecimiento" width="100%" height="320" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                        <div id="establecimientoMapEmpty" class="alert alert-light border mb-0 d-none">
                            Este establecimiento no tiene coordenadas registradas para visualizar el mapa incrustado.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="#" class="btn btn-outline-primary d-none" id="establecimientoMapsLink" target="_blank" rel="noopener">Abrir en Google Maps</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('confirmDeleteEstabModal');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const url = button.getAttribute('data-delete-url');
                const name = button.getAttribute('data-name');

                const form = document.getElementById('deleteEstabForm');
                const nameEl = document.getElementById('deleteEstabName');

                if (form) form.action = url;
                if (nameEl) nameEl.textContent = name;
            });


            const mapModal = document.getElementById('establecimientoMapModal');
            if (mapModal) {
                mapModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    if (!button) return;
                    const nombre = button.getAttribute('data-nombre') || '—';
                    const direccion = button.getAttribute('data-direccion') || 'Sin dirección registrada';
                    const comuna = button.getAttribute('data-comuna') || '—';
                    const rbd = button.getAttribute('data-rbd') || '—';
                    const latitud = button.getAttribute('data-latitud');
                    const longitud = button.getAttribute('data-longitud');
                    const mapsUrl = button.getAttribute('data-maps-url');
                    const embedUrl = button.getAttribute('data-embed-url');

                    document.getElementById('establecimientoMapNombre').textContent = nombre;
                    document.getElementById('establecimientoMapDireccion').textContent = direccion;
                    document.getElementById('establecimientoMapComuna').textContent = comuna;
                    document.getElementById('establecimientoMapRbd').textContent = rbd;
                    document.getElementById('establecimientoMapCoords').textContent = (latitud && longitud) ? `${latitud}, ${longitud}` : 'Sin coordenadas registradas';

                    const frame = document.getElementById('establecimientoMapFrame');
                    const wrap = document.getElementById('establecimientoMapWrap');
                    const empty = document.getElementById('establecimientoMapEmpty');
                    if (embedUrl) {
                        frame.src = embedUrl;
                        wrap.classList.remove('d-none');
                        empty.classList.add('d-none');
                    } else {
                        frame.removeAttribute('src');
                        wrap.classList.add('d-none');
                        empty.classList.remove('d-none');
                    }

                    const mapsLink = document.getElementById('establecimientoMapsLink');
                    if (mapsUrl) {
                        mapsLink.href = mapsUrl;
                        mapsLink.classList.remove('d-none');
                    } else {
                        mapsLink.href = '#';
                        mapsLink.classList.add('d-none');
                    }
                });
            }
        });
    </script>
@endsection
