@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Cursos por establecimiento</h1>
            <div class="text-muted small">Matrícula por curso/sección, régimen JEC y plan de estudio asociado.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success" href="{{ route('admin.establecimiento-cursos.template') }}">
                <i class="bi bi-file-earmark-excel"></i> Plantilla
            </a>
            <a class="btn btn-outline-primary" href="{{ route('admin.establecimiento-cursos.import') }}">
                <i class="bi bi-upload"></i> Carga masiva
            </a>
            <a class="btn btn-primary" href="{{ route('admin.establecimiento-cursos.create') }}">
                <i class="bi bi-plus-circle"></i> Nuevo registro
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

    <div class="alert alert-info small">
        Esta bandeja muestra directamente los registros activos de <strong>establecimiento_cursos</strong> asociados por ID a <strong>establecimientos</strong> y <strong>cursos</strong>. Si un registro existe en la tabla con establecimiento_id y curso_id, debe visualizarse aquí con sus datos reales.
    </div>

    <form method="GET" class="card card-body shadow-sm mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="RBD, establecimiento o curso">
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
                <label class="form-label">Régimen</label>
                <select class="form-select" name="regimen_jec">
                    <option value="">Todos</option>
                    <option value="Con JEC" @selected($regimen === 'Con JEC')>Con JEC</option>
                    <option value="Sin JEC" @selected($regimen === 'Sin JEC')>Sin JEC</option>
                    <option value="No aplica" @selected($regimen === 'No aplica')>No aplica</option>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-outline-danger" href="{{ route('admin.establecimiento-cursos.index') }}">Limpiar</a>
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
                        <th>Año</th>
                        <th>JEC</th>
                        <th class="text-end">Matrícula</th>
                        <th>Plan asociado</th>
                        <th class="text-end" style="width: 230px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $rbd = $item->rbd ?: $item->establecimiento_rbd;
                            $establecimientoNombre = $item->establecimiento_nombre ?: 'Sin establecimiento asociado';
                            $establecimientoComuna = $item->establecimiento_comuna ?: 'Sin comuna registrada';
                            $cursoNombre = $item->curso_nombre ?: 'Sin curso base asociado';
                            $letra = trim((string) ($item->letra ?? ''));
                            $seccion = trim((string) ($item->nombre_seccion ?? '')) ?: trim($cursoNombre.' '.$letra);
                            $planNombre = $item->plan_nombre ?: null;
                            $planHoras = $item->plan_horas_semanales_total;
                        @endphp
                        <tr>
                            <td>{{ $rbd ?: '—' }}</td>
                            <td>
                                <div class="fw-semibold">{{ $establecimientoNombre }}</div>
                                <div class="text-muted small">{{ $establecimientoComuna }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $seccion ?: 'Sección sin nombre' }}</div>
                                <div class="text-muted small">Base: {{ $cursoNombre }}</div>
                            </td>
                            <td>{{ $item->anio ?: '—' }}</td>
                            <td>{{ $item->regimen_jec ?: '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format((int) ($item->matricula ?? 0), 0, ',', '.') }}</td>
                            <td>
                                @if ($planNombre)
                                    <div>{{ $planNombre }}</div>
                                    <div class="text-muted small">{{ $item->plan_regimen_jec ?: 'Régimen no indicado' }} @if($planHoras) · {{ $planHoras }} h @endif</div>
                                @else
                                    <span class="badge text-bg-warning">Sin plan asociado</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('admin.establecimiento-cursos.show', $item->id) }}">Ver</a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.establecimiento-cursos.edit', $item->id) }}">Editar</a>
                                    <form method="POST" action="{{ route('admin.establecimiento-cursos.destroy', $item->id) }}" onsubmit="return confirm('¿Eliminar este curso/sección del establecimiento?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay cursos por establecimiento registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($items, 'links'))
            <div class="card-body">
                {{ $items->links() }}
            </div>
        @endif
    </div>
@endsection
