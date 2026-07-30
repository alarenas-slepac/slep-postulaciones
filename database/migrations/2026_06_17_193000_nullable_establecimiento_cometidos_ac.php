<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cometidos_funcionarios') && Schema::hasColumn('cometidos_funcionarios', 'establecimiento_id')) {
            DB::statement('ALTER TABLE cometidos_funcionarios MODIFY establecimiento_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cometidos_funcionarios') && Schema::hasColumn('cometidos_funcionarios', 'establecimiento_id')) {
            DB::statement('ALTER TABLE cometidos_funcionarios MODIFY establecimiento_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
