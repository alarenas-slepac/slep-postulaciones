<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maternidad_casos', function (Blueprint $table) {
            $table->date('fecha_rex_dominga')->nullable();
            $table->date('fecha_rex_lactancia')->nullable();
            $table->decimal('horas_semanales_lactancia', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        throw new \LogicException('La reversión de resoluciones de maternidad requiere un procedimiento autorizado.');
    }
};
