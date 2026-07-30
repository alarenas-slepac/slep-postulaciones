<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('viaticos_reembolsos_valores')) {
            return;
        }

        $now = now();
        $rows = [
            ['cargo_funcion' => '1° al 4°', 'valor_100' => 89416, 'valor_40' => 35766],
            ['cargo_funcion' => '5° al 10°', 'valor_100' => 82249, 'valor_40' => 32900],
            ['cargo_funcion' => '11° al 21°', 'valor_100' => 66751, 'valor_40' => 26700],
            ['cargo_funcion' => '22° al 31°', 'valor_100' => 49648, 'valor_40' => 19859],
        ];

        foreach ($rows as $row) {
            DB::table('viaticos_reembolsos_valores')->updateOrInsert(
                [
                    'estamento' => 'Código Administrativo',
                    'cargo_funcion' => $row['cargo_funcion'],
                    'vigente_desde' => '2026-06-01',
                    'vigente_hasta' => '2026-12-31',
                ],
                [
                    'valor_100' => $row['valor_100'],
                    'valor_40' => $row['valor_40'],
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('viaticos_reembolsos_valores')) {
            return;
        }

        DB::table('viaticos_reembolsos_valores')
            ->where('estamento', 'Código Administrativo')
            ->where('vigente_desde', '2026-06-01')
            ->where('vigente_hasta', '2026-12-31')
            ->whereIn('cargo_funcion', ['1° al 4°', '5° al 10°', '11° al 21°', '22° al 31°'])
            ->delete();
    }
};
