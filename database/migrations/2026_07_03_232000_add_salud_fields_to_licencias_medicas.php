<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licencias_medicas', function (Blueprint $table) {
            if (!Schema::hasColumn('licencias_medicas', 'sistema_salud')) {
                $table->string('sistema_salud', 20)->nullable()->after('tipo_licencia_glosa');
            }

            if (!Schema::hasColumn('licencias_medicas', 'institucion_salud')) {
                $table->string('institucion_salud', 150)->nullable()->after('sistema_salud');
            }
        });
    }

    public function down(): void
    {
        Schema::table('licencias_medicas', function (Blueprint $table) {
            if (Schema::hasColumn('licencias_medicas', 'institucion_salud')) {
                $table->dropColumn('institucion_salud');
            }

            if (Schema::hasColumn('licencias_medicas', 'sistema_salud')) {
                $table->dropColumn('sistema_salud');
            }
        });
    }
};
