<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descuentos_cgr', function (Blueprint $table): void {
            $table->string('estado', 32)->default('ingresado')->index();
            $table->string('institucion_reintegro')->nullable();
            $table->string('estamento_funcionario')->nullable();
            $table->timestamp('enviado_finanzas_en')->nullable();
            $table->timestamp('enviado_auditoria_en')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamp('certificado_generado_en')->nullable();
            $table->foreignId('certificado_generado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificado_firmado_path')->nullable();
            $table->string('certificado_firmado_nombre')->nullable();
            $table->timestamp('certificado_firmado_en')->nullable();
            $table->foreignId('certificado_firmado_por_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('descuentos_cgr_archivos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('descuento_cgr_id')->constrained('descuentos_cgr')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_cuota');
            $table->string('tipo', 32);
            $table->uuid('grupo_archivo');
            $table->string('path');
            $table->string('nombre_original');
            $table->unsignedBigInteger('tamano');
            $table->string('folio')->nullable();
            $table->date('fecha_reintegro')->nullable();
            $table->unsignedBigInteger('monto_reintegro_pesos')->nullable();
            $table->foreignId('cargado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['descuento_cgr_id', 'numero_cuota', 'tipo'], 'dcgr_archivo_cuota_tipo_unique');
            $table->index(['descuento_cgr_id', 'tipo']);
        });

        Schema::create('descuentos_cgr_notificaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('evento', 32)->unique();
            $table->text('correos_adicionales')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('roles')) {
            $rol = Role::findOrCreate('auditoria_slep', 'web');
            if (Schema::hasTable('modules') && Schema::hasTable('module_role')) {
                $modulo = DB::table('modules')->where('key', 'descuentos-cgr')->value('id');
                if ($modulo) {
                    $conTimestamps = Schema::hasColumn('module_role', 'created_at');
                    foreach (['funcionario_daf', 'auditoria_slep'] as $nombre) {
                        $roleId = $nombre === 'auditoria_slep' ? $rol->id : DB::table('roles')->where('name', $nombre)->value('id');
                        if ($roleId && ! DB::table('module_role')->where('module_id', $modulo)->where('role_id', $roleId)->exists()) {
                            DB::table('module_role')->insert(['module_id' => $modulo, 'role_id' => $roleId] + ($conTimestamps ? ['created_at' => now(), 'updated_at' => now()] : []));
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('descuentos_cgr_notificaciones');
        Schema::dropIfExists('descuentos_cgr_archivos');
        Schema::table('descuentos_cgr', function (Blueprint $table): void {
            $table->dropIndex(['estado']);
            $table->dropForeign(['certificado_firmado_por_id']);
            $table->dropForeign(['certificado_generado_por_id']);
            $table->dropColumn(['estado', 'institucion_reintegro', 'estamento_funcionario', 'enviado_finanzas_en', 'enviado_auditoria_en', 'cerrado_en', 'certificado_generado_en', 'certificado_generado_por_id', 'certificado_firmado_path', 'certificado_firmado_nombre', 'certificado_firmado_en', 'certificado_firmado_por_id']);
        });
    }
};
