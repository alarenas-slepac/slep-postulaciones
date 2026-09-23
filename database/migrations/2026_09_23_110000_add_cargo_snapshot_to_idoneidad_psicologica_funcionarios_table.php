<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idoneidad_psicologica_funcionarios')) {
            return;
        }

        Schema::table('idoneidad_psicologica_funcionarios', function (Blueprint $table): void {
            if (! Schema::hasColumn('idoneidad_psicologica_funcionarios', 'cargo_clave')) {
                $table->string('cargo_clave')->nullable()->after('cargo_funcion');
            }
            if (! Schema::hasColumn('idoneidad_psicologica_funcionarios', 'cargo_origen')) {
                $table->string('cargo_origen')->nullable()->after('cargo_clave');
            }
            if (! Schema::hasColumn('idoneidad_psicologica_funcionarios', 'solicitud_reemplazo_id')) {
                $table->unsignedBigInteger('solicitud_reemplazo_id')->nullable()->after('cargo_origen');
            }
        });

        if (! Schema::hasIndex('idoneidad_psicologica_funcionarios', 'idopf_rut_cargo_idx')) {
            Schema::table('idoneidad_psicologica_funcionarios', function (Blueprint $table): void {
                $table->index(['rut_normalizado', 'cargo_clave'], 'idopf_rut_cargo_idx');
            });
        }

        if (Schema::hasTable('solicitudes_reemplazo')) {
            $this->ensureForeignKey();
        }
    }

    public function down(): void
    {
        // Se conservan las fotografías históricas de cargo ya generadas.
    }

    private function ensureForeignKey(): void
    {
        $exists = collect(Schema::getForeignKeys('idoneidad_psicologica_funcionarios'))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['columns'] ?? []) === ['solicitud_reemplazo_id']);
        if ($exists) {
            return;
        }

        Schema::table('idoneidad_psicologica_funcionarios', function (Blueprint $table): void {
            $table->foreign('solicitud_reemplazo_id', 'idopf_solrep_fk')
                ->references('id')
                ->on('solicitudes_reemplazo')
                ->nullOnDelete();
        });
    }
};
