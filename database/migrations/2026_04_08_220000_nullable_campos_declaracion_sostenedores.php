<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('declaracion_sostenedores', function (Blueprint $table) {
            $table->string('nombres')->nullable()->change();
            $table->string('apellido_paterno')->nullable()->change();
            $table->string('apellido_materno')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('declaracion_sostenedores', function (Blueprint $table) {
            $table->string('nombres')->nullable(false)->change();
            $table->string('apellido_paterno')->nullable(false)->change();
            $table->string('apellido_materno')->nullable(false)->change();
        });
    }
};
