<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('establecimiento_valores_hora')) {
            Schema::drop('establecimiento_valores_hora');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('establecimiento_valores_hora')) {
            Schema::create('establecimiento_valores_hora', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('establecimiento_id');
                $table->unsignedBigInteger('area_desempeno_id');
                $table->decimal('valor_hora', 12, 2);
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['establecimiento_id', 'area_desempeno_id']);
            });
        }
    }
};
