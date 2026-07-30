<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {

            if (!Schema::hasColumn('solicitudes_reemplazo', 'area_desempeno_id')) {
                $table->foreignId('area_desempeno_id')
                    ->nullable()
                    ->after('postulant_profile_id')
                    ->constrained('areas_desempeno')
                    ->nullOnDelete();
                $table->index('area_desempeno_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'motivo_rechazo')) {
                $table->text('motivo_rechazo')->nullable()->after('estado');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'uatp_decision_user_id')) {
                $table->foreignId('uatp_decision_user_id')
                    ->nullable()
                    ->after('motivo_rechazo')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('uatp_decision_user_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'uatp_decision_at')) {
                $table->timestamp('uatp_decision_at')->nullable()->after('uatp_decision_user_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'derivada_a_user_id')) {
                $table->foreignId('derivada_a_user_id')
                    ->nullable()
                    ->after('uatp_decision_at')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('derivada_a_user_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'derivada_por_user_id')) {
                $table->foreignId('derivada_por_user_id')
                    ->nullable()
                    ->after('derivada_a_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('derivada_por_user_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'derivada_at')) {
                $table->timestamp('derivada_at')->nullable()->after('derivada_por_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {

            if (Schema::hasColumn('solicitudes_reemplazo', 'derivada_at')) {
                $table->dropColumn('derivada_at');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'derivada_por_user_id')) {
                $table->dropConstrainedForeignId('derivada_por_user_id');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'derivada_a_user_id')) {
                $table->dropConstrainedForeignId('derivada_a_user_id');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'uatp_decision_at')) {
                $table->dropColumn('uatp_decision_at');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'uatp_decision_user_id')) {
                $table->dropConstrainedForeignId('uatp_decision_user_id');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'motivo_rechazo')) {
                $table->dropColumn('motivo_rechazo');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'area_desempeno_id')) {
                $table->dropConstrainedForeignId('area_desempeno_id');
            }
        });
    }
};
