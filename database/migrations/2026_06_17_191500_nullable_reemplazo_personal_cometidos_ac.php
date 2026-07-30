<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cometidos_funcionarios') || ! Schema::hasColumn('cometidos_funcionarios', 'reemplazo_personal_id')) {
            return;
        }

        DB::statement('ALTER TABLE cometidos_funcionarios MODIFY reemplazo_personal_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        // No se revierte automáticamente: los cometidos de Administración Central se guardan sin reemplazo_personal_id.
    }
};
