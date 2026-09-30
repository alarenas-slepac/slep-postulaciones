<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_convivencia_horas')) {
            return;
        }
        Schema::create('dotacion_convivencia_horas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->decimal('horas', 8, 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['establecimiento_id', 'anio'], 'dch_est_anio_unique');
            $table->foreign('establecimiento_id', 'dch_est_fk')->references('id')->on('establecimientos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_convivencia_horas');
    }
};
