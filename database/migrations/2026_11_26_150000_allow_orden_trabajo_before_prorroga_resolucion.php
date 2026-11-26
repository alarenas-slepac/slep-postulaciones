<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLA = 'solicitud_reemplazo_prorrogas';

    private const CAMPOS = [
        'numero_resolucion' => 100,
        'fecha_resolucion' => null,
        'resolucion_path' => 255,
        'resolucion_nombre' => 255,
    ];

    public function up(): void
    {
        $this->cambiarNulabilidad(true);
        if (! Schema::hasColumn(self::TABLA, 'resolucion_registrada_at')) {
            Schema::table(self::TABLA, fn (Blueprint $table) => $table->timestamp('resolucion_registrada_at')->nullable());
        }
    }

    public function down(): void
    {
        if (DB::table(self::TABLA)->where(function ($query): void {
            foreach (array_keys(self::CAMPOS) as $columna) {
                $query->orWhereNull($columna);
            }
        })->exists()) {
            throw new RuntimeException('Existen expedientes con resolución pendiente. No se puede revertir la nulabilidad.');
        }
        $this->cambiarNulabilidad(false);
        if (Schema::hasColumn(self::TABLA, 'resolucion_registrada_at')) {
            Schema::table(self::TABLA, fn (Blueprint $table) => $table->dropColumn('resolucion_registrada_at'));
        }
    }

    private function cambiarNulabilidad(bool $nullable): void
    {
        $columnas = array_column(Schema::getColumns(self::TABLA), 'nullable', 'name');
        foreach (self::CAMPOS as $nombre => $longitud) {
            if ($columnas[$nombre] !== $nullable) {
                Schema::table(self::TABLA, function (Blueprint $table) use ($nombre, $longitud, $nullable): void {
                    $columna = $longitud === null ? $table->date($nombre) : $table->string($nombre, $longitud);
                    $columna->nullable($nullable)->change();
                });
            }
        }
    }
};
