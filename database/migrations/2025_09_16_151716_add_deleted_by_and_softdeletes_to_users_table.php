<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('remember_token');
                $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes(); // crea 'deleted_at'
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'deleted_by')) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
            }
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
