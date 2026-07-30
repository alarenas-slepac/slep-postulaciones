<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('solicitudes_reemplazo')) {
            return;
        }

        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'reemplazo_ajuste_observacion')) {
                $table->text('reemplazo_ajuste_observacion')->nullable()->after('reasignacion_postulante_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'reemplazo_ajuste_user_id')) {
                $table->unsignedBigInteger('reemplazo_ajuste_user_id')->nullable()->after('reemplazo_ajuste_observacion');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'reemplazo_ajuste_role')) {
                $table->string('reemplazo_ajuste_role', 50)->nullable()->after('reemplazo_ajuste_user_id');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'reemplazo_ajuste_at')) {
                $table->timestamp('reemplazo_ajuste_at')->nullable()->after('reemplazo_ajuste_role');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('solicitudes_reemplazo')) {
            return;
        }

        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach (['reemplazo_ajuste_observacion', 'reemplazo_ajuste_user_id', 'reemplazo_ajuste_role', 'reemplazo_ajuste_at'] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
