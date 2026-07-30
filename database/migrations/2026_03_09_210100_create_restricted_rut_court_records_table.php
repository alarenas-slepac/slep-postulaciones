<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restricted_rut_court_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restricted_rut_id')->constrained('restricted_ruts')->cascadeOnDelete();
            $table->string('nombre')->nullable();
            $table->string('run_original', 32)->nullable();
            $table->string('juzgado_origen')->nullable();
            $table->string('rit')->nullable();
            $table->date('fecha_fallo')->nullable();
            $table->string('inhabilidad_texto')->nullable();
            $table->boolean('activa')->default(true);
            $table->string('archivo_origen')->nullable();
            $table->timestamps();

            $table->unique('restricted_rut_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restricted_rut_court_records');
    }
};
