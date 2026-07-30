<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('declaracion_sostenedores', function (Blueprint $table) {
        $table->id();
        $table->integer('numero')->nullable();
        $table->string('rbd')->nullable();
        $table->string('rut');
        $table->string('nombres');
        $table->string('apellido_paterno');
        $table->string('apellido_materno');
        $table->integer('horas_contratadas')->nullable();

        $table->boolean('educacion_parvularia')->default(false);
        $table->boolean('ensenanza_basica')->default(false);
        $table->boolean('ensenanza_media')->default(false);

        $table->string('certificado_titulo')->nullable();
        $table->string('certificado_antecedentes')->nullable();

        $table->string('nombre_titulo')->nullable();
        $table->string('institucion_educacional')->nullable();
        $table->date('fecha_titulacion')->nullable();
        $table->string('pais_titulo')->nullable();

        $table->text('observacion_funcionario')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declaracion_sostenedores');
    }
};

