<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reemplazos_personal', 'fecha_antiguedad')) {
            Schema::table('reemplazos_personal', fn (Blueprint $table) => $table->date('fecha_antiguedad')->nullable());
        }
        Schema::create('padron_revisiones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('created_by')->index();
            $table->string('archivo');
            $table->char('archivo_hash', 64);
            $table->char('base_hash', 64);
            $table->unsignedSmallInteger('anio')->nullable();
            $table->unsignedTinyInteger('mes')->nullable();
            $table->json('resumen');
            $table->json('errores');
            $table->json('excesos');
            $table->timestamps();
        });
        Schema::create('padron_revision_filas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('padron_revision_id')->constrained('padron_revisiones')->cascadeOnDelete();
            $table->unsignedInteger('fila_excel')->nullable();
            $table->string('rut', 32)->nullable()->index();
            $table->string('nombre')->nullable();
            $table->string('accion', 50)->index();
            $table->unsignedBigInteger('personal_id')->nullable()->index();
            $table->json('datos');
            $table->json('anterior')->nullable();
            $table->json('candidatos');
            $table->json('asignaciones');
            $table->json('observaciones');
        });
        Schema::create('padron_revision_autorizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('padron_revision_id')->constrained('padron_revisiones')->cascadeOnDelete();
            $table->string('rut', 32);
            $table->decimal('jornada_total', 10, 2);
            $table->text('justificacion');
            $table->unsignedBigInteger('autorizado_por');
            $table->timestamps();
            $table->unique(['padron_revision_id', 'rut'], 'padron_revision_rut_autorizacion_unique');
        });
    }

    public function down(): void
    {
        // No simular un rollback exitoso dejando el esquema creado.
        throw new RuntimeException('La reversión del padrón requiere una migración específica autorizada que conserve revisiones, autorizaciones y fechas de antigüedad.');
    }
};
