@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Certificados laborales</h1>
            <p class="text-muted mb-0">
                Emisión del certificado de vigencia a partir del historial contractual activo.
            </p>
        </div>
        @if ($puedeEmitirTerceros)
            <a href="{{ route('certificados.importaciones.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-database-check"></i> Bases históricas
            </a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (! $baseActiva)
        <div class="alert alert-warning">
            No existe una base histórica activa. No se pueden emitir certificados hasta
            que una importación procesada sea activada.
        </div>
    @else
        <div class="alert alert-light border d-flex flex-wrap justify-content-between gap-2">
            <span>
                <i class="bi bi-database-check text-success"></i>
                Base activa: <strong>{{ $baseActiva->nombre_archivo }}</strong>
            </span>
            <span class="text-muted">
                {{ number_format($baseActiva->filas_validas, 0, ',', '.') }} contratos válidos
                · activada {{ $baseActiva->activado_at?->format('d-m-Y H:i') }}
            </span>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Consultar vigencia</h2>
                    @if ($puedeEmitirTerceros)
                        <form method="GET" action="{{ route('certificados.index') }}" class="row g-2 mb-3">
                            <div class="col">
                                <label for="rut" class="form-label">RUT del funcionario</label>
                                <input
                                    id="rut"
                                    type="text"
                                    name="rut"
                                    value="{{ $rutConsulta }}"
                                    class="form-control"
                                    placeholder="12.345.678-9"
                                    maxlength="20"
                                    required
                                >
                            </div>
                            <div class="col-auto d-flex align-items-end">
                                <button class="btn btn-outline-primary">
                                    <i class="bi bi-search"></i> Consultar
                                </button>
                            </div>
                        </form>
                    @endif

                    @if ($mensajeConsulta)
                        <div class="alert alert-warning mb-0">{{ $mensajeConsulta }}</div>
                    @elseif ($resultado)
                        <dl class="row small mb-3">
                            <dt class="col-sm-4">Funcionario</dt>
                            <dd class="col-sm-8">{{ $resultado['nombre'] }}</dd>
                            <dt class="col-sm-4">RUT</dt>
                            <dd class="col-sm-8">{{ $resultado['rut_formateado'] }}</dd>
                            <dt class="col-sm-4">Antigüedad</dt>
                            <dd class="col-sm-8">{{ $resultado['fecha_antiguedad']->format('d-m-Y') }}</dd>
                            <dt class="col-sm-4">Calidad</dt>
                            <dd class="col-sm-8">{{ $resultado['calidad_juridica'] }}</dd>
                            <dt class="col-sm-4">Régimen</dt>
                            <dd class="col-sm-8">{{ $resultado['regimen_juridico'] }}</dd>
                            <dt class="col-sm-4">Vigencia actual</dt>
                            <dd class="col-sm-8 mb-0">
                                @foreach ($resultado['establecimientos'] as $establecimiento)
                                    <div>
                                        {{ $establecimiento['establecimiento'] }}
                                        <span class="text-muted">· {{ $establecimiento['comuna'] }}</span>
                                    </div>
                                @endforeach
                            </dd>
                        </dl>

                        <form method="POST" action="{{ route('certificados.emitir') }}">
                            @csrf
                            <input type="hidden" name="rut" value="{{ $resultado['rut_normalizado'] }}">
                            @if ($puedeEmitirTerceros)
                                <div class="border rounded bg-light p-3 mb-3">
                                    <h3 class="h6 mb-1">Datos para este certificado</h3>
                                    <p class="small text-muted mb-3">
                                        Puedes ajustar estos antecedentes antes de emitir. Los cambios
                                        se aplicarán únicamente al certificado y no modificarán la base histórica.
                                    </p>
                                    <div class="mb-3">
                                        <label for="fecha_antiguedad" class="form-label">
                                            Fecha de antigüedad
                                        </label>
                                        <input
                                            id="fecha_antiguedad"
                                            type="date"
                                            name="fecha_antiguedad"
                                            value="{{ old('fecha_antiguedad', $resultado['fecha_antiguedad']->format('Y-m-d')) }}"
                                            max="{{ now()->format('Y-m-d') }}"
                                            class="form-control"
                                            required
                                        >
                                    </div>
                                    <div class="mb-3">
                                        <label for="calidad_juridica" class="form-label">
                                            Calidad jurídica
                                        </label>
                                        <input
                                            id="calidad_juridica"
                                            type="text"
                                            name="calidad_juridica"
                                            value="{{ old('calidad_juridica', $resultado['calidad_juridica']) }}"
                                            maxlength="500"
                                            class="form-control"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <label for="regimen_juridico" class="form-label">
                                            Régimen jurídico
                                        </label>
                                        <textarea
                                            id="regimen_juridico"
                                            name="regimen_juridico"
                                            rows="3"
                                            maxlength="500"
                                            class="form-control"
                                            required
                                        >{{ old('regimen_juridico', $resultado['regimen_juridico']) }}</textarea>
                                    </div>
                                </div>
                            @endif
                            <button class="btn btn-primary w-100" @disabled(! $baseActiva)>
                                <i class="bi bi-file-earmark-pdf"></i>
                                Emitir certificado de vigencia
                            </button>
                        </form>
                    @elseif (! $puedeEmitirTerceros && $baseActiva)
                        <div class="alert alert-info mb-0">
                            No tienes un contrato vigente registrado en la base histórica activa.
                        </div>
                    @else
                        <p class="text-muted mb-0">
                            Ingresa un RUT para revisar la continuidad antes de emitir.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Cómo se determina la antigüedad</h2>
                    <ul class="small text-muted ps-3 mb-0">
                        <li>Se parte desde el contrato vigente más reciente.</li>
                        <li>Los períodos superpuestos o consecutivos mantienen continuidad.</li>
                        <li>Una interrupción de al menos un día sin contrato corta la continuidad.</li>
                        <li>Un cambio de régimen jurídico inicia una nueva continuidad.</li>
                        <li>Los cambios de establecimiento o calidad jurídica mantienen continuidad.</li>
                        <li>
                            Un reemplazo docente anterior a contrata, o un reemplazo anterior
                            a plazo fijo, no se incorpora a la antigüedad estable.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    @if ($puedeEmitirTerceros && $resultado)
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-1">Historial de contratos</h2>
                <p class="small text-muted mb-0">
                    Registros de la base histórica activa, ordenados desde la fecha de ingreso más antigua.
                </p>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Establecimiento</th>
                            <th>Fecha de ingreso</th>
                            <th>Fecha de término</th>
                            <th>Calidad jurídica</th>
                            <th>Régimen jurídico</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resultado['historial_contratos'] as $contrato)
                            <tr>
                                <td>{{ $contrato['establecimiento'] }}</td>
                                <td class="text-nowrap">
                                    {{ \Carbon\CarbonImmutable::parse($contrato['fecha_ingreso'])->format('d-m-Y') }}
                                </td>
                                <td class="text-nowrap">
                                    @if ($contrato['termino_indefinido'] || ! $contrato['fecha_finiquito'])
                                        Indefinido
                                    @else
                                        {{ \Carbon\CarbonImmutable::parse($contrato['fecha_finiquito'])->format('d-m-Y') }}
                                    @endif
                                </td>
                                <td>{{ $contrato['calidad_juridica'] }}</td>
                                <td>{{ $contrato['regimen_juridico'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div id="certificados-emitidos" class="card shadow-sm mt-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">
                {{ $puedeEmitirTerceros ? 'Últimos certificados emitidos' : 'Mis certificados' }}
            </h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Número</th>
                        <th>Funcionario</th>
                        <th>Antigüedad</th>
                        <th>Emisión</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($historial as $certificado)
                        <tr>
                            <td class="fw-semibold">{{ $certificado->numero }}</td>
                            <td>
                                <div>{{ $certificado->nombre_snapshot }}</div>
                                <div class="small text-muted">{{ $certificado->rut_normalizado }}</div>
                            </td>
                            <td>{{ $certificado->fecha_antiguedad?->format('d-m-Y') }}</td>
                            <td>{{ $certificado->emitido_at?->format('d-m-Y H:i') }}</td>
                            <td>
                                @if ($certificado->estado === 'vigente')
                                    <span class="badge text-bg-success">Vigente</span>
                                @elseif ($certificado->estado === 'anulado')
                                    <span class="badge text-bg-danger">Anulado</span>
                                @else
                                    <span class="badge text-bg-warning">{{ $certificado->estado }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($certificado->archivo_pdf_path)
                                    <a
                                        href="{{ route('certificados.ver', $certificado) }}"
                                        class="btn btn-sm btn-outline-primary"
                                        target="_blank"
                                        rel="noopener"
                                    >Ver</a>
                                    <a
                                        href="{{ route('certificados.descargar', $certificado) }}"
                                        class="btn btn-sm btn-outline-secondary"
                                    >Descargar</a>
                                @endif
                                @if ($puedeEmitirTerceros && $certificado->estado === 'vigente')
                                    <form
                                        method="POST"
                                        action="{{ route('certificados.anular', $certificado) }}"
                                        class="d-inline"
                                        onsubmit="return confirm('¿Confirmas la anulación de este certificado?')"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="motivo" value="Anulación solicitada por el operador emisor.">
                                        <button class="btn btn-sm btn-outline-danger">Anular</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Todavía no existen certificados emitidos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($historial->hasPages())
            <div class="card-body border-top">{{ $historial->links() }}</div>
        @endif
    </div>
</div>
@endsection
