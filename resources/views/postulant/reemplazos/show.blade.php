@extends('layouts.app')

@section('content')
    @php
        $s = $solicitud;
        $estab = $s->establecimiento;
        $mapsUrl = null;
        $mapsEmbedUrl = null;
        if ($estab?->latitud !== null && $estab?->longitud !== null) {
            $mapsUrl = 'https://www.google.com/maps?q=' . urlencode($estab->latitud . ',' . $estab->longitud);
            $lat = (float) $estab->latitud;
            $lng = (float) $estab->longitud;
            $delta = 0.0045;
            $left = $lng - $delta;
            $right = $lng + $delta;
            $bottom = $lat - $delta;
            $top = $lat + $delta;
            $mapsEmbedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='
                . urlencode($left . ',' . $bottom . ',' . $right . ',' . $top)
                . '&layer=mapnik&marker=' . urlencode($lat . ',' . $lng);
        } else {
            $mapsQuery = trim(collect([
                $estab?->nombre_establecimiento ?: null,
                $estab?->comuna ?: null,
                'SLEP Andalién Costa',
            ])->filter()->implode(', '));
            $mapsUrl = $mapsQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapsQuery) : null;
        }

        $estadoLabel = match ($s->estado) {
            'pendiente_uatp' => 'Pendiente UATP',
            'pendiente_gdp' => 'Pendiente GDP',
            'rechazada_uatp' => 'Rechazada UATP',
            'derivada_slep' => 'Derivada a SLEP',
            'aceptada' => 'Aceptada',
            'cerrado' => 'Cerrado',
            'anulada' => 'Anulada',
            default => ucfirst(str_replace('_', ' ', (string) $s->estado)),
        };

        $estadoBadge = match ($s->estado) {
            'pendiente_gdp' => 'text-bg-warning',
            'rechazada_uatp' => 'text-bg-danger',
            'derivada_slep' => 'text-bg-info',
            'aceptada' => 'text-bg-success',
            'cerrado' => 'text-bg-dark',
            'anulada' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">Detalle de reemplazo</h1>
            <div class="text-muted">Solicitud N° {{ $s->numero_solicitud }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if (!empty($s->orden_trabajo_pdf_path))
                    <a href="{{ route('postulant.reemplazos.ot', $s) }}" class="btn btn-outline-success-dark" target="_blank" rel="noopener">Ver OT</a>
            @endif
            @if (!empty($s->horario_titular_pdf_path))
                <a href="{{ route('postulant.reemplazos.horario-titular', $s) }}" class="btn btn-outline-info" target="_blank" rel="noopener">Horario titular</a>
            @endif
            @if (!empty($s->contrato_trabajo_firmado_pdf_path))
                <a href="{{ route('postulant.reemplazos.contrato-firmado', $s) }}" class="btn btn-outline-danger" target="_blank" rel="noopener">Contrato firmado</a>
            @endif
            @if ($estab)
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ubicacionEstablecimientoModal">
                    Ver ubicación
                </button>
            @endif
            <a href="{{ route('postulant.reemplazos.index') }}" class="btn btn-outline-primary">Volver</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Estado</div>
                    <div class="mt-2"><span class="badge {{ $estadoBadge }}">{{ $estadoLabel }}</span></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Inicio trabajo</div>
                    <div class="fw-semibold mt-2">{{ cl_plain_date($s->fecha_inicio_trabajo) }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Término</div>
                    <div class="fw-semibold mt-2">{{ cl_plain_date($s->fecha_termino) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white"><strong>Información general</strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Establecimiento</dt>
                        <dd class="col-sm-8">{{ $estab?->nombre_establecimiento ?? '—' }}</dd>

                        <dt class="col-sm-4">Dirección</dt>
                        <dd class="col-sm-8">{{ $estab?->clasificacion ?: 'Sin dirección registrada' }}</dd>

                        <dt class="col-sm-4">Comuna</dt>
                        <dd class="col-sm-8">{{ $estab?->comuna ?? '—' }}</dd>

                        <dt class="col-sm-4">RBD</dt>
                        <dd class="col-sm-8">{{ $estab?->rbd ?? '—' }}</dd>

                        <dt class="col-sm-4">Funcionario reemplazado</dt>
                        <dd class="col-sm-8">{{ $s->funcionarioTitular?->nombre ?? '—' }}</dd>

                        <dt class="col-sm-4">RUT funcionario</dt>
                        <dd class="col-sm-8">{{ $s->funcionarioTitular?->rut ?? '—' }}</dd>

                        <dt class="col-sm-4">Área de desempeño</dt>
                        <dd class="col-sm-8">{{ $s->areaDesempeno?->nombre ?? '—' }}</dd>

                        <dt class="col-sm-4">Tipo de reemplazo</dt>
                        <dd class="col-sm-8">{{ $s->tipo_reemplazo_otro ?: $s->tipo_reemplazo ?: '—' }}</dd>

                        <dt class="col-sm-4">Período solicitado</dt>
                        <dd class="col-sm-8">{{ cl_plain_date($s->fecha_inicio) }} - {{ cl_plain_date($s->fecha_termino) }}</dd>

                        <dt class="col-sm-4">Observaciones</dt>
                        <dd class="col-sm-8">{{ $s->observaciones ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white"><strong>Jornada informada</strong></div>
                <div class="card-body table-responsive">
                    @php
                        $totalBasica = (float) $s->jornadas->sum(fn ($j) => (float) ($j->reemplazo_basica ?? 0));
                        $totalMedia = (float) $s->jornadas->sum(fn ($j) => (float) ($j->reemplazo_media ?? 0));
                        $totalHoras = (float) $s->jornadas->sum(fn ($j) => (float) ($j->reemplazo_total ?? 0));
                    @endphp
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Financiamiento</th>
                                <th class="text-end">Horas básica</th>
                                <th class="text-end">Horas media</th>
                                <th class="text-end">Total horas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($s->jornadas as $j)
                                <tr>
                                    <td>{{ $j->financiamiento ?: '—' }}</td>
                                    <td class="text-end">{{ number_format((float) ($j->reemplazo_basica ?? 0), 2, '.', '') }}</td>
                                    <td class="text-end">{{ number_format((float) ($j->reemplazo_media ?? 0), 2, '.', '') }}</td>
                                    <td class="text-end">{{ number_format((float) ($j->reemplazo_total ?? 0), 2, '.', '') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No hay jornadas registradas para esta solicitud.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($s->jornadas->isNotEmpty())
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td>Total</td>
                                    <td class="text-end">{{ number_format($totalBasica, 2, '.', '') }}</td>
                                    <td class="text-end">{{ number_format($totalMedia, 2, '.', '') }}</td>
                                    <td class="text-end">{{ number_format($totalHoras, 2, '.', '') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white"><strong>Ubicación del establecimiento</strong></div>
                <div class="card-body">
                    @if ($mapsEmbedUrl)
                        <div class="rounded overflow-hidden border mb-3" style="height: 300px;">
                            <iframe title="Mapa establecimiento" src="{{ $mapsEmbedUrl }}" width="100%" height="300" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    @else
                        <div class="alert alert-light border mb-3">Este establecimiento no tiene coordenadas registradas para visualizar el mapa incrustado.</div>
                    @endif
                    <div class="small text-muted">
                        @if ($estab?->latitud !== null && $estab?->longitud !== null)
                            Coordenadas: {{ $estab->latitud }}, {{ $estab->longitud }}
                        @else
                            Sin coordenadas registradas.
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white"><strong>Estado del proceso</strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Fecha solicitud</dt>
                        <dd class="col-sm-7">{{ cl_datetime($s->created_at, 'd-m-Y H:i') }}</dd>

                        <dt class="col-sm-5">Inicio trabajo</dt>
                        <dd class="col-sm-7">{{ cl_plain_date($s->fecha_inicio_trabajo) }}</dd>

                        <dt class="col-sm-5">Orden de trabajo</dt>
                        <dd class="col-sm-7">
                            @if (!empty($s->orden_trabajo_pdf_path))
                                <a href="{{ route('postulant.reemplazos.ot', $s) }}" target="_blank" rel="noopener">Ver OT</a>
                            @else
                                No disponible
                            @endif
                        </dd>

                        <dt class="col-sm-5">Horario titular</dt>
                        <dd class="col-sm-7">
                            @if (!empty($s->horario_titular_pdf_path))
                                <a href="{{ route('postulant.reemplazos.horario-titular', $s) }}" target="_blank" rel="noopener">Ver horario</a>
                            @else
                                No disponible
                            @endif
                        </dd>

                        <dt class="col-sm-5">Contrato firmado</dt>
                        <dd class="col-sm-7">
                            @if (!empty($s->contrato_trabajo_firmado_pdf_path))
                                <a href="{{ route('postulant.reemplazos.contrato-firmado', $s) }}" target="_blank" rel="noopener">Ver contrato firmado</a>
                                @if (!empty($s->contrato_trabajo_firmado_enviado_at))
                                    <div class="text-muted small">Notificado el {{ cl_datetime($s->contrato_trabajo_firmado_enviado_at, 'd-m-Y H:i') }}</div>
                                @endif
                            @else
                                No disponible
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><strong>Observación SLEP</strong></div>
                <div class="card-body">
                    @if (!empty($s->observacion_slep))
                        <div class="mb-2">{{ $s->observacion_slep }}</div>
                        <div class="text-muted small">
                            Informada por {{ $s->observacionSlepUser?->nombre_completo ?? $s->observacionSlepUser?->email ?? 'SLEP' }}
                            el {{ cl_datetime($s->observacion_slep_at, 'd-m-Y H:i') }}.
                        </div>
                    @else
                        <div class="text-muted">Esta solicitud no registra observaciones SLEP.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($estab)
        <div class="modal fade" id="ubicacionEstablecimientoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ubicación del establecimiento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2"><strong>{{ $estab->nombre_establecimiento }}</strong></div>
                        <div class="mb-2 text-muted">Dirección: {{ $estab->clasificacion ?: 'Sin dirección registrada' }}</div>
                        <div class="text-muted mb-2">Comuna: {{ $estab->comuna ?? 'Sin comuna registrada' }}</div>
                        <div class="text-muted">RBD: {{ $estab->rbd ?? '—' }}</div>
                        <div class="text-muted mb-3">Coordenadas: {{ ($estab->latitud !== null && $estab->longitud !== null) ? $estab->latitud . ', ' . $estab->longitud : 'Sin coordenadas registradas' }}</div>
                        @if ($mapsEmbedUrl)
                            <div class="rounded overflow-hidden border" style="height: 300px;">
                                <iframe title="Mapa establecimiento" src="{{ $mapsEmbedUrl }}" width="100%" height="300" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        @else
                            <div class="alert alert-light border mb-0">Este establecimiento no tiene coordenadas registradas para visualizar el mapa incrustado.</div>
                        @endif
                    </div>
                    <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="copiarDireccionDetalleBtn" data-copy-text="{{ trim(($estab->clasificacion ?: '') . (($estab->comuna ?? '') ? ', ' . $estab->comuna : '')) }}">Copiar dirección</button>
                        <div class="d-flex gap-2">
                            @if ($mapsUrl)
                                <a href="{{ $mapsUrl }}" class="btn btn-outline-primary" target="_blank" rel="noopener">Abrir en Google Maps</a>
                            @endif
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('copiarDireccionDetalleBtn')?.addEventListener('click', async function () {
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
