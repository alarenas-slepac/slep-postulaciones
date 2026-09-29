<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_docente_subsectores')) {
            return;
        }

        Schema::create('dotacion_docente_subsectores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id')->index();
            $table->unsignedSmallInteger('anio');
            $table->string('asignatura_key', 40);
            $table->string('nivel', 24);
            $table->string('asignatura_nombre', 255);
            $table->string('docente_rut_normalizado', 32);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->unique(['establecimiento_id', 'anio', 'asignatura_key', 'docente_rut_normalizado'], 'dot_subsector_docente_unique');
            $table->index(['establecimiento_id', 'anio', 'asignatura_key'], 'dot_subsector_asignatura_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_docente_subsectores');
    }
};
