<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('areas_desempeno', function (Blueprint $table) {
            $table->id();
            $table->string('estamento', 20); // docente | asistente
            $table->string('nombre', 255);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['estamento', 'nombre']);
            $table->index(['estamento', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas_desempeno');
    }
};
