<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cometido_funcionario_rendiciones')) {
            return;
        }

        Schema::table('cometido_funcionario_rendiciones', function (Blueprint $table) {
            if (! Schema::hasColumn('cometido_funcionario_rendiciones', 'monto_cdp_reembolso')) {
                $table->unsignedInteger('monto_cdp_reembolso')->nullable()->after('monto_autorizado_daf');
            }
            if (! Schema::hasColumn('cometido_funcionario_rendiciones', 'referencia_cdp_reembolso')) {
                $table->string('referencia_cdp_reembolso', 150)->nullable()->after('monto_cdp_reembolso');
            }
            if (! Schema::hasColumn('cometido_funcionario_rendiciones', 'documento_cdp_reembolso_path')) {
                $table->string('documento_cdp_reembolso_path')->nullable()->after('documento_daf_path');
            }
            if (! Schema::hasColumn('cometido_funcionario_rendiciones', 'observacion_cdp')) {
                $table->text('observacion_cdp')->nullable()->after('observacion_daf');
            }
            if (! Schema::hasColumn('cometido_funcionario_rendiciones', 'fecha_revision_cdp')) {
                $table->timestamp('fecha_revision_cdp')->nullable()->after('fecha_revision_daf');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cometido_funcionario_rendiciones')) {
            return;
        }

        Schema::table('cometido_funcionario_rendiciones', function (Blueprint $table) {
            foreach ([
                'fecha_revision_cdp',
                'observacion_cdp',
                'documento_cdp_reembolso_path',
                'referencia_cdp_reembolso',
                'monto_cdp_reembolso',
            ] as $column) {
                if (Schema::hasColumn('cometido_funcionario_rendiciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
