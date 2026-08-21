@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Carga MAE {{ sprintf('%02d/%04d', $maeCarga->mes, $maeCarga->anio) }} · {{ $maeCarga->dominio }}</h1>
            <p class="text-muted mb-0">Versión v{{ $maeCarga->version }} {{ $maeCarga->es_vigente ? 'vigente' : 'histórica' }}.</p>
        </div>
        <div class="d-flex gap-2">
            @if ($maeCarga->estado === 'pendiente_revision')
                <a href="{{ route('endeudamiento.cargas.clasificaciones', $maeCarga) }}" class="btn btn-warning"><i class="bi bi-tags"></i> Revisar categorías</a>
            @endif
            @if (in_array($maeCarga->estado, ['procesado', 'procesado_con_observaciones']))
                <a href="{{ route('endeudamiento.cuotas.create', ['carga_id' => $maeCarga->id]) }}" class="btn btn-outline-success"><i class="bi bi-list-ol"></i> Complementar cuotas</a>
            @endif
            <a href="{{ route('endeudamiento.registros.index', ['anio' => $maeCarga->anio, 'mes' => $maeCarga->mes, 'dominio' => $maeCarga->dominio]) }}" class="btn btn-outline-primary {{ in_array($maeCarga->estado, ['pendiente_revision','pendiente','procesando']) ? 'disabled' : '' }}" {{ in_array($maeCarga->estado, ['pendiente_revision','pendiente','procesando']) ? 'aria-disabled=true tabindex=-1' : '' }}>Ver registros</a>
            <a href="{{ route('endeudamiento.cargas.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif


    @if (in_array($maeCarga->estado, ['pendiente_revision', 'pendiente', 'procesando']))
        <div class="alert alert-info">
            @if ($maeCarga->estado === 'pendiente_revision')
                El archivo está almacenado, pero todavía debes revisar y confirmar sus categorías de descuento.
            @elseif ($maeCarga->estado === 'pendiente')
                Esta carga se está procesando en segundo plano.
                Está en cola esperando al worker de Laravel.
            @else
                Esta carga se está procesando en segundo plano.
                El archivo ya está siendo leído e importado.
            @endif
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Resumen</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Archivo</dt><dd class="col-7">{{ $maeCarga->nombre_archivo }}</dd>
                        <dt class="col-5">Comuna origen</dt><dd class="col-7">{{ $maeCarga->comuna_origen ?: '—' }}</dd>
                        <dt class="col-5">Estado</dt><dd class="col-7">{{ $maeCarga->estado }}</dd>
                        <dt class="col-5">Usuario</dt><dd class="col-7">{{ $maeCarga->subidaPor?->display_name ?? $maeCarga->subidaPor?->nombre_completo ?? '—' }}</dd>
                        <dt class="col-5">Procesado</dt><dd class="col-7">{{ $maeCarga->procesado_at?->format('d-m-Y H:i') ?: '—' }}</dd>
                        <dt class="col-5">Filas</dt><dd class="col-7">{{ number_format($maeCarga->total_filas, 0, ',', '.') }}</dd>
                        <dt class="col-5">Válidas</dt><dd class="col-7">{{ number_format($maeCarga->filas_validas, 0, ',', '.') }}</dd>
                        <dt class="col-5">Observadas</dt><dd class="col-7">{{ number_format($maeCarga->filas_observadas, 0, ',', '.') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Persistencia</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-6">Registros base</dt><dd class="col-6">{{ number_format($resumen['registros'], 0, ',', '.') }}</dd>
                        <dt class="col-6">Descuentos detalle</dt><dd class="col-6">{{ number_format($resumen['descuentos'], 0, ',', '.') }}</dd>
                        <dt class="col-6">Otros descuentos</dt><dd class="col-6">{{ number_format($resumen['otros_descuentos'], 0, ',', '.') }}</dd>
                        <dt class="col-6">Importaciones cuotas</dt><dd class="col-6">{{ number_format($resumen['cuotas_importaciones'], 0, ',', '.') }}</dd>
                        <dt class="col-6">Descuentos con cuota</dt><dd class="col-6">{{ number_format($resumen['descuentos_con_cuota'], 0, ',', '.') }}</dd>
                        <dt class="col-6">Reemplaza</dt><dd class="col-6">{{ $maeCarga->reemplazaCarga ? 'v' . $maeCarga->reemplazaCarga->version : '—' }}</dd>
                    </dl>
                    @if ($maeCarga->motivo_reemplazo)
                        <hr>
                        <div class="small">
                            <div class="fw-semibold mb-1">Motivo de reemplazo</div>
                            <div class="text-muted">{{ $maeCarga->motivo_reemplazo }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Observaciones</h2>
                    @if ($maeCarga->observaciones)
                        <div class="small text-muted">{{ $maeCarga->observaciones }}</div>
                    @else
                        <div class="small text-muted">Sin observaciones globales registradas en la carga.</div>
                    @endif
                    @if (!$maeCarga->es_vigente && in_array($maeCarga->estado, ['procesado', 'procesado_con_observaciones']))
                        <hr>
                        <form method="POST" action="{{ route('endeudamiento.cargas.activar', $maeCarga) }}">
                            @csrf
                            <button class="btn btn-outline-primary btn-sm">Activar esta versión</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Historial de versiones</h2>
                    <div class="list-group list-group-flush">
                        @foreach ($versiones as $version)
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">v{{ $version->version }} · {{ $version->created_at?->format('d-m-Y H:i') }}</div>
                                    <div class="small text-muted">{{ $version->nombre_archivo }}</div>
                                </div>
                                <div class="text-end">
                                    @if ($version->es_vigente)
                                        <span class="badge text-bg-success">Vigente</span>
                                    @endif
                                    <div><a class="small" href="{{ route('endeudamiento.cargas.show', $version) }}">Ver</a></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Muestra de registros importados</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>RUT-DV</th>
                                    <th>Nombre</th>
                                    <th>Imp.</th>
                                    <th>Trib.</th>
                                    <th>Otros</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($muestra as $registro)
                                    <tr>
                                        <td>{{ $registro->rut_dv }}</td>
                                        <td>{{ $registro->nombre_completo }}</td>
                                        <td>{{ number_format((float) $registro->monto_imponible, 0, ',', '.') }}</td>
                                        <td>{{ number_format((float) $registro->monto_tributable, 0, ',', '.') }}</td>
                                        <td>{{ number_format((float) $registro->total_otros_descuentos, 0, ',', '.') }}</td>
                                        <td class="text-end"><a href="{{ route('endeudamiento.registros.show', $registro) }}" class="btn btn-sm btn-outline-primary">Detalle</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">La carga todavía no tiene registros visibles.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
