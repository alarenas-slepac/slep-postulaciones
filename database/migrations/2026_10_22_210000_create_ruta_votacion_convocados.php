<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ruta_votacion_convocados')) {
            Schema::create('ruta_votacion_convocados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ruta_votacion_id')->constrained('rutas_votacion')->cascadeOnDelete();
                // El padrón puede contener un RBD que aún no esté en el catálogo.
                $table->unsignedInteger('rbd');
                $table->string('nombre_establecimiento');
                $table->timestamps();
                $table->unique(['ruta_votacion_id', 'rbd'], 'ruta_convocado_rbd_unique');
            });
        }
        if (! Schema::hasColumn('rutas_votacion', 'convocados_personalizados')) {
            Schema::table('rutas_votacion', fn (Blueprint $table) => $table->boolean('convocados_personalizados')->default(false));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_votacion_convocados');
        Schema::table('rutas_votacion', fn (Blueprint $table) => $table->dropColumn('convocados_personalizados'));
    }
};
