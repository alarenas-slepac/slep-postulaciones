<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->candidateTables() as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'grado')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->string('grado', 20)->nullable()->after('cargo_funcion');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->candidateTables() as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'grado')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->dropColumn('grado');
                });
            }
        }
    }

    /**
     * Posibles nombres usados por el módulo de Funcionarios Administración Central.
     * Se evalúan en tiempo de migración para no fallar si sólo existe una variante.
     *
     * @return array<int, string>
     */
    private function candidateTables(): array
    {
        return [
            'funcionario_ac_autorizado',
            'funcionarios_ac_autorizadas',
            'funcionarios_ac_autorizados',
        ];
    }
};
