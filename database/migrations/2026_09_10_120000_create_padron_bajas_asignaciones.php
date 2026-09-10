<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padron_bajas_asignaciones', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('padron_revision_id')->constrained('padron_revisiones');
            $t->string('rut', 32);
            $t->boolean('confirmada');
            $t->string('alcance_hash', 64);
            $t->json('alcance');
            $t->text('justificacion');
            $t->unsignedBigInteger('usuario_id');
            $t->timestamp('created_at');
            $t->index(['padron_revision_id', 'rut']);
        });
        Schema::create('padron_asignacion_cambios', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('padron_revision_id')->constrained('padron_revisiones');
            $t->foreignId('baja_asignaciones_id')->constrained('padron_bajas_asignaciones');
            $t->unsignedBigInteger('asignacion_id');
            $t->json('antes');
            $t->json('despues');
            $t->unsignedBigInteger('usuario_id');
            $t->timestamp('created_at');
            $t->unique(['padron_revision_id', 'asignacion_id'], 'padron_asig_revision_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La reversión de decisiones y auditoría de bajas requiere un procedimiento específico autorizado.');
    }
};
