@extends('layouts.app')

@section('content')
    @php
        $historial = $item->historial->sortByDesc('created_at')->values();
        $spanDias = ($item->fecha_desde && $item->fecha_hasta) ? $item->fecha_desde->diffInDays($item->fecha_hasta) : 0;
        $duracionLegible = collect([
            $item->dias . ' día(s)',
            $item->horas . ' hora(s)',
            $item->minutos . ' minuto(s)',
        ])->implode(', ');
        $estadoPeriodo = $spanDias === 0 ? 'Mismo día' : ($spanDias + 1) . ' días calendario';
        $informadoPor = $item->informadoPor?->nombre_completo ?: ($item->informadoPor?->email ?? '—');
        $actualizadoPor = $item->actualizadoPor?->nombre_completo ?: ($item->actualizadoPor?->email ?? '—');
        $funcionarioRut = \App\Support\Rut::format($item->funcionario_rut) ?? $item->funcionario_rut;
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Incumplimiento Laboral #{{ $item->id }}</h1>
            <p class="text-muted mb-0">Detalle ampliado del incumplimiento informado, con trazabilidad de cambios y constancia descargable.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('incumplimientos.constancia', $item) }}" class="btn btn-outline-dark">
                <i class="bi bi-file-earmark-pdf"></i> Descargar constancia
            </a>
            @if ($canEdit)
                <a href="{{ route('incumplimientos.edit', $item) }}" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            @endif
            @if (!empty($canDelete))
                <form method="POST" action="{{ route('incumplimientos.destroy', $item) }}" class="d-inline"
                    onsubmit="return confirm('¿Eliminar este incumplimiento laboral? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </form>
            @endif
            <a href="{{ route('incumplimientos.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Período</div>
                    <div class="fw-semibold">{{ cl_plain_date($item->fecha_desde) }} al {{ cl_plain_date($item->fecha_hasta) }}</div>
                    <div class="small text-muted">{{ $estadoPeriodo }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Duración informada</div>
                    <div class="fw-semibold">{{ $duracionLegible }}</div>
                    <div class="small text-muted">Valores enteros informados por el establecimiento.</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Informado por</div>
                    <div class="fw-semibold">{{ $informadoPor }}</div>
                    <div class="small text-muted">{{ cl_datetime($item->created_at) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Última actualización</div>
                    <div class="fw-semibold">{{ $actualizadoPor }}</div>
                    <div class="small text-muted">{{ cl_datetime($item->updated_at) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Funcionario</h2>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="text-muted small">RUT</div>
                            <div class="fw-semibold">{{ $funcionarioRut }}</div>
                        </div>
                        <div class="col-md-8">
                            <div class="text-muted small">Nombre</div>
                            <div class="fw-semibold">{{ $item->funcionario_nombre }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">RBD</div>
                            <div>{{ $item->funcionario_rbd ?: '—' }}</div>
                        </div>
                        <div class="col-md-8">
                            <div class="text-muted small">Establecimiento</div>
                            <div>{{ $item->establecimiento?->nombre_establecimiento ?: '—' }}</div>
                            <div class="small text-muted">{{ $item->establecimiento?->comuna ?: 'Sin comuna' }}</div>
                        </div>
                    </div>

                    <h2 class="h6 text-uppercase text-muted mb-3">Incumplimiento informado</h2>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="text-muted small">Fecha desde</div>
                            <div class="fw-semibold">{{ cl_plain_date($item->fecha_desde) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Fecha hasta</div>
                            <div class="fw-semibold">{{ cl_plain_date($item->fecha_hasta) }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Tramo calendario</div>
                            <div class="fw-semibold">{{ $estadoPeriodo }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Días</div>
                            <div class="fw-semibold">{{ $item->dias }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Horas</div>
                            <div class="fw-semibold">{{ $item->horas }}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Minutos</div>
                            <div class="fw-semibold">{{ $item->minutos }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <h2 class="h6 text-uppercase text-muted mb-0">Histórico de ediciones</h2>
                        <span class="badge text-bg-light">{{ $historial->count() }} evento(s)</span>
                    </div>

                    @forelse ($historial as $evento)
                        @php
                            $badgeClass = match ($evento->action) {
                                'created' => 'text-bg-success',
                                'updated' => 'text-bg-primary',
                                'deleted' => 'text-bg-danger',
                                default => 'text-bg-secondary',
                            };

                            $badgeLabel = match ($evento->action) {
                                'created' => 'Creación',
                                'updated' => 'Edición',
                                'deleted' => 'Eliminación',
                                default => ucfirst((string) $evento->action),
                            };

                            $actor = $evento->user?->nombre_completo ?: ($evento->user?->email ?? 'Sistema');
                            $changes = is_array($evento->changed_fields) ? $evento->changed_fields : [];
                        @endphp

                        <div class="border rounded-3 p-3 mb-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                    <span class="fw-semibold">{{ $actor }}</span>
                                </div>
                                <div class="small text-muted">{{ cl_datetime($evento->created_at) }}</div>
                            </div>

                            @if ($evento->action === 'created')
                                <div class="small text-muted">
                                    Se creó el registro con período {{ cl_plain_date(data_get($evento->new_values, 'fecha_desde')) }}
                                    al {{ cl_plain_date(data_get($evento->new_values, 'fecha_hasta')) }} y duración
                                    {{ data_get($evento->new_values, 'dias', 0) }} día(s),
                                    {{ data_get($evento->new_values, 'horas', 0) }} hora(s),
                                    {{ data_get($evento->new_values, 'minutos', 0) }} minuto(s).
                                </div>
                            @elseif ($evento->action === 'deleted')
                                <div class="small text-muted">
                                    Se eliminó el registro que correspondía al funcionario
                                    <span class="fw-semibold">{{ data_get($evento->old_values, 'funcionario_nombre', '—') }}</span>.
                                </div>
                            @elseif (!empty($changes))
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Campo</th>
                                                <th>Valor anterior</th>
                                                <th>Nuevo valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($changes as $change)
                                                <tr>
                                                    <td class="fw-semibold">{{ $change['label'] ?? $change['key'] ?? 'Campo' }}</td>
                                                    <td>{{ filled($change['from'] ?? null) ? $change['from'] : '—' }}</td>
                                                    <td>{{ filled($change['to'] ?? null) ? $change['to'] : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="small text-muted">No hay diferencias registradas para este evento.</div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">No hay historial registrado para este incumplimiento.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Auditoría</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-5">Informado por</dt>
                        <dd class="col-sm-7">{{ $informadoPor }}</dd>

                        <dt class="col-sm-5">Creado</dt>
                        <dd class="col-sm-7">{{ cl_datetime($item->created_at) }}</dd>

                        <dt class="col-sm-5">Actualizado por</dt>
                        <dd class="col-sm-7">{{ $actualizadoPor }}</dd>

                        <dt class="col-sm-5">Actualizado</dt>
                        <dd class="col-sm-7">{{ cl_datetime($item->updated_at) }}</dd>

                        <dt class="col-sm-5">ID interno</dt>
                        <dd class="col-sm-7">#{{ $item->id }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted mb-3">Constancia</h2>
                    <p class="text-muted small mb-3">
                        Descarga una constancia PDF del registro con identificación del funcionario, establecimiento,
                        período y datos de auditoría básicos.
                    </p>
                    <a href="{{ route('incumplimientos.constancia', $item) }}" class="btn btn-outline-dark w-100">
                        <i class="bi bi-file-earmark-pdf"></i> Descargar constancia PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
