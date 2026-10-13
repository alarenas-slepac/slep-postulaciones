@php
    $brechasContrata = [
        'plan_estudio' => (float) ($resumen['brecha_dotacion_general'] ?? 0),
        'parvularia' => (float) ($resumen['brecha_dotacion_parvularia'] ?? 0),
        'pie' => (float) ($resumen['brecha_dotacion_pie'] ?? 0),
    ];
    $bloquesContrata = \App\Services\Dotacion\NoRenovacionService::BLOQUES;
    $mostrarContrata = true;
@endphp
@include('admin.dotacion-establecimiento.partials._personal_select_assets')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <style>
        .js-no-renovacion-select + .select2-container .select2-selection { min-height: 2.65rem; border-color: #dbe4f0; border-radius: .75rem; }
        .dotacion-no-renovacion-dropdown { border-color: #dbe4f0; border-radius: .75rem; box-shadow: 0 .5rem 1.25rem rgba(15, 23, 42, .12); }
    </style>
@endpush
@if ($mostrarContrata)
    <div class="col-12" id="habilitaciones-contrata">
        <section class="card dotacion-section border-0" aria-labelledby="habilitaciones-contrata-titulo">
            <div class="dotacion-section-header">
                <div class="dotacion-eyebrow mb-1">Planificación de contratación</div>
                <h2 class="h5 fw-bold mb-1" id="habilitaciones-contrata-titulo">Docentes por contratar</h2>
                <p class="text-muted small mb-0">Cree cupos para cubrir una brecha o reemplazar un contrato a contrata en cualquiera de los tres bloques. Cada cupo permite asignar hasta 44 horas y conserva el título del docente reemplazado. Los cupos no se suman a los contratos vigentes del padrón.</p>
            </div>
            <div class="card-body">
                @if (session('contrata_success'))
                    <div class="alert alert-success" role="status">{{ session('contrata_success') }}</div>
                @endif
                @if (old('accion') === 'reemplazar' && $errors->any())
                    <div class="alert alert-danger" role="alert"><strong>No fue posible reemplazar el contrato.</strong><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @error('habilitacion')
                    <div class="alert alert-warning" role="alert">{{ $message }}</div>
                @enderror
                @if (! ($contrataHabilitacionesTableReady ?? false))
                    <div class="alert alert-info mb-0">La habilitación estará disponible al aplicar la migración pendiente.</div>
                @else
                    <div class="row g-3">
                        @foreach ($bloquesContrata as $bloque => $titulo)
                            @php
                                $habilitaciones = ($contrataHabilitaciones ?? collect())->get($bloque, collect());
                                $brecha = max(0, $brechasContrata[$bloque]);
                                $habilitadas = round((float) $habilitaciones->sum('horas'), 2);
                                $disponibles = max(0, round($brecha - $habilitadas, 2));
                            @endphp
                            <div class="col-xl-4 col-md-6">
                                <div class="dotacion-breakdown-item p-3 h-100">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                                        <div>
                                            <h3 class="h6 fw-bold mb-1">{{ $titulo }}</h3>
                                            <div class="small text-muted">{{ $fmt($brecha) }} h por contratar · {{ $fmt($habilitadas) }} h habilitadas · {{ $fmt($disponibles) }} h disponibles</div>
                                        </div>
                                        <span class="badge rounded-pill {{ $disponibles > 0 ? 'text-bg-success' : 'dotacion-badge-soft' }}">{{ $habilitaciones->count() }} cupo(s)</span>
                                    </div>
                                    @if ((float) $habilitaciones->filter(fn ($cupo) => empty($cupo->no_renovacion_id))->sum('horas') > $brecha)
                                        <div class="alert alert-warning small py-2">Las horas habilitadas exceden la brecha actual. Revise los cupos tras el cambio de contratos.</div>
                                    @endif
                                    @if ($habilitaciones->isNotEmpty())
                                        <ul class="list-unstyled mb-3">
                                            @foreach ($habilitaciones as $habilitacion)
                                                @php
                                                    $virtual = ($docentesVirtualesPorCupo ?? collect())->get((int) $habilitacion->id, []);
                                                    $horasAsignadasVirtual = (float) ($virtual['horas_asignadas_total'] ?? 0);
                                                    $tieneAsignacionesVirtual = collect($virtual['asignaciones'] ?? [])->isNotEmpty();
                                                @endphp
                                                <li class="d-flex align-items-center justify-content-between gap-2 border-top py-2">
                                                    <span class="small"><strong>Docente por contratar #{{ $habilitacion->id }}</strong><br>{{ $fmt($horasAsignadasVirtual) }} de {{ $fmt($habilitacion->horas) }} h asignadas
                                                        @if (!empty($habilitacion->titulo))<br><span class="text-muted">{{ $habilitacion->titulo }}</span>@endif
                                                        @if (!empty($habilitacion->no_renovacion_id))<br><span class="badge rounded-pill text-bg-light border">Reemplazo de contrata</span>@endif
                                                    </span>
                                                    @if ($canManageContrataHabilitaciones ?? false)
                                                        @if ($tieneAsignacionesVirtual)
                                                            <span class="badge rounded-pill text-bg-info">Con asignaciones</span>
                                                        @else
                                                            <form method="POST" action="{{ route('admin.dotacion-establecimiento.contrata-habilitaciones.destroy', [$establecimiento, $habilitacion->id]) }}" data-confirm="{{ !empty($habilitacion->no_renovacion_id) ? '¿Anular la no renovación? Se retirará el cupo sin asignaciones y el docente volverá a la dotación del año. Las asignaciones anteriores no se reactivan automáticamente.' : '¿Retirar este cupo sin asignaciones?' }}" onsubmit="return confirm(this.dataset.confirm);">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="anio" value="{{ $anio }}">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" aria-label="Retirar cupo {{ $loop->iteration }} de {{ $titulo }}">{{ !empty($habilitacion->no_renovacion_id) ? 'Anular reemplazo' : 'Retirar' }}</button>
                                                            </form>
                                                        @endif
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="small text-muted mb-3">Aún no hay docentes por contratar en este bloque.</p>
                                    @endif
                                    @if ($habilitaciones->isNotEmpty())
                                        <a class="btn btn-sm btn-outline-primary rounded-pill mb-3" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'asignacion']) }}">Ir a Asignación de horas</a>
                                    @endif
                                    @if (($canManageContrataHabilitaciones ?? false) && $anio >= 2027)
                                        @php($candidatos = ($candidatosNoRenovacion ?? collect())->get($bloque, collect()))
                                        <div class="border-top pt-3 mb-3">
                                            <h4 class="h6 fw-bold">Reemplazar contrato a contrata</h4>
                                            <p class="small text-muted">Disponible aunque no haya brecha de contratación. Reemplaza el contrato completo por cupos sin asignaciones ni asociaciones a asignaturas; retira al docente sólo de este establecimiento y año. Si tenía horas en distintos bloques, los cupos conservan esa distribución sin aumentar el total. Las asignaciones anteriores quedan inactivas y se conservan en el historial.</p>
                                            @if (!($noRenovacionesDisponible ?? false))
                                                <p class="small text-muted mb-0">Aplique la migración de no renovaciones para habilitar esta acción.</p>
                                            @elseif ($candidatos->isEmpty())
                                                <p class="small text-muted mb-0">No hay docentes íntegramente a contrata sin contrato protegido para reemplazar en este bloque.</p>
                                            @else
                                                <form method="POST" action="{{ route('admin.dotacion-establecimiento.contrata-habilitaciones.store', $establecimiento) }}" class="js-no-renovacion-form" data-confirm="¿Registrar la no renovación y reemplazar el contrato completo por Docente por contratar? El docente saldrá de esta dotación, sus asignaciones quedarán inactivas y el cupo no tendrá asignaciones ni asociaciones a asignaturas." onsubmit="return confirm(this.dataset.confirm);">
                                                    @csrf
                                                    <input type="hidden" name="accion" value="reemplazar">
                                                    <input type="hidden" name="anio" value="{{ $anio }}">
                                                    <input type="hidden" name="bloque" value="{{ $bloque }}">
                                                    <label id="no-renovacion-label-{{ $bloque }}" class="form-label small fw-semibold" for="no-renovacion-docente-{{ $bloque }}">Docente a contrata <span class="text-danger">*</span></label>
                                                    <select id="no-renovacion-docente-{{ $bloque }}" class="form-select js-no-renovacion-select" name="docente_rut" required aria-describedby="no-renovacion-detalle-{{ $bloque }}">
                                                        <option value="">Buscar por nombre, RUT o título...</option>
                                                        @foreach ($candidatos as $docente)
                                                            <option value="{{ $docente['rut'] }}" data-nombre="{{ $docente['nombre'] }}" data-titulo="{{ $docente['titulo'] }}" data-horas="{{ $fmt($docente['horas_contrata']) }}" @selected(old('accion') === 'reemplazar' && old('bloque') === $bloque && old('docente_rut') === $docente['rut'])>{{ $docente['nombre'] }} · {{ $docente['rut'] }} · {{ $docente['titulo'] }} · {{ $fmt($docente['horas_contrata']) }} h contrata</option>
                                                        @endforeach
                                                    </select>
                                                    <p id="no-renovacion-detalle-{{ $bloque }}" class="form-text js-no-renovacion-detalle">Seleccione un docente para revisar el título y las horas que se copiarán.</p>
                                                    <label class="form-label small fw-semibold" for="no-renovacion-motivo-{{ $bloque }}">Justificación de no renovación <span class="text-danger">*</span></label>
                                                    <textarea id="no-renovacion-motivo-{{ $bloque }}" name="justificacion" class="form-control" rows="3" minlength="10" maxlength="3000" required>{{ old('accion') === 'reemplazar' && old('bloque') === $bloque ? old('justificacion') : '' }}</textarea>
                                                    @if (old('accion') === 'reemplazar' && old('bloque') === $bloque && $errors->any())
                                                        <div class="alert alert-danger small mt-2" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                                                    @endif
                                                    <button type="submit" class="btn btn-primary rounded-pill w-100 mt-3"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Reemplazar por Docente por contratar</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                    @if (($canManageContrataHabilitaciones ?? false) && $disponibles >= 0.01)
                                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.contrata-habilitaciones.store', $establecimiento) }}" class="border-top pt-3">
                                            @csrf
                                            <input type="hidden" name="anio" value="{{ $anio }}">
                                            <input type="hidden" name="bloque" value="{{ $bloque }}">
                                            @if ($errors->any() && old('bloque') === $bloque)
                                                <div class="alert alert-danger small py-2" role="alert">Revise la cantidad y las horas indicadas para {{ $titulo }}.</div>
                                            @endif
                                            <div class="row g-2 align-items-end">
                                                <div class="col-sm-4">
                                                    <label for="contrata-cantidad-{{ $bloque }}" class="form-label small fw-semibold">Docentes</label>
                                                    <input id="contrata-cantidad-{{ $bloque }}" name="cantidad" type="number" class="form-control {{ old('bloque') === $bloque && $errors->has('cantidad') ? 'is-invalid' : '' }}" min="1" max="100" value="{{ old('bloque') === $bloque ? old('cantidad', 1) : 1 }}" required>
                                                    @if (old('bloque') === $bloque)
                                                        @error('cantidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    @endif
                                                </div>
                                                <div class="col-sm-4">
                                                    <label for="contrata-horas-{{ $bloque }}" class="form-label small fw-semibold">Horas por funcionario</label>
                                                    <input id="contrata-horas-{{ $bloque }}" name="horas" type="number" class="form-control {{ old('bloque') === $bloque && $errors->has('horas') ? 'is-invalid' : '' }}" min="0.01" max="{{ min(44, $disponibles) }}" step="0.01" value="{{ old('bloque') === $bloque ? old('horas', min(44, $disponibles)) : min(44, $disponibles) }}" required>
                                                    @if (old('bloque') === $bloque)
                                                        @error('horas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    @endif
                                                </div>
                                                <div class="col-sm-4">
                                                    <button type="submit" class="btn btn-primary rounded-pill w-100"><i class="bi bi-person-plus" aria-hidden="true"></i> Habilitar</button>
                                                </div>
                                            </div>
                                            <div class="form-text">Para cubrir todo el saldo se requieren al menos {{ (int) ceil($disponibles / 44) }} docente(s). La cantidad multiplicada por las horas no puede superar las {{ $fmt($disponibles) }} h disponibles.</div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if (($noRenovaciones ?? collect())->isNotEmpty())
                        <section class="border-top pt-3 mt-3" aria-labelledby="no-renovaciones-historial">
                            <h3 id="no-renovaciones-historial" class="h6 fw-bold">Historial de no renovaciones y reemplazos</h3>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light"><tr><th scope="col">Docente retirado</th><th scope="col">Bloque / horas</th><th scope="col">Justificación de no renovación</th><th scope="col">Estado</th></tr></thead>
                                    <tbody>
                                        @foreach ($noRenovaciones as $registro)
                                            <tr><td><strong>{{ $registro->docente_nombre }}</strong><div class="small text-muted">{{ $registro->docente_rut_normalizado }} · {{ $registro->titulo }}</div></td>
                                                <td>{{ $bloquesContrata[$registro->bloque] ?? $registro->bloque }}<div>{{ $fmt($registro->horas_contrata) }} h a contrata</div></td>
                                                <td class="text-break">{{ $registro->justificacion }}<div class="small text-muted">{{ $registro->created_at?->format('d/m/Y H:i') }}</div></td>
                                                <td><span class="badge rounded-pill {{ $registro->anulada_at ? 'text-bg-secondary' : 'text-bg-success' }}">{{ $registro->anulada_at ? 'Anulado' : 'Reemplazado' }}</span></td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif
                @endif
            </div>
        </section>
    </div>
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-no-renovacion-select').forEach(function (select) {
                const detalle = select.closest('form').querySelector('.js-no-renovacion-detalle');
                const actualizar = function () {
                    const opcion = select.selectedOptions[0];
                    detalle.textContent = opcion?.value ? 'Contrato a reemplazar: ' + opcion.dataset.horas + ' h. Título a copiar: ' + opcion.dataset.titulo + '.' : 'Seleccione un docente para revisar el título y las horas que se copiarán.';
                };
                select.addEventListener('change', actualizar);
                actualizar();
                if (window.jQuery?.fn?.select2) {
                    const $ = window.jQuery;
                    $(select).select2({
                        theme: 'bootstrap-5', width: '100%', placeholder: 'Buscar por nombre, RUT o título...', allowClear: true,
                        dropdownParent: $(document.body), dropdownCssClass: 'dotacion-no-renovacion-dropdown',
                        templateResult: function (item) {
                            if (!item.id || !item.element) return item.text;
                            const option = item.element;
                            return $('<div>', {class: 'py-1'}).append(
                                $('<div>', {class: 'fw-semibold'}).text(option.dataset.nombre + ' · ' + option.value),
                                $('<div>', {class: 'small text-muted'}).text(option.dataset.titulo),
                                $('<div>', {class: 'small fw-semibold text-primary'}).text('Contrato a reemplazar: ' + option.dataset.horas + ' h a contrata')
                            );
                        },
                        templateSelection: function (item) {
                            return item.id && item.element ? item.element.dataset.nombre + ' · ' + item.id : item.text;
                        },
                        language: {noResults: function () { return 'No se encontraron docentes a contrata.'; }}
                    }).on('change', actualizar);
                    $(select).next('.select2-container').find('.select2-selection').attr({'aria-labelledby': select.labels[0]?.id, 'aria-describedby': select.getAttribute('aria-describedby')});
                }
            });
        });
    </script>
@endpush
