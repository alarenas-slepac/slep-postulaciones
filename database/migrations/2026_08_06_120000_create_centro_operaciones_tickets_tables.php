<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 80)->unique();
            $table->string('unidad_departamento', 190)->nullable();
            $table->string('subdireccion_dependencia', 255)->nullable();
            $table->foreignId('responsable_funcionario_ac_id')->nullable();
            $table->foreign('responsable_funcionario_ac_id', 'co_inc_cfg_responsable_fk')
                ->references('id')
                ->on('funcionarios_ac_autorizados')
                ->nullOnDelete();
            $table->unsignedSmallInteger('plazo_dias')->default(4);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('centro_operaciones_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->nullable()->unique();
            $table->foreignId('incidencia_id')->unique()->constrained('centro_operaciones_incidencias')->cascadeOnDelete();
            $table->foreignId('configuracion_id')->nullable()->constrained('centro_operaciones_incidente_configuraciones')->nullOnDelete();
            $table->string('unidad_departamento', 190);
            $table->string('subdireccion_dependencia', 255);
            $table->foreignId('responsable_funcionario_ac_id')->constrained('funcionarios_ac_autorizados')->restrictOnDelete();
            $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vence_en');
            $table->string('estado', 30)->default('asignado');
            $table->timestamp('notificado_responsable_en')->nullable();
            $table->timestamp('escalado_en')->nullable();
            $table->timestamp('resuelto_en')->nullable();
            $table->foreignId('resuelto_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolucion')->nullable();
            $table->timestamps();
            $table->index(['estado', 'vence_en']);
            $table->index(['subdireccion_dependencia', 'estado']);
        });

        $ahora = now();
        $configuraciones = collect(config('centro_operaciones.incidencias', []))
            ->reject(fn (array $incidencia, string $tipo) => $tipo === 'otro' || ($incidencia['automatic'] ?? false))
            ->keys()
            ->map(fn (string $tipo) => [
                'tipo' => $tipo,
                'plazo_dias' => 4,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all();

        DB::table('centro_operaciones_incidente_configuraciones')->insert($configuraciones);
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_operaciones_tickets');
        Schema::dropIfExists('centro_operaciones_incidente_configuraciones');
    }
};
