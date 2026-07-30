<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios', 'solicita_anticipo_viatico')) {
                $table->boolean('solicita_anticipo_viatico')->default(false)->after('servicio_contempla_colacion');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'porcentaje_anticipo_viatico')) {
                $table->unsignedTinyInteger('porcentaje_anticipo_viatico')->nullable()->after('solicita_anticipo_viatico');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'monto_anticipo_viatico')) {
                $table->unsignedBigInteger('monto_anticipo_viatico')->nullable()->after('porcentaje_anticipo_viatico');
            }
            if (! Schema::hasColumn('cometidos_funcionarios', 'monto_saldo_viatico')) {
                $table->unsignedBigInteger('monto_saldo_viatico')->nullable()->after('monto_anticipo_viatico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            foreach (['monto_saldo_viatico', 'monto_anticipo_viatico', 'porcentaje_anticipo_viatico', 'solicita_anticipo_viatico'] as $column) {
                if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
