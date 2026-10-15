<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_establecimiento_configuraciones')
            && ! Schema::hasColumn('dotacion_establecimiento_configuraciones', 'horas_coordinacion_pie')) {
            Schema::table('dotacion_establecimiento_configuraciones', function (Blueprint $table): void {
                // NULL conserva el cálculo automático, incluido para los datos históricos.
                $table->unsignedSmallInteger('horas_coordinacion_pie')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dotacion_establecimiento_configuraciones', 'horas_coordinacion_pie')) {
            Schema::table('dotacion_establecimiento_configuraciones', function (Blueprint $table): void {
                $table->dropColumn('horas_coordinacion_pie');
            });
        }
    }
};
