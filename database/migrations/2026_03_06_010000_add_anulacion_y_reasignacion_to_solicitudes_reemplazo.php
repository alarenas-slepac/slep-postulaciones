<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'anulada_motivo')) {
                $table->text('anulada_motivo')->nullable()->after('motivo_rechazo');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'anulada_by')) {
                $table->unsignedBigInteger('anulada_by')->nullable()->after('anulada_motivo');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'anulada_at')) {
                $table->timestamp('anulada_at')->nullable()->after('anulada_by');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'reasignacion_postulante_from')) {
                $table->unsignedBigInteger('reasignacion_postulante_from')->nullable()->after('postulant_profile_id');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'reasignacion_postulante_motivo')) {
                $table->text('reasignacion_postulante_motivo')->nullable()->after('reasignacion_postulante_from');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'reasignacion_postulante_by')) {
                $table->unsignedBigInteger('reasignacion_postulante_by')->nullable()->after('reasignacion_postulante_motivo');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'reasignacion_postulante_at')) {
                $table->timestamp('reasignacion_postulante_at')->nullable()->after('reasignacion_postulante_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach (['reasignacion_postulante_at','reasignacion_postulante_by','reasignacion_postulante_motivo','reasignacion_postulante_from','anulada_at','anulada_by','anulada_motivo'] as $col) {
                if (Schema::hasColumn('solicitudes_reemplazo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
