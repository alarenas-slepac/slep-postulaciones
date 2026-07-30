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
            if (! Schema::hasColumn('cometidos_funcionarios', 'folio_tesoreria_viatico')) {
                $table->string('folio_tesoreria_viatico', 100)->nullable()->after('monto_pagado_viatico');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios')) {
            return;
        }

        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (Schema::hasColumn('cometidos_funcionarios', 'folio_tesoreria_viatico')) {
                $table->dropColumn('folio_tesoreria_viatico');
            }
        });
    }
};
