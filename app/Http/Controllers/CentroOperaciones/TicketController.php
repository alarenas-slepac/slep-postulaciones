<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroOperaciones\SubirTicketImagenesRequest;
use App\Models\CentroOperacionesTicket;
use App\Models\CentroOperacionesTicketImagen;
use App\Models\FuncionarioAcAutorizado;
use App\Services\CentroOperaciones\TicketDocumentoService;
use App\Services\CentroOperaciones\TicketImagenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    private const ROLES_TODOS = ['admin', 'director_ejecutivo', 'secretaria_direccion_ejecutiva', 'comunicaciones', 'gabinete_slep'];

    public function __construct(private readonly TicketDocumentoService $documentos)
    {
    }

    public function index(Request $request): View
    {
        $query = CentroOperacionesTicket::query()
            ->with(['incidencia.establecimiento', 'responsable', 'segundoResponsable']);
        if (Schema::hasColumn('centro_operaciones_incidencias', 'prioridad_nivel')) {
            $query->select('centro_operaciones_tickets.*')
                ->leftJoin(
                    'centro_operaciones_incidencias as incidencia_prioridad',
                    'incidencia_prioridad.id',
                    '=',
                    'centro_operaciones_tickets.incidencia_id'
                )
                ->orderByRaw("CASE incidencia_prioridad.prioridad_nivel WHEN 'P1' THEN 1 WHEN 'P2' THEN 2 WHEN 'P3' THEN 3 WHEN 'P4' THEN 4 ELSE 5 END")
                ->orderByDesc('incidencia_prioridad.prioridad_puntaje')
                ->orderBy('centro_operaciones_tickets.vence_en');
        } else {
            $query->latest();
        }
        $this->aplicarAlcance($query, $request);

        return view('centro-operaciones.tickets.index', ['tickets' => $query->paginate(25)]);
    }

    public function show(Request $request, CentroOperacionesTicket $ticket): View
    {
        $this->autorizarTicket($request, $ticket);

        $ticket->loadMissing([
            'incidencia.establecimiento',
            'incidencia.reporte.reportadoPor',
            'responsable',
            'segundoResponsable',
            'configuracion',
            'resueltoPor',
            'firmaResolucion',
            'imagenes',
        ]);

        return view('centro-operaciones.tickets.show', [
            'ticket' => $ticket,
            'puedeResolver' => $this->puedeResolver($request, $ticket),
            'puedeSubirImagenes' => $this->puedeSubirImagenes($request, $ticket),
        ]);
    }

    public function subirImagenes(
        SubirTicketImagenesRequest $request,
        CentroOperacionesTicket $ticket,
        TicketImagenService $servicio
    ): RedirectResponse {
        $archivos = $request->file('imagenes', []);
        $servicio->guardar($ticket, $request->user(), $archivos);

        return back()->with(
            'success',
            count($archivos) === 1
                ? 'Fotografía agregada correctamente.'
                : 'Fotografías agregadas correctamente.'
        );
    }

    public function imagen(
        Request $request,
        CentroOperacionesTicket $ticket,
        CentroOperacionesTicketImagen $imagen
    ): StreamedResponse {
        $this->autorizarTicket($request, $ticket);
        abort_unless((int) $imagen->ticket_id === (int) $ticket->id, 404);
        abort_unless(Storage::disk('local')->exists($imagen->path), 404);

        return Storage::disk('local')->response(
            $imagen->path,
            'fotografia-ticket-'.$imagen->id.'.jpg',
            [
                'Content-Type' => $imagen->mime_type,
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }

    public function resolver(Request $request, CentroOperacionesTicket $ticket): RedirectResponse
    {
        $request->validate(['resolucion' => ['required', 'string', 'max:2000']]);
        abort_if($ticket->estado === 'resuelto', 422, 'El ticket ya se encuentra resuelto.');
        abort_unless($this->puedeResolver($request, $ticket), 403);

        DB::transaction(function () use ($ticket, $request) {
            $ticket->update(['estado' => 'resuelto', 'resuelto_en' => now(), 'resuelto_por_id' => $request->user()->id, 'resolucion' => $request->string('resolucion')->trim()]);
            $ticket->incidencia()->update(['estado' => 'resuelta', 'resuelta_en' => now(), 'resuelta_por_id' => $request->user()->id]);
            $this->documentos->registrarFirmaResolucion($ticket->fresh(), $request->user(), $request);
        });

        return back()->with('success', 'Ticket resuelto correctamente.');
    }

    public function pdf(Request $request, CentroOperacionesTicket $ticket): Response
    {
        $this->autorizarTicket($request, $ticket);
        $contenido = $this->documentos->generarPdf($ticket);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$ticket->numero.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function verificar(string $codigo): View
    {
        $ticket = CentroOperacionesTicket::query()
            ->with([
                'incidencia.establecimiento',
                'incidencia.reporte.reportadoPor',
                'firmaResolucion',
                'responsable',
                'segundoResponsable',
            ])
            ->where('codigo_validacion', strtoupper($codigo))
            ->first();
        $integridad = $ticket
            ? $this->documentos->verificarIntegridad($ticket)
            : ['hash_actual' => '', 'integro' => false];

        return view('centro-operaciones.tickets.verificar', compact('ticket', 'integridad'));
    }

    private function autorizarTicket(Request $request, CentroOperacionesTicket $ticket): void
    {
        $query = CentroOperacionesTicket::query()->whereKey($ticket->id);
        $this->aplicarAlcance($query, $request);
        abort_unless($query->exists(), 403);
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
                ->orWhere('segundo_responsable_funcionario_ac_id', $funcionario->id)
                ->when($subdirecciones->isNotEmpty(), function ($sq) use ($subdirecciones) {
                    $sq->orWhereIn('subdireccion_dependencia', $subdirecciones)
                        ->orWhereIn('segunda_subdireccion_responsable', $subdirecciones);
                });
        });
    }

    private function puedeSubirImagenes(Request $request, CentroOperacionesTicket $ticket): bool
    {
        $usuario = $request->user();
        $ticket->loadMissing('incidencia');

        return $usuario->hasAnyRole(config('centro_operaciones.roles_gestion_total', []))
            || (
                $usuario->hasRole('funcionario_directivo_estab')
                && (int) $usuario->establecimiento_id === (int) $ticket->incidencia?->establecimiento_id
            );
    }

    private function puedeResolver(Request $request, CentroOperacionesTicket $ticket): bool
    {
        if ($request->user()->hasAnyRole(self::ROLES_TODOS)) return true;
        if ($request->user()->hasRole('funcionario_directivo_estab')) {
            return (int) $request->user()->establecimiento_id === (int) $ticket->incidencia->establecimiento_id;
        }
        $funcionarioId = $this->funcionario($request->user())?->id;

        return $funcionarioId !== null
            && in_array((int) $funcionarioId, [
                (int) $ticket->responsable_funcionario_ac_id,
                (int) $ticket->segundo_responsable_funcionario_ac_id,
            ], true);
    }

    private function funcionario($usuario): ?FuncionarioAcAutorizado
    {
        $rutNormalizado = $usuario->rut_normalized;

        return FuncionarioAcAutorizado::query()
            ->where(function ($query) use ($usuario, $rutNormalizado) {
                $query->where('registered_user_id', $usuario->id);
                if ($rutNormalizado !== '') {
                    $query->orWhere('rut_normalizado', $rutNormalizado);
                }
            })
            ->first();
    }
}
