<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'observacion_slep')) {
                $table->text('observacion_slep')->nullable()->after('derivada_at');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'observacion_slep_user_id')) {
                $table->unsignedBigInteger('observacion_slep_user_id')->nullable()->after('observacion_slep');
            }
            if (!Schema::hasColumn('solicitudes_reemplazo', 'observacion_slep_at')) {
                $table->timestamp('observacion_slep_at')->nullable()->after('observacion_slep_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach (['observacion_slep_at', 'observacion_slep_user_id', 'observacion_slep'] as $col) {
                if (Schema::hasColumn('solicitudes_reemplazo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
