<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            // quién creó la conversación (admin/funcionario/etc.)
            $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $t->timestamps(); // updated_at se usa para ordenar por actividad reciente
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
