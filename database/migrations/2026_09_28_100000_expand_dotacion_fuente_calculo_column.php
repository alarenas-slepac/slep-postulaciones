<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_docente_asignaciones')
            || ! Schema::hasColumn('dotacion_docente_asignaciones', 'fuente_calculo')) {
            return;
        }

        Schema::table('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->text('fuente_calculo')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Volver a VARCHAR(255) truncaría explicaciones ya guardadas.
    }
};
