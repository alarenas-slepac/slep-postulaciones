<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dotacion_docente_exclusiones', 'conservar_horas_necesarias')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                $table->boolean('conservar_horas_necesarias')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dotacion_docente_exclusiones', 'conservar_horas_necesarias')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                $table->dropColumn('conservar_horas_necesarias');
            });
        }
    }
};
