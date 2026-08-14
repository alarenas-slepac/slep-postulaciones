<?php

namespace App\Http\Controllers\Postulante;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazoDeudaPension;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DeudaPensionAlimentosController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()?->postulantProfile;
        $deudas = $profile
            ? SolicitudReemplazoDeudaPension::query()
                ->with(['solicitud.establecimiento', 'postulante.user.documents.type'])
                ->where('postulant_profile_id', $profile->id)
                ->latest('activado_at')
                ->paginate(15)
            : null;

        return view('postulant.deudas-pension-alimentos.index', compact('profile', 'deudas'));
    }

    public function show(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPropietario($request, $deuda);
        $deuda->load(['solicitud.establecimiento', 'postulante.user.documents.type', 'resolucionSubidaPor']);
        $declaracion = $deuda->declaracionCargoPublicoActual();

        return view('postulant.deudas-pension-alimentos.show', compact('deuda', 'declaracion'));
    }

    public function guardarResolucion(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPropietario($request, $deuda);
        $request->validate([
            'resolucion' => ['required', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:10240'],
        ], [
            'resolucion.mimetypes' => 'La resolución o dictamen debe ser un archivo PDF.',
            'resolucion.mimes' => 'La resolución o dictamen debe ser un archivo PDF.',
            'resolucion.max' => 'La resolución o dictamen no puede superar 10 MB.',
        ], [
            'resolucion' => 'resolución o dictamen',
        ]);

        $archivo = $request->file('resolucion');
        $pathAnterior = $deuda->resolucion_path;
        $path = $archivo->storeAs(
            "deudas-pension-alimentos/{$deuda->id}/resolucion",
            'resolucion-' . now()->format('YmdHis') . '-' . Str::lower(Str::random(8)) . '.pdf',
            'local'
        );

        $deuda->forceFill([
            'resolucion_path' => $path,
            'resolucion_nombre_original' => $archivo->getClientOriginalName(),
            'resolucion_mime' => 'application/pdf',
            'resolucion_size' => $archivo->getSize(),
            'resolucion_subida_por_user_id' => $request->user()->id,
            'resolucion_subida_at' => now(),
        ])->save();
        $deuda->sincronizarEstado();

        if ($pathAnterior && $pathAnterior !== $path) {
            Storage::disk('local')->delete($pathAnterior);
        }

        return back()->with('status', 'Resolución o dictamen actualizado correctamente.');
    }

    public function certificado(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPropietario($request, $deuda);

        return $this->archivoPrivado(
            $request,
            (string) $deuda->certificado_deuda_path,
            (string) ($deuda->certificado_deuda_nombre_original ?: 'certificado-deuda-pension.pdf')
        );
    }

    public function resolucion(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPropietario($request, $deuda);

        return $this->archivoPrivado(
            $request,
            (string) $deuda->resolucion_path,
            (string) ($deuda->resolucion_nombre_original ?: 'resolucion-dictamen-deuda-pension.pdf')
        );
    }

    public function declaracion(Request $request, SolicitudReemplazoDeudaPension $deuda)
    {
        $this->assertPropietario($request, $deuda);
        $documento = $deuda->declaracionCargoPublicoActual();
        abort_unless($documento, 404);

        return $this->archivoPublico($request, $documento);
    }

    private function assertPropietario(Request $request, SolicitudReemplazoDeudaPension $deuda): void
    {
        $profile = $request->user()?->postulantProfile;
        abort_unless($profile, 404);
        abort_unless((int) $deuda->postulant_profile_id === (int) $profile->id, 403);
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
