<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dotacion_edicion_bloqueos', function (Blueprint $table) {
            $table->dateTime('desbloqueo_hasta')->nullable();
        });
        Schema::table('dotacion_edicion_bloqueo_historial', function (Blueprint $table) {
            $table->dateTime('desbloqueo_hasta_anterior')->nullable();
            $table->dateTime('desbloqueo_hasta_nuevo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dotacion_edicion_bloqueo_historial', function (Blueprint $table) {
            $table->dropColumn(['desbloqueo_hasta_anterior', 'desbloqueo_hasta_nuevo']);
        });
        Schema::table('dotacion_edicion_bloqueos', function (Blueprint $table) {
            $table->dropColumn('desbloqueo_hasta');
        });
    }
};
