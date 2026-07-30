@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center mb-4">
        <div class="col-12 col-xl-10">
            <div class="card shadow-sm border-0 bg-light">
                <div class="card-body p-4 p-lg-5">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                        <div>
                            <span class="badge text-bg-primary mb-2">Bolsa de Trabajo</span>
                            <h1 class="h3 mb-2">Ofertas laborales vigentes</h1>
                            <p class="text-muted mb-0">
                                Revisa las ofertas laborales que actualmente se encuentran vigentes en el portal del Servicio Local.
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @auth
                                @if ($isAuthenticatedPortalUser)
                                    <a class="btn btn-primary" href="{{ route('postulant.ofertas-laborales.index') }}">
                                        <i class="bi bi-briefcase me-1"></i> Ir a ofertas laborales
                                    </a>
                                @else
                                    <a class="btn btn-outline-primary" href="{{ route('dashboard') }}">
                                        <i class="bi bi-box-arrow-in-right me-1"></i> Ir al sistema
                                    </a>
                                @endif
                            @else
                                <a class="btn btn-primary" href="{{ route('login') }}">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
                                </a>
                                <a class="btn btn-outline-secondary" href="{{ route('register') }}">
                                    <i class="bi bi-person-plus me-1"></i> Registrarse
                                </a>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center mb-4">
        <div class="col-12 col-xl-10">
            <div class="alert alert-info border-0 shadow-sm mb-0">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill mt-1"></i>
                    <div>
                        <div class="fw-semibold">Para acceder al detalle completo y postular debes iniciar sesión o registrarte.</div>
                        <div class="small mt-1">
                            Esta vista es solo informativa. La postulación a las ofertas laborales se habilita únicamente para usuarios autenticados con perfil y documentos al día.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="row g-3">
                @forelse ($items as $item)
                    <div class="col-12 col-lg-6">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                    <div>
                                        <h2 class="h5 mb-1">{{ $item->establecimientos_display ?: '—' }}</h2>
                                        <div class="text-muted small">RBD {{ $item->rbds_display ?: '—' }} · {{ $item->comuna }}</div>
                                    </div>
                                    <span class="badge text-bg-light border">{{ $item->estamento_label }}</span>
                                </div>

                                <dl class="row small mb-3">
                                    <dt class="col-sm-5">Área de desempeño</dt>
                                    <dd class="col-sm-7">{{ optional($item->areaDesempeno)->nombre ?? '—' }}</dd>
                                    <dt class="col-sm-5">Calidad contractual</dt>
                                    <dd class="col-sm-7">{{ $item->calidad_contractual_label }}</dd>
                                    <dt class="col-sm-5">Horas</dt>
                                    <dd class="col-sm-7">{{ $item->cantidad_horas }}</dd>
                                    <dt class="col-sm-5">Remuneración bruta</dt>
                                    <dd class="col-sm-7">{{ $item->remuneracion_bruta_formatted }}</dd>
                                    <dt class="col-sm-5">Inicio trabajo aprox.</dt>
                                    <dd class="col-sm-7">{{ optional($item->inicio_trabajo_aproximado)->format('d/m/Y') ?? '—' }}</dd>
                                    <dt class="col-sm-5">Inicio postulaciones</dt>
                                    <dd class="col-sm-7">{{ optional($item->fecha_inicio_postulaciones)->format('d/m/Y') }} {{ $item->hora_inicio_postulaciones }}</dd>
                                    <dt class="col-sm-5">Término postulaciones</dt>
                                    <dd class="col-sm-7">{{ optional($item->fecha_termino_postulaciones)->format('d/m/Y') }} {{ $item->hora_termino_postulaciones }}</dd>
                                    <dt class="col-sm-5">Número de postulantes</dt>
                                    <dd class="col-sm-7"><strong>{{ (int) $item->postulaciones_count }}</strong></dd>
                                </dl>

                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                                    <span class="badge text-bg-secondary">Debes iniciar sesión para postular</span>
                                    <div class="d-flex gap-2">
                                        <a class="btn btn-sm btn-primary" href="{{ route('login') }}">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('register') }}">
                                            <i class="bi bi-person-plus me-1"></i> Registrarse
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info mb-0">No hay ofertas laborales vigentes publicadas por el momento.</div>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">{{ $items->links() }}</div>
        </div>
    </div>
</div>
@endsection
