<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoAutorizacionDocente;
use App\Models\SolicitudReemplazoConfiguracion;
use App\Models\UserDocument;
use App\Services\SolicitudReemplazoAutorizacionDocenteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AutorizacionDocenteController extends Controller
{
    public function index(Request $request)
    {
        $this->assertRolUatp($request);

        $data = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(SolicitudReemplazoAutorizacionDocente::estados()))],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = SolicitudReemplazoAutorizacionDocente::query()
            ->with([
                'solicitud.establecimiento',
                'solicitud.areaDesempeno',
                'postulante.user',
                'solicitadoPor',
                'numeroRegistradoPor',
                'estadoActualizadoPor',
            ])
            ->latest('solicitado_at')
            ->latest('id');

        if (! empty($data['estado'])) {
            $query->where('estado', $data['estado']);
        }

        $busqueda = trim((string) ($data['q'] ?? ''));
        if ($busqueda !== '') {
            $like = '%' . str_replace(' ', '%', $busqueda) . '%';
            $query->where(function ($subquery) use ($like) {
                $subquery->where('numero_autorizacion', 'like', $like)
                    ->orWhereHas('solicitud', function ($solicitudQuery) use ($like) {
                        $solicitudQuery->where('numero_solicitud', 'like', $like)
                            ->orWhereHas('establecimiento', fn ($establecimientoQuery) => $establecimientoQuery->where('nombre_establecimiento', 'like', $like))
                            ->orWhereHas('postulante.user', function ($userQuery) use ($like) {
                                $userQuery->where('rut', 'like', $like)
                                    ->orWhereRaw("TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(apellido_paterno,''),' ',COALESCE(apellido_materno,''))) LIKE ?", [$like]);
                            });
                    });
            });
        }

        $autorizaciones = $query->paginate(25)->withQueryString();
        $totales = SolicitudReemplazoAutorizacionDocente::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('gestion.autorizaciones-docentes.index', compact('autorizaciones', 'totales'));
    }

    public function solicitar(
        Request $request,
        SolicitudReemplazo $solicitud,
        SolicitudReemplazoAutorizacionDocenteService $service
    ) {
        $this->assertRolUatp($request);
        $solicitud->loadMissing(['funcionarioTitular', 'postulante.user', 'areaDesempeno']);

        if ($solicitud->estado !== 'pendiente_uatp') {
            return back()->withErrors([
                'autorizacion_docente' => 'La autorización docente solo puede solicitarse mientras la solicitud está en revisión UATP.',
            ]);
        }

        if (! $service->esSolicitudDocenteConPropuesta($solicitud)) {
            return back()->withErrors([
                'autorizacion_docente' => 'Esta acción requiere una solicitud docente con postulante propuesto.',
            ]);
        }

        $correoDestino = SolicitudReemplazoConfiguracion::correoAutorizacionesDocentes();
        if (! $correoDestino) {
            return back()->withErrors([
                'autorizacion_docente' => 'El administrador debe configurar un correo válido para las autorizaciones docentes antes de enviar antecedentes.',
            ]);
        }

        $documentos = $service->documentosRequeridos($solicitud);
        $autorizacion = DB::transaction(function () use ($solicitud, $request, $correoDestino) {
            $autorizacion = SolicitudReemplazoAutorizacionDocente::query()
                ->lockForUpdate()
                ->firstOrNew(['solicitud_reemplazo_id' => $solicitud->id]);

            if ($autorizacion->exists
                && $autorizacion->correo_enviado_at
                && (int) $autorizacion->postulant_profile_id !== (int) $solicitud->postulant_profile_id) {
                throw ValidationException::withMessages([
                    'autorizacion_docente' => 'La autorización existente corresponde a otro postulante propuesto.',
                ]);
            }

            if (! $autorizacion->correo_enviado_at) {
                $autorizacion->fill([
                    'postulant_profile_id' => $solicitud->postulant_profile_id,
                    'estado' => SolicitudReemplazoAutorizacionDocente::ESTADO_EN_TRAMITE,
                    'correo_destino' => $correoDestino,
                    'solicitado_por_user_id' => $request->user()->id,
                    'solicitado_at' => now(),
                    'correo_error' => null,
                ])->save();
            }

            return $autorizacion;
        });

        if (! $autorizacion->correo_enviado_at) {
            try {
                $service->enviarCorreo($autorizacion, $documentos, $correoDestino);

                $autorizacion->update([
                    'correo_destino' => $correoDestino,
                    'correo_enviado_at' => now(),
                    'correo_error' => null,
                    'documentos_enviados' => $documentos->map(fn (UserDocument $documento) => [
                        'id' => $documento->id,
                        'slug' => $documento->type?->slug,
                        'nombre' => $documento->type?->label,
                        'archivo' => $documento->original_name ?: basename((string) $documento->path),
                        'estado' => $documento->status,
                    ])->values()->all(),
                ]);
            } catch (Throwable $exception) {
                $autorizacion->update([
                    'correo_error' => mb_substr($exception->getMessage(), 0, 4000),
                ]);
                report($exception);

                return back()->withErrors([
                    'autorizacion_docente' => 'No fue posible enviar el correo. La autorización quedó En trámite y el envío puede reintentarse.',
                ]);
            }
        }

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', [
                'solicitud' => $solicitud,
                'abrir_autorizacion_docente' => 1,
            ])
            ->with('status', 'Antecedentes enviados. Ingrese el número de autorización docente.');
    }

    public function guardarNumero(
        Request $request,
        SolicitudReemplazo $solicitud,
        SolicitudReemplazoAutorizacionDocente $autorizacion
    ) {
        $this->assertRolUatp($request);
        $this->assertPerteneceSolicitud($solicitud, $autorizacion);

        $data = $request->validate([
            'numero_autorizacion' => ['required', 'string', 'max:120'],
        ], [], [
            'numero_autorizacion' => 'número de autorización',
        ]);

        $autorizacion->update([
            'numero_autorizacion' => trim((string) $data['numero_autorizacion']),
            'numero_registrado_por_user_id' => $request->user()->id,
            'numero_registrado_at' => now(),
        ]);

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', 'Número de autorización docente guardado correctamente.');
    }

    public function actualizarEstado(Request $request, SolicitudReemplazoAutorizacionDocente $autorizacion)
    {
        $this->assertRolUatp($request);

        $data = $request->validate([
            'estado' => ['required', Rule::in(array_keys(SolicitudReemplazoAutorizacionDocente::estados()))],
            'observacion_estado' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'estado' => 'estado de autorización',
            'observacion_estado' => 'observación',
        ]);

        if ($data['estado'] === SolicitudReemplazoAutorizacionDocente::ESTADO_APROBADA
            && blank($autorizacion->numero_autorizacion)) {
            return back()->withErrors([
                'estado' => 'Debe registrar el número de autorización antes de marcarla como Aprobada.',
            ]);
        }

        $autorizacion->update([
            'estado' => $data['estado'],
            'observacion_estado' => trim((string) ($data['observacion_estado'] ?? '')) ?: null,
            'estado_actualizado_por_user_id' => $request->user()->id,
            'estado_actualizado_at' => now(),
        ]);

        return back()->with('status', 'Estado de autorización docente actualizado. La solicitud de reemplazo no fue modificada.');
    }

    private function assertRolUatp(Request $request): void
    {
        $user = $request->user();
        $activeRole = $user && method_exists($user, 'activeRoleName')
            ? (string) $user->activeRoleName()
            : '';

        abort_unless(in_array($activeRole, ['admin', 'coordinador_uatp'], true), 403);
    }

    private function assertPerteneceSolicitud(
        SolicitudReemplazo $solicitud,
        SolicitudReemplazoAutorizacionDocente $autorizacion
    ): void {
        abort_unless((int) $autorizacion->solicitud_reemplazo_id === (int) $solicitud->id, 404);
    }
}
