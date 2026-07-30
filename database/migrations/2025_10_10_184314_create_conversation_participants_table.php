<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['conversation_id', 'user_id']); // un participante solo una vez por conversación
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
