<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'dotacion_sobredotacion_justificaciones';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id');
                $table->unsignedSmallInteger('anio');
                $table->string('docente_rut_normalizado', 32);
                $table->string('bloque', 24);
                $table->string('tipo_horas', 16);
                $table->decimal('horas_detectadas', 8, 2);
                $table->text('justificacion');
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // MySQL limita las restricciones a 64 caracteres. Un intento anterior
        // pudo dejar la tabla creada antes de fallar al agregar la clave foránea.
        if (! Schema::hasIndex($tableName, 'dsj_est_anio_docente_bloque_tipo_unique')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(
                    ['establecimiento_id', 'anio', 'docente_rut_normalizado', 'bloque', 'tipo_horas'],
                    'dsj_est_anio_docente_bloque_tipo_unique'
                );
            });
        }

        $hasForeignKey = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreignKey) => ($foreignKey['columns'] ?? []) === ['establecimiento_id']);
        if (! $hasForeignKey) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('establecimiento_id', 'dsj_est_fk')
                    ->references('id')->on('establecimientos');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_sobredotacion_justificaciones');
    }
};
