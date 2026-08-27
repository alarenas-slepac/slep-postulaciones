<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procesos_votacion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('jornadas_votacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->date('fecha');
            $table->string('estado', 30)->default('borrador')->index();
            $table->boolean('publica')->default(false)->index();
            $table->text('descripcion')->nullable();
            $table->timestamp('publicada_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('finalizada_at')->nullable();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actualizada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('jornada_votacion_proceso', function (Blueprint $table) {
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->cascadeOnDelete();
            $table->foreignId('proceso_votacion_id')->constrained('procesos_votacion')->restrictOnDelete();
            $table->primary(['jornada_votacion_id', 'proceso_votacion_id'], 'jornada_proceso_pk');
        });

        Schema::create('grupos_votacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('nombre');
            $table->foreignId('encargado_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->text('observacion')->nullable();
            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('finalizado_at')->nullable();
            $table->timestamps();
            $table->unique(['jornada_votacion_id', 'numero'], 'grupo_jornada_numero_unique');
        });

        Schema::create('grupo_votacion_miembros', function (Blueprint $table) {
            $table->foreignId('grupo_votacion_id')->constrained('grupos_votacion')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('rol', 30)->default('MINISTRO_FE');
            $table->timestamps();
            $table->primary(['grupo_votacion_id', 'user_id'], 'grupo_miembro_pk');
        });

        Schema::create('rutas_votacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_votacion_id')->constrained('grupos_votacion')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['grupo_votacion_id', 'establecimiento_id'], 'ruta_grupo_estab_unique');
            $table->unique(['grupo_votacion_id', 'orden'], 'ruta_grupo_orden_unique');
        });

        Schema::create('visitas_votacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_votacion_id')->unique()->constrained('rutas_votacion')->cascadeOnDelete();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->timestamp('inicio_traslado_at')->nullable();
            $table->timestamp('inicio_votacion_at')->nullable();
            $table->timestamp('fin_votacion_at')->nullable();
            $table->foreignId('iniciada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalizada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        Schema::create('incidencias_votacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->cascadeOnDelete();
            $table->foreignId('grupo_votacion_id')->nullable()->constrained('grupos_votacion')->nullOnDelete();
            $table->foreignId('ruta_votacion_id')->nullable()->constrained('rutas_votacion')->nullOnDelete();
            $table->string('tipo', 50);
            $table->text('detalle_interno');
            $table->text('mensaje_publico')->nullable();
            $table->boolean('publica')->default(false)->index();
            $table->string('estado', 20)->default('abierta')->index();
            $table->foreignId('reportada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_at')->nullable();
            $table->text('resolucion')->nullable();
            $table->timestamps();
        });

        Schema::create('bitacora_votacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jornada_votacion_id')->constrained('jornadas_votacion')->cascadeOnDelete();
            $table->foreignId('grupo_votacion_id')->nullable()->constrained('grupos_votacion')->nullOnDelete();
            $table->foreignId('ruta_votacion_id')->nullable()->constrained('rutas_votacion')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento', 80)->index();
            $table->text('descripcion');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        DB::table('procesos_votacion')->insert([
            ['codigo' => 'CCAF', 'nombre' => 'Caja de Compensación de Asignación Familiar', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'CONSULTA_MUTUALIDADES', 'nombre' => 'Consulta de Mutualidades', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_votacion');
        Schema::dropIfExists('incidencias_votacion');
        Schema::dropIfExists('visitas_votacion');
        Schema::dropIfExists('rutas_votacion');
        Schema::dropIfExists('grupo_votacion_miembros');
        Schema::dropIfExists('grupos_votacion');
        Schema::dropIfExists('jornada_votacion_proceso');
        Schema::dropIfExists('jornadas_votacion');
        Schema::dropIfExists('procesos_votacion');
    }
};
