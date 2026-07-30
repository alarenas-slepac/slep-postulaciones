<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tablasPosibles = [
        'funcionarios_ac_autorizados',
        'funcionario_ac_autorizado',
        'funcionarios_ac_autorizadas',
    ];

    public function up(): void
    {
        foreach ($this->tablasPosibles as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'jefatura')) {
                continue;
            }

            $tieneSubdireccion = Schema::hasColumn($tabla, 'subdireccion_dependencia');

            Schema::table($tabla, function (Blueprint $table) use ($tieneSubdireccion) {
                $columna = $table->boolean('jefatura')->default(false);

                if ($tieneSubdireccion) {
                    $columna->after('subdireccion_dependencia');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablasPosibles as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'jefatura')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropColumn('jefatura');
                });
            }
        }
    }
};
