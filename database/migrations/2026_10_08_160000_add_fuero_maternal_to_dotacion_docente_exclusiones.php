<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_docente_exclusiones')
            && ! Schema::hasColumn('dotacion_docente_exclusiones', 'posee_fuero_maternal')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                // No se presume fuero maternal en registros históricos de lactancia.
                $table->boolean('posee_fuero_maternal')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dotacion_docente_exclusiones')
            && Schema::hasColumn('dotacion_docente_exclusiones', 'posee_fuero_maternal')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                $table->dropColumn('posee_fuero_maternal');
            });
        }
    }
};
