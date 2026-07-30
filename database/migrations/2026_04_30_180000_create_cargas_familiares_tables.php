<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cargas_familiares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('periodo_carga', 20)->nullable();
            $table->string('comuna_origen', 120)->nullable();
            $table->string('fuente_archivo', 255)->nullable();

            $table->string('beneficiario_run', 20)->nullable();
            $table->string('beneficiario_dv', 2)->nullable();
            $table->string('beneficiario_rut_completo', 25)->nullable();
            $table->string('beneficiario_run_normalizado', 25)->index();
            $table->string('beneficiario_apellido_paterno', 120)->nullable();
            $table->string('beneficiario_apellido_materno', 120)->nullable();
            $table->string('beneficiario_nombres', 180)->nullable();
            $table->string('beneficiario_email', 190)->nullable();

            $table->string('causante_run', 20)->nullable();
            $table->string('causante_dv', 2)->nullable();
            $table->string('causante_rut_completo', 25)->nullable();
            $table->string('causante_run_normalizado', 25)->nullable()->index();
            $table->string('causante_apellido_paterno', 120)->nullable();
            $table->string('causante_apellido_materno', 120)->nullable();
            $table->string('causante_nombres', 180)->nullable();

            $table->string('sexo', 30)->nullable();
            $table->string('parentesco', 120)->nullable();
            $table->string('codigo_siagf', 40)->nullable();
            $table->string('tipo_beneficio', 120)->nullable();
            $table->string('codigo_tipo_causante', 40)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_resolucion')->nullable();
            $table->string('numero_resolucion', 80)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->string('tipo', 120)->nullable();
            $table->string('tramo', 40)->nullable();
            $table->decimal('monto', 14, 2)->nullable();
            $table->string('estado_carga', 40)->default('vigente');
            $table->text('observaciones')->nullable();
            $table->json('raw_row')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado_carga']);
            $table->index(['beneficiario_run', 'beneficiario_dv']);
            $table->index(['comuna_origen', 'periodo_carga']);
        });

        Schema::create('cargas_familiares_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo_solicitud', 40)->default('nueva_carga');
            $table->string('estado', 40)->default('enviado');
            $table->json('beneficiario_snapshot')->nullable();
            $table->boolean('solicitante_distinto')->default(false);
            $table->json('solicitante_snapshot')->nullable();
            $table->boolean('solicita_pago_directo')->default(false);
            $table->boolean('declaracion_aceptada')->default(false);
            $table->json('declaracion_ingresos')->nullable();
            $table->timestamp('fecha_envio')->nullable();
            $table->timestamp('fecha_revision')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacion_revision')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'estado']);
            $table->index(['estado', 'fecha_envio']);
            $table->index('tipo_solicitud');
        });

        Schema::create('cargas_familiares_causantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('cargas_familiares_solicitudes')->cascadeOnDelete();
            $table->foreignId('carga_familiar_id')->nullable()->constrained('cargas_familiares')->nullOnDelete();
            $table->string('accion', 40)->default('nuevo');
            $table->string('run', 20)->nullable();
            $table->string('dv', 2)->nullable();
            $table->string('rut_completo', 25)->nullable();
            $table->string('run_normalizado', 25)->nullable()->index();
            $table->string('apellido_paterno', 120)->nullable();
            $table->string('apellido_materno', 120)->nullable();
            $table->string('nombres', 180)->nullable();
            $table->string('sexo', 30)->nullable();
            $table->string('parentesco', 120)->nullable();
            $table->string('codigo_tipo_beneficio', 40)->nullable();
            $table->string('codigo_tipo_causante', 40)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedSmallInteger('edad_al_enviar')->nullable();
            $table->date('fecha_inicio_beneficio')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado_revision', 40)->default('pendiente');
            $table->text('revision_observacion')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['solicitud_id', 'estado_revision']);
        });

        Schema::create('cargas_familiares_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('cargas_familiares_solicitudes')->cascadeOnDelete();
            $table->foreignId('causante_id')->nullable()->constrained('cargas_familiares_causantes')->cascadeOnDelete();
            $table->string('nivel', 30)->default('solicitud');
            $table->string('tipo_documento', 120);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('estado_revision', 40)->default('pendiente');
            $table->text('revision_observacion')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['solicitud_id', 'tipo_documento']);
            $table->index(['causante_id', 'tipo_documento']);
            $table->index('estado_revision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_familiares_documentos');
        Schema::dropIfExists('cargas_familiares_causantes');
        Schema::dropIfExists('cargas_familiares_solicitudes');
        Schema::dropIfExists('cargas_familiares');
    }
};
