@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Solicitudes de reemplazo</h1>
            <div class="text-muted">
                {{ $establecimiento->rbd ?? '' }} -
                {{ $establecimiento->nombre_establecimiento ?? ($establecimiento->nombre ?? '') }}
            </div>
        </div>

        <a href="{{ route('funcionario.solicitudes-reemplazo.create') }}" class="btn btn-primary">
            Solicitar reemplazo
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="GET"
                action="{{ route('funcionario.solicitudes-reemplazo.index') }}">
                <div class="col-md-4">
                    <label class="form-label">Vigencia</label>
                    <select name="vigencia" class="form-select">
                        <option value="activos" @selected($vigencia === 'activos')>Activos a la fecha</option>
                        <option value="caducados" @selected($vigencia === 'caducados')>Caducados</option>
                        <option value="todos" @selected($vigencia === 'todos')>Todas</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="" @selected($estado === '')>Todos</option>
                        <option value="pendiente_uatp" @selected($estado === 'pendiente_uatp')>Pendiente UATP</option>
                        <option value="pendiente_validacion" @selected($estado === 'pendiente_validacion')>Pendiente de Validación</option>
                        <option value="pendiente_gdp" @selected($estado === 'pendiente_gdp')>Pendiente GDP</option>
                        <option value="rechazada_uatp" @selected($estado === 'rechazada_uatp')>Rechazada UATP</option>
                        <option value="rechazada_plani" @selected($estado === 'rechazada_plani')>Rechazada Planificación</option>
                        <option value="derivada_slep" @selected($estado === 'derivada_slep')>Derivada a SLEP</option>
                        <option value="aceptada" @selected($estado === 'aceptada')>Aceptada (OT creada)</option>
                        <option value="cerrado" @selected($estado === 'cerrado')>Cerrado</option>
                        <option value="aprobada" @selected($estado === 'aprobada')>Aprobada (legacy)</option>
                        <option value="rechazada" @selected($estado === 'rechazada')>Rechazada (legacy)</option>
                        <option value="anulada" @selected($estado === 'anulada')>Anulada</option>
                    </select>
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-outline-primary" type="submit">Filtrar</button>
                    <a class="btn btn-outline-secondary"
                        href="{{ route('funcionario.solicitudes-reemplazo.index') }}">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            @php
                // ✅ Mantener los mismos labels/badges que en "Gestión de solicitudes"
                $estadoLabel = function ($e) {
                    return match ($e) {
                        'pendiente_uatp' => 'Pendiente UATP',
                        'pendiente_validacion' => 'Pendiente de Validación',
                        'pendiente_gdp' => 'Pendiente GDP',
                        'rechazada_uatp' => 'Rechazada UATP',
                        'rechazada_plani' => 'Rechazada Planificación',
                        'derivada_slep' => 'Derivada a SLEP',
                        'aceptada' => 'Aceptada (OT creada)',
                        'cerrado' => 'Cerrado',
                        default => ucfirst(str_replace('_', ' ', (string) $e)),
                    };
                };

                $estadoBadge = function ($e) {
                    return match ($e) {
                        'pendiente_validacion' => 'text-bg-primary',
                        'pendiente_gdp' => 'text-bg-warning',
                        'rechazada_uatp' => 'text-bg-danger',
                        'rechazada_plani' => 'text-bg-danger',
                        'derivada_slep' => 'text-bg-info',
                        'aceptada' => 'text-bg-success',
                        'cerrado' => 'text-bg-dark',
                        default => 'text-bg-secondary',
                    };
                };
            @endphp

            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha solicitud</th>
                        <th>Funcionario titular</th>
                        <th>Área</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($solicitudes as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s->numero_solicitud }}</td>
                            <td>{{ cl_datetime($s->created_at, 'd/m/Y H:i') }}</td>
                            <td>
                                {{ optional($s->funcionarioTitular)->rut }}<br>
                                <span class="text-muted">{{ optional($s->funcionarioTitular)->nombre }}</span>
                            </td>
                            <td>
                                @if ($s->areaDesempeno)
                                    <div class="text-muted small"> {{ $s->areaDesempeno->nombre }}</div>
                                @endif
                            </td>

                            <td>
                                {{ $s->fecha_inicio->format('d/m/Y') }} - {{ $s->fecha_termino->format('d/m/Y') }}
                            </td>
                            <td>
                                <span class="badge {{ $estadoBadge($s->estado) }}">{{ $estadoLabel($s->estado) }}</span>

                                @if ($s->derivadaA)
                                    <div class="text-muted small">Derivada a: {{ $s->derivadaA->nombre_completo }}@if(!empty($s->derivadaA->rut)) ({{ $s->derivadaA->rut }})@endif</div>
                                @endif

                                @if ($s->estado === 'rechazada_uatp' && !empty($s->motivo_rechazo))
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($s->motivo_rechazo, 120) }}</div>
                                @endif

                                @if ($s->estado === 'rechazada_plani' && !empty($s->plani_motivo_rechazo))
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($s->plani_motivo_rechazo, 120) }}</div>
                                @endif

                                @if (!empty($s->observacion_slep))
                                    <div class="text-muted small">Obs. SLEP: {{ \Illuminate\Support\Str::limit($s->observacion_slep, 120) }}</div>
                                @endif
                            </td>

                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('gestion.solicitudes-reemplazo.show', ['solicitud' => $s->id, 'return_to' => request()->fullUrl()]) }}">
                                    Ver
                                </a>

                                {{-- ✅ Contrato de trabajo (DOCX): reemplaza "ver OT" por descargar contrato --}}
                                @if (!empty($s->contrato_trabajo_docx_path))
                                    @php
                                        $contratoExt = strtolower(pathinfo($s->contrato_trabajo_docx_path, PATHINFO_EXTENSION));
                                        $contratoExt = in_array($contratoExt, ['docx', 'pdf'], true) ? $contratoExt : 'docx';
                                    @endphp
                                    <a class="btn btn-sm btn-outline-success-dark"
                                        href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo.download', $s) }}"
                                        title="Descargar Contrato">
                                        <i class="bi {{ $contratoExt === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-word' }}"></i>
                                    </a>
                                @endif

                                {{-- ✅ Orden de trabajo (PDF): mantener descarga, pero con verde más oscuro --}}
                                @if (!empty($s->orden_trabajo_pdf_path))
                                    <a class="btn btn-sm btn-outline-success-dark"
                                        href="{{ route('gestion.solicitudes-reemplazo.ot.download', $s) }}"
                                        title="Descargar Orden de Trabajo">
                                        <i class="bi bi-download"></i>
                                    </a>
                                @endif

                                @if (!empty($s->contrato_trabajo_firmado_pdf_path))
                                    <a class="btn btn-sm btn-outline-danger"
                                        href="{{ route('gestion.solicitudes-reemplazo.contrato-trabajo-firmado.download', $s) }}"
                                        title="Descargar contrato firmado">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                @endif

                                @if (in_array($s->estado, ['pendiente_uatp', 'rechazada_uatp', 'rechazada_plani']))
                                    <a class="btn btn-sm btn-outline-secondary"
                                        href="{{ route('funcionario.solicitudes-reemplazo.edit', $s) }}">
                                        Editar
                                    </a>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted">Sin solicitudes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $solicitudes->links() }}
        </div>
    </div>
@endsection
