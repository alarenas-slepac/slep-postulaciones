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
                $table->foreignId('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('updated_by', 'src_updated_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
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

        // MySQL puede conservar la tabla creada si una restricción falla antes de
        // que Laravel registre la migración. En ese caso no contiene datos válidos
        // y debe reconstruirse para asegurar que todas las claves queden aplicadas.
        if (Schema::hasTable('solicitud_reemplazo_autorizaciones_docentes')) {
            Schema::drop('solicitud_reemplazo_autorizaciones_docentes');
        }

        Schema::create('solicitud_reemplazo_autorizaciones_docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_reemplazo_id');
            $table->foreignId('postulant_profile_id')->nullable();
            $table->string('numero_autorizacion', 120)->nullable();
            $table->string('estado', 30)->default('en_tramite');
            $table->text('observacion_estado')->nullable();
            $table->string('correo_destino', 255)->nullable();
            $table->json('documentos_enviados')->nullable();
            $table->foreignId('solicitado_por_user_id')->nullable();
            $table->timestamp('solicitado_at')->nullable();
            $table->timestamp('correo_enviado_at')->nullable();
            $table->text('correo_error')->nullable();
            $table->foreignId('numero_registrado_por_user_id')->nullable();
            $table->timestamp('numero_registrado_at')->nullable();
            $table->foreignId('estado_actualizado_por_user_id')->nullable();
            $table->timestamp('estado_actualizado_at')->nullable();
            $table->timestamps();

            $table->unique('solicitud_reemplazo_id', 'srad_solicitud_unq');
            $table->index(['estado', 'solicitado_at'], 'srad_estado_solicitado_idx');
            $table->index('postulant_profile_id', 'srad_postulante_idx');

            $table->foreign('solicitud_reemplazo_id', 'srad_solicitud_fk')
                ->references('id')
                ->on('solicitudes_reemplazo')
                ->cascadeOnDelete();
            $table->foreign('postulant_profile_id', 'srad_postulante_fk')
                ->references('id')
                ->on('postulant_profiles')
                ->nullOnDelete();
            $table->foreign('solicitado_por_user_id', 'srad_solicitado_por_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('numero_registrado_por_user_id', 'srad_numero_por_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('estado_actualizado_por_user_id', 'srad_estado_por_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_reemplazo_autorizaciones_docentes');
        Schema::dropIfExists('solicitud_reemplazo_configuraciones');
    }
};
