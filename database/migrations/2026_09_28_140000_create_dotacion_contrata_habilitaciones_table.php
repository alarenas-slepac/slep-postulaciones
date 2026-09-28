<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id')->index();
            $table->unsignedSmallInteger('anio');
            $table->string('bloque', 20);
            $table->decimal('horas', 5, 2);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->index(['establecimiento_id', 'anio', 'bloque'], 'dotacion_contrata_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_contrata_habilitaciones');
    }
};
