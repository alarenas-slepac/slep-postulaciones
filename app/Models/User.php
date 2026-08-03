<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// 👇 IMPORTANTE: importar el trait desde Spatie
use Spatie\Permission\Traits\HasRoles;
// 👇 IMPORTA LAS RELACIONES CORRECTAS
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\UserDocument;
use Illuminate\Support\Str;
use App\Models\Establecimiento;
use App\Models\Module;
use Illuminate\Support\Facades\Schema;
use App\Support\NotificationAudit;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, MustVerifyEmailTrait, HasRoles;

    protected $fillable = [
        // 'name', // eliminado según tu decisión
        'rut',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'establecimiento_id',
        'password',
        'trabaja_en_otro_lugar',
        'trabaja_en_otro_lugar_observacion',
        'trabaja_en_otro_lugar_marcado_por',
        'trabaja_en_otro_lugar_marcado_en',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime', // 👈 importante
            'trabaja_en_otro_lugar' => 'boolean',
            'trabaja_en_otro_lugar_marcado_en' => 'datetime',
        ];
    }

    // RUT inmutable + normalización
    public function setRutAttribute($value): void
    {
        $normalized = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $value));
        if ($this->exists && !empty($this->attributes['rut'])) {
            $this->attributes['rut'] = $this->attributes['rut']; // inmutable
            return;
        }
        $this->attributes['rut'] = $normalized;
    }

    // Email en minúsculas y sin espacios
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }

    public function getRutNormalizedAttribute(): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($this->attributes['rut'] ?? '')));
    }

    // Si quieres usar tus notificaciones personalizadas:
    public function sendEmailVerificationNotification(): void
    {
        NotificationAudit::dispatchNotification($this, new \App\Notifications\Auth\CustomVerifyEmail(), [
            'event_key' => 'auth.email_verification',
            'description' => 'Envío de verificación de correo',
            'subject' => 'Verifica tu correo',
            'related' => $this,
        ]);
    }

    public function sendPasswordResetNotification($token): void
    {
        NotificationAudit::dispatchNotification($this, new \App\Notifications\Auth\CustomResetPassword($token), [
            'event_key' => 'auth.password_reset',
            'description' => 'Envío de restablecimiento de contraseña',
            'subject' => 'Restablecer contraseña',
            'related' => $this,
        ]);
    }
    public function postulantProfile(): HasOne
    {
        return $this->hasOne(PostulantProfile::class);
    }

    public function communes(): BelongsToMany
    {
        return $this->belongsToMany(Commune::class, 'commune_user');
    }
    use SoftDeletes;


    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
    // App\Models\User
    public function documents()
    {
        return $this->hasMany(UserDocument::class);
    }
    public function getDisplayNameAttribute(): string
    {
        // Soporta distintos esquemas comunes
        $first = trim((string)($this->nombres ?? ''));
        $lasts = trim(implode(' ', array_filter([
            $this->apellido_paterno ?? null,
            $this->apellido_materno ?? null,
        ])));

        $full = trim($first . ' ' . $lasts);

        if ($full !== '') {
            return $full;
        }

        // Último recurso
        return (string)$this->email;
    }
    // Nombre completo (para vistas/APIs)
    public function getFullNameAttribute(): string
    {
        return trim(($this->nombres ?? '') . ' ' . ($this->apellido_paterno ?? '') . ' ' . ($this->apellido_materno ?? '')) ?: ($this->email ?? 'Usuario');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(2));
    }

    /** Inicial para avatar (primera letra de nombres o name/email). */
    public function initial(): string
    {
        $base = $this->nombres
            ?? $this->name
            ?? strtok((string)$this->email, '@')
            ?? 'U';

        return Str::upper(mb_substr(trim($base), 0, 1));
    }

    /** Nombre completo “bonito” para mostrar. */
    public function displayName(): string
    {
        $n = trim(($this->nombres ?? $this->name ?? '') . ' ' . ($this->apellido_paterno ?? ''));
        return $n !== '' ? $n : ($this->email ?? 'Usuario');
    }

    // app/Models/User.php

    public function getNombreCompletoAttribute(): string
    {
        return trim(collect([
            $this->nombres,
            $this->apellido_paterno,
            $this->apellido_materno,
        ])->filter()->implode(' '));
    }


    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function trabajoExternoMarcadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trabaja_en_otro_lugar_marcado_por');
    }

    public function tramites()
    {
        return $this->hasMany(Tramite::class);
    }

    public function cargasFamiliares()
    {
        return $this->hasMany(CargaFamiliar::class);
    }

    public function cargasFamiliaresSolicitudes()
    {
        return $this->hasMany(CargaFamiliarSolicitud::class, 'user_id');
    }

    /** @var array<string, array> */
    protected array $allowedModuleKeysCache = [];

    public static function roleContextLabels(): array
    {
        return [
            'admin' => 'Administrador',
            'coordinador_gdp' => 'Coordinador GDP',
            'coordinador_uatp' => 'Coordinador UATP',
            'comunicaciones' => 'Comunicaciones',
            'gabinete_slep' => 'Gabinete SLEP',
            'secretaria_direccion_ejecutiva' => 'Secretaría Dirección Ejecutiva',
            'supervisor_plani' => 'Supervisor Planificación',
            'funcionario_slep' => 'Funcionario SLEP',
            'funcionario_ac' => 'Funcionario Administracion Central',
            'funcionario_estab' => 'Funcionario Establecimiento',
            'funcionario_directivo_estab' => 'Funcionario Directivo Establecimiento',
            'funcionario_daf' => 'Funcionario DAF',
            'funcionario_juridica' => 'Funcionario Jurídica',
            'funcionario' => 'Funcionario',
            'postulante' => 'Postulante',
        ];
    }

    public static function roleContextPriority(): array
    {
        return array_keys(static::roleContextLabels());
    }

    public function availableRoleContexts()
    {
        $roles = $this->getRoleNames()
            ->map(fn ($role) => Str::lower(trim((string) $role)))
            ->unique()
            ->values();

        $priority = array_flip(static::roleContextPriority());

        return $roles->sortBy(fn ($role) => $priority[$role] ?? 999)->values();
    }

    public function hasMultipleRoleContexts(): bool
    {
        return $this->availableRoleContexts()->count() > 1;
    }

    public function roleContextLabel(?string $roleName): string
    {
        $roleName = Str::lower(trim((string) $roleName));
        return static::roleContextLabels()[$roleName] ?? Str::headline(str_replace('_', ' ', $roleName));
    }

    public function defaultActiveRoleName(): ?string
    {
        $roles = $this->availableRoleContexts();
        foreach (static::roleContextPriority() as $role) {
            if ($roles->contains($role)) {
                return $role;
            }
        }

        return $roles->first();
    }

    public function activeRoleName(): ?string
    {
        $active = Str::lower(trim((string) session('active_role', '')));

        if ($active !== '' && $this->availableRoleContexts()->contains($active)) {
            return $active;
        }

        return $this->defaultActiveRoleName();
    }

    public function allowedModuleKeys(?string $roleName = null): array
    {
        $cacheKey = $roleName ? Str::lower(trim($roleName)) : '__all__';

        if (array_key_exists($cacheKey, $this->allowedModuleKeysCache)) {
            return $this->allowedModuleKeysCache[$cacheKey];
        }

        // Si aún no están las migraciones del sistema de módulos, no romper la app
        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return $this->allowedModuleKeysCache[$cacheKey] = ['*'];
        }

        if ($roleName !== null) {
            $roleName = Str::lower(trim($roleName));

            if ($roleName === 'admin' && method_exists($this, 'hasRole') && $this->hasRole('admin')) {
                return $this->allowedModuleKeysCache[$cacheKey] = ['*'];
            }

            $role = $this->roles->first(function ($role) use ($roleName) {
                return Str::lower(trim((string) $role->name)) === $roleName;
            });

            if (!$role) {
                return $this->allowedModuleKeysCache[$cacheKey] = [];
            }

            $roleIds = [$role->id];
        } else {
            if (method_exists($this, 'hasRole') && $this->hasRole('admin')) {
                return $this->allowedModuleKeysCache[$cacheKey] = ['*'];
            }

            $roleIds = $this->roles()->pluck('id')->all();
        }

        if (empty($roleIds)) {
            return $this->allowedModuleKeysCache[$cacheKey] = [];
        }

        $keys = Module::query()
            ->select('modules.key')
            ->join('module_role', 'module_role.module_id', '=', 'modules.id')
            ->whereIn('module_role.role_id', $roleIds)
            ->distinct()
            ->pluck('modules.key')
            ->all();

        return $this->allowedModuleKeysCache[$cacheKey] = $keys;
    }

    public function canModule(string $key, ?string $roleName = null): bool
    {
        $keys = $this->allowedModuleKeys($roleName);
        return in_array('*', $keys, true) || in_array($key, $keys, true);
    }

    public function canAnyModule(array $keys, ?string $roleName = null): bool
    {
        foreach ($keys as $k) {
            if ($this->canModule($k, $roleName)) return true;
        }
        return false;
    }
}
