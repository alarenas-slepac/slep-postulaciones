<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_operaciones_ticket_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id');
            $table->string('path', 500);
            $table->string('mime_type', 100)->default('image/jpeg');
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('subida_por_id')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id', 'co_ticket_img_ticket_fk')
                ->references('id')
                ->on('centro_operaciones_tickets')
                ->cascadeOnDelete();
            $table->foreign('subida_por_id', 'co_ticket_img_usuario_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['ticket_id', 'created_at'], 'co_ticket_img_ticket_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_operaciones_ticket_imagenes');
    }
};
