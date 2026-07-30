<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->string('prevision_afp', 60)->nullable()->after('cargos_funcion');
            $table->string('salud_institucion', 60)->nullable()->after('prevision_afp');
            $table->string('banco', 60)->nullable()->after('salud_institucion');
            $table->string('tipo_cuenta', 40)->nullable()->after('banco');
            $table->string('numero_cuenta', 40)->nullable()->after('tipo_cuenta');
        });
    }

    public function down(): void
    {
        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->dropColumn(['prevision_afp', 'salud_institucion', 'banco', 'tipo_cuenta', 'numero_cuenta']);
        });
    }
};
