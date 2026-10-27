<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dotacion_contrata_habilitaciones', 'bir_situacion_id')) {
            Schema::table('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
                $table->unsignedBigInteger('bir_situacion_id')->nullable();
            });
        }
        if (! Schema::hasColumn('dotacion_contrata_habilitaciones', 'bir_docente_rut_normalizado')) {
            Schema::table('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
                $table->string('bir_docente_rut_normalizado', 32)->nullable();
            });
        }
        if (! Schema::hasIndex('dotacion_contrata_habilitaciones', 'dot_cupo_bir_uk')) {
            Schema::table('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
                $table->unique(['bir_situacion_id', 'anio', 'bloque'], 'dot_cupo_bir_uk');
            });
        }
        if (! Schema::hasIndex('dotacion_contrata_habilitaciones', 'dot_cupo_bir_persona_uk')) {
            Schema::table('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
                $table->unique(['establecimiento_id', 'anio', 'bir_docente_rut_normalizado', 'bloque'], 'dot_cupo_bir_persona_uk');
            });
        }
    }

    public function down(): void
    {
        // Conserva la trazabilidad BIR y sus cupos al revertir código.
    }
};
