<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazoConfiguracion;
use Illuminate\Http\Request;

class SolicitudReemplazoConfiguracionController extends Controller
{
    public function edit(Request $request)
    {
        $this->assertAdminActivo($request);

        $correoAutorizaciones = SolicitudReemplazoConfiguracion::query()->firstOrCreate(
            ['clave' => SolicitudReemplazoConfiguracion::CORREO_AUTORIZACIONES_DOCENTES],
            [
                'nombre' => 'Correo para autorizaciones docentes',
                'descripcion' => 'Destinatario institucional de los expedientes enviados por UATP para solicitar una autorización docente.',
                'activo' => true,
            ]
        );

        $correoRemuneraciones = SolicitudReemplazoConfiguracion::query()->firstOrCreate(
            ['clave' => SolicitudReemplazoConfiguracion::CORREO_REMUNERACIONES_DEUDA_PENSION],
            [
                'nombre' => 'Correo encargada de remuneraciones',
                'descripcion' => 'Destinataria de antecedentes de postulantes con deuda de pensión de alimentos.',
                'activo' => false,
            ]
        );

        return view('admin.solicitudes-reemplazo-configuracion.edit', compact('correoAutorizaciones', 'correoRemuneraciones'));
    }

    public function update(Request $request)
    {
        $this->assertAdminActivo($request);

        $data = $request->validate([
            'correo_autorizaciones_docentes' => ['required', 'email:rfc', 'max:255'],
            'autorizaciones_docentes_activo' => ['nullable', 'boolean'],
            'correo_encargada_remuneraciones' => ['nullable', 'required_if:deuda_pension_activo,1', 'email:rfc', 'max:255'],
            'deuda_pension_activo' => ['nullable', 'boolean'],
        ], [], [
            'correo_autorizaciones_docentes' => 'correo para autorizaciones docentes',
            'correo_encargada_remuneraciones' => 'correo de la encargada de remuneraciones',
        ]);

        SolicitudReemplazoConfiguracion::query()->updateOrCreate(
            ['clave' => SolicitudReemplazoConfiguracion::CORREO_AUTORIZACIONES_DOCENTES],
            [
                'valor' => trim((string) $data['correo_autorizaciones_docentes']),
                'nombre' => 'Correo para autorizaciones docentes',
                'descripcion' => 'Destinatario institucional de los expedientes enviados por UATP para solicitar una autorización docente.',
                'activo' => $request->boolean('autorizaciones_docentes_activo'),
                'updated_by' => $request->user()->id,
            ]
        );

        SolicitudReemplazoConfiguracion::query()->updateOrCreate(
            ['clave' => SolicitudReemplazoConfiguracion::CORREO_REMUNERACIONES_DEUDA_PENSION],
            [
                'valor' => trim((string) ($data['correo_encargada_remuneraciones'] ?? '')) ?: null,
                'nombre' => 'Correo encargada de remuneraciones',
                'descripcion' => 'Destinataria de antecedentes de postulantes con deuda de pensión de alimentos.',
                'activo' => $request->boolean('deuda_pension_activo'),
                'updated_by' => $request->user()->id,
            ]
        );

        return redirect()
            ->route('admin.solicitudes-reemplazo-configuracion.edit')
            ->with('success', 'Configuración de solicitudes de reemplazo actualizada.');
    }

    private function assertAdminActivo(Request $request): void
    {
        $user = $request->user();
        $activeRole = $user && method_exists($user, 'activeRoleName')
            ? (string) $user->activeRoleName()
            : '';

        abort_unless($activeRole === 'admin', 403);
    }
}
