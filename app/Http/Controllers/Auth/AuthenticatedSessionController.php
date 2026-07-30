<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'active_role' => ['nullable', 'string'],
        ], [
            'login.required' => 'Este campo es obligatorio.',
            'password.required' => 'Este campo es obligatorio.',
        ]);

        $login = trim((string) $request->input('login'));
        $password = (string) $request->input('password');
        $remember = (bool) $request->boolean('remember');

        // Detectar si es email o RUT
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
        if (!$isEmail) {
            // Normalizar RUT: quitar puntos y guión, mayúsculas
            $login = strtoupper(preg_replace('/[^0-9Kk]/', '', $login));
        }

        // Buscar usuario por email o por rut
        $userModel = \App\Models\User::query()
            ->when($isEmail, fn($q) => $q->where('email', strtolower($login)))
            ->when(!$isEmail, fn($q) => $q->where('rut', $login))
            ->first();

        if (!$userModel) {
            throw ValidationException::withMessages([
                'login' => 'Credenciales inválidas.',
            ]);
        }

        // Intento de login usando email (credencial real)
        $credentials = ['email' => $userModel->email, 'password' => $password];

        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'login' => 'Credenciales inválidas.',
            ]);
        }

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

            if ($requestedRole === 'funcionario_ac') {
                return redirect()->intended(route('tramites.cargas-familiares.index'));
            }
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
