<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->foreignId('segundo_responsable_funcionario_ac_id')
                ->nullable()
                ->after('segunda_responsable_subdireccion');
            $table->foreign('segundo_responsable_funcionario_ac_id', 'co_inc_cfg_segundo_resp_fk')
                ->references('id')
                ->on('funcionarios_ac_autorizados')
                ->nullOnDelete();
        });

        Schema::table('centro_operaciones_tickets', function (Blueprint $table) {
            $table->foreignId('segundo_responsable_funcionario_ac_id')
                ->nullable()
                ->after('segunda_responsable_subdireccion');
            $table->foreign('segundo_responsable_funcionario_ac_id', 'co_ticket_segundo_resp_fk')
                ->references('id')
                ->on('funcionarios_ac_autorizados')
                ->nullOnDelete();

            $table->string('unidad_departamento', 190)->nullable()->change();
            $table->string('subdireccion_dependencia', 255)->nullable()->change();
            $table->unsignedBigInteger('responsable_funcionario_ac_id')->nullable()->change();
            $table->timestamp('vence_en')->nullable()->change();
            $table->index(
                ['segunda_subdireccion_responsable', 'estado'],
                'co_ticket_seg_subdir_estado_idx'
            );
        });

        $this->migrarSegundoResponsableLegado('centro_operaciones_incidente_configuraciones');
        $this->migrarSegundoResponsableLegado('centro_operaciones_tickets');
        $this->completarConfiguraciones();
        $this->crearTicketsFaltantes();
    }

    public function down(): void
    {
        Schema::table('centro_operaciones_tickets', function (Blueprint $table) {
            $table->dropIndex('co_ticket_seg_subdir_estado_idx');
            $table->dropForeign('co_ticket_segundo_resp_fk');
            $table->dropColumn('segundo_responsable_funcionario_ac_id');
        });

        Schema::table('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->dropForeign('co_inc_cfg_segundo_resp_fk');
            $table->dropColumn('segundo_responsable_funcionario_ac_id');
        });
    }

    private function migrarSegundoResponsableLegado(string $tabla): void
    {
        DB::table($tabla)
            ->whereNull('segundo_responsable_funcionario_ac_id')
            ->whereNotNull('segunda_responsable_subdireccion')
            ->orderBy('id')
            ->get(['id', 'segunda_responsable_subdireccion'])
            ->each(function (object $registro) use ($tabla) {
                $id = filter_var(
                    $registro->segunda_responsable_subdireccion,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($id && DB::table('funcionarios_ac_autorizados')->where('id', $id)->exists()) {
                    DB::table($tabla)->where('id', $registro->id)->update([
                        'segundo_responsable_funcionario_ac_id' => $id,
                    ]);
                }
            });
    }

    private function completarConfiguraciones(): void
    {
        $ahora = now();

        foreach (config('centro_operaciones.incidencias', []) as $tipo => $incidencia) {
            if (DB::table('centro_operaciones_incidente_configuraciones')->where('tipo', $tipo)->exists()) {
                continue;
            }

            DB::table('centro_operaciones_incidente_configuraciones')->insert([
                'tipo' => $tipo,
                'nombre' => $incidencia['label'] ?? $tipo,
                'severidad' => $incidencia['severity'] ?? 'alerta',
                'plazo_dias' => 4,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    private function crearTicketsFaltantes(): void
    {
        DB::table('centro_operaciones_incidencias as incidencia')
            ->leftJoin('centro_operaciones_tickets as ticket', 'ticket.incidencia_id', '=', 'incidencia.id')
            ->leftJoin('centro_operaciones_reportes as reporte', 'reporte.id', '=', 'incidencia.reporte_id')
            ->whereNull('ticket.id')
            ->orderBy('incidencia.id')
            ->select([
                'incidencia.id',
                'incidencia.tipo',
                'incidencia.estado',
                'incidencia.created_at',
                'incidencia.updated_at',
                'incidencia.resuelta_en',
                'incidencia.resuelta_por_id',
                'reporte.reportado_por_id',
            ])
            ->get()
            ->each(function (object $incidencia) {
                $configuracion = DB::table('centro_operaciones_incidente_configuraciones')
                    ->where('tipo', $incidencia->tipo)
                    ->first();
                $asignada = $configuracion
                    && (bool) $configuracion->activo
                    && $configuracion->unidad_departamento
                    && $configuracion->subdireccion_dependencia
                    && $configuracion->responsable_funcionario_ac_id;
                $resuelta = $incidencia->estado === 'resuelta';
                $creadoEn = $incidencia->created_at ?: now();
                $ticketId = DB::table('centro_operaciones_tickets')->insertGetId([
                    'incidencia_id' => $incidencia->id,
                    'configuracion_id' => $configuracion?->id,
                    'unidad_departamento' => $asignada ? $configuracion->unidad_departamento : null,
                    'subdireccion_dependencia' => $asignada ? $configuracion->subdireccion_dependencia : null,
                    'responsable_funcionario_ac_id' => $asignada ? $configuracion->responsable_funcionario_ac_id : null,
                    'segunda_subdireccion_responsable' => $asignada
                        ? $configuracion->segunda_subdireccion_responsable
                        : null,
                    'segundo_responsable_funcionario_ac_id' => $asignada
                        ? $configuracion->segundo_responsable_funcionario_ac_id
                        : null,
                    'creado_por_id' => $incidencia->reportado_por_id,
                    'vence_en' => $asignada
                        ? now()->addDays((int) ($configuracion->plazo_dias ?: 4))
                        : null,
                    'estado' => $resuelta
                        ? 'resuelto'
                        : ($asignada ? 'asignado' : 'pendiente_asignacion'),
                    'resuelto_en' => $resuelta ? ($incidencia->resuelta_en ?: $incidencia->updated_at) : null,
                    'resuelto_por_id' => $resuelta ? $incidencia->resuelta_por_id : null,
                    'resolucion' => $resuelta
                        ? 'Incidencia cerrada antes de la regularización de tickets.'
                        : null,
                    'created_at' => $creadoEn,
                    'updated_at' => $incidencia->updated_at ?: $creadoEn,
                ]);
                $anio = date('Y', strtotime((string) $creadoEn));

                DB::table('centro_operaciones_tickets')->where('id', $ticketId)->update([
                    'numero' => 'INC-'.$anio.'-'.str_pad((string) $ticketId, 6, '0', STR_PAD_LEFT),
                ]);
            });
    }
};
