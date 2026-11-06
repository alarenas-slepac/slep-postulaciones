<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_establecimiento_revisiones')) { return; }
        Schema::create('dotacion_establecimiento_revisiones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos', indexName: 'der_est_fk');
            $table->unsignedSmallInteger('anio');
            $table->string('clave_activa', 64)->nullable()->unique('der_activa_uk');
            $table->timestamp('finalizada_at');
            $table->foreignId('finalizada_by')->nullable();
            $table->timestamp('reabierta_at')->nullable();
            $table->foreignId('reabierta_by')->nullable();
            $table->text('motivo_reapertura')->nullable();
            $table->timestamps();
            $table->index(['anio', 'establecimiento_id'], 'der_ee_anio_idx');
        });
    }

    public function down(): void
    {
        // El historial de revisión anual se conserva al revertir el código.
    }
};
