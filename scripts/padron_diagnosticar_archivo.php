<?php

// Uso: php scripts/padron_diagnosticar_archivo.php "ruta/al/padron.xlsx"
// No arranca Laravel, no lee .env, no abre conexiones ni guarda filas/reportes.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Services\Padron\PadronArchivoDiagnostico;
use App\Services\Padron\PadronExcelReader;

if (PHP_SAPI !== 'cli' || $argc !== 2 || ! is_file($argv[1]) || strtolower(pathinfo($argv[1], PATHINFO_EXTENSION)) !== 'xlsx') {
    fwrite(STDERR, "Uso: php scripts/padron_diagnosticar_archivo.php archivo.xlsx\n");
    exit(1);
}

// Los errores de librerías pueden contener valores personales o rutas: no volcarlos a logs/terminal.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
set_error_handler(static function (int $severity): never {
    throw new RuntimeException('Error de lectura del archivo.');
});
$started = microtime(true);
try {
    $sourceHash = hash_file('sha256', $argv[1]);
    $rows = (new PadronExcelReader)->read($argv[1]);
    if (! hash_equals($sourceHash, hash_file('sha256', $argv[1]))) {
        fwrite(STDERR, "El archivo cambió durante la lectura. Guarde los cambios y vuelva a analizar; no se emite un resultado parcial.\n");
        exit(1);
    }
    $report = (new PadronArchivoDiagnostico)->analizar($rows);
    unset($rows);
    $report['segundos'] = round(microtime(true) - $started, 2);
    $report['memoria_maxima_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudo analizar el archivo. Revise formato, encabezados obligatorios y límites del lector. No se guardaron datos.\n");
    exit(1);
} finally {
    restore_error_handler();
}
