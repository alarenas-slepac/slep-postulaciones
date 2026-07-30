<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumnos_prioritarios_porcentajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->decimal('porcentaje', 5, 2);
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'anio'], 'alumnos_prioritarios_estab_anio_unique');
            $table->index(['anio', 'porcentaje']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos_prioritarios_porcentajes');
    }
};
