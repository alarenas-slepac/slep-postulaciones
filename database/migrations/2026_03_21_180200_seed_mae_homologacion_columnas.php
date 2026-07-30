<?php

use App\Support\MaeColumnNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('mae_homologacion_columnas')) {
            return;
        }

        $path = database_path('data/mae_homologacion_completa.csv');
        if (!file_exists($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            return;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return;
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            if (!$row) {
                continue;
            }

            $seccion = (string) ($row['zona'] ?? '');
            $accion = (string) ($row['accion_guardado'] ?? 'guardar');
            $tipo = $accion === 'ignorar' ? 'ignorado' : 'descuento';
            $esPatronal = strtolower((string) ($row['es_aporte_patronal'] ?? 'no')) === 'sí' || MaeColumnNormalizer::isAportePatronal((string) ($row['columna_original'] ?? ''));
            if ($esPatronal && $accion !== 'ignorar') {
                $tipo = 'aporte_patronal';
            }

            $prioridad = match ($seccion) {
                'descuentos' => 300,
                'resumen_base' => 200,
                'datos_trabajador' => 150,
                default => 100,
            };

            $rows[] = [
                'columna_origen' => (string) ($row['columna_original'] ?? ''),
                'columna_normalizada' => (string) ($row['normalizado'] ?? ''),
                'campo_canonico' => (string) ($row['campo_canonico'] ?? ''),
                'grupo' => (string) ($row['grupo_macro'] ?? ''),
                'subgrupo' => (string) ($row['subgrupo'] ?? ''),
                'seccion_archivo' => $seccion,
                'tipo_movimiento' => $tipo,
                'es_aporte_patronal' => $esPatronal,
                'es_guardable' => $accion !== 'ignorar',
                'guardar_en_resumen' => $seccion === 'resumen_base',
                'guardar_en_detalle' => $seccion === 'descuentos' && $accion !== 'ignorar',
                'prioridad' => $prioridad,
                'activo' => true,
                'observaciones' => (string) ($row['observaciones'] ?? ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($handle);

        if (!empty($rows)) {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('mae_homologacion_columnas')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mae_homologacion_columnas')) {
            DB::table('mae_homologacion_columnas')->delete();
        }
    }
};
