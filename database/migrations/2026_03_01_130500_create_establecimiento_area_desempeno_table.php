<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Nota: si esta migración se ejecutó previamente y falló (por nombres de índices muy largos en MySQL),
        // la tabla puede haber quedado creada sin los índices. En ese caso, sólo agregamos los índices con nombres cortos.
        if (Schema::hasTable('establecimiento_area_desempeno')) {
            $existingKeys = collect(DB::select('SHOW INDEX FROM establecimiento_area_desempeno'))
                ->pluck('Key_name')
                ->unique()
                ->values()
                ->all();

            Schema::table('establecimiento_area_desempeno', function (Blueprint $table) use ($existingKeys) {
                // Nombres cortos para evitar límite de 64 caracteres en MySQL
                if (!in_array('estab_area_uq', $existingKeys, true)) {
                    $table->unique(['establecimiento_id', 'area_desempeno_id'], 'estab_area_uq');
                }

                if (!in_array('estab_bloq_idx', $existingKeys, true)) {
                    $table->index(['establecimiento_id', 'bloqueada'], 'estab_bloq_idx');
                }
            });

            return;
        }

        Schema::create('establecimiento_area_desempeno', function (Blueprint $table) {
            $table->id();

            $table->foreignId('establecimiento_id')
                ->constrained('establecimientos')
                ->cascadeOnDelete();

            $table->foreignId('area_desempeno_id')
                ->constrained('areas_desempeno')
                ->cascadeOnDelete();

            // true = bloqueada por sobredotación
            $table->boolean('bloqueada')->default(false);

            $table->timestamps();

            // Nombres cortos para evitar límite de 64 caracteres en MySQL
            $table->unique(['establecimiento_id', 'area_desempeno_id'], 'estab_area_uq');
            $table->index(['establecimiento_id', 'bloqueada'], 'estab_bloq_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_area_desempeno');
    }
};
