<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padron_bajas_asignaciones', function (Blueprint $t): void {
            $t->string('tipo', 24)->default('ausencia');
            $t->unsignedBigInteger('establecimiento_origen_id')->nullable();
            $t->unsignedBigInteger('establecimiento_destino_id')->nullable();
            $t->index(['padron_revision_id', 'tipo', 'rut'], 'padron_bajas_tipo_rut_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión de decisiones de traslado requiere un procedimiento específico autorizado.');
    }
};
