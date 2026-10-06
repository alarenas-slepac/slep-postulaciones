@extends('layouts.app')

@section('content')
    <header class="card rounded-4 shadow-sm mb-4">
        <div class="card-body p-4 d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3">
            <div>
                <div class="small text-muted mb-1">Gestión de reemplazos · Administración</div>
                <h1 class="h2 fw-bold mb-2"><i class="bi bi-file-earmark-zip text-primary me-2" aria-hidden="true"></i>Documentos académicos de reemplazantes</h1>
                <p class="text-muted mb-0">Todas las solicitudes aceptadas o cerradas, de todos los años. Una persona por RUT, con sus solicitudes asociadas.</p>
            </div>
            <a class="btn btn-outline-secondary rounded-pill px-4" href="{{ route('gestion.solicitudes-reemplazo.index') }}">Volver a gestión</a>
        </div>
    </header>

    @if ($errors->any())
        <div class="alert alert-danger rounded-4" role="alert">
            <div class="fw-semibold"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>No fue posible descargar los documentos</div>
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="card rounded-4 shadow-sm mb-4" aria-labelledby="descarga-titulo">
        <div class="card-body p-4">
            <div class="row g-3 mb-4">
                <div class="col-md-4"><div class="bg-light rounded-3 p-3 h-100"><div class="text-muted">Personas sin duplicar</div><strong class="fs-3">{{ number_format($personas->total(), 0, ',', '.') }}</strong></div></div>
                <div class="col-md-4"><div class="bg-light rounded-3 p-3 h-100"><div class="text-muted">Solicitudes con perfil disponible</div><strong class="fs-3">{{ number_format($totalSolicitudes, 0, ',', '.') }}</strong></div></div>
                <div class="col-md-4"><div class="bg-light rounded-3 p-3 h-100"><div class="text-muted">Solicitudes sin perfil disponible</div><strong class="fs-3">{{ number_format($sinPerfil, 0, ',', '.') }}</strong></div></div>
            </div>
            <h2 id="descarga-titulo" class="h5 fw-bold">Contenido de la descarga</h2>
            <ul>@foreach ($tipos as $tipo)<li>{{ $tipo }}</li>@endforeach</ul>
            <p>El ZIP incluye una carpeta por persona y <strong>nomina_reemplazos.xlsx</strong>, con los antecedentes académicos, el detalle de solicitudes y la disponibilidad de cada documento.</p>
            <p class="small text-muted">Se incluyen los archivos cargados disponibles, aunque estén pendientes de revisión. Los documentos no cargados o cuyo archivo no está disponible se identifican en la hoja Documentos. Los datos académicos corresponden al perfil registrado; los campos no informados quedan vacíos.</p>
            @if ($sinPerfil > 0)
                <div class="alert alert-warning rounded-3">Las {{ $sinPerfil }} solicitudes sin perfil disponible se detallan en el Excel para revisión; no se atribuyen documentos a otra persona.</div>
            @endif
            @if ($personas->total() > 0)
                <a class="btn btn-primary rounded-pill px-4 fw-semibold" href="{{ route('gestion.solicitudes-reemplazo.documentos-academicos.download') }}"><i class="bi bi-download me-1" aria-hidden="true"></i>Descargar ZIP y nómina Excel</a>
            @else
                <div class="alert alert-info rounded-3 mb-0">No hay reemplazantes con perfil asociado en solicitudes aceptadas o cerradas para descargar.</div>
            @endif
        </div>
    </section>

    <section class="card rounded-4 shadow-sm" aria-labelledby="personas-titulo">
        <div class="card-header bg-light p-3 rounded-top-4"><h2 id="personas-titulo" class="h5 fw-bold mb-1">Personas incluidas</h2><div class="small text-muted">Vista paginada de consulta. La descarga incluye todas las personas, no solo esta página.</div></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Nombre completo</th><th scope="col">RUT</th><th scope="col">Solicitudes asociadas</th><th scope="col">RBD y establecimientos</th><th scope="col">Área de desempeño</th><th scope="col">Documentos disponibles</th></tr></thead>
                <tbody>
                    @forelse ($personas as $persona)
                        <tr>
                            <td class="fw-semibold">{{ $persona['nombre'] }}</td>
                            <td class="text-nowrap">{{ $persona['rut'] ?: 'Sin RUT registrado' }}</td>
                            <td>{{ $persona['numero_solicitudes'] }}</td>
                            <td>@foreach ($persona['solicitudes'] as $solicitud)<div class="small">{{ $solicitud->numero_solicitud ?: 'ID '.$solicitud->request_id }} · {{ $solicitud->rbd ?? 'Sin RBD' }} · {{ $solicitud->nombre_establecimiento ?? 'Sin establecimiento' }}</div>@endforeach</td>
                            <td>{{ $persona['area'] ?: 'Sin área registrada' }}</td>
                            <td><span class="badge rounded-pill {{ $persona['disponibles'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $persona['disponibles'] }} de 4</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted p-4">No hay personas para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $personas->links() }}</div>
    </section>
@endsection
