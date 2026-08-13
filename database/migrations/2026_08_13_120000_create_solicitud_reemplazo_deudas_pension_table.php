<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->boolean('deudor_pension_alimentos')->default(false);
            $table->timestamp('deudor_pension_alimentos_marcado_at')->nullable();
        });

        Schema::create('solicitud_reemplazo_deudas_pension', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_reemplazo_id');
            $table->foreignId('postulant_profile_id');
            $table->string('estado', 40)->default('pendiente_documentos');

            $table->foreignId('activado_por_user_id')->nullable();
            $table->timestamp('activado_at')->nullable();

            $table->string('certificado_deuda_path')->nullable();
            $table->string('certificado_deuda_nombre_original')->nullable();
            $table->string('certificado_deuda_mime', 120)->nullable();
            $table->unsignedBigInteger('certificado_deuda_size')->nullable();
            $table->foreignId('certificado_subido_por_user_id')->nullable();
            $table->timestamp('certificado_subido_at')->nullable();

            $table->string('resolucion_path')->nullable();
            $table->string('resolucion_nombre_original')->nullable();
            $table->string('resolucion_mime', 120)->nullable();
            $table->unsignedBigInteger('resolucion_size')->nullable();
            $table->decimal('valor_cuota_alimentaria', 12, 2)->nullable();
            $table->text('observacion_postulante')->nullable();
            $table->foreignId('resolucion_subida_por_user_id')->nullable();
            $table->timestamp('resolucion_subida_at')->nullable();

            $table->string('correo_destino', 255)->nullable();
            $table->foreignId('enviado_por_user_id')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamps();

            $table->unique('solicitud_reemplazo_id', 'srdp_solicitud_unq');
            $table->index(['postulant_profile_id', 'estado'], 'srdp_postulante_estado_idx');
            $table->index(['estado', 'activado_at'], 'srdp_estado_activado_idx');

            $table->foreign('solicitud_reemplazo_id', 'srdp_solicitud_fk')
                ->references('id')->on('solicitudes_reemplazo')->cascadeOnDelete();
            $table->foreign('postulant_profile_id', 'srdp_postulante_fk')
                ->references('id')->on('postulant_profiles')->restrictOnDelete();
            $table->foreign('activado_por_user_id', 'srdp_activado_por_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('certificado_subido_por_user_id', 'srdp_certificado_por_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolucion_subida_por_user_id', 'srdp_resolucion_por_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('enviado_por_user_id', 'srdp_enviado_por_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        DB::table('solicitud_reemplazo_configuraciones')->updateOrInsert(
            ['clave' => 'correo_encargada_remuneraciones_deuda_pension'],
            [
                'nombre' => 'Correo encargada de remuneraciones',
                'descripcion' => 'Destinataria de antecedentes de postulantes con deuda de pensión de alimentos.',
                'activo' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('solicitud_reemplazo_configuraciones')
            ->where('clave', 'correo_encargada_remuneraciones_deuda_pension')
            ->delete();

        Schema::dropIfExists('solicitud_reemplazo_deudas_pension');

        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'deudor_pension_alimentos',
                'deudor_pension_alimentos_marcado_at',
            ]);
        });
    }
};
