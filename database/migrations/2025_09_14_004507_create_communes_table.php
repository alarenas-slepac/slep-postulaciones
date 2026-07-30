<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 5); // p.ej. '01', 'RM'
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['region_code', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
    }
};
