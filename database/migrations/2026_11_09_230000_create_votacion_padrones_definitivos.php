<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'votacion_padrones_definitivos';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('jornada_votacion_id')
                    ->constrained('jornadas_votacion', indexName: 'vpd_jornada_fk')->restrictOnDelete();
                $table->unsignedInteger('personas');
                $table->json('datos');
                $table->json('resumen');
                $table->string('base_huella', 64);
                $table->string('certificado_path')->nullable();
                $table->unsignedBigInteger('creado_por');
                $table->unsignedBigInteger('publicado_por')->nullable();
                $table->timestamp('publicado_at')->nullable();
                $table->timestamps();
                $table->index(['jornada_votacion_id', 'publicado_at'], 'vpd_jornada_publicado_idx');
            });
        }

        // MySQL puede conservar la tabla y su FK si falla la sentencia del índice.
        // Reintentar debe completar el esquema, sin omitirlo ni perder versiones.
        if (! Schema::hasIndex($tableName, ['jornada_votacion_id', 'publicado_at'])) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['jornada_votacion_id', 'publicado_at'], 'vpd_jornada_publicado_idx');
            });
        }
    }

    public function down(): void
    {
        // Las versiones son antecedentes del proceso: no eliminar sus datos.
    }
};
