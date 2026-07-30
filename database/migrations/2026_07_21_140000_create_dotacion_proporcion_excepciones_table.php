<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_proporcion_excepciones')) {
            return;
        }

        Schema::create('dotacion_proporcion_excepciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->foreign('establecimiento_id', 'dpe_est_fk')
                ->references('id')
                ->on('establecimientos')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->string('proporcion', 16)->default('60_40');
            $table->string('alcance', 32)->default('todos_los_niveles');
            $table->text('justificacion');
            $table->boolean('activa')->default(true)->index();
            $table->unsignedInteger('ultima_recalculacion_total')->default(0);
            $table->timestamp('ultima_recalculacion_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'anio'], 'dpe_est_anio_unique');
            $table->index(['anio', 'activa'], 'dpe_anio_activa_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_proporcion_excepciones');
    }
};
