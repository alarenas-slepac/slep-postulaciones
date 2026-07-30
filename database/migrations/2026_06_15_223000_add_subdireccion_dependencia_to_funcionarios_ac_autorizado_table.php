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
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'subdireccion_dependencia')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'unidad_departamento')) {
                    $table->string('subdireccion_dependencia', 255)->nullable()->after('unidad_departamento');
                } else {
                    $table->string('subdireccion_dependencia', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tablasPosibles as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'subdireccion_dependencia')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('subdireccion_dependencia');
            });
        }
    }
};
