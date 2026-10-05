@if ($proceso2027['aplica'] ?? false)
    @php
        $pasosNavegacion = collect($proceso2027['pasos'] ?? []);
        $etapaActual = $pasosNavegacion->keys()->first(fn ($key) => ! ($pasosNavegacion[$key]['completo'] ?? false));
        $rolProceso = $activeRole ?? auth()->user()?->activeRoleName();
        $accionesEtapas = [
            'planes' => ['url' => in_array($rolProceso, ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'supervisor_plani'], true) ? route('admin.establecimiento-planes.index', ['anio' => $anio, 'establecimiento_id' => $establecimiento->id]) : null, 'label' => 'Configurar planes', 'ayuda' => 'Solicite la configuración al establecimiento, Administración, UATP o Supervisión de Planificación.'],
            'subsectores' => ['url' => '#dotacion-docentes-subsector', 'label' => 'Asociar docentes'],
            'combinaciones' => ['url' => '#dotacion-decision-combinacion', 'label' => 'Definir combinación'],
            'normativas' => ['url' => '#dotacion-definicion-normativas', 'label' => 'Definir funciones'],
            'maximos' => ['url' => '#dotacion-maximos', 'label' => 'Revisar máximos'],
            'asignacion' => ['url' => route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'asignacion', 'asig_pendientes' => 1]).'#dotacion-asignacion', 'label' => 'Asignar horas pendientes'],
        ];
    @endphp
    <section class="card dotacion-section mb-4" aria-labelledby="dotacion-etapas-titulo">
        <div class="dotacion-section-header d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="dotacion-eyebrow">Proceso guiado · {{ $anio }}</div>
                <h2 id="dotacion-etapas-titulo" class="h5 fw-bold mb-1">Etapas y siguiente acción</h2>
                <p class="small text-muted mb-0">{{ $pasosNavegacion->where('completo', true)->count() }} de {{ $pasosNavegacion->count() }} etapas completas. El estado corresponde a los datos guardados.</p>
            </div>
            @if ($etapaActual && !empty($accionesEtapas[$etapaActual]['url']))
                <a class="btn btn-primary rounded-pill" data-dotacion-contexto-salida href="{{ $accionesEtapas[$etapaActual]['url'] }}"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i> {{ $accionesEtapas[$etapaActual]['label'] }}</a>
            @elseif ($etapaActual)
                <p class="small text-muted mb-0">{{ $accionesEtapas[$etapaActual]['ayuda'] ?? 'Revise la configuración pendiente.' }}</p>
            @else
                <span class="badge rounded-pill text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Cobertura obligatoria completa</span>
            @endif
        </div>
        <nav class="card-body" aria-label="Etapas del proceso de dotación">
            <ol class="dotacion-steps list-unstyled mb-0">
                @foreach ($pasosNavegacion as $key => $paso)
                    <li class="dotacion-step {{ $paso['completo'] ? 'is-complete' : ($etapaActual === $key ? 'is-current' : '') }}" @if ($etapaActual === $key) aria-current="step" @endif>
                        <div class="small text-muted">Etapa {{ $loop->iteration }}</div>
                        <div class="fw-semibold mb-2">{{ $paso['label'] }}</div>
                        <span class="badge rounded-pill {{ $paso['completo'] ? 'text-bg-success' : ($etapaActual === $key ? 'text-bg-info' : 'text-bg-light border') }}">{{ $paso['completo'] ? 'Completada' : ($etapaActual === $key ? 'Por completar ahora' : 'Pendiente') }}</span>
                        @if (!empty($paso['detalle']))<p class="small text-muted mt-2 mb-0">{{ $paso['detalle'] }}</p>@endif
                        @if (!empty($accionesEtapas[$key]['url']))
                            <a class="d-block small fw-semibold mt-2" data-dotacion-contexto-salida href="{{ $accionesEtapas[$key]['url'] }}">{{ $accionesEtapas[$key]['label'] }}<span class="visually-hidden">: {{ $paso['label'] }}</span></a>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    </section>
@endif
