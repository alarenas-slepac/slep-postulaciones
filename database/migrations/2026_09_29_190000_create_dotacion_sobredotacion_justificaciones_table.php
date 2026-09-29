<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_sobredotacion_justificaciones')) {
            return;
        }

        Schema::create('dotacion_sobredotacion_justificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos');
            $table->unsignedSmallInteger('anio');
            $table->string('docente_rut_normalizado', 32);
            $table->string('bloque', 24);
            $table->string('tipo_horas', 16);
            $table->decimal('horas_detectadas', 8, 2);
            $table->text('justificacion');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['establecimiento_id', 'anio', 'docente_rut_normalizado', 'bloque', 'tipo_horas'],
                'dsj_est_anio_docente_bloque_tipo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_sobredotacion_justificaciones');
    }
};
