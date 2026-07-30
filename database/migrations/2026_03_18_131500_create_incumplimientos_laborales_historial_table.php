<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('incumplimientos_laborales_historial')) {
            $existingCount = DB::table('incumplimientos_laborales_historial')->count();

            if ($existingCount === 0) {
                Schema::drop('incumplimientos_laborales_historial');
            } else {
                return;
            }
        }

        Schema::create('incumplimientos_laborales_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incumplimiento_laboral_id');
            $table->string('action', 32);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('incumplimiento_laboral_id', 'inc_lab_hist_il_fk')
                ->references('id')
                ->on('incumplimientos_laborales')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'inc_lab_hist_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->timestamps();

            $table->index(['incumplimiento_laboral_id', 'created_at'], 'inc_lab_hist_item_created_idx');
            $table->index(['action', 'created_at'], 'inc_lab_hist_action_created_idx');
        });

        if (Schema::hasTable('incumplimientos_laborales')) {
            $rows = DB::table('incumplimientos_laborales as il')
                ->leftJoin('establecimientos as e', 'e.id', '=', 'il.establecimiento_id')
                ->select([
                    'il.id',
                    'il.establecimiento_id',
                    'il.reemplazo_personal_id',
                    'il.funcionario_rut',
                    'il.funcionario_nombre',
                    'il.funcionario_rbd',
                    'il.fecha_desde',
                    'il.fecha_hasta',
                    'il.dias',
                    'il.horas',
                    'il.minutos',
                    'il.informado_por_user_id',
                    'il.created_at',
                    'il.updated_at',
                    'e.nombre_establecimiento',
                    'e.rbd as establecimiento_rbd',
                ])
                ->orderBy('il.id')
                ->get();

            foreach ($rows as $row) {
                DB::table('incumplimientos_laborales_historial')->insert([
                    'incumplimiento_laboral_id' => $row->id,
                    'action' => 'created',
                    'user_id' => $row->informado_por_user_id,
                    'old_values' => null,
                    'new_values' => json_encode([
                        'establecimiento_id' => $row->establecimiento_id ? (int) $row->establecimiento_id : null,
                        'establecimiento' => $row->nombre_establecimiento,
                        'establecimiento_rbd' => $row->establecimiento_rbd ? (string) $row->establecimiento_rbd : ($row->funcionario_rbd ? (string) $row->funcionario_rbd : null),
                        'funcionario_rut' => (string) $row->funcionario_rut,
                        'funcionario_rut_formatted' => (string) $row->funcionario_rut,
                        'funcionario_nombre' => (string) $row->funcionario_nombre,
                        'reemplazo_personal_id' => $row->reemplazo_personal_id ? (int) $row->reemplazo_personal_id : null,
                        'fecha_desde' => $row->fecha_desde,
                        'fecha_hasta' => $row->fecha_hasta,
                        'dias' => (int) $row->dias,
                        'horas' => (int) $row->horas,
                        'minutos' => (int) $row->minutos,
                    ], JSON_UNESCAPED_UNICODE),
                    'changed_fields' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'created_at' => $row->created_at ?: now(),
                    'updated_at' => $row->updated_at ?: $row->created_at ?: now(),
                ]);
            }
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('incumplimientos_laborales_historial');
    }
};
