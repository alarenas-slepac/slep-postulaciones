<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Services\ReemplazoDocumentosAcademicosExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReemplazoDocumentosAcademicosController extends Controller
{
    public function index(Request $request, ReemplazoDocumentosAcademicosExport $export)
    {
        $this->authorizeAdmin($request);
        $personas = $export->peopleQuery()->paginate(25);
        $personas->setCollection($personas->getCollection()->map(fn ($persona) => $export->preview($persona)));

        return view('gestion.solicitudes-reemplazo.documentos-academicos', [
            'personas' => $personas,
            'totalSolicitudes' => $export->requestsQuery()->count(),
            'sinPerfil' => $export->missingProfilesQuery()->count(),
            'tipos' => ReemplazoDocumentosAcademicosExport::DOCUMENTS,
        ]);
    }

    public function download(Request $request, ReemplazoDocumentosAcademicosExport $export): BinaryFileResponse
    {
        $this->authorizeAdmin($request);
        if (! $export->peopleQuery()->exists()) {
            throw ValidationException::withMessages(['documentos' => 'No hay reemplazantes con perfil asociado en solicitudes aceptadas o cerradas.']);
        }

        try {
            $path = $export->generate();
        } catch (\Throwable $error) {
            // Sin rutas físicas, datos personales ni contenido de los documentos.
            Log::warning('No fue posible generar los documentos académicos de reemplazos.', ['exception_type' => $error::class]);
            throw ValidationException::withMessages(['documentos' => 'No fue posible generar el ZIP. Intente nuevamente o informe al administrador del sistema.']);
        }

        return response()->download($path, 'documentos_academicos_reemplazos_'.now()->format('Ymd_His').'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store, max-age=0',
        ])->deleteFileAfterSend(true);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin') && $request->user()->activeRoleName() === 'admin', 403);
    }
}
