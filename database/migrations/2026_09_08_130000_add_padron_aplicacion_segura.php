<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padron_revisiones', function (Blueprint $t): void {
            $t->timestamp('aplicada_at')->nullable();
            $t->unsignedBigInteger('aplicada_por')->nullable();
        });
        Schema::create('padron_revision_decisiones', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('padron_revision_id')->constrained('padron_revisiones');
            $t->foreignId('padron_revision_fila_id')->constrained('padron_revision_filas');
            $t->unsignedBigInteger('personal_id')->nullable();
            $t->text('justificacion');
            $t->unsignedBigInteger('resuelta_por');
            $t->timestamps();
        });
        Schema::create('padron_personal_cambios', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('padron_revision_id')->constrained('padron_revisiones');
            $t->unsignedBigInteger('personal_id')->index();
            $t->string('accion', 50);
            $t->json('antes')->nullable();
            $t->json('despues');
            $t->unsignedBigInteger('usuario_id');
            $t->timestamp('created_at');
        });
        Schema::create('padron_aplicacion_control', function (Blueprint $t): void {
            $t->unsignedInteger('id')->primary();
        });
        DB::table('padron_aplicacion_control')->insert(['id' => 1]);
    }

    public function down(): void
    {
        throw new RuntimeException('La auditoría del padrón requiere una reversión específica autorizada, sin eliminar historial.');
    }
};
