@php
    $proceso = $proceso2027 ?? ['aplica' => false];
    $configProceso = $proceso['configuracion'] ?? null;
    $fmtProceso = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
@endphp

@if ($proceso['aplica'] ?? false)
    <div class="card dotacion-section mb-4 border-primary">
        <div class="dotacion-section-header d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="dotacion-eyebrow">Proceso guiado 2027</div>
                <h2 class="h5 fw-bold mb-1">Avance de dotación por establecimiento</h2>
                <div class="small text-muted">Complete las etapas en orden. Las funciones no normativas se habilitan al finalizar la cobertura obligatoria.</div>
            </div>
            <span class="badge rounded-pill text-bg-primary">Año 2027</span>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                @foreach (($proceso['pasos'] ?? []) as $paso)
                    <div class="col-lg-3 col-md-6">
                        <div class="border rounded-4 p-3 h-100 {{ $paso['completo'] ? 'border-success bg-success-subtle' : 'border-warning bg-warning-subtle' }}">
                            <div class="small text-muted">{{ $loop->iteration }}. Etapa</div>
                            <div class="fw-semibold">{{ $paso['label'] }}</div>
                            <span class="badge rounded-pill {{ $paso['completo'] ? 'text-bg-success' : 'text-bg-warning' }} mt-2">{{ $paso['completo'] ? 'Completada' : 'Pendiente' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-lg-5">
                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.proceso-2027.update', $establecimiento) }}" class="border rounded-4 p-3 h-100">
                        @csrf
                        <input type="hidden" name="anio" value="2027">
                        <label class="form-label fw-semibold">Decisión sobre combinación de cursos</label>
                        <select name="decision_combinacion" class="form-select mb-2" required>
                            <option value="">Seleccione una decisión...</option>
                            @foreach (\App\Models\DotacionProceso2027Configuracion::COMBINACIONES as $key => $label)
                                <option value="{{ $key }}" @selected(old('decision_combinacion', $configProceso?->decision_combinacion) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <textarea name="observacion_combinacion" class="form-control mb-2" rows="2" maxlength="2000" placeholder="Fundamento o antecedente">{{ old('observacion_combinacion', $configProceso?->observacion_combinacion) }}</textarea>
                        <button class="btn btn-outline-primary btn-sm rounded-pill" type="submit"><i class="bi bi-check2-circle"></i> Guardar decisión</button>
                    </form>
                </div>
                <div class="col-lg-7">
                    @if ($canManageProceso2027Maximos ?? false)
                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.proceso-2027.update', $establecimiento) }}" class="border rounded-4 p-3 h-100">
                            @csrf
                            <input type="hidden" name="anio" value="2027">
                            <div class="fw-semibold mb-2">Máximos autorizados para asignar</div>
                            <div class="row g-2">
                                @foreach (($proceso['bloques'] ?? []) as $key => $bloque)
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Bloque {{ substr($key, -1) }}</label>
                                        <input type="number" name="max_horas_{{ $key }}" min="0" step="0.25" class="form-control" value="{{ old('max_horas_'.$key, $bloque['maximo']) }}" required>
                                        <div class="form-text">Req.: {{ $fmtProceso($bloque['requeridas']) }} h</div>
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn btn-primary btn-sm rounded-pill mt-3" type="submit"><i class="bi bi-save"></i> Guardar máximos</button>
                        </form>
                    @else
                        <div class="border rounded-4 p-3 h-100 bg-light">
                            <div class="fw-semibold">Máximos por bloque</div>
                            <div class="small text-muted">La configuración de máximos corresponde a Administración, UATP o Supervisión de Planificación.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Bloque</th><th class="text-end">Máximo</th><th class="text-end">Titulares</th><th class="text-end">Contrata</th><th class="text-end">Total asignado</th><th class="text-end">Obligatorio asignado</th><th class="text-end">Pendiente obligatorio</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody>
                        @foreach (($proceso['bloques'] ?? []) as $bloque)
                            <tr>
                                <td class="fw-semibold">{{ $bloque['label'] }}</td>
                                <td class="text-end">{{ $bloque['maximo'] === null ? 'Pendiente' : $fmtProceso($bloque['maximo']) }}</td>
                                <td class="text-end text-success">{{ $fmtProceso($bloque['titulares_asignadas']) }}</td>
                                <td class="text-end text-primary">{{ $fmtProceso($bloque['contrata_asignadas']) }}</td>
                                <td class="text-end fw-semibold">{{ $fmtProceso($bloque['asignadas']) }}</td>
                                <td class="text-end">{{ $fmtProceso($bloque['asignadas_obligatorias']) }}</td>
                                <td class="text-end {{ $bloque['pendientes'] > 0.01 ? 'text-warning fw-semibold' : 'text-success' }}">{{ $fmtProceso($bloque['pendientes']) }}</td>
                                <td class="text-end {{ $bloque['maximo_insuficiente'] ? 'text-danger fw-semibold' : '' }}">{{ $bloque['saldo_maximo'] === null ? '—' : $fmtProceso($bloque['saldo_maximo']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (!($proceso['funciones_no_normativas_habilitadas'] ?? false))
                <div class="alert alert-secondary small mt-3 mb-0"><i class="bi bi-lock"></i> Las funciones no normativas aún están bloqueadas. Deben completarse las necesidades obligatorias y mantenerse saldo disponible en el bloque 1.</div>
            @else
                <div class="alert alert-success small mt-3 mb-0"><i class="bi bi-unlock"></i> Funciones no normativas habilitadas: {{ $fmtProceso($proceso['capacidad_no_normativas'] ?? 0) }} horas disponibles.</div>
            @endif
        </div>
    </div>
@endif
