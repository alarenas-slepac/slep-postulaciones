<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Models\CentroOperacionesTicket;
use App\Models\FuncionarioAcAutorizado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TicketController extends Controller
{
    private const ROLES_TODOS = ['admin', 'director_ejecutivo', 'secretaria_direccion_ejecutiva', 'comunicaciones'];

    public function index(Request $request): View
    {
        $query = CentroOperacionesTicket::query()->with(['incidencia.establecimiento', 'responsable'])->latest();
        $this->aplicarAlcance($query, $request);

        return view('centro-operaciones.tickets.index', ['tickets' => $query->paginate(25)]);
    }

    public function show(Request $request, CentroOperacionesTicket $ticket): View
    {
        $query = CentroOperacionesTicket::query()->whereKey($ticket->id);
        $this->aplicarAlcance($query, $request);
        abort_unless($query->exists(), 403);

        // Cargar segunda subdirección y responsable de la configuración del ticket
        $ticket->loadMissing(['configuracion', 'configuracion.segundaSubdireccionResponsable', 'configuracion.segundaResponsableSubdireccion']);

        return view('centro-operaciones.tickets.show', [
            'ticket' => $ticket,
            'subdirecciones' => $this->getSubdirecciones(),
            'responsables' => $this->getResponsables(),
        ]);
    }

    private function getSubdirecciones()
    {
        // Método para cargar subdirecciones dinámicas para el formulario (opcional)
        return []; // Esto podría ser dinámico, como una lista de subdirecciones desde la base de datos
    }

    private function getResponsables()
    {
        // Método para cargar responsables dinámicos para el formulario
        return FuncionarioAcAutorizado::all();
    }

    public function resolver(Request $request, CentroOperacionesTicket $ticket): RedirectResponse
    {
        $request->validate(['resolucion' => ['required', 'string', 'max:2000']]);
        abort_if($ticket->estado === 'resuelto', 422, 'El ticket ya se encuentra resuelto.');
        abort_unless($this->puedeResolver($request, $ticket), 403);

        DB::transaction(function () use ($ticket, $request) {
            $ticket->update(['estado' => 'resuelto', 'resuelto_en' => now(), 'resuelto_por_id' => $request->user()->id, 'resolucion' => $request->string('resolucion')->trim()]);
            $ticket->incidencia()->update(['estado' => 'resuelta', 'resuelta_en' => now(), 'resuelta_por_id' => $request->user()->id]);
        });

        return back()->with('success', 'Ticket resuelto correctamente.');
    }

    private function aplicarAlcance($query, Request $request): void
    {
        $usuario = $request->user();
        if ($usuario->hasAnyRole(self::ROLES_TODOS)) return;
        if ($usuario->hasRole('funcionario_directivo_estab')) {
            $query->whereHas('incidencia', fn ($q) => $q->where('establecimiento_id', $usuario->establecimiento_id));
            return;
        }

        $funcionario = $this->funcionario($usuario);
        abort_unless($funcionario, 403);
        $subdirecciones = DB::table('funcionarios_ac_jefaturas_dependencias')
            ->where('activo', true)->where(function ($q) use ($funcionario) {
                $q->where('jefatura_funcionario_ac_id', $funcionario->id)
                    ->orWhere('subrogante_1_funcionario_ac_id', $funcionario->id)
                    ->orWhere('subrogante_2_funcionario_ac_id', $funcionario->id)
                    ->orWhere('subrogante_3_funcionario_ac_id', $funcionario->id);
            })->pluck('subdireccion_dependencia');
        $query->where(function ($q) use ($funcionario, $subdirecciones) {
            $q->where('responsable_funcionario_ac_id', $funcionario->id)
                ->when($subdirecciones->isNotEmpty(), fn ($sq) => $sq->orWhereIn('subdireccion_dependencia', $subdirecciones));
        });
    }

    private function puedeResolver(Request $request, CentroOperacionesTicket $ticket): bool
    {
        if ($request->user()->hasAnyRole(self::ROLES_TODOS)) return true;
        if ($request->user()->hasRole('funcionario_directivo_estab')) {
            return (int) $request->user()->establecimiento_id === (int) $ticket->incidencia->establecimiento_id;
        }
        return (int) $this->funcionario($request->user())?->id === (int) $ticket->responsable_funcionario_ac_id;
    }

    private function funcionario($usuario): ?FuncionarioAcAutorizado
    {
        return FuncionarioAcAutorizado::query()->where('registered_user_id', $usuario->id)
            ->orWhere('rut_normalizado', $usuario->rut_normalized)->first();
    }
}
