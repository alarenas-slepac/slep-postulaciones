@php
    $switcherUser = $user ?? auth()->user();
    $switcherRole = $activeRole ?? ($switcherUser?->activeRoleName());
    $switcherRoles = $switcherUser?->availableRoleContexts() ?? collect();
    $switcherVariant = $variant ?? 'dashboard';
@endphp

@if ($switcherUser && $switcherRoles->count() > 1 && Route::has('role-context.update'))
    @if ($switcherVariant === 'navbar')
        <li>
            <form method="POST" action="{{ route('role-context.update') }}" class="px-3 py-2">
                @csrf
                <label for="navbarActiveRole" class="form-label small text-muted mb-1">Cambiar rol activo</label>
                <select id="navbarActiveRole" name="active_role" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach ($switcherRoles as $roleName)
                        <option value="{{ $roleName }}" @selected($switcherRole === $roleName)>{{ $switcherUser->roleContextLabel($roleName) }}</option>
                    @endforeach
                </select>
            </form>
        </li>
        <li><hr class="dropdown-divider"></li>
    @else
        <div class="alert alert-light border d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <div class="fw-semibold mb-1"><i class="bi bi-person-switch me-1"></i> Rol activo</div>
                <div class="text-muted small">Puedes cambiar el contexto del sistema según el rol con el que deseas trabajar.</div>
            </div>
            <form method="POST" action="{{ route('role-context.update') }}" class="d-flex align-items-center gap-2 m-0">
                @csrf
                <label for="dashboardActiveRole" class="form-label mb-0 small text-muted">Rol</label>
                <select id="dashboardActiveRole" name="active_role" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach ($switcherRoles as $roleName)
                        <option value="{{ $roleName }}" @selected($switcherRole === $roleName)>{{ $switcherUser->roleContextLabel($roleName) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    @endif
@endif
