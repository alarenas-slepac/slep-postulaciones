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
    public function crearParaIncidencia(CentroOperacionesIncidencia $incidencia, User $creador): CentroOperacionesTicket
    {
        $creado = false;
        $ticket = DB::transaction(function () use ($incidencia, $creador, &$creado) {
            $incidenciaBloqueada = CentroOperacionesIncidencia::query()
                ->lockForUpdate()
                ->findOrFail($incidencia->id);
            $existente = CentroOperacionesTicket::query()
                ->where('incidencia_id', $incidenciaBloqueada->id)
                ->first();

            if ($existente) {
                return $existente;
            }

            $configuracion = CentroOperacionesIncidenteConfiguracion::query()
                ->with(['responsable', 'segundoResponsable'])
                ->where('tipo', $incidenciaBloqueada->tipo)
                ->first();
            $asignada = $this->configuracionAsignable($configuracion);
            $segundoResponsable = $asignada
                ? $this->segundoResponsableAsignable($configuracion)
                : null;

            $ticket = CentroOperacionesTicket::query()->create([
                'incidencia_id' => $incidenciaBloqueada->id,
                'configuracion_id' => $configuracion?->id,
                'unidad_departamento' => $asignada ? $configuracion->unidad_departamento : null,
                'subdireccion_dependencia' => $asignada ? $configuracion->subdireccion_dependencia : null,
                'responsable_funcionario_ac_id' => $asignada ? $configuracion->responsable_funcionario_ac_id : null,
                'segunda_subdireccion_responsable' => $segundoResponsable
                    ? $configuracion->segunda_subdireccion_responsable
                    : null,
                'segundo_responsable_funcionario_ac_id' => $segundoResponsable?->id,
                'creado_por_id' => $creador->id,
                'vence_en' => $asignada
                    ? $this->vencimiento($configuracion)
                    : null,
                'estado' => $asignada ? 'asignado' : 'pendiente_asignacion',
            ]);
            $ticket->update([
                'numero' => 'INC-'.now(config('centro_operaciones.timezone'))->format('Y').'-'
                    .str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT),
            ]);
            $creado = true;

            return $ticket;
        });

        if ($creado && $ticket->estado === 'asignado' && $this->notificar($ticket, 'asignacion')) {
            $ticket->update(['notificado_responsable_en' => now()]);
        }

        return $ticket;
    }

    public function escalarVencidos(): int
    {
        $tickets = CentroOperacionesTicket::query()->where('estado', 'asignado')
            ->whereNull('escalado_en')->where('vence_en', '<=', now())->get();

        foreach ($tickets as $ticket) {
            $ticket->update(['estado' => 'vencido', 'escalado_en' => now()]);
            $this->notificar($ticket->fresh(), 'escalamiento');
        }

        return $tickets->count();
    }

    public function sincronizarAsignaciones(
        CentroOperacionesIncidenteConfiguracion $configuracion
    ): int {
        $configuracion->loadMissing(['responsable', 'segundoResponsable']);
        if (! $this->configuracionAsignable($configuracion)) {
            return 0;
        }

        $segundoResponsable = $this->segundoResponsableAsignable($configuracion);
        $tickets = CentroOperacionesTicket::query()
            ->whereHas('incidencia', fn ($query) => $query->where('tipo', $configuracion->tipo))
            ->where('estado', '!=', 'resuelto')
            ->get();

        foreach ($tickets as $ticket) {
            $estabaPendiente = $ticket->estado === 'pendiente_asignacion';
            $asignacion = [
                'configuracion_id' => $configuracion->id,
                'unidad_departamento' => $configuracion->unidad_departamento,
                'subdireccion_dependencia' => $configuracion->subdireccion_dependencia,
                'responsable_funcionario_ac_id' => $configuracion->responsable_funcionario_ac_id,
                'segunda_subdireccion_responsable' => $segundoResponsable
                    ? $configuracion->segunda_subdireccion_responsable
                    : null,
                'segundo_responsable_funcionario_ac_id' => $segundoResponsable?->id,
                'vence_en' => $ticket->vence_en
                    ?: $this->vencimiento($configuracion),
                'estado' => $estabaPendiente ? 'asignado' : $ticket->estado,
            ];
            $cambioAsignacion = $estabaPendiente
                || collect($asignacion)
                    ->except(['vence_en', 'estado'])
                    ->contains(fn ($valor, $campo) => $ticket->getAttribute($campo) != $valor);
            $ticket->update($asignacion);

            if ($cambioAsignacion && $this->notificar($ticket->fresh(), 'asignacion')) {
                $ticket->update(['notificado_responsable_en' => now()]);
            }
        }

        return $tickets->count();
    }

    private function configuracionAsignable(?CentroOperacionesIncidenteConfiguracion $configuracion): bool
    {
        return $configuracion?->activo === true
            && (bool) $configuracion->unidad_departamento
            && (bool) $configuracion->subdireccion_dependencia
            && $configuracion->responsable?->estaActivo() === true
            && $configuracion->responsable->subdireccion_dependencia === $configuracion->subdireccion_dependencia;
    }

    private function segundoResponsableAsignable(
        CentroOperacionesIncidenteConfiguracion $configuracion
    ): ?FuncionarioAcAutorizado {
        $responsable = $configuracion->segundoResponsable;

        if (! $configuracion->segunda_subdireccion_responsable
            || ! $responsable?->estaActivo()
            || $responsable->subdireccion_dependencia !== $configuracion->segunda_subdireccion_responsable
            || (int) $responsable->id === (int) $configuracion->responsable_funcionario_ac_id) {
            return null;
        }

        return $responsable;
    }

    private function notificar(CentroOperacionesTicket $ticket, string $evento): bool
    {
        $ticket->loadMissing(['incidencia.establecimiento', 'responsable', 'segundoResponsable']);
        $destinatarios = $evento === 'asignacion'
            ? collect([$ticket->responsable, $ticket->segundoResponsable])
            : collect([
                $ticket->subdireccion_dependencia
                    ? $this->jefaturaActiva($ticket->subdireccion_dependencia)
                    : null,
                $ticket->segunda_subdireccion_responsable
                    ? $this->jefaturaActiva($ticket->segunda_subdireccion_responsable)
                    : null,
            ]);

        $correos = $destinatarios
            ->filter(fn (?FuncionarioAcAutorizado $destinatario) => (bool) $destinatario?->email)
            ->pluck('email')
            ->unique()
            ->values();

        foreach ($correos as $correo) {
            Mail::to($correo)->queue(new CentroOperacionesTicketMail($ticket, $evento));
        }

        return $correos->isNotEmpty();
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

    private function vencimiento(CentroOperacionesIncidenteConfiguracion $configuracion)
    {
        $ahora = now(config('centro_operaciones.timezone'));

        return $configuracion->sla_horas
            ? $ahora->addHours((int) $configuracion->sla_horas)
            : $ahora->addDays((int) $configuracion->plazo_dias);
    }
}
