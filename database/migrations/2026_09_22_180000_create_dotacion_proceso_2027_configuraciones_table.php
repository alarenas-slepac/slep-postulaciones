<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_proceso_2027_configuraciones')) {
            Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
                $table->unsignedSmallInteger('anio')->index();
                $table->string('decision_combinacion', 40)->nullable();
                $table->text('observacion_combinacion')->nullable();
                $table->decimal('max_horas_bloque_1', 8, 2)->nullable();
                $table->decimal('max_horas_bloque_2', 8, 2)->nullable();
                $table->decimal('max_horas_bloque_3', 8, 2)->nullable();
                $table->foreignId('combinacion_confirmada_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('combinacion_confirmada_at')->nullable();
                $table->foreignId('maximos_configurados_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('maximos_configurados_at')->nullable();
                $table->timestamps();
                $table->unique(['establecimiento_id', 'anio'], 'dotacion_proceso_2027_estab_anio_unique');
            });
        }

        if (Schema::hasTable('dotacion_docente_asignaciones')
            && ! Schema::hasColumn('dotacion_docente_asignaciones', 'excepcion_prelacion')) {
            Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) {
                $table->text('excepcion_prelacion')->nullable()->after('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dotacion_docente_asignaciones')
            && Schema::hasColumn('dotacion_docente_asignaciones', 'excepcion_prelacion')) {
            Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) {
                $table->dropColumn('excepcion_prelacion');
            });
        }

        Schema::dropIfExists('dotacion_proceso_2027_configuraciones');
    }
};
