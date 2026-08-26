<?php

namespace App\Services\LicenciasMedicas;

use App\Models\LicenciaMedica;
use App\Models\LicenciaMedicaHistorial;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LicenciaEstadoService
{
    public const ADMINISTRATIVO = 'administrativo';

    public const COMPIN = 'compin';

    public const RECUPERACION = 'recuperacion';

    public function dimensiones(): array
    {
        return [self::ADMINISTRATIVO, self::COMPIN, self::RECUPERACION];
    }

    public function opciones(string $dimension): array
    {
        return (array) config("licencias_medicas.estados.{$dimension}", []);
    }

    public function normalizar(string $dimension, ?string $valor): ?string
    {
        if ($this->normalizarTexto($valor) === '') {
            return null;
        }

        return $this->normalizarEstricto($dimension, $valor)
            ?? (array_key_exists('otro', $this->opciones($dimension)) ? 'otro' : null);
    }

    public function normalizarEstricto(string $dimension, ?string $valor): ?string
    {
        $normalizado = $this->normalizarTexto($valor);
        if ($normalizado === '') {
            return null;
        }

        if (array_key_exists($normalizado, $this->opciones($dimension))) {
            return $normalizado;
        }

        foreach ((array) config("licencias_medicas.aliases.{$dimension}", []) as $codigo => $aliases) {
            foreach ((array) $aliases as $alias) {
                if ($this->normalizarTexto($alias) === $normalizado) {
                    return array_key_exists($codigo, $this->opciones($dimension)) ? $codigo : null;
                }
            }
        }

        return null;
    }

    public function etiqueta(string $dimension, ?string $codigo, ?string $respaldo = null): string
    {
        return $this->opciones($dimension)[$codigo] ?? ($respaldo ?: 'Sin clasificar');
    }

    public function columna(string $dimension): string
    {
        return match ($dimension) {
            self::ADMINISTRATIVO => 'estado_administrativo_codigo',
            self::COMPIN => 'estado_compin_codigo',
            self::RECUPERACION => 'estado_recuperacion_codigo',
            default => throw ValidationException::withMessages(['dimension' => 'La dimensión de estado no es válida.']),
        };
    }

    public function puedeGestionar(?Authenticatable $usuario, string $dimension): bool
    {
        if (! $usuario || ! method_exists($usuario, 'hasAnyRole')) {
            return false;
        }

        $roles = (array) config('licencias_medicas.roles.estado_'.$dimension, []);

        return $usuario->hasAnyRole($roles);
    }

    public function atributosParaCambio(string $dimension, ?string $codigo, int $userId, bool $actualizarLegado = false): array
    {
        $cambios = [
            $this->columna($dimension) => $codigo,
            'updated_by' => $userId,
        ];

        if ($actualizarLegado && $codigo !== null) {
            if ($dimension === self::ADMINISTRATIVO) {
                $cambios['estado_actual'] = $this->etiqueta($dimension, $codigo);
            } elseif ($dimension === self::COMPIN) {
                $cambios['estado_compin'] = $this->etiqueta($dimension, $codigo);
            }
        }

        return $cambios;
    }

    public function cambiar(
        LicenciaMedica $licencia,
        string $dimension,
        string $codigo,
        int $userId,
        string $observacion,
        string $origen = 'manual',
        ?int $importacionId = null
    ): LicenciaMedica {
        $columna = $this->columna($dimension);
        if (! array_key_exists($codigo, $this->opciones($dimension))) {
            throw ValidationException::withMessages(['estado_codigo' => 'El estado seleccionado no pertenece al catálogo vigente.']);
        }

        return DB::transaction(function () use ($licencia, $dimension, $codigo, $userId, $observacion, $origen, $importacionId, $columna) {
            $registro = LicenciaMedica::query()->lockForUpdate()->findOrFail($licencia->id);
            $anterior = $registro->{$columna};

            if ($anterior === $codigo) {
                throw ValidationException::withMessages(['estado_codigo' => 'La licencia ya tiene el estado seleccionado.']);
            }

            $cambios = $this->atributosParaCambio(
                $dimension,
                $codigo,
                $userId,
                $dimension === self::ADMINISTRATIVO || $origen === 'manual'
            );

            $registro->update($cambios);

            LicenciaMedicaHistorial::create([
                'licencia_medica_id' => $registro->id,
                'accion' => 'cambio_estado',
                'descripcion' => $observacion,
                'datos_anteriores' => [$columna => $anterior],
                'datos_nuevos' => [$columna => $codigo],
                'estado_dimension' => $dimension,
                'estado_anterior' => $anterior,
                'estado_nuevo' => $codigo,
                'origen' => $origen,
                'importacion_id' => $importacionId,
                'user_id' => $userId,
                'created_at' => now(),
            ]);

            return $registro->fresh();
        });
    }

    private function normalizarTexto(?string $valor): string
    {
        return (string) Str::of((string) $valor)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->lower();
    }
}
