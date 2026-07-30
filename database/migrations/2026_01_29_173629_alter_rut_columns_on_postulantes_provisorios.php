<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes_provisorios', function (Blueprint $table) {
            // "123456789-K" + margen
            $table->string('rut', 15)->change();

            // por seguridad: permite 9 (y algo más por si llega un caso raro)
            $table->string('rut_body', 12)->change();

            $table->string('rut_dv', 1)->change();

            // opcional, solo para que nunca falle por strings “raras”
            $table->string('raw_rut', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('postulantes_provisorios', function (Blueprint $table) {
            // Ajusta estos tamaños a los que tenías originalmente (si eran otros)
            $table->string('rut', 12)->change();
            $table->string('rut_body', 8)->change();
            $table->string('rut_dv', 1)->change();
            $table->string('raw_rut', 50)->nullable()->change();
        });
    }
};
