<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use App\Services\FuncionarioRegisterLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterUserRequest $request, FuncionarioRegisterLookupService $lookupService): RedirectResponse
    {
        $lookup = $lookupService->lookup((string) $request->rut);
        $roleName = ($lookup['is_funcionario'] ?? false) ? 'funcionario' : 'postulante';
        $establecimientoId = $roleName === 'funcionario' ? (int) ($lookup['establecimiento_id'] ?? 0) : null;

        if (($lookup['status'] ?? null) === 'error') {
            return back()->withErrors(['rut' => (string) ($lookup['message'] ?? 'No fue posible validar el RUT contra la carga vigente.')])->withInput();
        }

        if ($roleName === 'funcionario' && !$establecimientoId) {
            return back()->withErrors([
                'rut' => 'No fue posible determinar el establecimiento asociado a este funcionario para completar el registro.',
            ])->withInput();
        }

        try {
            $user = DB::transaction(function () use ($request, $lookup, $roleName, $establecimientoId) {
                $user = User::create([
                    'rut' => $request->rut,
                    'nombres' => $roleName === 'funcionario' ? (string) ($lookup['nombres'] ?? $request->nombres) : $request->nombres,
                    'apellido_paterno' => $roleName === 'funcionario' ? (string) ($lookup['apellido_paterno'] ?? $request->apellido_paterno) : $request->apellido_paterno,
                    'apellido_materno' => $roleName === 'funcionario' ? (string) ($lookup['apellido_materno'] ?? $request->apellido_materno) : $request->apellido_materno,
                    'email' => strtolower(trim($request->email)),
                    'establecimiento_id' => $establecimientoId ?: null,
                    'password' => $request->password, // hashed por cast
                ]);

                Role::findOrCreate($roleName, 'web');
                $user->assignRole($roleName);

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error('No fue posible completar el registro.', [
                'rut' => $request->rut,
                'email' => strtolower(trim((string) $request->email)),
                'role' => $roleName,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'register' => 'No fue posible completar el registro en este momento. Intenta nuevamente o contacta soporte.',
            ])->withInput();
        }

        // No crear el perfil aquí: en producción `postulant_profiles` exige varios
        // campos obligatorios sin valor por defecto (ej. `fecha_nacimiento`).
        // El perfil se crea/guarda recién cuando la persona entra a "Mi perfil"
        // y completa sus datos.

        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()->route('verification.notice')
            ->with('status', $roleName === 'funcionario'
                ? 'Cuenta creada como funcionario. Te enviamos un enlace de verificación a tu correo.'
                : 'Cuenta creada como postulante. Te enviamos un enlace de verificación a tu correo.');
    }
}
