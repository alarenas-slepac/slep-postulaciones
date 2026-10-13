<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_no_renovaciones')) {
            Schema::create('dotacion_no_renovaciones', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('establecimiento_id')->constrained('establecimientos', indexName: 'dot_nr_ee_fk');
                $table->unsignedSmallInteger('anio');
                $table->string('docente_rut_normalizado', 32);
                $table->string('docente_nombre');
                $table->text('titulo');
                $table->string('bloque', 20);
                $table->decimal('horas_contrata', 8, 2);
                $table->text('justificacion');
                $table->json('asignaciones_anteriores');
                $table->string('clave_activa', 64)->nullable()->unique('dot_nr_activa_uk');
                $table->foreignId('created_by')->nullable();
                $table->foreignId('anulada_by')->nullable();
                $table->timestamp('anulada_at')->nullable();
                $table->timestamps();
                $table->index(['establecimiento_id', 'anio'], 'dot_nr_scope_idx');
            });
        }

        foreach (['no_renovacion_id', 'titulo', 'funcion'] as $column) {
            if (! Schema::hasColumn('dotacion_contrata_habilitaciones', $column)) {
                Schema::table('dotacion_contrata_habilitaciones', function (Blueprint $table) use ($column): void {
                    if ($column === 'no_renovacion_id') {
                        $table->unsignedBigInteger($column)->nullable()->index('dot_cupo_nr_idx');
                    } else {
                        $table->text($column)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // El historial de no renovación y los títulos de cupos no se eliminan
        // automáticamente al revertir código. Requieren una revisión explícita.
    }
};
