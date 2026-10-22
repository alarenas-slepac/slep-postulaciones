<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votacion_resultados_diarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->restrictOnDelete();
            $table->date('fecha');
            $table->json('conteos');
            $table->text('observacion_interna')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('publicado_at')->nullable()->index();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['jornada_votacion_id', 'fecha'], 'resultado_jornada_fecha_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votacion_resultados_diarios');
    }
};
