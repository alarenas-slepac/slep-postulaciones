<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios')) {
            return;
        }

        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios', 'monto_pagado_viatico')) {
                $table->unsignedInteger('monto_pagado_viatico')->nullable()->after('fecha_pago_viatico');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'documento_pago_viatico_path')) {
                $table->string('documento_pago_viatico_path')->nullable()->after('monto_pagado_viatico');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'observacion_pago_viatico')) {
                $table->text('observacion_pago_viatico')->nullable()->after('documento_pago_viatico_path');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'usuario_pago_viatico_id')) {
                $table->unsignedBigInteger('usuario_pago_viatico_id')->nullable()->after('observacion_pago_viatico');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'fecha_registro_pago_viatico')) {
                $table->timestamp('fecha_registro_pago_viatico')->nullable()->after('usuario_pago_viatico_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios')) {
            return;
        }

        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            foreach ([
                'monto_pagado_viatico',
                'documento_pago_viatico_path',
                'observacion_pago_viatico',
                'usuario_pago_viatico_id',
                'fecha_registro_pago_viatico',
            ] as $column) {
                if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
