@extends('layouts.app')

@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush

@section('content')
@php
    $pesos = fn ($monto) => '$'.number_format($monto, 0, ',', '.');
    $cerrada = (bool) $cuota->cerrado_at;
    $nombresMes = \App\Models\SnedCuota::MESES;
    $metodos = \App\Models\SnedCuota::METODOS;
@endphp
<div class="container py-4 slep-listing" data-slep-listing>
    <header class="slep-listing__header">
        <div class="slep-listing__heading">
            <span class="slep-listing__icon"><i class="bi bi-award" aria-hidden="true"></i></span>
            <div>
                <p class="slep-listing__eyebrow">Remuneraciones · SNED</p>
                <h1>Cuota {{ $cuota->numero }} · {{ $cuota->anio }} · {{ $cuota->comuna }}</h1>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="slep-status-pill slep-status-pill--{{ $cerrada ? 'success' : 'neutral' }}">{{ $cerrada ? 'Cerrada' : 'Borrador' }}</span>
                    <span class="small slep-listing__muted">{{ $nombresMes[min($cuota->meses())] }} a {{ mb_strtolower($nombresMes[max($cuota->meses())]) }} · Versión {{ $cuota->version }}</span>
                </div>
            </div>
        </div>
        <a href="{{ route('sned.index') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a cuotas</a>
    </header>

    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())
        <div class="slep-listing__notice slep-listing__notice--danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="slep-card p-4 mb-4" aria-labelledby="cargas-mae">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h2 id="cargas-mae" class="h5 mb-1">MAE de Haberes por Contrato</h2>
                <p class="small text-muted mb-0">Cargue el archivo XLSX de cada mes. La nómina incluye a quienes tienen contrato vigente al menos un día del mes de corte.</p>
            </div>
            @if ($cargas->contains(fn ($carga) => in_array($carga->estado, ['pendiente', 'procesando'], true)))
                <a class="btn btn-sm btn-outline-primary" href="{{ request()->fullUrl() }}"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Actualizar estado</a>
            @endif
        </div>
        <div class="row g-3">
            @foreach ($cuota->meses() as $mes)
                @php
                    $carga = $cargas->get($mes);
                    [$estado, $color] = match ($carga?->estado) {
                        'lista' => ['Procesado', 'success'],
                        'pendiente' => ['En cola', 'info'],
                        'procesando' => ['Procesando', 'info'],
                        'fallida' => ['Error de carga', 'danger'],
                        default => ['Pendiente', 'neutral'],
                    };
                @endphp
                <div class="col-lg-4">
                    <div class="border rounded-4 bg-light-subtle p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <h3 class="h6 mb-0">{{ $nombresMes[$mes] }}</h3>
                            <span class="slep-status-pill slep-status-pill--{{ $color }}">{{ $estado }}</span>
                        </div>
                        @if ($carga)
                            <div class="small text-break">{{ $carga->nombre_archivo }}</div>
                            @if ($carga->estado === 'lista')
                                <div class="small text-muted">{{ $carga->lectura['total'] }} líneas de contrato de la comuna.</div>
                                @if ($carga->lectura['fuera_comuna'] || $carga->lectura['duplicados'])
                                    <div class="small text-muted">{{ $carga->lectura['fuera_comuna'] }} filas fuera de los RBD de la comuna · {{ $carga->lectura['duplicados'] }} repetidas.</div>
                                @endif
                            @endif
                            @if ($carga->error)<div class="small text-danger">{{ $carga->error }}</div>@endif
                        @endif
                        @unless ($cerrada)
                            <form method="POST" action="{{ route('sned.cargar', $cuota) }}" enctype="multipart/form-data" class="mt-3">
                                @csrf
                                <input type="hidden" name="mes" value="{{ $mes }}">
                                <label for="mae-{{ $mes }}" class="form-label small">MAE de {{ mb_strtolower($nombresMes[$mes]) }} (.xlsx)</label>
                                <input id="mae-{{ $mes }}" name="excel" type="file" class="form-control form-control-sm" accept=".xlsx" required>
                                <button class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-upload me-1" aria-hidden="true"></i> {{ $carga ? 'Reemplazar MAE' : 'Cargar MAE' }}</button>
                            </form>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="slep-card p-4 mb-4" aria-labelledby="criterio-sned">
        <h2 id="criterio-sned" class="h5 mb-3">Criterio de cálculo</h2>
        @if ($cerrada)
            <p class="mb-1">{{ $metodos[$cuota->metodo] }}@if ($cuota->mes_pago) · Mes de pago {{ $cuota->mes_pago }}@endif</p>
            @if ($cuota->instruccion_calculo)<p class="small text-muted mb-0">{{ $cuota->instruccion_calculo }}</p>@endif
        @else
            <form method="POST" action="{{ route('sned.configurar', $cuota) }}" class="row g-3">
                @csrf
                @method('PATCH')
                <div class="col-md-8">
                    <label for="metodo" class="form-label">Criterio de reparto general</label>
                    <select id="metodo" name="metodo" class="form-select" aria-describedby="ayuda-metodo">
                        @foreach ($metodos as $clave => $metodo)
                            <option value="{{ $clave }}" @selected(old('metodo', $cuota->metodo) === $clave)>{{ $metodo }}</option>
                        @endforeach
                    </select>
                    <div id="ayuda-metodo" class="form-text">El prorrateo trimestral combina horas y días de los tres MAE, con 30 días equivalentes por mes completo. El criterio de corte usa las horas vigentes en el último día activo de la persona en ese mes.</div>
                </div>
                <div class="col-md-4">
                    <label for="mes-pago" class="form-label">Mes de pago (opcional)</label>
                    <input id="mes-pago" name="mes_pago" type="month" class="form-control" value="{{ old('mes_pago', $cuota->mes_pago) }}">
                </div>
                <div class="col-12">
                    <label for="instruccion-calculo" class="form-label">Referencia de la instrucción del SLEP</label>
                    <textarea id="instruccion-calculo" name="instruccion_calculo" class="form-control" rows="2" maxlength="2000" aria-describedby="ayuda-instruccion">{{ old('instruccion_calculo', $cuota->instruccion_calculo) }}</textarea>
                    <div id="ayuda-instruccion" class="form-text">Requerida para cerrar una cuota con prorrateo por días.</div>
                </div>
                <div class="col-12"><button class="btn btn-outline-primary">Guardar criterio</button></div>
            </form>
        @endif
    </section>

    <section class="slep-card p-4 mb-4" aria-labelledby="aportes-sned">
        <h2 id="aportes-sned" class="h5 mb-3">Establecimientos y aportes brutos</h2>
        @unless ($cerrada)
            <form method="POST" action="{{ route('sned.aportes.store', $cuota) }}" class="row g-3 align-items-end mb-4">
                @csrf
                <div class="col-lg-5">
                    <label for="rbd" class="form-label">Establecimiento</label>
                    <select id="rbd" name="rbd" class="form-select" data-slep-select="search" required>
                        <option value="">Seleccione un establecimiento</option>
                        @foreach ($establecimientos as $ee)
                            <option value="{{ $ee->rbd }}" @selected((int) old('rbd') === $ee->rbd)>{{ $ee->rbd }} · {{ $ee->nombre_establecimiento }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="bruto-docente" class="form-label">Docentes ($)</label>
                    <input id="bruto-docente" name="docentes" type="number" min="0" step="1" class="form-control" value="{{ old('docentes') }}" required>
                </div>
                <div class="col-6 col-lg-2">
                    <label for="bruto-aaee" class="form-label">AAEE ($)</label>
                    <input id="bruto-aaee" name="aaee" type="number" min="0" step="1" class="form-control" value="{{ old('aaee') }}" required>
                </div>
                <div class="col-lg-3"><button class="btn btn-primary">Guardar aporte</button></div>
            </form>
        @endunless
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" aria-labelledby="aportes-sned">
                <thead class="table-light"><tr><th scope="col">RBD y establecimiento</th><th scope="col" class="text-end">90% docente</th><th scope="col" class="text-end">10% incentivo</th><th scope="col" class="text-end">AAEE</th><th scope="col"><span class="visually-hidden">Acciones</span></th></tr></thead>
                <tbody>
                    @forelse ($aportes as $item)
                        <tr @class(['table-primary' => $aporte?->id === $item->id])>
                            <th scope="row">{{ $item->rbd }} · {{ $item->establecimiento }}</th>
                            <td class="text-end text-nowrap">{{ $pesos($item->docentes - $item->fondoIncentivo()) }}</td>
                            <td class="text-end text-nowrap">{{ $pesos($item->fondoIncentivo()) }}</td>
                            <td class="text-end text-nowrap">{{ $pesos($item->aaee) }}</td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-end gap-2">
                                    <a class="btn btn-sm btn-outline-primary text-nowrap" href="{{ route('sned.show', [$cuota, 'rbd' => $item->rbd]) }}#calculo-rbd" @if ($aporte?->id === $item->id) aria-current="true" @endif>Ver cálculo</a>
                                    @unless ($cerrada)
                                        <form method="POST" action="{{ route('sned.aportes.destroy', [$cuota, $item]) }}" onsubmit="return confirm('¿Desea quitar el establecimiento, su aporte y su reparto del borrador?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" aria-label="Quitar RBD {{ $item->rbd }} del borrador">Quitar</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center p-4">Agregue un establecimiento seleccionado para SNED.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($resultado)
        @include('remuneraciones.sned.resultado')
    @elseif ($aporte)
        <div id="calculo-rbd" class="alert alert-info">El cálculo se habilita cuando los tres MAE estén procesados.</div>
    @endif

    <section class="slep-card p-4 mb-4" aria-labelledby="cierre-sned">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <h2 id="cierre-sned" class="h5 mb-1">{{ $cerrada ? 'Nóminas para pago' : 'Cerrar cuota' }}</h2>
                @if ($cerrada)
                    <p class="small text-muted mb-0">Cerrada el {{ $cuota->cerrado_at->format('d/m/Y H:i') }}. Cada archivo consolida un monto por RUT en la comuna.</p>
                    @if ($cuota->version < $ultimaVersion)<p class="small text-warning-emphasis mb-0">Hay una versión posterior de esta cuota.</p>@endif
                @else
                    <p class="small text-muted mb-0">Al cerrar, el sistema fija el cálculo de esta versión y habilita las descargas.</p>
                    @if (! $listas || $aportes->isEmpty())
                        <p class="small text-muted mb-0">Para cerrar, procese los tres MAE y registre al menos un establecimiento con aporte.</p>
                    @endif
                @endif
            </div>
            @if ($cerrada)
                @if ((int) $cuota->version === $ultimaVersion)
                    <form method="POST" action="{{ route('sned.version', $cuota) }}">@csrf<button class="btn btn-outline-secondary">Crear nueva versión</button></form>
                @endif
            @else
                <form method="POST" action="{{ route('sned.cerrar', $cuota) }}" onsubmit="return confirm('¿Confirma el cierre de la cuota? Se fijará el cálculo de esta versión y se habilitarán las descargas.');">
                    @csrf
                    <button class="btn btn-primary" @disabled(! $listas || $aportes->isEmpty())>Cerrar cuota</button>
                </form>
            @endif
        </div>
        @if ($cerrada)
            <div class="row g-3 mt-1">
                @foreach (\App\Models\SnedCuota::NOMINAS as $codigo => $nomina)
                    <div class="col-md-4">
                        <div class="border rounded-4 bg-light-subtle p-3 h-100">
                            <h3 class="h6 mb-3">{{ $nomina['titulo'] }}</h3>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($nomina['formatos'] as $formato)
                                    <a class="btn btn-outline-primary" href="{{ route('sned.exportar', [$cuota, $codigo, $formato]) }}" aria-label="Descargar {{ $nomina['titulo'] }} en {{ strtoupper($formato) }}">
                                        <i class="bi bi-download me-1" aria-hidden="true"></i> {{ strtoupper($formato) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
