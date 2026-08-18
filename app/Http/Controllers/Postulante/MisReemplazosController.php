<?php

namespace App\Http\Controllers\Postulante;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class MisReemplazosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user?->postulantProfile;

        if (!$profile) {
            return view('postulant.reemplazos.index', [
                'profile' => null,
                'today' => Carbon::today(config('app.display_timezone', 'America/Santiago')),
                'activos' => collect(),
                'futuros' => collect(),
                'historial' => collect(),
            ]);
        }

        $today = Carbon::today(config('app.display_timezone', 'America/Santiago'))->toDateString();

        $base = SolicitudReemplazo::query()
            ->with([
                'establecimiento',
                'funcionarioTitular',
                'areaDesempeno',
                'observacionSlepUser',
            ])
            ->where('postulant_profile_id', $profile->id)
            ->whereNotNull('fecha_inicio_trabajo');

        $activos = (clone $base)
            ->where('estado', 'aceptada')
            ->whereDate('fecha_inicio_trabajo', '<=', $today)
            ->whereDate('fecha_termino', '>=', $today)
            ->orderBy('fecha_inicio_trabajo')
            ->orderBy('numero_solicitud')
            ->get();

        $futuros = (clone $base)
            ->where('estado', 'aceptada')
            ->whereDate('fecha_inicio_trabajo', '>', $today)
            ->orderBy('fecha_inicio_trabajo')
            ->orderBy('numero_solicitud')
            ->get();

        $historial = (clone $base)
            ->where(function ($q) use ($today) {
                $q->where('estado', 'cerrado')
                    ->orWhereDate('fecha_termino', '<', $today);
            })
            ->orderByDesc('fecha_termino')
            ->orderByDesc('fecha_inicio_trabajo')
            ->orderByDesc('id')
            ->get();

        return view('postulant.reemplazos.index', [
            'profile' => $profile,
            'today' => Carbon::parse($today),
            'activos' => $activos,
            'futuros' => $futuros,
            'historial' => $historial,
        ]);
    }

    public function show(Request $request, SolicitudReemplazo $solicitud)
    {
        $profile = $request->user()?->postulantProfile;

        abort_unless($profile, 404);
        abort_unless((int) $solicitud->postulant_profile_id === (int) $profile->id, 403);

        $solicitud->load([
            'establecimiento',
            'funcionarioTitular',
            'areaDesempeno',
            'postulante.user',
            'observacionSlepUser',
            'jornadas',
            'contratoTrabajoFirmadoSubidoPor',
            'contratoTrabajoFirmadoEnviadoPor',
            'cerradoPor',
        ]);

        return view('postulant.reemplazos.show', [
            'profile' => $profile,
            'solicitud' => $solicitud,
        ]);
    }

    public function ordenTrabajo(Request $request, SolicitudReemplazo $solicitud)
    {
        $profile = $request->user()?->postulantProfile;

        abort_unless($profile, 404);
        abort_unless((int) $solicitud->postulant_profile_id === (int) $profile->id, 403);

        $path = (string) ($solicitud->orden_trabajo_pdf_path ?? '');
        abort_unless($path !== '', 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $filename = 'orden_trabajo_' . ($solicitud->numero_solicitud ?? $solicitud->id) . '.pdf';
        return response()->file($disk->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function resolucionDocenteFirmada(Request $request, SolicitudReemplazo $solicitud)
    {
        $profile = $request->user()?->postulantProfile;
        abort_unless($profile, 404);
        abort_unless((int) $solicitud->postulant_profile_id === (int) $profile->id, 403);
        $path = (string) ($solicitud->resolucion_docente_firmada_pdf_path ?? '');
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="resolucion_docente_firmada.pdf"']);
    }

    public function contratoFirmado(Request $request, SolicitudReemplazo $solicitud)
    {
        $profile = $request->user()?->postulantProfile;

        abort_unless($profile, 404);
        abort_unless((int) $solicitud->postulant_profile_id === (int) $profile->id, 403);

        $path = (string) ($solicitud->contrato_trabajo_firmado_pdf_path ?? '');
        abort_unless($path !== '', 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $filename = 'contrato_trabajo_firmado_' . ($solicitud->numero_solicitud ?? $solicitud->id) . '.pdf';
        return response()->file($disk->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function horarioTitular(Request $request, SolicitudReemplazo $solicitud)
    {
        $profile = $request->user()?->postulantProfile;

        abort_unless($profile, 404);
        abort_unless((int) $solicitud->postulant_profile_id === (int) $profile->id, 403);

        $path = (string) ($solicitud->horario_titular_pdf_path ?? '');
        abort_unless($path !== '', 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $filename = 'horario_titular_' . ($solicitud->numero_solicitud ?? $solicitud->id) . '.pdf';
        return response()->file($disk->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
