<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agendamientos_recursos')) {
            Schema::create('agendamientos_recursos', function (Blueprint $table) {
                $table->id();
                $table->string('tipo_recurso', 40);
                $table->string('titulo', 180);
                $table->date('fecha');
                $table->time('hora_inicio');
                $table->time('hora_termino');
                $table->foreignId('solicitante_user_id')->nullable();
                $table->string('solicitante_nombre', 180)->nullable();
                $table->string('solicitante_email', 180)->nullable();
                $table->string('unidad', 180)->nullable();
                $table->string('lugar_uso', 220)->nullable();
                $table->unsignedInteger('cantidad_participantes')->nullable();
                $table->text('motivo')->nullable();
                $table->boolean('requiere_proyector')->default(false);
                $table->boolean('requiere_apoyo_tecnico')->default(false);
                $table->string('responsable_retiro', 180)->nullable();
                $table->string('responsable_devolucion', 180)->nullable();
                $table->string('estado', 40)->default('vigente');
                $table->text('observaciones')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
                $table->foreignId('anulado_by')->nullable();
                $table->timestamp('anulado_at')->nullable();
                $table->text('motivo_anulacion')->nullable();
                $table->timestamps();

                $table->index(['tipo_recurso', 'fecha', 'estado'], 'agrec_recurso_fecha_estado_idx');
                $table->index(['fecha', 'hora_inicio', 'hora_termino'], 'agrec_fecha_horas_idx');
            });

            Schema::table('agendamientos_recursos', function (Blueprint $table) {
                $table->foreign('solicitante_user_id', 'agrec_solicitante_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by', 'agrec_created_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'agrec_updated_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('anulado_by', 'agrec_anulado_by_fk')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agendamientos_recursos');
    }
};
