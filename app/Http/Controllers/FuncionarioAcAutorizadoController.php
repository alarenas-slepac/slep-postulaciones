<?php

namespace App\Http\Controllers;

use App\Models\FuncionarioAcAutorizado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FuncionarioAcAutorizadoController extends Controller
{
    private array $subdireccionesDependencia = [
        'Subdirección de Gestión y Desarrollo de las Personas',
        'Subdirección de Administración y Finanzas',
        'Subdirección de Planificación y Control de Gestión',
        'Subdirección de Apoyo Técnico Pedagógico',
        'Subdirección de Infraestructura y Mantenimiento',
        'Gabinete',
        'Unidad Jurídica',
        'Dirección Ejecutiva',
    ];

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        return view('tramites.cargas-familiares.funcionarios-ac.edit', [
            'funcionarioAc' => new FuncionarioAcAutorizado(),
            'subdireccionesDependencia' => $this->subdireccionesDependencia,
            'modoCrear' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        $data = $this->validatedData($request);
        $data['rut_normalizado'] = $this->rutNormalizado($data['run'] ?? '', $data['dv'] ?? '');
        $data = $this->agregarAuditoriaSiExiste($data, $request->user()->id, true);
        $data = $this->prepararDatosPersistencia($data);

        FuncionarioAcAutorizado::query()->create($data);

        return redirect()
            ->route('tramites.cargas-familiares.admin.funcionarios-ac.import')
            ->with('status', 'Funcionario AC autorizado creado correctamente.');
    }

    public function edit(Request $request, FuncionarioAcAutorizado $funcionarioAc): View
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        return view('tramites.cargas-familiares.funcionarios-ac.edit', [
            'funcionarioAc' => $funcionarioAc,
            'subdireccionesDependencia' => $this->subdireccionesDependencia,
            'modoCrear' => false,
        ]);
    }

    public function update(Request $request, FuncionarioAcAutorizado $funcionarioAc): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        $data = $this->validatedData($request, $funcionarioAc->id);
        $data['rut_normalizado'] = $this->rutNormalizado($data['run'] ?? '', $data['dv'] ?? '');
        $data = $this->agregarAuditoriaSiExiste($data, $request->user()->id, false);
        $data = $this->prepararDatosPersistencia($data);

        DB::transaction(function () use ($funcionarioAc, $data) {
            $funcionarioAc->forceFill($data)->save();
        });

        return redirect()
            ->route('tramites.cargas-familiares.admin.funcionarios-ac.edit', $funcionarioAc)
            ->with('status', 'Funcionario AC autorizado actualizado correctamente.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'run' => ['required', 'string', 'max:12'],
            'dv' => ['required', 'string', 'max:2'],
            'rut_normalizado' => ['nullable', 'string', 'max:20'],
            'nombres' => ['required', 'string', 'max:120'],
            'apellido_paterno' => ['required', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'unidad_departamento' => ['nullable', 'string', 'max:190'],
            'cargo_funcion' => ['nullable', 'string', 'max:190'],
            'subdireccion_dependencia' => ['nullable', 'string', 'max:190'],
            'calidad_juridica' => ['nullable', 'string', 'max:120'],
            'escalafon' => ['nullable', 'string', 'max:120'],
            'grado' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'periodo_nomina' => ['nullable', 'string', 'max:20'],
            'accion_sistema' => ['nullable', 'string', 'max:120'],
            'estado_autorizacion' => ['nullable', Rule::in(['activo', 'inactivo', 'pendiente'])],
            'jefatura' => ['nullable', 'boolean'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
        ], [], [
            'run' => 'RUN',
            'dv' => 'DV',
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
            'unidad_departamento' => 'unidad/departamento',
            'cargo_funcion' => 'cargo/función',
            'subdireccion_dependencia' => 'subdirección dependencia',
            'calidad_juridica' => 'calidad jurídica',
            'fecha_nacimiento' => 'fecha de nacimiento',
        ]);
    }



    private function agregarAuditoriaSiExiste(array $data, int $userId, bool $incluirCreacion = false): array
    {
        if ($incluirCreacion && Schema::hasColumn('funcionarios_ac_autorizados', 'created_by')) {
            $data['created_by'] = $userId;
        }

        if (Schema::hasColumn('funcionarios_ac_autorizados', 'updated_by')) {
            $data['updated_by'] = $userId;
        }

        return $data;
    }

    private function prepararDatosPersistencia(array $data): array
    {
        if (array_key_exists('escalafon', $data) && ! Schema::hasColumn('funcionarios_ac_autorizados', 'escalafon')) {
            $data['observaciones'] = $this->actualizarObservacionAdministrativa($data['observaciones'] ?? null, 'Escalafón', $data['escalafon'] ?? null);
            unset($data['escalafon']);
        }

        return $data;
    }

    private function actualizarObservacionAdministrativa(?string $observaciones, string $etiqueta, ?string $valor): ?string
    {
        $observaciones = trim((string) $observaciones);
        $valor = trim((string) $valor);

        if ($valor === '') {
            return $observaciones !== '' ? $observaciones : null;
        }

        $pattern = '/(' . preg_quote($etiqueta, '/') . '\s*:\s*)(.*?)(?=\s+(Unidad|Subdirección dependencia|Escalafón|Calidad jurídica|Grado)\s*:|$)/iu';

        if (preg_match($pattern, $observaciones)) {
            return trim(preg_replace_callback($pattern, function (array $matches) use ($valor) {
                return $matches[1] . $valor;
            }, $observaciones, 1));
        }

        return trim($observaciones . ($observaciones !== '' ? ' ' : '') . $etiqueta . ': ' . $valor);
    }

    private function rutNormalizado(string $run, string $dv): string
    {
        $run = preg_replace('/[^0-9]/', '', $run) ?? '';
        $dv = strtoupper(preg_replace('/[^0-9Kk]/', '', $dv) ?? '');

        return $run . $dv;
    }
}
