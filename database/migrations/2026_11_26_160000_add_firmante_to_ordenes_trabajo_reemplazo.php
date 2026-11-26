<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLAS = ['solicitudes_reemplazo', 'solicitud_reemplazo_prorrogas'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasColumn($tabla, 'orden_trabajo_firmante')) {
                Schema::table($tabla, fn (Blueprint $table) => $table->json('orden_trabajo_firmante')->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (Schema::hasColumn($tabla, 'orden_trabajo_firmante')) {
                Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('orden_trabajo_firmante'));
            }
        }
    }
};
