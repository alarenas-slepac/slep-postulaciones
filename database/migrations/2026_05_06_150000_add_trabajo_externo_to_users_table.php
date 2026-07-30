<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'trabaja_en_otro_lugar')) {
                $table->boolean('trabaja_en_otro_lugar')->default(false)->after('establecimiento_id');
            }

            if (! Schema::hasColumn('users', 'trabaja_en_otro_lugar_observacion')) {
                $table->text('trabaja_en_otro_lugar_observacion')->nullable()->after('trabaja_en_otro_lugar');
            }

            if (! Schema::hasColumn('users', 'trabaja_en_otro_lugar_marcado_por')) {
                $table->foreignId('trabaja_en_otro_lugar_marcado_por')
                    ->nullable()
                    ->after('trabaja_en_otro_lugar_observacion')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'trabaja_en_otro_lugar_marcado_en')) {
                $table->timestamp('trabaja_en_otro_lugar_marcado_en')->nullable()->after('trabaja_en_otro_lugar_marcado_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'trabaja_en_otro_lugar_marcado_por')) {
                $table->dropForeign(['trabaja_en_otro_lugar_marcado_por']);
            }

            foreach ([
                'trabaja_en_otro_lugar_marcado_en',
                'trabaja_en_otro_lugar_marcado_por',
                'trabaja_en_otro_lugar_observacion',
                'trabaja_en_otro_lugar',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
