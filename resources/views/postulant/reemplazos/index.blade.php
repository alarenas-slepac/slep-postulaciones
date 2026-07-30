@extends('layouts.app')

@section('content')
    @php
        $estadoLabel = function ($e) {
            return match ($e) {
                'pendiente_uatp' => 'Pendiente UATP',
                'pendiente_gdp' => 'Pendiente GDP',
                'rechazada_uatp' => 'Rechazada UATP',
                'derivada_slep' => 'Derivada a SLEP',
                'aceptada' => 'Aceptada',
                'cerrado' => 'Cerrado',
                'anulada' => 'Anulada',
                default => ucfirst(str_replace('_', ' ', (string) $e)),
            };
        };
        $estadoBadge = function ($e) {
            return match ($e) {
                'pendiente_uatp' => 'text-bg-secondary',
                'pendiente_gdp' => 'text-bg-warning',
                'rechazada_uatp' => 'text-bg-danger',
                'derivada_slep' => 'text-bg-info',
                'aceptada' => 'text-bg-success',
                'cerrado' => 'text-bg-dark',
                'anulada' => 'text-bg-danger',
                default => 'text-bg-secondary',
            };
        };
        $mapsUrl = function ($establecimiento) {
            if (!$establecimiento) {
                return null;
            }
            if ($establecimiento->latitud !== null && $establecimiento->longitud !== null) {
                return 'https://www.google.com/maps?q=' . urlencode($establecimiento->latitud . ',' . $establecimiento->longitud);
            }
            $query = trim(collect([
                $establecimiento->nombre_establecimiento ?: null,
                $establecimiento->comuna ?: null,
                'SLEP Andalién Costa',
            ])->filter()->implode(', '));

            return $query !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query) : null;
        };
        $mapsEmbedUrl = function ($establecimiento) {
            if (!$establecimiento || $establecimiento->latitud === null || $establecimiento->longitud === null) {
                return null;
            }
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
        $faseLabel = function ($sectionKey, $solicitud) use ($today) {
            if ($sectionKey === 'activos') {
                return 'Activo';
            }
            if ($sectionKey === 'historial') {
                return $solicitud->estado === 'cerrado' ? 'Cerrado' : 'Finalizado';
            }
            if (!$solicitud->fecha_inicio_trabajo) {
                return 'Próximo';
            }
            $dias = $today->diffInDays($solicitud->fecha_inicio_trabajo, false);
            return match (true) {
                $dias <= 0 => 'Comienza hoy',
                $dias === 1 => 'Comienza mañana',
                default => 'Comienza en ' . $dias . ' días',
            };
        };
        $faseBadge = function ($sectionKey, $solicitud) use ($today) {
            if ($sectionKey === 'activos') {
                return 'text-bg-success';
            }
            if ($sectionKey === 'historial') {
                return $solicitud->estado === 'cerrado' ? 'text-bg-dark' : 'text-bg-secondary';
            }
            if (!$solicitud->fecha_inicio_trabajo) {
                return 'text-bg-info';
            }
            $dias = $today->diffInDays($solicitud->fecha_inicio_trabajo, false);
            return $dias <= 1 ? 'text-bg-warning' : 'text-bg-info';
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">Mis Reemplazos</h1>
            <div class="text-muted">Visualiza tus reemplazos activos, futuros e historial.</div>
        </div>
    </div>

    @if (!$profile)
        <div class="alert alert-warning">
            Tu cuenta no tiene un perfil de postulante asociado, por lo que no es posible visualizar reemplazos.
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">Activos</div>
                        <div class="display-6 fw-semibold">{{ $activos->count() }}</div>
                        <div class="text-muted small">Vigentes al {{ cl_plain_date($today) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">Futuros</div>
                        <div class="display-6 fw-semibold">{{ $futuros->count() }}</div>
                        <div class="text-muted small">Próximos reemplazos asignados</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">Historial</div>
                        <div class="display-6 fw-semibold">{{ $historial->count() }}</div>
                        <div class="text-muted small">Reemplazos finalizados o cerrados</div>
                    </div>
                </div>
            </div>
        </div>

        @foreach ([
            'activos' => ['title' => 'Mis reemplazos activos', 'items' => $activos, 'empty' => 'No registras reemplazos activos.'],
            'futuros' => ['title' => 'Mis futuros reemplazos', 'items' => $futuros, 'empty' => 'No registras futuros reemplazos asignados.'],
            'historial' => ['title' => 'Historial de reemplazos', 'items' => $historial, 'empty' => 'Aún no registras historial de reemplazos.'],
        ] as $key => $section)
            <div class="card mb-4 shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2 class="h6 mb-0">{{ $section['title'] }}</h2>
                        <div class="text-muted small">{{ $section['items']->count() }} registro(s)</div>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Establecimiento</th>
                                <th>Funcionario reemplazado</th>
                                <th>Área</th>
                                <th>Período</th>
                                <th>Inicio trabajo</th>
                                <th>Fase</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($section['items'] as $s)
                                @php $estab = $s->establecimiento; @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $s->numero_solicitud }}</td>
                                    <td>
                                        <div>{{ $estab?->nombre_establecimiento ?? '—' }}</div>
                                        <div class="text-muted small">{{ $estab?->comuna ?? 'Sin comuna registrada' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $s->funcionarioTitular?->nombre ?? '—' }}</div>
                                        <div class="text-muted small">{{ $s->funcionarioTitular?->rut ?? 'Sin RUT' }}</div>
                                    </td>
                                    <td>{{ $s->areaDesempeno?->nombre ?? '—' }}</td>
                                    <td>{{ cl_plain_date($s->fecha_inicio) }} - {{ cl_plain_date($s->fecha_termino) }}</td>
                                    <td>{{ cl_plain_date($s->fecha_inicio_trabajo) }}</td>
                                    <td><span class="badge {{ $faseBadge($key, $s) }}">{{ $faseLabel($key, $s) }}</span></td>
                                    <td><span class="badge {{ $estadoBadge($s->estado) }}">{{ $estadoLabel($s->estado) }}</span></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                            <a href="{{ route('postulant.reemplazos.show', $s) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                            @if (!empty($s->orden_trabajo_pdf_path))
                                            <a href="{{ route('postulant.reemplazos.ot', $s) }}" class="btn btn-sm btn-outline-success-dark" target="_blank" rel="noopener">Ver OT</a>
                                            @endif
                                            @if (!empty($s->horario_titular_pdf_path))
                                                <a href="{{ route('postulant.reemplazos.horario-titular', $s) }}" class="btn btn-sm btn-outline-info" target="_blank" rel="noopener">Horario titular</a>
                                            @endif
                                            @if (!empty($s->contrato_trabajo_firmado_pdf_path))
                                                <a href="{{ route('postulant.reemplazos.contrato-firmado', $s) }}" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener">Contrato firmado</a>
                                            @endif
                                            @if ($estab)
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#ubicacionEstablecimientoModal"
                                                    data-nombre="{{ $estab->nombre_establecimiento }}"
                                                    data-comuna="{{ $estab->comuna ?? 'Sin comuna registrada' }}"
                                                    data-rbd="{{ $estab->rbd ?? '—' }}"
                                                    data-direccion="{{ $estab->clasificacion ?? '' }}"
                                                    data-latitud="{{ $estab->latitud }}"
                                                    data-longitud="{{ $estab->longitud }}"
                                                    data-maps-url="{{ $mapsUrl($estab) }}"
                                                    data-embed-url="{{ $mapsEmbedUrl($estab) }}">
                                                    Ver ubicación
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">{{ $section['empty'] }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

    <div class="modal fade" id="ubicacionEstablecimientoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ubicación del establecimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong id="ubicacionNombre">—</strong></div>
                    <div class="mb-2 text-muted">Dirección: <span id="ubicacionDireccion">Sin dirección registrada</span></div>
                    <div class="text-muted mb-2">Comuna: <span id="ubicacionComuna">—</span></div>
                    <div class="text-muted">RBD: <span id="ubicacionRbd">—</span></div>
                    <div class="text-muted mb-3">Coordenadas: <span id="ubicacionCoords">Sin coordenadas registradas</span></div>

                    <div id="ubicacionMapaWrap" class="rounded overflow-hidden border d-none" style="height: 300px;">
                        <iframe id="ubicacionMapaFrame" title="Mapa establecimiento" width="100%" height="300" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div id="ubicacionMapaEmpty" class="alert alert-light border mb-0 d-none">
                        Este establecimiento no tiene coordenadas registradas para visualizar el mapa incrustado.
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="copiarDireccionBtn">Copiar dirección</button>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-outline-primary d-none" id="ubicacionMapsLink" target="_blank" rel="noopener">Abrir en Google Maps</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('ubicacionEstablecimientoModal');
        if (!modal) return;

        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const nombre = button.getAttribute('data-nombre') || '—';
            const direccion = button.getAttribute('data-direccion') || 'Sin dirección registrada';
            const comuna = button.getAttribute('data-comuna') || '—';
            const rbd = button.getAttribute('data-rbd') || '—';
            const mapsUrl = button.getAttribute('data-maps-url');
            const embedUrl = button.getAttribute('data-embed-url');
            const latitud = button.getAttribute('data-latitud');
            const longitud = button.getAttribute('data-longitud');

            document.getElementById('ubicacionNombre').textContent = nombre;
            document.getElementById('ubicacionDireccion').textContent = direccion;
            document.getElementById('ubicacionComuna').textContent = comuna;
            document.getElementById('ubicacionRbd').textContent = rbd;
            document.getElementById('ubicacionCoords').textContent = (latitud && longitud) ? `${latitud}, ${longitud}` : 'Sin coordenadas registradas';

            const frame = document.getElementById('ubicacionMapaFrame');
            const mapWrap = document.getElementById('ubicacionMapaWrap');
            const mapEmpty = document.getElementById('ubicacionMapaEmpty');
            if (embedUrl) {
                frame.src = embedUrl;
                mapWrap.classList.remove('d-none');
                mapEmpty.classList.add('d-none');
            } else {
                frame.removeAttribute('src');
                mapWrap.classList.add('d-none');
                mapEmpty.classList.remove('d-none');
            }

            const mapsLink = document.getElementById('ubicacionMapsLink');
            if (mapsUrl) {
                mapsLink.href = mapsUrl;
                mapsLink.classList.remove('d-none');
            } else {
                mapsLink.href = '#';
                mapsLink.classList.add('d-none');
            }

            const copyBtn = document.getElementById('copiarDireccionBtn');
            copyBtn.dataset.copyText = [direccion, comuna].filter(Boolean).join(', ');
            copyBtn.textContent = 'Copiar dirección';
        });

        document.getElementById('copiarDireccionBtn')?.addEventListener('click', async function () {
            const value = this.dataset.copyText || '';
            if (!value) return;
            try {
                await navigator.clipboard.writeText(value);
                this.textContent = 'Dirección copiada';
            } catch (e) {
                this.textContent = 'No se pudo copiar';
            }
        });
    });
</script>
@endpush
