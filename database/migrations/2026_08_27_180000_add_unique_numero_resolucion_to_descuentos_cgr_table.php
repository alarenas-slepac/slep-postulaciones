<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descuentos_cgr', function (Blueprint $table) {
            $table->string('numero_resolucion_clave', 100)->nullable()->after('numero_resolucion');
        });

        $clavesRegistradas = [];
        DB::table('descuentos_cgr')
            ->select(['id', 'numero_resolucion'])
            ->orderBy('id')
            ->chunkById(500, function ($descuentos) use (&$clavesRegistradas): void {
                foreach ($descuentos as $descuento) {
                    $clave = Str::upper(Str::squish((string) $descuento->numero_resolucion));

                    if ($clave === '' || isset($clavesRegistradas[$clave])) {
                        continue;
                    }

                    DB::table('descuentos_cgr')
                        ->where('id', $descuento->id)
                        ->update(['numero_resolucion_clave' => $clave]);
                    $clavesRegistradas[$clave] = true;
                }
            });

        Schema::table('descuentos_cgr', function (Blueprint $table) {
            $table->unique('numero_resolucion_clave', 'descuentos_cgr_numero_resolucion_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::table('descuentos_cgr', function (Blueprint $table) {
            $table->dropUnique('descuentos_cgr_numero_resolucion_clave_unique');
            $table->dropColumn('numero_resolucion_clave');
        });
    }
};
