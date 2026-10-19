<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use App\Support\DotacionSobredotacionCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DotacionSobredotacionJustificacionController extends Controller
{
    public function store(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $this->authorizeRequest($request, $establecimiento);
        $usuario = $request->user();

        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'bloque' => ['required', Rule::in(['plan_estudio', 'parvularia', 'pie'])],
            'tipo_horas' => ['required', Rule::in(['titular', 'contrata'])],
            'docente_rut' => ['required', 'string', 'max:32'],
            'justificacion' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $anio = (int) $datos['anio'];
        $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio);
        $proceso2027 = DotacionProceso2027Calculator::resumen($establecimiento, $anio, $data);
        if ($proceso2027['aplica'] ?? false) {
            $data['docentes'] = collect($proceso2027['docentes'])
                ->reject(fn (array $docente) => isset($docente['cupo_contrata_id']))
                ->values();
        }
        $sobredotacion = DotacionSobredotacionCalculator::build(
            $data['docentes'], $data['resumen'], data_get($data, 'asignacion.necesidades.funciones', [])
        );
        $rut = DotacionEstablecimientoCalculator::normalizeRut($datos['docente_rut']);
        $horas = DotacionSobredotacionJustificacion::horasVacantes(
            $sobredotacion, $datos['bloque'], $rut, $datos['tipo_horas']
        );

        if ($rut === '' || $horas <= 0.01) {
            throw ValidationException::withMessages([
                'docente_rut' => 'Este docente ya no tiene horas sin asignación de ese tipo y bloque. Actualiza el detalle.',
            ]);
        }

        $justificacion = DotacionSobredotacionJustificacion::query()->firstOrNew([
            'establecimiento_id' => $establecimiento->id,
            'anio' => $anio,
            'docente_rut_normalizado' => $rut,
            'bloque' => $datos['bloque'],
            'tipo_horas' => $datos['tipo_horas'],
        ]);
        if (! $justificacion->exists) {
            $justificacion->created_by = $usuario->id;
        }
        $justificacion->fill([
            'horas_detectadas' => $horas,
            'justificacion' => trim($datos['justificacion']),
            'updated_by' => $usuario->id,
        ])->save();

        return redirect()->to(route('admin.dotacion-establecimiento.show', [
            $establecimiento, 'anio' => $anio, 'tab' => 'sobredotacion',
        ]).'#vacantes-'.$datos['bloque'])->with('success', 'Justificación guardada para el docente y bloque seleccionados.');
    }

    public function destroy(Request $request, Establecimiento $establecimiento, int $justificacion): RedirectResponse
    {
        $this->authorizeRequest($request, $establecimiento);
        $datos = $request->validate(['anio' => ['required', 'integer', 'min:2020', 'max:2100']]);
        $anio = (int) $datos['anio'];
        $registro = DotacionSobredotacionJustificacion::query()
            ->whereKey($justificacion)
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->firstOrFail();
        $bloque = $registro->bloque;
        $rut = DotacionEstablecimientoCalculator::normalizeRut($registro->docente_rut_normalizado);
        $registro->delete();

        $quedanJustificaciones = in_array($rut,
            DotacionSobredotacionJustificacion::rutsJustificados((int) $establecimiento->id, $anio), true);
        $mensaje = $quedanJustificaciones
            ? 'Justificación eliminada. El docente conserva otras justificaciones en este establecimiento y año; sigue excluido de otras funciones.'
            : 'Justificación eliminada. Se retiró la exclusión por justificación; el docente podrá seleccionarse según su saldo y los requisitos de la función.';

        return redirect()->to(route('admin.dotacion-establecimiento.show', [
            $establecimiento, 'anio' => $anio, 'tab' => 'sobredotacion',
        ]).'#vacantes-'.$bloque)->with('success', $mensaje);
    }

    private function authorizeRequest(Request $request, Establecimiento $establecimiento): void
    {
        $usuario = $request->user();
        $rol = $usuario?->activeRoleName();
        abort_unless(in_array($rol, ['admin', 'supervisor_plani'], true)
            || ($rol === 'funcionario_directivo_estab'
                && (int) $usuario->establecimiento_id === (int) $establecimiento->id), 403);
        abort_if((bool) $establecimiento->sala_cuna, 404);
        abort_unless(Schema::hasTable('dotacion_sobredotacion_justificaciones'), 503,
            'Debe ejecutar las migraciones antes de gestionar justificaciones.');
    }
}
