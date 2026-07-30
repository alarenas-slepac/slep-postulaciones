<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cometidos_funcionarios') && ! Schema::hasColumn('cometidos_funcionarios', 'tipo_pasaje_aereo')) {
            Schema::table('cometidos_funcionarios', function (Blueprint $table) {
                $table->string('tipo_pasaje_aereo', 40)->nullable()->after('requiere_pasaje_aereo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cometidos_funcionarios') && Schema::hasColumn('cometidos_funcionarios', 'tipo_pasaje_aereo')) {
            Schema::table('cometidos_funcionarios', function (Blueprint $table) {
                $table->dropColumn('tipo_pasaje_aereo');
            });
        }
    }
};
