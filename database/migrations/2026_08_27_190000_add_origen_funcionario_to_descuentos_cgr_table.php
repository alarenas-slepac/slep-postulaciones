<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descuentos_cgr', function (Blueprint $table) {
            $table->string('origen_funcionario', 30)->nullable()->after('nombre')->index();
        });

        $columnasAc = Schema::hasTable('funcionarios_ac_autorizados')
            ? collect(['run_normalizado', 'rut_normalizado'])
                ->filter(fn (string $columna) => Schema::hasColumn('funcionarios_ac_autorizados', $columna))
                ->values()
            : collect();
        $tienePadronEstablecimientos = Schema::hasTable('reemplazos_personal')
            && Schema::hasColumn('reemplazos_personal', 'rut');

        DB::table('descuentos_cgr')
            ->select(['id', 'rut'])
            ->orderBy('id')
            ->chunkById(200, function ($descuentos) use ($columnasAc, $tienePadronEstablecimientos): void {
                foreach ($descuentos as $descuento) {
                    $rutPlano = strtoupper((string) preg_replace('/[^0-9K]/i', '', (string) $descuento->rut));
                    if ($rutPlano === '') {
                        continue;
                    }

                    $esAdministracionCentral = $columnasAc->contains(function (string $columna) use ($rutPlano): bool {
                        return DB::table('funcionarios_ac_autorizados')
                            ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER({$columna}), '.', ''), '-', ''), ' ', '') = ?", [$rutPlano])
                            ->exists();
                    });

                    $origen = null;
                    if ($esAdministracionCentral) {
                        $origen = 'administracion_central';
                    } elseif ($tienePadronEstablecimientos) {
                        $esEstablecimiento = DB::table('reemplazos_personal')
                            ->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$rutPlano])
                            ->exists();
                        $origen = $esEstablecimiento ? 'establecimiento' : null;
                    }

                    if ($origen !== null) {
                        DB::table('descuentos_cgr')
                            ->where('id', $descuento->id)
                            ->update(['origen_funcionario' => $origen]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('descuentos_cgr', function (Blueprint $table) {
            $table->dropIndex(['origen_funcionario']);
            $table->dropColumn('origen_funcionario');
        });
    }
};
