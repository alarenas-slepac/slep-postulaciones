<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_operaciones_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $table->foreignId('reportado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_reporte')->index();
            $table->timestamp('reportado_en')->index();
            $table->string('establecimiento_nombre', 255);
            $table->unsignedInteger('establecimiento_rbd')->nullable();
            $table->string('establecimiento_comuna', 120)->nullable();
            $table->string('funcionamiento', 24);
            $table->unsignedInteger('matricula_total')->default(0);
            $table->string('matricula_fuente', 40)->nullable();
            $table->unsignedInteger('estudiantes_presentes')->default(0);
            $table->unsignedInteger('docentes_total')->default(0);
            $table->unsignedInteger('docentes_presentes')->default(0);
            $table->unsignedInteger('asistentes_total')->default(0);
            $table->unsignedInteger('asistentes_presentes')->default(0);
            $table->char('padron_periodo', 6)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('necesita_apoyo')->default(false);
            $table->text('apoyo_detalle')->nullable();
            $table->string('prioridad', 24);
            $table->string('estado_general', 16)->default('operativo')->index();
            $table->string('regla_version', 20)->default('1.0');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['establecimiento_id', 'fecha_reporte'], 'co_reportes_establecimiento_fecha_idx');
        });

        Schema::create('centro_operaciones_reporte_servicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('centro_operaciones_reportes')->cascadeOnDelete();
            $table->string('servicio', 48);
            $table->string('estado', 16);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['reporte_id', 'servicio'], 'co_reporte_servicio_unique');
            $table->index(['servicio', 'estado'], 'co_servicio_estado_idx');
        });

        Schema::create('centro_operaciones_reporte_afectaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('centro_operaciones_reportes')->cascadeOnDelete();
            $table->string('tipo', 48);
            $table->text('detalle')->nullable();
            $table->timestamps();

            $table->unique(['reporte_id', 'tipo'], 'co_reporte_afectacion_unique');
        });

        Schema::create('centro_operaciones_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('centro_operaciones_reportes')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $table->date('fecha_incidencia')->index();
            $table->string('tipo', 48);
            $table->string('severidad', 16);
            $table->text('descripcion')->nullable();
            $table->string('estado', 16)->default('activa')->index();
            $table->timestamp('resuelta_en')->nullable();
            $table->foreignId('resuelta_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resuelta_en_reporte_id')->nullable()->constrained('centro_operaciones_reportes')->nullOnDelete();
            $table->timestamps();

            $table->index(['establecimiento_id', 'estado'], 'co_incidencias_establecimiento_estado_idx');
        });

        Schema::create('centro_operaciones_reporte_revisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('centro_operaciones_reportes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('editado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('datos');
            $table->timestamps();

            $table->unique(['reporte_id', 'version'], 'co_reporte_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_operaciones_reporte_revisiones');
        Schema::dropIfExists('centro_operaciones_incidencias');
        Schema::dropIfExists('centro_operaciones_reporte_afectaciones');
        Schema::dropIfExists('centro_operaciones_reporte_servicios');
        Schema::dropIfExists('centro_operaciones_reportes');
    }
};
