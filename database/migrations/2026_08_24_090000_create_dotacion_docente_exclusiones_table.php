<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_docente_exclusiones')) {
            return;
        }

        Schema::create('dotacion_docente_exclusiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->string('docente_rut', 20);
            $table->string('docente_rut_normalizado', 20);
            $table->string('docente_nombre', 255);
            $table->string('motivo', 48);
            $table->decimal('horas', 8, 2);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('establecimiento_id', 'dde_establecimiento_fk')
                ->references('id')->on('establecimientos')->cascadeOnDelete();
            $table->unique(
                ['establecimiento_id', 'anio', 'docente_rut_normalizado'],
                'dde_est_anio_rut_uk'
            );
            $table->index(['establecimiento_id', 'anio'], 'dde_est_anio_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_docente_exclusiones');
    }
};
