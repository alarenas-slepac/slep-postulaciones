<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            // Nota: se deja nullable para no romper datos históricos; el flujo lo valida como obligatorio.
            $table->unsignedBigInteger('area_desempeno_id')
                ->nullable()
                ->after('reemplazo_personal_id');

            $table->foreign('area_desempeno_id')
                ->references('id')
                ->on('areas_desempeno')
                ->restrictOnDelete();

            $table->index('area_desempeno_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            $table->dropForeign(['area_desempeno_id']);
            $table->dropIndex(['area_desempeno_id']);
            $table->dropColumn('area_desempeno_id');
        });
    }
};
