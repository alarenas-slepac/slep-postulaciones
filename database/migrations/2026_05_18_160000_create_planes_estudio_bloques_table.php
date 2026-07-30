<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planes_estudio_bloques')) {
            Schema::create('planes_estudio_bloques', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_estudio_id')->constrained('planes_estudio')->cascadeOnDelete();
                $table->string('nombre', 160);
                $table->string('tipo_bloque', 80)->index();
                $table->decimal('horas_semanales', 6, 2)->nullable();
                $table->decimal('horas_anuales', 8, 2)->nullable();
                $table->boolean('permite_asignaturas_establecimiento')->default(false)->index();
                $table->boolean('permite_asignaturas_personalizadas')->default(false)->index();
                $table->unsignedSmallInteger('orden')->default(1)->index();
                $table->boolean('activo')->default(true)->index();
                $table->timestamps();

                $table->index(['plan_estudio_id', 'tipo_bloque'], 'planes_bloques_plan_tipo_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_estudio_bloques');
    }
};
