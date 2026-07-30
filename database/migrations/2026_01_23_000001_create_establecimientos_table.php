<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();

            // Columnas según nómina (Excel)
            $table->unsignedInteger('cod_estab')->unique();
            $table->unsignedInteger('rbd')->unique();
            $table->string('dv', 2)->nullable();
            $table->string('nombre_establecimiento', 255);
            $table->string('clasificacion', 255)->nullable();
            $table->string('tipo_estab', 80)->nullable();

            // Tipo enseñanza (S/N)
            $table->boolean('sala_cuna')->default(false);
            $table->boolean('pre_escolar')->default(false);
            $table->boolean('basica')->default(false);
            $table->boolean('media')->default(false);
            $table->boolean('tecnico_profesional')->default(false);
            $table->boolean('adultos')->default(false);
            $table->boolean('especial')->default(false);

            $table->string('comuna', 120)->nullable();
            $table->unsignedSmallInteger('asignacion_zona')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimientos');
    }
};
