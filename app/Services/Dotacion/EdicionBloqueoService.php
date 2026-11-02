<?php

namespace App\Services\Dotacion;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EdicionBloqueoService
{
    public const ROL_BLOQUEADO = 'funcionario_directivo_estab';
    public const ROLES_GESTION = ['admin', 'supervisor_plani', 'coordinador_gdp', 'coordinador_uatp'];

    public function disponible(): bool
    {
        return Schema::hasTable('dotacion_edicion_bloqueos') && Schema::hasTable('dotacion_edicion_bloqueo_historial');
    }

    public function puedeAdministrar(?User $user): bool
    {
        $rol = $user?->activeRoleName();

        return in_array($rol, self::ROLES_GESTION, true) && $user->hasRole($rol);
    }

    public function estado(?int $establecimientoId = null): array
    {
        $disponible = $this->disponible();
        $clave = 'establecimiento:'.($establecimientoId ?? 0);
        $registros = Schema::hasTable('dotacion_edicion_bloqueos')
            ? DB::table('dotacion_edicion_bloqueos')->whereIn('clave', ['general', $clave])->get()->keyBy('clave')
            : collect();
        $normalizar = static fn ($registro): array => [
            'bloqueado' => (bool) ($registro->bloqueado ?? false),
            'version' => (int) ($registro->version ?? 0),
            'motivo' => $registro->motivo ?? null,
            'updated_at' => $registro->updated_at ?? null,
            'updated_by' => $registro->updated_by ?? null,
            'rol_actualizador' => $registro->rol_actualizador ?? null,
        ];
        $general = $normalizar($registros->get('general'));
        $individual = $normalizar($registros->get($clave));
        $bloqueado = $general['bloqueado'] || $individual['bloqueado'];
        $origen = $general['bloqueado'] ? 'general' : ($individual['bloqueado'] ? 'establecimiento' : null);
        $motivo = $origen === 'general' ? $general['motivo'] : $individual['motivo'];

        return [
            'disponible' => $disponible, 'general' => $general, 'individual' => $individual,
            'bloqueado' => $bloqueado, 'origen' => $origen,
            'mensaje' => $bloqueado
                ? 'Dotación está en modo sólo consulta para el rol Funcionario Directivo de Establecimiento por un bloqueo '.($origen === 'general' ? 'general' : 'individual de este establecimiento').'. Aplica a todos los años y módulos asociados.'.($motivo ? ' Motivo: '.$motivo : '')
                : '',
        ];
    }

    public function guardar(?int $establecimientoId, bool $bloqueado, int $version, ?string $motivo, User $user): void
    {
        abort_unless($this->puedeAdministrar($user), 403);
        if (!$this->disponible()) {
            throw ValidationException::withMessages(['dotacion_bloqueo' => 'Instale la migración de bloqueos de edición antes de administrar esta configuración.']);
        }
        DB::transaction(function () use ($establecimientoId, $bloqueado, $version, $motivo, $user) {
            $clave = $establecimientoId ? 'establecimiento:'.$establecimientoId : 'general';
            DB::table('dotacion_edicion_bloqueos')->insertOrIgnore([
                'clave' => $clave, 'establecimiento_id' => $establecimientoId,
                'bloqueado' => false, 'version' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $actual = DB::table('dotacion_edicion_bloqueos')->where('clave', $clave)->lockForUpdate()->first();
            if ((int) $actual->version !== $version) {
                throw ValidationException::withMessages(['dotacion_bloqueo' => 'El bloqueo cambió desde que abrió la pantalla. Actualice la página antes de volver a guardar.']);
            }
            $motivo = trim((string) $motivo) ?: null;
            if ((bool) $actual->bloqueado === $bloqueado && $actual->motivo === $motivo) { return; }
            DB::table('dotacion_edicion_bloqueos')->where('id', $actual->id)->update([
                'bloqueado' => $bloqueado, 'version' => $version + 1, 'motivo' => $motivo,
                'updated_by' => $user->id, 'rol_actualizador' => $user->activeRoleName(), 'updated_at' => now(),
            ]);
            DB::table('dotacion_edicion_bloqueo_historial')->insert([
                'clave' => $clave, 'estado_anterior' => $actual->bloqueado, 'estado_nuevo' => $bloqueado,
                'version' => $version + 1, 'motivo' => $motivo, 'usuario_id' => $user->id,
                'rol_activo' => $user->activeRoleName(), 'created_at' => now(),
            ]);
        });
    }
}
