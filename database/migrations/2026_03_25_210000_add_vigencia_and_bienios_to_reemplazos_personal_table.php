<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reemplazos_personal', function (Blueprint $table) {
            if (!Schema::hasColumn('reemplazos_personal', 'bienios')) {
                $table->unsignedSmallInteger('bienios')->nullable()->after('jornada_media');
            }

            if (!Schema::hasColumn('reemplazos_personal', 'vigente')) {
                $table->boolean('vigente')->default(true)->after('bienios');
                $table->index('vigente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reemplazos_personal', function (Blueprint $table) {
            if (Schema::hasColumn('reemplazos_personal', 'vigente')) {
                $table->dropIndex(['vigente']);
                $table->dropColumn('vigente');
            }

            if (Schema::hasColumn('reemplazos_personal', 'bienios')) {
                $table->dropColumn('bienios');
            }
        });
    }
};
