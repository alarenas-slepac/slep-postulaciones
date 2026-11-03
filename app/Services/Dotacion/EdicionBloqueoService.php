<?php

namespace App\Services\Dotacion;

use App\Models\User;
use Carbon\CarbonImmutable;
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

    public function desbloqueoDisponible(): bool
    {
        return $this->disponible() && Schema::hasColumn('dotacion_edicion_bloqueos', 'desbloqueo_hasta')
            && Schema::hasColumns('dotacion_edicion_bloqueo_historial', ['desbloqueo_hasta_anterior', 'desbloqueo_hasta_nuevo']);
    }

    public function zonaHoraria(): string
    {
        return config('app.display_timezone', 'America/Santiago');
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
            'desbloqueo_hasta' => $registro->desbloqueo_hasta ?? null,
        ];
        $general = $normalizar($registros->get('general'));
        $individual = $normalizar($registros->get($clave));
        $hasta = $individual['desbloqueo_hasta']
            ? CarbonImmutable::parse($individual['desbloqueo_hasta'], config('app.timezone')) : null;
        $temporalDisponible = $this->desbloqueoDisponible();
        $vencido = $hasta && $hasta->lte(now());
        $activo = $establecimientoId && !$individual['bloqueado'] && $hasta && !$vencido && $temporalDisponible;
        // El plazo vence también si se desactiva el general. Consultar el estado
        // no escribe ni depende de cron: cada petición verifica la hora vigente.
        $individual['bloqueado_efectivo'] = $individual['bloqueado'] || $vencido || ($hasta && !$temporalDisponible);
        $bloqueado = ($general['bloqueado'] && !$activo) || $individual['bloqueado_efectivo'];
        $origen = $general['bloqueado'] && !$activo ? 'general' : ($individual['bloqueado_efectivo'] ? 'establecimiento' : null);
        $motivo = $origen === 'general' ? $general['motivo'] : $individual['motivo'];
        $fecha = $hasta?->setTimezone($this->zonaHoraria())->format('d/m/Y H:i');

        return [
            'disponible' => $disponible, 'general' => $general, 'individual' => $individual,
            'bloqueado' => $bloqueado, 'origen' => $origen,
            'desbloqueo_disponible' => $temporalDisponible, 'desbloqueo_activo' => (bool) $activo,
            'desbloqueo_vencido' => (bool) $vencido, 'desbloqueo_hasta_texto' => $fecha,
            'desbloqueo_hasta_formulario' => $hasta?->setTimezone($this->zonaHoraria())->format('Y-m-d H:i'),
            'desbloqueo_hasta_iso' => $hasta?->toIso8601String(), 'zona_horaria' => $this->zonaHoraria(),
            'segundos_restantes' => $activo ? max(0, now()->diffInSeconds($hasta, false)) : 0,
            'mensaje' => $bloqueado
                ? 'Dotación está en modo sólo consulta para el rol Funcionario Directivo de Establecimiento por un bloqueo '.($origen === 'general' ? 'general' : 'individual de este establecimiento').'. Aplica a todos los años y módulos asociados.'.($vencido ? ' El desbloqueo autorizado venció el '.$fecha.' ('.$this->zonaHoraria().').' : '').($motivo ? ' Motivo: '.$motivo : '')
                : '',
        ];
    }

    public function guardar(?int $establecimientoId, bool $bloqueado, int $version, ?string $motivo, User $user, ?string $desbloqueoHasta = null): void
    {
        abort_unless($this->puedeAdministrar($user), 403);
        if (!$this->disponible()) {
            throw ValidationException::withMessages(['dotacion_bloqueo' => 'Instale la migración de bloqueos de edición antes de administrar esta configuración.']);
        }
        $temporal = $establecimientoId !== null && !$bloqueado;
        if ($temporal && !$this->desbloqueoDisponible()) {
            throw ValidationException::withMessages(['dotacion_bloqueo' => 'Instale la actualización de desbloqueos temporales antes de autorizar una reapertura.']);
        }
        if (!$temporal && $desbloqueoHasta !== null) {
            throw ValidationException::withMessages(['desbloqueo_hasta' => 'El plazo sólo se aplica a un desbloqueo individual autorizado.']);
        }
        DB::transaction(function () use ($establecimientoId, $bloqueado, $version, $motivo, $user, $desbloqueoHasta, $temporal) {
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
            $hasta = null;
            if ($temporal) {
                if (!$motivo) {
                    throw ValidationException::withMessages(['motivo' => 'Indique el motivo de la autorización de desbloqueo.']);
                }
                try {
                    $fecha = $desbloqueoHasta !== null && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $desbloqueoHasta)
                        ? CarbonImmutable::createFromFormat('!Y-m-d H:i', $desbloqueoHasta, $this->zonaHoraria()) : null;
                } catch (\Carbon\Exceptions\InvalidFormatException) {
                    $fecha = null;
                }
                if (!$fecha || $fecha->format('Y-m-d H:i') !== $desbloqueoHasta || $fecha->lte(now())) {
                    throw ValidationException::withMessages(['desbloqueo_hasta' => 'Indique una fecha y hora futuras válidas para volver a bloquear, en '.$this->zonaHoraria().'.']);
                }
                $hasta = $fecha->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            }
            if ((bool) $actual->bloqueado === $bloqueado && $actual->motivo === $motivo && ($actual->desbloqueo_hasta ?? null) === $hasta) { return; }
            $cambiosPlazo = Schema::hasColumn('dotacion_edicion_bloqueos', 'desbloqueo_hasta') ? ['desbloqueo_hasta' => $hasta] : [];
            DB::table('dotacion_edicion_bloqueos')->where('id', $actual->id)->update([
                'bloqueado' => $bloqueado, 'version' => $version + 1, 'motivo' => $motivo,
                'updated_by' => $user->id, 'rol_actualizador' => $user->activeRoleName(), 'updated_at' => now(),
            ] + $cambiosPlazo);
            $auditoriaPlazo = Schema::hasColumns('dotacion_edicion_bloqueo_historial', ['desbloqueo_hasta_anterior', 'desbloqueo_hasta_nuevo'])
                ? ['desbloqueo_hasta_anterior' => $actual->desbloqueo_hasta ?? null, 'desbloqueo_hasta_nuevo' => $hasta] : [];
            DB::table('dotacion_edicion_bloqueo_historial')->insert([
                'clave' => $clave, 'estado_anterior' => $actual->bloqueado, 'estado_nuevo' => $bloqueado,
                'version' => $version + 1, 'motivo' => $motivo, 'usuario_id' => $user->id,
                'rol_activo' => $user->activeRoleName(), 'created_at' => now(),
            ] + $auditoriaPlazo);
        });
    }
}
