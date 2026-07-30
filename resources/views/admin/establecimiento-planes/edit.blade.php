@extends('layouts.app')

@section('content')
    @php
        $ec = $configuracion->establecimientoCurso;
        $plan = $configuracion->planEstudio;
        $asignaturasJson = json_encode($asignaturasPorTipo, JSON_UNESCAPED_UNICODE);
        $tipoOptions = \App\Models\Asignatura::TIPOS;
        $planComunAsociadoTypes = ['obligatoria','plan_comun_electivo'];
        $allowedByBlock = [
            'plan_comun_formacion_general_electivo' => ['plan_comun_electivo'],
            'plan_diferenciado_hc' => ['plan_diferenciado_hc'],
            'plan_diferenciado_tp' => ['plan_diferenciado_tp'],
            'plan_diferenciado_artistico' => ['plan_diferenciado_artistico'],
            'libre_disposicion' => ['obligatoria','plan_comun_electivo','plan_diferenciado_hc','plan_diferenciado_tp','plan_diferenciado_artistico','libre_disposicion'],
        ];
    @endphp

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Configurar plan del establecimiento</h1>
            <div class="text-muted small">{{ $configuracion->establecimiento->nombre_establecimiento ?? 'Establecimiento' }} · {{ $ec->nombre_seccion ?? '' }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.establecimiento-planes.index') }}">Volver</a>
            <a class="btn btn-outline-info" href="{{ route('admin.establecimiento-planes.show', $configuracion) }}">Ver configuración</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4"><div class="text-muted small">Establecimiento</div><div class="fw-semibold">{{ $configuracion->establecimiento->rbd ?? '' }} — {{ $configuracion->establecimiento->nombre_establecimiento ?? '' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Curso/sección</div><div class="fw-semibold">{{ $ec->nombre_seccion ?? '' }}</div></div>
            <div class="col-md-2"><div class="text-muted small">Matrícula</div><div class="fw-semibold">{{ number_format((int) ($ec->matricula ?? 0), 0, ',', '.') }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Plan</div><div class="fw-semibold">{{ $plan->nombre_plan ?? '' }}</div><div class="small text-muted">{{ $ec->regimen_jec ?? '' }} · {{ $plan->horas_semanales_total ?? 0 }} h</div></div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.establecimiento-planes.update', $configuracion) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Bloques del plan</div>
            <div class="card-body">
                @foreach ($bloques as $bloque)
                    @php
                        $detalles = $detallesPorBloque[$bloque->id] ?? collect();
                        $oficiales = collect($asignaturasOficialesPorBloque[$bloque->tipo_bloque] ?? []);
                        $editable = $bloque->permite_asignaturas_establecimiento || $bloque->permite_asignaturas_personalizadas;
                        $allowedTypes = $allowedByBlock[$bloque->tipo_bloque] ?? ['obligatoria','plan_comun_electivo','plan_diferenciado_hc','plan_diferenciado_tp','plan_diferenciado_artistico','libre_disposicion'];
                        $isLibreDisposicion = $bloque->tipo_bloque === 'libre_disposicion';
                    @endphp
                    <div class="border rounded-3 p-3 mb-3" data-block="{{ $bloque->id }}" data-allowed='@json($allowedTypes)' data-personalizadas="{{ $bloque->permite_asignaturas_personalizadas ? 1 : 0 }}" data-libre-disposicion="{{ $isLibreDisposicion ? 1 : 0 }}">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">{{ $bloque->nombre }}</div>
                                <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $bloque->tipo_bloque)) }} · Máximo {{ number_format((float) $bloque->horas_semanales, 2, ',', '.') }} h semanales</div>
                            </div>
                            <div>
                                @if ($editable)
                                    <span class="badge text-bg-primary">Completable por EE</span>
                                    @if ($bloque->permite_asignaturas_personalizadas)<span class="badge text-bg-success">Permite personalizada</span>@endif
                                @else
                                    <span class="badge text-bg-light border">Bloque fijo</span>
                                @endif
                            </div>
                        </div>

                        @if (! $editable)
                            @if ($oficiales->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Asignatura oficial del plan</th>
                                                <th class="text-end">Horas sem.</th>
                                                <th class="text-end">Horas anuales</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($oficiales as $oficial)
                                                <tr>
                                                    <td>{{ $oficial['asignatura'] ?? 'Asignatura sin nombre' }}</td>
                                                    <td class="text-end">{{ number_format((float) ($oficial['horas_semanales'] ?? 0), 2, ',', '.') }}</td>
                                                    <td class="text-end">{{ ($oficial['horas_anuales'] ?? null) !== null ? number_format((float) $oficial['horas_anuales'], 2, ',', '.') : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-light border mb-0 small">Este bloque es fijo. Se usa como referencia para validar la carga horaria del plan oficial.</div>
                            @endif
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead>
                                        <tr>
                                            <th style="width: 70px;">Orden</th>
                                            <th>Asignatura oficial</th>
                                            <th>Personalizada</th>
                                            @if ($isLibreDisposicion)
                                                <th>Plan común asociado <span class="text-muted fw-normal">(opcional)</span></th>
                                            @endif
                                            <th style="width: 130px;">Horas sem.</th>
                                            <th style="width: 130px;">Horas anuales</th>
                                            <th>Observación</th>
                                            <th style="width: 80px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="detalle-body">
                                        @forelse ($detalles as $detalle)
                                            <tr>
                                                <td><input type="number" name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][orden]" class="form-control form-control-sm" value="{{ $detalle->orden }}"></td>
                                                <td>
                                                    <input type="hidden" name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][plan_estudio_bloque_id]" value="{{ $bloque->id }}">
                                                    <select name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][asignatura_id]" class="form-select form-select-sm asignatura-select">
                                                        <option value="">Seleccione...</option>
                                                        @foreach ($asignaturasPorTipo as $tipo => $niveles)
                                                            @if (in_array($tipo, $allowedTypes, true))
                                                                @foreach ($niveles as $nivel => $areas)
                                                                    @foreach ($areas as $area => $lista)
                                                                        <optgroup label="{{ ($tipoOptions[$tipo] ?? $tipo) }} / {{ $nivel }} / {{ $area }}">
                                                                            @foreach ($lista as $asignatura)
                                                                                <option value="{{ $asignatura['id'] }}" @selected((int) $detalle->asignatura_id === (int) $asignatura['id'])>{{ $asignatura['nombre'] }}</option>
                                                                            @endforeach
                                                                        </optgroup>
                                                                    @endforeach
                                                                @endforeach
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="text" name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][nombre_asignatura_personalizada]" class="form-control form-control-sm" value="{{ $detalle->nombre_asignatura_personalizada }}" @disabled(! $bloque->permite_asignaturas_personalizadas)></td>
                                                @if ($isLibreDisposicion)
                                                    <td>
                                                        <select name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][asignatura_plan_comun_id]" class="form-select form-select-sm">
                                                            <option value="">Sin asociación</option>
                                                            @foreach ($asignaturasPorTipo as $tipo => $niveles)
                                                                @if (in_array($tipo, $planComunAsociadoTypes, true))
                                                                    @foreach ($niveles as $nivel => $areas)
                                                                        @foreach ($areas as $area => $lista)
                                                                            <optgroup label="{{ ($tipoOptions[$tipo] ?? $tipo) }} / {{ $nivel }} / {{ $area }}">
                                                                                @foreach ($lista as $asignatura)
                                                                                    <option value="{{ $asignatura['id'] }}" @selected((int) $detalle->asignatura_plan_comun_id === (int) $asignatura['id'])>{{ $asignatura['nombre'] }}</option>
                                                                                @endforeach
                                                                            </optgroup>
                                                                        @endforeach
                                                                    @endforeach
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                @endif
                                                <td><input type="number" step="0.01" min="0" name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][horas_semanales]" class="form-control form-control-sm" value="{{ $detalle->horas_semanales }}"></td>
                                                <td><input type="number" step="0.01" min="0" name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][horas_anuales]" class="form-control form-control-sm" value="{{ $detalle->horas_anuales }}"></td>
                                                <td><input type="text" name="detalles[{{ $loop->parent->index }}_{{ $loop->index }}][observacion]" class="form-control form-control-sm" value="{{ $detalle->observacion }}"></td>
                                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Quitar</button></td>
                                            </tr>
                                        @empty
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary add-row" data-bloque="{{ $bloque->id }}">Agregar asignatura</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <label class="form-label">Observación general</label>
                <textarea class="form-control" name="observacion" rows="3">{{ old('observacion', $configuracion->observacion) }}</textarea>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2">
            <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-planes.index') }}">Cancelar</a>
            <button class="btn btn-primary" name="action" value="guardar" type="submit">Guardar borrador</button>
            <button class="btn btn-success" name="action" value="enviar" type="submit">Guardar y marcar enviado</button>
        </div>
    </form>

    <script>
        (() => {
            let counter = 10000;
            const asignaturas = {!! $asignaturasJson ?: '{}' !!};
            const labels = @json($tipoOptions);
            const planComunAsociadoTypes = @json($planComunAsociadoTypes);

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function buildOptions(allowed) {
                let html = '<option value="">Seleccione...</option>';
                allowed.forEach(tipo => {
                    if (!asignaturas[tipo]) return;

                    Object.entries(asignaturas[tipo]).forEach(([nivel, areas]) => {
                        Object.entries(areas).forEach(([area, lista]) => {
                            const label = `${labels[tipo] || tipo} / ${nivel} / ${area}`;
                            html += `<optgroup label="${escapeHtml(label)}">`;
                            lista.forEach(a => {
                                html += `<option value="${a.id}">${escapeHtml(a.nombre)}</option>`;
                            });
                            html += '</optgroup>';
                        });
                    });
                });
                return html;
            }

            document.querySelectorAll('.add-row').forEach(button => {
                button.addEventListener('click', () => {
                    const wrap = button.closest('[data-block]');
                    const bloqueId = wrap.dataset.block;
                    const allowed = JSON.parse(wrap.dataset.allowed || '[]');
                    const personalizadas = wrap.dataset.personalizadas === '1';
                    const libreDisposicion = wrap.dataset.libreDisposicion === '1';
                    const key = 'new_' + (counter++);
                    const tr = document.createElement('tr');
                    const asociacionHtml = libreDisposicion
                        ? `<td><select name="detalles[${key}][asignatura_plan_comun_id]" class="form-select form-select-sm"><option value="">Sin asociación</option>${buildOptions(planComunAsociadoTypes).replace('<option value="">Seleccione...</option>', '')}</select></td>`
                        : '';
                    tr.innerHTML = `
                        <td><input type="number" name="detalles[${key}][orden]" class="form-control form-control-sm" value="1"></td>
                        <td>
                            <input type="hidden" name="detalles[${key}][plan_estudio_bloque_id]" value="${bloqueId}">
                            <select name="detalles[${key}][asignatura_id]" class="form-select form-select-sm asignatura-select">${buildOptions(allowed)}</select>
                        </td>
                        <td><input type="text" name="detalles[${key}][nombre_asignatura_personalizada]" class="form-control form-control-sm" ${personalizadas ? '' : 'disabled'}></td>
                        ${asociacionHtml}
                        <td><input type="number" step="0.01" min="0" name="detalles[${key}][horas_semanales]" class="form-control form-control-sm" value="0"></td>
                        <td><input type="number" step="0.01" min="0" name="detalles[${key}][horas_anuales]" class="form-control form-control-sm"></td>
                        <td><input type="text" name="detalles[${key}][observacion]" class="form-control form-control-sm"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">Quitar</button></td>`;
                    wrap.querySelector('.detalle-body').appendChild(tr);
                });
            });

            document.addEventListener('click', event => {
                if (event.target.classList.contains('remove-row')) {
                    event.target.closest('tr')?.remove();
                }
            });
        })();
    </script>
@endsection
