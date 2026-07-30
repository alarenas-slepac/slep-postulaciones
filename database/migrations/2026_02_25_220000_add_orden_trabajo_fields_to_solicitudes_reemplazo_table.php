<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'fecha_inicio_trabajo')) {
                $table->date('fecha_inicio_trabajo')->nullable()->after('fecha_termino');
                $table->index('fecha_inicio_trabajo');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'orden_trabajo_creada_por_user_id')) {
                $table->foreignId('orden_trabajo_creada_por_user_id')
                    ->nullable()
                    ->after('derivada_at')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('orden_trabajo_creada_por_user_id');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'orden_trabajo_creada_at')) {
                $table->timestamp('orden_trabajo_creada_at')->nullable()->after('orden_trabajo_creada_por_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (Schema::hasColumn('solicitudes_reemplazo', 'orden_trabajo_creada_at')) {
                $table->dropColumn('orden_trabajo_creada_at');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'orden_trabajo_creada_por_user_id')) {
                $table->dropConstrainedForeignId('orden_trabajo_creada_por_user_id');
            }

            if (Schema::hasColumn('solicitudes_reemplazo', 'fecha_inicio_trabajo')) {
                $table->dropColumn('fecha_inicio_trabajo');
            }
        });
    }
};
