<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'plani_reapertura_motivo')) {
                $table->text('plani_reapertura_motivo')->nullable()->after('plani_motivo_rechazo');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'plani_rechazo_reabierto_motivo')) {
                $table->text('plani_rechazo_reabierto_motivo')->nullable()->after('plani_reapertura_motivo');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'uatp_reapertura_motivo')) {
                $table->text('uatp_reapertura_motivo')->nullable()->after('motivo_rechazo');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'uatp_rechazo_reabierto_motivo')) {
                $table->text('uatp_rechazo_reabierto_motivo')->nullable()->after('uatp_reapertura_motivo');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'plani_reapertura_user_id')) {
                $table->foreignId('plani_reapertura_user_id')
                    ->nullable()
                    ->after('plani_decision_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'plani_reapertura_at')) {
                $table->timestamp('plani_reapertura_at')->nullable()->after('plani_reapertura_user_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'uatp_reapertura_user_id')) {
                $table->foreignId('uatp_reapertura_user_id')
                    ->nullable()
                    ->after('uatp_decision_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'uatp_reapertura_at')) {
                $table->timestamp('uatp_reapertura_at')->nullable()->after('uatp_reapertura_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_reemplazo', 'plani_reapertura_user_id')) {
                $table->dropConstrainedForeignId('plani_reapertura_user_id');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'uatp_reapertura_user_id')) {
                $table->dropConstrainedForeignId('uatp_reapertura_user_id');
            }

            foreach ([
                'plani_reapertura_at',
                'plani_rechazo_reabierto_motivo',
                'plani_reapertura_motivo',
                'uatp_reapertura_at',
                'uatp_rechazo_reabierto_motivo',
                'uatp_reapertura_motivo',
            ] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
