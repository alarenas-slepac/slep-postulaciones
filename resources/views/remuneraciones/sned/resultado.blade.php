<section id="calculo-rbd" class="slep-card p-4 mb-4" aria-labelledby="detalle-sned">
    <h2 id="detalle-sned" class="h5 mb-1">{{ $resultado['establecimiento'] }}</h2>
    <p class="small text-muted mb-3">RBD {{ $resultado['rbd'] }} · {{ $metodos[$cuota->metodo] }}</p>
    @if ($resultado['errores'])
        <div class="alert alert-warning">
            <ul class="mb-0">@foreach ($resultado['errores'] as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="row g-3 mb-4">
        @foreach (['docentes' => '90% docente', 'incentivo' => '10% incentivo docente', 'aaee' => 'AAEE'] as $clave => $label)
            <div class="col-md-4">
                <div class="border rounded-4 bg-light-subtle p-3">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="fw-bold fs-5">{{ $pesos($resultado['pagos'][$clave]) }}</div>
                    <div class="small text-muted">Fondo disponible {{ $pesos($resultado['fondos'][$clave]) }}</div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <caption class="caption-top pt-0">Personas con vigencia en {{ mb_strtolower($nombresMes[max($cuota->meses())]) }}</caption>
            <thead class="table-light">
                <tr><th scope="col">Funcionario/a</th><th scope="col">Estamento</th><th scope="col" class="text-end">Horas de corte</th><th scope="col" class="text-end">Reparto general</th><th scope="col" class="text-end">Incentivo</th><th scope="col" class="text-end">Total</th></tr>
            </thead>
            <tbody>
                @forelse ($resultado['personas'] as $persona)
                    <tr>
                        <th scope="row" class="fw-normal">
                            <div class="fw-semibold">{{ $persona['nombre'] }}</div>
                            <div class="small text-muted">{{ $persona['rut'] }}</div>
                            <details class="small mt-1">
                                <summary class="text-primary">Ver tramos</summary>
                                <ul class="ps-3 mb-0 pt-1">
                                    @foreach ($persona['tramos'] as $tramo)
                                        <li>
                                            {{ $tramo['desde'] }} a {{ $tramo['hasta'] }} · {{ $tramo['minutos'] / 60 }} h · {{ $tramo['dias'] }} días
                                            @if ($cuota->metodo === 'jornada_dias')
                                                ({{ number_format($tramo['dias'] * 30 / $tramo['dias_mes'], 2, ',', '.') }} equivalentes)
                                            @endif
                                            <small class="text-muted">· Línea {{ $tramo['linea'] }} · Fila {{ $tramo['fila'] }}</small>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        </th>
                        <td>{{ $persona['estamento'] === 'aaee' ? 'AAEE' : 'Docente' }}</td>
                        <td class="text-end">{{ number_format($persona['minutos_corte'] / 60, 2, ',', '.') }}</td>
                        <td class="text-end">{{ $pesos($persona['general']) }}</td>
                        <td class="text-end">{{ $pesos($persona['incentivo']) }}</td>
                        <td class="text-end fw-semibold">{{ $pesos($persona['general'] + $persona['incentivo']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">Sin personas elegibles en el mes de corte.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light"><tr><th colspan="5" scope="row" class="text-end">Total del establecimiento</th><td class="text-end fw-bold">{{ $pesos($resultado['total']) }}</td></tr></tfoot>
        </table>
    </div>
    @if ($resultado['fuera_del_corte'])
        <details class="mt-2">
            <summary class="fw-semibold">Personas con contrato solo en meses anteriores al corte</summary>
            <ul class="small pt-2 mb-0">
                @foreach ($resultado['fuera_del_corte'] as $personas)
                    @foreach ($personas as $persona)
                        <li>{{ $persona['nombre'] }} · {{ $persona['rut'] }}</li>
                    @endforeach
                @endforeach
            </ul>
        </details>
    @endif
</section>

@php
    $docentesElegibles = array_filter($resultado['personas'], fn ($persona) => $persona['estamento'] === 'docentes');
@endphp
@if ($aporte->fondoIncentivo() && $docentesElegibles)
    <section class="slep-card p-4 mb-4" aria-labelledby="incentivo-sned">
        <h2 id="incentivo-sned" class="h5 mb-1">Incentivo docente 10%</h2>
        <p class="text-muted mb-3">{{ $aporte->establecimiento }} · Fondo {{ $pesos($aporte->fondoIncentivo()) }}</p>
        @if ($cerrada)
            <p class="mb-1">{{ $aporte->incentivo['referencia'] ?? '' }}</p>
            <p class="small text-muted mb-0">
                {{ ($aporte->incentivo['modo'] ?? '') === 'montos' ? 'Montos individuales' : 'Partes iguales' }}
                @if (! empty($aporte->incentivo['registrado_at'])) · Registrado el {{ $aporte->incentivo['registrado_at'] }}@endif
            </p>
        @else
            <form method="POST" action="{{ route('sned.incentivo', [$cuota, $aporte]) }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label for="alcance" class="form-label">Docentes incluidos</label>
                    <select id="alcance" name="alcance" class="form-select" aria-describedby="ayuda-alcance">
                        <option value="seleccion" @selected(old('alcance', $aporte->incentivo['alcance'] ?? 'seleccion') === 'seleccion')>Una o varias personas seleccionadas</option>
                        <option value="todos" @selected(old('alcance', $aporte->incentivo['alcance'] ?? 'seleccion') === 'todos')>Todos los docentes elegibles</option>
                    </select>
                    <p id="ayuda-alcance" class="form-text mb-0">Para un reparto parcial, marque las personas indicadas por el establecimiento.</p>
                </div>
                <div class="col-md-6">
                    <label for="modo-incentivo" class="form-label">Forma de reparto</label>
                    <select id="modo-incentivo" name="modo" class="form-select" aria-describedby="ayuda-modo">
                        <option value="iguales" @selected(old('modo', $aporte->incentivo['modo'] ?? '') === 'iguales')>Partes iguales</option>
                        <option value="montos" @selected(old('modo', $aporte->incentivo['modo'] ?? '') === 'montos')>Montos individuales</option>
                    </select>
                    <p id="ayuda-modo" class="form-text mb-0">Complete los montos de la tabla si el establecimiento indicó montos individuales.</p>
                </div>
                <div class="col-12">
                    <label for="referencia" class="form-label">Referencia de la instrucción del establecimiento</label>
                    <textarea id="referencia" name="referencia" class="form-control" rows="2" maxlength="2000" required>{{ old('referencia', $aporte->incentivo['referencia'] ?? '') }}</textarea>
                </div>
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th scope="col">Incluir</th><th scope="col">Docente</th><th scope="col">Monto individual ($)</th></tr></thead>
                            <tbody>
                                @foreach ($docentesElegibles as $persona)
                                    @php
                                        $rut = $persona['rut'];
                                    @endphp
                                    <tr>
                                        <td><input type="checkbox" name="ruts[]" value="{{ $rut }}" class="form-check-input" aria-label="Incluir a {{ $persona['nombre'] }}" @checked(in_array($rut, old('ruts', $aporte->incentivo['ruts'] ?? []), true))></td>
                                        <th scope="row">{{ $persona['nombre'] }} <span class="small text-muted fw-normal">{{ $rut }}</span></th>
                                        <td><input type="number" name="montos[{{ $rut }}]" min="1" step="1" class="form-control form-control-sm" aria-label="Monto individual para {{ $persona['nombre'] }}" value="{{ old('montos.'.$rut, $aporte->incentivo['montos'][$rut] ?? '') }}"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-12"><button class="btn btn-primary">Guardar reparto del 10%</button></div>
            </form>
        @endif
    </section>
@endif
