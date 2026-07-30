<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reemplazos_personal', function (Blueprint $table) {
            $table->id();

            $table->foreignId('establecimiento_id')
                ->nullable()
                ->constrained('establecimientos')
                ->nullOnDelete();

            $table->unsignedInteger('rbd');

            $table->string('rut', 20);
            $table->string('nombre');

            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_termino')->nullable();

            $table->string('tipocontrato')->nullable();
            $table->string('financiamiento')->nullable();
            $table->string('estatuto')->nullable();
            $table->string('escalafon')->nullable();

            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');

            $table->unsignedSmallInteger('jornada')->nullable();
            $table->unsignedSmallInteger('jornada_basica')->nullable();
            $table->unsignedSmallInteger('jornada_media')->nullable();

            // Hash idempotente basado en la "clave de negocio" de la fila (ver controlador de import)
            $table->char('row_hash', 64)->unique();

            $table->string('source_filename')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['anio', 'mes']);
            $table->index(['rbd']);
            $table->index(['rut']);
            $table->index(['establecimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reemplazos_personal');
    }
};
