<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commune_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('commune_id');

            $table->primary(['user_id', 'commune_id']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('commune_id')->references('id')->on('communes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commune_user');
    }
};
