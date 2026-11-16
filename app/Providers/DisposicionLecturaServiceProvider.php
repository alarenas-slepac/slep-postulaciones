<?php

namespace App\Providers;

use App\Services\Dotacion\DisposicionLecturaCache;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DisposicionLecturaServiceProvider extends ServiceProvider
{
    private array $pendientes = [];

    public function register(): void
    {
        $this->app->scoped(DisposicionLecturaCache::class);
    }

    public function boot(): void
    {
        Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $event): void {
            unset($this->pendientes[spl_object_id($event->connection)]);
        });
        DB::listen(function (QueryExecuted $event): void {
            if ((int) config('dotacion_disposicion.cache_seconds', 120) <= 0) { return; }
            // Incluye escrituras mediante Query Builder, que no disparan observers.
            if (! preg_match('/^\s*(?:insert(?:\s+or\s+\w+|\s+ignore)?\s+into|update|delete\s+from|replace\s+into)\s+[`"\[]?([a-zA-Z0-9_]+)/i', $event->sql, $match)) {
                return;
            }
            $table = strtolower($match[1]);
            $prefix = $event->connection->getTablePrefix();
            if ($prefix !== '' && str_starts_with($table, strtolower($prefix))) {
                $table = substr($table, strlen($prefix));
            }
            if ($table === 'dotacion_docente_distribuciones'
                || ! preg_match('/^(dotacion_|establecimiento|planes_estudio|docente_|padron_|reemplazos_personal$|declaracion_sostenedores$|alumnos_prioritarios|areas_desempeno$|cursos$)/', $table)) {
                return;
            }
            $invalidar = fn () => app(DisposicionLecturaCache::class)->invalidar();
            if ($event->connection->transactionLevel() > 0) {
                // No publicar una generación nueva antes del commit: otro lector
                // podría guardar en ella los datos todavía no confirmados.
                $id = spl_object_id($event->connection);
                if (isset($this->pendientes[$id])) { return; }
                $this->pendientes[$id] = true;
                $event->connection->afterCommit(function () use ($id, $invalidar): void {
                    unset($this->pendientes[$id]);
                    $invalidar();
                });
            } else {
                $invalidar();
            }
        });
    }
}
