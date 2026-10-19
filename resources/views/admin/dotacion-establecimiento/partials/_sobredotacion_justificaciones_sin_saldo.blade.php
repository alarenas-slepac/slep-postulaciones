@php
    $justificacionesSinSaldo = collect($justificacionesSobredotacion ?? [])->filter(function ($registro) use ($vacantesPorBloque) {
        if (!$registro->exists || !$registro->getKey()) return false;
        $campo = $registro->tipo_horas === 'titular' ? 'horas_sobredotacion_planta' : 'horas_sobredotacion_contrata';
        return !collect($vacantesPorBloque[$registro->bloque]['items'] ?? [])->contains(fn ($docente) =>
            \App\Support\DotacionEstablecimientoCalculator::normalizeRut($docente['rut']) === \App\Support\DotacionEstablecimientoCalculator::normalizeRut($registro->docente_rut_normalizado)
            && (float) ($docente[$campo] ?? 0) > 0.01
        );
    });
    $nombresJustificacion = collect($docentes ?? [])->keyBy(fn ($docente) => \App\Support\DotacionEstablecimientoCalculator::normalizeRut($docente['rut']));
@endphp
@if ($justificacionesSinSaldo->isNotEmpty())
    <section class="card dotacion-section mb-4" aria-labelledby="justificaciones-sin-saldo-titulo" data-revision-group>
        <div class="dotacion-section-header">
            <h2 id="justificaciones-sin-saldo-titulo" class="h5 fw-bold mb-1">Justificaciones fuera de la nómina de saldos</h2>
            <p class="small text-muted mb-0">Revise los registros cuyo bloque o calidad ya no tiene saldo visible. Una justificación puede seguir excluyendo al docente de otras funciones hasta que se elimine.</p>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Docente</th><th scope="col">Bloque y calidad</th><th scope="col">Justificación registrada</th><th scope="col">Acción</th></tr></thead>
                <tbody>
                    @foreach ($justificacionesSinSaldo as $registro)
                        @php
                            $rutRegistro = \App\Support\DotacionEstablecimientoCalculator::normalizeRut($registro->docente_rut_normalizado);
                            $nombreRegistro = data_get($nombresJustificacion->get($rutRegistro), 'nombre', $registro->docente_rut_normalizado);
                            $bloqueRegistro = ['plan_estudio' => 'Plan de estudio', 'parvularia' => 'Educación Parvularia', 'pie' => 'PIE'][$registro->bloque] ?? $registro->bloque;
                        @endphp
                        <tr data-revision-row="justificacion-{{ $registro->getKey() }}" data-revision-search="{{ $nombreRegistro }} {{ $rutRegistro }} {{ $bloqueRegistro }}" data-revision-block="{{ $registro->bloque }}" data-revision-state="justificacion">
                            <th scope="row" class="fw-normal"><div class="fw-semibold">{{ $nombreRegistro }}</div><div class="small text-muted">{{ $registro->docente_rut_normalizado }}</div></th>
                            <td>{{ $bloqueRegistro }} · {{ $registro->tipo_horas === 'titular' ? 'Titular' : 'Contrata' }}<div class="small text-muted">Registrada para {{ $fmt($registro->horas_detectadas) }} h</div></td>
                            <td class="text-break">{{ $registro->justificacion }}</td>
                            <td>@include('admin.dotacion-establecimiento.partials._sobredotacion_eliminar_justificacion')</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
