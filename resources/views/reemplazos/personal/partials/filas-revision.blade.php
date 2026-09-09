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
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead><tr><th>Fila Excel / funcionario</th><th>ID actual / acción propuesta</th><th>Actual → archivo</th><th>Observaciones y asignaciones</th></tr></thead>
            <tbody>
                @forelse ($filas as $fila)
                    @php
                        $estadoRevision = $resumenResolucion['estados'][$fila->id] ?? 'pendiente';
                        $idSeleccionado = $resumenResolucion['selecciones'][$fila->id] ?? null;
                        $anteriorComparacion = $fila->anterior;
                        if ($fila->accion === 'revision_manual' && $decisiones->has($fila->id)) {
                            $anteriorComparacion = collect($fila->candidatos)->firstWhere('id', $idSeleccionado);
                        }
                        $idHistorico = $fila->fila_excel ? $idSeleccionado : $fila->personal_id;
                        $vinculos = $dependenciasHistoricas[$idHistorico] ?? null;
                    @endphp
                    <tr @class(['table-danger' => $fila->accion === 'error', 'table-warning' => in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar']), 'table-secondary' => $fila->accion === 'reemplazo_anterior_omitido'])>
                        <td>{{ $fila->fila_excel ?? 'Ausente' }}<br><strong>{{ $fila->rut }}</strong><br>{{ $fila->nombre }}</td>
                        <td>ID: {{ $fila->personal_id ?? 'Sin seleccionar' }}<br>{{ $etiquetas[$fila->accion] ?? $fila->accion }}
                            <div class="fw-semibold">{{ $estadosRevision[$estadoRevision] ?? $estadoRevision }}</div>
                            @if ($estadoRevision === 'ausencia_vinculada')
                                <div class="small">El ID se conservaría en otra fila del archivo; no se propone su baja mientras permanezca seleccionado.</div>
                            @endif
                            @if ($decisiones->has($fila->id))
                                <div class="text-success">Decisión: {{ $decisiones[$fila->id]->personal_id ? 'ID '.$decisiones[$fila->id]->personal_id : ($fila->fila_excel ? 'Nueva línea' : 'Confirmar baja') }} · usuario #{{ $decisiones[$fila->id]->resuelta_por }}</div>
                                <div class="small">{{ $decisiones[$fila->id]->justificacion }}</div>
                                <details><summary>Historial de decisiones</summary>
                                    @foreach ($historialDecisiones->get($fila->id, collect()) as $decision)
                                        <div class="small">#{{ $decision->id }} · {{ $decision->created_at }} · usuario #{{ $decision->resuelta_por }} · {{ $decision->personal_id ? 'ID '.$decision->personal_id : ($fila->fila_excel ? 'Nueva línea' : 'Baja propuesta') }} · {{ $decision->justificacion }}</div>
                                    @endforeach
                                </details>
                            @endif
                            @if ($fila->candidatos)
                                <details><summary>{{ count($fila->candidatos) }} candidatos</summary>
                                    @foreach ($fila->candidatos as $candidato)
                                        <div>ID {{ $candidato['id'] }} · RBD {{ $candidato['rbd'] ?? '—' }} · {{ $candidato['tipocontrato'] ?? '—' }} · {{ $candidato['jornada'] ?? '—' }} h · {{ $candidato['financiamiento'] ?? '—' }}</div>
                                        @if (isset($dependenciasHistoricas[$candidato['id']]))
                                            <div class="small text-warning-emphasis">{{ $dependenciasHistoricas[$candidato['id']]['total'] }} referencias históricas asociadas a este candidato.</div>
                                        @endif
                                    @endforeach
                                </details>
                            @endif
                            @if ($resolucionDisponible && ! $revision->aplicada_at && ! $obsoleta && ! $revision->errores && $estadoRevision !== 'ausencia_vinculada' && in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar']) && (! $fila->fila_excel || \App\Services\Padron\PadronConciliador::tipo($fila->datos) !== 'por_clasificar'))
                                <details><summary>Resolver correspondencia</summary>
                                    <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" class="mt-2">
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
                                        <input type="hidden" name="decision_anterior" value="{{ $decisiones->get($fila->id)?->id ?? 0 }}">
                                        <label class="form-label" for="decision-{{ $fila->id }}">Registro a conservar</label>
                                        <select id="decision-{{ $fila->id }}" name="personal_id" class="form-select form-select-sm" required>
                                            <option value="">Seleccione explícitamente</option>
                                            @foreach ($fila->candidatos as $candidato)
                                                <option value="{{ $candidato['id'] }}">ID {{ $candidato['id'] }} · RBD {{ $candidato['rbd'] ?? '—' }} · {{ $candidato['jornada'] ?? '—' }} h · {{ $candidato['financiamiento'] ?? '—' }}{{ isset($candidato['_redistribucion']) ? ' · Receptor sugerido (requiere confirmación)' : '' }}</option>
                                            @endforeach
                                            <option value="0">{{ $fila->fila_excel ? 'Confirmar nueva línea contractual' : 'Confirmar baja de esta línea' }}</option>
                                        </select>
                                        <label class="form-label mt-1" for="motivo-{{ $fila->id }}">Justificación</label>
                                        <textarea id="motivo-{{ $fila->id }}" name="justificacion" class="form-control form-control-sm" minlength="10" maxlength="2000" required></textarea>
                                        <button class="btn btn-sm btn-outline-primary mt-1">Registrar decisión</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                        <td>
                            @foreach (['rbd' => 'RBD', 'tipocontrato' => 'Contrato', 'financiamiento' => 'Financiamiento', 'jornada' => 'Jornada', 'fecha_antiguedad' => 'Antigüedad'] as $campo => $titulo)
                                <div><strong>{{ $titulo }}:</strong> {{ $anteriorComparacion[$campo] ?? '—' }} → {{ $fila->datos[$campo] ?? ($campo === 'fecha_antiguedad' && $fila->fila_excel ? 'Conservar anterior' : '—') }}</div>
                            @endforeach
                            <details><summary>Otros datos comparados</summary>
                                @foreach (['nombre', 'fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'estatuto', 'escalafon', 'anio', 'mes', 'jornada_basica', 'jornada_media', 'bienios', 'tramo'] as $campo)
                                    <div>{{ $campo }}: {{ $anteriorComparacion[$campo] ?? '—' }} → {{ $fila->datos[$campo] ?? '—' }}</div>
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
