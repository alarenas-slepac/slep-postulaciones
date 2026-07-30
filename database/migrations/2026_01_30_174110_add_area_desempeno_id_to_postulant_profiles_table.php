<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->foreignId('area_desempeno_id')
                ->nullable()
                ->after('estamento')
                ->constrained('areas_desempeno')
                ->nullOnDelete();

            $table->index('area_desempeno_id');
        });
    }

    public function down(): void
    {
        Schema::table('postulant_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_desempeno_id');
        });
    }
};
