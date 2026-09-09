<?php

namespace App\Services\Padron;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Entrada antes de leer/validar; comparte el control del aplicador completo. */
class PadronEscrituraService
{
    private ?\Illuminate\Database\Connection $connection = null;

    public function ejecutar(callable $operacion): mixed
    {
        $connection = DB::connection();
        if ($this->connection === $connection && $connection->transactionLevel() > 0) {
            return $operacion(); // Importador/servicio dentro de una petición coordinada.
        }
        // Compatibilidad previa a la migración: el aplicador no puede habilitarse
        // sin esta tabla. No consultar metadatos dentro de la nueva transacción.
        if (! Schema::hasTable('padron_aplicacion_control')) {
            return $operacion();
        }
        if ($connection->transactionLevel() !== 0) {
            throw new \LogicException('La coordinación del padrón debe comenzar antes de abrir otra transacción o leer datos.');
        }

        // Un solo intento: la operación puede incluir archivos o notificaciones;
        // nunca repetir automáticamente efectos externos de un controlador.
        try {
            return $connection->transaction(function () use ($connection, $operacion) {
                if (! $connection->table('padron_aplicacion_control')->where('id', 1)->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['padron' => 'Falta el control de escritura del padrón. Contacte al administrador.']);
                }
                $this->connection = $connection;
                try {
                    return $operacion();
                } finally {
                    $this->connection = null;
                }
            }, 1);
        } catch (\Illuminate\Database\QueryException $exception) {
            $cause = $exception->getPrevious();
            if ($cause instanceof \PDOException && in_array((int) ($cause->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw ValidationException::withMessages(['padron' => 'No se completó la operación por un bloqueo concurrente del padrón. Revise el estado actual antes de reintentar.']);
            }
            throw $exception;
        }
    }
}
