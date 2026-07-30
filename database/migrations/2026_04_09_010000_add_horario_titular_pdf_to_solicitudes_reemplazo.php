<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'horario_titular_pdf_path')) {
                $table->string('horario_titular_pdf_path')->nullable()->after('respaldo_pdf_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_reemplazo', 'horario_titular_pdf_path')) {
                $table->dropColumn('horario_titular_pdf_path');
            }
        });
    }
};
