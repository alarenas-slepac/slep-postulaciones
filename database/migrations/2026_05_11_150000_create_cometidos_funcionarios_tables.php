<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cometidos_funcionarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->unsignedInteger('rbd')->nullable()->index();
            $table->string('estado', 60)->default('borrador')->index();
            $table->date('fecha_solicitud');

            $table->foreignId('reemplazo_personal_id')->constrained('reemplazos_personal')->cascadeOnDelete();
            $table->string('funcionario_rut', 20)->index();
            $table->string('funcionario_nombre');
            $table->string('calidad_juridica')->nullable();
            $table->string('estamento')->nullable();
            $table->string('cargo_funcion')->nullable();

            $table->string('region_destino', 20);
            $table->foreignId('comuna_destino_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->string('comuna_destino_nombre')->nullable();
            $table->string('institucion_destino');
            $table->string('destino');
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->time('hora_salida');
            $table->time('hora_regreso');

            $table->json('medios_transporte')->nullable();
            $table->string('medio_transporte_otro')->nullable();
            $table->string('motivo');
            $table->string('motivo_otro')->nullable();
            $table->text('descripcion_actividades');

            $table->boolean('existe_citacion_invitacion')->default(false);
            $table->string('archivo_citacion_invitacion_path')->nullable();
            $table->string('archivo_citacion_invitacion_nombre')->nullable();
            $table->boolean('solicita_viatico')->default(false);
            $table->boolean('solicita_reembolso')->default(false);

            $table->foreignId('uatp_revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uatp_revisado_at')->nullable();
            $table->string('uatp_decision', 30)->nullable();
            $table->text('uatp_observacion')->nullable();

            $table->foreignId('cdp_revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cdp_revisado_at')->nullable();
            $table->boolean('cdp_aprobado')->nullable();
            $table->text('cdp_observacion')->nullable();

            $table->foreignId('gdp_revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gdp_revisado_at')->nullable();
            $table->string('numero_resolucion_cometido')->nullable();
            $table->date('fecha_resolucion_cometido')->nullable();
            $table->string('archivo_resolucion_cometido_path')->nullable();

            $table->foreignId('finanzas_revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finanzas_revisado_at')->nullable();
            $table->date('fecha_pago_viatico')->nullable();
            $table->text('finanzas_observacion')->nullable();
            $table->timestamps();

            $table->index(['establecimiento_id', 'estado']);
            $table->index(['fecha_desde', 'fecha_hasta']);
        });

        Schema::create('cometido_funcionario_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cometido_funcionario_id')->constrained('cometidos_funcionarios')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_anterior', 60)->nullable();
            $table->string('estado_nuevo', 60)->nullable();
            $table->string('accion');
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['cometido_funcionario_id', 'created_at'], 'cfh_cometido_created_idx');
        });

        Schema::create('cometido_funcionario_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cometido_funcionario_id')->constrained('cometidos_funcionarios')->cascadeOnDelete();
            $table->string('tipo', 80);
            $table->string('nombre_original');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cometido_funcionario_id', 'tipo'], 'cfd_cometido_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cometido_funcionario_documentos');
        Schema::dropIfExists('cometido_funcionario_historial');
        Schema::dropIfExists('cometidos_funcionarios');
    }
};
