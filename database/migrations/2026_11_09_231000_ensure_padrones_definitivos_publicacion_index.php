<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'votacion_padrones_definitivos';

        // Un reintento con la versión anterior podía registrar la migración como
        // aplicada al encontrar la tabla, aunque aún faltara su índice compuesto.
        if (Schema::hasTable($tableName)
            && ! Schema::hasIndex($tableName, ['jornada_votacion_id', 'publicado_at'])) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['jornada_votacion_id', 'publicado_at'], 'vpd_jornada_publicado_idx');
            });
        }
    }

    public function down(): void
    {
        // Conservar el índice y las versiones históricas del padrón.
    }
};
