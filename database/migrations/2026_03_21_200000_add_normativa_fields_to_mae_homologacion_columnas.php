<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('mae_homologacion_columnas', function (Blueprint $table) {
            $table->string('normativa_bucket', 50)->nullable()->after('subgrupo');
            $table->string('normativa_label', 120)->nullable()->after('normativa_bucket');
            $table->text('normativa_regla')->nullable()->after('normativa_label');
            $table->unsignedInteger('normativa_prioridad')->nullable()->after('normativa_regla');
            $table->boolean('normativa_activa')->default(false)->after('normativa_prioridad');

            $table->index(['normativa_activa', 'normativa_bucket'], 'mae_homologacion_normativa_index');
        });
    }

    public function down(): void
    {
        Schema::table('mae_homologacion_columnas', function (Blueprint $table) {
            $table->dropIndex('mae_homologacion_normativa_index');
            $table->dropColumn([
                'normativa_bucket',
                'normativa_label',
                'normativa_regla',
                'normativa_prioridad',
                'normativa_activa',
            ]);
        });
    }
};
