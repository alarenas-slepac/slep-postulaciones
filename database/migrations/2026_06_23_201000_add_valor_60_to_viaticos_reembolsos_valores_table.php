<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('viaticos_reembolsos_valores', 'valor_60')) {
            Schema::table('viaticos_reembolsos_valores', function (Blueprint $table) {
                $table->unsignedInteger('valor_60')->nullable()->after('valor_100');
            });
        }

        DB::table('viaticos_reembolsos_valores')
            ->whereNull('valor_60')
            ->whereNotNull('valor_100')
            ->update(['valor_60' => DB::raw('ROUND(valor_100 * 0.60)')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('viaticos_reembolsos_valores', 'valor_60')) {
            Schema::table('viaticos_reembolsos_valores', function (Blueprint $table) {
                $table->dropColumn('valor_60');
            });
        }
    }
};
