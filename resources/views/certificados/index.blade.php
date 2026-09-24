@extends('layouts.app')

@section('content')
<div class="cl-page py-4">
    <div class="cl-hero">
        <div class="cl-hero-main">
            <span class="cl-hero-icon" aria-hidden="true"><i class="bi bi-file-earmark-check"></i></span>
            <div>
                <div class="cl-eyebrow">Trámites y operación · Certificados</div>
                <h1 class="cl-hero-title">Certificados laborales</h1>
                <p class="cl-hero-subtitle">
                    Emisión del certificado de vigencia a partir del historial contractual activo.
                </p>
            </div>
        </div>
        @if ($puedeEmitirTerceros)
            <div class="cl-hero-actions">
                <a href="{{ route('certificados.importaciones.index') }}" class="btn btn-outline-primary">
                    <i class="bi bi-database-check" aria-hidden="true"></i> Bases históricas
                </a>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success cl-alert" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger cl-alert" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (! $baseActiva)
        <div class="alert alert-warning cl-alert" role="alert">
            No existe una base histórica activa. No se pueden emitir certificados hasta
            que una importación procesada sea activada.
        </div>
    @else
        <div class="alert alert-light border cl-alert d-flex flex-wrap justify-content-between gap-2">
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
            <div class="card cl-panel h-100">
                <div class="card-header">
                    <div class="cl-panel-kicker">Emisión</div>
                    <h2 class="cl-panel-title">Consultar vigencia</h2>
                </div>
                <div class="card-body">
                    @if ($puedeEmitirTerceros)
                        <form method="GET" action="{{ route('certificados.index') }}" class="row g-3 align-items-end mb-3">
                            <div class="col-12 col-sm">
                                <label for="rut" class="form-label">RUT del funcionario <span class="text-danger">*</span></label>
                                <input
                                    id="rut"
                                    type="text"
                                    name="rut"
                                    value="{{ $rutConsulta }}"
                                    class="form-control @error('rut') is-invalid @enderror"
                                    placeholder="12.345.678-9"
                                    maxlength="20"
                                    required
                                >
                                @error('rut')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-sm-auto">
                                <button class="btn btn-outline-primary w-100" type="submit">
                                    <i class="bi bi-search" aria-hidden="true"></i> Consultar
                                </button>
                            </div>
                        </form>
                    @endif

                    @if ($mensajeConsulta)
                        <div class="alert alert-warning cl-alert mb-0" role="status">{{ $mensajeConsulta }}</div>
                    @elseif ($resultado)
                        <dl class="row g-2 mb-3">
                            <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">Funcionario</dt><dd class="cl-fact-value">{{ $resultado['nombre'] }}</dd></div></div>
                            <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">RUT</dt><dd class="cl-fact-value">{{ $resultado['rut_formateado'] }}</dd></div></div>
                            <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">Antigüedad</dt><dd class="cl-fact-value">{{ $resultado['fecha_antiguedad']->format('d-m-Y') }}</dd></div></div>
                            <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">Calidad</dt><dd class="cl-fact-value">{{ $resultado['calidad_juridica'] }}</dd></div></div>
                            <div class="col-12"><div class="cl-fact"><dt class="cl-fact-label">Régimen</dt><dd class="cl-fact-value">{{ $resultado['regimen_juridico'] }}</dd></div></div>
                            <div class="col-12"><div class="cl-fact"><dt class="cl-fact-label">Vigencia actual</dt><dd class="cl-fact-value">
                                @foreach ($resultado['establecimientos'] as $establecimiento)
                                    <div>
                                        {{ $establecimiento['establecimiento'] }}
                                        <span class="cl-table-meta">· {{ $establecimiento['comuna'] }}</span>
                                    </div>
                                @endforeach
                            </dd></div></div>
                        </dl>

                        <form method="POST" action="{{ route('certificados.emitir') }}">
                            @csrf
                            <input type="hidden" name="rut" value="{{ $resultado['rut_normalizado'] }}">
                            @if ($puedeEmitirTerceros)
                                <div class="cl-section mb-3">
                                    <h3 class="cl-section-title">Datos para este certificado</h3>
                                    <p class="cl-section-help">
                                        Puedes ajustar estos antecedentes antes de emitir. Los cambios
                                        se aplicarán únicamente al certificado y no modificarán la base histórica.
                                    </p>
                                    <div class="mb-3">
                                        <label for="fecha_antiguedad" class="form-label">
                                            Fecha de antigüedad <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="fecha_antiguedad"
                                            type="date"
                                            name="fecha_antiguedad"
                                            value="{{ old('fecha_antiguedad', $resultado['fecha_antiguedad']->format('Y-m-d')) }}"
                                            max="{{ now()->format('Y-m-d') }}"
                                            class="form-control @error('fecha_antiguedad') is-invalid @enderror"
                                            required
                                        >
                                        @error('fecha_antiguedad')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-3">
                                        <label for="calidad_juridica" class="form-label">
                                            Calidad jurídica <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="calidad_juridica"
                                            type="text"
                                            name="calidad_juridica"
                                            value="{{ old('calidad_juridica', $resultado['calidad_juridica']) }}"
                                            maxlength="500"
                                            class="form-control @error('calidad_juridica') is-invalid @enderror"
                                            required
                                        >
                                        @error('calidad_juridica')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div>
                                        <label for="regimen_juridico" class="form-label">
                                            Régimen jurídico <span class="text-danger">*</span>
                                        </label>
                                        <textarea
                                            id="regimen_juridico"
                                            name="regimen_juridico"
                                            rows="3"
                                            maxlength="500"
                                            class="form-control @error('regimen_juridico') is-invalid @enderror"
                                            required
                                        >{{ old('regimen_juridico', $resultado['regimen_juridico']) }}</textarea>
                                        @error('regimen_juridico')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            @endif
                            <button class="btn btn-primary w-100" @disabled(! $baseActiva)>
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                                Emitir certificado de vigencia
                            </button>
                        </form>
                    @elseif (! $puedeEmitirTerceros && $baseActiva)
                        <div class="alert alert-info cl-alert mb-0">
                            No tienes un contrato vigente registrado en la base histórica activa.
                        </div>
                    @else
                        <p class="cl-section-help mb-0">
                            Ingresa un RUT para revisar la continuidad antes de emitir.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card cl-panel h-100">
                <div class="card-header">
                    <div class="cl-panel-kicker">Ayuda</div>
                    <h2 class="cl-panel-title">Cómo se determina la antigüedad</h2>
                </div>
                <div class="card-body">
                    <ul class="cl-help-list">
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
        <div class="card cl-panel mt-4">
            <div class="card-header">
                <div class="cl-panel-kicker">Antecedentes</div>
                <h2 class="cl-panel-title">Historial de contratos</h2>
                <p class="cl-panel-subtitle">
                    Registros de la base histórica activa, ordenados desde la fecha de ingreso más antigua.
                </p>
            </div>
            <div class="table-responsive">
                <table class="table cl-table align-middle">
                    <thead>
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
                                <td class="cl-table-primary">{{ $contrato['establecimiento'] }}</td>
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

    <div id="certificados-emitidos" class="card cl-panel mt-4">
        <div class="card-header">
            <div class="cl-panel-kicker">Documentos</div>
            <h2 class="cl-panel-title">
                {{ $puedeEmitirTerceros ? 'Últimos certificados emitidos' : 'Mis certificados' }}
            </h2>
        </div>
        <div class="table-responsive">
            <table class="table cl-table align-middle">
                <thead>
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
                            <td class="cl-table-primary">{{ $certificado->numero }}</td>
                            <td>
                                <div class="cl-table-primary">{{ $certificado->nombre_snapshot }}</div>
                                <div class="cl-table-meta">{{ $certificado->rut_normalizado }}</div>
                            </td>
                            <td>{{ $certificado->fecha_antiguedad?->format('d-m-Y') }}</td>
                            <td>{{ $certificado->emitido_at?->format('d-m-Y H:i') }}</td>
                            <td>
                                @if ($certificado->estado === 'vigente')
                                    <span class="cl-chip is-success">Vigente</span>
                                @elseif ($certificado->estado === 'anulado')
                                    <span class="cl-chip is-danger">Anulado</span>
                                @else
                                    <span class="cl-chip is-warning">{{ $certificado->estado }}</span>
                                @endif
                            </td>
                            <td><div class="cl-row-actions">
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
                            </div></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="cl-empty">
                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                Todavía no existen certificados emitidos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($historial->hasPages())
            <div class="card-footer">{{ $historial->links() }}</div>
        @endif
    </div>
</div>
@endsection
