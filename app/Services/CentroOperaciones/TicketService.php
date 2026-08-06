<?php

namespace App\Services\CentroOperaciones;

use App\Mail\CentroOperacionesTicketMail;
use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesIncidenteConfiguracion;
use App\Models\CentroOperacionesTicket;
use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TicketService
{
    public function crearParaIncidencia(CentroOperacionesIncidencia $incidencia, User $creador): ?CentroOperacionesTicket
    {
        if ($incidencia->tipo === 'otro' || $incidencia->ticket()->exists()) {
            return null;
        }

        $configuracion = CentroOperacionesIncidenteConfiguracion::query()
            ->where('tipo', $incidencia->tipo)->where('activo', true)
            ->whereNotNull('responsable_funcionario_ac_id')->first();

        if (! $configuracion || ! $configuracion->unidad_departamento || ! $configuracion->subdireccion_dependencia) {
            return null;
        }

        $ticket = DB::transaction(function () use ($incidencia, $creador, $configuracion) {
            $ticket = CentroOperacionesTicket::query()->create([
                'incidencia_id' => $incidencia->id,
                'configuracion_id' => $configuracion->id,
                'unidad_departamento' => $configuracion->unidad_departamento,
                'subdireccion_dependencia' => $configuracion->subdireccion_dependencia,
                'responsable_funcionario_ac_id' => $configuracion->responsable_funcionario_ac_id,
                'creado_por_id' => $creador->id,
                'vence_en' => now(config('centro_operaciones.timezone'))->addDays($configuracion->plazo_dias),
            ]);
            $ticket->update(['numero' => 'INC-'.now()->format('Y').'-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);

            return $ticket;
        });

        $this->notificar($ticket, 'asignacion');
        $ticket->update(['notificado_responsable_en' => now()]);

        return $ticket;
    }

    public function escalarVencidos(): int
    {
        $tickets = CentroOperacionesTicket::query()->where('estado', 'asignado')
            ->whereNull('escalado_en')->where('vence_en', '<=', now())->get();

        foreach ($tickets as $ticket) {
            $this->notificar($ticket, 'escalamiento');
            $ticket->update(['estado' => 'vencido', 'escalado_en' => now()]);
        }

        return $tickets->count();
    }

    private function notificar(CentroOperacionesTicket $ticket, string $evento): void
    {
        $ticket->loadMissing(['incidencia.establecimiento', 'responsable']);
        $destinatario = $evento === 'asignacion'
            ? $ticket->responsable
            : $this->jefaturaActiva($ticket->subdireccion_dependencia);

        if ($destinatario?->email) {
            Mail::to($destinatario->email)->queue(new CentroOperacionesTicketMail($ticket, $evento));
        }
    }

    private function jefaturaActiva(string $subdireccion): ?FuncionarioAcAutorizado
    {
        $matriz = DB::table('funcionarios_ac_jefaturas_dependencias')
            ->where('subdireccion_dependencia', $subdireccion)->where('activo', true)->first();
        if (! $matriz) {
            return null;
        }

        $nivel = (int) ($matriz->subrogante_activo_nivel ?? 0);
        $enPeriodo = ! $matriz->subrogancia_desde || $matriz->subrogancia_desde <= now()->toDateString();
        $enPeriodo = $enPeriodo && (! $matriz->subrogancia_hasta || $matriz->subrogancia_hasta >= now()->toDateString());
        $campo = $matriz->subrogancia_activa && $nivel >= 1 && $nivel <= 3 && $enPeriodo
            ? "subrogante_{$nivel}_funcionario_ac_id" : 'jefatura_funcionario_ac_id';

        return FuncionarioAcAutorizado::query()->find($matriz->{$campo} ?? null);
    }
}
