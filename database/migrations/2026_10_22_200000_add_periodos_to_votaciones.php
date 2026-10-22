<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jornadas_votacion', function (Blueprint $table) {
            $table->date('fecha_fin')->nullable();
        });
        Schema::table('grupos_votacion', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->foreignId('grupo_origen_id')->nullable()->constrained('grupos_votacion')->nullOnDelete();
            $table->unique(['jornada_votacion_id', 'grupo_origen_id'], 'grupo_jornada_origen_unique');
        });
    }

    public function down(): void
    {
        Schema::table('grupos_votacion', function (Blueprint $table) {
            $table->dropUnique('grupo_jornada_origen_unique');
            $table->dropConstrainedForeignId('grupo_origen_id');
            $table->dropColumn(['fecha_inicio', 'fecha_fin']);
        });
        Schema::table('jornadas_votacion', fn (Blueprint $table) => $table->dropColumn('fecha_fin'));
    }
};
