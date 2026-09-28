@php
    $brechasContrata = [
        'parvularia' => (float) ($resumen['brecha_dotacion_parvularia'] ?? 0),
        'pie' => (float) ($resumen['brecha_dotacion_pie'] ?? 0),
    ];
    $bloquesContrata = ['parvularia' => 'Parvularia', 'pie' => 'PIE'];
    $mostrarContrata = collect($brechasContrata)->contains(fn ($brecha) => $brecha > 0.01)
        || ($contrataHabilitaciones ?? collect())->flatten(1)->isNotEmpty();
@endphp
@if ($mostrarContrata)
    <div class="col-12" id="habilitaciones-contrata">
        <section class="card dotacion-section border-0" aria-labelledby="habilitaciones-contrata-titulo">
            <div class="dotacion-section-header">
                <div class="dotacion-eyebrow mb-1">Planificación de contratación</div>
                <h2 class="h5 fw-bold mb-1" id="habilitaciones-contrata-titulo">Funcionarios a contrata habilitados</h2>
                <p class="text-muted small mb-0">Los cupos se calculan por bloque y año. Cada funcionario puede tener hasta 44 horas. Las habilitaciones no se suman a los contratos vigentes.</p>
            </div>
            <div class="card-body">
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
                            <div class="col-lg-6">
                                <div class="dotacion-breakdown-item p-3 h-100">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                                        <div>
                                            <h3 class="h6 fw-bold mb-1">{{ $titulo }}</h3>
                                            <div class="small text-muted">{{ $fmt($brecha) }} h por contratar · {{ $fmt($habilitadas) }} h habilitadas · {{ $fmt($disponibles) }} h disponibles</div>
                                        </div>
                                        <span class="badge rounded-pill {{ $disponibles > 0 ? 'text-bg-success' : 'dotacion-badge-soft' }}">{{ $habilitaciones->count() }} funcionario(s)</span>
                                    </div>
                                    @if ($habilitadas > $brecha)
                                        <div class="alert alert-warning small py-2">Las horas habilitadas exceden la brecha actual. Revise los cupos tras el cambio de contratos.</div>
                                    @endif
                                    @if ($habilitaciones->isNotEmpty())
                                        <ul class="list-unstyled mb-3">
                                            @foreach ($habilitaciones as $habilitacion)
                                                <li class="d-flex align-items-center justify-content-between gap-2 border-top py-2">
                                                    <span class="small">Funcionario {{ $loop->iteration }} · <strong>{{ $fmt($habilitacion->horas) }} h</strong></span>
                                                    @if ($canManageContrataHabilitaciones ?? false)
                                                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.contrata-habilitaciones.destroy', [$establecimiento, $habilitacion->id]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="anio" value="{{ $anio }}">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" aria-label="Retirar cupo {{ $loop->iteration }} de {{ $titulo }}">Retirar</button>
                                                        </form>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="small text-muted mb-3">Aún no hay funcionarios habilitados en este bloque.</p>
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
                                                    <label for="contrata-cantidad-{{ $bloque }}" class="form-label small fw-semibold">Funcionarios</label>
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
                                            <div class="form-text">Para cubrir todo el saldo se requieren al menos {{ (int) ceil($disponibles / 44) }} funcionario(s). La cantidad multiplicada por las horas no puede superar las {{ $fmt($disponibles) }} h disponibles.</div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
@endif
