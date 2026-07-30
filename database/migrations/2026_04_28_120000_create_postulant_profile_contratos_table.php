<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulant_profile_contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulant_profile_id')->constrained('postulant_profiles')->cascadeOnDelete();
            $table->string('tipo_contrato', 30);
            $table->unsignedSmallInteger('cantidad_horas')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['postulant_profile_id', 'activo'], 'pp_contratos_profile_activo_idx');
            $table->index(['establecimiento_id', 'activo'], 'pp_contratos_est_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postulant_profile_contratos');
    }
};
