<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('declaracion_sostenedores')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                if (!Schema::hasColumn('declaracion_sostenedores', 'tipo_titulo')) {
                    $table->string('tipo_titulo', 20)->nullable()->after('nombre_funcion');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('declaracion_sostenedores')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                if (Schema::hasColumn('declaracion_sostenedores', 'tipo_titulo')) {
                    $table->dropColumn('tipo_titulo');
                }
            });
        }
    }
};
