<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cometido_funcionario_rendiciones')) {
            Schema::create('cometido_funcionario_rendiciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cometido_funcionario_id');
                $table->unsignedInteger('monto_rendido')->default(0);
                $table->unsignedInteger('monto_autorizado_daf')->nullable();
                $table->string('estado', 80)->default('rendicion_enviada');
                $table->text('observacion_establecimiento')->nullable();
                $table->text('observacion_daf')->nullable();
                $table->json('documentos_respaldo')->nullable();
                $table->string('documento_daf_path')->nullable();
                $table->timestamp('fecha_envio_rendicion')->nullable();
                $table->timestamp('fecha_revision_daf')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('cometido_funcionario_id', 'cfr_cometido_idx');
                $table->index('estado', 'cfr_estado_idx');
                $table->foreign('cometido_funcionario_id', 'cfr_cometido_fk')
                    ->references('id')->on('cometidos_funcionarios')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('cometido_funcionario_resoluciones_reembolso')) {
            Schema::create('cometido_funcionario_resoluciones_reembolso', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cometido_funcionario_id');
                $table->unsignedBigInteger('rendicion_id')->nullable();
                $table->string('numero_resolucion', 100)->nullable();
                $table->date('fecha_resolucion')->nullable();
                $table->unsignedInteger('monto_resolucion')->nullable();
                $table->string('documento_resolucion_path')->nullable();
                $table->string('estado', 80)->default('en_juridica_resolucion_reembolso');
                $table->text('observacion_juridica')->nullable();
                $table->timestamp('fecha_envio_juridica')->nullable();
                $table->timestamp('fecha_emision_resolucion')->nullable();
                $table->unsignedInteger('monto_pagado_reembolso')->nullable();
                $table->date('fecha_pago_reembolso')->nullable();
                $table->string('documento_pago_path')->nullable();
                $table->text('observacion_pago')->nullable();
                $table->unsignedBigInteger('usuario_pago_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('cometido_funcionario_id', 'cfrr_cometido_idx');
                $table->index('rendicion_id', 'cfrr_rendicion_idx');
                $table->index('estado', 'cfrr_estado_idx');
                $table->foreign('cometido_funcionario_id', 'cfrr_cometido_fk')
                    ->references('id')->on('cometidos_funcionarios')->cascadeOnDelete();
                $table->foreign('rendicion_id', 'cfrr_rendicion_fk')
                    ->references('id')->on('cometido_funcionario_rendiciones')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cometido_funcionario_resoluciones_reembolso');
        Schema::dropIfExists('cometido_funcionario_rendiciones');
    }
};
