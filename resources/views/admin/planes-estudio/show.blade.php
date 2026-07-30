@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Plan de estudio</h1>
            <div class="text-muted small">{{ $plan->curso?->nombre }} · {{ $plan->anio }} · {{ $plan->regimen_jec }}</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.planes-estudio.edit', $plan) }}">Editar</a>
            <a class="btn btn-outline-danger" href="{{ route('admin.planes-estudio.index') }}">Volver</a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><strong>Curso:</strong><br>{{ $plan->curso?->nombre }}</div>
                <div class="col-md-2"><strong>Año:</strong><br>{{ $plan->anio }}</div>
                <div class="col-md-2"><strong>Régimen:</strong><br>{{ $plan->regimen_jec }}</div>
                <div class="col-md-4"><strong>Plan:</strong><br>{{ $plan->nombre_plan }}</div>
                <div class="col-md-3"><strong>Subtotal semanal:</strong><br>{{ $plan->horas_semanales_subtotal !== null ? number_format((float) $plan->horas_semanales_subtotal, 2, ',', '.') : '—' }}</div>
                <div class="col-md-3"><strong>Libre disposición:</strong><br>{{ $plan->horas_semanales_libre_disposicion !== null ? number_format((float) $plan->horas_semanales_libre_disposicion, 2, ',', '.') : '—' }}</div>
                <div class="col-md-3"><strong>Total semanal:</strong><br>{{ number_format((float) $plan->horas_semanales_total, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Total anual:</strong><br>{{ $plan->horas_anuales_total !== null ? number_format((float) $plan->horas_anuales_total, 2, ',', '.') : '—' }}</div>
                <div class="col-md-12"><strong>Decreto / referencia:</strong><br>{{ $plan->decreto_referencia ?: '—' }}</div>
                <div class="col-md-12"><strong>Observación:</strong><br>{{ $plan->observacion ?: '—' }}</div>
            </div>
        </div>
    </div>


    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Bloques del plan de estudio</div>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Bloque</th>
                        <th>Tipo</th>
                        <th class="text-end">Horas semanales</th>
                        <th class="text-end">Horas anuales</th>
                        <th class="text-center">Selección EE</th>
                        <th class="text-center">Personalizadas</th>
                        <th class="text-center">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plan->bloques as $bloque)
                        <tr>
                            <td>{{ $bloque->orden }}</td>
                            <td>{{ $bloque->nombre }}</td>
                            <td>{{ str_replace('_', ' ', $bloque->tipo_bloque) }}</td>
                            <td class="text-end">{{ $bloque->horas_semanales !== null ? number_format((float) $bloque->horas_semanales, 2, ',', '.') : '—' }}</td>
                            <td class="text-end">{{ $bloque->horas_anuales !== null ? number_format((float) $bloque->horas_anuales, 2, ',', '.') : '—' }}</td>
                            <td class="text-center">{{ $bloque->permite_asignaturas_establecimiento ? 'Sí' : 'No' }}</td>
                            <td class="text-center">{{ $bloque->permite_asignaturas_personalizadas ? 'Sí' : 'No' }}</td>
                            <td class="text-center"><span class="badge {{ $bloque->activo ? 'bg-success' : 'bg-secondary' }}">{{ $bloque->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay bloques registrados para este plan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Detalle de asignaturas / componentes</div>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Asignatura / componente</th>
                        <th>Tipo</th>
                        <th class="text-end">Horas semanales</th>
                        <th class="text-end">Horas anuales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plan->asignaturas as $asignatura)
                        <tr>
                            <td>{{ $asignatura->orden }}</td>
                            <td>{{ $asignatura->asignatura }}</td>
                            <td>{{ $asignatura->tipo_bloque }}</td>
                            <td class="text-end">{{ $asignatura->horas_semanales !== null ? number_format((float) $asignatura->horas_semanales, 2, ',', '.') : '—' }}</td>
                            <td class="text-end">{{ $asignatura->horas_anuales !== null ? number_format((float) $asignatura->horas_anuales, 2, ',', '.') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay asignaturas registradas para este plan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
