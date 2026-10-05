@php
    $docentesConExceso = collect($docentes ?? [])->filter(fn ($persona) =>
        ($persona['horas_contrato'] ?? null) !== null
        && (float) ($persona['horas_asignadas_total'] ?? 0) + (float) ($persona['redondeo_parvularia'] ?? 0) - (float) $persona['horas_contrato'] > 0.01
    );
@endphp
@if ($docentesConExceso->isNotEmpty())
    <section class="card dotacion-section mb-4" aria-labelledby="revision-sobrecargas-titulo" data-revision-group>
        <div class="dotacion-section-header">
            <div class="dotacion-eyebrow">Revisión de asignaciones</div>
            <h2 id="revision-sobrecargas-titulo" class="h5 fw-bold mb-1">Docentes con sobreasignación contractual</h2>
            <p class="small text-muted mb-0">La ocupación supera el contrato considerado del docente. Incluye reservas y redondeo NT. Revise las asignaciones sin compensar este exceso con el saldo de otra persona.</p>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Docente</th><th scope="col" class="text-end">Contrato considerado</th><th scope="col" class="text-end">Contrato ocupado</th><th scope="col" class="text-end">Exceso</th><th scope="col">Acción</th></tr></thead>
                <tbody>
                    @foreach ($docentesConExceso as $persona)
                        @php
                            $ocupacionPersona = round((float) $persona['horas_asignadas_total'] + (float) ($persona['redondeo_parvularia'] ?? 0), 2);
                            $piePersona = ($resumen['establecimiento_especial'] ?? false) ? 0 : (float) ($persona['horas_contrato_pie'] ?? \App\Support\DotacionAsignacionCalculator::contratoPiePorDocente($persona));
                            $bloquesPersona = [];
                            if ((float) $persona['horas_contrato'] - $piePersona > 0.01) $bloquesPersona[] = \App\Support\DotacionProfesionDocenteResolver::perfilTitulo($persona)['es_educacion_parvulos'] ? 'parvularia' : 'plan_estudio';
                            if ($piePersona > 0.01) $bloquesPersona[] = 'pie';
                        @endphp
                        <tr data-revision-row="{{ sha1('sobrecarga'.$persona['rut']) }}" data-revision-search="{{ $persona['nombre'] }} {{ $persona['rut'] }} {{ $persona['titulo'] ?? '' }} {{ $persona['funcion'] ?? '' }}" data-revision-block="{{ implode(' ', $bloquesPersona) }}" data-revision-state="sobrecarga">
                            <td><div class="fw-semibold">{{ $persona['nombre'] }}</div><div class="small text-muted">{{ $persona['rut'] }} · {{ $persona['titulo'] ?? 'Sin título declarado' }}</div></td>
                            <td class="text-end">{{ $fmt($persona['horas_contrato']) }} h</td>
                            <td class="text-end fw-semibold">{{ $fmt($ocupacionPersona) }} h</td>
                            <td class="text-end text-danger fw-bold">{{ $fmt($ocupacionPersona - (float) $persona['horas_contrato']) }} h</td>
                            <td><a class="btn btn-sm btn-outline-primary rounded-pill" data-dotacion-contexto-salida href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'docentes', 'revision_docentes_q' => $persona['rut'], 'revision_docentes_state' => 'sobrecarga']) }}">Revisar contrato<span class="visually-hidden"> de {{ $persona['nombre'] }}</span></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
