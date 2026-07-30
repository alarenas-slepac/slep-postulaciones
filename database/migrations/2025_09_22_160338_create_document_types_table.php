<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // ej: curriculum, cedula, titulo_mencion
            $table->string('label');          // nombre visible
            $table->enum('required_for', ['docente', 'asistente', 'both', 'conditional'])->default('both');
            $table->json('conditions')->nullable(); // reglas condicionales (ver modelo)
            $table->string('template_path')->nullable(); // plantilla descargable (storage/public)
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
