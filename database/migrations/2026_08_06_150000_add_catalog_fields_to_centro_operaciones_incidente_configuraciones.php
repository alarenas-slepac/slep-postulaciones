<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->string('nombre', 120)->nullable()->after('tipo');
            $table->string('severidad', 20)->default('alerta')->after('nombre');
        });

        foreach (config('centro_operaciones.incidencias', []) as $tipo => $incidencia) {
            DB::table('centro_operaciones_incidente_configuraciones')
                ->where('tipo', $tipo)
                ->update([
                    'nombre' => $incidencia['label'] ?? $tipo,
                    'severidad' => $incidencia['severity'] ?? 'alerta',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'severidad']);
        });
    }
};
