@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3>Ingreso y actualización individual del padrón</h3>
            <div class="text-muted">Período abierto: {{ $periodo ? sprintf('%02d/%04d', $periodo % 100, intdiv($periodo, 100)) : 'Sin padrón' }}</div>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('reemplazos.index') }}">Volver al padrón</a>
    </div>
    @if(session('status')) <div class="alert alert-success">{{ session('status') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">
            @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul></div>
    @endif
    <div class="alert alert-info">
        Se valida el dígito verificador y se consultan los datos internos del padrón; no es una consulta al Registro Civil.
        Los contratos regulares se actualizan conservando su ID. Cada nuevo reemplazo recibe un ID nuevo.
        No se eliminan documentos, bloqueos ni asignaciones. Esta operación no abre un nuevo mes ni reemplaza una carga completa.
    </div>
    <form method="GET" action="{{ route('reemplazos.individual.index') }}" class="card card-body mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label for="buscar_rut" class="form-label">RUT con dígito verificador</label>
                <input id="buscar_rut" name="rut" class="form-control" value="{{ $rut ?? old('rut') }}" maxlength="20" required>
            </div>
            <div class="col-md-6"><button class="btn btn-primary">Validar RUT y consultar registros</button></div>
        </div>
    </form>
    @if($rut)
        <div class="card card-body mb-3">
            <h5>Registros de {{ \App\Support\Rut::format($rut) }}</h5>
            <p>Seleccione el ID que desea modificar. Si hay varias líneas, las demás conservarán sus datos.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>ID / período</th><th>Nombre / establecimiento</th><th>Contrato / financiamiento</th><th>Horas</th><th>Duración</th><th>Acción</th></tr></thead>
                    <tbody>
                    @forelse($registros as $p)
                        <tr>
                            <td>{{ $p->id }} · {{ $p->anio }}/{{ $p->mes }}<br>{{ $p->vigente ? 'Vigente' : 'Inactivo' }}</td>
                            <td>{{ $p->nombre }}<br>RBD {{ $p->rbd }} · {{ $p->establecimiento?->nombre_establecimiento }}</td>
                            <td>{{ $p->tipocontrato }}<br>{{ $p->financiamiento }}</td>
                            <td>{{ $p->jornada }}</td>
                            <td>{{ $p->fecha_ingreso?->format('d/m/Y') ?? '—' }}<br>{{ $p->fecha_termino?->format('d/m/Y') ?? 'Sin término' }}</td>
                            <td>
                                @if($editables->contains('id', $p->id))
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('reemplazos.individual.index', ['rut' => $rut, 'personal_id' => $p->id]) }}">{{ $p->vigente ? 'Modificar' : 'Reactivar' }} ID {{ $p->id }}</a>
                                @else
                                    <span class="text-muted">Consulta; no se sobrescribe</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Este RUT no tiene registros. Puede ingresar su primer contrato.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($item)
                <a href="{{ route('reemplazos.individual.index', ['rut' => $rut]) }}">Volver al ingreso de nuevo contrato / reemplazo</a>
            @endif
        </div>
        @if(!$instalado || !$periodo)
            <div class="alert alert-warning">Para guardar se requiere instalar la migración de auditoría individual y disponer de un período de padrón cargado.</div>
        @else
            <div class="card card-body mb-3">
                <h5>{{ $item ? 'Actualizar ID '.$item->id.' sin cambiar sus referencias' : 'Ingresar nuevo registro' }}</h5>
                @if(!$item && $tieneRegular)
                    <div class="alert alert-warning">Este RUT ya tiene contratos regulares: solo puede crear un REEMPLAZO aquí. Para modificar un contrato regular, seleccione su ID arriba.</div>
                    @if($editables->isEmpty())
                        <p>Los contratos regulares son de períodos anteriores. Su incorporación al período abierto debe resolverse en la carga completa, conservando el ID y el historial mensual.</p>
                    @endif
                @endif
                @if($item && !$item->vigente)
                    <div class="alert alert-warning">Al guardar se reactivará este mismo ID en el período abierto. Los bloqueos del RUT se mantienen.</div>
                @endif
                @if($asignaciones->isNotEmpty())
                    <div class="alert alert-warning">
                        Este RUT / ID tiene {{ $asignaciones->count() }} asignaciones activas ({{ $asignaciones->sum('horas_contrato') }} h).
                        Revise abajo dónde están asignadas sus horas. Para el traslado puede seleccionar cuáles liberar de los establecimientos de origen.
                        Las no seleccionadas se conservan; no se crean asignaciones en destino.
                    </div>
                @endif
                <form method="POST" action="{{ route('reemplazos.individual.store') }}">
                    @csrf
                    <input type="hidden" name="rut" value="{{ $rut }}">
                    <input type="hidden" name="huella" value="{{ $huella }}">
                    <input type="hidden" name="periodo" value="{{ $periodo }}">
                    @if($item)<input type="hidden" name="personal_id" value="{{ $item->id }}">@endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre completo</label>
                            <input id="nombre" name="nombre" class="form-control" maxlength="255" value="{{ old('nombre', $item?->nombre ?? $registros->last()?->nombre) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="establecimiento_id" class="form-label">Establecimiento</label>
                            <select id="establecimiento_id" name="establecimiento_id" class="form-select" required>
                                <option value="">Seleccione</option>
                                @foreach($establecimientos as $est)
                                    <option value="{{ $est->id }}" @selected((int) old('establecimiento_id', $item?->establecimiento_id) === $est->id)>{{ $est->nombre_establecimiento }} · RBD {{ $est->rbd }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="tipocontrato" class="form-label">Tipo de contrato</label>
                            <select id="tipocontrato" name="tipocontrato" class="form-select" required>
                                <option value="">Seleccione</option>
                                @foreach(\App\Services\Padron\PadronIndividualService::CONTRATOS as $tipo)
                                    @if(($item && $tipo !== 'REEMPLAZO') || (!$item && (!$tieneRegular || $tipo === 'REEMPLAZO')))
                                        <option value="{{ $tipo }}" @selected(old('tipocontrato', $item?->tipocontrato ?? ($tieneRegular ? 'REEMPLAZO' : '')) === $tipo)>{{ $tipo }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <div class="form-text">Si el nombre antiguo incluía SEP/PIE, elija el tipo correcto y mantenga el financiamiento separado.</div>
                        </div>
                        @foreach(['financiamiento' => 'Financiamiento', 'estatuto' => 'Estatuto (DOCENTE / AAEE)', 'escalafon' => 'Escalafón'] as $campo => $etiqueta)
                            <div class="col-md-4">
                                <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                                <input id="{{ $campo }}" name="{{ $campo }}" class="form-control" maxlength="255" value="{{ old($campo, $item?->$campo) }}" required>
                            </div>
                        @endforeach
                        @foreach(['fecha_nacimiento' => 'Fecha de nacimiento', 'fecha_ingreso' => 'Fecha de inicio', 'fecha_termino' => 'Fecha de término', 'fecha_antiguedad' => 'Fecha de antigüedad'] as $campo => $etiqueta)
                            <div class="col-md-3">
                                <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                                <input type="date" id="{{ $campo }}" name="{{ $campo }}" class="form-control" value="{{ old($campo, $item?->$campo?->format('Y-m-d')) }}" @required($campo === 'fecha_ingreso')>
                            </div>
                        @endforeach
                        @foreach(['jornada' => 'Jornada total', 'jornada_basica' => 'Jornada básica', 'jornada_media' => 'Jornada media', 'bienios' => 'Bienios'] as $campo => $etiqueta)
                            <div class="col-md-3">
                                <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                                <input type="number" id="{{ $campo }}" name="{{ $campo }}" class="form-control" min="{{ $campo === 'jornada' ? 1 : 0 }}" max="{{ $campo === 'bienios' ? 50 : 168 }}" step="1" value="{{ old($campo, $item?->$campo ?? ($campo === 'jornada' ? '' : 0)) }}" @required($campo !== 'bienios')>
                            </div>
                        @endforeach
                        <div class="col-md-6">
                            <label for="tramo" class="form-label">Tramo</label>
                            <input id="tramo" name="tramo" class="form-control" maxlength="100" value="{{ old('tramo', $item?->tramo) }}" list="tramos">
                            <datalist id="tramos"><option>Acceso</option><option>Inicial</option><option>Temprano</option><option>Avanzado</option><option>Experto 1</option><option>Experto 2</option></datalist>
                        </div>
                        <div class="col-12">
                            <label for="justificacion" class="form-label">Justificación del ingreso o modificación</label>
                            <textarea id="justificacion" name="justificacion" class="form-control" minlength="10" maxlength="2000" required>{{ old('justificacion') }}</textarea>
                        </div>
                        <div class="col-12">
                            <details @if($errors->has('autorizar_exceso') || old('autorizar_exceso')) open @endif>
                                <summary>Autorización excepcional: más de 44 horas docentes</summary>
                                <p class="small mt-2">Se controlan contratos docentes simultáneos del RUT entre establecimientos. Los contratos consecutivos sin superposición no se suman entre sí.</p>
                                <label><input type="checkbox" name="autorizar_exceso" value="1" @checked(old('autorizar_exceso'))> Autorizo expresamente el exceso de 44 horas</label>
                                <label for="justificacion_exceso" class="form-label d-block mt-2">Justificación de la excepción</label>
                                <textarea id="justificacion_exceso" name="justificacion_exceso" class="form-control" maxlength="2000">{{ old('justificacion_exceso') }}</textarea>
                            </details>
                        </div>
                        @if($asignaciones->isNotEmpty())
                            <div class="col-12">
                                <h5>Asignaciones actuales por establecimiento — año {{ $item->anio }}</h5>
                                <p>Marque solo las asignaciones que deben dejar de cubrir horas por el traslado. Seleccione arriba el establecimiento de destino.
                                    No se permite liberar asignaciones de ese destino. También puede resolver asignaciones de un origen anterior cuando el traslado contractual ya está registrado.</p>
                                @foreach($asignaciones->groupBy('establecimiento_id') as $establecimientoId => $grupoAsignaciones)
                                    @php($origenAsignaciones = $establecimientos->firstWhere('id', (int) $establecimientoId))
                                    <div class="border rounded p-3 mb-3">
                                        <h6>RBD {{ $origenAsignaciones?->rbd ?? '—' }} · {{ $origenAsignaciones?->nombre_establecimiento ?? 'Establecimiento ID '.$establecimientoId }}</h6>
                                        <p>{{ $grupoAsignaciones->count() }} asignaciones · {{ number_format($grupoAsignaciones->sum('horas_contrato'), 2, ',', '.') }} h de contrato</p>
                                        @if($origenAsignaciones)
                                            <a class="btn btn-sm btn-outline-secondary mb-2" target="_blank" rel="noopener" href="{{ route('admin.dotacion-establecimiento.show', ['establecimiento' => $establecimientoId, 'anio' => $item->anio, 'tab' => 'asignacion']) }}">Revisar Dotación en otra pestaña</a>
                                        @endif
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle">
                                                <thead><tr><th>Liberar</th><th>ID</th><th>Tipo / detalle</th><th>Curso / necesidad</th><th>Vínculo contractual</th><th>Horas contrato</th></tr></thead>
                                                <tbody>
                                                @foreach($grupoAsignaciones as $asignacion)
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" name="liberar_asignaciones[]" value="{{ $asignacion->id }}" aria-label="Liberar asignación {{ $asignacion->id }}" @checked(in_array((string) $asignacion->id, array_map('strval', (array) old('liberar_asignaciones', [])), true))>
                                                            <input type="hidden" name="huellas_asignaciones[{{ $asignacion->id }}]" value="{{ app(\App\Services\Padron\PadronIndividualAsignacionesService::class)->huella($asignacion) }}">
                                                        </td>
                                                        <td>#{{ $asignacion->id }}</td>
                                                        <td>{{ $asignacion->tipo_asignacion ?? 'Asignación' }}<br>{{ $asignacion->asignatura_nombre ?? $asignacion->subtipo_asignacion ?? '—' }}</td>
                                                        <td>{{ isset($asignacion->establecimiento_curso_id) ? 'Curso ID '.$asignacion->establecimiento_curso_id : 'Sin curso' }}<br>{{ $asignacion->necesidad_key ?? '—' }}</td>
                                                        <td>{{ $asignacion->reemplazos_personal_id ? 'ID '.$asignacion->reemplazos_personal_id : 'Por RUT' }}</td>
                                                        <td>{{ number_format($asignacion->horas_contrato, 2, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                                <div class="alert alert-warning">
                                    Las asignaciones seleccionadas se inactivarán únicamente al guardar este formulario. Sus horas y referencias quedarán en el historial,
                                    pero dejarán de cubrir necesidades. Si falla el traslado o la liberación, no se guardará ninguno de los cambios.
                                </div>
                                <label><input type="checkbox" name="confirmar_liberacion" value="1" @checked(old('confirmar_liberacion'))> Confirmo la liberación de las asignaciones seleccionadas por traslado</label>
                                <label for="justificacion_liberacion" class="form-label d-block mt-2">Justificación de la liberación (obligatoria si selecciona asignaciones)</label>
                                <textarea id="justificacion_liberacion" name="justificacion_liberacion" class="form-control mb-3" maxlength="2000">{{ old('justificacion_liberacion') }}</textarea>
                                <label><input type="checkbox" name="confirmar_asignaciones" value="1" @checked(old('confirmar_asignaciones'))> Si conservo asignaciones al trasladar o reducir horas, confirmo que revisaré las pendientes en Dotación.</label>
                            </div>
                        @endif
                        <div class="col-12"><button class="btn btn-primary">{{ $item ? 'Guardar cambios conservando ID '.$item->id : 'Crear nuevo registro' }}</button></div>
                    </div>
                </form>
            </div>
        @endif
        @if($historial->isNotEmpty())
            <details class="card card-body"><summary>Últimos 10 cambios individuales de este RUT</summary>
                <ul class="mt-2 mb-0">@foreach($historial as $cambio)
                    <li>
                        {{ $cambio->created_at }} · ID {{ $cambio->personal_id }} · {{ $cambio->accion }} · Usuario #{{ $cambio->usuario_id }}: {{ $cambio->justificacion }}
                        @php($controlCambio = json_decode($cambio->controles, true) ?? [])
                        @if(!empty($controlCambio['liberaciones']))
                            <div>Liberadas: {{ count($controlCambio['liberaciones']) }} asignaciones · {{ $controlCambio['justificacion_liberacion'] ?? '' }}</div>
                            <ul>@foreach($controlCambio['liberaciones'] as $liberacion)
                                <li>#{{ $liberacion['antes']['id'] }} · Establecimiento ID {{ $liberacion['antes']['establecimiento_id'] }} · {{ $liberacion['antes']['horas_contrato'] }} h · Inactiva</li>
                            @endforeach</ul>
                        @endif
                    </li>
                @endforeach</ul>
            </details>
        @endif
    @endif
@endsection
