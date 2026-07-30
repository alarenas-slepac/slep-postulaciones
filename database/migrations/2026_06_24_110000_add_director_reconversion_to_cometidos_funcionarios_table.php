<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios', 'requiere_autorizacion_director_sin_disponibilidad')) {
                $table->boolean('requiere_autorizacion_director_sin_disponibilidad')->default(false)->after('estado_reembolso');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'estado_autorizacion_director')) {
                $table->string('estado_autorizacion_director', 40)->nullable()->after('requiere_autorizacion_director_sin_disponibilidad');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'monto_viatico_solicitado_director')) {
                $table->unsignedInteger('monto_viatico_solicitado_director')->nullable()->after('estado_autorizacion_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'monto_disponible_director')) {
                $table->unsignedInteger('monto_disponible_director')->nullable()->after('monto_viatico_solicitado_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'diferencia_presupuestaria_director')) {
                $table->unsignedInteger('diferencia_presupuestaria_director')->nullable()->after('monto_disponible_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'fundamento_planificacion_director')) {
                $table->text('fundamento_planificacion_director')->nullable()->after('diferencia_presupuestaria_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'decision_director')) {
                $table->string('decision_director', 80)->nullable()->after('fundamento_planificacion_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'observacion_director')) {
                $table->text('observacion_director')->nullable()->after('decision_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'fecha_solicitud_director')) {
                $table->timestamp('fecha_solicitud_director')->nullable()->after('observacion_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'fecha_decision_director')) {
                $table->timestamp('fecha_decision_director')->nullable()->after('fecha_solicitud_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'director_user_id')) {
                $table->unsignedBigInteger('director_user_id')->nullable()->after('fecha_decision_director');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'viatico_reconvertido_a_reembolso')) {
                $table->boolean('viatico_reconvertido_a_reembolso')->default(false)->after('director_user_id');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'motivo_reconversion_reembolso')) {
                $table->text('motivo_reconversion_reembolso')->nullable()->after('viatico_reconvertido_a_reembolso');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'tenia_derecho_viatico_original')) {
                $table->boolean('tenia_derecho_viatico_original')->default(false)->after('motivo_reconversion_reembolso');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'monto_viatico_original')) {
                $table->unsignedInteger('monto_viatico_original')->nullable()->after('tenia_derecho_viatico_original');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            $columns = [
                'requiere_autorizacion_director_sin_disponibilidad',
                'estado_autorizacion_director',
                'monto_viatico_solicitado_director',
                'monto_disponible_director',
                'diferencia_presupuestaria_director',
                'fundamento_planificacion_director',
                'decision_director',
                'observacion_director',
                'fecha_solicitud_director',
                'fecha_decision_director',
                'director_user_id',
                'viatico_reconvertido_a_reembolso',
                'motivo_reconversion_reembolso',
                'tenia_derecho_viatico_original',
                'monto_viatico_original',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
