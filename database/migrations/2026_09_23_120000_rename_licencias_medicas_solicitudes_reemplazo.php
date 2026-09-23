<?php

use App\Support\TipoReemplazo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (TipoReemplazo::equivalenciasHistoricas() as $anterior => $nuevo) {
            DB::table('solicitudes_reemplazo')
                ->where('tipo_reemplazo', $anterior)
                ->update(['tipo_reemplazo' => $nuevo]);
        }
    }

    public function down(): void
    {
        foreach (TipoReemplazo::equivalenciasHistoricas() as $anterior => $nuevo) {
            DB::table('solicitudes_reemplazo')
                ->where('tipo_reemplazo', $nuevo)
                ->update(['tipo_reemplazo' => $anterior]);
        }
    }
};
