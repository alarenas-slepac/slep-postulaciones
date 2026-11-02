<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo_modificaciones_termino', function (Blueprint $table) {
            $table->string('respaldo_licencia_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo_modificaciones_termino', function (Blueprint $table) {
            $table->dropColumn('respaldo_licencia_path');
        });
    }
};
