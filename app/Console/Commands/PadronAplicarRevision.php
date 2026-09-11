<?php

namespace App\Console\Commands;

use App\Models\PadronRevision;
use App\Models\User;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Console\Output\BufferedOutput;

/** Entrada operativa explícita. No se registra ningún escritor habilitado en el contenedor. */
class PadronAplicarRevision extends Command
{
    protected $signature = 'padron:aplicar-revision
        {revision : ID de la revisión ya resuelta}
        {--usuario= : ID del administrador responsable}
        {--aplicar : Ejecutar la escritura definitiva; sin esta opción solo consulta}
        {--confirmacion= : SHA-256 del plan consultado previamente}
        {--confirmar= : Frase APLICAR:revision:anio:mes mostrada en la consulta}
        {--respaldo-sha256= : SHA-256 del respaldo completo reciente, verificado por el operador}';

    protected $description = 'Consulta o aplica un padrón resuelto por CLI, con revalidación transaccional y confirmación explícita.';

    public function handle(): int
    {
        try {
            $this->exigir(PHP_SAPI === 'cli' && app()->runningInConsole(), 'Disponible únicamente desde PHP CLI.');
            foreach ([$this->argument('revision'), $this->option('usuario')] as $id) {
                $this->exigir(is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id), 'Indique revisión y --usuario como IDs positivos.');
            }
            $this->verificarEntorno();
            $usuario = User::find((int) $this->option('usuario'));
            $this->exigir($usuario && $usuario->hasRole('admin'), 'El usuario debe existir, no estar eliminado y tener rol admin.');
            $revision = PadronRevision::find((int) $this->argument('revision'));
            $this->exigir($revision !== null, 'La revisión indicada no existe.');
            if ($revision->aplicada_at) {
                $this->emitir(['estado' => 'ya_aplicada', 'revision' => $revision->id,
                    'aplicada_at' => (string) $revision->aplicada_at, 'aplicada_por' => $revision->aplicada_por]);
                return self::SUCCESS; // No recalcular ni volver a escribir una revisión aplicada.
            }
            $this->exigir(! app(PadronRevisionService::class)->stale($revision), 'La base contractual cambió; esta revisión requiere un nuevo análisis.');
            $plan = app(PadronAplicacionService::class)->plan($revision);
            $frase = 'APLICAR:'.$revision->id.':'.$revision->anio.':'.$revision->mes;
            $liberaciones = [];
            foreach (['bajas_asignaciones', 'traslados_asignaciones'] as $tipo) {
                $liberaciones[$tipo] = 0;
                foreach ($plan['conflictos'][$tipo] ?? [] as $confirmacion) {
                    if ($confirmacion['confirmada'] ?? false) {
                        $liberaciones[$tipo] += count($confirmacion['alcance']['asignaciones'] ?? []);
                    }
                }
            }
            $resumen = ['estado' => $plan['errores'] ? 'bloqueada' : 'lista_para_confirmar',
                'solo_lectura' => ! $this->option('aplicar'), 'web_habilitada' => false,
                'revision' => $revision->id, 'anio' => $revision->anio, 'mes' => $revision->mes,
                'destinos' => count($plan['destinos']), 'bajas' => count($plan['bajas']),
                'asignaciones_a_liberar' => $liberaciones,
                'casos_bloqueantes' => $plan['conflictos']['grupos_bloqueantes'] ?? 0,
                'casos_avisos' => $plan['conflictos']['grupos_avisos'] ?? 0,
                'cantidad_errores' => count($plan['errores'])];
            if ($plan['errores']) {
                // Los errores de dominio pueden contener RUT: consultar el detalle en la revisión autenticada.
                $this->emitir($resumen);
                $this->error('Quedan bloqueos. Revise el detalle en la pantalla del padrón; no se aplicaron cambios.');
                return self::FAILURE;
            }
            $hash = $plan['confirmacion_hash'];
            unset($plan); // No retener otra copia completa mientras el escritor recalcula bajo bloqueo.
            if (! $this->option('aplicar')) {
                $this->emitir($resumen + ['confirmacion' => $hash, 'confirmar' => $frase]);
                return self::SUCCESS;
            }
            $this->exigir(hash_equals($hash, (string) $this->option('confirmacion')), 'El plan cambió o falta --confirmacion. Consulte nuevamente sin --aplicar; se conservan sus decisiones.');
            $this->exigir($this->option('confirmar') === $frase, 'Falta la frase exacta --confirmar='.$frase.'.');
            $respaldo = (string) $this->option('respaldo-sha256');
            $this->exigir((bool) preg_match('/^[a-f0-9]{64}$/D', $respaldo), 'Indique --respaldo-sha256 del respaldo completo reciente y verificado.');

            $contexto = ['revision' => $revision->id, 'usuario_id' => $usuario->id,
                'confirmacion_hash' => $hash, 'respaldo_sha256' => $respaldo,
                'anio' => $revision->anio, 'mes' => $revision->mes];
            // Metadatos operativos, sin nombres, RUT, contratos ni credenciales.
            // La auditoría contractual y de asignaciones permanece dentro de la transacción existente.
            Log::info('padron.cli.aplicacion_solicitada', $contexto);
            $inicio = microtime(true);
            $aplicada = $this->ejecutor()->aplicar($revision, (int) $usuario->id, $hash);
            $resultado = ['estado' => 'aplicada', 'revision' => $aplicada->id,
                'aplicada_at' => (string) $aplicada->aplicada_at, 'aplicada_por' => $aplicada->aplicada_por,
                'segundos' => round(microtime(true) - $inicio, 2),
                'memoria_pico_mb' => round(memory_get_peak_usage(true) / 1048576, 2), 'web_habilitada' => false];
            Log::info('padron.cli.aplicacion_completada', $contexto + $resultado);
            $this->emitir($resultado);
            return self::SUCCESS;
        } catch (ValidationException) {
            $this->error('La revalidación impidió aplicar el padrón. Consulte nuevamente el plan y la revisión; las decisiones se conservan.');
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage()); // Solo mensajes propios, nunca SQL ni datos del padrón.
        } catch (\Throwable) {
            // Una desconexión al confirmar puede dejar un resultado incierto. No prometer rollback.
            $this->error('No se pudo completar o confirmar la operación. Consulte esta revisión sin --aplicar antes de reintentar; no restaure ni repita a ciegas.');
        }
        return self::FAILURE;
    }

    protected function verificarEntorno(): void
    {
        $memory = ini_parse_quantity((string) ini_get('memory_limit'));
        $this->exigir($memory >= 256 * 1048576, 'Use PHP 8.3 con -d memory_limit=256M (límite finito mínimo de 256 MB).');
        $output = new BufferedOutput;
        $this->exigir($this->runCommand('padron:verificar-entorno', ['--json' => true], $output) === self::SUCCESS,
            'El diagnóstico técnico falló. Ejecute padron:verificar-entorno --json antes de continuar.');
        $report = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);
        $this->exigir((bool) preg_match('/^10\.11\.\d+-MariaDB/', $report['servidor_version'] ?? ''),
            'Este ejecutor requiere la serie MariaDB 10.11 validada en el ensayo.');
        // El diagnóstico original antecede a las liberaciones: verificar también sus tablas y auditoría.
        foreach (['padron_bajas_asignaciones' => ['tipo', 'establecimiento_origen_id', 'establecimiento_destino_id', 'alcance_hash', 'alcance'],
            'padron_asignacion_cambios' => ['padron_revision_id', 'asignacion_id', 'antes', 'despues']] as $table => $columns) {
            $engine = DB::table('information_schema.TABLES')->whereRaw('TABLE_SCHEMA = DATABASE()')->where('TABLE_NAME', $table)->value('ENGINE');
            $this->exigir(strtoupper((string) $engine) === 'INNODB' && ! array_diff($columns, Schema::getColumnListing($table)),
                'Revise motor y migraciones de '.$table.'.');
            $this->exigir(! DB::table('information_schema.TRIGGERS')->whereRaw('TRIGGER_SCHEMA = DATABASE()')
                ->where('EVENT_OBJECT_TABLE', $table)->exists(), 'Debe revisar los triggers de '.$table.' antes de aplicar.');
        }
    }

    private function ejecutor(): PadronAplicacionService
    {
        // Capacidad local y efímera: nunca cambiar la constante ni el binding usado por HTTP.
        return new class(app(PadronRevisionService::class)) extends PadronAplicacionService {
            public function disponible(): bool
            {
                return PHP_SAPI === 'cli' && app()->runningInConsole();
            }
        };
    }

    private function exigir(mixed $condicion, string $mensaje): void
    {
        if (! $condicion) { throw new \InvalidArgumentException($mensaje); }
    }

    private function emitir(array $datos): void
    {
        $this->line(json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
