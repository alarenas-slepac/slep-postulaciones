@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Planes de estudio</h1>
            <div class="text-muted small">Mantenedor administrativo de horas semanales/anuales por curso, modalidad y régimen JEC.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success" href="{{ route('admin.planes-estudio.template') }}">
                <i class="bi bi-file-earmark-excel"></i> Plantilla
            </a>
            <a class="btn btn-outline-primary" href="{{ route('admin.planes-estudio.import') }}">
                <i class="bi bi-upload"></i> Carga masiva
            </a>
            <a class="btn btn-primary" href="{{ route('admin.planes-estudio.create') }}">
                <i class="bi bi-plus-circle"></i> Nuevo plan
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Observaciones de carga</div>
            <ul class="mb-0 small">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" class="card card-body shadow-sm mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Plan, curso o decreto">
            </div>
            <div class="col-md-2">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="anio" value="{{ $anio }}" placeholder="2026">
            </div>
            <div class="col-md-3">
                <label class="form-label">Curso</label>
                <select class="form-select" name="curso_id">
                    <option value="">Todos</option>
                    @foreach ($cursos as $curso)
                        <option value="{{ $curso->id }}" @selected((string) $cursoId === (string) $curso->id)>{{ $curso->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Régimen</label>
                <select class="form-select" name="regimen_jec">
                    <option value="">Todos</option>
                    <option value="Con JEC" @selected($regimen === 'Con JEC')>Con JEC</option>
                    <option value="Sin JEC" @selected($regimen === 'Sin JEC')>Sin JEC</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="activo">
                    <option value="">Todos</option>
                    <option value="1" @selected($activo === '1')>Activos</option>
                    <option value="0" @selected($activo === '0')>Inactivos</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-danger" href="{{ route('admin.planes-estudio.index') }}">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Año</th>
                        <th>Curso</th>
                        <th>Plan</th>
                        <th>Régimen</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Libre disp.</th>
                        <th class="text-end">Total semanal</th>
                        <th>Estado</th>
                        <th class="text-end" style="width: 230px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($planes as $plan)
                        <tr>
                            <td>{{ $plan->anio }}</td>
                            <td class="fw-semibold">{{ $plan->curso?->nombre }}</td>
                            <td>
                                <div>{{ $plan->nombre_plan }}</div>
                                <div class="text-muted small">{{ $plan->nivel_educativo }}{{ $plan->modalidad ? ' · '.$plan->modalidad : '' }}</div>
                            </td>
                            <td>{{ $plan->regimen_jec }}</td>
                            <td class="text-end">{{ $plan->horas_semanales_subtotal !== null ? number_format((float) $plan->horas_semanales_subtotal, 1, ',', '.') : '—' }}</td>
                            <td class="text-end">{{ $plan->horas_semanales_libre_disposicion !== null ? number_format((float) $plan->horas_semanales_libre_disposicion, 1, ',', '.') : '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format((float) $plan->horas_semanales_total, 1, ',', '.') }}</td>
                            <td>
                                @if ($plan->activo)
                                    <span class="badge text-bg-success">Activo</span>
                                @else
                                    <span class="badge bg-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('admin.planes-estudio.show', $plan) }}">Ver</a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.planes-estudio.edit', $plan) }}">Editar</a>
                                    <form method="POST" action="{{ route('admin.planes-estudio.destroy', $plan) }}" onsubmit="return confirm('¿Eliminar este plan de estudio?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No hay planes de estudio registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $planes->links() }}
        </div>
    </div>
@endsection
