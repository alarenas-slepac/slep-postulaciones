<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\OrdenTrabajoPdfService;

class OrdenTrabajoPdfController extends Controller
{
    public function show(Request $request, SolicitudReemplazo $solicitud)
    {
        return $this->serve($request, $solicitud, false);
    }

    public function download(Request $request, SolicitudReemplazo $solicitud)
    {
        return $this->serve($request, $solicitud, true);
    }

    private function serve(Request $request, SolicitudReemplazo $solicitud, bool $download)
    {
        $user = $request->user();

        $allowedRoles = [
            'admin',
            'coordinador_uatp',
            'coordinador_gdp',
            'coordinador_gdp_admin',
            'funcionario_slep',
            'supervisor_plani',
            'funcionario_estab',
        ];
        $activeRole = $user && method_exists($user, 'activeRoleName')
            ? (string) $user->activeRoleName()
            : '';

        $hasAccess = $user && method_exists($user, 'hasAnyRole')
            ? in_array($activeRole, $allowedRoles, true) && $user->hasAnyRole($allowedRoles)
            : false;

        abort_unless($hasAccess, 403);

        // Seguridad extra: funcionario_estab solo puede ver OT de su establecimiento
        if ($activeRole === 'funcionario_estab') {
            $user->loadMissing('establecimiento');
            abort_unless($user->establecimiento && (int) $solicitud->establecimiento_id === (int) $user->establecimiento->id, 403);
        }

        if ($request->boolean('regenerar')) {
            $canRegenerate = $user && method_exists($user, 'hasAnyRole')
                ? $activeRole !== 'supervisor_plani'
                    && $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
                : false;

            if ($canRegenerate) {
                $path = app(OrdenTrabajoPdfService::class)->generateAndStore($solicitud);
                $solicitud->forceFill(['orden_trabajo_pdf_path' => $path])->save();
                $solicitud->refresh();
            }
        }

        $path = (string) ($solicitud->orden_trabajo_pdf_path ?? '');
        abort_unless($path !== '', 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $filename = 'orden_trabajo_' . ($solicitud->numero_solicitud ?? $solicitud->id) . '.pdf';
        $fullPath = $disk->path($path);

        if ($download) {
            return response()->download($fullPath, $filename, ['Content-Type' => 'application/pdf']);
        }

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
