<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();     // ej: admin.users, reemplazos, messages
            $table->string('name');              // etiqueta amigable
            $table->string('section')->default('Otros'); // Operación/Catálogos/Revisión/...
            $table->string('icon')->nullable();  // opcional (bi bi-...)
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
