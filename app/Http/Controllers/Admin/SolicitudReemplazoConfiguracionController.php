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

        $configuracion = SolicitudReemplazoConfiguracion::query()->firstOrCreate(
            ['clave' => SolicitudReemplazoConfiguracion::CORREO_AUTORIZACIONES_DOCENTES],
            [
                'nombre' => 'Correo para autorizaciones docentes',
                'descripcion' => 'Destinatario institucional de los expedientes enviados por UATP para solicitar una autorización docente.',
                'activo' => true,
            ]
        );

        return view('admin.solicitudes-reemplazo-configuracion.edit', compact('configuracion'));
    }

    public function update(Request $request)
    {
        $this->assertAdminActivo($request);

        $data = $request->validate([
            'correo_autorizaciones_docentes' => ['required', 'email:rfc', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'correo_autorizaciones_docentes' => 'correo para autorizaciones docentes',
        ]);

        SolicitudReemplazoConfiguracion::query()->updateOrCreate(
            ['clave' => SolicitudReemplazoConfiguracion::CORREO_AUTORIZACIONES_DOCENTES],
            [
                'valor' => trim((string) $data['correo_autorizaciones_docentes']),
                'nombre' => 'Correo para autorizaciones docentes',
                'descripcion' => 'Destinatario institucional de los expedientes enviados por UATP para solicitar una autorización docente.',
                'activo' => $request->boolean('activo'),
                'updated_by' => $request->user()->id,
            ]
        );

        return redirect()
            ->route('admin.solicitudes-reemplazo-configuracion.edit')
            ->with('success', 'Configuración de autorizaciones docentes actualizada.');
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
