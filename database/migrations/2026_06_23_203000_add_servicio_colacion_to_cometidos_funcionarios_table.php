<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('cometidos_funcionarios', 'servicio_contempla_colacion')) {
                $table->string('servicio_contempla_colacion', 20)->default('no_informado')->after('contempla_alojamiento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            if (Schema::hasColumn('cometidos_funcionarios', 'servicio_contempla_colacion')) {
                $table->dropColumn('servicio_contempla_colacion');
            }
        });
    }
};
