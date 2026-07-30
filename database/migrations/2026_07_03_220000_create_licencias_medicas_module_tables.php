<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('licencias_medicas')) {
            Schema::create('licencias_medicas', function (Blueprint $table) {
                $table->id();
                $table->string('tipo_ingreso_licencia', 1);
                $table->string('cuerpo_licencia', 20);
                $table->string('dv_licencia', 1);
                $table->string('folio_licencia', 40)->unique();
                $table->string('rut_funcionario', 20)->nullable()->index();
                $table->string('dv_funcionario', 1)->nullable();
                $table->string('rut_normalizado', 20)->nullable()->index();
                $table->string('rut_formateado', 20)->nullable();
                $table->string('nombre_funcionario')->nullable();
                $table->string('apellido_paterno')->nullable();
                $table->string('apellido_materno')->nullable();
                $table->string('nombres')->nullable();
                $table->string('sexo', 30)->nullable();
                $table->unsignedSmallInteger('edad')->nullable();
                $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
                $table->string('establecimiento_nombre')->nullable();
                $table->string('comuna', 120)->nullable()->index();
                $table->string('calidad_juridica', 120)->nullable();
                $table->string('estamento', 120)->nullable();
                $table->date('fecha_emision')->nullable();
                $table->date('fecha_recepcion')->nullable();
                $table->date('fecha_inicio')->nullable()->index();
                $table->date('fecha_termino')->nullable();
                $table->unsignedSmallInteger('dias_solicitados')->nullable();
                $table->unsignedSmallInteger('dias_laborales')->nullable();
                $table->string('tipo_licencia', 2)->nullable()->index();
                $table->string('tipo_licencia_glosa')->nullable();
                $table->string('tipo_reposo', 80)->nullable();
                $table->string('lugar_reposo', 120)->nullable();
                $table->text('direccion_reposo')->nullable();
                $table->string('telefono', 60)->nullable();
                $table->string('correo_trabajador')->nullable();
                $table->string('rut_empleador', 20)->nullable();
                $table->string('nombre_empleador')->nullable();
                $table->string('estado_actual', 120)->default('Ingresada')->index();
                $table->string('estado_compin', 120)->nullable()->index();
                $table->unsignedSmallInteger('dias_autorizados')->nullable();
                $table->string('derecho_subsidio', 80)->nullable();
                $table->decimal('monto_subsidio', 14, 2)->nullable();
                $table->decimal('monto_recuperable', 14, 2)->nullable();
                $table->decimal('monto_cotizacion', 14, 2)->nullable();
                $table->string('estado_notificacion', 80)->nullable()->index();
                $table->string('estado_alerta', 80)->nullable()->index();
                $table->string('origen_ingreso', 40)->index();
                $table->string('tipo_documento_ingreso', 40)->index();
                $table->string('archivo_licencia_path')->nullable();
                $table->string('archivo_licencia_nombre')->nullable();
                $table->string('archivo_licencia_mime', 120)->nullable();
                $table->unsignedBigInteger('archivo_licencia_size')->nullable();
                $table->string('extraccion_pdf_estado', 80)->nullable();
                $table->json('extraccion_pdf_json')->nullable();
                $table->string('extraccion_pdf_confianza', 40)->nullable();
                $table->string('fuente_asociacion_funcionario', 80)->nullable()->index();
                $table->string('periodo_reemplazos_usado', 7)->nullable();
                $table->text('observaciones')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tipo_ingreso_licencia', 'cuerpo_licencia', 'dv_licencia'], 'lic_medicas_folio_parts_unique');
                $table->index(['tipo_ingreso_licencia', 'cuerpo_licencia'], 'lic_medicas_compin_lookup_idx');
                $table->index(['rut_normalizado', 'fecha_inicio'], 'lic_medicas_rut_fecha_idx');
            });
        }

        if (!Schema::hasTable('licencias_medicas_historial')) {
            Schema::create('licencias_medicas_historial', function (Blueprint $table) {
                $table->id();
                $table->foreignId('licencia_medica_id')->constrained('licencias_medicas')->cascadeOnDelete();
                $table->string('accion', 80)->index();
                $table->text('descripcion')->nullable();
                $table->json('datos_anteriores')->nullable();
                $table->json('datos_nuevos')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias_medicas_historial');
        Schema::dropIfExists('licencias_medicas');
    }
};
