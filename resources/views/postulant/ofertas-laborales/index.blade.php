@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-1">Ofertas Laborales</h2>
            <p class="text-muted mb-0">Listado de ofertas publicadas desde la Bolsa de Trabajo del Servicio Local.</p>
        </div>
    </div>

    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="alert {{ $eligibility['eligible'] ? 'alert-success' : 'alert-warning' }} mb-4">
        <div class="d-flex align-items-start gap-2">
            <i class="bi {{ $eligibility['eligible'] ? 'bi-check-circle' : 'bi-exclamation-triangle' }}"></i>
            <div>
                <div class="fw-semibold">{{ $eligibility['eligible'] ? 'Puedes postular a las ofertas laborales' : 'No puedes postular por ahora' }}</div>
                <div>{{ $eligibility['message'] }}</div>
                @if (!empty($eligibility['missing_docs']))
                    <div class="small mt-2"><strong>Documentos pendientes:</strong> {{ implode(', ', $eligibility['missing_docs']) }}</div>
                @endif
                @if (!empty($eligibility['rejected_docs']))
                    <div class="small mt-2"><strong>Documentos rechazados:</strong> {{ implode(', ', $eligibility['rejected_docs']) }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        @forelse ($items as $item)
            @php $alreadyApplied = in_array((int) $item->id, $appliedIds, true); @endphp
            <div class="col-12 col-lg-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <h3 class="h5 mb-1">{{ $item->establecimientos_display ?: '—' }}</h3>
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
                            <dt class="col-sm-5">Correo de contacto</dt>
                            <dd class="col-sm-7">{{ $item->correo_contacto }}</dd>
                            <dt class="col-sm-5">Número de postulantes</dt>
                            <dd class="col-sm-7"><strong>{{ (int) $item->postulaciones_count }}</strong></dd>
                            <dt class="col-sm-5">Bases</dt>
                            <dd class="col-sm-7">
                                @if (!empty($item->bases_pdf_path))
                                    <a href="{{ route('postulant.ofertas-laborales.bases', $item) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-file-earmark-pdf"></i> Descargar PDF
                                    </a>
                                @else
                                    —
                                @endif
                            </dd>
                        </dl>

                        <div class="d-flex justify-content-between align-items-center gap-2">
                            @if ($alreadyApplied)
                                <span class="badge text-bg-success">Ya postulaste</span>
                            @elseif ($eligibility['eligible'])
                                <span class="badge text-bg-primary">Habilitado para postular</span>
                            @else
                                <span class="badge text-bg-warning">No habilitado para postular</span>
                            @endif

                            <form method="POST" action="{{ route('postulant.ofertas-laborales.store', $item) }}">
                                @csrf
                                <button class="btn btn-primary" type="submit" @disabled(!$eligibility['eligible'] || $alreadyApplied)>
                                    <i class="bi bi-send"></i> Postular
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info mb-0">No hay ofertas laborales publicadas por el momento.</div>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
