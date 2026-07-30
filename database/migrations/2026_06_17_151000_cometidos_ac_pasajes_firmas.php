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
        if (Schema::hasTable('cometidos_funcionarios')) {
            Schema::table('cometidos_funcionarios', function (Blueprint $table) {
                $this->addColumnIfMissing($table, 'origen_cometido', fn () => $table->string('origen_cometido', 40)->default('establecimiento')->after('user_id'));
                $this->addColumnIfMissing($table, 'funcionario_ac_autorizado_id', fn () => $table->unsignedBigInteger('funcionario_ac_autorizado_id')->nullable()->after('reemplazo_personal_id'));
                $this->addColumnIfMissing($table, 'numero_cometido_interno', fn () => $table->string('numero_cometido_interno', 60)->nullable()->after('fecha_solicitud'));
                $this->addColumnIfMissing($table, 'region_origen', fn () => $table->string('region_origen', 20)->nullable()->after('cargo_funcion'));
                $this->addColumnIfMissing($table, 'comuna_origen_id', fn () => $table->unsignedBigInteger('comuna_origen_id')->nullable()->after('region_origen'));
                $this->addColumnIfMissing($table, 'comuna_origen_nombre', fn () => $table->string('comuna_origen_nombre', 255)->nullable()->after('comuna_origen_id'));
                $this->addColumnIfMissing($table, 'es_destino_extranjero', fn () => $table->boolean('es_destino_extranjero')->default(false)->after('comuna_origen_nombre'));
                $this->addColumnIfMissing($table, 'pais_destino', fn () => $table->string('pais_destino', 120)->nullable()->after('es_destino_extranjero'));
                $this->addColumnIfMissing($table, 'ciudad_destino_extranjero', fn () => $table->string('ciudad_destino_extranjero', 160)->nullable()->after('pais_destino'));
                $this->addColumnIfMissing($table, 'subdireccion_dependencia_ac', fn () => $table->string('subdireccion_dependencia_ac', 255)->nullable()->after('ciudad_destino_extranjero'));
                $this->addColumnIfMissing($table, 'unidad_departamento_ac', fn () => $table->string('unidad_departamento_ac', 255)->nullable()->after('subdireccion_dependencia_ac'));
                $this->addColumnIfMissing($table, 'es_jefatura_ac', fn () => $table->boolean('es_jefatura_ac')->default(false)->after('unidad_departamento_ac'));
                $this->addColumnIfMissing($table, 'estado_autorizacion_jefatura_ac', fn () => $table->string('estado_autorizacion_jefatura_ac', 80)->nullable()->after('es_jefatura_ac'));
                $this->addColumnIfMissing($table, 'jefatura_autorizadora_ac_id', fn () => $table->unsignedBigInteger('jefatura_autorizadora_ac_id')->nullable()->after('estado_autorizacion_jefatura_ac'));
                $this->addColumnIfMissing($table, 'jefatura_autorizadora_user_id', fn () => $table->unsignedBigInteger('jefatura_autorizadora_user_id')->nullable()->after('jefatura_autorizadora_ac_id'));
                $this->addColumnIfMissing($table, 'autorizado_por_subrogante', fn () => $table->boolean('autorizado_por_subrogante')->default(false)->after('jefatura_autorizadora_user_id'));
                $this->addColumnIfMissing($table, 'fecha_autorizacion_jefatura_ac', fn () => $table->timestamp('fecha_autorizacion_jefatura_ac')->nullable()->after('autorizado_por_subrogante'));
                $this->addColumnIfMissing($table, 'observacion_jefatura_ac', fn () => $table->text('observacion_jefatura_ac')->nullable()->after('fecha_autorizacion_jefatura_ac'));
                $this->addColumnIfMissing($table, 'requiere_pasaje_aereo', fn () => $table->boolean('requiere_pasaje_aereo')->default(false)->after('observacion_jefatura_ac'));
                $this->addColumnIfMissing($table, 'dias_habiles_anticipacion', fn () => $table->integer('dias_habiles_anticipacion')->nullable()->after('requiere_pasaje_aereo'));
                $this->addColumnIfMissing($table, 'justificacion_menor_7_dias', fn () => $table->text('justificacion_menor_7_dias')->nullable()->after('dias_habiles_anticipacion'));
            });
        }

        if (! Schema::hasTable('cometido_funcionario_documentos_generados')) {
            Schema::create('cometido_funcionario_documentos_generados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cometido_funcionario_id');
                $table->string('tipo_documento', 80);
                $table->string('numero_documento', 80)->nullable();
                $table->string('codigo_validacion', 80);
                $table->unique('codigo_validacion', 'cfg_doc_codigo_uq');
                $table->string('token_validacion', 120);
                $table->unique('token_validacion', 'cfg_doc_token_uq');
                $table->string('documento_hash', 128)->nullable();
                $table->string('archivo_pdf_path', 500)->nullable();
                $table->string('estado', 40)->default('vigente');
                $table->unsignedBigInteger('emitido_por_user_id')->nullable();
                $table->timestamp('emitido_at')->nullable();
                $table->timestamps();
                $table->index(['cometido_funcionario_id', 'tipo_documento'], 'cfg_doc_com_tipo_idx');
            });
        } elseif (Schema::hasTable('cometido_funcionario_documentos_generados')) {
            $this->addUniqueIndexIfMissing('cometido_funcionario_documentos_generados', 'codigo_validacion', 'cfg_doc_codigo_uq');
            $this->addUniqueIndexIfMissing('cometido_funcionario_documentos_generados', 'token_validacion', 'cfg_doc_token_uq');
        }

        if (! Schema::hasTable('cometido_funcionario_firmas')) {
            Schema::create('cometido_funcionario_firmas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cometido_funcionario_id');
                $table->unsignedBigInteger('documento_generado_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('funcionario_ac_autorizado_id')->nullable();
                $table->string('tipo_firma', 80);
                $table->string('rol_firmante', 80)->nullable();
                $table->string('nombre_firmante', 255)->nullable();
                $table->string('rut_firmante', 40)->nullable();
                $table->string('cargo_firmante', 255)->nullable();
                $table->string('dependencia_firmante', 255)->nullable();
                $table->boolean('es_subrogante')->default(false);
                $table->string('ip_firma', 80)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('fecha_firma')->nullable();
                $table->string('token_firma', 120);
                $table->unique('token_firma', 'cfg_firma_token_uq');
                $table->string('hash_firma', 128)->nullable();
                $table->timestamps();
                $table->index(['cometido_funcionario_id', 'tipo_firma'], 'cfg_firma_tipo_idx');
            });
        } elseif (Schema::hasTable('cometido_funcionario_firmas')) {
            $this->addUniqueIndexIfMissing('cometido_funcionario_firmas', 'token_firma', 'cfg_firma_token_uq');
        }

        if (! Schema::hasTable('cometido_funcionario_pasajes_aereos')) {
            Schema::create('cometido_funcionario_pasajes_aereos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cometido_funcionario_id');
                $table->unique('cometido_funcionario_id', 'cfg_pasaje_cometido_uq');
                $table->string('numero_solicitud_pedido', 80)->nullable();
                $table->unique('numero_solicitud_pedido', 'cfg_pasaje_num_sp_uq');
                $table->string('solicitud_pedido_pdf_path', 500)->nullable();
                $table->string('estado_pasaje', 80)->default('pendiente_reserva');
                $table->unsignedBigInteger('reserva_usuario_id')->nullable();
                $table->string('reserva_archivo_path', 500)->nullable();
                $table->string('reserva_nombre_original', 255)->nullable();
                $table->timestamp('reserva_fecha')->nullable();
                $table->text('reserva_observacion')->nullable();
                $table->unsignedBigInteger('cdp_usuario_id')->nullable();
                $table->string('cdp_referencia', 255)->nullable();
                $table->date('cdp_fecha')->nullable();
                $table->string('cdp_archivo_path', 500)->nullable();
                $table->string('cdp_nombre_original', 255)->nullable();
                $table->text('cdp_observacion')->nullable();
                $table->unsignedBigInteger('compra_usuario_id')->nullable();
                $table->string('proveedor', 255)->nullable();
                $table->unsignedBigInteger('monto')->nullable();
                $table->date('fecha_compra')->nullable();
                $table->string('numero_oc', 100)->nullable();
                $table->string('compra_archivo_path', 500)->nullable();
                $table->string('compra_nombre_original', 255)->nullable();
                $table->text('compra_observacion')->nullable();
                $table->timestamp('boleto_disponible_at')->nullable();
                $table->timestamp('notificado_funcionario_at')->nullable();
                $table->timestamps();
            });
        } elseif (Schema::hasTable('cometido_funcionario_pasajes_aereos')) {
            $this->addUniqueIndexIfMissing('cometido_funcionario_pasajes_aereos', 'cometido_funcionario_id', 'cfg_pasaje_cometido_uq');
            $this->addUniqueIndexIfMissing('cometido_funcionario_pasajes_aereos', 'numero_solicitud_pedido', 'cfg_pasaje_num_sp_uq');
        }

        if (Schema::hasTable('roles')) {
            Role::findOrCreate('funcionario_daf_compra', 'web');
        }

        if (Schema::hasTable('modules') && Schema::hasTable('module_role') && Schema::hasTable('roles')) {
            $moduleId = DB::table('modules')->where('key', 'tramites.cometidos-funcionarios')->value('id')
                ?: DB::table('modules')->where('key', 'tramites.cometidos-funcionarios.index')->value('id');
            if ($moduleId) {
                $roleIds = DB::table('roles')->whereIn('name', ['funcionario_ac', 'funcionario_daf_compra'])->pluck('id');
                foreach ($roleIds as $roleId) {
                    if (! DB::table('module_role')->where('module_id', $moduleId)->where('role_id', $roleId)->exists()) {
                        DB::table('module_role')->insert([
                            'module_id' => $moduleId,
                            'role_id' => $roleId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cometido_funcionario_pasajes_aereos');
        Schema::dropIfExists('cometido_funcionario_firmas');
        Schema::dropIfExists('cometido_funcionario_documentos_generados');

        if (Schema::hasTable('cometidos_funcionarios')) {
            Schema::table('cometidos_funcionarios', function (Blueprint $table) {
                foreach ([
                    'origen_cometido', 'funcionario_ac_autorizado_id', 'numero_cometido_interno', 'region_origen', 'comuna_origen_id', 'comuna_origen_nombre',
                    'es_destino_extranjero', 'pais_destino', 'ciudad_destino_extranjero', 'subdireccion_dependencia_ac', 'unidad_departamento_ac', 'es_jefatura_ac',
                    'estado_autorizacion_jefatura_ac', 'jefatura_autorizadora_ac_id', 'jefatura_autorizadora_user_id', 'autorizado_por_subrogante',
                    'fecha_autorizacion_jefatura_ac', 'observacion_jefatura_ac', 'requiere_pasaje_aereo', 'dias_habiles_anticipacion', 'justificacion_menor_7_dias',
                ] as $column) {
                    if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function addColumnIfMissing(Blueprint $table, string $column, callable $definition): void
    {
        if (! Schema::hasColumn('cometidos_funcionarios', $column)) {
            $definition();
        }
    }

    private function addUniqueIndexIfMissing(string $tableName, string $columnName, string $indexName): void
    {
        if (! Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        $exists = collect(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]))->isNotEmpty();

        if (! $exists) {
            Schema::table($tableName, function (Blueprint $table) use ($columnName, $indexName) {
                $table->unique($columnName, $indexName);
            });
        }
    }
};
