@extends('layouts.app')

@push('styles')
    @vite('resources/js/padron-individual.js')
@endpush

@section('content')
@php
    $textoPeriodo = $periodo ? sprintf('%02d/%04d', $periodo % 100, intdiv($periodo, 100)) : 'Sin padrón';
@endphp
<div class="slep-listing padron-individual" data-slep-listing data-padron-individual>
    <header class="slep-listing__header">
        <div class="slep-listing__heading">
            <span class="slep-listing__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
            <div><p class="slep-listing__eyebrow">Reemplazos · Gestión del padrón</p><h1>Ingreso y actualización individual del padrón</h1><p class="slep-listing__muted mb-0">Consulta un RUT, selecciona un contrato o incorpora un nuevo registro con trazabilidad.</p></div>
        </div>
        <a class="slep-listing__button slep-listing__button--secondary" href="{{ route('reemplazos.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i>Volver al padrón</a>
        <dl class="padron-summary">
            <div><dt><i class="bi bi-calendar3" aria-hidden="true"></i>Período abierto</dt><dd>{{ $textoPeriodo }}</dd><dd class="padron-summary-help">Último período cargado del padrón.</dd></div>
            <div><dt><i class="bi bi-person-check" aria-hidden="true"></i>RUT consultado</dt><dd>{{ $rut ? \App\Support\Rut::format($rut) : 'Sin consultar' }}</dd><dd class="padron-summary-help">{{ $rut ? $registros->count().' registros encontrados.' : 'Ingresa el RUT para comenzar.' }}</dd></div>
            <div><dt><i class="bi bi-pencil-square" aria-hidden="true"></i>Operación</dt><dd>{{ $item ? 'Actualizar ID '.$item->id : 'Nuevo registro' }}</dd><dd class="padron-summary-help">{{ $item ? 'Conserva sus referencias e historial.' : 'Primero revisa los contratos existentes.' }}</dd></div>
        </dl>
    </header>
    @if(session('status'))<div class="slep-record-state slep-record-state--success mb-4" role="status"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="slep-listing__notice slep-listing__notice--danger" role="alert" tabindex="-1" data-padron-errors><strong><i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>Revisa los datos antes de continuar</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="slep-listing__panel" aria-labelledby="padron-buscar-titulo">
        <div class="slep-listing__panel-header"><div><p class="slep-listing__eyebrow">Paso 1 · Consulta</p><h2 id="padron-buscar-titulo">Buscar persona por RUT</h2></div><span class="slep-listing__chip"><i class="bi bi-shield-check" aria-hidden="true"></i>Validación de dígito verificador</span></div>
        <form method="GET" action="{{ route('reemplazos.individual.index') }}" class="padron-body" data-padron-search novalidate>
            <div class="row g-3"><div class="col-lg-6">
                <label for="buscar_rut" class="form-label">RUT con dígito verificador <span class="text-danger" aria-hidden="true">*</span></label><input id="buscar_rut" name="rut" class="form-control @error('rut') is-invalid @enderror" value="{{ $rut ?? old('rut') }}" maxlength="20" required autocomplete="off" placeholder="Ingresa el RUT con su dígito verificador" aria-describedby="buscar_rut-help buscar_rut-error" @error('rut') aria-invalid="true" @enderror><p class="form-text" id="buscar_rut-help">Puedes ingresarlo con o sin puntos y guion. Se consultan sólo los registros internos.</p><div class="invalid-feedback" id="buscar_rut-error">@error('rut'){{ $message }}@enderror</div>
            </div><div class="col-lg-6 padron-search-action"><button type="submit" class="slep-listing__button slep-listing__button--primary"><i class="bi bi-search" aria-hidden="true"></i><span>Validar RUT y consultar registros</span></button></div></div>
        </form>
    </section>
    <div class="slep-record-state slep-record-state--info mb-4 padron-guidance"><i class="bi bi-info-circle" aria-hidden="true"></i><div><strong>Una actualización por registro</strong><p class="mb-0">La consulta no verifica identidad en el Registro Civil. Los contratos regulares conservan su ID y cada reemplazo nuevo recibe uno propio. Esta operación mantiene documentos, bloqueos y asignaciones; no abre otro mes ni reemplaza la carga completa.</p></div></div>
    @if(!$rut)
        <section class="slep-listing__panel padron-empty" aria-labelledby="padron-inicio-titulo"><i class="bi bi-search" aria-hidden="true"></i><h2 id="padron-inicio-titulo">Comienza consultando un RUT</h2><p class="slep-listing__muted mb-0">Aquí podrás revisar sus registros, elegir el contrato que necesitas modificar o completar un nuevo ingreso.</p></section>
    @else
        <section class="slep-listing__panel" aria-labelledby="padron-registros-titulo">
            <div class="slep-listing__panel-header"><div><p class="slep-listing__eyebrow">Paso 2 · Registros encontrados</p><h2 id="padron-registros-titulo">Registros de {{ \App\Support\Rut::format($rut) }}</h2><p class="slep-listing__muted mb-0">Selecciona el ID que deseas modificar. Las demás líneas conservarán sus datos.</p></div><span class="slep-status-pill slep-status-pill--neutral">{{ $editables->count() }} contratos editables</span></div>
            <div class="slep-listing__table-wrap"><table class="slep-listing__table padron-table" role="table"><caption class="visually-hidden">Contratos del RUT consultado y acciones disponibles</caption><thead><tr><th scope="col">ID / período</th><th scope="col">Nombre / establecimiento</th><th scope="col">Contrato / financiamiento</th><th scope="col">Horas</th><th scope="col">Duración</th><th scope="col">Acción</th></tr></thead><tbody>
                @forelse($registros as $p)
                    <tr @class(['padron-row-selected' => $item?->id === $p->id])>
                        <td data-label="ID / período"><strong class="d-block mb-2">ID {{ $p->id }} · {{ sprintf('%02d/%04d', $p->mes, $p->anio) }}</strong><span class="slep-status-pill slep-status-pill--{{ $p->vigente ? 'success' : 'neutral' }}">{{ $p->vigente ? 'Vigente' : 'Inactivo' }}</span></td>
                        <td data-label="Nombre / establecimiento"><strong class="slep-listing__person">{{ $p->nombre }}</strong><span class="d-block">{{ $p->establecimiento?->nombre_establecimiento ?? 'Establecimiento sin información' }}</span><span class="slep-listing__muted">RBD {{ $p->rbd }}</span></td>
                        <td data-label="Contrato / financiamiento"><strong class="d-block">{{ $p->tipocontrato }}</strong><span class="slep-listing__muted">{{ $p->financiamiento }}</span></td><td data-label="Horas">{{ $p->jornada }} h</td>
                        <td data-label="Duración"><span class="d-block">Desde {{ $p->fecha_ingreso?->format('d/m/Y') ?? '—' }}</span><span class="slep-listing__muted">{{ $p->fecha_termino ? 'Hasta '.$p->fecha_termino->format('d/m/Y') : 'Sin término' }}</span></td>
                        <td data-label="Acción">@if($editables->contains('id', $p->id))<a class="slep-listing__button slep-listing__button--secondary slep-listing__button--compact" href="{{ route('reemplazos.individual.index', ['rut' => $rut, 'personal_id' => $p->id]) }}" @if($item?->id === $p->id) aria-current="true" @endif><i class="bi bi-{{ $p->vigente ? 'pencil-square' : 'arrow-clockwise' }}" aria-hidden="true"></i>{{ $p->vigente ? 'Modificar' : 'Reactivar' }} ID {{ $p->id }}</a>@if($item?->id === $p->id)<span class="slep-status-pill slep-status-pill--info mt-2">Seleccionado</span>@endif @else<span class="slep-listing__muted">Consulta; no se sobrescribe</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="slep-listing__empty"><i class="bi bi-person-plus" aria-hidden="true"></i><strong>Este RUT no tiene registros.</strong><p class="slep-listing__muted mb-0">Puedes ingresar su primer contrato en el formulario siguiente.</p></td></tr>
                @endforelse
            </tbody></table></div>
            @if($item)<div class="slep-listing__footer"><p class="slep-listing__muted mb-0">Estás modificando el contrato ID {{ $item->id }}.</p><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('reemplazos.individual.index', ['rut' => $rut]) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i>Ingresar nuevo contrato / reemplazo</a></div>@endif
        </section>
        @if(!$instalado || !$periodo)
            <div class="slep-record-state slep-record-state--warning mb-4" role="status"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>Para guardar se requiere instalar la migración de auditoría individual y disponer de un período de padrón cargado.</div>
        @else
            @include('reemplazos.personal.partials._formulario-individual')
        @endif
        @if($historial->isNotEmpty())
            <section class="slep-listing__panel" aria-labelledby="padron-historial-titulo"><div class="slep-listing__panel-header"><div><p class="slep-listing__eyebrow">Trazabilidad</p><h2 id="padron-historial-titulo"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Últimos 10 cambios individuales de este RUT</h2></div><span class="slep-status-pill slep-status-pill--neutral">{{ $historial->count() }} cambios</span></div>
                <ol class="padron-history padron-body">@foreach($historial as $cambio)<li><div class="padron-history-heading"><strong>ID {{ $cambio->personal_id }} · {{ ucfirst($cambio->accion) }}</strong><time datetime="{{ \Illuminate\Support\Carbon::parse($cambio->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($cambio->created_at)->format('d/m/Y H:i') }}</time></div><p class="slep-listing__muted">Usuario #{{ $cambio->usuario_id }}</p><p>{{ $cambio->justificacion }}</p>
                    @php($controlCambio = json_decode($cambio->controles, true) ?? [])
                    @if(!empty($controlCambio['liberaciones']))<div class="slep-record-state slep-record-state--warning"><strong>Liberadas: {{ count($controlCambio['liberaciones']) }} asignaciones</strong><p>{{ $controlCambio['justificacion_liberacion'] ?? '' }}</p><ul class="mb-0">@foreach($controlCambio['liberaciones'] as $liberacion)<li>#{{ $liberacion['antes']['id'] }} · Establecimiento ID {{ $liberacion['antes']['establecimiento_id'] }} · {{ $liberacion['antes']['horas_contrato'] }} h · Inactiva</li>@endforeach</ul></div>@endif
                </li>@endforeach</ol>
            </section>
        @endif
    @endif
</div>
@endsection
