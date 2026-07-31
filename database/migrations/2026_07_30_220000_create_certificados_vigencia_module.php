<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificado_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->string('ruta_archivo', 500);
            $table->char('hash_archivo', 64)->unique();
            $table->string('estado', 40)->default('pendiente')->index();
            $table->boolean('es_vigente')->default(false)->index();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_omitidas')->default(0);
            $table->unsignedInteger('filas_duplicadas')->default(0);
            $table->json('errores')->nullable();
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('procesado_at')->nullable();
            $table->timestamp('activado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificado_contratos_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')
                ->constrained('certificado_importaciones')
                ->cascadeOnDelete();
            $table->unsignedInteger('fila_origen');
            $table->string('rut_normalizado', 16);
            $table->string('nombre');
            $table->string('establecimiento');
            $table->string('comuna', 160);
            $table->date('fecha_ingreso');
            $table->date('fecha_finiquito')->nullable();
            $table->boolean('termino_indefinido')->default(false);
            $table->string('calidad_juridica', 160);
            $table->string('regimen_juridico', 500);
            $table->char('row_hash', 64);
            $table->timestamps();

            $table->unique(['importacion_id', 'row_hash'], 'cert_contrato_import_hash_uq');
            $table->index(
                ['importacion_id', 'rut_normalizado', 'fecha_ingreso'],
                'cert_contrato_import_rut_ing_idx'
            );
            $table->index(
                ['importacion_id', 'rut_normalizado', 'fecha_finiquito'],
                'cert_contrato_import_rut_fin_idx'
            );
        });

        Schema::create('certificados_emitidos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 60)->default('vigencia_laboral');
            $table->string('numero', 80)->nullable()->unique();
            $table->char('codigo_validacion', 32)->unique();
            $table->string('rut_normalizado', 16)->index();
            $table->string('nombre_snapshot');
            $table->date('fecha_antiguedad');
            $table->string('calidad_juridica_snapshot', 500)->nullable();
            $table->string('regimen_juridico_snapshot', 500);
            $table->json('establecimientos_snapshot');
            $table->json('contratos_snapshot');
            $table->foreignId('importacion_id')
                ->nullable()
                ->constrained('certificado_importaciones')
                ->nullOnDelete();
            $table->foreignId('usuario_beneficiario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('emitido_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('rol_emisor', 80)->nullable();
            $table->string('estado', 30)->default('vigente')->index();
            $table->string('archivo_pdf_path', 500)->nullable();
            $table->char('documento_hash', 64)->nullable();
            $table->timestamp('emitido_at');
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('modules')) {
            $now = now();
            DB::table('modules')->updateOrInsert(
                ['key' => 'certificados'],
                [
                    'name' => 'Certificados',
                    'section' => 'Trámites',
                    'icon' => 'bi-file-earmark-check',
                    'sort' => 39,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            if (Schema::hasTable('module_role') && Schema::hasTable('roles')) {
                $moduleId = DB::table('modules')->where('key', 'certificados')->value('id');
                $roleIds = DB::table('roles')
                    ->where('guard_name', 'web')
                    ->whereIn('name', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario'])
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    DB::table('module_role')->insertOrIgnore([
                        'module_id' => $moduleId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modules')) {
            $moduleId = DB::table('modules')->where('key', 'certificados')->value('id');
            if ($moduleId && Schema::hasTable('module_role')) {
                DB::table('module_role')->where('module_id', $moduleId)->delete();
            }
            if ($moduleId) {
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        }

        Schema::dropIfExists('certificados_emitidos');
        Schema::dropIfExists('certificado_contratos_historicos');
        Schema::dropIfExists('certificado_importaciones');
    }
};
