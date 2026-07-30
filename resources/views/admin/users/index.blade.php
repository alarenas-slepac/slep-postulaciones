@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0">Usuarios</h1>
            <div class="text-muted small">Administración de cuentas, roles y asignación institucional.</div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.users.export', request()->query()) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
            </a>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-primary">
                <i class="bi bi-person-gear me-1"></i> Administrar roles
            </a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Crear usuario</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif


    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-xl-4">
                    <div class="border rounded-3 h-100 p-3 bg-light-subtle">
                        <div class="text-muted small text-uppercase fw-semibold mb-2">Resumen general</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge text-bg-dark fs-6">Total: {{ $summary['total'] }}</span>
                            <span class="badge text-bg-success fs-6">Verificados: {{ $summary['verified'] }}</span>
                            <span class="badge text-bg-warning text-dark fs-6">Pendientes: {{ $summary['pending'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-8">
                    <div class="border rounded-3 h-100 p-3">
                        <div class="text-muted small text-uppercase fw-semibold mb-2">Usuarios por rol</div>
                        <div class="row g-2">
                            @foreach ($summary['by_role'] as $roleSummary)
                                <div class="col-12 col-md-6 col-xxl-4">
                                    <div class="border rounded-3 h-100 p-2">
                                        <div class="fw-semibold">{{ $roleSummary['label'] }}</div>
                                        <div class="small text-muted">{{ $roleSummary['total'] }} total</div>
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

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control"
                        placeholder="RUT, nombre, apellido o correo">
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected($filters['rol'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label">Verificación</label>
                    <select name="verificado" class="form-select">
                        <option value="">Todas</option>
                        <option value="si" @selected($filters['verificado'] === 'si')>Verificados</option>
                        <option value="no" @selected($filters['verificado'] === 'no')>Pendientes</option>
                    </select>
                </div>
                <div class="col-12 col-md-8 col-lg-3">
                    <label class="form-label">Establecimiento</label>
                    <select name="establecimiento_id" class="form-select">
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
                <div class="col-12 col-md-4 col-lg-1 d-grid">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link text-decoration-none px-0">Limpiar filtros</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
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
                                    <div class="fw-semibold">{{ $u->nombre_completo ?: $u->email }}</div>
                                </td>
                                <td>{{ $u->email }}</td>
                                <td>
                                    @if ($u->establecimiento)
                                        <div class="fw-semibold">{{ $u->establecimiento->rbd }} — {{ $u->establecimiento->nombre_establecimiento }}</div>
                                        @if ($u->establecimiento->comuna)
                                            <div class="small text-muted">{{ $u->establecimiento->comuna }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse ($u->getRoleNames() as $r)
                                        <span class="badge bg-secondary">{{ $r }}</span>
                                    @empty
                                        <span class="text-muted">Sin rol</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($u->email_verified_at)
                                    <span class="badge text-bg-success">Sí</span>
                                    @else
                                        <span class="badge bg-warning text-dark">No</span>
                                    @endif
                                </td>
                                <td>{{ cl_datetime($u->created_at) }}</td>
                                <td>
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="tooltip" title="Ver ficha">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="tooltip" title="Editar usuario">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteUserModal"
                                            data-delete-url="{{ route('admin.users.destroy', $u) }}"
                                            data-username="{{ $u->nombre_completo ?: $u->email }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Sin usuarios para los filtros aplicados.</td>
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
            <form id="deleteUserForm" method="POST" class="modal-content">
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
                        <input type="password" class="form-control" id="deletePassword" name="password"
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
                    <button type="submit" class="btn btn-danger">Eliminar usuario</button>
                </div>
            </form>
        </div>
    </div>
@endsection

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

            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                new bootstrap.Tooltip(el);
            });
        });
    </script>
@endpush
