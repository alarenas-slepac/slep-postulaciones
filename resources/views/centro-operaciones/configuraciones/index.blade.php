@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Mantenedor de incidencias</h1><p class="text-muted mb-0">Asigne responsable, unidad, subdirección y plazo para cada tipo.</p></div><a href="{{ route('centro-operaciones.tickets.index') }}" class="btn btn-outline-primary">Ver tickets</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="row g-3">
    @foreach($configuraciones as $configuracion)
        <div class="col-12"><form method="POST" action="{{ route('centro-operaciones.configuraciones.update', $configuracion) }}" class="card card-body shadow-sm"><div class="row g-3 align-items-end">@csrf @method('PUT')
            <div class="col-lg-3"><label class="form-label fw-semibold">Incidencia</label><div>{{ config("centro_operaciones.incidencias.{$configuracion->tipo}.label", $configuracion->tipo) }}</div></div>
            <div class="col-lg-5"><label class="form-label">Responsable</label><select name="responsable_funcionario_ac_id" class="form-select" required><option value="">Seleccione…</option>@foreach($funcionarios->groupBy('unidad_departamento') as $unidad => $personas)<optgroup label="{{ $unidad }}">@foreach($personas as $persona)<option value="{{ $persona->id }}" @selected($configuracion->responsable_funcionario_ac_id === $persona->id)>{{ $persona->nombre_completo }} · {{ $persona->subdireccion_dependencia }}</option>@endforeach</optgroup>@endforeach</select></div>
            <div class="col-lg-2"><label class="form-label">Plazo (días)</label><input name="plazo_dias" type="number" min="1" max="365" value="{{ $configuracion->plazo_dias }}" class="form-control" required></div>
            <div class="col-lg-1 form-check"><input type="hidden" name="activo" value="0"><input class="form-check-input" type="checkbox" name="activo" value="1" @checked($configuracion->activo)><label class="form-check-label">Activo</label></div>
            <div class="col-lg-1"><button class="btn btn-primary w-100">Guardar</button></div>
        </div></form></div>
    @endforeach
    </div>
</div>
@endsection
