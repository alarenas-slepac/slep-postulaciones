<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('establecimiento_cursos')) {
            Schema::create('establecimiento_cursos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
                $table->unsignedInteger('rbd')->nullable()->index();
                $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
                $table->foreignId('plan_estudio_id')->nullable()->constrained('planes_estudio')->nullOnDelete();
                $table->unsignedSmallInteger('anio')->index();
                $table->string('letra', 20)->nullable();
                $table->string('nombre_seccion', 160);
                $table->unsignedSmallInteger('matricula')->default(0);
                $table->string('regimen_jec', 20)->index();
                $table->string('fuente', 120)->nullable();
                $table->boolean('activo')->default(true)->index();
                $table->timestamps();

                $table->unique(['establecimiento_id', 'curso_id', 'anio', 'letra'], 'establecimiento_cursos_unique');
                $table->index(['anio', 'regimen_jec'], 'establecimiento_cursos_anio_regimen_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_cursos');
    }
};
