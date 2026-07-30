<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restricted_rut_manual_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restricted_rut_id')->constrained('restricted_ruts')->cascadeOnDelete();
            $table->date('fecha_inicio_prohibicion');
            $table->date('fecha_termino_prohibicion');
            $table->text('comentario')->nullable();
            $table->boolean('activa')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('restricted_rut_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restricted_rut_manual_records');
    }
};
