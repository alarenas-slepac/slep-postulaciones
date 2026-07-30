@extends('layouts.app')

@push('styles')
    <style>
        .fac-page-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            border: 1px solid #d9e4f3;
            border-radius: 24px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .fac-page-header__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem 1.75rem 1.25rem;
        }

        .fac-page-header__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            margin-bottom: .45rem;
        }

        .fac-page-header__eyebrow-icon {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 10px 24px rgba(37, 99, 235, .28);
            font-size: 1.2rem;
        }

        .fac-page-header__title {
            font-size: clamp(1.7rem, 2vw, 2.2rem);
            line-height: 1.1;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .4rem;
        }

        .fac-page-header__subtitle {
            color: #475569;
            font-size: 1rem;
            margin-bottom: 0;
            max-width: 60rem;
        }

        .fac-role-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .65rem 1rem;
            border-radius: 999px;
            border: 1px solid #cfe0ff;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            white-space: nowrap;
        }

        .fac-summary-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            padding: 1.2rem 1.75rem 1.75rem;
            border-top: 1px solid #e5edf6;
            background: linear-gradient(180deg, #fcfdff 0%, #f8fbff 100%);
        }

        .fac-summary-card {
            border: 1px solid #dbe6f2;
            border-radius: 18px;
            background: #fff;
            padding: 1rem 1.1rem;
            min-height: 100%;
        }

        .fac-summary-card__label {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .82rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .4rem;
        }

        .fac-summary-card__value {
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin-bottom: .4rem;
        }

        .fac-summary-card__help {
            color: #64748b;
            font-size: .88rem;
            margin: 0;
        }

        .fac-panel {
            border: 1px solid #d9e4f3;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
            overflow: hidden;
        }

        .fac-panel__header {
            padding: 1.25rem 1.5rem 1rem;
            border-bottom: 1px solid #e8eef5;
        }

        .fac-panel__eyebrow {
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: .35rem;
        }

        .fac-panel__title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .35rem;
        }

        .fac-panel__subtitle {
            color: #64748b;
            margin-bottom: 0;
        }

        .fac-panel__body {
            padding: 1.4rem 1.5rem 1.5rem;
        }

        .fac-filter-card {
            margin: 0 1rem 1rem;
            padding: 1rem;
            border: 1px solid #dbe6f2;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .fac-filter-grid {
            display: grid;
            grid-template-columns: 1fr 1.4fr 1.4fr auto;
            gap: .85rem;
            align-items: end;
        }

        .fac-filter-label {
            font-size: .78rem;
            font-weight: 800;
            color: #334155;
            margin-bottom: .35rem;
        }

        .fac-filter-actions {
            display: flex;
            gap: .5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        @media (max-width: 991.98px) {
            .fac-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        .fac-form-label {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .55rem;
        }

        .fac-btn-primary,
        .fac-btn-secondary,
        .fac-btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            min-height: 44px;
            border-radius: 14px;
            font-weight: 800;
            padding: .75rem 1.15rem;
            text-decoration: none;
            transition: .2s ease;
        }

        .fac-btn-primary {
            border: 1px solid #2563eb;
            background: #2563eb;
            color: #fff;
            box-shadow: 0 12px 24px rgba(37, 99, 235, .22);
        }

        .fac-btn-primary:hover { color: #fff; background: #1d4ed8; border-color: #1d4ed8; }

        .fac-btn-secondary {
            border: 1px solid #d9e4f3;
            background: #fff;
            color: #0f172a;
        }

        .fac-btn-secondary:hover { color: #0f172a; background: #f8fafc; }

        .fac-btn-outline {
            border: 1px solid #cfe0ff;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .fac-btn-outline:hover { color: #1d4ed8; background: #dbeafe; }

        .fac-table-wrap {
            padding: 0 1.25rem 1.25rem;
        }

        .fac-table {
            margin-bottom: 0;
            font-size: .78rem;
            line-height: 1.25;
        }

        .fac-table thead th {
            background: #f8fafc;
            color: #334155;
            font-weight: 800;
            font-size: .76rem;
            border-bottom: 1px solid #dbe6f2;
            border-top: 0;
            padding: .55rem .45rem;
            white-space: nowrap;
            vertical-align: middle;
        }

        .fac-table tbody td {
            padding: .55rem .45rem;
            vertical-align: middle;
            border-color: #e8eef5;
        }

        .fac-name {
            font-size: .82rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .15rem;
            line-height: 1.18;
            max-width: 160px;
        }

        .fac-meta {
            color: #64748b;
            font-size: .72rem;
            line-height: 1.25;
        }

        .fac-meta--cargo {
            max-width: 160px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }


        .fac-contact-icons {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .28rem;
            min-width: 52px;
            white-space: nowrap;
        }

        .fac-contact-icon {
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: .82rem;
            text-decoration: none;
        }

        .fac-contact-icon:hover {
            color: #1d4ed8;
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .fac-contact-icon--empty {
            border-color: #e2e8f0;
            background: #f8fafc;
            color: #94a3b8;
            cursor: default;
        }

        .fac-cell-run { width: 92px; min-width: 92px; }
        .fac-cell-funcionario { width: 180px; min-width: 180px; }
        .fac-cell-unidad { width: 210px; min-width: 210px; max-width: 210px; }
        .fac-cell-subdireccion { width: 190px; min-width: 190px; max-width: 190px; }
        .fac-cell-calidad { width: 112px; min-width: 112px; }
        .fac-cell-escalafon { width: 104px; min-width: 104px; }
        .fac-cell-grado { width: 62px; min-width: 62px; text-align: center; }
        .fac-cell-jefatura { width: 84px; min-width: 84px; text-align: center; }
        .fac-cell-estado { width: 88px; min-width: 88px; }
        .fac-cell-usuario { width: 100px; min-width: 100px; }
        .fac-cell-acciones { width: 88px; min-width: 88px; }

        .fac-compact-text {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .fac-wrap-text {
            display: block;
            max-width: 100%;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: normal;
            line-height: 1.22;
        }

        .fac-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .25rem;
            border-radius: 999px;
            padding: .25rem .45rem;
            font-size: .7rem;
            font-weight: 800;
            line-height: 1;
            border: 1px solid transparent;
            white-space: nowrap;
            max-width: 100%;
        }

        .fac-badge--wrap {
            white-space: normal;
            text-align: left;
            justify-content: flex-start;
            line-height: 1.18;
            max-width: 100%;
        }

        .fac-badge--success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .fac-badge--info { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
        .fac-badge--warning { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .fac-badge--muted { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
        .fac-badge--primary { background: #eff6ff; color: #1d4ed8; border-color: #cfe0ff; }
        .fac-badge--indigo { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }


        .fac-btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .25rem;
            min-height: 30px;
            border-radius: 10px;
            padding: .35rem .55rem;
            font-size: .72rem;
            font-weight: 800;
            text-decoration: none;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            white-space: nowrap;
        }

        .fac-btn-action:hover {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .fac-empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #64748b;
        }

        .fac-empty-state__icon {
            width: 4rem;
            height: 4rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-size: 1.55rem;
            margin-bottom: 1rem;
        }

        .fac-help-list {
            display: grid;
            gap: .75rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .fac-help-list li {
            display: flex;
            gap: .65rem;
            align-items: flex-start;
            color: #334155;
            font-size: .92rem;
        }

        .fac-help-list i {
            color: #2563eb;
            margin-top: .08rem;
        }

        .fac-pagination {
            padding: 1rem 1.5rem 1.4rem;
            border-top: 1px solid #e8eef5;
        }


        .fac-table-panel .fac-panel__header {
            padding: .9rem 1rem .75rem;
        }

        .fac-table-panel .fac-panel__title {
            font-size: 1.08rem;
            margin-bottom: .2rem;
        }

        .fac-table-panel .fac-panel__subtitle {
            font-size: .84rem;
        }

        .fac-table-panel .fac-table-wrap {
            padding: 0 .65rem .65rem;
        }

        .fac-table-panel .table-responsive {
            overflow-x: auto;
        }

        .fac-table-panel table {
            min-width: 1140px;
            table-layout: fixed;
        }

        @media (max-width: 991.98px) {
            .fac-summary-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .fac-page-header__top {
                flex-direction: column;
            }

            .fac-summary-strip {
                grid-template-columns: 1fr;
                padding: 1rem 1rem 1.25rem;
            }

            .fac-panel__header,
            .fac-panel__body {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .fac-table-wrap {
                padding: 0 1rem 1rem;
            }
        }
    </style>
@endpush

@section('content')
@php
    $rutasImportar = [
        'cargas-familiares.funcionarios-ac.import.store',
        'cargas-familiares.funcionarios-ac.store',
        'funcionarios-ac.import.store',
        'funcionarios-ac.importar',
        'funcionarios-ac.store',
    ];

    $rutaImportar = url()->current();
    foreach ($rutasImportar as $nombreRuta) {
        if (Route::has($nombreRuta)) {
            $rutaImportar = route($nombreRuta);
            break;
        }
    }

    $rutasPlantilla = [
        'cargas-familiares.funcionarios-ac.plantilla',
        'funcionarios-ac.plantilla',
        'funcionarios-ac.template',
    ];

    $rutaPlantilla = null;
    foreach ($rutasPlantilla as $nombreRuta) {
        if (Route::has($nombreRuta)) {
            $rutaPlantilla = route($nombreRuta);
            break;
        }
    }

    $rutaExportarFuncionarioAc = 'tramites.cargas-familiares.admin.funcionarios-ac.export';
    $puedeExportarFuncionarioAc = Route::has($rutaExportarFuncionarioAc);

    $filtrosFuncionariosAc = $filters ?? [
        'rut' => request('rut', ''),
        'nombre' => request('nombre', ''),
        'subdireccion' => request('subdireccion', ''),
    ];
    $hayFiltrosFuncionariosAc = collect($filtrosFuncionariosAc)->filter(fn ($valor) => trim((string) $valor) !== '')->isNotEmpty();

    $rutaCrearFuncionarioAc = 'tramites.cargas-familiares.admin.funcionarios-ac.create';
    $puedeCrearFuncionarioAc = Route::has($rutaCrearFuncionarioAc);

    $rutaEditarFuncionarioAc = 'tramites.cargas-familiares.admin.funcionarios-ac.edit';
    $puedeEditarFuncionarioAc = Route::has($rutaEditarFuncionarioAc);

    $rutaJefaturasAc = 'tramites.cargas-familiares.admin.funcionarios-ac.jefaturas.index';
    $puedeVerJefaturasAc = Route::has($rutaJefaturasAc);

    $fuenteRegistros = $ultimosAutorizados
        ?? $ultimosFuncionarios
        ?? $funcionariosAcAutorizados
        ?? $funcionariosAc
        ?? $autorizados
        ?? $funcionarios
        ?? collect();

    $registros = $fuenteRegistros instanceof \Illuminate\Pagination\AbstractPaginator
        ? $fuenteRegistros->getCollection()
        : collect($fuenteRegistros);

    $totalRegistros = $fuenteRegistros instanceof \Illuminate\Pagination\AbstractPaginator
        ? $fuenteRegistros->total()
        : $registros->count();

    $valorCampo = function ($item, array $campos, $default = '-') {
        foreach ($campos as $campo) {
            $valor = data_get($item, $campo);
            if ($valor !== null && $valor !== '') {
                return $valor;
            }
        }
        return $default;
    };

    $nombreCompleto = function ($item) use ($valorCampo) {
        $directo = $valorCampo($item, ['nombre_completo', 'nombre', 'funcionario_nombre', 'nombres_completos'], null);
        if ($directo) {
            return $directo;
        }

        $partes = array_filter([
            $valorCampo($item, ['nombres'], ''),
            $valorCampo($item, ['apellido_paterno'], ''),
            $valorCampo($item, ['apellido_materno'], ''),
        ]);

        return $partes ? trim(implode(' ', $partes)) : '-';
    };

    $runCompleto = function ($item) use ($valorCampo) {
        $directo = $valorCampo($item, ['run_completo', 'rut_completo', 'rut_normalizado', 'rut', 'run'], null);
        $dv = $valorCampo($item, ['dv', 'digito_verificador'], null);

        if ($directo && $dv && ! str_contains((string) $directo, '-')) {
            return $directo . '-' . $dv;
        }

        return $directo ?: '-';
    };

    $extraerDesdeObservacion = function ($observacion, $campo) {
        $observacion = trim((string) $observacion);
        if ($observacion === '') {
            return null;
        }

        $patrones = [
            'unidad' => '/Unidad:\s*(.*?)(?:\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'subdireccion_dependencia' => '/Subdirecci[oó]n dependencia:\s*(.*?)(?:\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'escalafon' => '/Escalaf[oó]n:\s*(.*?)(?:\s+Calidad jur[ií]dica:|$)/iu',
            'calidad_juridica' => '/Calidad jur[ií]dica:\s*(.*?)$/iu',
        ];

        if (! isset($patrones[$campo])) {
            return null;
        }

        if (preg_match($patrones[$campo], $observacion, $coincidencia)) {
            return trim($coincidencia[1] ?? '') ?: null;
        }

        return null;
    };

    $activos = (int) data_get($stats ?? [], 'activos', $registros->filter(function ($item) use ($valorCampo) {
        $estado = strtolower((string) $valorCampo($item, ['estado_autorizacion', 'estado', 'activo'], ''));
        return in_array($estado, ['activo', 'activa', 'vigente', '1', 'si', 'sí'], true);
    })->count());

    $vinculados = (int) data_get($stats ?? [], 'vinculados', $registros->filter(function ($item) use ($valorCampo) {
        $usuario = data_get($item, 'usuario') ?? data_get($item, 'user') ?? data_get($item, 'usuario_asociado') ?? null;
        $estadoUsuario = strtolower((string) $valorCampo($item, ['estado_usuario', 'usuario_estado'], ''));
        return $usuario || in_array($estadoUsuario, ['vinculado', 'asignado', 'creado'], true);
    })->count());

    $pendientes = (int) data_get($stats ?? [], 'pendientes', max($totalRegistros - $vinculados, 0));

    $badgeEstado = function ($estado) {
        $estado = trim((string) $estado);
        $normalizado = strtolower($estado);
        $clase = in_array($normalizado, ['activo', 'activa', 'vigente', '1', 'si', 'sí'], true)
            ? 'fac-badge fac-badge--success'
            : 'fac-badge fac-badge--muted';

        return '<span class="' . $clase . '"><i class="bi bi-check2-circle"></i>' . e($estado ?: 'Sin estado') . '</span>';
    };

    $badgeUsuario = function ($item) use ($valorCampo) {
        $usuario = data_get($item, 'usuario') ?? data_get($item, 'user') ?? data_get($item, 'usuario_asociado') ?? null;
        $estadoUsuario = strtolower((string) $valorCampo($item, ['estado_usuario', 'usuario_estado'], ''));
        $vinculado = $usuario || in_array($estadoUsuario, ['vinculado', 'asignado', 'creado'], true);

        if ($vinculado) {
            return '<span class="fac-badge fac-badge--info"><i class="bi bi-person-check"></i>Vinculado</span>';
        }

        return '<span class="fac-badge fac-badge--warning"><i class="bi bi-person-plus"></i>Pendiente</span>';
    };
@endphp

<div class="container-fluid py-3 px-3">
    <div class="fac-page-header mb-4">
        <div class="fac-page-header__top">
            <div>
                <div class="fac-page-header__eyebrow">
                    <span class="fac-page-header__eyebrow-icon"><i class="bi bi-building-gear"></i></span>
                    <span>Administración Central · Carga masiva</span>
                </div>
                <h1 class="fac-page-header__title">Funcionarios AC autorizados</h1>
                <p class="fac-page-header__subtitle">
                    Carga y revisión de funcionarios autorizados para operar como Funcionario Administración Central en la plataforma SLEP.
                    La tabla visible prioriza datos administrativos: unidad, subdirección dependencia, calidad jurídica, escalafón, grado y estado de usuario. Email y mensaje se mantienen sólo como campos internos de carga cuando el importador los requiera.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="fac-role-pill">
                    <i class="bi bi-person-badge"></i>
                    Vista administración
                </span>

                @if($puedeCrearFuncionarioAc)
                    <a href="{{ route($rutaCrearFuncionarioAc) }}" class="fac-btn-primary">
                        <i class="bi bi-person-plus"></i>
                        Crear funcionario AC
                    </a>
                @endif

                @if($puedeVerJefaturasAc)
                    <a href="{{ route($rutaJefaturasAc) }}" class="fac-btn-secondary">
                        <i class="bi bi-diagram-3"></i>
                        Jefaturas y subrogancias
                    </a>
                @endif

                @if($rutaPlantilla)
                    <a href="{{ $rutaPlantilla }}" class="fac-btn-outline">
                        <i class="bi bi-file-earmark-excel"></i>
                        Descargar plantilla
                    </a>
                @endif
            </div>
        </div>

        <div class="fac-summary-strip">
            <div class="fac-summary-card">
                <div class="fac-summary-card__label"><i class="bi bi-people"></i> Registros visibles</div>
                <div class="fac-summary-card__value">{{ number_format($totalRegistros, 0, ',', '.') }}</div>
                <p class="fac-summary-card__help">Funcionarios AC en la tabla.</p>
            </div>
            <div class="fac-summary-card">
                <div class="fac-summary-card__label"><i class="bi bi-check2-circle"></i> Activos</div>
                <div class="fac-summary-card__value">{{ number_format($activos, 0, ',', '.') }}</div>
                <p class="fac-summary-card__help">Con autorización vigente.</p>
            </div>
            <div class="fac-summary-card">
                <div class="fac-summary-card__label"><i class="bi bi-person-check"></i> Vinculados</div>
                <div class="fac-summary-card__value">{{ number_format($vinculados, 0, ',', '.') }}</div>
                <p class="fac-summary-card__help">Con usuario existente o asociado.</p>
            </div>
            <div class="fac-summary-card">
                <div class="fac-summary-card__label"><i class="bi bi-hourglass-split"></i> Pendientes</div>
                <div class="fac-summary-card__value">{{ number_format($pendientes, 0, ',', '.') }}</div>
                <p class="fac-summary-card__help">Autorizados sin usuario vinculado.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>No fue posible procesar la carga.</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="fac-panel h-100">
                <div class="fac-panel__header">
                    <div class="fac-panel__eyebrow">Carga masiva</div>
                    <h2 class="fac-panel__title">Subir archivo de nómina</h2>
                    <p class="fac-panel__subtitle">
                        Selecciona la plantilla Excel compatible con el sistema. La carga mantiene las columnas internas necesarias para reconocimiento del archivo e incorpora calidad jurídica, escalafón y grado.
                    </p>
                </div>

                <div class="fac-panel__body">
                    <form method="POST" action="{{ $rutaImportar }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="archivo" class="form-label fac-form-label">Archivo Excel</label>
                            <input id="archivo" name="archivo" type="file" accept=".xlsx,.xls,.csv" required class="form-control form-control-lg">
                            <div class="form-text">Formato recomendado: .xlsx usando la plantilla compatible. No elimines columnas internas de la plantilla aunque no se muestren en la tabla visual.</div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            @if($rutaPlantilla)
                                <a href="{{ $rutaPlantilla }}" class="fac-btn-secondary">
                                    <i class="bi bi-download"></i>
                                    Plantilla
                                </a>
                            @endif
                            <button type="submit" class="fac-btn-primary">
                                <i class="bi bi-cloud-arrow-up"></i>
                                Cargar funcionarios AC
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="fac-panel h-100">
                <div class="fac-panel__header">
                    <div class="fac-panel__eyebrow">Tabla de revisión</div>
                    <h2 class="fac-panel__title">Datos visibles</h2>
                    <p class="fac-panel__subtitle">Vista simplificada para revisión administrativa; la carga usa plantilla compatible con columnas internas.</p>
                </div>
                <div class="fac-panel__body">
                    <ul class="fac-help-list">
                        <li><i class="bi bi-person-vcard"></i><span>RUN y nombre del funcionario.</span></li>
                        <li><i class="bi bi-diagram-3"></i><span>Unidad o dependencia.</span></li>
                        <li><i class="bi bi-diagram-2"></i><span>Subdirección dependencia.</span></li>
                        <li><i class="bi bi-card-checklist"></i><span>Calidad jurídica, escalafón y grado.</span></li>
                        <li><i class="bi bi-shield-check"></i><span>Marca de jefatura para matriz de autorización y subrogancias.</span></li>
                        <li><i class="bi bi-eye-slash"></i><span>Email y mensaje quedan ocultos en la tabla, pero la plantilla los conserva para que el sistema reconozca el archivo.</span></li>
                        <li><i class="bi bi-person-plus"></i><span>El botón Crear funcionario AC permite registrar manualmente docentes o asistentes que trabajan en el SLEP sin exigir grado.</span></li>
                        <li><i class="bi bi-pencil-square"></i><span>El botón Editar permite corregir unidad, subdirección dependencia, calidad jurídica, escalafón, grado y antecedentes del registro.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="fac-panel fac-table-panel">
        <div class="fac-panel__header">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <div class="fac-panel__eyebrow">Registro administrativo</div>
                    <h2 class="fac-panel__title">Últimos funcionarios AC autorizados</h2>
                    <p class="fac-panel__subtitle">
                        Vista compacta Bootstrap para revisar RUN, funcionario, unidad, subdirección, calidad jurídica, escalafón, grado, jefatura, estado y acciones.
                    </p>
                </div>
                <div class="d-flex align-items-start gap-2 flex-wrap justify-content-md-end">
                    @if($puedeExportarFuncionarioAc)
                        <a href="{{ route($rutaExportarFuncionarioAc, request()->only(['rut', 'nombre', 'subdireccion'])) }}" class="fac-btn-outline">
                            <i class="bi bi-file-earmark-excel"></i>
                            Exportar XLSX
                        </a>
                    @endif
                    <span class="fac-badge {{ $hayFiltrosFuncionariosAc ? 'fac-badge--info' : 'fac-badge--primary' }}">
                        <i class="bi {{ $hayFiltrosFuncionariosAc ? 'bi-funnel' : 'bi-table' }}"></i>
                        {{ $hayFiltrosFuncionariosAc ? 'Filtros activos' : 'Vista resumida' }}
                    </span>
                </div>
            </div>
        </div>

        <div class="fac-filter-card">
            <form method="GET" action="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="fac-filter-grid">
                <div>
                    <label for="filtro_rut" class="fac-filter-label">RUN</label>
                    <input id="filtro_rut" name="rut" type="text" class="form-control" value="{{ $filtrosFuncionariosAc['rut'] ?? '' }}" placeholder="Ej: 12345678-9">
                </div>
                <div>
                    <label for="filtro_nombre" class="fac-filter-label">Nombre funcionario</label>
                    <input id="filtro_nombre" name="nombre" type="text" class="form-control" value="{{ $filtrosFuncionariosAc['nombre'] ?? '' }}" placeholder="Nombre o apellido">
                </div>
                <div>
                    <label for="filtro_subdireccion" class="fac-filter-label">Subdirección</label>
                    <select id="filtro_subdireccion" name="subdireccion" class="form-select">
                        <option value="">Todas las subdirecciones</option>
                        @foreach(($subdireccionesDependencia ?? []) as $subdireccionFiltro)
                            <option value="{{ $subdireccionFiltro }}" @selected(($filtrosFuncionariosAc['subdireccion'] ?? '') === $subdireccionFiltro)>{{ $subdireccionFiltro }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="fac-filter-actions">
                    <button type="submit" class="fac-btn-primary">
                        <i class="bi bi-funnel"></i>
                        Filtrar
                    </button>
                    @if($hayFiltrosFuncionariosAc)
                        <a href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="fac-btn-secondary">
                            <i class="bi bi-x-circle"></i>
                            Limpiar
                        </a>
                    @endif
                </div>
            </form>
            <div class="small text-muted mt-2">
                El botón Exportar XLSX descarga todos los registros que cumplen los filtros actuales; si no hay filtros, descarga la nómina completa.
            </div>
        </div>

        <div class="fac-table-wrap">
            <div class="table-responsive">
                <table class="table table-sm fac-table align-middle">
                    <thead>
                        <tr>
                            <th class="fac-cell-run">RUN</th>
                            <th class="fac-cell-funcionario">Funcionario</th>
                            <th class="fac-cell-unidad">Unidad</th>
                            <th class="fac-cell-subdireccion">Subdirección dependencia</th>
                            <th class="fac-cell-calidad">Calidad jurídica</th>
                            <th class="fac-cell-escalafon">Escalafón</th>
                            <th class="fac-cell-grado">Grado</th>
                            <th class="fac-cell-contacto text-center" title="Contacto"><i class="bi bi-person-lines-fill"></i></th>
                            <th class="fac-cell-nacimiento">Nacimiento</th>
                            <th class="fac-cell-jefatura">Jefatura</th>
                            <th class="fac-cell-estado">Estado</th>
                            <th class="fac-cell-usuario">Usuario</th>
                            <th class="fac-cell-acciones text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($registros as $funcionario)
                            @php
                                $observaciones = (string) $valorCampo($funcionario, ['observaciones', 'observacion'], '');
                                $unidad = $valorCampo($funcionario, ['unidad_departamento', 'unidad', 'departamento'], null)
                                    ?: $extraerDesdeObservacion($observaciones, 'unidad')
                                    ?: '-';
                                $subdireccionDependencia = $valorCampo($funcionario, ['subdireccion_dependencia', 'subdireccion', 'dependencia_subdireccion'], null)
                                    ?: $extraerDesdeObservacion($observaciones, 'subdireccion_dependencia')
                                    ?: '-';
                                $calidadJuridica = $valorCampo($funcionario, ['calidad_juridica', 'calidad'], null)
                                    ?: $extraerDesdeObservacion($observaciones, 'calidad_juridica')
                                    ?: '-';
                                $escalafon = $valorCampo($funcionario, ['escalafon'], null)
                                    ?: $extraerDesdeObservacion($observaciones, 'escalafon')
                                    ?: '-';
                                $grado = $valorCampo($funcionario, ['grado', 'grado_nivel', 'nivel'], '-');
                                $esJefatura = in_array(strtolower((string) $valorCampo($funcionario, ['jefatura'], '0')), ['1', 'si', 'sí', 'true'], true);
                                $cargo = $valorCampo($funcionario, ['cargo_funcion', 'cargo', 'funcion'], null);
                                $telefono = $valorCampo($funcionario, ['telefono', 'fono', 'celular'], null) ?: '-';
                                $emailFuncionarioAc = $valorCampo($funcionario, ['email', 'correo', 'correo_electronico'], null) ?: '-';
                                $fechaNacimientoAc = $valorCampo($funcionario, ['fecha_nacimiento', 'nacimiento'], null);
                                $fechaNacimientoAc = $fechaNacimientoAc ? \Illuminate\Support\Carbon::parse($fechaNacimientoAc)->format('d-m-Y') : '-';
                                $estado = $valorCampo($funcionario, ['estado_autorizacion', 'estado'], 'Activo');
                            @endphp
                            <tr>
                                <td class="fac-cell-run fw-bold text-nowrap">{{ $runCompleto($funcionario) }}</td>
                                <td class="fac-cell-funcionario">
                                    <div class="fac-name">{{ $nombreCompleto($funcionario) }}</div>
                                    @if($cargo)
                                        <div class="fac-meta fac-meta--cargo" title="{{ $cargo }}">{{ $cargo }}</div>
                                    @endif
                                </td>
                                <td class="fac-cell-unidad">
                                    <span class="fac-wrap-text fw-semibold text-dark" title="{{ $unidad }}">{{ $unidad }}</span>
                                </td>
                                <td class="fac-cell-subdireccion"><span class="fac-badge fac-badge--muted fac-badge--wrap" title="{{ $subdireccionDependencia }}">{{ $subdireccionDependencia }}</span></td>
                                <td class="fac-cell-calidad"><span class="fac-badge fac-badge--muted" title="{{ $calidadJuridica }}">{{ $calidadJuridica }}</span></td>
                                <td class="fac-cell-escalafon"><span class="fac-badge fac-badge--indigo" title="{{ $escalafon }}">{{ $escalafon }}</span></td>
                                <td class="fac-cell-grado"><span class="fac-badge fac-badge--primary">{{ $grado }}</span></td>
                                <td class="fac-cell-contacto text-center">
                                    <div class="fac-contact-icons">
                                        @if($telefono && $telefono !== '-')
                                            <a href="tel:{{ preg_replace('/\s+/', '', $telefono) }}" class="fac-contact-icon" title="Teléfono: {{ $telefono }}" aria-label="Teléfono {{ $telefono }}">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        @else
                                            <span class="fac-contact-icon fac-contact-icon--empty" title="Sin teléfono registrado" aria-label="Sin teléfono registrado">
                                                <i class="bi bi-telephone-x"></i>
                                            </span>
                                        @endif

                                        @if($emailFuncionarioAc && $emailFuncionarioAc !== '-')
                                            <a href="mailto:{{ $emailFuncionarioAc }}" class="fac-contact-icon" title="Email: {{ $emailFuncionarioAc }}" aria-label="Email {{ $emailFuncionarioAc }}">
                                                <i class="bi bi-envelope"></i>
                                            </a>
                                        @else
                                            <span class="fac-contact-icon fac-contact-icon--empty" title="Sin email registrado" aria-label="Sin email registrado">
                                                <i class="bi bi-envelope-x"></i>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="fac-cell-nacimiento"><span class="fac-badge fac-badge--muted">{{ $fechaNacimientoAc }}</span></td>
                                <td class="fac-cell-jefatura">
                                    @if($esJefatura)
                                        <span class="fac-badge fac-badge--success" title="Puede ser configurado como jefatura o subrogante">Sí</span>
                                    @else
                                        <span class="fac-badge fac-badge--muted">No</span>
                                    @endif
                                </td>
                                <td class="fac-cell-estado">{!! $badgeEstado($estado) !!}</td>
                                <td class="fac-cell-usuario">{!! $badgeUsuario($funcionario) !!}</td>
                                <td class="fac-cell-acciones text-end">
                                    @if($puedeEditarFuncionarioAc && data_get($funcionario, 'id'))
                                        <a href="{{ route($rutaEditarFuncionarioAc, data_get($funcionario, 'id')) }}" class="fac-btn-action">
                                            <i class="bi bi-pencil-square"></i>
                                            Editar
                                        </a>
                                    @else
                                        <span class="fac-badge fac-badge--muted">No disponible</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13">
                                    <div class="fac-empty-state">
                                        <div class="fac-empty-state__icon"><i class="bi bi-inbox"></i></div>
                                        <div class="fw-bold text-dark mb-1">No hay funcionarios AC autorizados para mostrar.</div>
                                        <p class="mb-0">Cuando se cargue la nómina o se registren autorizaciones, aparecerán en esta tabla.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($fuenteRegistros instanceof \Illuminate\Pagination\AbstractPaginator)
            <div class="fac-pagination">
                {{ $fuenteRegistros->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
