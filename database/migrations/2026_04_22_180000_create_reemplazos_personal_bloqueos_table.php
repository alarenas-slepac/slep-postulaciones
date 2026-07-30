<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reemplazos_personal_bloqueos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reemplazo_personal_id')->constrained('reemplazos_personal')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $table->unsignedInteger('rbd')->nullable()->index();
            $table->string('rut', 30)->nullable()->index();
            $table->string('nombre')->nullable();
            $table->string('motivo', 255);
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->foreignId('bloqueado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('desbloqueado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('desbloqueado_at')->nullable();
            $table->timestamps();

            $table->index(['reemplazo_personal_id', 'activo']);
            $table->index(['establecimiento_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reemplazos_personal_bloqueos');
    }
};
