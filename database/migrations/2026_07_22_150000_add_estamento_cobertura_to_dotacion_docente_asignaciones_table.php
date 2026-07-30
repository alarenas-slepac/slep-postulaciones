<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_docente_asignaciones')
            || Schema::hasColumn('dotacion_docente_asignaciones', 'estamento_cobertura')) {
            return;
        }

        Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) {
            $table->string('estamento_cobertura', 20)
                ->default('docente')
                ->after('declaracion_sostenedor_id');
            $table->index('estamento_cobertura', 'dda_estamento_cov_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dotacion_docente_asignaciones')
            || ! Schema::hasColumn('dotacion_docente_asignaciones', 'estamento_cobertura')) {
            return;
        }

        Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) {
            $table->dropIndex('dda_estamento_cov_idx');
            $table->dropColumn('estamento_cobertura');
        });
    }
};
