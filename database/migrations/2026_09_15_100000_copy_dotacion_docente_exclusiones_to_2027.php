<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ANIO_ORIGEN = 2026;
    private const ANIO_DESTINO = 2027;

    public function up(): void
    {
        if (! Schema::hasTable('dotacion_docente_exclusiones')) {
            return;
        }

        DB::table('dotacion_docente_exclusiones')
            ->where('anio', self::ANIO_ORIGEN)
            ->orderBy('id')
            ->chunkById(200, function ($situaciones): void {
                foreach ($situaciones as $situacion) {
                    $yaExiste = DB::table('dotacion_docente_exclusiones')
                        ->where('establecimiento_id', $situacion->establecimiento_id)
                        ->where('anio', self::ANIO_DESTINO)
                        ->where('docente_rut_normalizado', $situacion->docente_rut_normalizado)
                        ->exists();

                    if ($yaExiste) {
                        continue;
                    }

                    DB::table('dotacion_docente_exclusiones')->insert([
                        'establecimiento_id' => $situacion->establecimiento_id,
                        'anio' => self::ANIO_DESTINO,
                        'docente_rut' => $situacion->docente_rut,
                        'docente_rut_normalizado' => $situacion->docente_rut_normalizado,
                        'docente_nombre' => $situacion->docente_nombre,
                        'motivo' => $situacion->motivo,
                        'horas' => $situacion->horas,
                        'created_by' => $situacion->created_by,
                        'updated_by' => $situacion->updated_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // No se eliminan situaciones de 2027: después de copiarlas no es posible
        // distinguir con seguridad las que fueron actualizadas por usuarios.
    }
};
