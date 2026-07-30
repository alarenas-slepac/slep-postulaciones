<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->date('fecha_titulacion')->nullable()->after('institucion_titulo');
        });
    }

    public function down(): void
    {
        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->dropColumn('fecha_titulacion');
        });
    }
};
