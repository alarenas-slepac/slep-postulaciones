@extends('layouts.app')
@push('styles')
    @vite('resources/css/consulta-votacion.css')
@endpush
@section('content')
@php($fmt = fn ($valor) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($valor))
@php($bloquesBir = \App\Services\Dotacion\NoRenovacionService::BLOQUES)
@php($seleccionBir = is_array(old('cupos')) ? old('cupos') : [])
<div class="cv-shell">
    <header class="cv-panel cv-page-header mb-4">
        <div><span class="cv-eyebrow"><i class="bi bi-person-plus" aria-hidden="true"></i> Dotación {{ $anio }} · Proceso BIR</span><h1>Crear docentes por contratar desde BIR</h1><p class="mb-0">{{ $establecimiento->rbd }} · {{ $establecimiento->nombre_establecimiento }}. Revise las horas conservadas desde {{ $anio - 1 }}, su bloque y título antes de generar cupos.</p></div>
        <a class="btn cv-secondary" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio]) }}#habilitaciones-contrata">Volver a la dotación</a>
    </header>
    @if(session('success'))<div class="alert alert-success rounded-4" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger rounded-4" role="alert"><strong>Revise la selección.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(!$disponible)
        <section class="cv-panel"><h2>Preparación pendiente</h2><p class="mb-0">Aplique la migración de origen BIR y las migraciones de situaciones docentes para habilitar la revisión y creación de cupos.</p></section>
    @else
        <section class="cv-panel mb-4" aria-labelledby="bir-filtros">
            <h2 id="bir-filtros">Filtrar por bloque y título</h2>
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="anio" value="{{ $anio }}">
                <div class="col-md-4"><label class="form-label" for="bir-bloque">Bloque</label><select class="form-select" id="bir-bloque" name="bloque"><option value="">Todos los bloques</option>@foreach($bloquesBir as $key => $label)<option value="{{ $key }}" @selected(($filtros['bloque'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-5"><label class="form-label" for="bir-titulo">Título del docente de origen</label><input class="form-control" id="bir-titulo" name="q" maxlength="150" value="{{ $filtros['q'] ?? '' }}" placeholder="Buscar por título..."></div>
                <div class="col-md-3 d-flex gap-2"><button class="btn cv-primary" type="submit">Filtrar</button><a class="btn cv-secondary" href="{{ route('admin.dotacion-establecimiento.bir.index', [$establecimiento, 'anio' => $anio]) }}">Limpiar</a></div>
            </form>
        </section>
        <section class="cv-panel mb-4" aria-labelledby="bir-reglas">
            <h2 id="bir-reglas">Horas conservadas para {{ $anio }}</h2>
            <p>Se consideran docentes con Proceso BIR en {{ $anio - 1 }}, que no continúan y conservan horas. Cada cupo copia el título y la distribución contractual por bloque del origen, hasta el contrato vigente y con un máximo de 44 h por docente.</p>
            <p class="mb-0 text-secondary">Los cupos se suman al contrato de {{ $anio }}, con o sin sobredotación, y quedan sin asignaciones ni asociaciones a asignaturas. El docente y sus asignaciones de {{ $anio - 1 }} se conservan. Una situación histórica sin cantidad BIR expresa utiliza el contrato completo según la regla existente.</p>
        </section>
        <form method="POST" action="{{ route('admin.dotacion-establecimiento.bir.store', $establecimiento) }}">
            @csrf<input type="hidden" name="anio" value="{{ $anio }}">
            @forelse($items->groupBy('bloque') as $bloque => $grupo)
                <section class="cv-panel mb-4" aria-labelledby="bir-grupo-{{ $bloque }}">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><h2 id="bir-grupo-{{ $bloque }}">{{ $bloquesBir[$bloque] }}</h2><span class="badge rounded-pill text-bg-light border">{{ $grupo->count() }} cupo(s) revisado(s)</span></div>
                    @foreach($grupo->groupBy('titulo') as $titulo => $filas)
                        <h3 class="mt-3">{{ $titulo }}</h3>
                        <div class="table-responsive"><table class="table cv-table align-middle mb-0">
                            <thead><tr><th scope="col">Seleccionar</th><th scope="col">Docente de origen</th><th scope="col">Contrato {{ $anio - 1 }}</th><th scope="col">Horas definidas para {{ $anio }}</th><th scope="col">Cupo en este bloque</th><th scope="col">Estado</th></tr></thead>
                            <tbody>@foreach($filas as $item)
                                <tr><td>
                                    @if(!$item['motivo'] && !$item['cupo'])
                                        <input class="form-check-input" id="bir-cupo-{{ $item['situacion_id'] }}-{{ $bloque }}" type="checkbox" name="cupos[]" value="{{ $item['clave'] }}" @checked(in_array($item['clave'], $seleccionBir, true))>
                                        <label class="form-check-label small" for="bir-cupo-{{ $item['situacion_id'] }}-{{ $bloque }}">Generar cupo <span class="visually-hidden">de {{ $item['nombre'] }} en {{ $bloquesBir[$bloque] }}</span></label>
                                        <input type="hidden" name="huellas[{{ $item['clave'] }}]" value="{{ $item['huella'] }}">
                                    @else<span class="text-secondary">—</span>@endif
                                </td><td><strong>{{ $item['nombre'] }}</strong><div class="small text-secondary">{{ $item['rut'] }}</div></td>
                                <td>{{ $fmt($item['contrato_origen']) }} h</td><td>{{ $fmt($item['horas_definidas']) }} h @if($item['historico'])<div class="small text-secondary">Regla histórica: contrato completo</div>@endif @if($item['horas_definidas'] > $item['horas_conservadas'])<div class="small text-secondary">Limitadas a {{ $fmt($item['horas_conservadas']) }} h vigentes.</div>@endif</td>
                                <td class="fw-bold">{{ $fmt($item['horas']) }} h</td><td>
                                    @if($item['cupo'])<span class="cv-badge">Cupo #{{ $item['cupo']->id }} generado · {{ $fmt($item['cupo']->horas) }} h</span>
                                        @if($item['motivo'])<div class="cv-inline-note">{{ $item['motivo'] }}</div>@endif
                                    @elseif($item['motivo'])<span class="small text-secondary">{{ $item['motivo'] }}</span>@else<span class="cv-badge cv-badge-muted">Disponible</span>@endif
                                </td></tr>
                            @endforeach</tbody>
                        </table></div>
                    @endforeach
                </section>
            @empty
                <section class="cv-panel cv-empty mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i><h2>No hay situaciones BIR para esta selección</h2><p>Revise el año de origen y los filtros de bloque y título.</p></section>
            @endforelse
            @if($items->contains(fn ($item) => !$item['motivo'] && !$item['cupo']))
                <div class="cv-panel d-flex align-items-center justify-content-between flex-wrap gap-3"><p class="mb-0 text-secondary">Marque los cupos que desea crear. Las horas y títulos se vuelven a verificar al guardar.</p><button class="btn cv-primary" type="submit"><i class="bi bi-person-plus" aria-hidden="true"></i> Crear docentes por contratar seleccionados</button></div>
            @endif
        </form>
    @endif
</div>
@endsection
