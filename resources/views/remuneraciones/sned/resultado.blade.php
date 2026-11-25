@php
    $pestanas = ['docentes' => 'Docentes', 'aaee' => 'Asistentes'];
    $esDocente = $estamento === 'docentes';
    $personas = array_filter($resultado['personas'], fn ($persona) => $persona['estamento'] === $estamento);
    $fueraDelCorte = $resultado['fuera_del_corte'][$estamento] ?? [];
    $fondosNomina = $esDocente ? ['docentes' => '90% docente', 'incentivo' => '10% incentivo docente'] : ['aaee' => 'Aporte de asistentes'];
    $totalNomina = $resultado['pagos'][$estamento] + ($esDocente ? $resultado['pagos']['incentivo'] : 0);
@endphp
<section id="calculo-rbd" class="slep-card p-4 mb-4" aria-labelledby="detalle-sned">
    <h2 id="detalle-sned" class="h5 mb-1">{{ $resultado['establecimiento'] }}</h2>
    <p class="small text-muted mb-3">RBD {{ $resultado['rbd'] }} · {{ $metodos[$cuota->metodo] }}</p>
    @if ($resultado['errores'])
        <div class="alert alert-warning">
            <ul class="mb-0">@foreach ($resultado['errores'] as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <nav class="nav nav-tabs mb-3" aria-label="Nómina por estamento">
        @foreach ($pestanas as $clave => $label)
            <a @class(['nav-link', 'active' => $estamento === $clave])
               href="{{ route('sned.show', [$cuota, 'rbd' => $aporte->rbd, 'estamento' => $clave]) }}#calculo-rbd"
               @if ($estamento === $clave) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="row g-3 mb-4">
        @foreach ($fondosNomina as $clave => $label)
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
            <caption class="caption-top pt-0">{{ $pestanas[$estamento] }} con vigencia en {{ mb_strtolower($nombresMes[max($cuota->meses())]) }}</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Funcionario/a</th>
                    <th scope="col" class="text-end">Horas de corte</th>
                    @if ($esDocente)
                        <th scope="col" class="text-end">Reparto general</th>
                        <th scope="col" class="text-end">Incentivo</th>
                    @endif
                    <th scope="col" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($personas as $persona)
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
                        <td class="text-end">{{ number_format($persona['minutos_corte'] / 60, 2, ',', '.') }}</td>
                        @if ($esDocente)
                            <td class="text-end">{{ $pesos($persona['general']) }}</td>
                            <td class="text-end">{{ $pesos($persona['incentivo']) }}</td>
                        @endif
                        <td class="text-end fw-semibold">{{ $pesos($persona['general'] + $persona['incentivo']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $esDocente ? 5 : 3 }}" class="text-center text-muted">Sin personas elegibles en el mes de corte.</td></tr>
                @endforelse
            </tbody>
            <tfoot class="table-light"><tr><th colspan="{{ $esDocente ? 4 : 2 }}" scope="row" class="text-end">Total {{ mb_strtolower($pestanas[$estamento]) }}</th><td class="text-end fw-bold">{{ $pesos($totalNomina) }}</td></tr></tfoot>
        </table>
    </div>
    @if ($fueraDelCorte)
        <details class="mt-2">
            <summary class="fw-semibold">Personas con contrato solo en meses anteriores al corte</summary>
            <ul class="small pt-2 mb-0">
                @foreach ($fueraDelCorte as $persona)
                    <li>{{ $persona['nombre'] }} · {{ $persona['rut'] }}</li>
                @endforeach
            </ul>
        </details>
    @endif
</section>

@if ($esDocente && $aporte->fondoIncentivo() && $personas)
    <section class="slep-card p-4 mb-4" aria-labelledby="incentivo-sned">
        <h2 id="incentivo-sned" class="h5 mb-1">Incentivo docente 10%</h2>
        <p class="text-muted mb-3">{{ $aporte->establecimiento }} · Fondo {{ $pesos($aporte->fondoIncentivo()) }}</p>
        @if ($cerrada)
            <p class="mb-1">{{ $aporte->incentivo['referencia'] ?? '' }}</p>
            @if (! empty($aporte->incentivo['registrado_at']))
                <p class="small text-muted mb-0">Registrado el {{ $aporte->incentivo['registrado_at'] }}</p>
            @endif
        @else
            @php
                $rutsSeleccionados = array_key_exists('referencia', old())
                    ? (array) old('ruts', [])
                    : ($aporte->incentivo['ruts'] ?? []);
            @endphp
            <form id="form-incentivo-sned" method="POST" action="{{ route('sned.incentivo', [$cuota, $aporte]) }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <label for="referencia" class="form-label">Referencia de la instrucción del establecimiento</label>
                    <textarea id="referencia" name="referencia" class="form-control @error('referencia') is-invalid @enderror" rows="2" maxlength="2000" required>{{ old('referencia', $aporte->incentivo['referencia'] ?? '') }}</textarea>
                    @error('referencia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <p id="ayuda-incentivo-sned" class="form-text mb-2">El fondo se reparte en partes iguales entre los docentes seleccionados. Si selecciona a uno, recibe el fondo completo.</p>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <button type="button" id="alternar-incentivo-sned" class="btn btn-sm btn-outline-secondary" aria-controls="docentes-incentivo-sned" hidden>Seleccionar todos</button>
                        <span id="conteo-incentivo-sned" class="small text-muted" role="status"></span>
                    </div>
                    @error('ruts')<p class="small text-danger mb-2">{{ $message }}</p>@enderror
                    <div class="table-responsive">
                        <table id="docentes-incentivo-sned" class="table table-hover align-middle mb-0" aria-label="Docentes elegibles para el incentivo" aria-describedby="ayuda-incentivo-sned">
                            <thead class="table-light"><tr><th scope="col">Incluir</th><th scope="col">Docente</th></tr></thead>
                            <tbody>
                                @foreach ($personas as $persona)
                                    <tr>
                                        <td><input id="docente-incentivo-{{ $loop->iteration }}" type="checkbox" name="ruts[]" value="{{ $persona['rut'] }}" class="form-check-input" @checked(in_array($persona['rut'], $rutsSeleccionados, true))></td>
                                        <th scope="row"><label class="form-check-label" for="docente-incentivo-{{ $loop->iteration }}">{{ $persona['nombre'] }} <span class="small text-muted fw-normal">{{ $persona['rut'] }}</span></label></th>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-12"><button class="btn btn-primary">Guardar reparto del 10%</button></div>
            </form>
            @push('scripts')
                <script>
                    (() => {
                        const formulario = document.getElementById('form-incentivo-sned');
                        const boton = document.getElementById('alternar-incentivo-sned');
                        const conteo = document.getElementById('conteo-incentivo-sned');
                        const casillas = Array.from(formulario.querySelectorAll('input[name="ruts[]"]'));

                        const actualizarSeleccion = () => {
                            const seleccionados = casillas.filter(casilla => casilla.checked).length;
                            boton.textContent = seleccionados === casillas.length ? 'Deseleccionar todos' : 'Seleccionar todos';
                            conteo.textContent = `${seleccionados} de ${casillas.length} seleccionados`;
                        };

                        boton.addEventListener('click', () => {
                            const marcar = casillas.some(casilla => !casilla.checked);
                            casillas.forEach(casilla => { casilla.checked = marcar; });
                            actualizarSeleccion();
                        });
                        casillas.forEach(casilla => casilla.addEventListener('change', actualizarSeleccion));
                        actualizarSeleccion();
                        boton.hidden = false;
                    })();
                </script>
            @endpush
        @endif
    </section>
@endif
