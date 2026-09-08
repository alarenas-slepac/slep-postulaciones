<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['solicitudes_reemplazo', 'cometidos_funcionarios', 'incumplimientos_laborales'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'padron_personal_snapshot')) {
                Schema::table($table, fn (Blueprint $t) => $t->json('padron_personal_snapshot')->nullable());
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Las copias contractuales históricas requieren una reversión específica autorizada sin pérdida de información.');
    }
};
