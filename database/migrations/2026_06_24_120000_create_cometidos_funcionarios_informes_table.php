<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cometidos_funcionarios_informes')) {
            return;
        }

        Schema::create('cometidos_funcionarios_informes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cometido_funcionario_id');
            $table->string('estado_informe', 80)->default('borrador');
            $table->date('fecha_desde_real')->nullable();
            $table->date('fecha_hasta_real')->nullable();
            $table->time('hora_salida_real')->nullable();
            $table->time('hora_regreso_real')->nullable();
            $table->text('justificacion_cambio_fechas')->nullable();
            $table->text('organismos_autoridades_relatores')->nullable();
            $table->longText('descripcion_actividades_realizadas')->nullable();
            $table->longText('resultados_obtenidos')->nullable();
            $table->longText('opiniones_propuestas')->nullable();
            $table->boolean('requiere_nuevo_cometido_diferencia')->default(false);
            $table->timestamp('fecha_envio')->nullable();
            $table->unsignedBigInteger('user_id_envia')->nullable();
            $table->text('observacion_sistema')->nullable();
            $table->timestamps();

            $table->foreign('cometido_funcionario_id', 'com_inf_com_fk')
                ->references('id')->on('cometidos_funcionarios')
                ->cascadeOnDelete();

            $table->foreign('user_id_envia', 'com_inf_user_fk')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index(['cometido_funcionario_id', 'estado_informe'], 'com_inf_com_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cometidos_funcionarios_informes');
    }
};
