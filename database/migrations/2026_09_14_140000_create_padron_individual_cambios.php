<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padron_individual_cambios', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('personal_id')->index();
            $t->unsignedBigInteger('usuario_id');
            $t->string('accion', 40);
            $t->text('justificacion');
            $t->json('antes')->nullable();
            $t->json('despues');
            $t->json('controles');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La auditoría individual requiere una reversión específica autorizada, sin eliminar historial.');
    }
};
