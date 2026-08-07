<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->string('segunda_subdireccion_responsable', 255)->nullable()
                ->comment('Segunda subdirección responsable (opcional)');
            $table->string('segunda_responsable_subdireccion', 190)->nullable()
                ->comment('Segundo responsable dentro de la subdirección (opcional)');
        });
    }

    public function down(): void
    {
        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['segunda_subdireccion_responsable', 'segunda_responsable_subdireccion']);
        });
    }
};