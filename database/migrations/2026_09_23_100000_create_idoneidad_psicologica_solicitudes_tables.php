<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idoneidad_psicologica_solicitudes')) {
            Schema::create('idoneidad_psicologica_solicitudes', function (Blueprint $table): void {
                $table->id();
                $table->date('fecha_inicio')->index();
                $table->date('fecha_termino')->index();
                $table->string('estado', 30)->default('solicitada')->index();
                $table->text('observacion')->nullable();
                $table->foreignId('solicitada_por')->nullable();
                $table->timestamp('solicitada_at')->nullable();
                $table->foreignId('actualizada_por')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('idoneidad_psicologica_funcionarios')) {
            Schema::create('idoneidad_psicologica_funcionarios', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('solicitud_id');
                $table->unsignedBigInteger('reemplazo_personal_id')->nullable();
                $table->unsignedBigInteger('establecimiento_id')->nullable();
                $table->unsignedInteger('rbd')->nullable();
                $table->string('establecimiento_nombre')->nullable();
                $table->string('comuna')->nullable();
                $table->string('rut', 20)->index();
                $table->string('rut_normalizado', 20)->index();
                $table->string('nombre');
                $table->string('estamento')->nullable();
                $table->string('cargo_funcion')->nullable();
                $table->string('cargo_clave')->nullable();
                $table->string('cargo_origen')->nullable();
                $table->unsignedBigInteger('solicitud_reemplazo_id')->nullable();
                $table->string('tipo_contrato')->nullable();
                $table->date('fecha_ingreso')->nullable();
                $table->date('fecha_termino')->nullable();
                $table->string('estado', 20)->default('solicitado')->index();
                $table->text('observacion_resultado')->nullable();
                $table->foreignId('resultado_registrado_por')->nullable();
                $table->timestamp('resultado_registrado_at')->nullable();
                $table->timestamps();

                $table->unique(['solicitud_id', 'establecimiento_id', 'rut_normalizado'], 'idops_sol_est_rut_unique');
                $table->index(['rut_normalizado', 'cargo_clave'], 'idopf_rut_cargo_idx');
            });
        }

        $this->ensureForeignKey('idoneidad_psicologica_solicitudes', 'solicitada_por', 'users', 'idops_sol_by_fk');
        $this->ensureForeignKey('idoneidad_psicologica_solicitudes', 'actualizada_por', 'users', 'idops_upd_by_fk');
        $this->ensureForeignKey('idoneidad_psicologica_funcionarios', 'solicitud_id', 'idoneidad_psicologica_solicitudes', 'idopf_sol_fk', true);
        $this->ensureForeignKey('idoneidad_psicologica_funcionarios', 'reemplazo_personal_id', 'reemplazos_personal', 'idopf_padron_fk');
        $this->ensureForeignKey('idoneidad_psicologica_funcionarios', 'establecimiento_id', 'establecimientos', 'idopf_est_fk');
        $this->ensureForeignKey('idoneidad_psicologica_funcionarios', 'solicitud_reemplazo_id', 'solicitudes_reemplazo', 'idopf_solrep_fk');
        $this->ensureForeignKey('idoneidad_psicologica_funcionarios', 'resultado_registrado_por', 'users', 'idopf_res_by_fk');
    }

    public function down(): void
    {
        Schema::dropIfExists('idoneidad_psicologica_funcionarios');
        Schema::dropIfExists('idoneidad_psicologica_solicitudes');
    }

    private function ensureForeignKey(string $tableName, string $column, string $references, string $name, bool $cascade = false): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasTable($references) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        $exists = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === [$column]);
        if ($exists) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $references, $name, $cascade): void {
            $foreign = $table->foreign($column, $name)->references('id')->on($references);
            $cascade ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
        });
    }
};
