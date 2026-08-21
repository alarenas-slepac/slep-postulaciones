<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mae_carga_clasificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mae_carga_id')->constrained('mae_cargas')->cascadeOnDelete();
            $table->unsignedInteger('orden_columna');
            $table->string('columna_origen', 191);
            $table->string('columna_normalizada', 191);
            $table->string('campo_canonico', 191)->nullable();
            $table->string('categoria_detectada', 50);
            $table->string('categoria_seleccionada', 50);
            $table->string('fuente_deteccion', 30);
            $table->string('grupo', 100)->nullable();
            $table->string('subgrupo', 100)->nullable();
            $table->string('tipo_movimiento', 50)->default('descuento');
            $table->boolean('es_aporte_patronal')->default(false);
            $table->foreignId('homologacion_id')->nullable()->constrained('mae_homologacion_columnas')->nullOnDelete();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamps();

            $table->unique(['mae_carga_id', 'orden_columna'], 'mae_carga_clasificacion_columna_unique');
            $table->index(['mae_carga_id', 'categoria_seleccionada'], 'mae_carga_clasificacion_categoria_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mae_carga_clasificaciones');
    }
};
