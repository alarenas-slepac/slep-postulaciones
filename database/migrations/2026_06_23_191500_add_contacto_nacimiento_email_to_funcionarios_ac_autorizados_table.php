<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionarios_ac_autorizados', function (Blueprint $table) {
            if (! Schema::hasColumn('funcionarios_ac_autorizados', 'telefono')) {
                $table->string('telefono', 50)->nullable()->after('cargo_funcion');
            }
            if (! Schema::hasColumn('funcionarios_ac_autorizados', 'fecha_nacimiento')) {
                $table->date('fecha_nacimiento')->nullable()->after('telefono');
            }
            if (! Schema::hasColumn('funcionarios_ac_autorizados', 'email')) {
                $table->string('email', 190)->nullable()->after('fecha_nacimiento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('funcionarios_ac_autorizados', function (Blueprint $table) {
            if (Schema::hasColumn('funcionarios_ac_autorizados', 'email')) {
                $table->dropColumn('email');
            }
            if (Schema::hasColumn('funcionarios_ac_autorizados', 'fecha_nacimiento')) {
                $table->dropColumn('fecha_nacimiento');
            }
            if (Schema::hasColumn('funcionarios_ac_autorizados', 'telefono')) {
                $table->dropColumn('telefono');
            }
        });
    }
};
