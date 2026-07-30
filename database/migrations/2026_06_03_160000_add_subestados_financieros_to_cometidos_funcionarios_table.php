<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios', 'estado_viatico')) {
                $table->string('estado_viatico', 60)->nullable()->after('estado')->index();
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'estado_reembolso')) {
                $table->string('estado_reembolso', 60)->nullable()->after('estado_viatico')->index();
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'viatico_finalizado_at')) {
                $table->timestamp('viatico_finalizado_at')->nullable()->after('fecha_pago_viatico');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'reembolso_finalizado_at')) {
                $table->timestamp('reembolso_finalizado_at')->nullable()->after('viatico_finalizado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            foreach (['estado_viatico', 'estado_reembolso', 'viatico_finalizado_at', 'reembolso_finalizado_at'] as $column) {
                if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
