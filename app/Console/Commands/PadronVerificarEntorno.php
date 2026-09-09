<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Diagnóstico de solo lectura. No imprime conexión, credenciales ni registros. */
class PadronVerificarEntorno extends Command
{
    protected $signature = 'padron:verificar-entorno {--json : Mostrar diagnóstico sin datos personales}';

    protected $description = 'Verifica requisitos técnicos del padrón sin aplicar cargas ni ejecutar migraciones.';

    public const REQUERIDAS = [
        'reemplazos_personal' => ['id', 'rut', 'vigente', 'fecha_antiguedad', 'row_hash', 'anio', 'mes'],
        'padron_aplicacion_control' => ['id'],
        'padron_revisiones' => ['aplicada_at', 'aplicada_por'],
        'padron_revision_filas' => ['id'], 'padron_revision_decisiones' => ['id'],
        'padron_revision_autorizaciones' => ['id'], 'padron_personal_cambios' => ['id'],
        'padron_periodo_versiones' => ['id'], 'padron_periodo_personal' => ['personal_id'],
        'establecimientos' => ['id', 'rbd'], 'declaracion_sostenedores' => ['id'],
        'dotacion_docente_asignaciones' => ['id'], 'dotacion_docente_exclusiones' => ['id'],
        'reemplazos_personal_bloqueos' => ['id', 'reemplazo_personal_id', 'rut', 'activo'],
        'solicitudes_reemplazo' => ['padron_personal_snapshot'],
        'cometidos_funcionarios' => ['padron_personal_snapshot'],
        'incumplimientos_laborales' => ['padron_personal_snapshot'],
    ];

    public function handle(): int
    {
        $report = ['php' => PHP_VERSION, 'laravel' => app()->version(),
            'driver' => DB::connection()->getDriverName(), 'memory_limit' => ini_get('memory_limit'),
            'solo_lectura' => true, 'habilita_aplicacion' => false, 'errores' => []];
        if (PHP_VERSION_ID < 80300) { $report['errores'][] = 'Se requiere PHP 8.3 o superior.'; }
        if ($report['driver'] !== 'mysql') {
            $report['errores'][] = 'Esta verificación requiere la conexión MySQL/MariaDB del entorno de pruebas.';
        } else {
            try {
                $report['servidor_version'] = DB::selectOne('SELECT VERSION() AS version')->version;
                $vars = DB::select("SHOW VARIABLES WHERE Variable_name IN ('transaction_isolation', 'tx_isolation', 'innodb_lock_wait_timeout', 'sql_mode')");
                $report['variables'] = collect($vars)->mapWithKeys(fn ($v) => [$v->Variable_name => $v->Value])->all();
                $engines = collect(DB::select('SELECT TABLE_NAME AS tabla, ENGINE AS motor FROM information_schema.tables WHERE table_schema = DATABASE()'));
                $report['tablas'] = [];
                foreach (self::REQUERIDAS as $table => $columns) {
                    $engine = $engines->firstWhere('tabla', $table)?->motor;
                    $missing = $engine === null ? $columns : array_values(array_diff($columns, Schema::getColumnListing($table)));
                    $report['tablas'][$table] = ['motor' => $engine, 'columnas_faltantes' => $missing];
                    if (strtoupper((string) $engine) !== 'INNODB' || $missing) {
                        $report['errores'][] = 'Revisar estructura o motor transaccional de '.$table.'.';
                    }
                }
                if (! Schema::hasTable('padron_aplicacion_control') || ! DB::table('padron_aplicacion_control')->where('id', 1)->exists()) {
                    $report['errores'][] = 'Falta la fila global de control ID 1.';
                }
                // Solo nombres de objetos/metadatos; nunca cuerpos de triggers ni datos.
                $report['triggers'] = DB::table('information_schema.TRIGGERS')
                    ->whereRaw('TRIGGER_SCHEMA = DATABASE()')->whereIn('EVENT_OBJECT_TABLE', array_keys(self::REQUERIDAS))
                    ->get(['TRIGGER_NAME', 'EVENT_OBJECT_TABLE', 'EVENT_MANIPULATION'])->all();
                if ($report['triggers']) { $report['errores'][] = 'Existen triggers en tablas participantes: revisar efectos antes de certificar.'; }
            } catch (\Throwable) {
                $report['errores'][] = 'No se pudo completar la inspección. Revise acceso y permisos de lectura de metadatos; no se muestran detalles de conexión.';
            }
        }
        $report['requisitos_tecnicos_ok'] = $report['errores'] === [];
        $report['advertencia'] = 'Este diagnóstico no certifica concurrencia, volumen, historial ni efectos de archivos/correos. No habilita la aplicación definitiva.';
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->info('Diagnóstico de solo lectura; aplicación definitiva sin cambios.');
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        return $report['requisitos_tecnicos_ok'] ? self::SUCCESS : self::FAILURE;
    }
}
