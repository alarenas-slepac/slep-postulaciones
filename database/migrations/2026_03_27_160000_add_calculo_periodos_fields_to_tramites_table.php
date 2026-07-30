<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'calculo_periodos_habilitado_at')) {
                $table->timestamp('calculo_periodos_habilitado_at')->nullable()->after('anulado_por_user_id');
            }

            if (!Schema::hasColumn('tramites', 'calculo_periodos_data')) {
                $table->longText('calculo_periodos_data')->nullable()->after('calculo_periodos_habilitado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('tramites', 'calculo_periodos_data')) {
                $columns[] = 'calculo_periodos_data';
            }

            if (Schema::hasColumn('tramites', 'calculo_periodos_habilitado_at')) {
                $columns[] = 'calculo_periodos_habilitado_at';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
