<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('descuentos_cgr', function (Blueprint $table): void {
            $table->decimal('certificado_residuo_utm_aceptado', 14, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('descuentos_cgr', function (Blueprint $table): void {
            $table->dropColumn('certificado_residuo_utm_aceptado');
        });
    }
};
