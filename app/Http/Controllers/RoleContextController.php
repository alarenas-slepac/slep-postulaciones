<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'active_role' => ['required', 'string'],
        ]);

        $requestedRole = Str::lower(trim((string) $validated['active_role']));
        $availableRoles = $user->availableRoleContexts()->all();

        abort_unless(in_array($requestedRole, $availableRoles, true), 403, 'Rol no disponible para esta cuenta.');

        $request->session()->put('active_role', $requestedRole);

        $previousUrl = (string) $request->headers->get('referer', url()->previous());

        if (str_contains($previousUrl, '/tramites/cargas-familiares')) {
            if (in_array($requestedRole, ['admin', 'funcionario_slep', 'coordinador_gdp'], true)) {
                return redirect()
                    ->route('tramites.cargas-familiares.admin.index')
                    ->with('status', 'Rol activo actualizado a ' . $user->roleContextLabel($requestedRole) . '.');
            }

            if (in_array($requestedRole, (array) config('cargas_familiares.acceso_solicitantes.roles_habilitados', ['funcionario_ac']), true)) {
                return redirect()
                    ->route('tramites.cargas-familiares.index')
                    ->with('status', 'Rol activo actualizado a ' . $user->roleContextLabel($requestedRole) . '.');
            }

            return redirect()
                ->route('dashboard')
                ->with('status', 'Rol activo actualizado a ' . $user->roleContextLabel($requestedRole) . '.');
        }

        return redirect()->back()->with('status', 'Rol activo actualizado a ' . $user->roleContextLabel($requestedRole) . '.');
    }
}
