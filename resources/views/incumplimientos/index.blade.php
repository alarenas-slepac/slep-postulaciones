@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-1">Incumplimiento Laboral</h1>
            <p class="text-muted mb-0">Registro y consulta de atrasos e inasistencias informadas sobre funcionarios del padrón.</p>
        </div>
        <div class="d-flex gap-2">
            @if ($isAdmin)
                <a href="{{ route('incumplimientos.export', request()->query()) }}" class="btn btn-outline-success">
                    <i class="bi bi-filetype-csv"></i> Exportar CSV
                </a>
            @endif
            @if ($isAdmin || $isFuncionarioEstab)
                <a href="{{ route('incumplimientos.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Nuevo incumplimiento
                </a>
            @endif
        </div>
    </div>

    @if ($isFuncionarioEstab)
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="bi bi-envelope-paper mt-1"></i>
            <div>
                Si necesitas modificar un incumplimiento ya informado, debes enviar un correo a
                <a href="mailto:karla.munoz@slepandaliencosta.gob.cl">karla.munoz@slepandaliencosta.gob.cl</a>
                indicando el cambio a realizar.
            </div>
        </div>
    @endif

    @if ($lockedWithoutEstablecimiento)
        <div class="alert alert-danger">Tu usuario no tiene establecimiento asociado, por lo que no es posible registrar ni consultar incumplimientos laborales.</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Registros</div>
                <div class="fs-4 fw-semibold">{{ number_format($summary['total'], 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Establecimientos</div>
                <div class="fs-4 fw-semibold">{{ number_format($summary['establecimientos'], 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-md-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Último registro</div>
                <div class="fw-semibold">{{ $summary['ultimo'] ? cl_datetime($summary['ultimo']) : '—' }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('incumplimientos.index') }}" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">RUT</label>
                    <input type="text" name="rut" value="{{ $filters['rut'] }}" class="form-control" placeholder="12.345.678-9">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" value="{{ $filters['nombre'] }}" class="form-control" placeholder="Funcionario">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Establecimiento</label>
                    <select name="establecimiento_id" class="form-select" {{ $isFuncionarioEstab ? 'disabled' : '' }}>
                        <option value="">Todos</option>
                        @foreach ($establecimientos as $establecimiento)
                            <option value="{{ $establecimiento->id }}" @selected((int) ($filters['establecimiento_id'] ?? 0) === (int) $establecimiento->id)>
                                {{ $establecimiento->nombre_establecimiento }}
                            </option>
                        @endforeach
                    </select>
                    @if ($isFuncionarioEstab && $forcedEstablecimiento)
                        <input type="hidden" name="establecimiento_id" value="{{ $forcedEstablecimiento->id }}">
                    @endif
                </div>
                <div class="col-md-2">
                    <label class="form-label">Mes</label>
                    <input type="month" name="mes" value="{{ $filters['mes'] }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] }}" class="form-control">
                </div>
                @if ($isAdmin || $isFuncionarioSlep)
                    <div class="col-md-2">
                        <label class="form-label">Registro desde</label>
                        <input type="date" name="fecha_registro_desde" value="{{ $filters['fecha_registro_desde'] }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Registro hasta</label>
                        <input type="date" name="fecha_registro_hasta" value="{{ $filters['fecha_registro_hasta'] }}" class="form-control">
                    </div>
                @endif
                <div class="col-md-2">
                    <label class="form-label">Registros</label>
                    <select name="per_page" class="form-select">
                        @foreach ([15,25,50,100] as $size)
                            <option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button>
                    <a href="{{ route('incumplimientos.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>RUT</th>
                            <th>Funcionario</th>
                            <th>Establecimiento</th>
                            <th>Fecha desde</th>
                            <th>Fecha hasta</th>
                            <th class="text-end">Días</th>
                            <th class="text-end">Horas</th>
                            <th class="text-end">Minutos</th>
                            <th>Informado por</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td class="text-nowrap">#{{ $item->id }}</td>
                                <td class="text-nowrap">{{ \App\Support\Rut::format($item->funcionario_rut) ?? $item->funcionario_rut }}</td>
                                <td>{{ $item->funcionario_nombre }}</td>
                                <td>
                                    <div>{{ $item->establecimiento?->nombre_establecimiento ?: '—' }}</div>
                                    <div class="small text-muted">{{ $item->establecimiento?->comuna ?: 'Sin comuna' }}</div>
                                </td>
                                <td class="text-nowrap">{{ cl_plain_date($item->fecha_desde) }}</td>
                                <td class="text-nowrap">{{ cl_plain_date($item->fecha_hasta) }}</td>
                                <td class="text-end">{{ $item->dias }}</td>
                                <td class="text-end">{{ $item->horas }}</td>
                                <td class="text-end">{{ $item->minutos }}</td>
                                <td>{{ $item->informadoPor?->nombre_completo ?: ($item->informadoPor?->email ?? '—') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('incumplimientos.show', $item) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <a href="{{ route('incumplimientos.constancia', $item) }}" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF
                                    </a>
                                    @if ($isAdmin)
                                        <a href="{{ route('incumplimientos.edit', $item) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Editar
                                        </a>
                                        <form method="POST" action="{{ route('incumplimientos.destroy', $item) }}" class="d-inline"
                                            onsubmit="return confirm('¿Eliminar este incumplimiento laboral? Esta acción no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No hay incumplimientos laborales registrados con los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $items->links() }}
            </div>
        </div>
    </div>
@endsection
