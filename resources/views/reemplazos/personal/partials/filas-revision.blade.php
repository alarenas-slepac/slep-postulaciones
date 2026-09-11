    @php
        $etiquetas = [
            'sin_cambios' => 'Sin cambios', 'actualizacion_propuesta' => 'Actualización propuesta',
            'traslado_propuesto' => 'Traslado propuesto', 'reactivacion_propuesta' => 'Reactivación propuesta',
            'nueva_incorporacion' => 'Nueva incorporación propuesta', 'baja_propuesta' => 'Baja propuesta',
            'revision_manual' => 'Revisión manual', 'ausencia_por_revisar' => 'Ausencia por revisar', 'error' => 'Error',
            'reemplazo_anterior_omitido' => 'Reemplazo anterior omitido',
        ];
        $estadosRevision = [
            'pendiente' => 'Pendiente de decisión', 'resuelta' => 'Decisión registrada',
            'ausencia_vinculada' => 'Ausencia vinculada a una fila', 'error' => 'Error de archivo',
            'propuesta_automatica' => 'Propuesta automática',
            'omitida_por_vigencia' => 'Omitida por vigencia (REEMPLAZO)',
            'conservada' => 'Conservar en el período de carga',
            'nueva_linea_reemplazo' => 'REEMPLAZO: nueva línea automática',
        ];
    @endphp
<div data-padron-filas>
    <form method="GET" action="{{ route('reemplazos.personal.import') }}" class="row g-2 mb-3">
        <input type="hidden" name="revision" value="{{ $revision->id }}">
        <input type="hidden" name="conflictos_page" value="{{ $paginaConflictos }}">
        @if ($casoRut && $casoEstablecimiento)
            <input type="hidden" name="caso_rut" value="{{ $casoRut }}">
            <input type="hidden" name="caso_establecimiento" value="{{ $casoEstablecimiento }}">
        @endif
        <div class="col-md-5"><label class="form-label" for="busqueda">Nombre o RUT sin puntos ni guion</label><input id="busqueda" name="q" class="form-control" value="{{ request('q') }}"></div>
        <div class="col-md-5"><label class="form-label" for="accion-filtro">Acción propuesta</label><select class="form-select" name="accion_filtro" id="accion-filtro">
            <option value="">Todas</option>
            @foreach ($etiquetas as $valor => $texto)
                <option value="{{ $valor }}" @selected(request('accion_filtro') === $valor)>{{ $texto }}</option>
            @endforeach
        </select></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Ver filas del RUT</button></div>
    </form>
    @if (! $mostrarFilas)
        <p class="text-muted">Seleccione «Ver filas del RUT» en el caso que desea revisar. Las correspondencias se cargan solo al solicitarlas.</p>
    @else
    @if ($obsoleta)<div class="alert alert-warning">La revisión cambió de base. Analice nuevamente el archivo antes de registrar decisiones.</div>@endif
    <p class="small text-muted">{{ $filas->total() }} registros. Los casos ambiguos muestran los IDs candidatos; no se selecciona uno automáticamente. Corrija el archivo y vuelva a analizar cuando corresponda.</p>
    @if ($resolucionDisponible && ! $revision->aplicada_at && ! $obsoleta && ! $revision->errores)
        <div class="border rounded p-3 mb-3">
            <button type="button" class="btn btn-primary" data-padron-resolver-varias>Registrar selecciones del mismo RUT</button>
            <div class="small mt-2">Complete «Registro a conservar» y la justificación en las filas que desea guardar. Se envían juntas solo las filas seleccionadas de esta página, de un único RUT. Las demás quedan pendientes. Si hay un error, no se guarda ninguna del grupo.</div>
            <div data-padron-lote-error class="text-danger mt-2" role="alert"></div>
            <noscript>Para registrar varias a la vez debe activar JavaScript. Puede seguir usando «Registrar decisión» en cada fila.</noscript>
        </div>
    @endif
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead><tr><th>Fila Excel / funcionario</th><th>ID actual / acción propuesta</th><th>Actual → propuesta</th><th>Observaciones y asignaciones</th></tr></thead>
            <tbody>
                @forelse ($filas as $fila)
                    @php
                        $estadoRevision = $resumenResolucion['estados'][$fila->id] ?? 'pendiente';
                        $conservacionActualizacion = app(\App\Services\Padron\PadronResolucionService::class)->permiteConservarActualizacion($fila);
                        $idSeleccionado = $resumenResolucion['selecciones'][$fila->id] ?? null;
                        $anteriorComparacion = $fila->anterior;
                        $datosPropuestos = $estadoRevision === 'conservada'
                            ? array_replace($fila->anterior ?? [], ['anio' => $revision->anio, 'mes' => $revision->mes]) : $fila->datos;
                        if ($fila->accion === 'revision_manual' && $decisiones->has($fila->id)) {
                            $anteriorComparacion = collect($fila->candidatos)->firstWhere('id', $idSeleccionado);
                        }
                        $idHistorico = $fila->fila_excel ? $idSeleccionado : $fila->personal_id;
                        $vinculos = $dependenciasHistoricas[$idHistorico] ?? null;
                        $decisionEnviada = collect(old('decisiones', []))->firstWhere('fila', $fila->id);
                        if (! $decisionEnviada && (int) old('fila') === $fila->id) {
                            $decisionEnviada = ['personal_id' => old('personal_id'), 'justificacion' => old('justificacion'), 'decision_anterior' => old('decision_anterior')];
                        }
                    @endphp
                    <tr @class(['table-danger' => $fila->accion === 'error', 'table-warning' => $estadoRevision === 'pendiente' || in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar']), 'table-secondary' => $fila->accion === 'reemplazo_anterior_omitido'])>
                        <td>{{ $fila->fila_excel ?? 'Ausente' }}<br><strong>{{ $fila->rut }}</strong><br>{{ $fila->nombre }}</td>
                        <td>ID: {{ $estadoRevision === 'nueva_linea_reemplazo' ? 'Nuevo al aplicar' : ($fila->personal_id ?? 'Sin seleccionar') }}<br>{{ $estadoRevision === 'nueva_linea_reemplazo' ? 'Nueva incorporación propuesta' : ($estadoRevision === 'conservada' ? ($fila->fila_excel ? 'Conservar datos anteriores; solo actualizar mes' : 'Conservación propuesta (ausente del Excel)') : ($etiquetas[$fila->accion] ?? $fila->accion)) }}
                            <div class="fw-semibold">{{ $estadosRevision[$estadoRevision] ?? $estadoRevision }}</div>
                            @if ($estadoRevision === 'nueva_linea_reemplazo')
                                <div class="small text-success">Se incorporará con un ID nuevo al aplicar el padrón, sin vincular documentos ni asignaciones de contratos anteriores. No requiere seleccionar candidato.</div>
                            @endif
                            @if ($estadoRevision === 'pendiente' && $fila->accion === 'baja_propuesta')
                                <div class="small">Ya se conservó otro contrato de este RUT. Decida también si conserva este ID o confirma su baja; no se dará de baja automáticamente.</div>
                            @endif
                            @if ($estadoRevision === 'conservada')
                                <div class="small">Se conserva este ID y sus datos para {{ $revision->anio }}/{{ $revision->mes }}, sin duplicar contratos ni liberar asignaciones. Solo se actualiza el período al aplicar definitivamente.</div>
                            @endif
                            @if ($estadoRevision === 'ausencia_vinculada')
                                <div class="small">El ID se conservaría en otra fila del archivo; no se propone su baja mientras permanezca seleccionado.</div>
                            @endif
                            @if ($decisiones->has($fila->id))
                                <div class="text-success">Decisión: {{ $decisiones[$fila->id]->personal_id ? ($conservacionActualizacion ? 'Conservar datos anteriores · ID ' : 'ID ').$decisiones[$fila->id]->personal_id : ($conservacionActualizacion ? 'Retomar datos del Excel' : ($fila->fila_excel ? 'Nueva línea' : 'Confirmar baja')) }} · usuario #{{ $decisiones[$fila->id]->resuelta_por }}</div>
                                <div class="small">{{ $decisiones[$fila->id]->justificacion }}</div>
                                <details><summary>Historial de decisiones</summary>
                                    @foreach ($historialDecisiones->get($fila->id, collect()) as $decision)
                                        <div class="small">#{{ $decision->id }} · {{ $decision->created_at }} · usuario #{{ $decision->resuelta_por }} · {{ $decision->personal_id ? ($conservacionActualizacion ? 'Conservar datos anteriores · ID ' : 'ID ').$decision->personal_id : ($conservacionActualizacion ? 'Retomar datos del Excel' : ($fila->fila_excel ? 'Nueva línea' : 'Baja propuesta')) }} · {{ $decision->justificacion }}</div>
                                    @endforeach
                                </details>
                            @endif
                            @if ($fila->candidatos && $estadoRevision !== 'nueva_linea_reemplazo')
                                <details><summary>{{ count($fila->candidatos) }} candidatos</summary>
                                    @foreach ($fila->candidatos as $candidato)
                                        <div>ID {{ $candidato['id'] }} · RBD {{ $candidato['rbd'] ?? '—' }} · {{ $candidato['tipocontrato'] ?? '—' }} · {{ $candidato['jornada'] ?? '—' }} h · {{ $candidato['financiamiento'] ?? '—' }}</div>
                                        @if (isset($dependenciasHistoricas[$candidato['id']]))
                                            <div class="small text-warning-emphasis">{{ $dependenciasHistoricas[$candidato['id']]['total'] }} referencias históricas asociadas a este candidato.</div>
                                        @endif
                                    @endforeach
                                </details>
                            @endif
                            @if ($resolucionDisponible && ! $revision->aplicada_at && ! $obsoleta && ! $revision->errores && ! in_array($estadoRevision, ['ausencia_vinculada', 'nueva_linea_reemplazo']) && ($conservacionActualizacion || in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar', 'baja_propuesta'])) && (! $fila->fila_excel || \App\Services\Padron\PadronConciliador::tipo($fila->datos) !== 'por_clasificar'))
                                <details><summary>{{ $conservacionActualizacion ? 'Revisar actualización' : ($fila->fila_excel ? 'Resolver correspondencia' : 'Resolver ausencia') }}</summary>
                                    <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" class="mt-2" data-padron-decision-form data-rut="{{ $fila->rut }}">
                                        @csrf
                                        <input type="hidden" name="accion" value="resolver">
                                        <input type="hidden" name="q" value="{{ request('q') }}">
                                        <input type="hidden" name="accion_filtro" value="{{ request('accion_filtro') }}">
                                        <input type="hidden" name="page" value="{{ $filas->currentPage() }}">
                                        <input type="hidden" name="conflictos_page" value="{{ $paginaConflictos }}">
                                        @if ($casoRut && $casoEstablecimiento)
                                            <input type="hidden" name="caso_rut" value="{{ $casoRut }}">
                                            <input type="hidden" name="caso_establecimiento" value="{{ $casoEstablecimiento }}">
                                        @endif
                                        <input type="hidden" name="revision" value="{{ $revision->id }}">
                                        <input type="hidden" name="fila" value="{{ $fila->id }}">
                                        <input type="hidden" name="decision_anterior" value="{{ $decisionEnviada['decision_anterior'] ?? $decisiones->get($fila->id)?->id ?? 0 }}">
                                        <label class="form-label" for="decision-{{ $fila->id }}">Registro a conservar</label>
                                        <select id="decision-{{ $fila->id }}" name="personal_id" class="form-select form-select-sm" required>
                                            <option value="">Seleccione explícitamente</option>
                                            @if ($conservacionActualizacion)
                                                <option value="{{ $fila->personal_id }}" @selected((string) ($decisionEnviada['personal_id'] ?? '') === (string) $fila->personal_id)>Conservar datos anteriores; solo actualizar mes · ID {{ $fila->personal_id }} · {{ $revision->anio }}/{{ $revision->mes }}</option>
                                            @elseif (! $fila->fila_excel)
                                                <option value="{{ $fila->personal_id }}" @selected((string) ($decisionEnviada['personal_id'] ?? '') === (string) $fila->personal_id)>Conservar este ID en el período de carga · ID {{ $fila->personal_id }} · {{ $revision->anio }}/{{ $revision->mes }}</option>
                                            @endif
                                            @foreach ($conservacionActualizacion ? [] : $fila->candidatos as $candidato)
                                                <option value="{{ $candidato['id'] }}" @selected((string) ($decisionEnviada['personal_id'] ?? '') === (string) $candidato['id'])>ID {{ $candidato['id'] }} · RBD {{ $candidato['rbd'] ?? '—' }} · {{ $candidato['jornada'] ?? '—' }} h · {{ $candidato['financiamiento'] ?? '—' }}{{ isset($candidato['_redistribucion']) ? ' · Receptor sugerido (requiere confirmación)' : '' }}</option>
                                            @endforeach
                                            <option value="0" @selected((string) ($decisionEnviada['personal_id'] ?? '') === '0')>{{ $conservacionActualizacion ? 'Retomar actualización con los datos del Excel' : ($fila->fila_excel ? 'Confirmar nueva línea contractual' : 'Confirmar baja de esta línea') }}</option>
                                        </select>
                                        <label class="form-label mt-1" for="motivo-{{ $fila->id }}">Justificación</label>
                                        <textarea id="motivo-{{ $fila->id }}" name="justificacion" class="form-control form-control-sm" minlength="10" maxlength="2000" required>{{ $decisionEnviada['justificacion'] ?? '' }}</textarea>
                                        <button class="btn btn-sm btn-outline-primary mt-1">Registrar decisión</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                        <td>
                            @if ($conservacionActualizacion && $estadoRevision === 'conservada')
                                <details class="mb-2"><summary>Propuesta original del Excel (no se aplicará)</summary>
                                    @foreach ($fila->datos as $campo => $valor)<div>{{ $campo }}: {{ $valor ?? '—' }}</div>@endforeach
                                </details>
                            @endif
                            @foreach (['rbd' => 'RBD', 'tipocontrato' => 'Contrato', 'financiamiento' => 'Financiamiento', 'jornada' => 'Jornada', 'fecha_antiguedad' => 'Antigüedad'] as $campo => $titulo)
                                <div><strong>{{ $titulo }}:</strong> {{ $anteriorComparacion[$campo] ?? '—' }} → {{ $datosPropuestos[$campo] ?? ($campo === 'fecha_antiguedad' && $fila->fila_excel ? 'Conservar anterior' : '—') }}</div>
                            @endforeach
                            <details><summary>Otros datos comparados</summary>
                                @foreach (['nombre', 'fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'estatuto', 'escalafon', 'anio', 'mes', 'jornada_basica', 'jornada_media', 'bienios', 'tramo'] as $campo)
                                    <div>{{ $campo }}: {{ $anteriorComparacion[$campo] ?? '—' }} → {{ $datosPropuestos[$campo] ?? '—' }}</div>
                                @endforeach
                            </details>
                        </td>
                        <td>
                            @foreach ($fila->candidatos ?? [] as $candidatoRedistribucion)
                                @if (isset($candidatoRedistribucion['_redistribucion']))
                                    @include('reemplazos.personal.partials.redistribucion', ['propuesta' => $candidatoRedistribucion['_redistribucion']])
                                @endif
                            @endforeach
                            @if ($vinculos)
                                <details class="mb-2"><summary>{{ $vinculos['total'] }} referencias históricas del ID {{ $idHistorico }}</summary>
                                    @foreach ($vinculos['referencias'] as $vinculo)
                                        <div>{{ $vinculo['modulo'] }} #{{ $vinculo['id'] }}</div>
                                    @endforeach
                                    @if ($vinculos['total'] > count($vinculos['referencias']))<div>Se muestran las primeras 20 referencias.</div>@endif
                                    <div class="small">Referencias actuales: se revalidan y protegen antes de aplicar cambios contractuales. No se trasladan ni eliminan estos documentos.</div>
                                </details>
                            @endif
                            <div class="small text-muted">Observaciones del análisis original; la cobertura actual se muestra en el diagnóstico de Dotación.</div>
                            @foreach ($fila->observaciones as $observacion)
                                <div>{{ $observacion }}</div>
                            @endforeach
                            @if ($fila->asignaciones)
                                <details><summary>{{ count($fila->asignaciones) }} asignaciones relacionadas al analizar (por RUT o ID)</summary>
                                    <div class="small">Copia del análisis original. Consulte los vínculos y la cobertura actuales en «Conflictos con asignaciones de Dotación».</div>
                                    @foreach ($fila->asignaciones as $asignacion)
                                        <div>#{{ $asignacion['id'] }} · Establecimiento ID {{ $asignacion['establecimiento_id'] ?? '—' }} · {{ $asignacion['tipo_asignacion'] ?? '—' }} · {{ $asignacion['horas_contrato'] ?? '—' }} h</div>
                                    @endforeach
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay registros para estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $filas->links() }}
    @endif
</div>
