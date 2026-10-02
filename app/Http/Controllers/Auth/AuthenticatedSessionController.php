<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Login aceptando RUT o email en el campo "login".
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $userModel = $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put('show_changelog_modal', true);
        $request->session()->forget('changelog_seen_version');

        $requestedRole = strtolower(trim((string) $request->input('active_role', '')));
        if ($requestedRole !== '') {
            if (!method_exists($userModel, 'hasRole') || !$userModel->hasRole($requestedRole)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'login' => 'La cuenta no tiene habilitado el rol solicitado.',
                ]);
            }

            $request->session()->put('active_role', $requestedRole);
        }

        $request->clearLoginAttempts();
        if ($requestedRole === 'funcionario_ac') {
            return redirect()->intended(route('tramites.cargas-familiares.index'));
        }

        // Redirige al dashboard (intended si venía de ruta protegida)
        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
