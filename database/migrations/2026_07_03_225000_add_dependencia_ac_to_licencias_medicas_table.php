<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licencias_medicas')) {
            return;
        }

        Schema::table('licencias_medicas', function (Blueprint $table) {
            if (! Schema::hasColumn('licencias_medicas', 'tipo_dependencia')) {
                $table->string('tipo_dependencia', 60)->nullable()->index()->after('edad');
            }
            if (! Schema::hasColumn('licencias_medicas', 'subdireccion')) {
                $table->string('subdireccion', 190)->nullable()->after('comuna');
            }
            if (! Schema::hasColumn('licencias_medicas', 'unidad_departamento')) {
                $table->string('unidad_departamento', 190)->nullable()->after('subdireccion');
            }
            if (! Schema::hasColumn('licencias_medicas', 'cargo')) {
                $table->string('cargo', 190)->nullable()->after('unidad_departamento');
            }
            if (! Schema::hasColumn('licencias_medicas', 'grado')) {
                $table->string('grado', 30)->nullable()->after('cargo');
            }
            if (! Schema::hasColumn('licencias_medicas', 'escalafon')) {
                $table->string('escalafon', 120)->nullable()->after('grado');
            }
            if (! Schema::hasColumn('licencias_medicas', 'correo_funcionario')) {
                $table->string('correo_funcionario', 190)->nullable()->after('correo_trabajador');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('licencias_medicas')) {
            return;
        }

        Schema::table('licencias_medicas', function (Blueprint $table) {
            foreach (['correo_funcionario', 'escalafon', 'grado', 'cargo', 'unidad_departamento', 'subdireccion', 'tipo_dependencia'] as $column) {
                if (Schema::hasColumn('licencias_medicas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
