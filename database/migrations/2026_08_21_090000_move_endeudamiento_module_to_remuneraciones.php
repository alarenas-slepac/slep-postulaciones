<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->actualizarSeccion('Remuneraciones');
    }

    public function down(): void
    {
        $this->actualizarSeccion('Operación');
    }

    private function actualizarSeccion(string $seccion): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $datos = ['section' => $seccion];
        if (Schema::hasColumn('modules', 'updated_at')) {
            $datos['updated_at'] = now();
        }

        DB::table('modules')
            ->where('key', 'endeudamiento')
            ->update($datos);
    }
};
