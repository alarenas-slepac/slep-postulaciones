<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitud_reemplazo_configuraciones')) {
            Schema::create('solicitud_reemplazo_configuraciones', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 120)->unique();
                $table->text('valor')->nullable();
                $table->string('nombre', 190);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        DB::table('solicitud_reemplazo_configuraciones')->updateOrInsert(
            ['clave' => 'correo_autorizaciones_docentes'],
            [
                'nombre' => 'Correo para autorizaciones docentes',
                'descripcion' => 'Destinatario institucional de los expedientes enviados por UATP para solicitar una autorización docente.',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (! Schema::hasTable('solicitud_reemplazo_autorizaciones_docentes')) {
            Schema::create('solicitud_reemplazo_autorizaciones_docentes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('solicitud_reemplazo_id')->unique()->constrained('solicitudes_reemplazo')->cascadeOnDelete();
                $table->foreignId('postulant_profile_id')->nullable()->constrained('postulant_profiles')->nullOnDelete();
                $table->string('numero_autorizacion', 120)->nullable();
                $table->string('estado', 30)->default('en_tramite');
                $table->text('observacion_estado')->nullable();
                $table->string('correo_destino', 255)->nullable();
                $table->json('documentos_enviados')->nullable();
                $table->foreignId('solicitado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('solicitado_at')->nullable();
                $table->timestamp('correo_enviado_at')->nullable();
                $table->text('correo_error')->nullable();
                $table->foreignId('numero_registrado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('numero_registrado_at')->nullable();
                $table->foreignId('estado_actualizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('estado_actualizado_at')->nullable();
                $table->timestamps();

                $table->index(['estado', 'solicitado_at'], 'srad_estado_solicitado_idx');
                $table->index('postulant_profile_id', 'srad_postulante_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_reemplazo_autorizaciones_docentes');
        Schema::dropIfExists('solicitud_reemplazo_configuraciones');
    }
};
