<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoDeudaPension;
use App\Models\UserDocument;
use App\Services\SolicitudReemplazoDeudaPensionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeudaPensionAlimentosController extends Controller
{
    public function index(Request $request)
    {
        $this->assertRolActivo($request);
        $query = SolicitudReemplazoDeudaPension::query()
            ->with(['solicitud.establecimiento', 'postulante.user.documents.type', 'activadoPor'])
            ->latest('activado_at');

        if ($this->rolActivo($request) !== 'admin') {
            $userId = (int) $request->user()->id;
            $query->whereHas('solicitud', fn ($q) => $q->where('derivada_a_user_id', $userId));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $normalized = preg_replace('/[^0-9Kk]/', '', $term);
            $query->where(function ($q) use ($term, $normalized) {
                $q->whereHas('solicitud', fn ($sq) => $sq->where('numero_solicitud', 'like', "%{$term}%"))
                    ->orWhereHas('postulante.user', function ($uq) use ($term, $normalized) {
                        $uq->where('nombres', 'like', "%{$term}%")
                            ->orWhere('apellido_paterno', 'like', "%{$term}%")
                            ->orWhere('apellido_materno', 'like', "%{$term}%");
                        if ($normalized !== '') {
                            $uq->orWhere('rut', 'like', "%{$normalized}%");
                        }
                    });
            });
        }

        $deudas = $query->paginate(20)->withQueryString();

        return view('gestion.deudas-pension-alimentos.index', compact('deudas'));
    }

    public function activar(Request $request, SolicitudReemplazo $solicitud)
    {
        $this->assertPuedeGestionarSolicitud($request, $solicitud);
        $solicitud->loadMissing('derivadaA.roles');
        abort_unless($solicitud->estado === 'derivada_slep', 422, 'La deuda sólo puede activarse antes de aceptar la solicitud.');
        abort_unless($solicitud->derivada_a_user_id, 422, 'La solicitud debe tener un funcionario SLEP asignado.');
        abort_unless(
            $solicitud->derivadaA && method_exists($solicitud->derivadaA, 'hasRole') && $solicitud->derivadaA->hasRole('funcionario_slep'),
            422,
            'La persona asignada a la solicitud debe tener el rol funcionario SLEP.'
        );
        abort_unless($solicitud->postulant_profile_id, 422, 'La solicitud no tiene un postulante asociado.');

        $deuda = DB::transaction(function () use ($request, $solicitud) {
            $deuda = SolicitudReemplazoDeudaPension::query()->firstOrCreate(
                ['solicitud_reemplazo_id' => $solicitud->id],
                [
                    'postulant_profile_id' => $solicitud->postulant_profile_id,
                    'estado' => SolicitudReemplazoDeudaPension::ESTADO_PENDIENTE_DOCUMENTOS,
                    'activado_por_user_id' => $request->user()->id,
                    'activado_at' => now(),
                ]
            );

            $deuda->postulante()->update([
                'deudor_pension_alimentos' => true,
                'deudor_pension_alimentos_marcado_at' => now(),
            ]);

            return $deuda;
        });

        return redirect()
            ->route('gestion.deudas-pension-alimentos.show', $deuda)
            ->with('status', 'Deuda de pensión de alimentos activada. La solicitud quedará bloqueada hasta enviar el expediente a Remuneraciones.');
    }

    public function show(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPuedeGestionarDeuda($request, $deuda);
        $deuda->load([
            'solicitud.establecimiento',
            'solicitud.areaDesempeno',
            'postulante.user.documents.type',
            'activadoPor',
            'certificadoSubidoPor',
            'resolucionSubidaPor',
            'enviadoPor',
        ]);
        $declaracion = $deuda->declaracionCargoPublicoActual();

        return view('gestion.deudas-pension-alimentos.show', compact('deuda', 'declaracion'));
    }

    public function guardarCertificado(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPuedeGestionarDeuda($request, $deuda);
        $request->validate([
            'certificado_deuda' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:10240'],
        ], [
            'certificado_deuda.mimetypes' => 'El certificado debe ser un archivo PDF.',
            'certificado_deuda.mimes' => 'El certificado debe ser un archivo PDF.',
            'certificado_deuda.max' => 'El certificado no puede superar 10 MB.',
        ]);

        $archivo = $request->file('certificado_deuda');
        $pathAnterior = $deuda->certificado_deuda_path;
        $path = $archivo->storeAs(
            "deudas-pension-alimentos/{$deuda->id}/certificado",
            'certificado-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8)) . '.pdf',
            'local'
        );

        $deuda->forceFill([
            'certificado_deuda_path' => $path,
            'certificado_deuda_nombre_original' => $archivo->getClientOriginalName(),
            'certificado_deuda_mime' => 'application/pdf',
            'certificado_deuda_size' => $archivo->getSize(),
            'certificado_subido_por_user_id' => $request->user()->id,
            'certificado_subido_at' => now(),
        ])->save();
        $deuda->sincronizarEstado();

        if ($pathAnterior && $pathAnterior !== $path) {
            Storage::disk('local')->delete($pathAnterior);
        }

        return back()->with('status', 'Certificado de deuda actualizado correctamente.');
    }

    public function certificado(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPuedeGestionarDeuda($request, $deuda);

        return $this->archivoPrivado(
            $request,
            (string) $deuda->certificado_deuda_path,
            (string) ($deuda->certificado_deuda_nombre_original ?: 'certificado-deuda-pension.pdf')
        );
    }

    public function resolucion(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPuedeGestionarDeuda($request, $deuda);

        return $this->archivoPrivado(
            $request,
            (string) $deuda->resolucion_path,
            (string) ($deuda->resolucion_nombre_original ?: 'resolucion-dictamen-deuda-pension.pdf')
        );
    }

    public function declaracion(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPuedeGestionarDeuda($request, $deuda);
        $documento = $deuda->declaracionCargoPublicoActual();
        abort_unless($documento, 404);

        return $this->archivoPublico($request, $documento);
    }

    public function enviar(
        Request $request,
        SolicitudReemplazoDeudaPension $deuda,
        SolicitudReemplazoDeudaPensionService $service
    ) {
        $this->assertPuedeGestionarDeuda($request, $deuda);
        $service->enviarARemuneraciones($deuda, $request->user());

        return back()->with('status', 'Antecedentes enviados correctamente a la encargada de remuneraciones.');
    }

    private function assertPuedeGestionarSolicitud(Request $request, SolicitudReemplazo $solicitud): void
    {
        $this->assertRolActivo($request);
        if ($this->rolActivo($request) !== 'admin') {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $request->user()->id, 403);
        }
    }

    private function assertPuedeGestionarDeuda(Request $request, SolicitudReemplazoDeudaPension $deuda): void
    {
        $deuda->loadMissing('solicitud');
        $this->assertPuedeGestionarSolicitud($request, $deuda->solicitud);
    }

    private function assertRolActivo(Request $request): void
    {
        abort_unless(in_array($this->rolActivo($request), ['admin', 'funcionario_slep'], true), 403);
    }

    private function rolActivo(Request $request): string
    {
        $user = $request->user();

        return $user && method_exists($user, 'activeRoleName') ? (string) $user->activeRoleName() : '';
    }

    private function archivoPrivado(Request $request, string $path, string $nombre)
    {
        $disk = Storage::disk('local');
        abort_unless($path !== '' && $disk->exists($path), 404);
        $nombre = $this->nombreSeguro($nombre);

        return $request->boolean('download')
            ? $disk->download($path, $nombre)
            : response()->file($disk->path($path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . addslashes($nombre) . '"',
            ]);
    }

    private function archivoPublico(Request $request, UserDocument $documento)
    {
        $disk = Storage::disk('public');
        abort_unless($documento->path && $disk->exists($documento->path), 404);
        $nombre = $this->nombreSeguro($documento->original_name ?: basename($documento->path));

        return $request->boolean('download')
            ? $disk->download($documento->path, $nombre)
            : response()->file($disk->path($documento->path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . addslashes($nombre) . '"',
            ]);
    }

    private function nombreSeguro(string $nombre): string
    {
        $nombre = preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '', basename($nombre)) ?: 'documento.pdf';

        return Str::limit($nombre, 180, '');
    }
}
