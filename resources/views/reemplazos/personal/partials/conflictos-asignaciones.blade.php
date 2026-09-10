<div class="card mb-3" id="conflictos-asignaciones">
    <div class="card-header">Conflictos con asignaciones de Dotación</div>
    <div class="card-body">
        <p><strong>{{ $conflictos['grupos_bloqueantes'] }} casos con bloqueos</strong> · {{ $conflictos['grupos_avisos'] }} casos con avisos sin bloqueo.
            Cada caso corresponde a un RUT y establecimiento.</p>
        <p class="small">{{ $conflictos['asignaciones_revisadas'] }} asignaciones activas revisadas del año {{ $revision->anio ?? 'sin determinar' }},
            agrupadas en {{ $conflictos['grupos_revisados'] }} casos.
            {{ $conflictos['bloqueantes'] }} asignaciones con bloqueos · {{ $conflictos['avisos'] }} con avisos sin bloqueo.</p>
        <p class="small">Diagnóstico de toda la carga y las decisiones actuales, independiente del filtro de filas.
            Las horas se contrastan por RUT y establecimiento, sin sumar Jornada Básica/Media nuevamente.
            La declaración tiene prioridad y no se suma al padrón; las exclusiones docentes se descuentan.
            Un exceso preexistente que la carga no aumenta se informa como aviso. Los excesos nuevos o agravados y los problemas de vínculos mantienen el bloqueo.</p>
        @if ($obsoleta || $revision->errores)
            <div class="alert alert-warning">Diagnóstico orientativo: corrija el archivo o genere una revisión vigente antes de continuar.</div>
        @endif
        <div class="alert alert-info">Para resolver: corrija la correspondencia de IDs cuando proceda; si las horas o vínculos son incorrectos, revíselos en Dotación con sus permisos habituales. Luego recargue esta misma revisión para recalcular los conflictos, conservando las decisiones y autorizaciones registradas. Para un retiro completo puede confirmar la baja con liberación diferida: las asignaciones se inactivan solo al aplicar el padrón, sin borrar historial. Una autorización sobre 44 horas no levanta estos bloqueos.</div>
        @if (! ($liberacionInstalada ?? false))
            <div class="alert alert-warning">La confirmación de bajas con liberación requiere instalar la migración correspondiente con PHP 8.3. Esto no habilita la aplicación definitiva.</div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead><tr><th>Funcionario / asignaciones</th><th>Establecimiento</th><th>Cobertura actual y propuesta</th><th>Motivo / revisión</th></tr></thead>
                <tbody>
                    @forelse ($conflictosPaginados as $item)
                        <tr class="{{ $item['bloqueante'] ? 'table-danger' : 'table-warning' }}">
                            <td>{{ $item['rut'] ?: 'RUT sin identificar' }}<br>
                                @if ($item['rut'])
                                    <a data-padron-filas-link href="{{ route('reemplazos.personal.import', ['revision' => $revision->id, 'q' => $item['rut'], 'conflictos_page' => $conflictosPaginados->currentPage(), 'caso_rut' => $item['rut'], 'caso_establecimiento' => $item['establecimiento_id']]) }}#filas-padron">Ver filas del RUT</a>
                                @endif
                                <details class="mt-2">
                                    <summary>Ver {{ count($item['asignaciones']) }} asignaciones</summary>
                                    @foreach ($item['asignaciones'] as $asignacion)
                                        <div class="border-top mt-2 pt-2">
                                            <strong>#{{ $asignacion['asignacion_id'] }} · {{ $asignacion['horas'] }} h</strong><br>
                                            ID contractual: {{ $asignacion['personal_id'] ?? 'Sin ID (vínculo por RUT)' }}<br>
                                            {{ $asignacion['tipo'] }} · {{ $asignacion['asignatura'] }}
                                            @if ($asignacion['fila_excel'])<div>Fila Excel {{ $asignacion['fila_excel'] }}</div>@endif
                                            @foreach ($asignacion['motivos'] as $codigo => $motivo)
                                                @if ($codigo !== 'cobertura_insuficiente')<div>{{ $motivo }}</div>@endif
                                            @endforeach
                                        </div>
                                    @endforeach
                                </details>
                            </td>
                            <td>RBD {{ $item['rbd'] ?? '—' }} · {{ $item['establecimiento'] }}
                                @if ($item['rbd'])
                                    <div><a href="{{ route('admin.dotacion-establecimiento.show', ['establecimiento' => $item['establecimiento_id'], 'anio' => $revision->anio]) }}" target="_blank" rel="noopener">Revisar Dotación</a></div>
                                @endif
                            </td>
                            <td><strong>Total asignado: {{ $item['total_asignadas'] }} h</strong><br>
                                Cobertura actual: {{ $item['cobertura_actual']['horas'] }} h<br>
                                <span class="small">{{ $item['cobertura_actual']['fuente'] }} · Padrón actual: {{ $item['cobertura_actual']['horas_archivo'] }} h</span><br>
                                Cobertura propuesta: {{ $item['cobertura']['horas'] }} h<br>
                                <span class="small">{{ $item['cobertura']['fuente'] }} · Padrón propuesto: {{ $item['cobertura']['horas_archivo'] }} h</span><br>
                                Declaradas: {{ $item['cobertura']['horas_declaradas'] ?? '—' }} h · Exclusión docente: {{ $item['cobertura']['excluidas'] }} h<br>
                                Exceso actual: {{ $item['comparacion']['exceso_actual'] === null ? 'No comparable' : $item['comparacion']['exceso_actual'].' h' }}<br>
                                Exceso propuesto: {{ $item['comparacion']['exceso_propuesto'] }} h
                            </td>
                            <td><strong>{{ $item['bloqueante'] ? 'Bloquea la aplicación' : 'Aviso: no bloquea por este caso' }}</strong>
                                @if ($item['correspondencias_pendientes'] > 0)
                                    <div class="fw-semibold">Correspondencias pendientes del RUT: {{ $item['correspondencias_pendientes'] }}</div>
                                    <div class="small">Cobertura propuesta provisional. El caso permanece pendiente hasta resolver todas las filas del RUT, incluidas las ausencias por revisar. Después se revalidan horas y vínculos; resolver las correspondencias no elimina otros conflictos.</div>
                                @endif
                                @foreach ($item['motivos'] as $motivo)<div>{{ $motivo }}</div>@endforeach
                                @foreach ($item['avisos'] as $aviso)<div>{{ $aviso }}</div>@endforeach
                                @if ($item['baja_asignaciones'] ?? null)
                                    @include('reemplazos.personal.partials.baja-asignaciones', ['baja' => $item['baja_asignaciones']])
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No hay conflictos ni cambios contractuales vinculados que informar en esta página. Esto no habilita la aplicación definitiva.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="small text-muted">Cada RUT/establecimiento se muestra una sola vez, con el detalle desplegable de todas sus asignaciones.
            Si falta el RUT, cada asignación se informa por separado para no mezclar identidades.
            Un aviso no habilita por sí solo la aplicación definitiva del padrón.</p>
        <p>Caso {{ $conflictosPaginados->total() ? $conflictosPaginados->currentPage() : 0 }} de {{ $conflictosPaginados->total() }}. Se muestra un caso a la vez; al resolver sus bloqueos se muestra el siguiente pendiente.</p>
        {{ $conflictosPaginados->onEachSide(1)->links() }}
    </div>
</div>
