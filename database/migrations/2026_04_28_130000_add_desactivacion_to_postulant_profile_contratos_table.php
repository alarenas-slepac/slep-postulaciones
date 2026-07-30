<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulant_profile_contratos', function (Blueprint $table) {
            if (!Schema::hasColumn('postulant_profile_contratos', 'motivo_desactivacion')) {
                $table->string('motivo_desactivacion', 500)->nullable()->after('activo');
            }

            if (!Schema::hasColumn('postulant_profile_contratos', 'desactivado_por')) {
                $table->foreignId('desactivado_por')->nullable()->after('motivo_desactivacion')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('postulant_profile_contratos', 'desactivado_at')) {
                $table->timestamp('desactivado_at')->nullable()->after('desactivado_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('postulant_profile_contratos', function (Blueprint $table) {
            if (Schema::hasColumn('postulant_profile_contratos', 'desactivado_por')) {
                $table->dropForeign(['desactivado_por']);
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('postulant_profile_contratos', 'motivo_desactivacion') ? 'motivo_desactivacion' : null,
                Schema::hasColumn('postulant_profile_contratos', 'desactivado_por') ? 'desactivado_por' : null,
                Schema::hasColumn('postulant_profile_contratos', 'desactivado_at') ? 'desactivado_at' : null,
            ]));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
