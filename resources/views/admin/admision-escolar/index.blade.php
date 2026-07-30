@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 admision-admin">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3 mb-4">
        <div>
            <div class="text-uppercase text-primary fw-bold small mb-2">Administración</div>
            <h1 class="h2 mb-2">Admisión Escolar</h1>
            <p class="text-muted mb-0">Gestiona la información editorial que se mostrará en la vitrina pública de establecimientos.</p>
        </div>
        @if (Route::has('public.admision-escolar.index'))
            <a class="btn btn-outline-primary" href="{{ route('public.admision-escolar.index') }}" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-1"></i> Abrir vitrina pública
            </a>
        @endif
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Total establecimientos', 'value' => $summary['total'], 'icon' => 'bi-buildings', 'tone' => 'primary'],
            ['label' => 'Publicados', 'value' => $summary['publicados'], 'icon' => 'bi-check2-circle', 'tone' => 'success'],
            ['label' => 'Borradores', 'value' => $summary['borradores'], 'icon' => 'bi-pencil-square', 'tone' => 'warning'],
            ['label' => 'Incompletos', 'value' => $summary['incompletos'], 'icon' => 'bi-exclamation-triangle', 'tone' => 'danger'],
        ] as $stat)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100 admision-stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="admision-stat-icon bg-{{ $stat['tone'] }}-subtle text-{{ $stat['tone'] }}">
                            <i class="bi {{ $stat['icon'] }}"></i>
                        </span>
                        <div>
                            <div class="text-muted small fw-semibold">{{ $stat['label'] }}</div>
                            <div class="fs-2 fw-bold lh-1 mt-1">{{ number_format($stat['value'], 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.admision-escolar.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="admision-q">Buscar establecimiento</label>
                    <input id="admision-q" type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, RBD o comuna">
                </div>
                <div class="col-12 col-md-5 col-lg-3">
                    <label class="form-label" for="admision-comuna">Comuna</label>
                    <select id="admision-comuna" name="comuna" class="form-select">
                        <option value="">Todas las comunas</option>
                        @foreach ($comunas as $itemComuna)
                            <option value="{{ $itemComuna }}" @selected($comuna === $itemComuna)>{{ $itemComuna }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-5 col-lg-2">
                    <label class="form-label" for="admision-estado">Estado</label>
                    <select id="admision-estado" name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="publicado" @selected($estado === 'publicado')>Publicados</option>
                        <option value="borrador" @selected($estado === 'borrador')>Borradores</option>
                        <option value="incompleto" @selected($estado === 'incompleto')>Incompletos</option>
                        <option value="sin_ficha" @selected($estado === 'sin_ficha')>Sin ficha</option>
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i> Filtrar</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.admision-escolar.index') }}" title="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 admision-table">
                <thead>
                    <tr>
                        <th>Establecimiento</th>
                        <th>Comuna</th>
                        <th>Estado</th>
                        <th style="min-width:180px;">Completitud</th>
                        <th>Última edición</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $perfil = $item->admisionPerfil;
                            $completitud = $item->admision_completitud;
                            $publicado = $perfil?->isPublicado() ?? false;
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="admision-row-logo">
                                        @if ($perfil?->logoUrl())
                                            <img src="{{ $perfil->logoUrl() }}" alt="Logo de {{ $item->nombre_establecimiento }}">
                                        @else
                                            <i class="bi bi-building"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="fw-bold">{{ $item->nombre_establecimiento }}</div>
                                        <div class="text-muted small">RBD {{ $item->rbd }}{{ $item->dv ? '-' . $item->dv : '' }}</div>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach ($item->nivelesEducativos() as $nivel)
                                                <span class="badge rounded-pill text-bg-light border fw-normal">{{ $nivel }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $item->comuna ?: '—' }}</td>
                            <td>
                                @if ($publicado)
                                    <span class="badge rounded-pill text-bg-success"><i class="bi bi-circle-fill me-1" style="font-size:.45rem;"></i>Publicado</span>
                                    <div class="small text-muted mt-1">{{ optional($perfil->publicado_at)->format('d/m/Y H:i') }}</div>
                                @elseif ($perfil)
                                    <span class="badge rounded-pill text-bg-warning">Borrador</span>
                                @else
                                    <span class="badge rounded-pill text-bg-secondary">Sin ficha</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex justify-content-between small fw-semibold mb-1">
                                    <span>{{ $completitud['label'] }}</span>
                                    <span>{{ $completitud['score'] }}%</span>
                                </div>
                                <div class="progress" style="height:7px;" role="progressbar" aria-valuenow="{{ $completitud['score'] }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-{{ $completitud['tone'] }}" style="width:{{ $completitud['score'] }}%"></div>
                                </div>
                                @if (! $completitud['publishable'])
                                    <div class="small text-danger mt-1">No cumple requisitos de publicación</div>
                                @endif
                            </td>
                            <td>
                                @if ($perfil?->updated_at)
                                    <div>{{ $perfil->updated_at->format('d/m/Y H:i') }}</div>
                                    <div class="small text-muted">{{ $perfil->actualizadoPor?->display_name ?? '—' }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-primary" href="{{ route('admin.admision-escolar.edit', $item) }}">
                                    <i class="bi bi-pencil-square me-1"></i> Editar
                                </a>
                                @if ($perfil)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.admision-escolar.preview', $item) }}" target="_blank" rel="noopener">
                                        <i class="bi bi-eye"></i>
                                        <span class="visually-hidden">Previsualizar</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">No se encontraron establecimientos con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="card-footer bg-white py-3">{{ $items->links() }}</div>
        @endif
    </div>
</div>

<style>
    .admision-admin .card { border-radius: 18px; }
    .admision-stat-icon { width: 54px; height: 54px; border-radius: 16px; display:inline-flex; align-items:center; justify-content:center; font-size:1.35rem; }
    .admision-table th { color:#64748b; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
    .admision-table td { padding-top:1rem; padding-bottom:1rem; }
    .admision-row-logo { width:54px; height:54px; flex:0 0 54px; border-radius:14px; border:1px solid #dbe4f0; background:#f8fafc; display:flex; align-items:center; justify-content:center; color:#64748b; font-size:1.25rem; overflow:hidden; }
    .admision-row-logo img { width:100%; height:100%; object-fit:contain; padding:.25rem; }
</style>
@endsection
