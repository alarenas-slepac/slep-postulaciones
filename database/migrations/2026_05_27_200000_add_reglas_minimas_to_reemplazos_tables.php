<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('establecimientos', 'unidocencia')) {
                $table->boolean('unidocencia')->default(false)->after('sala_cuna');
            }
        });

        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'es_continuidad')) {
                $table->boolean('es_continuidad')->default(false)->after('continuidad');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'solicitud_anterior_id')) {
                $table->unsignedBigInteger('solicitud_anterior_id')->nullable()->after('es_continuidad');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'continuidad_validada_at')) {
                $table->timestamp('continuidad_validada_at')->nullable()->after('solicitud_anterior_id');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'regla_minima_aplicada')) {
                $table->string('regla_minima_aplicada', 80)->nullable()->after('continuidad_validada_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'regla_minima_excepcion')) {
                $table->string('regla_minima_excepcion', 80)->nullable()->after('regla_minima_aplicada');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'rut_titular_normalizado')) {
                $table->string('rut_titular_normalizado', 20)->nullable()->after('regla_minima_excepcion');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'rut_reemplazo_normalizado')) {
                $table->string('rut_reemplazo_normalizado', 20)->nullable()->after('rut_titular_normalizado');
            }
        });

        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            try {
                $table->index(['rut_titular_normalizado', 'rut_reemplazo_normalizado'], 'sr_ruts_norm_idx');
            } catch (Throwable $e) {
                // Índice ya existente o no aplicable en instalaciones parcialmente migradas.
            }
            try {
                $table->index(['fecha_inicio', 'fecha_termino'], 'sr_fechas_reemplazo_idx');
            } catch (Throwable $e) {
                // Índice ya existente o no aplicable en instalaciones parcialmente migradas.
            }
            try {
                $table->foreign('solicitud_anterior_id', 'sr_solicitud_anterior_fk')
                    ->references('id')
                    ->on('solicitudes_reemplazo')
                    ->nullOnDelete();
            } catch (Throwable $e) {
                // Constraint ya existente o no aplicable en instalaciones parcialmente migradas.
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            try {
                $table->dropForeign('sr_solicitud_anterior_fk');
            } catch (Throwable $e) {
                // Ignorar rollback parcial.
            }
            try {
                $table->dropIndex('sr_ruts_norm_idx');
            } catch (Throwable $e) {
                // Ignorar rollback parcial.
            }
            try {
                $table->dropIndex('sr_fechas_reemplazo_idx');
            } catch (Throwable $e) {
                // Ignorar rollback parcial.
            }
        });

        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach ([
                'rut_reemplazo_normalizado',
                'rut_titular_normalizado',
                'regla_minima_excepcion',
                'regla_minima_aplicada',
                'continuidad_validada_at',
                'solicitud_anterior_id',
                'es_continuidad',
            ] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('establecimientos', function (Blueprint $table) {
            if (Schema::hasColumn('establecimientos', 'unidocencia')) {
                $table->dropColumn('unidocencia');
            }
        });
    }
};
