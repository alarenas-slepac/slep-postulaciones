@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Configuración de planes por establecimiento</h1>
            <div class="text-muted small">Permite completar bloques flexibles del plan oficial asociado a cada curso/sección.</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info small">
        La configuración se crea desde los cursos/secciones que ya tienen plan asociado. Los bloques fijos se muestran como referencia; sólo se completan los bloques marcados como selección del establecimiento o libre disposición.
    </div>

    <form method="GET" class="card card-body shadow-sm mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="RBD, establecimiento, curso o plan">
            </div>
            <div class="col-md-2">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="anio" value="{{ $anio }}" placeholder="2026">
            </div>
            <div class="col-md-3">
                <label class="form-label">Establecimiento</label>
                <select class="form-select" name="establecimiento_id">
                    <option value="">Todos</option>
                    @foreach ($establecimientos as $comuna => $itemsEstablecimientos)
                        <optgroup label="{{ $comuna }}">
                            @foreach ($itemsEstablecimientos as $establecimiento)
                                <option value="{{ $establecimiento->id }}" @selected((string) $establecimientoId === (string) $establecimiento->id)>
                                    {{ $establecimiento->rbd }} — {{ $establecimiento->nombre_establecimiento }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Curso</label>
                <select class="form-select" name="curso_id">
                    <option value="">Todos</option>
                    @foreach ($cursos as $curso)
                        <option value="{{ $curso->id }}" @selected((string) $cursoId === (string) $curso->id)>{{ $curso->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="estado">
                    <option value="">Todos</option>
                    <option value="sin_configurar" @selected($estado === 'sin_configurar')>Sin configurar</option>
                    @foreach ($estados as $value => $label)
                        <option value="{{ $value }}" @selected($estado === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-planes.index') }}">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>RBD</th>
                        <th>Establecimiento</th>
                        <th>Curso/sección</th>
                        <th class="text-end">Matrícula</th>
                        <th>Plan asociado</th>
                        <th>Estado configuración</th>
                        <th class="text-end" style="width: 220px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $estadoConfig = $item->configuracion_estado ?: 'sin_configurar';
                            $estadoLabel = $estadoConfig === 'sin_configurar' ? 'Sin configurar' : ($estados[$estadoConfig] ?? ucfirst($estadoConfig));
                            $badge = match ($estadoConfig) {
                                'enviado' => 'text-bg-info',
                                'observado' => 'text-bg-warning',
                                'aprobado' => 'text-bg-success',
                                'cerrado' => 'text-bg-secondary',
                                'sin_configurar' => 'text-bg-light border',
                                default => 'text-bg-primary',
                            };
                        @endphp
                        <tr>
                            <td>{{ $item->rbd ?: $item->establecimiento_rbd }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->establecimiento_nombre }}</div>
                                <div class="text-muted small">{{ $item->establecimiento_comuna ?: 'Sin comuna' }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $item->nombre_seccion ?: trim($item->curso_nombre.' '.($item->letra ?? '')) }}</div>
                                <div class="text-muted small">{{ $item->curso_nombre }} · {{ $item->regimen_jec }} · {{ $item->anio }}</div>
                            </td>
                            <td class="text-end fw-semibold">{{ number_format((int) $item->matricula, 0, ',', '.') }}</td>
                            <td>
                                <div>{{ $item->plan_nombre }}</div>
                                <div class="text-muted small">{{ $item->plan_horas_semanales_total }} h semanales</div>
                            </td>
                            <td><span class="badge {{ $badge }}">{{ $estadoLabel }}</span></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    @if ($item->establecimiento_plan_id)
                                        <a class="btn btn-sm btn-outline-info" href="{{ route('admin.establecimiento-planes.show', $item->establecimiento_plan_id) }}">Ver</a>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.establecimiento-planes.edit', $item->establecimiento_plan_id) }}">Editar</a>
                                    @else
                                        <a class="btn btn-sm btn-primary" href="{{ route('admin.establecimiento-planes.configure', $item->establecimiento_curso_id) }}">Configurar</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No hay cursos con plan asociado para configurar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $items->links() }}
        </div>
    </div>
@endsection
