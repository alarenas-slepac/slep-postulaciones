@extends('layouts.app')
@section('title', 'Revisar importación de maternidad')
@include('tramites.maternidad.partials._assets')
@section('content')
<div class="slep-listing mat-workspace" data-slep-listing>
    <header class="slep-listing__header"><div><p class="slep-listing__eyebrow">Trámites y operación · Maternidad</p><h1>Revisar importación</h1><p class="slep-listing__muted">{{ $importacion->cantidad }} caso(s) validados. La previsualización vence el {{ $importacion->expira_at->format('d-m-Y H:i') }}.</p></div><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('tramites.maternidad.importar') }}">Volver a cargar</a></header>
    @include('tramites.maternidad.partials._mensajes')
    @if ($importacion->procesada_at)<div class="slep-record-state slep-record-state--success">Esta importación ya fue registrada el {{ $importacion->procesada_at->format('d-m-Y H:i') }}.</div>@else
    <div class="slep-record-state slep-record-state--warning mb-4">Los documentos se completan después del ingreso. Las fechas definitivas y los beneficios requieren sus respectivos respaldos.</div>
    <section class="slep-listing__panel"><div class="slep-listing__table-wrap"><table class="slep-listing__table"><thead><tr><th>Funcionaria / caso</th><th>Vínculo laboral</th><th>Fechas informadas</th><th>Beneficios / hijos</th></tr></thead><tbody>
        @foreach ($importacion->payload ?? [] as $fila) @php($datos = $fila['datos'])<tr><td data-label="Funcionaria / caso"><strong>{{ $datos['nombres'] }} {{ $datos['apellido_paterno'] }} {{ $datos['apellido_materno'] ?? '' }}</strong><div>{{ $datos['rut'] }}</div><div class="small slep-listing__muted">{{ $datos['referencia_caso'] }}</div></td><td data-label="Vínculo laboral">{{ config('maternidad.estamentos.'.$datos['estamento']) }}<div>{{ $datos['establecimiento_nombre'] }}</div><div class="small">{{ $datos['comuna'] }} · {{ $datos['calidad_juridica'] }}</div></td><td data-label="Fechas informadas">FPP: {{ $datos['fpp'] ?? 'Sin informar' }}<div>FNH: {{ $datos['fnh'] ?? 'Sin informar' }}</div>
            @foreach (['fecha_rex_dominga' => 'REX Ley Dominga', 'fecha_rex_lactancia' => 'REX lactancia'] as $campoRex => $labelRex) @if (!empty($datos[$campoRex]))<div>{{ $labelRex }}: {{ $datos[$campoRex] }}</div>@endif @endforeach
            @if (isset($datos['horas_semanales_lactancia']))<div>Lactancia: {{ number_format((float) $datos['horas_semanales_lactancia'], 2, ',', '.') }} horas a la semana</div>@endif</td><td data-label="Beneficios / hijos">@foreach (['ley_dominga' => 'Ley Dominga', 'lactancia' => 'Lactancia', 'postnatal_parental' => 'Postnatal parental'] as $tipo => $label) @if ($datos[$tipo])<div>{{ $label }} · pendiente de respaldo</div>@endif @endforeach<div>{{ count($fila['hijos']) }} hijo(s) informado(s)</div>@if ($fila['observacion'])<p class="small mat-texto">{{ $fila['observacion'] }}</p>@endif</td></tr>@endforeach
    </tbody></table></div></section>
    <form action="{{ route('tramites.maternidad.confirmar', $importacion) }}" method="POST" class="mat-form-actions">@csrf<p class="small slep-listing__muted mb-0">Se crearán {{ $importacion->cantidad }} expedientes nuevos.</p><button class="slep-listing__button slep-listing__button--primary" type="submit">Confirmar ingreso de {{ $importacion->cantidad }} caso(s)</button></form>
    @endif
</div>
@endsection
