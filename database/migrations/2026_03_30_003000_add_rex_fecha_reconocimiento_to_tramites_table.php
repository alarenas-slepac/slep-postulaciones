<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'rex_fecha_reconocimiento')) {
                $table->date('rex_fecha_reconocimiento')->nullable()->after('rex_generado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (Schema::hasColumn('tramites', 'rex_fecha_reconocimiento')) {
                $table->dropColumn('rex_fecha_reconocimiento');
            }
        });
    }
};
