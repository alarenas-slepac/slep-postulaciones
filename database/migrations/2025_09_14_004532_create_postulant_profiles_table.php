<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('postulant_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();

            // Personales
            $table->string('email_contacto', 190);
            $table->date('fecha_nacimiento');
            $table->string('direccion', 190);
            $table->string('region_code', 5);
            $table->unsignedBigInteger('comuna_id');
            $table->string('nacionalidad', 80)->default('Chile');
            $table->string('telefono1', 30);
            $table->string('telefono2', 30)->nullable();
            $table->string('genero', 20);
            $table->string('pronombres', 20)->nullable();
            $table->string('foto_path')->nullable();
            $table->string('foto_thumb_path')->nullable();

            // Académicos
            $table->string('estamento', 20); // docente|asistente
            $table->string('area_desempeno', 100)->nullable();
            $table->string('mencion', 150)->nullable();
            $table->string('especialidad_tp', 150)->nullable();
            $table->string('nivel_estudios', 80)->nullable();
            $table->string('institucion_titulo', 190)->nullable();
            $table->unsignedInteger('semestres')->nullable();
            $table->unsignedInteger('horas_totales')->nullable();
            $table->unsignedInteger('anios_experiencia')->nullable();
            $table->string('cargos_funcion', 150)->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('comuna_id')->references('id')->on('communes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulant_profiles');
    }
};
