<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Campos personales requeridos por el proyecto
            $table->string('rut', 12)->unique()->after('id');
            $table->string('nombres')->after('rut');
            $table->string('apellido_paterno')->after('nombres');
            $table->string('apellido_materno')->after('apellido_paterno');

            // La migración base trae 'name'. Lo removemos si existe.
            if (Schema::hasColumn('users', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revertir: agregar 'name' de vuelta (nullable para no romper datos)
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable()->after('id');
            }

            // Quitar los campos agregados
            if (Schema::hasColumn('users', 'apellido_materno')) {
                $table->dropColumn('apellido_materno');
            }
            if (Schema::hasColumn('users', 'apellido_paterno')) {
                $table->dropColumn('apellido_paterno');
            }
            if (Schema::hasColumn('users', 'nombres')) {
                $table->dropColumn('nombres');
            }
            if (Schema::hasColumn('users', 'rut')) {
                $table->dropUnique('users_rut_unique');
                $table->dropColumn('rut');
            }
        });
    }
};
