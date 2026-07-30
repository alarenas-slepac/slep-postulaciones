@php
    $exceptionReady = (bool) ($proporcionExcepcionTableReady ?? false);
    $exception = $proporcionExcepcion ?? null;
    $exceptionActive = (bool) ($exception?->activa ?? false);
    $canManage = (bool) ($canManageProporcionExcepcion ?? false);
@endphp

@if (!$exceptionReady)
    @if ($canManage)
        <div class="alert alert-warning rounded-4 shadow-sm mb-4">
            <strong><i class="bi bi-database-exclamation"></i> Configuración 60/40 pendiente.</strong>
            Ejecute las migraciones del parche para habilitar excepciones por establecimiento y año.
        </div>
    @endif
@elseif ($exceptionActive)
    <div class="alert alert-info border-0 rounded-4 shadow-sm mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start">
            <div>
                <div class="fw-bold fs-5"><i class="bi bi-percent"></i> Proporción especial 60/40 activa</div>
                <div class="mt-1">Se aplica a <strong>todos los niveles y asignaturas curriculares</strong> del establecimiento durante {{ $anio }}.</div>
                <div class="small mt-2"><strong>Justificación:</strong> {{ $exception->justificacion }}</div>
                <div class="small text-muted mt-1">
                    Última recalculación:
                    {{ $exception->ultima_recalculacion_at?->format('d-m-Y H:i') ?? 'Sin registro' }}
                    · Asignaciones revisadas: {{ number_format((int) ($exception->ultima_recalculacion_total ?? 0), 0, ',', '.') }}
                </div>
            </div>
            @if ($canManage)
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.proporcion-excepcion.recalculate', $establecimiento) }}">
                        @csrf
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <button class="btn btn-outline-primary rounded-pill" type="submit">
                            <i class="bi bi-arrow-repeat"></i> Recalcular asignaciones
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.proporcion-excepcion.destroy', $establecimiento) }}" onsubmit="return confirm('¿Desactivar la excepción 60/40 y recalcular con la regla ordinaria?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="anio" value="{{ $anio }}">
                        <button class="btn btn-outline-danger rounded-pill" type="submit">
                            <i class="bi bi-x-circle"></i> Desactivar
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@elseif ($canManage)
    <div class="card dotacion-section mb-4">
        <div class="dotacion-section-header">
            <div class="d-flex gap-3 align-items-start">
                <span class="dotacion-icon" style="width:38px;height:38px;"><i class="bi bi-percent"></i></span>
                <div>
                    <div class="dotacion-eyebrow">Configuración excepcional</div>
                    <h2 class="h5 fw-bold mb-1">Habilitar proporción 60/40 para todos los niveles</h2>
                    <div class="text-muted small">La excepción solo afecta a este establecimiento y al año seleccionado. Las funciones no lectivas continúan como horas contrato directas.</div>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="{{ route('admin.dotacion-establecimiento.proporcion-excepcion.store', $establecimiento) }}" class="row g-3">
                @csrf
                <input type="hidden" name="anio" value="{{ $anio }}">
                <div class="col-12">
                    <label class="form-label fw-semibold" for="justificacion_60_40">Justificación institucional</label>
                    <textarea class="form-control @error('justificacion') is-invalid @enderror" id="justificacion_60_40" name="justificacion" rows="3" required maxlength="3000" placeholder="Ej.: Aplicación excepcional por condición de Liceo Bicentenario y resolución institucional correspondiente.">{{ old('justificacion', $exception?->justificacion) }}</textarea>
                    @error('justificacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary rounded-pill px-4" onclick="return confirm('¿Habilitar 60/40 para todos los niveles y recalcular las asignaciones existentes?');">
                        <i class="bi bi-check-circle"></i> Habilitar 60/40 para {{ $anio }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
