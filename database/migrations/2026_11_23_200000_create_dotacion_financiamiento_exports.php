<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = 'dotacion_financiamiento_exports';
        if (! Schema::hasTable($name)) {
            Schema::create($name, function (Blueprint $table): void {
                $table->uuid('id')->primary('dfe_pk');
                $table->unsignedBigInteger('user_id');
                $table->unsignedSmallInteger('anio');
                $table->json('filtros');
                $table->string('estado', 12);
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }
        if (! Schema::hasIndex($name, 'dfe_user_created_idx')) {
            Schema::table($name, fn (Blueprint $table) => $table->index(['user_id', 'created_at'], 'dfe_user_created_idx'));
        }
        if (! Schema::hasIndex($name, 'dfe_expires_idx')) {
            Schema::table($name, fn (Blueprint $table) => $table->index('expires_at', 'dfe_expires_idx'));
        }
    }

    public function down(): void
    {
        // Conservar las solicitudes para que la limpieza programada pueda retirar sus archivos.
    }
};
