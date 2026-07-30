<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aaee_valores_hora', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('area_desempeno_id');
            $table->string('categoria'); // profesional|tecnico|administrativo|auxiliar
            $table->decimal('valor_hora', 12, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['area_desempeno_id', 'categoria']);
            $table->foreign('area_desempeno_id')->references('id')->on('areas_desempeno')->cascadeOnDelete();
        });

        Schema::create('establecimiento_valores_hora', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->string('rol'); // educadora_parvulos|directora_jardin|directora_sala_cuna
            $table->decimal('valor_hora', 12, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['establecimiento_id', 'rol']);
            $table->foreign('establecimiento_id')->references('id')->on('establecimientos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_valores_hora');
        Schema::dropIfExists('aaee_valores_hora');
    }
};
