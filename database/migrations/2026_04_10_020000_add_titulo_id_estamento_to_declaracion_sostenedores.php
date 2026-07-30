<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declaracion_sostenedores', function (Blueprint $table) {
            if (!Schema::hasColumn('declaracion_sostenedores', 'titulo_catalogo_id')) {
                $table->unsignedBigInteger('titulo_catalogo_id')->nullable()->after('nombre_titulo');
            }
            if (!Schema::hasColumn('declaracion_sostenedores', 'estamento')) {
                $table->string('estamento', 50)->nullable()->after('pais_titulo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('declaracion_sostenedores', function (Blueprint $table) {
            if (Schema::hasColumn('declaracion_sostenedores', 'titulo_catalogo_id')) {
                $table->dropColumn('titulo_catalogo_id');
            }
            if (Schema::hasColumn('declaracion_sostenedores', 'estamento')) {
                $table->dropColumn('estamento');
            }
        });
    }
};
