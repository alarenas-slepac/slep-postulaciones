@extends('layouts.app')

@push('styles')
    @vite('resources/css/dotacion-establecimiento.css')
@endpush

@section('content')
<div class="dotacion-workspace row">
    <div class="col-12 col-xl-8">
        <section class="card shadow-sm" aria-labelledby="financiamiento-titulo">
            <div class="card-body p-3 p-md-4">
                <p class="small text-muted mb-2">Dotación establecimiento</p>
                <h1 id="financiamiento-titulo" class="h3 fw-bold mb-3">Excel por financiamiento {{ $anio }}</h1>
                <dl class="row small mb-3">
                    <dt class="col-sm-3">Comuna</dt><dd class="col-sm-9 text-break">{{ $filtros['comuna'] ?: 'Todas' }}</dd>
                    <dt class="col-sm-3">Búsqueda</dt><dd class="col-sm-9 text-break">{{ $filtros['q'] ?: 'Todos los establecimientos' }}</dd>
                </dl>

                @if (session('financiamiento_aviso'))
                    <div class="alert alert-info" role="status">{{ session('financiamiento_aviso') }}</div>
                @endif
                @error('financiamiento')
                    <div class="alert alert-danger" role="alert">{{ $message }}</div>
                @enderror

                @if (! $disponible)
                    <div class="alert alert-warning" role="status">La exportación todavía no está disponible. Contacte a soporte.</div>
                @elseif (in_array($estado, ['pendiente', 'procesando'], true))
                    <div class="alert alert-info" role="status">
                        <p class="fw-semibold mb-1" id="financiamiento-estado">{{ $estado === 'pendiente' ? 'En cola' : 'Generando Excel' }}</p>
                        <p class="mb-0">Puede seguir trabajando y volver desde «Ver última exportación». La descarga se habilitará aquí al terminar.</p>
                    </div>
                @elseif ($estado === 'listo')
                    <div class="alert alert-success" role="status">El archivo está listo. Disponible hasta el {{ $solicitud->expires_at->format('d/m/Y H:i') }}.</div>
                @elseif ($estado === 'vencido')
                    <div class="alert alert-warning" role="status">La solicitud venció. Puede preparar otro Excel con los mismos filtros.</div>
                @elseif ($estado === 'error')
                    <div class="alert alert-danger" role="alert">No se pudo completar la exportación. Vuelva a solicitarla y, si el problema persiste, contacte a soporte.</div>
                @else
                    <p>El informe se prepara en segundo plano e incluye todos los establecimientos de los filtros indicados.</p>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    @if ($estado === 'listo')
                        <a class="btn btn-primary rounded-pill" href="{{ route('admin.dotacion-establecimiento.financiamiento.download', $solicitud->id) }}"><i class="bi bi-download me-1" aria-hidden="true"></i> Descargar Excel</a>
                    @elseif (in_array($estado, ['pendiente', 'procesando'], true))
                        <a id="financiamiento-actualizar" class="btn btn-outline-primary rounded-pill" href="{{ route('admin.dotacion-establecimiento.financiamiento.show', $solicitud->id) }}">Actualizar estado</a>
                    @elseif ($disponible)
                        <form method="POST" action="{{ route('admin.dotacion-establecimiento.financiamiento.store') }}">
                            @csrf
                            <input type="hidden" name="anio" value="{{ $anio }}">
                            <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                            <input type="hidden" name="comuna" value="{{ $filtros['comuna'] }}">
                            <button type="submit" class="btn btn-primary rounded-pill">Preparar Excel</button>
                        </form>
                    @endif
                    <a class="btn btn-outline-secondary rounded-pill" href="{{ route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'anio' => $anio] + $filtros) }}">Volver al informe</a>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@if (in_array($estado, ['pendiente', 'procesando'], true))
    @push('scripts')
        <script>
            (() => {
                const actualizar = document.getElementById('financiamiento-actualizar');
                const estado = document.getElementById('financiamiento-estado');

                const consultar = async () => {
                    if (!document.hidden) {
                        try {
                            const respuesta = await fetch(actualizar.href, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                            if (respuesta.redirected || [401, 403, 404, 419].includes(respuesta.status)) return;
                            if (respuesta.ok) {
                                const datos = await respuesta.json();
                                if (!['pendiente', 'procesando'].includes(datos.estado)) {
                                    window.location.reload();
                                    return;
                                }
                                const texto = datos.estado === 'procesando' ? 'Generando Excel' : 'En cola';
                                if (estado.textContent !== texto) estado.textContent = texto;
                            }
                        } catch {
                            // La consulta manual permanece disponible.
                        }
                    }
                    window.setTimeout(consultar, 8000);
                };
                window.setTimeout(consultar, 4000);
            })();
        </script>
    @endpush
@endif
