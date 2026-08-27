<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licencias_medicas_importacion_errores')) {
            return;
        }

        Schema::create('licencias_medicas_importacion_errores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('licencias_medicas_importaciones')->cascadeOnDelete();
            $table->string('hoja', 120)->nullable();
            $table->unsignedInteger('fila')->nullable();
            $table->string('codigo_error', 80)->index();
            $table->text('motivo');
            $table->string('folio_recibido', 80)->nullable()->index();
            $table->string('rut_recibido', 30)->nullable()->index();
            $table->json('valores_originales')->nullable();
            $table->json('valores_corregidos')->nullable();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->unsignedSmallInteger('intentos_reproceso')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->string('resultado_reproceso', 30)->nullable();
            $table->foreignId('licencia_medica_id')->nullable()->constrained('licencias_medicas')->nullOnDelete();
            $table->foreignId('corregido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corregido_at')->nullable();
            $table->foreignId('reprocesado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reprocesado_at')->nullable();
            $table->timestamps();

            $table->unique(['importacion_id', 'hoja', 'fila'], 'lic_med_import_error_fila_unique');
            $table->index(['importacion_id', 'estado'], 'lic_med_import_error_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias_medicas_importacion_errores');
    }
};
