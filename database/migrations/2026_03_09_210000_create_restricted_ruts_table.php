<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restricted_ruts', function (Blueprint $table) {
            $table->id();
            $table->string('rut_normalized', 16)->unique();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restricted_ruts');
    }
};
