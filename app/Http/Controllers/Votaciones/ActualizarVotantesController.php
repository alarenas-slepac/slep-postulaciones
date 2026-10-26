<?php

namespace App\Http\Controllers\Votaciones;

use App\Exports\PadronVotacionExcel;
use App\Http\Controllers\Controller;
use App\Models\JornadaVotacion;
use App\Services\Votaciones\ActualizarVotantesExcel;
use App\Services\Votaciones\ActualizarVotantesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActualizarVotantesController extends Controller
{
    private function campos(Request $request): array
    {
        return $request->validate([
            'campos' => ['required', 'array', 'min:1', 'max:4'],
            'campos.*' => ['required', 'string', 'distinct', Rule::in(array_keys(ActualizarVotantesExcel::CAMPOS))],
        ])['campos'];
    }

    public function plantilla(Request $request, JornadaVotacion $jornada, PadronVotacionExcel $excel)
    {
        $request->validate(['modo' => ['required', Rule::in(['completo', 'actualizacion'])], 'precargar' => ['sometimes', 'boolean']]);
        $actualizacion = $request->input('modo') === 'actualizacion';
        $campos = $actualizacion ? $this->campos($request) : [];
        return $excel->descargar($excel->plantilla($jornada, $actualizacion, $campos, $request->boolean('precargar')),
            ($actualizacion ? 'plantilla_actualizar_votantes_' : 'plantilla_padron_locales_').$jornada->slug);
    }

    public function actualizar(Request $request, JornadaVotacion $jornada, ActualizarVotantesExcel $lector, ActualizarVotantesService $service, PadronVotacionExcel $excel)
    {
        $campos = $this->campos($request);
        $request->validate(['archivo_actualizacion' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'confirmar_actualizacion' => ['required', 'accepted'], 'agregar' => ['sometimes', 'boolean']]);
        try {
            $filas = $lector->leerActualizacion($request->file('archivo_actualizacion')->getRealPath(), $campos);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['archivo_actualizacion' => $e->getMessage()]);
        }
        try {
            $resultado = $service->aplicar($jornada, $filas, $campos, $request->boolean('agregar'), $request->user());
        } catch (\Throwable $e) {
            // Una excepción SQL puede contener las filas: nunca registrar sus valores.
            Log::error('Falló la actualización masiva de votantes.', ['jornada_id' => $jornada->id, 'tipo' => get_class($e)]);
            throw ValidationException::withMessages(['archivo_actualizacion' => 'No se aplicaron cambios. Ocurrió un problema al guardar la actualización; vuelva a intentarlo o contacte a Administración.']);
        }
        $request->session()->flash('resumen_actualizacion_padron', $resultado['resumen'] + ['jornada_id' => $jornada->id]);
        return $excel->descargar($excel->reporte($resultado), 'resultado_actualizacion_votantes_'.$jornada->slug.'_'.now()->format('Ymd_His'));
    }
}
