<div class="card mb-3" id="conflictos-asignaciones">
    <div class="card-header">Conflictos con asignaciones de Dotación</div>
    <div class="card-body">
        <p><strong>{{ $conflictos['bloqueantes'] }} asignaciones con bloqueos</strong> · {{ $conflictos['avisos'] }} con avisos sin bloqueo · {{ $conflictos['asignaciones_revisadas'] }} activas revisadas del año {{ $revision->anio ?? 'sin determinar' }}.</p>
        <p class="small">Diagnóstico de toda la carga y las decisiones actuales, independiente del filtro de filas.
            Las horas se contrastan por RUT y establecimiento, sin sumar Jornada Básica/Media nuevamente.
            La declaración tiene prioridad y no se suma al padrón; las exclusiones docentes se descuentan.</p>
        @if ($obsoleta || $revision->errores)
            <div class="alert alert-warning">Diagnóstico orientativo: corrija el archivo o genere una revisión vigente antes de continuar.</div>
        @endif
        <div class="alert alert-info">Para resolver: corrija la correspondencia de IDs cuando proceda; si las horas o vínculos son incorrectos, revíselos en Dotación con sus permisos habituales. Luego analice nuevamente el padrón completo. No se trasladan ni eliminan asignaciones automáticamente. Una autorización sobre 44 horas no levanta estos bloqueos.</div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead><tr><th>Asignación / funcionario</th><th>Establecimiento</th><th>Horas y cobertura propuesta</th><th>Motivo / revisión</th></tr></thead>
                <tbody>
                    @forelse ($conflictosPaginados as $item)
                        <tr class="{{ $item['bloqueante'] ? 'table-danger' : 'table-warning' }}">
                            <td>#{{ $item['asignacion_id'] }} · ID contractual {{ $item['personal_id'] ?? 'Sin ID (vínculo por RUT)' }}<br>
                                {{ $item['rut'] }}<br>{{ $item['tipo'] }} · {{ $item['asignatura'] }}
                                @if ($item['fila_excel'])<div>Fila Excel {{ $item['fila_excel'] }}</div>@endif
                                <a href="{{ route('reemplazos.personal.import', ['revision' => $revision->id, 'q' => $item['rut']]) }}">Ver filas del RUT</a>
                            </td>
                            <td>RBD {{ $item['rbd'] ?? '—' }} · {{ $item['establecimiento'] }}
                                @if ($item['rbd'])
                                    <div><a href="{{ route('admin.dotacion-establecimiento.show', ['establecimiento' => $item['establecimiento_id'], 'anio' => $revision->anio]) }}" target="_blank" rel="noopener">Revisar Dotación</a></div>
                                @endif
                            </td>
                            <td>Esta asignación: {{ $item['horas'] }} h<br>
                                Total RUT/establecimiento: {{ $item['total_asignadas'] }} h<br>
                                Cobertura: {{ $item['cobertura']['horas'] }} h<br>
                                Fuente: {{ $item['cobertura']['fuente'] }}<br>
                                Padrón propuesto: {{ $item['cobertura']['horas_archivo'] }} h · Declaradas: {{ $item['cobertura']['horas_declaradas'] ?? '—' }} h<br>
                                Exclusión docente: {{ $item['cobertura']['excluidas'] }} h
                            </td>
                            <td><strong>{{ $item['bloqueante'] ? 'Bloquea la aplicación' : 'Cambio informado: sin conflicto de cobertura detectado' }}</strong>
                                @foreach ($item['motivos'] as $motivo)<div>{{ $motivo }}</div>@endforeach
                                @foreach ($item['avisos'] as $aviso)<div>{{ $aviso }}</div>@endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No hay conflictos ni cambios contractuales vinculados que informar en esta página. Esto no habilita la aplicación definitiva.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="small text-muted">Una asignación se cuenta una sola vez aunque tenga varios motivos. El total de horas del RUT/establecimiento se repite como referencia: no debe sumarse entre filas.</p>
        {{ $conflictosPaginados->links() }}
    </div>
</div>
