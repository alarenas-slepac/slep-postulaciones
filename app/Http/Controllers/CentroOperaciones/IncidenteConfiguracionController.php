<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Models\CentroOperacionesIncidenteConfiguracion;
use App\Models\FuncionarioAcAutorizado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IncidenteConfiguracionController extends Controller
{
    public function index(): View
    {
        $configuraciones = CentroOperacionesIncidenteConfiguracion::with('responsable')->orderBy('tipo')->get();
        $funcionarios = FuncionarioAcAutorizado::query()->where('estado_autorizacion', 'activo')
            ->whereNotNull('unidad_departamento')->whereNotNull('subdireccion_dependencia')
            ->orderBy('subdireccion_dependencia')->orderByDesc('jefatura')
            ->orderBy('apellido_paterno')->get()
            ->filter(fn (FuncionarioAcAutorizado $funcionario) => $funcionario->estaActivo())
            ->values();
        $subdirecciones = $funcionarios->pluck('subdireccion_dependencia')->unique()->sort()->values();

        return view('centro-operaciones.configuraciones.index', compact(
            'configuraciones',
            'funcionarios',
            'subdirecciones'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request, true);
        $responsable = $this->responsableSeleccionado($datos);
        $nombreNormalizado = Str::lower(Str::ascii(trim($datos['nombre'])));
        $nombreReservado = collect(config('centro_operaciones.incidencias', []))
            ->contains(fn (array $incidencia) =>
                Str::lower(Str::ascii(trim((string) ($incidencia['label'] ?? '')))) === $nombreNormalizado
            );

        if ($nombreReservado) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe una incidencia del catálogo con ese nombre.',
            ]);
        }

        $tipoBase = Str::slug($datos['nombre'], '_');

        if ($tipoBase === '') {
            throw ValidationException::withMessages([
                'nombre' => 'El nombre debe contener al menos una letra o un número.',
            ]);
        }

        $tipoBase = mb_substr($tipoBase, 0, 48);
        $tipo = $tipoBase;
        $sufijo = 2;
        $tiposReservados = array_keys(config('centro_operaciones.incidencias', []));
        while (in_array($tipo, $tiposReservados, true)
            || CentroOperacionesIncidenteConfiguracion::query()->where('tipo', $tipo)->exists()) {
            $terminacion = '_'.$sufijo;
            $tipo = mb_substr($tipoBase, 0, 48 - strlen($terminacion)).$terminacion;
            $sufijo++;
        }

        CentroOperacionesIncidenteConfiguracion::query()->create($datos + [
            'tipo' => $tipo,
            'unidad_departamento' => $responsable->unidad_departamento,
            'activo' => $request->boolean('activo'),
        ]);

        return back()->with('success', 'La incidencia fue creada y ya está disponible en el reporte diario.');
    }

    public function update(Request $request, CentroOperacionesIncidenteConfiguracion $configuracion): RedirectResponse
    {
        $datos = $this->validar($request);
        $responsable = $this->responsableSeleccionado($datos);
        $configuracion->update($datos + [
            'unidad_departamento' => $responsable->unidad_departamento,
            'activo' => $request->boolean('activo'),
        ]);

        return back()->with('success', 'Configuración actualizada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, bool $creando = false): array
    {
        $reglas = [
            'subdireccion_dependencia' => [
                'required',
                'string',
                'max:255',
                Rule::exists('funcionarios_ac_autorizados', 'subdireccion_dependencia')
                    ->where('estado_autorizacion', 'activo'),
            ],
            'responsable_funcionario_ac_id' => [
                'required',
                Rule::exists('funcionarios_ac_autorizados', 'id')
                    ->where('estado_autorizacion', 'activo'),
            ],
            'plazo_dias' => ['required', 'integer', 'between:1,365'],
            'activo' => ['nullable', 'boolean'],
        ];

        if ($creando) {
            $reglas = [
                'nombre' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('centro_operaciones_incidente_configuraciones', 'nombre'),
                ],
                'severidad' => ['required', Rule::in(['alerta', 'critico'])],
            ] + $reglas;
        }

        return $request->validate($reglas, [], [
            'subdireccion_dependencia' => 'subdirección',
            'responsable_funcionario_ac_id' => 'responsable de subdirección',
            'plazo_dias' => 'plazo',
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function responsableSeleccionado(array $datos): FuncionarioAcAutorizado
    {
        $responsable = FuncionarioAcAutorizado::query()
            ->whereKey($datos['responsable_funcionario_ac_id'])
            ->where('estado_autorizacion', 'activo')
            ->where('subdireccion_dependencia', $datos['subdireccion_dependencia'])
            ->first();

        if (! $responsable || ! $responsable->estaActivo() || ! $responsable->unidad_departamento) {
            throw ValidationException::withMessages([
                'responsable_funcionario_ac_id' => 'Selecciona un responsable activo que pertenezca a la subdirección indicada.',
            ]);
        }

        return $responsable;
    }
}
