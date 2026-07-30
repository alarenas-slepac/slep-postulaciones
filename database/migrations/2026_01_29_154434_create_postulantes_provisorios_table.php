<?php

// database/migrations/2026_01_29_000000_create_postulantes_provisorios_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('postulantes_provisorios', function (Blueprint $table) {
            $table->id();

            $table->string('rut', 12)->unique();          // ej: 12345678-K
            $table->string('rut_body', 8)->index();       // ej: 12345678
            $table->char('rut_dv', 1);                    // ej: K

            $table->string('raw_rut')->nullable();        // lo que venía en el Excel
            $table->string('nombres')->nullable();
            $table->string('apellidos')->nullable();

            $table->string('email')->nullable();          // email principal
            $table->json('emails')->nullable();           // lista de emails detectados (por duplicados)

            $table->string('source_filename')->nullable();
            $table->string('import_status')->nullable();  // ok / inferred / invalid / etc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulantes_provisorios');
    }
};
