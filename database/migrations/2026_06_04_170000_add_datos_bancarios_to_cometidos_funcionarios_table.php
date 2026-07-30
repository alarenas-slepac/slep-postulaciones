<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (!Schema::hasColumn('cometidos_funcionarios', 'banco_pago')) {
                $table->string('banco_pago', 120)->nullable()->after('contempla_alojamiento');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'tipo_cuenta_pago')) {
                $table->string('tipo_cuenta_pago', 80)->nullable()->after('banco_pago');
            }
            if (!Schema::hasColumn('cometidos_funcionarios', 'numero_cuenta_pago')) {
                $table->string('numero_cuenta_pago', 40)->nullable()->after('tipo_cuenta_pago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            foreach (['numero_cuenta_pago', 'tipo_cuenta_pago', 'banco_pago'] as $column) {
                if (Schema::hasColumn('cometidos_funcionarios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
