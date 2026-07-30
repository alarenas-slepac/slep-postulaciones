<?php

namespace App\Http\Controllers\Postulante;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MisFiniquitosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $profile = $user?->postulantProfile;
        $rut = $this->rutComparable((string) ($user?->rut ?? ''));

        $finiquitos = collect();
        if ($profile || $rut !== '') {
            $finiquitos = SolicitudReemplazo::query()
                ->with([
                    'establecimiento:id,rbd,nombre_establecimiento,comuna,sala_cuna',
                    'funcionarioTitular:id,rut,nombre,estatuto',
                    'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                    'contratoPostulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                ])
                ->where('finiquito_estado', 'completado')
                ->whereNotNull('finiquito_firmado_pdf_path')
                ->where(function ($q) use ($profile, $rut) {
                    if ($profile) {
                        $q->where('postulant_profile_id', $profile->id)
                            ->orWhere('contrato_trabajo_postulant_profile_id', $profile->id);
                    }

                    if ($rut !== '') {
                        $q->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut_reemplazo_normalizado, '.', ''), '-', ''), ' ', '')) = ?", [$rut])
                            ->orWhereHas('postulante.user', function ($qq) use ($rut) {
                                $qq->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rut]);
                            })
                            ->orWhereHas('contratoPostulante.user', function ($qq) use ($rut) {
                                $qq->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rut]);
                            });
                    }
                })
                ->orderByDesc('fecha_termino')
                ->orderByDesc('fecha_inicio_trabajo')
                ->orderByDesc('id')
                ->get();
        }

        return view('postulant.finiquitos.index', [
            'profile' => $profile,
            'finiquitos' => $finiquitos,
        ]);
    }

    public function descargar(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        $profile = $user?->postulantProfile;
        $rut = $this->rutComparable((string) ($user?->rut ?? ''));

        abort_unless($this->puedeVerFiniquito($solicitud, $profile?->id, $rut), 403);
        abort_unless((string) $solicitud->finiquito_estado === 'completado', 404);

        $path = (string) ($solicitud->finiquito_firmado_pdf_path ?? '');
        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);

        $filename = 'finiquito_firmado_' . ($solicitud->numero_solicitud ?: $solicitud->id) . '.pdf';
        return Storage::disk('public')->download($path, $filename);
    }

    private function puedeVerFiniquito(SolicitudReemplazo $solicitud, ?int $profileId, string $rut): bool
    {
        if ($profileId && ((int) $solicitud->postulant_profile_id === $profileId || (int) $solicitud->contrato_trabajo_postulant_profile_id === $profileId)) {
            return true;
        }

        if ($rut === '') {
            return false;
        }

        $solicitud->loadMissing(['postulante.user', 'contratoPostulante.user']);
        $rutSolicitud = $this->rutComparable(
            (string) ($solicitud->contratoPostulante?->user?->rut
                ?: $solicitud->postulante?->user?->rut
                ?: $solicitud->rut_reemplazo_normalizado)
        );

        return $rutSolicitud !== '' && $rutSolicitud === $rut;
    }

    private function rutComparable(string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9K]/', '', $rut));
    }
}
