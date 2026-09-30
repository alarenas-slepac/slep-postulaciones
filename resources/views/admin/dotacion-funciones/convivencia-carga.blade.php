@extends('layouts.app')

@section('content')
    @php $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value); @endphp
    <div class="slep-card p-4 mb-4 d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="small text-muted text-uppercase mb-2"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Dotación · Carga anual</div>
            <h1 class="h2 fw-bold mb-2">Horas de Coordinación de Convivencia Educativa</h1>
            <p class="text-muted mb-0">Defina las horas de contrato por establecimiento para el año seleccionado.</p>
        </div>
        <div><a class="btn btn-outline-secondary rounded-pill" href="{{ route('admin.dotacion-funciones.index', ['anio' => $anio]) }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver a dotación</a></div>
    </div>

    @if (session('success'))
        <div class="alert alert-success rounded-4" role="status"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger rounded-4" role="alert">
            <div class="fw-semibold"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>No fue posible completar la carga</div>
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            <div class="small mt-2">Corrija las filas indicadas y vuelva a cargar el archivo. No se guardaron cambios parciales.</div>
        </div>
    @endif

    <form method="GET" class="slep-card p-4 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-sm-5 col-lg-3">
                <label for="convivencia-anio" class="form-label fw-semibold">Año de dotación</label>
                <input id="convivencia-anio" name="anio" type="number" min="2020" max="2100" value="{{ $anio }}" required class="form-control rounded-3">
            </div>
            <div class="col-sm-7"><button class="btn btn-outline-primary rounded-pill" type="submit"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i> Consultar año</button></div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach ([['Establecimientos', $filas->count(), 'Incluidos en la plantilla.'], ['Matrícula registrada', $filas->sum('matricula'), 'Cursos activos del año '.$anio.'.'], ['Horas definidas', $fmt($filas->sum('horas')).' h', 'Valores precargados para el año seleccionado.']] as [$label, $value, $help])
            <div class="col-md-4"><div class="slep-card p-4 h-100"><div class="text-muted mb-2">{{ $label }}</div><div class="h3 fw-bold">{{ $value }}</div><div class="small text-muted">{{ $help }}</div></div></div>
        @endforeach
    </div>

    <div class="slep-card p-4 mb-4">
        <h2 class="h5 fw-bold mb-3"><i class="bi bi-upload me-1" aria-hidden="true"></i> Cargar horas para {{ $anio }}</h2>
        <div class="alert alert-info rounded-4 mb-3" role="note">
            <p class="mb-2">Descargue la plantilla y edite <strong>«N° Hrs de Coord. Conv. Educativa.»</strong>. Incluye RBD, nombre del establecimiento y matrícula registrada de {{ $anio }}.</p>
            <p class="mb-2">Se precargan las horas de la carga anual vigente; si no existe, las asignaciones activas, la definición previa o las horas del catálogo, en ese orden. La matrícula sólo sirve de referencia.</p>
            <p class="mb-0">Admite de 0 a 44 horas y hasta dos decimales. Dejar horas vacías conserva el valor actual; escribir 0 define cero horas. La carga se aplica sólo al año seleccionado.</p>
        </div>
        <a class="btn btn-outline-primary rounded-pill mb-4" href="{{ route('admin.dotacion-funciones.convivencia.plantilla', ['anio' => $anio]) }}"><i class="bi bi-download me-1" aria-hidden="true"></i> Descargar plantilla {{ $anio }}</a>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.dotacion-funciones.convivencia.store') }}">
            @csrf
            <input type="hidden" name="anio" value="{{ $anio }}">
            <label for="convivencia-archivo" class="form-label fw-semibold">Plantilla completada <span class="text-danger">*</span></label>
            <input id="convivencia-archivo" name="archivo" type="file" accept=".xlsx" required class="form-control rounded-3 @error('archivo') is-invalid @enderror" aria-describedby="convivencia-archivo-ayuda">
            <div id="convivencia-archivo-ayuda" class="form-text">Archivo XLSX de hasta 10 MB. Conserve sus hojas, año y encabezados; puede eliminar filas que no desee actualizar.</div>
            @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-upload me-1" aria-hidden="true"></i> Aplicar horas de {{ $anio }}</button></div>
        </form>
    </div>

    <div class="slep-card overflow-hidden">
        <div class="p-4 border-bottom"><h2 class="h5 fw-bold mb-1">Valores vigentes por establecimiento</h2><p class="small text-muted mb-0">Las horas definidas se aplican a la necesidad de la función. Las asignaciones previas se conservan y sus excesos se muestran para revisión.</p></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">RBD</th><th scope="col">Establecimiento</th><th scope="col" class="text-end">Matrícula {{ $anio }}</th><th scope="col" class="text-end">Horas definidas</th><th scope="col" class="text-end">Horas asignadas</th><th scope="col">Estado</th></tr></thead>
                <tbody>
                    @forelse ($filas as $fila)
                        <tr><td>{{ $fila['rbd'] }}</td><td class="fw-semibold">{{ $fila['nombre'] }}</td><td class="text-end">{{ $fila['matricula'] }}</td><td class="text-end fw-semibold">{{ $fmt($fila['horas']) }} h</td><td class="text-end">{{ $fmt($fila['asignadas']) }} h</td><td>
                            <span class="badge rounded-pill {{ $fila['carga_anual'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $fila['carga_anual'] ? 'Carga anual aplicada' : 'Valor precargado' }}</span>
                            @if ($fila['asignadas'] > $fila['horas'] + 0.001)<div class="small text-warning-emphasis mt-1">Exceso asignado: {{ $fmt($fila['asignadas'] - $fila['horas']) }} h. Revise las asignaciones.</div>@endif
                        </td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted p-4">No hay establecimientos registrados para generar la plantilla.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
