<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_docente_exclusiones')
            && ! Schema::hasColumn('dotacion_docente_exclusiones', 'horas_traspaso_bir')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                // Null conserva el traspaso completo de los registros anteriores.
                $table->decimal('horas_traspaso_bir', 8, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dotacion_docente_exclusiones')
            && Schema::hasColumn('dotacion_docente_exclusiones', 'horas_traspaso_bir')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                $table->dropColumn('horas_traspaso_bir');
            });
        }
    }
};
