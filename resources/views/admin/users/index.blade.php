@extends('layouts.app')

@section('content')
<div class="ur-page">
    <div class="ur-hero">
        <div class="ur-hero-main">
            <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
            <div>
                <div class="ur-eyebrow">Administración · Accesos</div>
                <h1 class="ur-hero-title">Usuarios</h1>
                <p class="ur-hero-subtitle">Administración de cuentas, roles y asignación institucional.</p>
            </div>
        </div>
        <div class="ur-hero-actions">
            <a href="{{ route('admin.users.export', request()->query()) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
            </a>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-person-gear me-1"></i> Administrar roles
            </a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Crear usuario</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div>
    @endif


    <div class="card ur-panel mb-4">
        <div class="ur-panel-header">
            <div class="ur-panel-kicker">Vista general</div>
            <h2 class="ur-panel-title">Resumen de usuarios</h2>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-xl-4">
                    <div class="ur-summary-card">
                        <span class="ur-summary-label">Cuentas registradas</span>
                        <div class="ur-summary-value">{{ $summary['total'] }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="ur-chip is-success"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ $summary['verified'] }} verificados</span>
                            <span class="ur-chip is-warning"><i class="bi bi-clock" aria-hidden="true"></i>{{ $summary['pending'] }} pendientes</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-8">
                    <div class="ur-section">
                        <div class="ur-section-title">Usuarios por rol</div>
                        <div class="row g-2">
                            @foreach ($summary['by_role'] as $roleSummary)
                                <div class="col-12 col-md-6 col-xxl-4">
                                    <div class="ur-info-item">
                                        <div class="ur-info-value">{{ $roleSummary['label'] }}</div>
                                        <div class="ur-info-help">{{ $roleSummary['total'] }} total</div>
                                        <div class="small">
                                            <span class="text-success">{{ $roleSummary['verified'] }} verificados</span>
                                            <span class="text-muted">/</span>
                                            <span class="text-warning-emphasis">{{ $roleSummary['pending'] }} pendientes</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card ur-panel mb-4">
        <div class="ur-panel-header">
            <div class="ur-panel-kicker">Búsqueda y filtrado</div>
            <h2 class="ur-panel-title">Filtrar usuarios</h2>
            <p class="ur-panel-subtitle">Acota el listado por identidad, rol, verificación o establecimiento.</p>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-3">
                    <label class="form-label" for="users-filter-q">Buscar</label>
                    <input id="users-filter-q" type="text" name="q" value="{{ $filters['q'] }}" class="form-control"
                        placeholder="RUT, nombre, apellido o correo">
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label" for="users-filter-role">Rol</label>
                    <select id="users-filter-role" name="rol" class="form-select js-ur-searchable-select" data-placeholder="Todos los roles">
                        <option value="">Todos</option>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected($filters['rol'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label" for="users-filter-verified">Verificación</label>
                    <select id="users-filter-verified" name="verificado" class="form-select">
                        <option value="">Todas</option>
                        <option value="si" @selected($filters['verificado'] === 'si')>Verificados</option>
                        <option value="no" @selected($filters['verificado'] === 'no')>Pendientes</option>
                    </select>
                </div>
                <div class="col-12 col-md-8 col-lg-3">
                    <label class="form-label" for="users-filter-establishment">Establecimiento</label>
                    <select id="users-filter-establishment" name="establecimiento_id" class="form-select js-ur-searchable-select" data-placeholder="Todos los establecimientos">
                        <option value="">Todos</option>
                        @foreach ($establecimientos as $comuna => $items)
                            <optgroup label="{{ $comuna }}">
                                @foreach ($items as $e)
                                    <option value="{{ $e->id }}" @selected($filters['establecimiento_id'] === (string) $e->id)>
                                        {{ $e->rbd }} — {{ $e->nombre_establecimiento }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Filtrar</button>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link text-decoration-none px-0">Limpiar filtros</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card ur-panel">
        <div class="ur-panel-header">
            <div class="ur-panel-kicker">Resultados</div>
            <h2 class="ur-panel-title">Listado de usuarios</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle ur-page-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>RUT</th>
                            <th>Nombre completo</th>
                            <th>Email</th>
                            <th>Establecimiento</th>
                            <th>Roles</th>
                            <th>Verificado</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $u)
                            <tr>
                                <td>{{ $u->id }}</td>
                                <td>{{ $u->rut }}</td>
                                <td>
                                    <div class="ur-table-primary">{{ $u->nombre_completo ?: $u->email }}</div>
                                </td>
                                <td>{{ $u->email }}</td>
                                <td>
                                    @if ($u->establecimiento)
                                        <div class="ur-table-primary">{{ $u->establecimiento->rbd }} — {{ $u->establecimiento->nombre_establecimiento }}</div>
                                        @if ($u->establecimiento->comuna)
                                            <div class="ur-table-meta">{{ $u->establecimiento->comuna }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($u->getRoleNames() as $r)
                                        <span class="ur-chip">{{ $r }}</span>
                                    @empty
                                        <span class="text-muted">Sin rol</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($u->email_verified_at)
                                    <span class="ur-chip is-success">Verificado</span>
                                    @else
                                        <span class="ur-chip is-warning">Pendiente</span>
                                    @endif
                                </td>
                                <td>{{ cl_datetime($u->created_at) }}</td>
                                <td>
                                    <div class="ur-actions justify-content-end flex-nowrap">
                                        <a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-outline-primary"
                                            title="Ver ficha">
                                            <i class="bi bi-eye" aria-hidden="true"></i> Ver
                                        </a>
                                        <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"
                                            title="Editar usuario">
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i> Editar
                                        </a>
                                        <button type="button" class="btn btn-sm ur-danger" data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteUserModal"
                                            data-delete-url="{{ route('admin.users.destroy', $u) }}"
                                            data-username="{{ $u->nombre_completo ?: $u->email }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i> Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="ur-empty"><i class="bi bi-people" aria-hidden="true"></i>Sin usuarios para los filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted small">Mostrando {{ $users->count() }} de {{ $users->total() }} usuarios.</div>
            {{ $users->links() }}
        </div>
    </div>

    <div class="modal fade" id="confirmDeleteUserModal" tabindex="-1" aria-labelledby="confirmDeleteUserLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="deleteUserForm" method="POST" class="modal-content ur-modal">
                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteUserLabel">Confirmar eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <p>¿Seguro que deseas eliminar al usuario <strong id="deleteUserName">—</strong>? Esta acción dará de
                        baja la cuenta y conservará un registro de auditoría.</p>

                    <div class="mb-3">
                        <label for="deletePassword" class="form-label">Tu contraseña</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="deletePassword" name="password"
                            placeholder="Ingresa tu contraseña" required>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    @error('general')
                        <div class="alert alert-danger py-2 mb-0">{{ $message }}</div>
                    @enderror
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn ur-danger">Eliminar usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@include('admin.users._select2_assets')

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('confirmDeleteUserModal');
            const form = document.getElementById('deleteUserForm');
            const nameSpan = document.getElementById('deleteUserName');
            const pwd = document.getElementById('deletePassword');

            modalEl?.addEventListener('show.bs.modal', function(event) {
                const btn = event.relatedTarget;
                const url = btn.getAttribute('data-delete-url');
                const username = btn.getAttribute('data-username') || 'usuario';

                form.setAttribute('action', url);
                nameSpan.textContent = username;
                pwd.value = '';
                setTimeout(() => pwd.focus(), 200);
            });

        });
    </script>
@endpush
