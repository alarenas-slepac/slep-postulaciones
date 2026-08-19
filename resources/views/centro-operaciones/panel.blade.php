@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
@php
    $metricas = $datos['metricas'];
    $fechaLocal = \Carbon\CarbonImmutable::parse($datos['fecha'], config('centro_operaciones.timezone'));
    $estadoLabels = ['operativo' => 'Operativo', 'alerta' => 'Alerta', 'critico' => 'Crítico', 'sin_reporte' => 'Sin reporte'];
    $slepLogoUrl = asset(config('brand.logo_slep', 'branding/logo-andaliencosta.png'));
@endphp
<div class="co-shell" data-co-panel data-url="{{ route('centro-operaciones.datos', ['fecha' => $datos['fecha']]) }}" data-report-url="{{ route('centro-operaciones.reportes.show', ['reporte' => '__ID__']) }}" data-tv="{{ $modoTv ? '1' : '0' }}">
    <header class="co-hero">
        <div class="co-hero-brand">
            <div class="co-slep-logo">
                <img src="{{ $slepLogoUrl }}" alt="Servicio Local de Educación Pública Andalién Costa">
            </div>
            <div class="co-hero-copy">
                <div class="co-eyebrow">SLEP Andalién Costa</div>
                <h1>Centro de Operaciones</h1>
                <p>Monitoreo diario de establecimientos y continuidad de servicios.</p>
            </div>
        </div>
        <div class="co-hero-actions">
            <div class="co-update"><i class="bi bi-arrow-repeat"></i> Actualizado <span data-co-updated>{{ now(config('centro_operaciones.timezone'))->format('H:i') }}</span></div>
            @unless($modoTv)
                <form method="GET" class="co-date-form">
                    <label for="co-fecha">Fecha</label>
                    <input id="co-fecha" type="date" name="fecha" value="{{ $datos['fecha'] }}" max="{{ now(config('centro_operaciones.timezone'))->toDateString() }}" onchange="this.form.submit()">
                </form>
                <a class="btn btn-outline-primary" href="{{ route('centro-operaciones.tv', ['fecha' => $datos['fecha']]) }}" target="_blank">
                    <i class="bi bi-tv"></i> Modo TV
                </a>
                <a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.reportes.history') }}">
                    <i class="bi bi-clock-history"></i> Historial
                </a>
            @endunless
        </div>
    </header>

    <section class="co-kpis" aria-label="Resumen general">
        <article class="co-kpi co-kpi--blue">
            <i class="bi bi-buildings"></i>
            <div><span>Reportes recibidos</span><strong><b data-metric="reportados">{{ $metricas['reportados'] }}</b> / {{ $metricas['establecimientos_total'] }}</strong><small><b data-metric="cobertura_reportes">{{ number_format($metricas['cobertura_reportes'], 1, ',', '.') }}</b>% de cobertura</small></div>
        </article>
        <article class="co-kpi co-kpi--green">
            <i class="bi bi-check-circle"></i>
            <div><span>Operativos</span><strong data-metric="operativos">{{ $metricas['operativos'] }}</strong><small>establecimientos</small></div>
        </article>
        <article class="co-kpi co-kpi--yellow">
            <i class="bi bi-exclamation-triangle"></i>
            <div><span>En alerta</span><strong data-metric="alertas">{{ $metricas['alertas'] }}</strong><small>requieren seguimiento</small></div>
        </article>
        <article class="co-kpi co-kpi--red">
            <i class="bi bi-exclamation-octagon"></i>
            <div><span>Críticos</span><strong data-metric="criticos">{{ $metricas['criticos'] }}</strong><small>atención prioritaria</small></div>
        </article>
        <article class="co-kpi co-kpi--cyan">
            <i class="bi bi-mortarboard"></i>
            <div><span>Estudiantes presentes</span><strong data-metric="estudiantes_presentes">{{ number_format($metricas['estudiantes_presentes'], 0, ',', '.') }}</strong><small><b data-metric="asistencia_estudiantes">{{ number_format($metricas['asistencia_estudiantes'], 1, ',', '.') }}</b>% de matrícula reportada</small></div>
        </article>
        <article class="co-kpi co-kpi--teal">
            <i class="bi bi-people"></i>
            <div><span>Funcionarios presentes</span><strong data-metric="funcionarios_presentes">{{ number_format($metricas['funcionarios_presentes'], 0, ',', '.') }}</strong><small><b data-metric="asistencia_funcionarios">{{ number_format($metricas['asistencia_funcionarios'], 1, ',', '.') }}</b>% de dotación reportada</small></div>
        </article>
        <article class="co-kpi co-kpi--gray">
            <i class="bi bi-hourglass-split"></i>
            <div><span>Sin reporte</span><strong data-metric="sin_reporte">{{ $metricas['sin_reporte'] }}</strong><small>pendientes del día</small></div>
        </article>
        <article class="co-kpi co-kpi--purple">
            <i class="bi bi-shield-exclamation"></i>
            <div><span>Incidencias activas</span><strong data-metric="incidencias_activas">{{ $metricas['incidencias_activas'] }}</strong><small><b data-metric="incidencias_del_dia">{{ $metricas['incidencias_del_dia'] }}</b> reportadas en la fecha</small></div>
        </article>
    </section>

    <div class="co-grid co-grid--main">
        <section class="co-card">
            <div class="co-card-head">
                <div><span class="co-eyebrow">Resumen territorial</span><h2>Estado por comuna</h2></div>
                <span class="co-date-chip"><i class="bi bi-calendar3"></i> {{ $fechaLocal->translatedFormat('d M Y') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table co-table align-middle mb-0">
                    <thead><tr><th>Comuna</th><th>Total</th><th class="co-col-reportados">Reportados</th><th>Operativos</th><th>Alertas</th><th>Críticos</th><th>Sin reporte</th><th>Asistencia</th></tr></thead>
                    <tbody data-co-communes>
                    @foreach($datos['comunas'] as $comuna)
                        <tr>
                            <td class="fw-semibold">{{ $comuna['comuna'] }}</td><td>{{ $comuna['establecimientos'] }}</td><td class="co-col-reportados">{{ $comuna['reportados'] }}</td>
                            <td><span class="co-dot co-dot--operativo"></span>{{ $comuna['operativos'] }}</td>
                            <td><span class="co-dot co-dot--alerta"></span>{{ $comuna['alertas'] }}</td>
                            <td><span class="co-dot co-dot--critico"></span>{{ $comuna['criticos'] }}</td>
                            <td><span class="co-dot co-dot--sin_reporte"></span>{{ $comuna['sin_reporte'] }}</td>
                            <td>{{ number_format($comuna['asistencia'], 1, ',', '.') }}%</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="co-card co-map-card">
            <div class="co-card-head"><div><span class="co-eyebrow">Georreferencia</span><h2>Mapa de establecimientos</h2></div></div>
            <div class="co-map-toolbar" aria-label="Controles del mapa territorial">
                <span class="co-map-toolbar-label"><i class="bi bi-geo-alt"></i> Enfocar comuna</span>
                <div class="co-map-commune-buttons" role="group" aria-label="Enfocar mapa por comuna">
                    <button type="button" class="co-map-commune-button is-active" data-map-commune="" aria-controls="co-map" aria-pressed="true">Ver territorio</button>
                    @foreach(['Lota', 'Coronel', 'San Pedro de la Paz', 'Santa Juana'] as $comunaMapa)
                        <button type="button" class="co-map-commune-button" data-map-commune="{{ $comunaMapa }}" aria-controls="co-map" aria-pressed="false">{{ $comunaMapa }}</button>
                    @endforeach
                </div>
            </div>
            <div id="co-map" aria-label="Mapa de establecimientos"></div>
            <div class="co-map-legend">
                @foreach($estadoLabels as $estado => $label)<span><i class="co-dot co-dot--{{ $estado }}"></i>{{ $label }}</span>@endforeach
            </div>
        </section>
    </div>

    <div class="co-grid co-grid--lower">
        <section class="co-card">
            <div class="co-card-head"><div><span class="co-eyebrow">Continuidad operacional</span><h2>Estado de servicios</h2></div></div>
            <div class="co-services" data-co-services>
            @foreach($datos['servicios'] as $servicio)
                <div class="co-service-row co-service--{{ $servicio['codigo'] }}">
                    <div class="co-service-label"><i class="bi {{ $servicio['icon'] }}"></i><span>{{ $servicio['label'] }}</span></div>
                    <div class="co-progress"><span style="width: {{ $servicio['porcentaje_operativo'] }}%"></span></div>
                    <strong>{{ number_format($servicio['porcentaje_operativo'], 1, ',', '.') }}%</strong>
                </div>
            @endforeach
            </div>
        </section>

        <section class="co-card">
            <div class="co-card-head"><div><span class="co-eyebrow">Seguimiento</span><h2>Establecimientos con alerta</h2></div><span class="co-count">{{ count($datos['alertas']) }}</span></div>
            <div class="co-list" data-co-alerts>
                @forelse(array_slice($datos['alertas'], 0, 7) as $alerta)
                    <a href="{{ $alerta['reporte_id'] ? route('centro-operaciones.reportes.show', $alerta['reporte_id']) : '#' }}" class="co-list-item">
                        <span class="co-status-bar co-status-bar--{{ $alerta['estado'] }}"></span>
                        <span><strong>{{ $alerta['nombre'] }}</strong><small>{{ $alerta['comuna'] }} · {{ $estadoLabels[$alerta['estado']] }}</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                @empty
                    <div class="co-empty"><i class="bi bi-check-circle"></i> No hay establecimientos en alerta o estado crítico.</div>
                @endforelse
            </div>
        </section>

        <section class="co-card">
            <div class="co-card-head"><div><span class="co-eyebrow">Acumulado</span><h2>Incidencias activas</h2></div><span class="co-count">{{ count($datos['incidencias_activas']) }}</span></div>
            <div class="co-list" data-co-incidents>
                @forelse(array_slice($datos['incidencias_activas'], 0, 7) as $incidencia)
                    <div class="co-list-item">
                        <span class="co-status-bar co-status-bar--{{ $incidencia['severidad'] }}"></span>
                        <span><strong>{{ $incidencia['label'] }}</strong><small>{{ $incidencia['establecimiento'] }} · {{ $incidencia['comuna'] }}</small></span>
                    </div>
                @empty
                    <div class="co-empty"><i class="bi bi-shield-check"></i> No existen incidencias activas.</div>
                @endforelse
            </div>
        </section>
    </div>

    <script type="application/json" id="co-dashboard-data">@json($datos)</script>
</div>
@endsection

@push('scripts')
    @vite('resources/js/centro-operaciones.js')
@endpush
