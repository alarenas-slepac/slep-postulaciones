<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FuncionarioAcJefaturaDependenciaController extends Controller
{
    private array $tablasFuncionariosPosibles = [
        'funcionarios_ac_autorizados',
        'funcionario_ac_autorizado',
        'funcionarios_ac_autorizadas',
    ];

    private array $dependencias = [
        'Subdirección de Gestión y Desarrollo de las Personas',
        'Subdirección de Administración y Finanzas',
        'Subdirección de Planificación y Control de Gestión',
        'Subdirección de Apoyo Técnico Pedagógico',
        'Subdirección de Infraestructura y Mantenimiento',
        'Gabinete',
        'Unidad Jurídica',
        'Dirección Ejecutiva',
    ];

    public function index(): View
    {
        $this->asegurarRegistrosBase();

        $funcionariosTabla = $this->resolverTablaFuncionarios();
        $funcionarios = $this->funcionariosElegibles($funcionariosTabla);
        $jefaturas = DB::table('funcionarios_ac_jefaturas_dependencias')
            ->orderByRaw("FIELD(subdireccion_dependencia, '" . implode("','", array_map('addslashes', $this->dependencias)) . "')")
            ->orderBy('subdireccion_dependencia')
            ->get();

        return view('tramites.cargas-familiares.funcionarios-ac.jefaturas.index', [
            'jefaturas' => $jefaturas,
            'funcionarios' => $funcionarios,
            'dependencias' => $this->dependencias,
        ]);
    }

    public function update(Request $request, int $jefaturaDependencia): RedirectResponse
    {
        $registro = DB::table('funcionarios_ac_jefaturas_dependencias')->where('id', $jefaturaDependencia)->first();
        abort_if(! $registro, 404);

        $request->validate([
            'subdireccion_dependencia' => ['required', 'string', Rule::in($this->dependencias)],
            'jefatura_funcionario_ac_id' => ['nullable', 'integer'],
            'subrogante_1_funcionario_ac_id' => ['nullable', 'integer'],
            'subrogante_2_funcionario_ac_id' => ['nullable', 'integer'],
            'subrogante_3_funcionario_ac_id' => ['nullable', 'integer'],
            'subrogancia_activa' => ['nullable', 'in:0,1'],
            'subrogante_activo_nivel' => ['nullable', 'in:1,2,3'],
            'subrogancia_desde' => ['nullable', 'date'],
            'subrogancia_hasta' => ['nullable', 'date', 'after_or_equal:subrogancia_desde'],
            'motivo_subrogancia' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'activo' => ['nullable', 'in:0,1'],
        ]);

        $subroganciaActiva = (bool) $request->boolean('subrogancia_activa');
        $nivel = $subroganciaActiva ? $request->input('subrogante_activo_nivel') : null;

        DB::table('funcionarios_ac_jefaturas_dependencias')
            ->where('id', $jefaturaDependencia)
            ->update([
                'subdireccion_dependencia' => $request->input('subdireccion_dependencia'),
                'jefatura_funcionario_ac_id' => $request->input('jefatura_funcionario_ac_id') ?: null,
                'subrogante_1_funcionario_ac_id' => $request->input('subrogante_1_funcionario_ac_id') ?: null,
                'subrogante_2_funcionario_ac_id' => $request->input('subrogante_2_funcionario_ac_id') ?: null,
                'subrogante_3_funcionario_ac_id' => $request->input('subrogante_3_funcionario_ac_id') ?: null,
                'subrogancia_activa' => $subroganciaActiva,
                'subrogante_activo_nivel' => $nivel,
                'subrogancia_desde' => $subroganciaActiva ? $request->input('subrogancia_desde') : null,
                'subrogancia_hasta' => $subroganciaActiva ? $request->input('subrogancia_hasta') : null,
                'subrogancia_activada_por' => $subroganciaActiva ? auth()->id() : null,
                'motivo_subrogancia' => $subroganciaActiva ? $request->input('motivo_subrogancia') : null,
                'observaciones' => $request->input('observaciones'),
                'activo' => $request->input('activo', 1),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('tramites.cargas-familiares.admin.funcionarios-ac.jefaturas.index')
            ->with('success', 'Jefatura y subrogancia actualizadas correctamente.');
    }

    private function asegurarRegistrosBase(): void
    {
        foreach ($this->dependencias as $dependencia) {
            $existe = DB::table('funcionarios_ac_jefaturas_dependencias')
                ->where('subdireccion_dependencia', $dependencia)
                ->exists();

            if (! $existe) {
                DB::table('funcionarios_ac_jefaturas_dependencias')->insert([
                    'subdireccion_dependencia' => $dependencia,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function resolverTablaFuncionarios(): string
    {
        foreach ($this->tablasFuncionariosPosibles as $tabla) {
            if (Schema::hasTable($tabla)) {
                return $tabla;
            }
        }

        abort(500, 'No se encontró la tabla de funcionarios AC autorizados.');
    }

    private function funcionariosElegibles(string $tabla)
    {
        $columnas = Schema::getColumnListing($tabla);

        return DB::table($tabla)
            ->select($this->columnasSeleccionables($columnas))
            ->when(in_array('jefatura', $columnas, true), function ($query) {
                $query->where('jefatura', true);
            })
            ->when(in_array('activo', $columnas, true), function ($query) {
                $query->where(function ($subquery) {
                    $subquery->where('activo', true)->orWhereNull('activo');
                });
            })
            ->when(in_array('apellido_paterno', $columnas, true), fn ($query) => $query->orderBy('apellido_paterno'))
            ->when(in_array('apellido_materno', $columnas, true), fn ($query) => $query->orderBy('apellido_materno'))
            ->when(in_array('nombres', $columnas, true), fn ($query) => $query->orderBy('nombres'))
            ->get()
            ->map(function ($funcionario) {
                $funcionario->nombre_selector = trim(implode(' ', array_filter([
                    $funcionario->nombres ?? null,
                    $funcionario->apellido_paterno ?? null,
                    $funcionario->apellido_materno ?? null,
                ]))) ?: ('Funcionario AC #' . ($funcionario->id ?? ''));

                $run = $funcionario->run ?? $funcionario->rut ?? $funcionario->rut_normalizado ?? '';
                $dv = $funcionario->dv ?? $funcionario->digito_verificador ?? '';
                $funcionario->run_selector = $run && $dv && ! str_contains((string) $run, '-') ? $run . '-' . $dv : $run;

                return $funcionario;
            });
    }

    private function columnasSeleccionables(array $columnas): array
    {
        $permitidas = [
            'id', 'run', 'rut', 'dv', 'digito_verificador', 'rut_normalizado',
            'nombres', 'apellido_paterno', 'apellido_materno', 'unidad_departamento',
            'subdireccion_dependencia', 'cargo_funcion', 'jefatura', 'activo',
        ];

        return array_values(array_intersect($permitidas, $columnas));
    }
}
