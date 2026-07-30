@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <div class="text-uppercase text-muted fw-bold small">Mantenedor</div>
            <h1 class="h3 mb-1">Salas y recursos administrables</h1>
            <p class="text-muted mb-0">Configure salas, aprobación y administradores responsables. Cada sala/recurso debe tener al menos un administrador asignado.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('gestion.agendamientos-recursos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver agenda</a>
            <a href="{{ route('gestion.agendamientos-recursos.recursos.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> Nueva sala/recurso</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Ubicación</th>
                        <th>Aprobación</th>
                        <th>Administradores</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recursos as $recurso)
                        <tr>
                            <td><strong>{{ $recurso->nombre }}</strong><br><span class="small text-muted">{{ $recurso->slug }}</span></td>
                            <td>{{ $recurso->tipo_label }}</td>
                            <td>{{ $recurso->ubicacion ?: '—' }}</td>
                            <td>{!! $recurso->requiere_aprobacion ? '<span class="badge text-bg-warning">Requiere aprobación</span>' : '<span class="badge text-bg-success">Reserva directa</span>' !!}</td>
                            <td>
                                @forelse($recurso->administradores as $admin)
                                    <span class="badge text-bg-light border text-dark">{{ $admin->nombre_completo ?: $admin->email }}</span>
                                @empty
                                    <span class="badge text-bg-danger">Sin administrador asignado</span><div class="small text-danger mt-1">Debe asignar responsable.</div>
                                @endforelse
                            </td>
                            <td>{!! $recurso->activo ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' !!}</td>
                            <td class="text-end"><a href="{{ route('gestion.agendamientos-recursos.recursos.edit', $recurso) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay salas o recursos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
