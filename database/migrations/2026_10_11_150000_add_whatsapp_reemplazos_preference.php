<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('funcionarios_ac_autorizados', 'notificar_whatsapp_reemplazos')) {
            Schema::table('funcionarios_ac_autorizados', function (Blueprint $table): void {
                $table->boolean('notificar_whatsapp_reemplazos')->default(false);
            });
        }
    }

    public function down(): void
    {
        // Conservar las preferencias históricas. Desactivar el módulo por configuración.
    }
};
