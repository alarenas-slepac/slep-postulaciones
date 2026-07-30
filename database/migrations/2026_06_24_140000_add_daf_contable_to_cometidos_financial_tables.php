<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cometidos_funcionarios')) {
            Schema::table('cometidos_funcionarios', function (Blueprint $table) {
                if (! Schema::hasColumn('cometidos_funcionarios', 'folio_compromiso_viatico')) {
                    $table->string('folio_compromiso_viatico', 100)->nullable()->after('fecha_pago_viatico');
                }
                if (! Schema::hasColumn('cometidos_funcionarios', 'fecha_compromiso_viatico')) {
                    $table->date('fecha_compromiso_viatico')->nullable()->after('folio_compromiso_viatico');
                }
                if (! Schema::hasColumn('cometidos_funcionarios', 'folio_devengo_viatico')) {
                    $table->string('folio_devengo_viatico', 100)->nullable()->after('fecha_compromiso_viatico');
                }
                if (! Schema::hasColumn('cometidos_funcionarios', 'fecha_devengo_viatico')) {
                    $table->date('fecha_devengo_viatico')->nullable()->after('folio_devengo_viatico');
                }
                if (! Schema::hasColumn('cometidos_funcionarios', 'documento_contable_viatico_path')) {
                    $table->string('documento_contable_viatico_path')->nullable()->after('fecha_devengo_viatico');
                }
                if (! Schema::hasColumn('cometidos_funcionarios', 'observacion_contable_viatico')) {
                    $table->text('observacion_contable_viatico')->nullable()->after('documento_contable_viatico_path');
                }
                if (! Schema::hasColumn('cometidos_funcionarios', 'daf_contable_viatico_user_id')) {
                    $table->unsignedBigInteger('daf_contable_viatico_user_id')->nullable()->after('observacion_contable_viatico');
                }
                if (! Schema::hasColumn('cometidos_funcionarios', 'daf_contable_viatico_at')) {
                    $table->timestamp('daf_contable_viatico_at')->nullable()->after('daf_contable_viatico_user_id');
                }
            });
        }

        if (Schema::hasTable('cometidos_funcionarios_resoluciones_reembolso')) {
            Schema::table('cometidos_funcionarios_resoluciones_reembolso', function (Blueprint $table) {
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'folio_compromiso_contable')) {
                    $table->string('folio_compromiso_contable', 100)->nullable()->after('fecha_emision_resolucion');
                }
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'fecha_compromiso_contable')) {
                    $table->date('fecha_compromiso_contable')->nullable()->after('folio_compromiso_contable');
                }
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'folio_devengo_contable')) {
                    $table->string('folio_devengo_contable', 100)->nullable()->after('fecha_compromiso_contable');
                }
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'fecha_devengo_contable')) {
                    $table->date('fecha_devengo_contable')->nullable()->after('folio_devengo_contable');
                }
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'documento_contable_path')) {
                    $table->string('documento_contable_path')->nullable()->after('fecha_devengo_contable');
                }
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'observacion_contable')) {
                    $table->text('observacion_contable')->nullable()->after('documento_contable_path');
                }
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'usuario_contable_id')) {
                    $table->unsignedBigInteger('usuario_contable_id')->nullable()->after('observacion_contable');
                }
                if (! Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', 'fecha_registro_contable')) {
                    $table->timestamp('fecha_registro_contable')->nullable()->after('usuario_contable_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cometidos_funcionarios')) {
            Schema::table('cometidos_funcionarios', function (Blueprint $table) {
                foreach ([
                    'folio_compromiso_viatico',
                    'fecha_compromiso_viatico',
                    'folio_devengo_viatico',
                    'fecha_devengo_viatico',
                    'documento_contable_viatico_path',
                    'observacion_contable_viatico',
                    'daf_contable_viatico_user_id',
                    'daf_contable_viatico_at',
                ] as $column) {
                    if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('cometidos_funcionarios_resoluciones_reembolso')) {
            Schema::table('cometidos_funcionarios_resoluciones_reembolso', function (Blueprint $table) {
                foreach ([
                    'folio_compromiso_contable',
                    'fecha_compromiso_contable',
                    'folio_devengo_contable',
                    'fecha_devengo_contable',
                    'documento_contable_path',
                    'observacion_contable',
                    'usuario_contable_id',
                    'fecha_registro_contable',
                ] as $column) {
                    if (Schema::hasColumn('cometidos_funcionarios_resoluciones_reembolso', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
