@php
    $docentesReserva = \App\Support\DotacionReservaNoNormativa::elegibles($proceso2027Asignacion);
    $faseReserva = \App\Support\DotacionReservaNoNormativa::fase($docentesReserva);
    $opcionesReserva = \App\Support\DotacionReservaNoNormativa::opciones($docentesReserva);
    $capacidadReserva = (float) ($proceso2027Asignacion['capacidad_reserva_no_normativa'] ?? 0);
    $reservasActivas = collect($asignaciones)->where('tipo_asignacion', 'reserva_no_normativa')->values();
    $funcionesVinculables = collect($necesidades['funciones'] ?? [])
        ->filter(fn (array $item) => (int) ($item['dotacion_funcion_id'] ?? 0) > 0
            && (float) ($item['horas_contrato_pendientes'] ?? 0) > 0.01)
        ->values();
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header d-flex align-items-start gap-3">
        <span class="dotacion-icon" style="width:38px;height:38px;background:#0d6efd;"><i class="bi bi-arrow-left-right"></i></span>
        <div>
            <div class="dotacion-eyebrow">Antes de asignar el plan de estudios</div>
            <h2 class="h5 fw-bold mb-1">Traspaso de horas a otras funciones</h2>
            <div class="text-muted small">Reserve horas de contrato por docente antes de crear la función. Estas horas dejan de estar disponibles para plan y PIE; al crear la función podrá vincular la reserva sin duplicarlas.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Saldo no normativo por traspasar</div><strong class="h4 mb-0">{{ $fmt($capacidadReserva) }} h</strong></div></div>
            <div class="col-md-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas reservadas sin función</div><strong class="h4 mb-0">{{ $fmt($reservasActivas->sum(fn ($row) => (float) $row->horas_contrato)) }} h</strong></div></div>
            <div class="col-md-4"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Orden de traspaso</div><strong>{{ $faseReserva === 'titular' ? 'Horas titulares' : 'Horas a contrata' }}</strong><div class="small text-muted">Los saldos individuales menores a 1 h se omiten.</div></div></div>
        </div>

        <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.reservas.store', $establecimiento) }}" class="border rounded-4 p-3 bg-light">
            @csrf
            <input type="hidden" name="anio" value="2027">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <label class="form-label fw-semibold" for="docente-reserva-no-normativa">Docente con saldo {{ $faseReserva }}</label>
                    <select id="docente-reserva-no-normativa" name="docente_rut" class="form-select js-dotacion-docente-select" data-placeholder="Buscar por nombre, RUT o título..." required>
                        <option value="">Seleccione docente...</option>
                        @foreach ($opcionesReserva as $docente)
                            @php $maximoDocente = \App\Support\DotacionReservaNoNormativa::maximoParaDocente($proceso2027Asignacion, $docente, $faseReserva); @endphp
                            @continue($maximoDocente < 1)
                            <option value="{{ $docente['rut'] }}" data-nombre="{{ $docente['nombre'] }}" data-rut="{{ $docente['rut'] }}" data-titulo="{{ $docente['titulo'] ?? 'Sin título declarado' }}" data-prioridad-label="{{ $docente['prioridad_2027_label'] ?? '' }}" data-antiguedad="{{ $docente['fecha_antiguedad'] ?? '' }}" data-titular-disponible="{{ $fmt($docente['horas_titulares_disponibles'] ?? 0) }}" data-contrata-disponible="{{ $fmt($docente['horas_contrata_disponibles'] ?? 0) }}">{{ $docente['nombre'] }} · {{ $docente['rut'] }} · Título: {{ $docente['titulo'] ?? 'Sin título declarado' }} · {{ $docente['prioridad_2027_label'] ?? '' }} · Máximo a traspasar: {{ $fmt($maximoDocente) }} h</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold" for="horas-reserva-no-normativa">Horas contrato</label>
                    <input id="horas-reserva-no-normativa" type="number" name="horas_contrato" class="form-control" min="1" max="{{ $capacidadReserva }}" step="0.01" required>
                </div>
                <div class="col-lg-3">
                    <button class="btn btn-primary rounded-pill w-100" type="submit" @disabled(!$asignacion2027Habilitada || $capacidadReserva < 1 || $opcionesReserva->isEmpty())><i class="bi bi-arrow-left-right"></i> Traspasar horas</button>
                </div>
            </div>
            <div class="form-text">El total traspasado no puede superar el saldo no normativo ni el saldo contractual del docente y su bloque. Primero se usan horas titulares; luego, horas a contrata.</div>
        </form>

        @if ($reservasActivas->isNotEmpty())
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Docente</th><th>Origen</th><th class="text-end">Horas reservadas</th><th>Vincular a función creada</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($reservasActivas as $reserva)
                            <tr>
                                <td class="fw-semibold">{{ $reserva->docente_nombre }}</td>
                                <td>{{ $reserva->subtipo_asignacion === 'titular' ? 'Titular' : 'Contrata' }}</td>
                                <td class="text-end">{{ $fmt($reserva->horas_contrato) }}</td>
                                <td>
                                    @if (($proceso2027Asignacion['funciones_no_normativas_habilitadas'] ?? false) && $funcionesVinculables->isNotEmpty())
                                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.reservas.vincular', [$establecimiento, $reserva]) }}" class="d-flex gap-2 flex-wrap align-items-end">
                                            @csrf
                                            <div class="flex-grow-1">
                                                <label class="form-label small mb-1" for="funcion-reserva-{{ $reserva->id }}">Función</label>
                                                <select id="funcion-reserva-{{ $reserva->id }}" name="necesidad_key" class="form-select form-select-sm" required>
                                                    <option value="">Seleccione función...</option>
                                                    @foreach ($funcionesVinculables as $funcion)
                                                        <option value="{{ $funcion['key'] }}">{{ $funcion['titulo'] }} · {{ $fmt($funcion['horas_contrato_pendientes']) }} h pendientes</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1" for="horas-vincular-{{ $reserva->id }}">Horas</label>
                                                <input id="horas-vincular-{{ $reserva->id }}" type="number" name="horas_contrato" class="form-control form-control-sm" min="0.01" max="{{ $reserva->horas_contrato }}" step="0.01" value="{{ $reserva->horas_contrato }}" required style="width:6.5rem">
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit">Vincular</button>
                                        </form>
                                    @else
                                        <span class="small text-muted">Disponible al crear funciones y completar la cobertura obligatoria.</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.dotacion-establecimiento.asignaciones.destroy', [$establecimiento, $reserva]) }}" onsubmit="return confirm('¿Liberar las horas reservadas de este docente?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">Liberar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
