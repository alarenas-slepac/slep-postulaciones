@extends('layouts.app')

@push('styles')
    <style>
        .pt-hero { border:1px solid #dbe4f0; border-radius:24px; background:linear-gradient(135deg,#ffffff 0%,#f7fbff 100%); box-shadow:0 18px 44px rgba(15,23,42,.07); padding:1.5rem; }
        .pt-kicker { color:#64748b; font-size:.75rem; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
        .pt-title { font-weight:900; color:#0f172a; margin:0; }
        .pt-panel { border:1px solid #dbe4f0; border-radius:22px; background:#fff; box-shadow:0 14px 34px rgba(15,23,42,.055); overflow:hidden; }
        .pt-panel-header { padding:1.15rem 1.25rem; border-bottom:1px solid #e8eef5; }
        .pt-filter-grid { display:grid; grid-template-columns:minmax(260px,1fr) 150px auto; gap:1rem; align-items:end; }
        .pt-table th { font-size:.78rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; white-space:nowrap; }
        .pt-table td { vertical-align:middle; }
        .pt-avatar { width:42px; height:42px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; background:#eaf2ff; color:#0b3d91; font-weight:900; }
        .pt-muted { color:#64748b; }
        .pt-role-select { min-width:190px; }
        @media (max-width: 991.98px) { .pt-filter-grid { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
    @php
        $activeImpersonation = session('tutorial_postulante_impersonation');
    @endphp

    <div class="pt-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="pt-kicker mb-2"><i class="bi bi-person-video2 me-1"></i> Administracion y acompanamiento</div>
                <h1 class="pt-title h3">Vista temporal de usuarios</h1>
                <p class="pt-muted mb-0">
                    @if($canViewAllUsers)
                        Busca cualquier usuario del sistema, selecciona uno de sus roles e ingresa temporalmente a su dashboard para revisar exactamente sus menus y permisos.
                    @else
                        Busca postulantes o funcionarios habilitados e ingresa temporalmente a su dashboard para apoyo y capacitacion.
                    @endif
                </p>
            </div>
            @if(is_array($activeImpersonation))
                <form method="POST" action="{{ route('gestion.postulante-tutorial.stop') }}" class="align-self-start align-self-lg-center">
                    @csrf
                    <button type="submit" class="btn btn-danger rounded-pill fw-bold"><i class="bi bi-box-arrow-left me-1"></i> Finalizar vista activa</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success border-0 shadow-sm rounded-4">{{ session('status') }}</div>
    @endif

    <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex gap-3 align-items-start">
        <i class="bi bi-shield-lock-fill fs-4"></i>
        <div>
            <div class="fw-bold">Acceso administrativo temporal</div>
            <div class="small">La cuenta original y su rol activo se restauran al pulsar <strong>Finalizar vista</strong>. No selecciones tu propia cuenta.</div>
        </div>
    </div>

    <section class="pt-panel mb-4">
        <div class="pt-panel-header">
            <form method="GET" action="{{ route('gestion.postulante-tutorial.index') }}" class="pt-filter-grid">
                <div>
                    <label class="form-label fw-bold" for="q">Nombre, RUT, correo o rol</label>
                    <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Ej.: 12.345.678-9, Juan Perez, funcionario_slep">
                </div>
                <div>
                    <label class="form-label fw-bold" for="per_page">Mostrar</label>
                    <select id="per_page" name="per_page" class="form-select">
                        @foreach([10,15,25,50] as $n)
                            <option value="{{ $n }}" @selected((int)($filters['per_page'] ?? 15) === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold rounded-pill"><i class="bi bi-search me-1"></i> Buscar</button>
                    <a href="{{ route('gestion.postulante-tutorial.index') }}" class="btn btn-outline-secondary fw-bold rounded-pill"><i class="bi bi-x-lg me-1"></i> Limpiar</a>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 pt-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>RUT</th>
                        <th>Correo</th>
                        <th>Roles asignados</th>
                        <th>Establecimiento</th>
                        <th class="text-end">Vista temporal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($usuarios as $usuario)
                        @php
                            $nombre = $usuario->nombre_completo ?: ($usuario->email ?? 'Usuario');
                            $initials = \App\Support\SlepUiRegistry::initials($usuario);
                            $roleContexts = $usuario->availableRoleContexts();
                            $isCurrentAccount = (int) auth()->id() === (int) $usuario->id;
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="pt-avatar">{{ $initials }}</span>
                                    <div>
                                        <div class="fw-bold text-dark">{{ $nombre }}</div>
                                        <div class="small pt-muted">ID usuario: {{ $usuario->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="fw-semibold">{{ $usuario->rut ?: '-' }}</td>
                            <td>{{ $usuario->email ?: '-' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse($roleContexts as $roleName)
                                        <span class="badge text-bg-light border text-dark">{{ $usuario->roleContextLabel($roleName) }}</span>
                                    @empty
                                        <span class="badge text-bg-danger">Sin rol</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                @if($usuario->establecimiento)
                                    <div class="fw-semibold">{{ $usuario->establecimiento->nombre_establecimiento }}</div>
                                    <div class="small pt-muted">RBD {{ $usuario->establecimiento->rbd ?: '-' }}</div>
                                @else
                                    <span class="pt-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($isCurrentAccount)
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" disabled>
                                        <i class="bi bi-person-check me-1"></i> Cuenta actual
                                    </button>
                                @elseif($roleContexts->isEmpty())
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold" disabled>
                                        <i class="bi bi-exclamation-circle me-1"></i> Sin rol
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('gestion.postulante-tutorial.start', $usuario) }}" class="d-inline-flex flex-column flex-xl-row gap-2 align-items-xl-center justify-content-end" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Iniciar vista temporal como ' . $nombre . '?') }});">
                                        @csrf
                                        @if($roleContexts->count() > 1)
                                            <select name="active_role" class="form-select form-select-sm pt-role-select" aria-label="Rol para visualizar">
                                                @foreach($roleContexts as $roleName)
                                                    <option value="{{ $roleName }}">{{ $usuario->roleContextLabel($roleName) }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="hidden" name="active_role" value="{{ $roleContexts->first() }}">
                                            <span class="small fw-semibold text-nowrap">{{ $usuario->roleContextLabel($roleContexts->first()) }}</span>
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill fw-bold text-nowrap">
                                            <i class="bi bi-person-video2 me-1"></i> Ver dashboard
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No se encontraron usuarios con los filtros indicados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($usuarios->hasPages())
            <div class="p-3 border-top">
                {{ $usuarios->links() }}
            </div>
        @endif
    </section>
@endsection
