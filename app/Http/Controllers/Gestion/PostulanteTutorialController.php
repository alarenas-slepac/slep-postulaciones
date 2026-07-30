<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PostulanteTutorialController extends Controller
{
    private const ROLES_AUTORIZADOS = ['admin', 'coordinador_gdp', 'funcionario_slep'];

    private const SESSION_KEY = 'tutorial_postulante_impersonation';

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ]);

        $filters = [
            'q' => trim((string) ($data['q'] ?? '')),
            'per_page' => (int) ($data['per_page'] ?? 15),
        ];

        $canViewAllUsers = $this->operatorCanViewAllUsers($request);

        $query = User::query()
            ->with([
                'roles:id,name',
                'establecimiento:id,rbd,nombre_establecimiento',
            ]);

        // Los operadores no administradores mantienen el alcance historico
        // limitado a postulantes y funcionarios con perfil de postulacion.
        if (! $canViewAllUsers) {
            $query->where(function ($q) {
                $q->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', ['postulante', 'funcionario']))
                    ->orWhereHas('postulantProfile');
            });
        }

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $rutClean = strtoupper(preg_replace('/[^0-9Kk]/', '', $search));
            $tokens = array_values(array_filter(preg_split('/\s+/', $search) ?: []));

            $query->where(function ($qq) use ($search, $rutClean, $tokens) {
                if ($rutClean !== '') {
                    $qq->orWhere('rut', 'like', '%' . $rutClean . '%');
                }

                $qq->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereRaw("CONCAT_WS(' ', nombres, apellido_paterno, apellido_materno) LIKE ?", ['%' . $search . '%'])
                    ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'like', '%' . $search . '%'));

                foreach ($tokens as $token) {
                    $qq->orWhere(function ($tt) use ($token) {
                        $tt->where('nombres', 'like', '%' . $token . '%')
                            ->orWhere('apellido_paterno', 'like', '%' . $token . '%')
                            ->orWhere('apellido_materno', 'like', '%' . $token . '%');
                    });
                }
            });
        }

        $usuarios = $query
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->orderBy('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('gestion.postulantes-tutorial.index', [
            'usuarios' => $usuarios,
            'filters' => $filters,
            'canViewAllUsers' => $canViewAllUsers,
        ])->with('title', 'Vista temporal de usuarios');
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'active_role' => ['nullable', 'string', 'max:100'],
        ]);

        abort_if($this->isImpersonating($request), 409, 'Ya existe una vista temporal activa. Finalizala antes de iniciar otra.');
        abort_if((int) $request->user()->id === (int) $user->id, 422, 'No puedes iniciar una vista temporal sobre tu propia cuenta.');

        $user->loadMissing(['roles', 'postulantProfile']);

        // Solo un administrador operando en contexto Administrador puede abrir
        // cuentas con cualquier rol. Los demas operadores conservan el alcance
        // anterior para evitar elevacion de privilegios.
        if (! $this->operatorCanViewAllUsers($request)) {
            abort_unless(
                $user->hasAnyRole(['postulante', 'funcionario']) || $user->postulantProfile,
                404,
                'El usuario seleccionado no corresponde a un postulante o funcionario habilitado para esta vista.'
            );
        }

        $availableRoles = $user->availableRoleContexts();
        abort_if($availableRoles->isEmpty(), 422, 'El usuario seleccionado no tiene un rol asignado y no puede abrir un dashboard.');

        $requestedRole = Str::lower(trim((string) ($validated['active_role'] ?? '')));
        if ($requestedRole !== '') {
            abort_unless(
                $availableRoles->contains($requestedRole),
                422,
                'El rol seleccionado no pertenece a la cuenta indicada.'
            );
        }

        $roleForTarget = $requestedRole !== ''
            ? $requestedRole
            : $user->defaultActiveRoleName();

        abort_unless($roleForTarget, 422, 'No fue posible determinar el contexto de rol del usuario seleccionado.');

        $operator = $request->user();
        $previousRole = $operator?->activeRoleName();

        Log::notice('[VistaTemporalUsuarios] Inicio de vista temporal', [
            'impersonator_id' => $operator->id,
            'impersonator_role' => $previousRole,
            'target_id' => $user->id,
            'target_role' => $roleForTarget,
            'ip' => $request->ip(),
        ]);

        Auth::login($user);

        $request->session()->put(self::SESSION_KEY, [
            'impersonator_id' => $operator->id,
            'impersonator_name' => $operator->nombre_completo ?: ($operator->email ?? 'Usuario'),
            'impersonator_role' => $previousRole,
            'target_id' => $user->id,
            'target_name' => $user->nombre_completo ?: ($user->email ?? 'Usuario'),
            'target_role' => $roleForTarget,
            'started_at' => now()->toDateTimeString(),
        ]);
        $request->session()->put('active_role', $roleForTarget);

        return redirect()
            ->route('dashboard')
            ->with('status', "Vista temporal iniciada con el rol {$user->roleContextLabel($roleForTarget)}. Usa Finalizar vista para volver a tu cuenta.");
    }

    public function stop(Request $request): RedirectResponse
    {
        $data = $request->session()->get(self::SESSION_KEY);
        abort_unless(is_array($data) && ! empty($data['impersonator_id']), 404, 'No existe una vista temporal activa.');

        $impersonatorId = (int) $data['impersonator_id'];
        $previousRole = trim((string) ($data['impersonator_role'] ?? ''));

        abort_unless(Auth::loginUsingId($impersonatorId), 404, 'No fue posible recuperar la cuenta original.');

        Log::notice('[VistaTemporalUsuarios] Fin de vista temporal', [
            'impersonator_id' => $impersonatorId,
            'target_id' => (int) ($data['target_id'] ?? 0),
            'target_role' => (string) ($data['target_role'] ?? ''),
            'started_at' => (string) ($data['started_at'] ?? ''),
            'ip' => $request->ip(),
        ]);

        $request->session()->forget(self::SESSION_KEY);
        if ($previousRole !== '') {
            $request->session()->put('active_role', $previousRole);
        } else {
            $request->session()->forget('active_role');
        }

        return redirect()
            ->route('dashboard')
            ->with('status', 'Vista temporal finalizada. Volviste a la cuenta original.');
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::ROLES_AUTORIZADOS),
            403,
            'No tienes permiso para usar la vista temporal de usuarios.'
        );
    }

    private function operatorCanViewAllUsers(Request $request): bool
    {
        $user = $request->user();

        return $user
            && method_exists($user, 'hasRole')
            && $user->hasRole('admin')
            && method_exists($user, 'activeRoleName')
            && $user->activeRoleName() === 'admin';
    }

    private function isImpersonating(Request $request): bool
    {
        return is_array($request->session()->get(self::SESSION_KEY));
    }
}
