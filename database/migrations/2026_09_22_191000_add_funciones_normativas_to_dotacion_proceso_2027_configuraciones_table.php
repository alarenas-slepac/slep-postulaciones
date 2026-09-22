<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'dotacion_proceso_2027_configuraciones';
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            if (! Schema::hasColumn('dotacion_proceso_2027_configuraciones', 'funciones_normativas')) {
                $table->json('funciones_normativas')->nullable();
            }
            if (! Schema::hasColumn('dotacion_proceso_2027_configuraciones', 'funciones_normativas_configuradas_by')) {
                $table->unsignedBigInteger('funciones_normativas_configuradas_by')->nullable();
            }
            if (! Schema::hasColumn('dotacion_proceso_2027_configuraciones', 'funciones_normativas_configuradas_at')) {
                $table->timestamp('funciones_normativas_configuradas_at')->nullable();
            }
        });

        $hasForeignKey = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreignKey) => ($foreignKey['columns'] ?? []) === ['funciones_normativas_configuradas_by']);
        if (! $hasForeignKey) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('funciones_normativas_configuradas_by', 'd2027cfg_norm_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tableName = 'dotacion_proceso_2027_configuraciones';
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropForeign('d2027cfg_norm_by_fk');
            $table->dropColumn([
                'funciones_normativas',
                'funciones_normativas_configuradas_by',
                'funciones_normativas_configuradas_at',
            ]);
        });
    }
};
