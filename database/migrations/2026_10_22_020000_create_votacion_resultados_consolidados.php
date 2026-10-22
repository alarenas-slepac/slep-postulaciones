<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votacion_resultados_consolidados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->char('huella', 64);
            // El cast array serializa JSON; TEXT conserva el orden original de las opciones.
            $table->longText('dias');
            $table->longText('resumen');
            $table->timestamp('publicado_at')->nullable();
            $table->timestamp('retirado_at')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('retirado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['jornada_votacion_id', 'version'], 'consolidado_jornada_version_unique');
            $table->index(['jornada_votacion_id', 'publicado_at', 'retirado_at'], 'consolidado_publicacion_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votacion_resultados_consolidados');
    }
};
