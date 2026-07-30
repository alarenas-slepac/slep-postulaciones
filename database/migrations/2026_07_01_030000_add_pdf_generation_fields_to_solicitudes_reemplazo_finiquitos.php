<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'finiquito_estado', fn () => $table->string('finiquito_estado', 40)->nullable()->after('finiquito_observacion'));
            $this->addColumnIfMissing($table, 'finiquito_monto', fn () => $table->unsignedInteger('finiquito_monto')->nullable()->after('finiquito_estado'));
            $this->addColumnIfMissing($table, 'finiquito_fecha_emision', fn () => $table->date('finiquito_fecha_emision')->nullable()->after('finiquito_monto'));
            $this->addColumnIfMissing($table, 'finiquito_pdf_path', fn () => $table->string('finiquito_pdf_path')->nullable()->after('finiquito_fecha_emision'));
            $this->addColumnIfMissing($table, 'finiquito_generado_por_user_id', fn () => $table->unsignedBigInteger('finiquito_generado_por_user_id')->nullable()->after('finiquito_pdf_path'));
            $this->addColumnIfMissing($table, 'finiquito_generado_at', fn () => $table->timestamp('finiquito_generado_at')->nullable()->after('finiquito_generado_por_user_id'));
            $this->addColumnIfMissing($table, 'finiquito_firmante_nombre', fn () => $table->string('finiquito_firmante_nombre')->nullable()->after('finiquito_generado_at'));
            $this->addColumnIfMissing($table, 'finiquito_firmante_rut', fn () => $table->string('finiquito_firmante_rut', 20)->nullable()->after('finiquito_firmante_nombre'));
            $this->addColumnIfMissing($table, 'finiquito_firmante_cargo', fn () => $table->string('finiquito_firmante_cargo')->nullable()->after('finiquito_firmante_rut'));
            $this->addColumnIfMissing($table, 'finiquito_firmante_es_subrogante', fn () => $table->boolean('finiquito_firmante_es_subrogante')->default(false)->after('finiquito_firmante_cargo'));
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach ([
                'finiquito_firmante_es_subrogante',
                'finiquito_firmante_cargo',
                'finiquito_firmante_rut',
                'finiquito_firmante_nombre',
                'finiquito_generado_at',
                'finiquito_generado_por_user_id',
                'finiquito_pdf_path',
                'finiquito_fecha_emision',
                'finiquito_monto',
                'finiquito_estado',
            ] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addColumnIfMissing(Blueprint $table, string $column, callable $definition): void
    {
        if (! Schema::hasColumn('solicitudes_reemplazo', $column)) {
            $definition();
        }
    }
};
