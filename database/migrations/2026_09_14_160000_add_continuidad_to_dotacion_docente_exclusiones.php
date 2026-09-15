<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dotacion_docente_exclusiones', 'considerar_dotacion_siguiente')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                $table->boolean('considerar_dotacion_siguiente')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dotacion_docente_exclusiones', 'considerar_dotacion_siguiente')) {
            Schema::table('dotacion_docente_exclusiones', function (Blueprint $table): void {
                $table->dropColumn('considerar_dotacion_siguiente');
            });
        }
    }
};
