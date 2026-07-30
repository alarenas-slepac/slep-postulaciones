<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\RestrictedRutService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $users = $this->filteredUsers($request);
        $roleLabels = $this->availableRoles();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => $roleLabels,
            'establecimientos' => $this->establecimientosGrouped(),
            'summary' => $this->buildSummary($request, $roleLabels),
            'filters' => [
                'q' => (string) $request->string('q'),
                'rol' => (string) $request->string('rol'),
                'verificado' => (string) $request->string('verificado'),
                'establecimiento_id' => (string) $request->string('establecimiento_id'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $users = $this->buildFilteredUsersQuery($request, false)
            ->with(['postulantProfile.comuna'])
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->orderBy('id')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $headers = [
            'RUT',
            'Nombres',
            'Apellido paterno',
            'Apellido materno',
            'Email',
            'Dirección',
            'Comuna',
        ];

        $rows = [$headers];

        foreach ($users as $user) {
            $profile = $user->postulantProfile;

            $rows[] = [
                $this->formatRutForExport($user->rut),
                (string) ($user->nombres ?? ''),
                (string) ($user->apellido_paterno ?? ''),
                (string) ($user->apellido_materno ?? ''),
                (string) ($user->email ?? ''),
                (string) ($profile?->direccion ?? ''),
                (string) ($profile?->comuna?->name ?? ''),
            ];
        }

        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->freezePane('A2');

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'nomina_usuarios_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['roles', 'establecimiento']);

        return view('admin.users.show', compact('user'));
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => $this->availableRoles(),
            'establecimientos' => $this->establecimientosGrouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedRoles = $this->allowedRoles();

        $data = $request->validate([
            'rut' => ['required', 'string', 'max:12', 'regex:/^[0-9Kk\.\-]+$/', 'unique:users,rut'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($allowedRoles)],
        ], [
            'rut.required' => 'El RUT es obligatorio.',
            'rut.regex' => 'Formato de RUT inválido.',
            'rut.unique' => 'Este RUT ya está registrado.',
            'email.unique' => 'Este email ya está registrado.',
            'roles.required' => 'Debes seleccionar al menos un rol.',
            'roles.array' => 'La selección de roles es inválida.',
            'roles.min' => 'Debes seleccionar al menos un rol.',
            'roles.*.in' => 'Uno de los roles seleccionados es inválido.',
        ]);

        $selectedRoles = collect($data['roles'] ?? [])->map(fn ($role) => (string) $role)->unique()->values()->all();
        $needsEstablecimiento = $this->requiresEstablecimiento($selectedRoles);

        if ($needsEstablecimiento && empty($data['establecimiento_id'])) {
            return back()->withErrors([
                'establecimiento_id' => 'Debes seleccionar un establecimiento para usuarios con rol Funcionario, Funcionario establecimiento o Funcionario Directivo Establecimiento.',
            ])->withInput();
        }

        if (in_array('postulante', $selectedRoles, true) && app(RestrictedRutService::class)->isRestrictedRut((string) $data['rut'])) {
            return back()->withErrors([
                'rut' => 'Este RUT mantiene una restricción vigente para ejercer y no puede ser creado con rol postulante.',
            ])->withInput();
        }

        $tmpPassword = Str::random(16);

        $user = new User();
        $user->rut = $data['rut'];
        $user->nombres = $data['nombres'];
        $user->apellido_paterno = $data['apellido_paterno'];
        $user->apellido_materno = $data['apellido_materno'];
        $user->email = strtolower(trim($data['email']));
        $user->password = $tmpPassword;
        $user->email_verified_at = $this->containsInternalRole($selectedRoles) ? now() : null;
        $user->establecimiento_id = $needsEstablecimiento ? $data['establecimiento_id'] : null;

        $user->save();

        foreach ($selectedRoles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
        $user->syncRoles($selectedRoles);

        $rolesText = collect($selectedRoles)
            ->map(fn (string $roleName) => $this->roleLabel($roleName))
            ->implode(', ');

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Usuario creado con roles: ' . $rolesText . '. Puede usar "Olvidé mi contraseña" para definir su clave.');
    }

    public function edit(User $user): View
    {
        $user->load(['roles', 'establecimiento']);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->availableRoles(),
            'establecimientos' => $this->establecimientosGrouped(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $allowedRoles = $this->allowedRoles();
        $currentRoles = $user->roles()->pluck('name')->all();

        $data = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'establecimiento_id' => ['nullable', 'integer', 'exists:establecimientos,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($allowedRoles)],
            'email_verified' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'Este email ya está registrado.',
            'roles.required' => 'Debes seleccionar al menos un rol.',
            'roles.array' => 'La selección de roles es inválida.',
            'roles.min' => 'Debes seleccionar al menos un rol.',
            'roles.*.in' => 'Uno de los roles seleccionados es inválido.',
        ]);

        $selectedRoles = collect($data['roles'] ?? [])->map(fn ($role) => (string) $role)->unique()->values()->all();
        $needsEstablecimiento = $this->requiresEstablecimiento($selectedRoles);

        if ($needsEstablecimiento && empty($data['establecimiento_id'])) {
            return back()->withErrors([
                'establecimiento_id' => 'Debes seleccionar un establecimiento para usuarios con rol Funcionario, Funcionario establecimiento o Funcionario Directivo Establecimiento.',
            ])->withInput();
        }

        if (! in_array('postulante', $currentRoles, true) && in_array('postulante', $selectedRoles, true) && app(RestrictedRutService::class)->isRestrictedRut((string) $user->rut)) {
            return back()->withErrors([
                'roles' => 'Este RUT mantiene una restricción vigente para ejercer y no puede quedar con rol postulante.',
            ])->withInput();
        }

        if ($request->user()?->is($user) && $user->hasRole('admin') && ! in_array('admin', $selectedRoles, true)) {
            return back()->withErrors([
                'roles' => 'No puedes quitarte a ti mismo el rol admin desde esta pantalla.',
            ])->withInput();
        }

        DB::transaction(function () use ($user, $data, $selectedRoles, $needsEstablecimiento) {
            $user->nombres = $data['nombres'];
            $user->apellido_paterno = $data['apellido_paterno'];
            $user->apellido_materno = $data['apellido_materno'];
            $user->email = $data['email'];
            $user->establecimiento_id = $needsEstablecimiento ? $data['establecimiento_id'] : null;
            $user->email_verified_at = !empty($data['email_verified'])
                ? ($user->email_verified_at ?? now())
                : null;
            $user->save();

            foreach ($selectedRoles as $roleName) {
                Role::findOrCreate($roleName, 'web');
            }
            $user->syncRoles($selectedRoles);
        });

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Contraseña incorrecta.'])->withInput();
        }

        if ($request->user()->is($user)) {
            return back()->withErrors(['general' => 'No puedes eliminar tu propia cuenta.']);
        }

        DB::transaction(function () use ($request, $user) {
            $user->deleted_by = $request->user()->id;
            $user->save();

            $profile = $user->postulantProfile()->first();
            if ($profile) {
                if (!empty($profile->foto_path) && Storage::disk('public')->exists($profile->foto_path)) {
                    Storage::disk('public')->delete($profile->foto_path);
                }
                if (!empty($profile->foto_thumb_path) && Storage::disk('public')->exists($profile->foto_thumb_path)) {
                    Storage::disk('public')->delete($profile->foto_thumb_path);
                }
                $profile->delete();
            }

            try {
                $user->communes()->detach();
            } catch (\Throwable $e) {
            }

            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('status', 'Usuario eliminado correctamente.');
    }

    private function filteredUsers(Request $request): LengthAwarePaginator
    {
        return $this->buildFilteredUsersQuery($request, true)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    private function buildFilteredUsersQuery(Request $request, bool $withRelations = false): Builder
    {
        $query = User::query();

        if ($withRelations) {
            $query->with(['roles', 'establecimiento']);
        }

        $search = trim((string) $request->string('q'));
        if ($search !== '') {
            $terms = preg_split('/\s+/', $search) ?: [];
            $query->where(function (Builder $outer) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . $term . '%';
                    $normalizedRut = strtoupper(preg_replace('/[^0-9Kk]/', '', $term));
                    $rutLike = $normalizedRut !== '' ? '%' . $normalizedRut . '%' : null;

                    $outer->where(function (Builder $inner) use ($like, $rutLike) {
                        if ($rutLike !== null) {
                            $inner->where('rut', 'like', $rutLike);
                        } else {
                            $inner->where('rut', 'like', $like);
                        }

                        $inner->orWhere('email', 'like', $like)
                            ->orWhere('nombres', 'like', $like)
                            ->orWhere('apellido_paterno', 'like', $like)
                            ->orWhere('apellido_materno', 'like', $like);
                    });
                }
            });
        }

        $role = trim((string) $request->string('rol'));
        if ($role !== '') {
            $query->whereHas('roles', fn(Builder $q) => $q->where('name', $role));
        }

        $verified = trim((string) $request->string('verificado'));
        if ($verified === 'si') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verified === 'no') {
            $query->whereNull('email_verified_at');
        }

        $establecimientoId = trim((string) $request->string('establecimiento_id'));
        if ($establecimientoId !== '') {
            $query->where('establecimiento_id', $establecimientoId);
        }

        return $query;
    }

    private function buildSummary(Request $request, Collection $roleLabels): array
    {
        $baseQuery = $this->buildFilteredUsersQuery($request, false);

        $total = (clone $baseQuery)->count();
        $verified = (clone $baseQuery)->whereNotNull('email_verified_at')->count();
        $pending = max($total - $verified, 0);

        $filteredUsersSub = (clone $baseQuery)->select('users.id', 'users.email_verified_at');

        $rows = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->joinSub($filteredUsersSub->toBase(), 'filtered_users', function ($join) {
                $join->on('filtered_users.id', '=', 'mhr.model_id');
            })
            ->where('mhr.model_type', User::class)
            ->whereIn('r.name', $this->allowedRoles())
            ->groupBy('r.name')
            ->orderBy('r.name')
            ->get([
                'r.name as role_name',
                DB::raw('COUNT(DISTINCT filtered_users.id) as total'),
                DB::raw('COUNT(DISTINCT CASE WHEN filtered_users.email_verified_at IS NOT NULL THEN filtered_users.id END) as verified'),
            ]);

        $summaryByRole = [];
        foreach ($roleLabels as $roleName => $label) {
            $summaryByRole[$roleName] = [
                'label' => $label,
                'total' => 0,
                'verified' => 0,
                'pending' => 0,
            ];
        }

        foreach ($rows as $row) {
            if (! array_key_exists($row->role_name, $summaryByRole)) {
                continue;
            }

            $roleTotal = (int) $row->total;
            $roleVerified = (int) $row->verified;
            $summaryByRole[$row->role_name]['total'] = $roleTotal;
            $summaryByRole[$row->role_name]['verified'] = $roleVerified;
            $summaryByRole[$row->role_name]['pending'] = max($roleTotal - $roleVerified, 0);
        }

        return [
            'total' => $total,
            'verified' => $verified,
            'pending' => $pending,
            'by_role' => $summaryByRole,
        ];
    }

    private function availableRoles()
    {
        $this->ensureDefaultRolesExist();

        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(Role $role) => [$role->name => $this->roleLabel($role->name)]);
    }

    private function establecimientosGrouped()
    {
        return Establecimiento::query()
            ->select('id', 'rbd', 'dv', 'nombre_establecimiento', 'comuna')
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get()
            ->groupBy(fn($e) => $e->comuna ?: 'Sin comuna');
    }




    private function formatRutForExport(?string $rut): string
    {
        $clean = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut) ?? '');

        if ($clean === '') {
            return '';
        }

        if (strlen($clean) === 1) {
            return $clean;
        }

        return substr($clean, 0, -1) . '-' . substr($clean, -1);
    }

    private function requiresEstablecimiento(array $roles): bool
    {
        return count(array_intersect($roles, ['funcionario', 'funcionario_estab', 'funcionario_directivo_estab'])) > 0;
    }

    private function containsInternalRole(array $roles): bool
    {
        return count(array_intersect($roles, $this->internalRoles())) > 0;
    }

    private function roleLabel(string $roleName): string
    {
        return match ($roleName) {
            'admin' => 'Admin',
            'funcionario_slep' => 'Funcionario SLEP',
            'coordinador_uatp' => 'Coordinador UATP',
            'comunicaciones' => 'Comunicaciones',
            'coordinador_gdp' => 'Coordinador GDP',
            'supervisor_plani' => 'Supervisor Planificación',
            'funcionario_estab' => 'Funcionario establecimiento',
            'funcionario_directivo_estab' => 'Funcionario Directivo Establecimiento',
            'funcionario_daf' => 'Funcionario DAF',
            'funcionario_juridica' => 'Funcionario Jurídica',
            'funcionario' => 'Funcionario',
            'postulante' => 'Postulante',
            default => Str::headline(str_replace(['-', '_'], ' ', $roleName)),
        };
    }

    private function allowedRoles(): array
    {
        $this->ensureDefaultRolesExist();

        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($role) => (string) $role)
            ->filter()
            ->values()
            ->all();
    }

    private function defaultRoleNames(): array
    {
        return [
            'admin',
            'funcionario_slep',
            'coordinador_uatp',
            'comunicaciones',
            'coordinador_gdp',
            'supervisor_plani',
            'funcionario_estab',
            'funcionario_directivo_estab',
            'funcionario_daf',
            'funcionario_juridica',
            'funcionario',
            'postulante',
        ];
    }

    private function ensureDefaultRolesExist(): void
    {
        foreach ($this->defaultRoleNames() as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }

    private function internalRoles(): array
    {
        return ['admin', 'funcionario_slep', 'coordinador_uatp', 'comunicaciones', 'coordinador_gdp', 'supervisor_plani', 'coordinador_plani', 'funcionario_estab', 'funcionario_directivo_estab', 'funcionario_daf', 'funcionario_juridica', 'funcionario'];
    }
}
