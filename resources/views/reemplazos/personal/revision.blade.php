@extends('layouts.app')

@section('content')
    @php
        $etiquetas = [
            'sin_cambios' => 'Sin cambios', 'actualizacion_propuesta' => 'Actualización propuesta',
            'traslado_propuesto' => 'Traslado propuesto', 'reactivacion_propuesta' => 'Reactivación propuesta',
            'nueva_incorporacion' => 'Nueva incorporación propuesta', 'baja_propuesta' => 'Baja propuesta',
            'revision_manual' => 'Revisión manual', 'ausencia_por_revisar' => 'Ausencia por revisar', 'error' => 'Error',
        ];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3>Revisión del padrón #{{ $revision->id }}</h3>
            <div class="text-muted">{{ $revision->archivo }} · Período: {{ $revision->anio ?? 'Mixto/inválido' }} / {{ $revision->mes ?? '—' }}</div>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('reemplazos.personal.import') }}">Analizar otro archivo</a>
    </div>
    <div class="alert alert-info">
        @if ($revision->aplicada_at)
            <strong>Carga aplicada el {{ $revision->aplicada_at }} por usuario #{{ $revision->aplicada_por }}.</strong>
            {{ $cambiosAplicados }} cambios auditados. Esta revisión está cerrada y conserva las propuestas originales.
        @else
            <strong>Solo previsualización.</strong> No se modifican contratos, vigencias ni asignaciones.
            Las autorizaciones de jornada tampoco aplican registros al padrón.
        @endif
        La Declaración de Sostenedores mantiene su prioridad.
    </div>
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @if ($obsoleta)
        <div class="alert alert-warning">La base o sus dependencias cambiaron desde este análisis. Las propuestas están desactualizadas. Analice nuevamente el archivo; no es posible autorizar excepciones sobre esta revisión.</div>
    @endif
    @foreach ($revision->errores as $error)
        <div class="alert alert-danger">{{ $error }}</div>
    @endforeach
    @if (! $revision->aplicada_at)
        <div class="card mb-3"><div class="card-body">
            <h5>Aplicación definitiva del padrón completo</h5>
            @if (! $aplicacionDisponible)
                <div class="alert alert-warning">No habilitada en esta etapa. La actualización, incorporación y desactivación de personal quedan pendientes de validar la compatibilidad con documentos históricos. Instalar las migraciones no habilita esta acción.</div>
            @elseif ($bloqueos)
                <div class="alert alert-warning"><strong>{{ count($bloqueos) }} bloqueos por resolver</strong>
                    <ul class="mb-0">
                        @foreach (array_slice($bloqueos, 0, 100) as $bloqueo)
                            <li>{{ $bloqueo }}</li>
                        @endforeach
                    </ul>
                    @if (count($bloqueos) > 100)<div>Se muestran los primeros 100; revise las filas mediante los filtros.</div>@endif
                </div>
            @elseif (! $obsoleta)
                <form method="POST" action="{{ route('reemplazos.personal.import.store') }}">
                    @csrf
                    <input type="hidden" name="accion" value="aplicar">
                    <input type="hidden" name="revision" value="{{ $revision->id }}">
                    <div class="form-check mb-2"><input id="confirmar-aplicacion" class="form-check-input" type="checkbox" name="confirmar_aplicacion" value="1" required><label class="form-check-label" for="confirmar-aplicacion">Confirmo el padrón completo y autorizo estas actualizaciones, incorporaciones y desactivaciones.</label></div>
                    <button class="btn btn-danger">Aplicar padrón completo</button>
                </form>
            @endif
        </div></div>
    @endif

    <div class="row g-2 mb-3">
        @foreach ($revision->resumen as $accion => $cantidad)
            <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-2">
                <div class="small">{{ $etiquetas[$accion] ?? $accion }}</div><strong class="fs-4">{{ $cantidad }}</strong>
            </div></div></div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-header">Revisión de jornada docente: más de 44 horas</div>
        <div class="card-body">
            <p class="small text-muted">Se suma Jornada de todas las líneas del RUT, en todos los RBD y financiamientos, si tiene al menos un contrato docente. Jornada Básica y Media no se vuelven a sumar. La autorización es exclusiva de esta carga y de este total; no es una excepción permanente ni aplica cambios al padrón.</p>
            @forelse ($revision->excesos as $rut => $exceso)
                <div class="border rounded p-3 mb-2">
                    <strong>{{ $rut }}: {{ $exceso['total'] }} h</strong> · Exceso: {{ $exceso['exceso'] }} h · Filas Excel: {{ implode(', ', $exceso['filas']) }}
                    @if ($autorizaciones->has($rut))
                        @php
                            $autorizacion = $autorizaciones->get($rut);
                        @endphp
                        <div class="text-success mt-2">Autorizada por usuario #{{ $autorizacion->autorizado_por }} · {{ $autorizacion->created_at }}</div>
                        <div>{{ $autorizacion->justificacion }}</div>
                    @elseif (! $obsoleta && ! $revision->errores && ! $revision->aplicada_at)
                        <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="accion" value="autorizar_exceso">
                            <input type="hidden" name="revision" value="{{ $revision->id }}">
                            <input type="hidden" name="rut" value="{{ $rut }}">
                            <label for="justificacion-{{ $loop->index }}" class="form-label">Justificación de la excepción</label>
                            <textarea id="justificacion-{{ $loop->index }}" name="justificacion" class="form-control mb-2" minlength="10" maxlength="2000" rows="2" required></textarea>
                            <button class="btn btn-outline-danger btn-sm">Registrar autorización de {{ $exceso['total'] }} horas</button>
                        </form>
                    @else
                        <div class="text-danger mt-2">Corrija los errores y genere una nueva revisión antes de autorizar.</div>
                    @endif
                </div>
            @empty
                <div>No se detectaron jornadas docentes superiores a 44 horas.</div>
            @endforelse
        </div>
    </div>

    <form method="GET" action="{{ route('reemplazos.personal.import') }}" class="row g-2 mb-3">
        <input type="hidden" name="revision" value="{{ $revision->id }}">
        <div class="col-md-5"><label class="form-label" for="busqueda">Nombre o RUT sin puntos ni guion</label><input id="busqueda" name="q" class="form-control" value="{{ request('q') }}"></div>
        <div class="col-md-5"><label class="form-label" for="accion-filtro">Acción propuesta</label><select class="form-select" name="accion_filtro" id="accion-filtro">
            <option value="">Todas</option>
            @foreach ($etiquetas as $valor => $texto)
                <option value="{{ $valor }}" @selected(request('accion_filtro') === $valor)>{{ $texto }}</option>
            @endforeach
        </select></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">Filtrar</button></div>
    </form>
    <p class="small text-muted">{{ $filas->total() }} registros. Los casos ambiguos muestran los IDs candidatos; no se selecciona uno automáticamente. Corrija el archivo y vuelva a analizar cuando corresponda.</p>
    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead><tr><th>Fila Excel / funcionario</th><th>ID actual / acción propuesta</th><th>Actual → archivo</th><th>Observaciones y asignaciones</th></tr></thead>
            <tbody>
                @forelse ($filas as $fila)
                    <tr @class(['table-danger' => $fila->accion === 'error', 'table-warning' => in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar'])])>
                        <td>{{ $fila->fila_excel ?? 'Ausente' }}<br><strong>{{ $fila->rut }}</strong><br>{{ $fila->nombre }}</td>
                        <td>ID: {{ $fila->personal_id ?? 'Sin seleccionar' }}<br>{{ $etiquetas[$fila->accion] ?? $fila->accion }}
                            @if ($decisiones->has($fila->id))
                                <div class="text-success">Decisión: {{ $decisiones[$fila->id]->personal_id ? 'ID '.$decisiones[$fila->id]->personal_id : ($fila->fila_excel ? 'Nueva línea' : 'Confirmar baja') }} · usuario #{{ $decisiones[$fila->id]->resuelta_por }}</div>
                                <div class="small">{{ $decisiones[$fila->id]->justificacion }}</div>
                            @endif
                            @if ($fila->candidatos)
                                <details><summary>{{ count($fila->candidatos) }} candidatos</summary>
                                    @foreach ($fila->candidatos as $candidato)
                                        <div>ID {{ $candidato['id'] }} · RBD {{ $candidato['rbd'] ?? '—' }} · {{ $candidato['tipocontrato'] ?? '—' }} · {{ $candidato['jornada'] ?? '—' }} h · {{ $candidato['financiamiento'] ?? '—' }}</div>
                                    @endforeach
                                </details>
                            @endif
                            @if ($aplicacionDisponible && ! $revision->aplicada_at && ! $obsoleta && in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar']))
                                <details><summary>Resolver correspondencia</summary>
                                    <form method="POST" action="{{ route('reemplazos.personal.import.store') }}" class="mt-2">
                                        @csrf
                                        <input type="hidden" name="accion" value="resolver">
                                        <input type="hidden" name="revision" value="{{ $revision->id }}">
                                        <input type="hidden" name="fila" value="{{ $fila->id }}">
                                        <label class="form-label" for="decision-{{ $fila->id }}">Registro a conservar</label>
                                        <select id="decision-{{ $fila->id }}" name="personal_id" class="form-select form-select-sm" required>
                                            <option value="">Seleccione explícitamente</option>
                                            @foreach ($fila->candidatos as $candidato)
                                                <option value="{{ $candidato['id'] }}">ID {{ $candidato['id'] }} · RBD {{ $candidato['rbd'] ?? '—' }} · {{ $candidato['jornada'] ?? '—' }} h</option>
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
                                <div><strong>{{ $titulo }}:</strong> {{ $fila->anterior[$campo] ?? '—' }} → {{ $fila->datos[$campo] ?? ($campo === 'fecha_antiguedad' && $fila->fila_excel ? 'Conservar anterior' : '—') }}</div>
                            @endforeach
                            <details><summary>Otros datos comparados</summary>
                                @foreach (['nombre', 'fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'estatuto', 'escalafon', 'anio', 'mes', 'jornada_basica', 'jornada_media', 'bienios', 'tramo'] as $campo)
                                    <div>{{ $campo }}: {{ $fila->anterior[$campo] ?? '—' }} → {{ $fila->datos[$campo] ?? '—' }}</div>
                                @endforeach
                            </details>
                        </td>
                        <td>
                            @foreach ($fila->observaciones as $observacion)
                                <div>{{ $observacion }}</div>
                            @endforeach
                            @if ($fila->asignaciones)
                                <details><summary>{{ count($fila->asignaciones) }} asignaciones activas relacionadas (por RUT o ID)</summary>
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
@endsection
