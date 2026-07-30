<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_referencia')) {
                $table->string('cdp_referencia')->nullable()->after('cdp_aprobado');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_catalogo_valor_id')) {
                $table->foreignId('cdp_catalogo_valor_id')->nullable()->after('cdp_observacion')->constrained('viaticos_reembolsos_valores')->nullOnDelete();
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_estamento')) {
                $table->string('cdp_estamento', 50)->nullable()->after('cdp_catalogo_valor_id');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_cargo_funcion')) {
                $table->string('cdp_cargo_funcion')->nullable()->after('cdp_estamento');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_viatico_total')) {
                $table->unsignedInteger('cdp_viatico_total')->nullable()->after('cdp_cargo_funcion');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_reembolso_total_maximo')) {
                $table->unsignedInteger('cdp_reembolso_total_maximo')->nullable()->after('cdp_viatico_total');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_monto_total')) {
                $table->unsignedInteger('cdp_monto_total')->nullable()->after('cdp_reembolso_total_maximo');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_monto_asignado_at')) {
                $table->timestamp('cdp_monto_asignado_at')->nullable()->after('cdp_monto_total');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'cdp_monto_asignado_by')) {
                $table->foreignId('cdp_monto_asignado_by')->nullable()->after('cdp_monto_asignado_at')->constrained('users')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('cometido_funcionario_cdp_montos')) {
            Schema::create('cometido_funcionario_cdp_montos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cometido_funcionario_id')->constrained('cometidos_funcionarios')->cascadeOnDelete();
                $table->string('tipo', 20);
                $table->date('fecha');
                $table->unsignedSmallInteger('dia_numero');
                $table->unsignedSmallInteger('porcentaje');
                $table->unsignedInteger('valor_diario');
                $table->unsignedInteger('monto');
                $table->foreignId('catalogo_valor_id')->nullable()->constrained('viaticos_reembolsos_valores')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['cometido_funcionario_id', 'tipo', 'fecha'], 'cf_cdp_montos_unique_dia_tipo');
                $table->index(['cometido_funcionario_id', 'tipo', 'dia_numero'], 'cf_cdp_montos_cometido_tipo_dia_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cometido_funcionario_cdp_montos');

        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            foreach (['cdp_catalogo_valor_id', 'cdp_monto_asignado_by'] as $column) {
                if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach ([
                'cdp_referencia',
                'cdp_estamento',
                'cdp_cargo_funcion',
                'cdp_viatico_total',
                'cdp_reembolso_total_maximo',
                'cdp_monto_total',
                'cdp_monto_asignado_at',
            ] as $column) {
                if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
