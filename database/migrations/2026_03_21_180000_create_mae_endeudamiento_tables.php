<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mae_cargas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('dominio', 100);
            $table->string('comuna_origen', 100)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('es_vigente')->default(false);
            $table->foreignId('reemplaza_carga_id')->nullable()->constrained('mae_cargas')->nullOnDelete();
            $table->string('motivo_reemplazo', 500)->nullable();
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->string('hash_archivo', 64);
            $table->string('estado', 50)->default('pendiente');
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_omitidas')->default(0);
            $table->unsignedInteger('filas_observadas')->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('procesado_at')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'mes', 'dominio', 'version'], 'mae_cargas_periodo_version_unique');
            $table->index(['anio', 'mes', 'dominio', 'es_vigente'], 'mae_cargas_periodo_vigente_index');
            $table->index(['dominio', 'hash_archivo'], 'mae_cargas_hash_index');
        });

        Schema::create('mae_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mae_carga_id')->constrained('mae_cargas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('dominio', 100);
            $table->string('comuna_origen', 100)->nullable();
            $table->string('rut', 32);
            $table->string('nombre_completo')->nullable();
            $table->decimal('dias_trab', 10, 2)->nullable();
            $table->json('datos_trabajador_json')->nullable();
            $table->decimal('total_haberes', 15, 2)->nullable();
            $table->decimal('monto_imponible', 15, 2)->nullable();
            $table->decimal('monto_tributable', 15, 2)->nullable();
            $table->decimal('imposiciones', 15, 2)->nullable();
            $table->decimal('salud', 15, 2)->nullable();
            $table->decimal('impuesto', 15, 2)->nullable();
            $table->decimal('total_descuentos_homologados', 15, 2)->default(0);
            $table->decimal('total_aportes_patronales', 15, 2)->default(0);
            $table->decimal('total_otros_descuentos', 15, 2)->default(0);
            $table->text('observaciones_importacion')->nullable();
            $table->json('raw_row_json')->nullable();
            $table->timestamps();

            $table->index(['mae_carga_id', 'rut'], 'mae_registros_carga_rut_index');
            $table->index(['anio', 'mes', 'dominio', 'rut'], 'mae_registros_periodo_rut_index');
        });

        Schema::create('mae_registro_descuentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mae_registro_id')->constrained('mae_registros')->cascadeOnDelete();
            $table->unsignedInteger('orden_columna');
            $table->string('columna_origen', 191);
            $table->string('columna_normalizada', 191);
            $table->string('campo_canonico', 191)->nullable();
            $table->string('grupo', 100)->nullable();
            $table->string('subgrupo', 100)->nullable();
            $table->string('tipo_movimiento', 50)->default('descuento');
            $table->boolean('es_aporte_patronal')->default(false);
            $table->decimal('valor', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['mae_registro_id', 'tipo_movimiento'], 'mae_registro_descuentos_tipo_index');
            $table->index(['columna_normalizada'], 'mae_registro_descuentos_col_norm_index');
        });

        Schema::create('mae_registro_otros_descuentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mae_registro_id')->constrained('mae_registros')->cascadeOnDelete();
            $table->string('columna_origen', 191);
            $table->string('columna_normalizada', 191);
            $table->decimal('valor', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['mae_registro_id'], 'mae_registro_otros_desc_registro_index');
        });

        Schema::create('mae_homologacion_columnas', function (Blueprint $table) {
            $table->id();
            $table->string('columna_origen', 191);
            $table->string('columna_normalizada', 191);
            $table->string('campo_canonico', 191)->nullable();
            $table->string('grupo', 100)->nullable();
            $table->string('subgrupo', 100)->nullable();
            $table->string('seccion_archivo', 100)->nullable();
            $table->string('tipo_movimiento', 50)->default('descuento');
            $table->boolean('es_aporte_patronal')->default(false);
            $table->boolean('es_guardable')->default(true);
            $table->boolean('guardar_en_resumen')->default(false);
            $table->boolean('guardar_en_detalle')->default(false);
            $table->unsignedInteger('prioridad')->default(100);
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['columna_normalizada', 'activo'], 'mae_homologacion_col_norm_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mae_homologacion_columnas');
        Schema::dropIfExists('mae_registro_otros_descuentos');
        Schema::dropIfExists('mae_registro_descuentos');
        Schema::dropIfExists('mae_registros');
        Schema::dropIfExists('mae_cargas');
    }
};
