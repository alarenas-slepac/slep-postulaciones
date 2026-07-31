<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('establecimientos', 'matricula_total')) {
                $table->unsignedInteger('matricula_total')->nullable()->after('asignacion_zona');
            }
        });
    }

    public function down(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            if (Schema::hasColumn('establecimientos', 'matricula_total')) {
                $table->dropColumn('matricula_total');
            }
        });
    }
};
