<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('reemplazos_personal', 'tramo')) {
            Schema::table('reemplazos_personal', function (Blueprint $table) {
                $table->string('tramo', 100)->nullable()->after('bienios');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reemplazos_personal', 'tramo')) {
            Schema::table('reemplazos_personal', function (Blueprint $table) {
                $table->dropColumn('tramo');
            });
        }
    }
};
