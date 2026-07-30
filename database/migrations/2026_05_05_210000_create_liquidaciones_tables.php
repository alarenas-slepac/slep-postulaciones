<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('liquidacion_cargas')) {
            Schema::create('liquidacion_cargas', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('mes');
                $table->unsignedSmallInteger('anio');
                $table->string('dominio', 100);
                $table->string('archivo_original_path');
                $table->string('archivo_original_nombre')->nullable();
                $table->string('estado', 40)->default('pendiente')->index();
                $table->unsignedInteger('total_paginas')->default(0);
                $table->unsignedInteger('total_con_rut')->default(0);
                $table->unsignedInteger('total_reemplazos')->default(0);
                $table->unsignedInteger('total_publicadas')->default(0);
                $table->unsignedInteger('total_errores')->default(0);
                $table->json('errores')->nullable();
                $table->foreignId('subida_por_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('procesada_at')->nullable();
                $table->timestamps();

                $table->index(['anio', 'mes', 'dominio']);
            });
        }

        if (!Schema::hasTable('liquidaciones_funcionarios')) {
            Schema::create('liquidaciones_funcionarios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('liquidacion_carga_id')->constrained('liquidacion_cargas')->cascadeOnDelete();
                $table->string('rut_original', 20)->nullable();
                $table->string('rut_normalizado', 20)->index();
                $table->string('nombre')->nullable();
                $table->unsignedTinyInteger('mes');
                $table->unsignedSmallInteger('anio');
                $table->string('dominio', 100);
                $table->unsignedInteger('pagina_origen');
                $table->string('archivo_pdf_path')->nullable();
                $table->boolean('es_reemplazo')->default(false)->index();
                $table->string('tipo_contrato_detectado')->nullable();
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_termino')->nullable();
                $table->text('texto_detectado_resumen')->nullable();
                $table->timestamps();

                $table->unique(['rut_normalizado', 'anio', 'mes', 'dominio', 'pagina_origen'], 'liq_func_rut_periodo_dominio_pagina_unique');
                $table->index(['anio', 'mes', 'dominio']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones_funcionarios');
        Schema::dropIfExists('liquidacion_cargas');
    }
};
