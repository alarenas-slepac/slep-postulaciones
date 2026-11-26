@extends('layouts.app')

@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush

@section('content')
    @inject('prorrogas', 'App\Services\SolicitudReemplazoProrrogaService')
    @php
        $ruta = 'gestion.solicitudes-reemplazo.prorrogas.';
        $fecha = fn ($valor) => \Carbon\CarbonImmutable::parse($valor)->format('d/m/Y');
    @endphp
    <div class="slep-listing" data-slep-listing>
        <header class="slep-listing__header">
            <div class="slep-listing__heading">
                <span class="slep-listing__icon"><i class="bi bi-calendar-plus" aria-hidden="true"></i></span>
                <div>
                    <p class="slep-listing__eyebrow">Prórrogas Reemplazos · {{ $estamentos[$estamento] }} · Diciembre {{ $anio }}</p>
                    <h1>{{ $caso['nombre'] }}</h1>
                    <p class="mb-0 slep-listing__muted">{{ \App\Support\Rut::format($rut) }}</p>
                </div>
            </div>
            <a class="slep-listing__button slep-listing__button--secondary" href="{{ route($ruta.'index', compact('anio', 'estamento')) }}">Volver a la bandeja</a>
        </header>

        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="slep-listing__notice slep-listing__notice--danger" data-slep-errors tabindex="-1" role="alert">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="slep-listing__panel" aria-labelledby="prorroga-detalle">
            <div class="slep-listing__panel-header">
                <div>
                    <h2 id="prorroga-detalle">Períodos de reemplazo</h2>
                    <p class="mb-0 slep-listing__muted">Continuidad desde el {{ $fecha($caso['inicio_continuidad']) }} hasta el corte del {{ $fecha($caso['fecha_corte']) }}.</p>
                </div>
                <span class="slep-status-pill slep-status-pill--{{ $registrada ? 'success' : 'warning' }}">{{ $registrada ? 'Registrada' : 'Pendiente de revisión' }}</span>
            </div>
            <div class="slep-listing__table-wrap">
                <table class="slep-listing__table" role="table">
                    <thead><tr><th scope="col">Solicitud</th><th scope="col">Establecimiento</th><th scope="col">Inicio efectivo</th><th scope="col">Término vigente</th><th scope="col">Jornada y financiamiento</th></tr></thead>
                    <tbody>
                    @foreach ($caso['periodos'] as $periodo)
                        <tr role="row">
                            <td data-label="Solicitud"><a href="{{ route('gestion.solicitudes-reemplazo.show', $periodo['id']) }}">{{ $periodo['solicitud'] }}</a></td>
                            <td data-label="Establecimiento">{{ $periodo['establecimiento'] ?? 'Sin establecimiento informado' }}@if ($periodo['rbd'])<div class="small slep-listing__muted">RBD {{ $periodo['rbd'] }}</div>@endif</td>
                            <td data-label="Inicio efectivo">{{ $fecha($periodo['desde']) }}</td>
                            <td data-label="Término vigente">{{ $fecha($periodo['hasta']) }}</td>
                            <td data-label="Jornada y financiamiento">
                                {{ isset($periodo['horas']) ? number_format($periodo['horas'], 2, ',', '.').' h' : 'Jornada sin informar' }}
                                <div class="small slep-listing__muted">{{ $prorrogas->financiamiento($periodo) ?: 'Financiamiento sin informar' }}</div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="slep-listing__panel" aria-labelledby="prorroga-resolucion">
            <div class="slep-listing__panel-header">
                <div>
                    <h2 id="prorroga-resolucion">Resolución de prórroga</h2>
                    <p class="mb-0 slep-listing__muted">Período del {{ $fecha($vigencia['desde']) }} al {{ $fecha($vigencia['hasta']) }}.</p>
                </div>
            </div>
            <div class="slep-listing__filters">
                @if ($caso['cobertura'])
                    <div class="slep-record-state slep-record-state--info mb-3">
                        <p class="fw-semibold mb-1">Períodos de enero y febrero con cobertura registrada en este estamento</p>
                        <ul class="mb-0">
                            @foreach ($caso['cobertura'] as $tramo)<li>{{ $fecha($tramo['desde']) }} al {{ $fecha($tramo['hasta']) }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                @if ($registrada)
                    <dl class="row mb-2">
                        <dt class="col-sm-3">Número de resolución</dt><dd class="col-sm-9 text-break">{{ $registrada->numero_resolucion }}</dd>
                        <dt class="col-sm-3">Fecha de resolución</dt><dd class="col-sm-9">{{ $registrada->fecha_resolucion->format('d/m/Y') }}</dd>
                        <dt class="col-sm-3">Fecha de registro</dt><dd class="col-sm-9">{{ ($registrada->resolucion_registrada_at ?? $registrada->created_at)->format('d/m/Y H:i') }}</dd>
                        <dt class="col-sm-3">Registrada por</dt><dd class="col-sm-9">{{ $registrada->registradaPor?->nombre_completo ?: 'Usuario no disponible' }}</dd>
                        @if ($registrada->observacion)<dt class="col-sm-3">Observación</dt><dd class="col-sm-9 text-break">{!! nl2br(e($registrada->observacion)) !!}</dd>@endif
                    </dl>
                    <a class="slep-listing__button slep-listing__button--secondary" href="{{ route($ruta.'resolucion', $registrada) }}">
                        <i class="bi bi-download" aria-hidden="true"></i> Descargar resolución
                    </a>
                    <span class="small slep-listing__muted ms-sm-2 text-break">{{ $registrada->resolucion_nombre }}</span>
                @else
                    <form method="POST" action="{{ route($ruta.'store', compact('anio', 'estamento', 'rut')) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="prorroga-numero" class="form-label">Número de resolución</label>
                                <input id="prorroga-numero" name="numero_resolucion" class="form-control @error('numero_resolucion') is-invalid @enderror" value="{{ old('numero_resolucion') }}" maxlength="100" required>
                                @error('numero_resolucion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="prorroga-fecha" class="form-label">Fecha de resolución</label>
                                <input id="prorroga-fecha" name="fecha_resolucion" type="date" class="form-control @error('fecha_resolucion') is-invalid @enderror" value="{{ old('fecha_resolucion') }}" required>
                                @error('fecha_resolucion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="prorroga-archivo" class="form-label">Resolución en PDF</label>
                                <input id="prorroga-archivo" name="resolucion" type="file" class="form-control @error('resolucion') is-invalid @enderror" accept="application/pdf,.pdf" aria-describedby="prorroga-archivo-ayuda" required>
                                <div class="form-text" id="prorroga-archivo-ayuda">Tamaño máximo de 20 MB.</div>
                                @error('resolucion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="prorroga-observacion" class="form-label">Observación (opcional)</label>
                                <textarea id="prorroga-observacion" name="observacion" rows="3" maxlength="5000" class="form-control @error('observacion') is-invalid @enderror">{{ old('observacion') }}</textarea>
                                @error('observacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="slep-listing__actions mt-3">
                            <button type="submit" class="slep-listing__button slep-listing__button--primary">
                                <i class="bi bi-check2" aria-hidden="true"></i> Registrar prórroga
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </section>

        <section class="slep-listing__panel" aria-labelledby="prorroga-orden">
            <div class="slep-listing__panel-header"><h2 id="prorroga-orden">Orden de trabajo</h2></div>
            <div class="slep-listing__filters">
                @php
                    $ultimo = $caso['ultimo_reemplazo'];
                    $numeroOrden = $expediente?->correlativoOrdenTrabajo?->id;
                @endphp
                <dl class="row mb-3">
                    <dt class="col-sm-3">Reemplazo de origen</dt><dd class="col-sm-9">{{ $ultimo['solicitud'] ?? 'Sin información' }}</dd>
                    <dt class="col-sm-3">Establecimiento</dt><dd class="col-sm-9">{{ $ultimo['establecimiento'] ?? 'Sin información' }}</dd>
                    <dt class="col-sm-3">Área de desempeño</dt><dd class="col-sm-9">{{ $ultimo['cargo'] ?? 'Sin información' }}</dd>
                    <dt class="col-sm-3">Jornada</dt><dd class="col-sm-9">{{ isset($ultimo['horas']) ? number_format($ultimo['horas'], 2, ',', '.').' h' : 'Sin información' }}</dd>
                    <dt class="col-sm-3">Financiamiento</dt><dd class="col-sm-9">{{ $prorrogas->financiamiento($ultimo) ?: 'Sin información' }}</dd>
                    <dt class="col-sm-3">Vigencia de la orden</dt><dd class="col-sm-9">{{ $fecha($vigencia['desde']) }} al {{ $fecha($vigencia['hasta']) }}</dd>
                    <dt class="col-sm-3">Motivo</dt><dd class="col-sm-9">{{ $prorrogas->motivo($estamento) }}</dd>
                </dl>
                @if ($numeroOrden)
                    <div class="slep-listing__actions">
                        @if ($ordenDisponible)
                            <a class="slep-listing__button slep-listing__button--secondary" href="{{ route($ruta.'orden-trabajo.descargar', $expediente) }}">
                                <i class="bi bi-download" aria-hidden="true"></i> Descargar OT N° {{ $numeroOrden }}
                            </a>
                        @endif
                        <form method="POST" action="{{ route($ruta.'orden-trabajo.generar', $expediente) }}" onsubmit="return confirm('¿Regenerar el PDF de esta orden de trabajo con el mismo número?');">
                            @csrf
                            <button type="submit" name="regenerar" value="1" class="slep-listing__button slep-listing__button--secondary">
                                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Regenerar orden de trabajo
                            </button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route($ruta.'orden-trabajo.generar-caso', compact('anio', 'estamento', 'rut')) }}">
                        @csrf
                        <button type="submit" class="slep-listing__button slep-listing__button--primary"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Generar orden de trabajo</button>
                    </form>
                @endif
            </div>
        </section>
    </div>
@endsection
