<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('viaticos_disponibilidad_presupuestaria')) {
            return;
        }

        Schema::create('viaticos_disponibilidad_presupuestaria', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->string('origen_tipo', 40)->index();
            $table->unsignedBigInteger('monto_inicial')->default(0);
            $table->unsignedBigInteger('monto_comprometido')->default(0);
            $table->unsignedBigInteger('monto_ejecutado')->default(0);
            $table->unsignedBigInteger('saldo_disponible')->default(0);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->text('observaciones')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['anio', 'origen_tipo', 'activo'], 'viaticos_disp_anio_origen_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viaticos_disponibilidad_presupuestaria');
    }
};
