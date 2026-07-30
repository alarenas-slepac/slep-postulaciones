<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('instituciones_catalogo')) {
            Schema::create('instituciones_catalogo', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('declaracion_sostenedores') && !Schema::hasColumn('declaracion_sostenedores', 'institucion_catalogo_id')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                $table->unsignedBigInteger('institucion_catalogo_id')->nullable()->after('titulo_catalogo_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('declaracion_sostenedores') && Schema::hasColumn('declaracion_sostenedores', 'institucion_catalogo_id')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                $table->dropColumn('institucion_catalogo_id');
            });
        }

        Schema::dropIfExists('instituciones_catalogo');
    }
};
