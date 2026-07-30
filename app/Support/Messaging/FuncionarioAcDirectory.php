<?php

namespace App\Support\Messaging;

use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use App\Support\SlepUiRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FuncionarioAcDirectory
{
    public const ESTABLECIMIENTO_ROLES = [
        'funcionario_estab',
        'funcionario_establecimiento',
        'funcionario_directivo_estab',
        'funcionario_directivo_establecimiento',
    ];

    public const INTERNAL_ROLES = [
        'admin',
        'director_ejecutivo',
        'funcionario_ac',
        'funcionario_slep',
        'coordinador_gdp',
        'coordinador_uatp',
        'supervisor_plani',
        'coordinador_plani',
        'funcionario_daf',
        'funcionario_daf_compra',
        'funcionario_juridica',
        'juridica',
    ];

    public static function userRoles(User $user): Collection
    {
        if (! method_exists($user, 'getRoleNames')) {
            return collect();
        }

        return $user->getRoleNames()
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter()
            ->values();
    }

    public static function canUseDirectory(User $user): bool
    {
        return self::userRoles($user)
            ->intersect(array_merge(self::ESTABLECIMIENTO_ROLES, self::INTERNAL_ROLES))
            ->isNotEmpty();
    }

    public static function canStartGeneralConversation(User $user): bool
    {
        return self::userRoles($user)->intersect(self::INTERNAL_ROLES)->isNotEmpty();
    }

    public static function isEstablishmentUser(User $user): bool
    {
        return self::userRoles($user)->intersect(self::ESTABLECIMIENTO_ROLES)->isNotEmpty();
    }

    /**
     * Determina si el destinatario pertenece a la libreta institucional.
     *
     * La fuente de verdad es el rol funcionario_ac asignado al usuario. Esto
     * permite incluir cuentas registradas aunque el vínculo
     * funcionarios_ac_autorizados.registered_user_id aún no haya sido
     * completado o provenga de una carga histórica.
     */
    public static function isActiveDirectoryUser(int $userId): bool
    {
        return User::query()
            ->whereKey($userId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'funcionario_ac')
                    ->where('guard_name', 'web');
            })
            ->exists();
    }

    /**
     * Consulta histórica conservada para compatibilidad con otros consumos.
     * La libreta visible utiliza items(), cuya fuente principal son los
     * usuarios registrados con rol funcionario_ac.
     */
    public static function queryActiveDirectory(array $filters = [])
    {
        $today = now()->toDateString();
        $search = trim((string) ($filters['q'] ?? ''));
        $subdireccion = trim((string) ($filters['subdireccion'] ?? ''));
        $unidad = trim((string) ($filters['unidad'] ?? ''));
        $recentOnly = (bool) ($filters['recent_only'] ?? false);

        $query = FuncionarioAcAutorizado::query()
            ->with([
                'registeredUser:id,rut,nombres,apellido_paterno,apellido_materno,email,last_seen_at,deleted_at',
                'registeredUser.roles:id,name',
            ])
            ->whereNotNull('registered_user_id')
            ->whereRaw('LOWER(COALESCE(estado_autorizacion, "")) = ?', ['activo'])
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_inicio_autorizacion')
                    ->orWhereDate('fecha_inicio_autorizacion', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('fecha_fin_autorizacion')
                    ->orWhereDate('fecha_fin_autorizacion', '>=', $today);
            })
            ->whereHas('registeredUser')
            ->when($subdireccion !== '', function ($query) use ($subdireccion) {
                $query->where('subdireccion_dependencia', $subdireccion);
            })
            ->when($unidad !== '', function ($query) use ($unidad) {
                $query->where('unidad_departamento', $unidad);
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(' ', '%', $search) . '%';
                $query->where(function ($subquery) use ($like) {
                    $subquery->where('nombres', 'like', $like)
                        ->orWhere('apellido_paterno', 'like', $like)
                        ->orWhere('apellido_materno', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('cargo_funcion', 'like', $like)
                        ->orWhere('unidad_departamento', 'like', $like)
                        ->orWhere('subdireccion_dependencia', 'like', $like)
                        ->orWhere('run', 'like', $like);
                });
            })
            ->orderBy('subdireccion_dependencia')
            ->orderBy('unidad_departamento')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres');

        if ($recentOnly) {
            $query->whereHas('registeredUser', function ($userQuery) {
                $userQuery->where('last_seen_at', '>=', now()->subDays(30));
            });
        }

        return $query;
    }

    /**
     * Construye la libreta a partir de users + rol funcionario_ac.
     *
     * Los antecedentes de subdirección, unidad y cargo se enriquecen desde
     * funcionarios_ac_autorizados por registered_user_id y, como respaldo,
     * por RUT normalizado. De esta manera una cuenta recién registrada no se
     * pierde por un vínculo pendiente o histórico.
     */
    public static function items(array $filters = []): Collection
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $subdireccion = trim((string) ($filters['subdireccion'] ?? ''));
        $unidad = trim((string) ($filters['unidad'] ?? ''));
        $recentOnly = (bool) ($filters['recent_only'] ?? false);

        $users = User::query()
            ->select([
                'id',
                'rut',
                'nombres',
                'apellido_paterno',
                'apellido_materno',
                'email',
                'last_seen_at',
                'deleted_at',
            ])
            ->with('roles:id,name')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'funcionario_ac')
                    ->where('guard_name', 'web');
            })
            ->when($recentOnly, function ($query) {
                $query->where('last_seen_at', '>=', now()->subDays(30));
            })
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->get();

        if ($users->isEmpty()) {
            return collect();
        }

        $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->values();
        $normalizedRuts = $users
            ->map(fn (User $user) => self::normalizeRut($user->rut))
            ->filter()
            ->unique()
            ->values();

        $authorizations = FuncionarioAcAutorizado::query()
            ->where(function ($query) use ($userIds, $normalizedRuts) {
                $query->whereIn('registered_user_id', $userIds);

                if ($normalizedRuts->isNotEmpty()) {
                    $query->orWhereIn('rut_normalizado', $normalizedRuts);
                }
            })
            ->orderByDesc('updated_at')
            ->get();

        $byUserId = $authorizations
            ->filter(fn (FuncionarioAcAutorizado $item) => ! empty($item->registered_user_id))
            ->groupBy(fn (FuncionarioAcAutorizado $item) => (int) $item->registered_user_id);

        $byRut = $authorizations
            ->filter(fn (FuncionarioAcAutorizado $item) => self::normalizeRut($item->rut_normalizado ?: ($item->run . $item->dv)) !== '')
            ->groupBy(fn (FuncionarioAcAutorizado $item) => self::normalizeRut($item->rut_normalizado ?: ($item->run . $item->dv)));

        $items = $users
            ->map(function (User $user) use ($byUserId, $byRut) {
                $linked = collect($byUserId->get((int) $user->id, collect()));
                $rut = self::normalizeRut($user->rut);
                $matchedByRut = $rut !== '' ? collect($byRut->get($rut, collect())) : collect();

                $funcionario = self::preferredAuthorization($linked)
                    ?? self::preferredAuthorization($matchedByRut);

                return self::mapUser($user, $funcionario);
            })
            ->filter(fn ($item) => ! empty($item['user_id']));

        if ($search !== '') {
            $terms = collect(preg_split('/\s+/', self::normalizeSearch($search)) ?: [])
                ->filter()
                ->values();

            $items = $items->filter(function (array $item) use ($terms) {
                $haystack = self::normalizeSearch(implode(' ', [
                    $item['name'] ?? '',
                    $item['email'] ?? '',
                    $item['cargo'] ?? '',
                    $item['subdireccion'] ?? '',
                    $item['unidad'] ?? '',
                    $item['escalafon'] ?? '',
                    $item['grado'] ?? '',
                    $item['rut'] ?? '',
                ]));

                return $terms->every(fn ($term) => str_contains($haystack, $term));
            });
        }

        if ($subdireccion !== '') {
            $expected = self::normalizeSearch($subdireccion);
            $items = $items->filter(fn ($item) => self::normalizeSearch((string) ($item['subdireccion'] ?? '')) === $expected);
        }

        if ($unidad !== '') {
            $expected = self::normalizeSearch($unidad);
            $items = $items->filter(fn ($item) => self::normalizeSearch((string) ($item['unidad'] ?? '')) === $expected);
        }

        return $items
            ->sortBy(fn ($item) => self::normalizeSearch(implode('|', [
                $item['subdireccion'] ?? '',
                $item['unidad'] ?? '',
                $item['name'] ?? '',
            ])), SORT_NATURAL)
            ->values();
    }

    public static function grouped(array $filters = []): Collection
    {
        return self::items($filters)
            ->groupBy(fn ($item) => $item['subdireccion'] ?: 'Sin subdirección registrada')
            ->map(fn (Collection $items) => $items->groupBy(fn ($item) => $item['unidad'] ?: 'Sin unidad registrada'));
    }

    public static function filters(): array
    {
        $items = self::items();

        return [
            'subdirecciones' => $items
                ->pluck('subdireccion')
                ->filter(fn ($value) => $value && $value !== 'Sin subdirección registrada')
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'unidades' => $items
                ->pluck('unidad')
                ->filter(fn ($value) => $value && $value !== 'Sin unidad registrada')
                ->unique()
                ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ];
    }

    public static function mapFuncionario(FuncionarioAcAutorizado $funcionario): array
    {
        $user = $funcionario->registeredUser;

        if ($user) {
            return self::mapUser($user, $funcionario);
        }

        $name = trim((string) ($funcionario->nombre_completo ?: $funcionario->email ?: 'Funcionario AC'));
        $initials = self::initials($name);

        return [
            'id' => $funcionario->id,
            'user_id' => null,
            'name' => $name,
            'initials' => $initials,
            'email' => $funcionario->email,
            'cargo' => $funcionario->cargo_funcion,
            'subdireccion' => $funcionario->subdireccion_dependencia ?: 'Sin subdirección registrada',
            'unidad' => $funcionario->unidad_departamento ?: 'Sin unidad registrada',
            'escalafon' => $funcionario->escalafon,
            'grado' => $funcionario->grado,
            'rut' => self::normalizeRut($funcionario->rut_normalizado ?: ($funcionario->run . $funcionario->dv)),
            'online' => false,
            'last_seen_label' => 'Sin conexión reciente',
            'roles' => collect(),
            'role_labels' => collect(),
        ];
    }

    private static function mapUser(User $user, ?FuncionarioAcAutorizado $funcionario = null): array
    {
        $name = trim((string) ($user->full_name ?: $funcionario?->nombre_completo ?: $user->email ?: 'Funcionario AC'));
        $lastSeen = $user->last_seen_at;
        $roles = method_exists($user, 'getRoleNames')
            ? $user->getRoleNames()->map(fn ($role) => strtolower((string) $role))->values()
            : collect();

        return [
            'id' => $funcionario?->id,
            'user_id' => $user->id,
            'name' => $name,
            'initials' => self::initials($name),
            'email' => $user->email ?: $funcionario?->email,
            'cargo' => $funcionario?->cargo_funcion,
            'subdireccion' => $funcionario?->subdireccion_dependencia ?: 'Sin subdirección registrada',
            'unidad' => $funcionario?->unidad_departamento ?: 'Sin unidad registrada',
            'escalafon' => $funcionario?->escalafon,
            'grado' => $funcionario?->grado,
            'rut' => self::normalizeRut($user->rut ?: $funcionario?->rut_normalizado),
            'online' => $lastSeen && $lastSeen->gte(now()->subMinutes(5)),
            'last_seen_label' => $lastSeen ? $lastSeen->diffForHumans() : 'Sin conexión reciente',
            'roles' => $roles,
            'role_labels' => $roles->map(fn ($role) => SlepUiRegistry::roleLabel($role))->values(),
        ];
    }

    private static function preferredAuthorization(Collection $items): ?FuncionarioAcAutorizado
    {
        if ($items->isEmpty()) {
            return null;
        }

        return $items
            ->sortByDesc(function (FuncionarioAcAutorizado $item) {
                $activeScore = self::authorizationIsCurrent($item) ? 1000000000000 : 0;
                $updatedScore = (int) optional($item->updated_at)->format('YmdHis');

                return $activeScore + $updatedScore;
            })
            ->first();
    }

    private static function authorizationIsCurrent(FuncionarioAcAutorizado $funcionario): bool
    {
        if (strtolower(trim((string) $funcionario->estado_autorizacion)) !== 'activo') {
            return false;
        }

        $today = now()->startOfDay();

        if ($funcionario->fecha_inicio_autorizacion && $funcionario->fecha_inicio_autorizacion->startOfDay()->gt($today)) {
            return false;
        }

        if ($funcionario->fecha_fin_autorizacion && $funcionario->fecha_fin_autorizacion->startOfDay()->lt($today)) {
            return false;
        }

        return true;
    }

    private static function initials(string $name): string
    {
        return collect(preg_split('/\s+/', $name) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('') ?: 'AC';
    }

    private static function normalizeRut(mixed $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut) ?? '');
    }

    private static function normalizeSearch(mixed $value): string
    {
        return Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
