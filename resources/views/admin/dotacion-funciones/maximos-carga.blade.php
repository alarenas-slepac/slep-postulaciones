@extends('layouts.app')

@section('content')
    @php $fmt = fn ($value) => $value === null ? 'Sin configurar' : \App\Support\DotacionEstablecimientoCalculator::formatHoras($value).' h'; @endphp
    <div class="slep-card p-4 mb-4 d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <div class="small text-muted text-uppercase mb-2"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i> Dotación · Carga anual</div>
            <h1 class="h2 fw-bold mb-2">Máximos de horas por bloque</h1>
            <p class="text-muted mb-0">Establezca los máximos autorizados de contrato por RBD y año para los tres componentes de dotación.</p>
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
            <div class="col-sm-5 col-lg-3"><label for="maximos-anio" class="form-label fw-semibold">Año de dotación</label><input id="maximos-anio" name="anio" type="number" min="2020" max="2100" value="{{ $anio }}" required class="form-control rounded-3"></div>
            <div class="col-sm-7"><button class="btn btn-outline-primary rounded-pill" type="submit"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i> Consultar año</button></div>
        </div>
    </form>
    <div class="row g-3 mb-4">
        @foreach ([['Establecimientos', $filas->count(), 'Incluidos en la plantilla.'], ['Matrícula registrada', $filas->sum('matricula'), 'Cursos activos del año '.$anio.'.'], ['Bloques configurables', 3, 'Plan general, Parvularia y PIE especializado.']] as [$label, $value, $help])
            <div class="col-md-4"><div class="slep-card p-4 h-100"><div class="text-muted mb-2">{{ $label }}</div><div class="h3 fw-bold">{{ $value }}</div><div class="small text-muted">{{ $help }}</div></div></div>
        @endforeach
    </div>
    <div class="slep-card p-4 mb-4">
        <h2 class="h5 fw-bold mb-3"><i class="bi bi-upload me-1" aria-hidden="true"></i> Cargar máximos para {{ $anio }}</h2>
        <div class="alert alert-info rounded-4 mb-3" role="note">
            <p class="mb-2">Descargue la plantilla con todos los RBD, nombres, matrícula y máximos guardados en {{ $anio }}. Edite las columnas de máximos de <strong>Plan general</strong>, <strong>Educación Parvularia</strong> y <strong>PIE especializado</strong>.</p>
            <p class="mb-0">Cada celda vacía conserva el valor actual de ese bloque; 0 establece un máximo de cero. Admite entre 0 y 9999 horas y hasta dos decimales. Las asignaciones, reservas y otras configuraciones se conservan.</p>
        </div>
        <a class="btn btn-outline-primary rounded-pill mb-4" href="{{ route('admin.dotacion-funciones.maximos.plantilla', ['anio' => $anio]) }}"><i class="bi bi-download me-1" aria-hidden="true"></i> Descargar plantilla {{ $anio }}</a>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.dotacion-funciones.maximos.store') }}">
            @csrf
            <input type="hidden" name="anio" value="{{ $anio }}">
            <label for="maximos-archivo" class="form-label fw-semibold">Plantilla completada <span class="text-danger">*</span></label>
            <input id="maximos-archivo" name="archivo" type="file" accept=".xlsx" required class="form-control rounded-3 @error('archivo') is-invalid @enderror" aria-describedby="maximos-archivo-ayuda">
            <div id="maximos-archivo-ayuda" class="form-text">Archivo XLSX de hasta 10 MB. Conserve sus hojas, año y encabezados; puede eliminar filas que no desee actualizar.</div>
            @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="d-flex justify-content-end mt-3"><button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-upload me-1" aria-hidden="true"></i> Aplicar máximos de {{ $anio }}</button></div>
        </form>
    </div>
    <div class="slep-card overflow-hidden">
        <div class="p-4 border-bottom"><h2 class="h5 fw-bold mb-1">Máximos vigentes por establecimiento</h2><p class="small text-muted mb-0">Un máximo inferior a la necesidad obligatoria mantiene el bloqueo del proceso guiado. Los bloques sin máximo quedan pendientes de configuración.</p></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead class="table-light"><tr><th scope="col">RBD</th><th scope="col">Establecimiento</th><th scope="col" class="text-end">Matrícula {{ $anio }}</th><th scope="col" class="text-end">Plan general + PIE y funciones normativas</th><th scope="col" class="text-end">Parvularia + PIE NT1/NT2</th><th scope="col" class="text-end">PIE especializado</th></tr></thead>
            <tbody>
                @forelse ($filas as $fila)
                    <tr><td>{{ $fila['rbd'] }}</td><td class="fw-semibold">{{ $fila['nombre'] }}</td><td class="text-end">{{ $fila['matricula'] }}</td>@foreach (\App\Exports\DotacionMaximosBloqueExport::COLUMNAS as $campo)<td class="text-end {{ $fila[$campo] === null ? 'text-muted' : 'fw-semibold' }}">{{ $fmt($fila[$campo]) }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted p-4">No hay establecimientos registrados para generar la plantilla.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
@endsection
