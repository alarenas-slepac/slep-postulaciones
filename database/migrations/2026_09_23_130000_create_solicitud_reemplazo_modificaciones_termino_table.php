<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_reemplazo_modificaciones_termino', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitud_reemplazo_id');
            $table->string('causal', 50);
            $table->string('estado_origen', 40);
            $table->date('fecha_termino_anterior');
            $table->date('fecha_termino_nueva');
            $table->text('motivo');
            $table->string('carta_renuncia_path')->nullable();
            $table->string('resolucion_renuncia_path')->nullable();
            $table->string('orden_trabajo_anterior_path')->nullable();
            $table->timestamp('orden_trabajo_anterior_creada_at')->nullable();
            $table->string('resolucion_docente_docx_anterior_path')->nullable();
            $table->string('resolucion_docente_firmada_anterior_path')->nullable();
            $table->boolean('orden_trabajo_requiere_regeneracion')->default(false);
            $table->timestamp('orden_trabajo_regenerada_at')->nullable();
            $table->boolean('resolucion_docente_requiere_regeneracion')->default(false);
            $table->timestamp('resolucion_docente_regenerada_at')->nullable();
            $table->timestamp('finalizada_at')->nullable();
            $table->unsignedBigInteger('reabierta_por_user_id')->nullable();
            $table->timestamps();

            $table->index(['solicitud_reemplazo_id', 'finalizada_at'], 'srmt_solicitud_finalizada_idx');
            $table->foreign('solicitud_reemplazo_id', 'srmt_solicitud_fk')
                ->references('id')->on('solicitudes_reemplazo')->cascadeOnDelete();
            $table->foreign('reabierta_por_user_id', 'srmt_reabierta_por_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_reemplazo_modificaciones_termino');
    }
};
