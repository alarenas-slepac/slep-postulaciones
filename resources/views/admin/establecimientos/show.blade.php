@extends('layouts.app')

@section('content')
    @php
        $mapsUrl = null;
        $mapsEmbedUrl = null;
        if ($establecimiento->latitud !== null && $establecimiento->longitud !== null) {
            $mapsUrl = 'https://www.google.com/maps?q=' . urlencode($establecimiento->latitud . ',' . $establecimiento->longitud);
            $lat = (float) $establecimiento->latitud;
            $lng = (float) $establecimiento->longitud;
            $delta = 0.0045;
            $left = $lng - $delta;
            $right = $lng + $delta;
            $bottom = $lat - $delta;
            $top = $lat + $delta;
            $mapsEmbedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='
                . urlencode($left . ',' . $bottom . ',' . $right . ',' . $top)
                . '&layer=mapnik&marker=' . urlencode($lat . ',' . $lng);
        }
    @endphp
    <div class="container">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-0">
                    {{ $establecimiento->nombre_establecimiento ?? ($establecimiento->nombre ?? 'Establecimiento') }}
                </h4>
                <div class="text-muted small">
                    ID: {{ $establecimiento->id }} |
                    RBD: {{ $establecimiento->rbd ?? '-' }}
                </div>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('admin.establecimientos.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>

                <a href="{{ route('admin.establecimientos.areas-desempeno-bloqueadas.edit', $establecimiento) }}"
                    class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-shield-lock"></i> Áreas bloqueadas
                </a>

                <a href="{{ route('admin.establecimientos.edit', $establecimiento) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            </div>
        </div>

        {{-- Resumen establecimiento --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="fw-semibold">Total jornada (establecimiento)</div>
                        <div class="fs-5">{{ $totalJornada }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-semibold">Registros</div>
                        <div class="fs-5">{{ $registros->count() }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-semibold">Funcionarios únicos (por RUT)</div>
                        <div class="fs-5">{{ $funcionariosResumen->count() }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-semibold">Coordenadas</div>
                        <div class="fs-6">{{ ($establecimiento->latitud !== null && $establecimiento->longitud !== null) ? $establecimiento->latitud . ', ' . $establecimiento->longitud : 'Sin coordenadas' }}</div>
                    </div>
                </div>
            </div>
        </div>


        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Ubicación del establecimiento</span>
                @if ($mapsUrl)
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-geo-alt"></i> Abrir en Google Maps
                    </a>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div><strong>Dirección:</strong> {{ $establecimiento->clasificacion ?: 'Sin dirección registrada' }}</div>
                        <div class="text-muted">Comuna: {{ $establecimiento->comuna ?? '—' }}</div>
                        <div class="text-muted">RBD: {{ $establecimiento->rbd ?? '—' }}</div>
                        <div class="text-muted">Coordenadas: {{ ($establecimiento->latitud !== null && $establecimiento->longitud !== null) ? $establecimiento->latitud . ', ' . $establecimiento->longitud : 'Sin coordenadas registradas' }}</div>
                    </div>
                    <div class="col-lg-8">
                        @if ($mapsEmbedUrl)
                            <div class="rounded overflow-hidden border" style="height: 320px;">
                                <iframe src="{{ $mapsEmbedUrl }}" title="Mapa del establecimiento" width="100%" height="320" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        @else
                            <div class="alert alert-light border mb-0">
                                Este establecimiento no tiene coordenadas registradas para visualizar el mapa incrustado.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla Resumen por funcionario --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold">Resumen por funcionario</span>
                <span class="text-muted small">1 fila por RUT (jornadas sumadas)</span>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>RUT</th>
                                <th>Nombre</th>
                                <th>Estatuto</th>
                                <th>Escalafón</th>
                                <th class="text-end">Jornada total</th>
                                <th class="text-end">Básica total</th>
                                <th class="text-end">Media total</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($funcionariosResumen as $f)
                                <tr>
                                    <td class="text-nowrap">{{ $f->rut }}</td>
                                    <td>{{ $f->nombre }}</td>
                                    <td>{{ $f->estatuto }}</td>
                                    <td>{{ $f->escalafon }}</td>

                                    <td class="text-end fw-semibold">{{ $f->jornada_total }}</td>
                                    <td class="text-end">{{ $f->jornada_basica_total }}</td>
                                    <td class="text-end">{{ $f->jornada_media_total }}</td>

                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-ver-detalle"
                                            data-rut="{{ $f->rut }}">
                                            <i class="bi bi-search"></i> Ver detalle
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No hay registros de funcionarios para este establecimiento.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        {{-- DETALLE DE FUNCIONARIO --}}
        <div class="card" id="detalle-funcionario">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <span class="fw-semibold">Detalle de funcionario</span>
                    <div class="text-muted small" id="detalle-subtitulo">Selecciona un funcionario desde el resumen.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-limpiar-detalle">
                        Limpiar
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div id="detalle-placeholder" class="alert alert-light mb-0">
                    Presiona <strong>“Ver detalle”</strong> en un funcionario para ver sus contratos/registros en este
                    establecimiento.
                </div>

                <div id="detalle-contenido" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>RBD</th>
                                    <th>RUT</th>
                                    <th>Nombre</th>
                                    <th>F. nacimiento</th>

                                    <th>Estatuto</th>
                                    <th>Escalafón</th>

                                    <th>Tipo contrato</th>
                                    <th>F. ingreso</th>
                                    <th>F. término</th>
                                    <th>Financiamiento</th>

                                    <th class="text-end">Jornada</th>
                                    <th class="text-end">Básica</th>
                                    <th class="text-end">Media</th>

                                    <th class="text-end">Año</th>
                                    <th class="text-end">Mes</th>
                                </tr>
                            </thead>

                            <tbody id="detalle-tbody"></tbody>
                        </table>
                    </div>

                    <div class="border-top pt-3">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="fw-semibold">Total jornada</div>
                                <div class="fs-5" id="detalle-total-jornada">0</div>
                            </div>
                            <div class="col-md-4">
                                <div class="fw-semibold">Total jornada básica</div>
                                <div class="fs-5" id="detalle-total-basica">0</div>
                            </div>
                            <div class="col-md-4">
                                <div class="fw-semibold">Total jornada media</div>
                                <div class="fs-5" id="detalle-total-media">0</div>
                            </div>
                        </div>
                    </div>
                </div> {{-- /detalle-contenido --}}
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const registros = @json($registros);

            const placeholder = document.getElementById('detalle-placeholder');
            const contenido = document.getElementById('detalle-contenido');
            const tbody = document.getElementById('detalle-tbody');

            const subtitulo = document.getElementById('detalle-subtitulo');
            const totalJornadaEl = document.getElementById('detalle-total-jornada');
            const totalBasicaEl = document.getElementById('detalle-total-basica');
            const totalMediaEl = document.getElementById('detalle-total-media');

            const btnLimpiar = document.getElementById('btn-limpiar-detalle');
            const anchorDetalle = document.getElementById('detalle-funcionario');

            function escapeHtml(str) {
                return String(str ?? '').replace(/[&<>"']/g, (m) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                })[m]);
            }

            function toNumber(v) {
                if (v === null || v === undefined || v === '') return 0;
                const n = parseFloat(String(v).replace(',', '.'));
                return Number.isFinite(n) ? n : 0;
            }

            // ✅ YYYY-MM-DD o YYYY-MM-DD HH:MM:SS -> DD-MM-YYYY
            function formatDateDMY(value) {
                if (value === null || value === undefined) return '';

                const s = String(value).trim();
                if (!s) return '';

                // Tomar siempre solo la parte de fecha (sirve para ISO: YYYY-MM-DDT... y datetime: YYYY-MM-DD HH:MM:SS)
                const datePart = s.slice(0, 10); // "1974-03-26" desde "1974-03-26T00:00:00.000000Z"

                // Casos "indefinido"
                if (
                    datePart === '0000-00-00' ||
                    datePart === '00-00-0000' ||
                    /^0{4}-0{2}-0{2}$/.test(datePart) ||
                    /^0{2}-0{2}-0{4}$/.test(datePart)
                ) {
                    return 'Indefinido';
                }

                // Si ya viene en dd-mm-yyyy, devolverlo
                if (/^\d{2}-\d{2}-\d{4}$/.test(datePart)) {
                    return datePart;
                }

                // yyyy-mm-dd -> dd-mm-yyyy
                const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(datePart);
                if (m) {
                    const yyyy = m[1],
                        mm = m[2],
                        dd = m[3];
                    if (yyyy === '0000' || mm === '00' || dd === '00') return 'Indefinido';
                    return `${dd}-${mm}-${yyyy}`;
                }

                // Fallback: intentar parsear con Date
                const dt = new Date(s);
                if (!Number.isNaN(dt.getTime())) {
                    const dd = String(dt.getDate()).padStart(2, '0');
                    const mm = String(dt.getMonth() + 1).padStart(2, '0');
                    const yyyy = dt.getFullYear();
                    if (!yyyy) return 'Indefinido';
                    return `${dd}-${mm}-${yyyy}`;
                }

                // Si no se pudo parsear, devolver original
                return s;
            }


            function limpiarDetalle() {
                tbody.innerHTML = '';
                subtitulo.textContent = 'Selecciona un funcionario desde el resumen.';
                totalJornadaEl.textContent = '0';
                totalBasicaEl.textContent = '0';
                totalMediaEl.textContent = '0';

                contenido.classList.add('d-none');
                placeholder.classList.remove('d-none');
            }

            function renderDetallePorRut(rut) {
                const rows = registros.filter(r => String(r.rut ?? '') === String(rut ?? ''));

                if (!rows.length) {
                    limpiarDetalle();
                    subtitulo.textContent = `Sin registros para RUT ${rut}`;
                    return;
                }

                rows.sort((a, b) => {
                    const ay = toNumber(a.anio),
                        by = toNumber(b.anio);
                    if (ay !== by) return ay - by;
                    const am = toNumber(a.mes),
                        bm = toNumber(b.mes);
                    if (am !== bm) return am - bm;
                    return String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''));
                });

                const first = rows[0];
                subtitulo.textContent = `${first.nombre ?? ''} — RUT ${rut}`;

                let sumJ = 0,
                    sumB = 0,
                    sumM = 0;

                tbody.innerHTML = rows.map(r => {
                    sumJ += toNumber(r.jornada);
                    sumB += toNumber(r.jornada_basica);
                    sumM += toNumber(r.jornada_media);

                    return `
                <tr>
                    <td>${escapeHtml(r.rbd)}</td>
                    <td class="text-nowrap">${escapeHtml(r.rut)}</td>
                    <td>${escapeHtml(r.nombre)}</td>
                    <td class="text-nowrap">${escapeHtml(formatDateDMY(r.fecha_nacimiento))}</td>

                    <td>${escapeHtml(r.estatuto)}</td>
                    <td>${escapeHtml(r.escalafon)}</td>

                    <td>${escapeHtml(r.tipocontrato)}</td>
                    <td class="text-nowrap">${escapeHtml(formatDateDMY(r.fecha_ingreso))}</td>
                    <td class="text-nowrap">${escapeHtml(formatDateDMY(r.fecha_termino))}</td>
                    <td>${escapeHtml(r.financiamiento)}</td>

                    <td class="text-end">${escapeHtml(r.jornada)}</td>
                    <td class="text-end">${escapeHtml(r.jornada_basica)}</td>
                    <td class="text-end">${escapeHtml(r.jornada_media)}</td>

                    <td class="text-end">${escapeHtml(r.anio)}</td>
                    <td class="text-end">${escapeHtml(r.mes)}</td>
                </tr>
            `;
                }).join('');

                totalJornadaEl.textContent = sumJ;
                totalBasicaEl.textContent = sumB;
                totalMediaEl.textContent = sumM;

                placeholder.classList.add('d-none');
                contenido.classList.remove('d-none');

                anchorDetalle.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }

            document.querySelectorAll('.btn-ver-detalle').forEach(btn => {
                btn.addEventListener('click', () => renderDetallePorRut(btn.dataset.rut));
            });

            if (btnLimpiar) {
                btnLimpiar.addEventListener('click', () => {
                    limpiarDetalle();
                    anchorDetalle.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            }
        });
    </script>
@endsection
