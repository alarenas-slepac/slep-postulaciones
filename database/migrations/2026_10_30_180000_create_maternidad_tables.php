<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maternidad_casos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('referencia_caso', 100);
            $table->string('rut', 16)->index();
            $table->string('nombres', 150);
            $table->string('apellido_paterno', 100);
            $table->string('apellido_materno', 100)->nullable();
            $table->string('estamento', 16)->index();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->string('establecimiento_nombre');
            $table->string('establecimiento_rbd', 16)->nullable();
            $table->string('comuna', 120)->nullable();
            $table->string('calidad_juridica', 120);
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->date('fecha_registro');
            $table->date('fecha_certificado_embarazo')->nullable();
            $table->date('fpc')->nullable();
            $table->date('fpp')->nullable();
            $table->date('fnh')->nullable();
            $table->boolean('ley_dominga')->default(false);
            $table->date('fecha_ley_dominga')->nullable();
            $table->string('regla_fuero_dominga', 32)->nullable();
            $table->string('causal_ampliacion_dominga', 32)->nullable();
            $table->text('motivo_ampliacion_dominga')->nullable();
            $table->boolean('lactancia')->default(false);
            $table->date('fecha_inicio_lactancia')->nullable();
            $table->boolean('postnatal_parental')->default(false);
            $table->date('fecha_inicio_postnatal')->nullable();
            $table->date('fecha_termino_postnatal')->nullable();
            $table->string('modalidad_postnatal', 16)->nullable();
            $table->date('fecha_rex_postnatal')->nullable();
            $table->date('fuero_estimado')->nullable();
            $table->date('fuero_nacimiento')->nullable();
            $table->date('fuero_dominga')->nullable();
            $table->date('fecha_termino_fuero')->nullable()->index();
            $table->string('base_fuero', 32)->nullable();
            $table->date('fecha_termino_lactancia')->nullable();
            $table->json('documentos_pendientes')->nullable();
            $table->string('origen_registro', 16)->default('individual');
            $table->string('fuente_personal', 32);
            $table->unsignedBigInteger('fuente_personal_id');
            $table->json('fuente_snapshot')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['rut', 'referencia_caso'], 'mat_rut_referencia_unique');
        });
        Schema::create('maternidad_hijos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternidad_caso_id')->constrained('maternidad_casos')->restrictOnDelete();
            $table->string('nombre_completo', 255)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('rut', 16)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
        Schema::create('maternidad_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternidad_caso_id')->constrained('maternidad_casos')->restrictOnDelete();
            $table->string('tipo', 32);
            $table->string('path');
            $table->string('nombre_original');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['maternidad_caso_id', 'tipo', 'id'], 'mat_doc_tipo_idx');
        });
        Schema::create('maternidad_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternidad_caso_id')->constrained('maternidad_casos')->restrictOnDelete();
            $table->string('evento', 40);
            $table->text('observacion')->nullable();
            $table->json('cambios')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('maternidad_importaciones', function (Blueprint $table) {
            $table->id(); $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->longText('payload')->nullable();
            $table->unsignedSmallInteger('cantidad');
            $table->timestamp('expira_at');
            $table->timestamp('procesada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new \LogicException('Los expedientes de maternidad requieren un procedimiento de reversión autorizado; no se eliminan automáticamente.');
    }
};
