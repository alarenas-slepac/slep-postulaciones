<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'devuelta_desde')) {
                $table->string('devuelta_desde', 30)->nullable()->after('estado');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'retornar_a_etapa')) {
                $table->string('retornar_a_etapa', 30)->nullable()->after('devuelta_desde');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'ultima_observacion_rechazo')) {
                $table->text('ultima_observacion_rechazo')->nullable()->after('retornar_a_etapa');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'fecha_ultima_devolucion')) {
                $table->timestamp('fecha_ultima_devolucion')->nullable()->after('ultima_observacion_rechazo');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'usuario_ultima_devolucion_id')) {
                $table->unsignedBigInteger('usuario_ultima_devolucion_id')->nullable()->after('fecha_ultima_devolucion');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'corregida_establecimiento_at')) {
                $table->timestamp('corregida_establecimiento_at')->nullable()->after('usuario_ultima_devolucion_id');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'corregida_establecimiento_user_id')) {
                $table->unsignedBigInteger('corregida_establecimiento_user_id')->nullable()->after('corregida_establecimiento_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'correccion_establecimiento_observacion')) {
                $table->text('correccion_establecimiento_observacion')->nullable()->after('corregida_establecimiento_user_id');
            }
        });

        if (!Schema::hasTable('solicitudes_reemplazo_observaciones')) {
            Schema::create('solicitudes_reemplazo_observaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('solicitud_reemplazo_id');
                $table->string('etapa', 50);
                $table->string('accion', 50);
                $table->string('estado_origen', 80)->nullable();
                $table->string('estado_destino', 80)->nullable();
                $table->text('motivo')->nullable();
                $table->text('observacion')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->foreign('solicitud_reemplazo_id', 'sro_solicitud_fk')
                    ->references('id')
                    ->on('solicitudes_reemplazo')
                    ->cascadeOnDelete();

                $table->foreign('user_id', 'sro_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(['solicitud_reemplazo_id', 'created_at'], 'sro_solicitud_fecha_idx');
                $table->index(['etapa', 'accion'], 'sro_etapa_accion_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_reemplazo_observaciones');

        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach ([
                'correccion_establecimiento_observacion',
                'corregida_establecimiento_user_id',
                'corregida_establecimiento_at',
                'usuario_ultima_devolucion_id',
                'fecha_ultima_devolucion',
                'ultima_observacion_rechazo',
                'retornar_a_etapa',
                'devuelta_desde',
            ] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
