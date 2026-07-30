<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bolsa_trabajo_ofertas')) {
            return;
        }

        Schema::table('bolsa_trabajo_ofertas', function (Blueprint $table) {
            if (!Schema::hasColumn('bolsa_trabajo_ofertas', 'remuneracion_bruta')) {
                $table->unsignedBigInteger('remuneracion_bruta')->nullable()->after('cantidad_horas');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bolsa_trabajo_ofertas')) {
            return;
        }

        Schema::table('bolsa_trabajo_ofertas', function (Blueprint $table) {
            if (Schema::hasColumn('bolsa_trabajo_ofertas', 'remuneracion_bruta')) {
                $table->dropColumn('remuneracion_bruta');
            }
        });
    }
};
