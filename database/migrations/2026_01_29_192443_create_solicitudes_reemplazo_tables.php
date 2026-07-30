<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_reemplazo', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('establecimiento_id');

            // Funcionario titular desde reemplazos_personal (tabla que ya tienes)
            $table->unsignedBigInteger('reemplazo_personal_id');

            // Propuesta de reemplazo (postulante_profile)
            $table->unsignedBigInteger('postulant_profile_id')->nullable();

            // Correlativo por año
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('correlativo'); // 1..99999
            $table->string('numero_solicitud', 12)->unique(); // "00001-2026"

            // Datos contacto
            $table->string('contacto_nombre');
            $table->string('contacto_fono', 30);
            $table->string('contacto_email');

            // Reemplazo
            $table->string('tipo_reemplazo', 80);
            $table->string('tipo_reemplazo_otro')->nullable();

            $table->date('fecha_inicio');
            $table->date('fecha_termino');

            $table->boolean('propone_reemplazo')->default(false);
            $table->boolean('continuidad')->nullable(); // solo si propone_reemplazo = true

            // Archivos
            $table->string('oficio_pdf_path')->nullable();
            $table->string('respaldo_pdf_path')->nullable();

            $table->text('observaciones')->nullable();

            // Estados
            $table->string('estado', 40)->default('pendiente_uatp'); // pendiente_uatp|aprobada|rechazada|anulada

            $table->timestamps();

            $table->index(['establecimiento_id', 'estado']);
            $table->index(['anio', 'correlativo']);

            $table->foreign('establecimiento_id')->references('id')->on('establecimientos')->cascadeOnDelete();
            $table->foreign('reemplazo_personal_id')->references('id')->on('reemplazos_personal')->cascadeOnDelete();
            $table->foreign('postulant_profile_id')->references('id')->on('postulant_profiles')->nullOnDelete();
        });

        Schema::create('solicitud_reemplazo_jornadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitud_reemplazo_id');

            $table->string('financiamiento', 50); // SUBV GRAL, PIE, SEP, PRO-RETENCION, etc.

            // Snapshot del titular
            $table->decimal('titular_basica', 8, 2)->default(0);
            $table->decimal('titular_media', 8, 2)->default(0);
            $table->decimal('titular_total', 8, 2)->default(0);

            // Horas propuestas para el reemplazo (editable si propone_reemplazo)
            $table->decimal('reemplazo_basica', 8, 2)->default(0);
            $table->decimal('reemplazo_media', 8, 2)->default(0);
            $table->decimal('reemplazo_total', 8, 2)->default(0);

            $table->timestamps();

            $table->foreign('solicitud_reemplazo_id')
                ->references('id')->on('solicitudes_reemplazo')
                ->cascadeOnDelete();

            $table->index(['solicitud_reemplazo_id', 'financiamiento'], 'srj_solrep_fin_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_reemplazo_jornadas');
        Schema::dropIfExists('solicitudes_reemplazo');
    }
};
