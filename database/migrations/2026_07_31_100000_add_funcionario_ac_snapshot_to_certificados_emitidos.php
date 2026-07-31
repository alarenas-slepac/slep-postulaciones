<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('certificados_emitidos')
            && ! Schema::hasColumn('certificados_emitidos', 'es_funcionario_ac_snapshot')
        ) {
            Schema::table('certificados_emitidos', function (Blueprint $table) {
                $table->boolean('es_funcionario_ac_snapshot')
                    ->default(false)
                    ->after('contratos_snapshot');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('certificados_emitidos')
            && Schema::hasColumn('certificados_emitidos', 'es_funcionario_ac_snapshot')
        ) {
            Schema::table('certificados_emitidos', function (Blueprint $table) {
                $table->dropColumn('es_funcionario_ac_snapshot');
            });
        }
    }
};
