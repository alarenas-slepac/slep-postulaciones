<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('viaticos_disponibilidad_movimientos')) {
            return;
        }

        Schema::create('viaticos_disponibilidad_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('viatico_disponibilidad_presupuestaria_id');
            $table->unsignedBigInteger('cometido_funcionario_id')->nullable();
            $table->string('tipo_movimiento', 80)->index();
            $table->unsignedBigInteger('monto')->default(0);
            $table->unsignedBigInteger('saldo_anterior')->default(0);
            $table->unsignedBigInteger('saldo_nuevo')->default(0);
            $table->string('referencia', 255)->nullable();
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['viatico_disponibilidad_presupuestaria_id'], 'viat_disp_mov_disp_idx');
            $table->index(['cometido_funcionario_id'], 'viat_disp_mov_com_idx');
            $table->index(['created_by'], 'viat_disp_mov_user_idx');
            $table->index(['tipo_movimiento', 'cometido_funcionario_id'], 'viaticos_disp_mov_tipo_cometido_idx');

            $table->foreign('viatico_disponibilidad_presupuestaria_id', 'viat_disp_mov_disp_fk')
                ->references('id')
                ->on('viaticos_disponibilidad_presupuestaria')
                ->cascadeOnDelete();

            $table->foreign('cometido_funcionario_id', 'viat_disp_mov_com_fk')
                ->references('id')
                ->on('cometidos_funcionarios')
                ->nullOnDelete();

            $table->foreign('created_by', 'viat_disp_mov_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viaticos_disponibilidad_movimientos');
    }
};
