<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reemplazos_personal', function (Blueprint $table) {
            $table->timestamp('imported_at')->nullable()->after('updated_at');
            // opcional
            $table->index('imported_at');
        });
    }

    public function down(): void
    {
        Schema::table('reemplazos_personal', function (Blueprint $table) {
            $table->dropIndex(['imported_at']);
            $table->dropColumn('imported_at');
        });
    }
};
