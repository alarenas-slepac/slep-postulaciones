<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lastRut = null;

        $rows = DB::table('reemplazos_personal')
            ->select('rut', 'bienios')
            ->whereNotNull('bienios')
            ->whereNotNull('rut')
            ->where('rut', '<>', '')
            ->orderBy('rut')
            ->orderByRaw('CASE WHEN imported_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('imported_at')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderByDesc('id')
            ->cursor();

        foreach ($rows as $row) {
            if ($row->rut === $lastRut) {
                continue;
            }

            $lastRut = $row->rut;

            DB::table('reemplazos_personal')
                ->where('rut', $row->rut)
                ->whereNull('bienios')
                ->update(['bienios' => (int) $row->bienios]);
        }
    }

    public function down(): void
    {
        // Backfill irreversible: no-op.
    }
};
