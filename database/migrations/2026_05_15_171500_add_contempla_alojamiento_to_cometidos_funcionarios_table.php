<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cometidos_funcionarios')) {
            return;
        }

        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (!Schema::hasColumn('cometidos_funcionarios', 'contempla_alojamiento')) {
                $table->boolean('contempla_alojamiento')->default(false)->after('solicita_reembolso');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cometidos_funcionarios')) {
            return;
        }

        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (Schema::hasColumn('cometidos_funcionarios', 'contempla_alojamiento')) {
                $table->dropColumn('contempla_alojamiento');
            }
        });
    }
};
