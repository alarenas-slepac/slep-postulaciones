<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (!Schema::hasColumn('tramites', 'anulado_at')) {
                $table->timestamp('anulado_at')->nullable()->after('enviado_at');
            }
            if (!Schema::hasColumn('tramites', 'anulado_por_user_id')) {
                $table->foreignId('anulado_por_user_id')->nullable()->after('anulado_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tramites', function (Blueprint $table) {
            if (Schema::hasColumn('tramites', 'anulado_por_user_id')) {
                $table->dropConstrainedForeignId('anulado_por_user_id');
            }
            if (Schema::hasColumn('tramites', 'anulado_at')) {
                $table->dropColumn('anulado_at');
            }
        });
    }
};
