<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('votacion_padrones_definitivos')) { return; }
        Schema::create('votacion_padrones_definitivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->restrictOnDelete();
            $table->unsignedInteger('personas');
            $table->json('datos');
            $table->json('resumen');
            $table->string('base_huella', 64);
            $table->string('certificado_path')->nullable();
            $table->unsignedBigInteger('creado_por');
            $table->unsignedBigInteger('publicado_por')->nullable();
            $table->timestamp('publicado_at')->nullable();
            $table->timestamps();
            $table->index(['jornada_votacion_id', 'publicado_at']);
        });
    }

    public function down(): void
    {
        // Las versiones son antecedentes del proceso: no eliminar sus datos.
    }
};
