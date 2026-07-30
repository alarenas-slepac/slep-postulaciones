<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 190);        // nombre de la mención
            $table->unsignedSmallInteger('anio')->nullable();
            $table->foreignId('subsector_id')->constrained('subsectores')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('universidad', 190)->nullable();
            $table->timestamps();

            // Evita duplicados exactos del mismo registro
            $table->unique(['nombre', 'universidad', 'anio', 'subsector_id'], 'menciones_unique_key');
            $table->index(['subsector_id', 'anio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menciones');
    }
};
