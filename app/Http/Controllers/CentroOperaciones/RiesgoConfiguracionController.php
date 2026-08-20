<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Models\CentroOperacionesRiesgoModelo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RiesgoConfiguracionController extends Controller
{
    public function index(Request $request): View
    {
        $modelos = CentroOperacionesRiesgoModelo::query()
            ->withCount(['dimensiones', 'evaluaciones'])
            ->latest('id')
            ->get();
        $modeloId = $request->integer('modelo');
        $modelo = CentroOperacionesRiesgoModelo::query()
            ->with('dimensiones.opciones')
            ->when($modeloId, fn ($query) => $query->whereKey($modeloId))
            ->first()
            ?? CentroOperacionesRiesgoModelo::query()
                ->with('dimensiones.opciones')
                ->latest('id')
                ->firstOrFail();

        return view('centro-operaciones.riesgos.configuracion', compact('modelos', 'modelo'));
    }

    public function crearVersion(Request $request): RedirectResponse
    {
        $origen = CentroOperacionesRiesgoModelo::query()
            ->with('dimensiones.opciones')
            ->where('estado', 'publicado')
            ->latest('publicado_en')
            ->firstOrFail();

        $nueva = DB::transaction(function () use ($origen, $request) {
            $modelo = $origen->replicate([
                'version',
                'estado',
                'creado_por_id',
                'publicado_en',
            ]);
            $modelo->fill([
                'version' => $this->siguienteVersion(),
                'nombre' => $origen->nombre,
                'estado' => 'borrador',
                'creado_por_id' => $request->user()->id,
                'publicado_en' => null,
            ])->save();

            foreach ($origen->dimensiones as $dimensionOrigen) {
                $dimension = $dimensionOrigen->replicate(['modelo_id']);
                $dimension->modelo_id = $modelo->id;
                $dimension->save();
                foreach ($dimensionOrigen->opciones as $opcionOrigen) {
                    $opcion = $opcionOrigen->replicate(['dimension_id']);
                    $opcion->dimension_id = $dimension->id;
                    $opcion->save();
                }
            }

            return $modelo;
        });

        return redirect()
            ->route('centro-operaciones.riesgos.configuracion', ['modelo' => $nueva->id])
            ->with('success', 'Se creó la versión '.$nueva->version.' como borrador.');
    }

    public function update(Request $request, CentroOperacionesRiesgoModelo $modelo): RedirectResponse
    {
        $this->asegurarBorrador($modelo);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'umbral_monitoreo' => ['required', 'integer', 'between:20,100'],
            'umbral_atencion' => ['required', 'integer', 'between:20,100'],
            'umbral_critico' => ['required', 'integer', 'between:20,100'],
            'score_alerta_roja' => ['required', 'integer', 'between:1,5'],
            'vigencia_dias' => ['required', 'integer', 'between:1,730'],
            'accion_estable' => ['required', 'string', 'max:255'],
            'accion_monitoreo' => ['required', 'string', 'max:255'],
            'accion_atencion' => ['required', 'string', 'max:255'],
            'accion_critica' => ['required', 'string', 'max:255'],
            'accion_factor_critico' => ['required', 'string', 'max:255'],
            'dimensiones' => ['required', 'array'],
            'dimensiones.*.nombre' => ['required', 'string', 'max:160'],
            'dimensiones.*.pregunta' => ['required', 'string', 'max:1000'],
            'dimensiones.*.peso' => ['required', 'integer', 'between:1,100'],
            'opciones' => ['required', 'array'],
            'opciones.*.*.nombre' => ['required', 'string', 'max:190'],
            'opciones.*.*.score' => ['required', 'integer', 'between:1,5'],
        ]);
        $this->validarUmbrales($datos);

        DB::transaction(function () use ($modelo, $datos) {
            $modelo->update(collect($datos)->except(['dimensiones', 'opciones'])->all());
            $dimensiones = $modelo->dimensiones()->with('opciones')->get();
            foreach ($dimensiones as $dimension) {
                $entrada = $datos['dimensiones'][$dimension->id] ?? null;
                if (! $entrada) {
                    throw ValidationException::withMessages(['dimensiones' => 'La configuración de dimensiones está incompleta.']);
                }
                $dimension->update($entrada);
                foreach ($dimension->opciones as $opcion) {
                    $opcionEntrada = $datos['opciones'][$dimension->id][$opcion->id] ?? null;
                    if (! $opcionEntrada) {
                        throw ValidationException::withMessages(['opciones' => 'La configuración de alternativas está incompleta.']);
                    }
                    $opcion->update($opcionEntrada);
                }
            }
        });

        return back()->with('success', 'La versión borrador fue actualizada.');
    }

    public function publicar(CentroOperacionesRiesgoModelo $modelo): RedirectResponse
    {
        $this->asegurarBorrador($modelo);
        $modelo->load('dimensiones.opciones');
        $this->validarModeloPublicable($modelo);

        DB::transaction(function () use ($modelo) {
            CentroOperacionesRiesgoModelo::query()
                ->where('estado', 'publicado')
                ->where('id', '!=', $modelo->id)
                ->update(['estado' => 'reemplazado', 'updated_at' => now()]);
            $modelo->update(['estado' => 'publicado', 'publicado_en' => now()]);
        });

        return back()->with('success', 'La versión '.$modelo->version.' quedó publicada para nuevas evaluaciones.');
    }

    private function siguienteVersion(): string
    {
        $menor = CentroOperacionesRiesgoModelo::query()
            ->pluck('version')
            ->map(function (string $version) {
                return preg_match('/^1\.(\d+)$/', $version, $coincidencias)
                    ? (int) $coincidencias[1]
                    : 0;
            })->max();

        return '1.'.((int) $menor + 1);
    }

    private function asegurarBorrador(CentroOperacionesRiesgoModelo $modelo): void
    {
        abort_unless($modelo->estado === 'borrador', 422, 'Solo se pueden modificar versiones en borrador.');
    }

    /** @param array<string, mixed> $datos */
    private function validarUmbrales(array $datos): void
    {
        if (! ($datos['umbral_monitoreo'] < $datos['umbral_atencion']
            && $datos['umbral_atencion'] < $datos['umbral_critico'])) {
            throw ValidationException::withMessages([
                'umbral_monitoreo' => 'Los umbrales deben mantener el orden Monitoreo < Atención < Crítico.',
            ]);
        }
    }

    private function validarModeloPublicable(CentroOperacionesRiesgoModelo $modelo): void
    {
        if ($modelo->dimensiones->where('activo', true)->sum('peso') !== 100) {
            throw ValidationException::withMessages(['dimensiones' => 'Los pesos activos deben sumar exactamente 100%.']);
        }
        foreach ($modelo->dimensiones->where('activo', true) as $dimension) {
            $scores = $dimension->opciones->where('activo', true)->pluck('score')->sort()->values()->all();
            if ($scores !== [1, 2, 3, 4, 5]) {
                throw ValidationException::withMessages([
                    'opciones' => "{$dimension->nombre} debe tener una alternativa activa para cada score de 1 a 5.",
                ]);
            }
        }
        $this->validarUmbrales($modelo->only(['umbral_monitoreo', 'umbral_atencion', 'umbral_critico']));
    }
}
