<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jornadas_votacion', function (Blueprint $table): void {
            $table->boolean('consulta_publica')->default(false);
        });

        Schema::create('votacion_locales_consulta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->cascadeOnDelete();
            $table->unsignedInteger('rbd_origen');
            $table->string('establecimiento');
            $table->unsignedInteger('rbd_local');
            $table->string('lugar_votacion');
            $table->date('dia');
            $table->time('hora_inicio');
            $table->timestamps();
            $table->unique(['jornada_votacion_id', 'rbd_origen', 'rbd_local'], 'votacion_consulta_local_unique');
        });

        Schema::create('votacion_padron_consulta', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->cascadeOnDelete();
            $table->foreignId('local_consulta_id')->constrained('votacion_locales_consulta')->restrictOnDelete();
            $table->string('rut', 9);
            $table->string('nombre');
            $table->unsignedInteger('rbd');
            $table->string('establecimiento');
            $table->string('tipo_contrato', 150);
            $table->string('grupo', 100)->nullable();
            $table->string('fila_hash', 64);
            $table->timestamps();
            $table->index(['jornada_votacion_id', 'rut'], 'votacion_consulta_rut_index');
            $table->unique(['jornada_votacion_id', 'fila_hash'], 'votacion_consulta_fila_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votacion_padron_consulta');
        Schema::dropIfExists('votacion_locales_consulta');
        Schema::table('jornadas_votacion', function (Blueprint $table): void {
            $table->dropColumn('consulta_publica');
        });
    }
};
