@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $detalle = $sobredotacion['aula'] ?? [];
    $ajusteItems = collect($detalle['ajustes'] ?? []);
    $contratosProtegidos = collect($sobredotacion['protegidos'] ?? []);
    $sobredotacionResumen = $detalle['resumen'] ?? [];
    $vacantesPorBloque = $sobredotacion['vacantes_por_bloque'] ?? [];
    $justificacionesSobredotacion = $justificacionesSobredotacion ?? collect();
    $declaradasAsignadas = $sobredotacionResumen['horas_declaradas_asignadas'] ?? $sobredotacionResumen['horas_declaradas_ajustables'] ?? 0;
    $formula = $detalle['formula'] ?? [];
    $brechaEstructural = (float) ($sobredotacionResumen['brecha_estructural'] ?? 0);
    $resultadoEstructural = $brechaEstructural < -0.01
        ? ['label' => 'Sobredotación estructural', 'value' => $fmt(abs($brechaEstructural)), 'class' => 'alert-danger']
        : ($brechaEstructural > 0.01
            ? ['label' => 'Horas estructuralmente necesarias', 'value' => '+'.$fmt($brechaEstructural), 'class' => 'alert-success']
            : ['label' => 'Dotación estructural cuadrada', 'value' => '0', 'class' => 'alert-primary']);
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start gap-3">
            <span class="dotacion-icon" style="width:40px;height:40px;background:#dc3545;"><i class="bi bi-person-exclamation"></i></span>
            <div>
                <div class="dotacion-eyebrow">Análisis contractual por docente</div>
                <h2 class="h5 fw-bold mb-1">Detalle sobredotación</h2>
                <div class="text-muted small">Distingue las horas contractuales realmente sin asignación de las horas declaradas que sí están asignadas, pero pueden revisarse o redistribuirse.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted">Los contratos con Fuero maternal u Horas gremiales quedan protegidos en su totalidad y se excluyen de las nóminas de posible reducción y del universo sujeto a revisión. Las horas no necesarias siguen fuera del contrato contabilizado.</p>

        <div class="alert {{ $resultadoEstructural['class'] }} border-0 rounded-4">
            <div class="fw-bold mb-1">Sobredotación estructural</div>
            <div class="fs-4 fw-bold">{{ $resultadoEstructural['value'] }}</div>
        </div>

            <div class="row g-3">
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes analizados</div><div class="h4 fw-bold mb-0">{{ number_format((int) ($sobredotacionResumen['docentes_analizados'] ?? 0), 0, ',', '.') }}</div><div class="small text-muted">Con contrato Aula o asignación</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Contrato Aula</div><div class="h4 fw-bold mb-0">{{ $fmt($sobredotacionResumen['horas_dotacion_total'] ?? 0) }}</div><div class="small text-muted">Contrato individualizado</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-success-subtle h-100"><div class="small text-muted">Asignaciones protegidas</div><div class="h4 fw-bold text-success mb-0">{{ $fmt($sobredotacionResumen['horas_asignadas_protegidas'] ?? 0) }}</div><div class="small text-muted">Plan, colaboración y normativa</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-warning-subtle h-100"><div class="small text-muted">Declaradas docentes / requeridas</div><div class="h4 fw-bold text-warning-emphasis mb-0">{{ $fmt($declaradasAsignadas) }} / {{ $fmt($sobredotacionResumen['horas_declaradas_requeridas'] ?? $formula['bloque_declarado'] ?? 0) }}</div><div class="small text-muted">Pendientes de cobertura docente: {{ $fmt($sobredotacionResumen['horas_declaradas_pendientes'] ?? 0) }}</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Contrato sin asignación registrada</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</div><div class="small text-muted">Horas contractuales vacantes</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-primary-subtle h-100"><div class="small text-muted">Sin asignación Planta</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</div><div class="small text-muted">Horas titulares</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-info-subtle h-100"><div class="small text-muted">Sin asignación Contrata</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</div><div class="small text-muted">Horas a contrata</div></div></div>
                <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-secondary-subtle h-100"><div class="small text-muted">Universo sujeto a revisión</div><div class="h4 fw-bold text-secondary mb-0">{{ $fmt($sobredotacionResumen['horas_universo_revision'] ?? $sobredotacionResumen['horas_potencial_ajuste'] ?? 0) }}</div><div class="small text-muted">Sin asignación + declaradas asignadas</div></div></div>
            </div>
    </div>
</div>

@include('admin.dotacion-establecimiento.partials._conciliacion_general')

@if ($contratosProtegidos->isNotEmpty())
    <section class="card dotacion-section mb-4" aria-labelledby="contratos-protegidos-titulo">
        <div class="dotacion-section-header">
            <h2 id="contratos-protegidos-titulo" class="h5 fw-bold mb-1">Contratos protegidos por situación docente</h2>
            <p class="small text-muted mb-0">Nómina informativa del establecimiento, excluida de las propuestas de reducción. Incluye las horas originales completas, aunque parte o todas estén clasificadas como no necesarias.</p>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Docente</th><th scope="col">Situación</th><th scope="col" class="text-end">Contrato original protegido</th><th scope="col" class="text-end">Horas necesarias contabilizadas</th></tr></thead>
                <tbody>
                    @foreach ($contratosProtegidos as $protegido)
                        <tr><th scope="row" class="fw-normal"><div class="fw-semibold">{{ $protegido['nombre'] }}</div><div class="small text-muted">{{ $protegido['rut'] }}</div></th><td>{{ $protegido['motivo_proteccion'] }}</td><td class="text-end fw-bold">{{ $fmt($protegido['contrato_original']) }}</td><td class="text-end">{{ $fmt($protegido['contrato_considerado']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body small text-muted">En la categoría consultada, {{ $fmt($sobredotacionResumen['horas_sobredotacion_protegida'] ?? 0) }} h de saldo protegido quedan fuera de revisión. También se excluyen {{ $fmt($sobredotacionResumen['horas_declaradas_protegidas'] ?? 0) }} h de funciones declaradas, manteniendo su cobertura registrada.</div>
    </section>
@endif

    <div class="alert alert-info border-0 rounded-4 small">
        <i class="bi bi-info-circle"></i>
        <strong>Cálculo individual:</strong> las funciones directivas, técnico-pedagógicas y planes normativos se imputan directamente al docente que los tiene asignados, junto con contrato plan y trabajo colaborativo PIE. Las horas declaradas también reducen el saldo sin asignación, pero se informan separadamente como posibles ajustes. En contratos mixtos, las asignaciones cubren primero Planta y luego Contrata.
    </div>

    @if ((float) ($sobredotacionResumen['horas_sobreasignadas'] ?? 0) > 0.01)
        <div class="alert alert-warning border-0 rounded-4 small">
            <i class="bi bi-exclamation-triangle"></i> Existen {{ $fmt($sobredotacionResumen['horas_sobreasignadas']) }} hora(s) asignadas por sobre el contrato Aula de sus docentes. Se informan en la tabla para revisión y no generan horas contractuales adicionales.
        </div>
    @endif

    <div class="card dotacion-section mb-4">
        <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="dotacion-eyebrow">Horas contractuales vacantes</div>
                <h2 class="h5 fw-bold mb-1">Contrato sin asignación registrada</h2>
                <div class="text-muted small">Saldos contractuales por bloque, descontando las asignaciones registradas y las horas reservadas para otras funciones. Los contratos protegidos quedan fuera de esta nómina.</div>
                <div class="text-muted small mt-1">En Parvularia, la fracción inferior a 1 h que completa el contrato al redondear hacia arriba se netea y no figura como vacante.</div>
            </div>
            <span class="badge rounded-pill text-bg-danger">{{ collect($vacantesPorBloque)->sum(fn ($bloque) => collect($bloque['items'] ?? [])->count()) }} saldo(s)</span>
        </div>
        @if (session('success'))
            <div class="alert alert-success rounded-4 mx-3 mt-3 mb-0" role="status"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
        @endif
        @if (! ($justificacionesSobredotacionTableReady ?? false))
            <div class="alert alert-warning rounded-4 mx-3 mt-3 mb-0" role="alert">El registro de justificaciones estará disponible después de ejecutar la migración de esta actualización.</div>
        @else
            <div class="px-3 py-2 small text-muted">El establecimiento debe fundamentar por separado la supresión de horas titulares y la no renovación de horas a contrata de cada docente con saldo. Si el saldo cambia, actualice su justificación.</div>
        @endif
        @foreach (['plan_estudio' => 'Plan de estudio', 'parvularia' => 'Educación Parvularia', 'pie' => 'PIE'] as $claveBloque => $tituloBloque)
            @php
                $bloqueVacante = $vacantesPorBloque[$claveBloque] ?? [];
            @endphp
            <section class="border-top" aria-labelledby="vacantes-{{ $claveBloque }}">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-3 bg-light">
                    <div><h3 id="vacantes-{{ $claveBloque }}" class="h6 fw-bold mb-0">{{ $tituloBloque }}</h3><div class="small text-muted">{{ collect($bloqueVacante['items'] ?? [])->count() }} docente(s) con saldo libre</div></div>
                    <span class="badge rounded-pill text-bg-danger">{{ $fmt($bloqueVacante['horas_total'] ?? 0) }} h sin asignación</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th scope="col">RUT</th><th scope="col">Docente</th><th scope="col">Función</th><th scope="col" class="text-end">Contrato del bloque</th><th scope="col" class="text-end">Sin asignación</th><th scope="col" class="text-end">Planta</th><th scope="col" class="text-end">Contrata</th><th scope="col">Justificación</th></tr></thead>
                        <tbody>
                            @forelse (($bloqueVacante['items'] ?? collect()) as $docente)
                                @php
                                    $rutNormalizado = \App\Support\DotacionEstablecimientoCalculator::normalizeRut($docente['rut']);
                                    $idJustificacion = 'justificacion-'.$claveBloque.'-'.$rutNormalizado;
                                    $tiposHoras = [
                                        'titular' => ['horas' => (float) $docente['horas_sobredotacion_planta'], 'titulo' => 'Supresión de horas titulares'],
                                        'contrata' => ['horas' => (float) $docente['horas_sobredotacion_contrata'], 'titulo' => 'No renovación de horas a contrata'],
                                    ];
                                    $pendientes = collect($tiposHoras)->filter(function ($tipo, $calidad) use ($justificacionesSobredotacion, $claveBloque, $docente) {
                                        if ($tipo['horas'] <= 0.01) return false;
                                        $registro = ($justificacionesSobredotacion ?? collect())->get(\App\Models\DotacionSobredotacionJustificacion::clave($claveBloque, $docente['rut'], $calidad));
                                        return ! $registro || ! $registro->vigentePara($tipo['horas']);
                                    })->count();
                                    $mostrarErrores = old('bloque') === $claveBloque && \App\Support\DotacionEstablecimientoCalculator::normalizeRut(old('docente_rut')) === $rutNormalizado;
                                @endphp
                                <tr>
                                    <td class="text-nowrap fw-semibold">{{ $docente['rut'] }}</td>
                                    <td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['tipo_contrato'] }}</div></td>
                                    <td>{{ $docente['funcion'] }}</td>
                                    <td class="text-end fw-semibold">{{ $fmt($docente['horas_contrato_categoria']) }}</td>
                                    <td class="text-end text-danger fw-bold">{{ $fmt($docente['horas_sobredotacion_total']) }}</td>
                                    <td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_sobredotacion_planta']) }}</td>
                                    <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_sobredotacion_contrata']) }}</td>
                                    <td class="text-nowrap">
                                        @if (($justificacionesSobredotacionTableReady ?? false))
                                            <span class="badge rounded-pill {{ $pendientes ? 'text-bg-warning' : 'text-bg-success' }}">{{ $pendientes ? $pendientes.' pendiente(s)' : 'Completa' }}</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill ms-1" data-bs-toggle="collapse" data-bs-target="#{{ $idJustificacion }}" aria-expanded="{{ $mostrarErrores ? 'true' : 'false' }}" aria-controls="{{ $idJustificacion }}">{{ ($canManageJustificacionesSobredotacion ?? false) ? 'Justificar' : 'Ver motivos' }}</button>
                                        @else
                                            <span class="badge rounded-pill text-bg-secondary">No disponible</span>
                                        @endif
                                    </td>
                                </tr>
                                @if (($justificacionesSobredotacionTableReady ?? false))
                                    <tr><td colspan="8" class="p-0 border-0">
                                        <div id="{{ $idJustificacion }}" class="collapse {{ $mostrarErrores ? 'show' : '' }}">
                                            <div class="p-3 bg-light border-top border-bottom">
                                                <div class="row g-3">
                                                    @foreach ($tiposHoras as $calidad => $tipo)
                                                        @if ($tipo['horas'] > 0.01)
                                                            @php
                                                                $registro = ($justificacionesSobredotacion ?? collect())->get(\App\Models\DotacionSobredotacionJustificacion::clave($claveBloque, $docente['rut'], $calidad));
                                                                $vigente = $registro?->vigentePara($tipo['horas']) ?? false;
                                                                $idCampo = $idJustificacion.'-'.$calidad;
                                                                $esFormularioActivo = $mostrarErrores && old('tipo_horas') === $calidad;
                                                            @endphp
                                                            <div class="col-lg-6">
                                                                <div class="card h-100 border rounded-4 shadow-sm">
                                                                    <div class="card-body">
                                                                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                                                                            <div><h4 class="h6 fw-bold mb-1">{{ $tipo['titulo'] }}</h4><div class="small text-muted">{{ $fmt($tipo['horas']) }} h sin asignación · {{ $tituloBloque }}</div></div>
                                                                            <span class="badge rounded-pill {{ $vigente ? 'text-bg-success' : 'text-bg-warning' }}">{{ $vigente ? 'Registrada' : ($registro ? 'Actualizar' : 'Pendiente') }}</span>
                                                                        </div>
                                                                        @if ($registro && ! $vigente)
                                                                            <div class="small text-warning-emphasis mb-2">La justificación anterior correspondía a {{ $fmt($registro->horas_detectadas) }} h. Revísela para el saldo actual.</div>
                                                                        @endif
                                                                        @if (($canManageJustificacionesSobredotacion ?? false))
                                                                            <form method="POST" action="{{ route('admin.dotacion-establecimiento.sobredotacion.justificaciones.store', $establecimiento) }}">
                                                                                @csrf
                                                                                <input type="hidden" name="anio" value="{{ $anio }}">
                                                                                <input type="hidden" name="bloque" value="{{ $claveBloque }}">
                                                                                <input type="hidden" name="tipo_horas" value="{{ $calidad }}">
                                                                                <input type="hidden" name="docente_rut" value="{{ $docente['rut'] }}">
                                                                                <label class="form-label fw-semibold" for="{{ $idCampo }}">Fundamento <span class="text-danger">*</span></label>
                                                                                <textarea class="form-control rounded-3 {{ $esFormularioActivo && $errors->any() ? 'is-invalid' : '' }}" id="{{ $idCampo }}" name="justificacion" rows="3" minlength="10" maxlength="3000" required>{{ $esFormularioActivo ? old('justificacion') : $registro?->justificacion }}</textarea>
                                                                                @if ($esFormularioActivo && $errors->any())
                                                                                    <div class="invalid-feedback d-block">{{ $errors->first() }}</div>
                                                                                @endif
                                                                                <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                                                                                    <span class="small text-muted">{{ $registro?->updated_at?->format('d-m-Y H:i') }}</span>
                                                                                    <button type="submit" class="btn btn-primary rounded-pill px-3"><i class="bi bi-check-circle me-1"></i>Guardar</button>
                                                                                </div>
                                                                            </form>
                                                                        @else
                                                                            <p class="mb-0 text-break">{{ $registro?->justificacion ?? 'El establecimiento aún no registra el fundamento.' }}</p>
                                                                            @if ($registro)<div class="small text-muted mt-2">Actualizada: {{ $registro->updated_at?->format('d-m-Y H:i') }}</div>@endif
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </td></tr>
                                @endif
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-4">No hay horas contractuales sin asignación en este bloque.</td></tr>
                            @endforelse
                        </tbody>
                        @if (collect($bloqueVacante['items'] ?? [])->isNotEmpty())
                            <tfoot class="table-light fw-bold"><tr><td colspan="4">Total {{ $tituloBloque }}</td><td class="text-end text-danger">{{ $fmt($bloqueVacante['horas_total']) }}</td><td class="text-end text-primary">{{ $fmt($bloqueVacante['horas_planta']) }}</td><td class="text-end text-info">{{ $fmt($bloqueVacante['horas_contrata']) }}</td><td></td></tr></tfoot>
                        @endif
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    <div class="card dotacion-section">
        <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="dotacion-eyebrow">Funciones no normativas asignadas</div>
                <h2 class="h5 fw-bold mb-1">Funciones declaradas asignadas a docentes (revisables)</h2>
                <div class="text-muted small">Son horas del bloque declarado que actualmente tienen asignación. No forman parte de la sobredotación sin asignación, pero pueden revisarse. Abre cada docente para conocer las funciones que componen sus horas.</div>
            </div>
            <span class="badge rounded-pill text-bg-warning">{{ $ajusteItems->count() }} docente(s)</span>
        </div>
        <div class="card-body border-bottom">
            <div class="row g-3">
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-warning-subtle h-100"><div class="small text-muted">Docentes asignadas / requeridas</div><div class="h4 fw-bold text-warning-emphasis mb-0">{{ $fmt($declaradasAsignadas) }} / {{ $fmt($sobredotacionResumen['horas_declaradas_requeridas'] ?? $formula['bloque_declarado'] ?? 0) }}</div><div class="small text-muted">Pendientes de cobertura docente: {{ $fmt($sobredotacionResumen['horas_declaradas_pendientes'] ?? 0) }}</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-primary-subtle h-100"><div class="small text-muted">Horas titulares</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_titulares'] ?? 0) }}</div><div class="small text-muted">Imputadas a contrato Planta</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-info-subtle h-100"><div class="small text-muted">Horas Contrata</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_contrata'] ?? 0) }}</div><div class="small text-muted">Imputadas a contrato Contrata</div></div></div>
                <div class="col-xl-3 col-md-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Sin cobertura contractual</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($sobredotacionResumen['horas_declaradas_sin_cobertura'] ?? 0) }}</div><div class="small text-muted">Declaradas sobre el contrato disponible</div></div></div>
            </div>
            <div class="small text-muted mt-3"><i class="bi bi-info-circle"></i> Para clasificar las horas declaradas se imputan primero las asignaciones protegidas y se conserva primero el contrato Titular/Planta; luego se utiliza Contrata.</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th><span class="visually-hidden">Detalle</span></th><th>Docente</th><th>Función</th><th class="text-end">Contrato Aula</th><th class="text-end">Protegidas</th><th class="text-end">Declaradas asignadas</th><th class="text-end">Titulares</th><th class="text-end">Contrata</th><th class="text-end">Sin cobertura</th><th class="text-end">Sin asignación</th><th class="text-end">Sobreasignadas</th></tr></thead>
                <tbody>
                    @forelse ($ajusteItems as $docente)
                        @php($detalleId = 'detalle-ajuste-docente-'.$loop->index)
                        <tr>
                            <td><button type="button" class="btn btn-sm btn-outline-secondary rounded-circle" data-bs-toggle="collapse" data-bs-target="#{{ $detalleId }}" aria-expanded="false" aria-controls="{{ $detalleId }}" title="Ver desglose de horas"><i class="bi bi-chevron-down"></i><span class="visually-hidden">Ver detalle de {{ $docente['nombre'] }}</span></button></td>
                            <td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['rut'] }} · {{ $docente['tipo_contrato'] }}</div></td>
                            <td>{{ $docente['funcion'] }}</td>
                            <td class="text-end fw-semibold">{{ $fmt($docente['horas_contrato_categoria']) }}</td>
                            <td class="text-end text-success">{{ $fmt($docente['horas_asignadas_protegidas']) }}</td>
                            <td class="text-end text-warning-emphasis fw-bold">{{ $fmt($docente['horas_declaradas_ajustables']) }}</td>
                            <td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_declaradas_titulares']) }}</td>
                            <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_declaradas_contrata']) }}</td>
                            <td class="text-end {{ $docente['horas_declaradas_sin_cobertura'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $fmt($docente['horas_declaradas_sin_cobertura']) }}</td>
                            <td class="text-end text-danger">{{ $fmt($docente['horas_sobredotacion_total']) }}</td>
                            <td class="text-end {{ $docente['horas_sobreasignadas'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $fmt($docente['horas_sobreasignadas']) }}</td>
                        </tr>
                        <tr>
                            <td colspan="11" class="p-0 border-0">
                                <div class="collapse" id="{{ $detalleId }}">
                                    <div class="p-3 bg-light border-top border-bottom">
                                        <div class="fw-semibold mb-2"><i class="bi bi-list-ul"></i> Desglose de horas de posible ajuste</div>
                                        <div class="table-responsive rounded-3 border bg-white">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light"><tr><th>Tipo</th><th>Función o actividad</th><th>Subtipo</th><th>Subvención</th><th class="text-end">Horas</th></tr></thead>
                                                <tbody>
                                                    @forelse (($docente['horas_declaradas_detalle'] ?? []) as $detalle)
                                                        <tr><td><span class="badge text-bg-light border">{{ $detalle['tipo_label'] }}</span></td><td class="fw-semibold">{{ $detalle['nombre'] }}</td><td>{{ $detalle['subtipo_label'] ?: '—' }}</td><td>{{ $detalle['subvencion'] ?: '—' }}</td><td class="text-end fw-bold text-warning-emphasis">{{ $fmt($detalle['horas']) }}</td></tr>
                                                    @empty
                                                        <tr><td colspan="5" class="text-center text-muted py-3">No existe un desglose individual disponible para estas horas declaradas.</td></tr>
                                                    @endforelse
                                                </tbody>
                                                <tfoot class="table-light fw-bold"><tr><td colspan="4">Total docente</td><td class="text-end text-warning-emphasis">{{ $fmt($docente['horas_declaradas_ajustables']) }}</td></tr></tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-5">No existen horas declaradas asignadas susceptibles de ajuste.</td></tr>
                    @endforelse
                </tbody>
                @if ($ajusteItems->isNotEmpty())
                    <tfoot class="table-light fw-bold"><tr><td colspan="5">Total horas declaradas de posible ajuste</td><td class="text-end text-warning-emphasis">{{ $fmt($sobredotacionResumen['horas_declaradas_ajustables'] ?? 0) }}</td><td class="text-end text-primary">{{ $fmt($sobredotacionResumen['horas_declaradas_titulares'] ?? 0) }}</td><td class="text-end text-info">{{ $fmt($sobredotacionResumen['horas_declaradas_contrata'] ?? 0) }}</td><td class="text-end text-danger">{{ $fmt($sobredotacionResumen['horas_declaradas_sin_cobertura'] ?? 0) }}</td><td colspan="2"></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
