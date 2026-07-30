<?php

namespace App\Http\Controllers;

use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use App\Services\FuncionarioAcImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

class FuncionarioAcController extends Controller
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

    public function registerForm(): View
    {
        return view('auth.funcionario-ac.register');
    }

    public function registerStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'run' => ['required', 'string', 'max:12'],
            'dv' => ['required', 'string', 'max:2'],
            'nombres' => ['nullable', 'string', 'max:120'],
            'apellido_paterno' => ['nullable', 'string', 'max:120'],
            'apellido_materno' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ], [], [
            'run' => 'RUN',
            'dv' => 'digito verificador',
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
        ]);

        $run = $this->onlyRun($data['run'] ?? '');
        $dv = $this->dv($data['dv'] ?? '');
        $rutNormalizado = $run . $dv;

        $autorizacion = FuncionarioAcAutorizado::query()
            ->where('rut_normalizado', $rutNormalizado)
            ->first();

        if (!$autorizacion || !$autorizacion->estaActivo()) {
            return back()->withErrors([
                'run' => 'El RUN ingresado no se encuentra autorizado para registro como Funcionario Administracion Central, o la autorizacion no esta vigente.',
            ])->withInput();
        }

        $existingUser = User::query()
            ->whereRaw("REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '') = ?", [$rutNormalizado])
            ->first();

        Role::findOrCreate('funcionario_ac', 'web');

        if ($existingUser) {
            if (!$existingUser->hasRole('funcionario_ac')) {
                $existingUser->assignRole('funcionario_ac');
            }

            $autorizacion->update([
                'registered_user_id' => $existingUser->id,
                'registered_at' => now(),
                'last_import_message' => 'Usuario existente: rol funcionario_ac asignado. Debe ingresar con sus credenciales ya creadas.',
            ]);

            return redirect()
                ->route('funcionario-ac.login')
                ->with('status', 'Tu RUN ya tenia una cuenta creada. Se agrego el rol Funcionario Administracion Central. Ingresa con tus credenciales ya creadas.');
        }

        $missing = [];
        foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'email', 'password'] as $field) {
            if (empty($data[$field])) {
                $missing[$field] = 'Este campo es obligatorio para crear la cuenta.';
            }
        }

        if ($missing !== []) {
            return back()->withErrors($missing)->withInput();
        }

        if (User::query()->where('email', strtolower(trim((string) $data['email'])))->exists()) {
            return back()->withErrors(['email' => 'Este correo ya esta registrado. Usa el acceso normal o recupera tu contrasena.'])->withInput();
        }

        try {
            $user = DB::transaction(function () use ($data, $run, $dv, $rutNormalizado, $autorizacion) {
                $user = User::query()->create([
                    'rut' => $rutNormalizado,
                    'nombres' => $data['nombres'],
                    'apellido_paterno' => $data['apellido_paterno'],
                    'apellido_materno' => $data['apellido_materno'],
                    'email' => strtolower(trim((string) $data['email'])),
                    'establecimiento_id' => null,
                    'password' => $data['password'],
                ]);

                $user->assignRole('funcionario_ac');

                $autorizacion->update([
                    'run' => $run,
                    'dv' => $dv,
                    'registered_user_id' => $user->id,
                    'registered_at' => now(),
                    'last_import_message' => 'Usuario creado desde registro especial de Funcionario Administracion Central.',
                ]);

                return $user;
            });
        } catch (\Throwable $e) {
            Log::error('No fue posible crear usuario funcionario_ac.', [
                'rut' => $rutNormalizado,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['register' => 'No fue posible completar el registro. Intenta nuevamente o contacta soporte.'])->withInput();
        }

        $user->sendEmailVerificationNotification();
        Auth::login($user);

        return redirect()->route('verification.notice')
            ->with('status', 'Cuenta creada como Funcionario Administracion Central. Te enviamos un enlace de verificacion a tu correo.');
    }

    public function loginForm(): View
    {
        return view('auth.funcionario-ac.login');
    }

    public function adminImportForm(Request $request): View
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        $filters = $this->funcionariosAcFilters($request);
        $query = FuncionarioAcAutorizado::query()->with('registeredUser.roles');
        $this->aplicarFiltrosFuncionariosAc($query, $filters);

        $statsQuery = clone $query;
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'activos' => (clone $statsQuery)->where('estado_autorizacion', 'activo')->count(),
            'vinculados' => (clone $statsQuery)->whereNotNull('registered_user_id')->count(),
        ];
        $stats['pendientes'] = max($stats['total'] - $stats['vinculados'], 0);

        $funcionarios = $query
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('tramites.cargas-familiares.funcionarios-ac.import', [
            'stats' => $stats,
            'funcionarios' => $funcionarios,
            'filters' => $filters,
            'subdireccionesDependencia' => $this->subdireccionesDependencia,
        ]);
    }

    public function adminExport(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        $filters = $this->funcionariosAcFilters($request);
        $query = FuncionarioAcAutorizado::query()->with('registeredUser.roles');
        $this->aplicarFiltrosFuncionariosAc($query, $filters);

        $funcionarios = $query
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Funcionarios AC');

        $headers = [
            'RUN',
            'Nombre completo',
            'Unidad',
            'Subdirección dependencia',
            'Calidad jurídica',
            'Escalafón',
            'Grado',
            'Cargo / función',
            'Teléfono',
            'Fecha nacimiento',
            'Email funcionario AC',
            'Jefatura',
            'Estado',
            'Usuario vinculado',
            'Correo usuario',
            'Fecha registro usuario',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');
        $sheet->getStyle('A1:P1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:P1');

        $row = 2;
        foreach ($funcionarios as $funcionario) {
            $observaciones = (string) ($funcionario->observaciones ?? '');
            $usuario = $funcionario->registeredUser;

            $sheet->fromArray([
                $funcionario->rut_completo,
                $funcionario->nombre_completo,
                $funcionario->unidad_departamento ?: $this->extraerDatoAdministrativoDesdeObservacion($observaciones, 'unidad') ?: '',
                $funcionario->subdireccion_dependencia ?: $this->extraerDatoAdministrativoDesdeObservacion($observaciones, 'subdireccion_dependencia') ?: '',
                $funcionario->calidad_juridica ?: $this->extraerDatoAdministrativoDesdeObservacion($observaciones, 'calidad_juridica') ?: '',
                $funcionario->escalafon ?: $this->extraerDatoAdministrativoDesdeObservacion($observaciones, 'escalafon') ?: '',
                $funcionario->grado ?: '',
                $funcionario->cargo_funcion ?: '',
                $funcionario->telefono ?: '',
                ! empty($funcionario->fecha_nacimiento) ? \Illuminate\Support\Carbon::parse($funcionario->fecha_nacimiento)->format('d-m-Y') : '',
                $funcionario->email ?: '',
                $funcionario->jefatura ? 'Sí' : 'No',
                $funcionario->estado_autorizacion ?: '',
                $usuario ? 'Vinculado' : 'Pendiente',
                $usuario?->email ?: '',
                optional($funcionario->registered_at)->format('d-m-Y H:i') ?: '',
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'funcionarios_ac_autorizados_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function adminImportStore(Request $request, FuncionarioAcImportService $service): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ], [
            'excel.required' => 'Debes seleccionar un archivo Excel.',
            'excel.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
        ]);

        try {
            $summary = $service->import($request->file('excel'), (int) $request->user()->id);

            return redirect()
                ->route('tramites.cargas-familiares.admin.funcionarios-ac.import')
                ->with('status', 'Carga masiva de funcionarios AC procesada correctamente.')
                ->with('import_summary', $summary);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error importando funcionarios AC', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['excel' => 'No fue posible importar el archivo. Detalle: ' . $e->getMessage()])->withInput();
        }
    }

    public function adminDownloadTemplate(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'funcionario_slep', 'coordinador_gdp']), 403);

        $path = resource_path('templates/plantilla-funcionarios-ac-carga-masiva.xlsx');
        abort_unless(is_file($path), 404);

        return response()->download($path, 'plantilla-funcionarios-ac-carga-masiva.xlsx');
    }


    private function funcionariosAcFilters(Request $request): array
    {
        return [
            'rut' => trim((string) $request->query('rut', '')),
            'nombre' => trim((string) $request->query('nombre', '')),
            'subdireccion' => trim((string) $request->query('subdireccion', '')),
        ];
    }

    private function aplicarFiltrosFuncionariosAc($query, array $filters): void
    {
        if ($filters['rut'] !== '') {
            $rut = preg_replace('/[^0-9kK]/', '', $filters['rut']) ?? '';
            $rutLike = '%' . $rut . '%';
            $textoLike = '%' . $filters['rut'] . '%';

            $query->where(function ($q) use ($rutLike, $textoLike) {
                $q->where('rut_normalizado', 'like', $rutLike)
                    ->orWhere('run', 'like', $rutLike)
                    ->orWhere('dv', 'like', $rutLike)
                    ->orWhereRaw("CONCAT(COALESCE(run, ''), COALESCE(dv, '')) LIKE ?", [$rutLike])
                    ->orWhereRaw("CONCAT(COALESCE(run, ''), '-', COALESCE(dv, '')) LIKE ?", [$textoLike]);
            });
        }

        if ($filters['nombre'] !== '') {
            $nombreLike = '%' . $filters['nombre'] . '%';
            $query->where(function ($q) use ($nombreLike) {
                $q->where('nombres', 'like', $nombreLike)
                    ->orWhere('apellido_paterno', 'like', $nombreLike)
                    ->orWhere('apellido_materno', 'like', $nombreLike)
                    ->orWhereRaw("CONCAT(COALESCE(nombres, ''), ' ', COALESCE(apellido_paterno, ''), ' ', COALESCE(apellido_materno, '')) LIKE ?", [$nombreLike]);
            });
        }

        if ($filters['subdireccion'] !== '') {
            $subdireccionLike = '%' . $filters['subdireccion'] . '%';
            $query->where(function ($q) use ($subdireccionLike) {
                $q->where('subdireccion_dependencia', 'like', $subdireccionLike)
                    ->orWhere('observaciones', 'like', $subdireccionLike);
            });
        }
    }

    private function extraerDatoAdministrativoDesdeObservacion(?string $observacion, string $campo): ?string
    {
        $observacion = trim((string) $observacion);
        if ($observacion === '') {
            return null;
        }

        $patrones = [
            'unidad' => '/Unidad:\s*(.*?)(?:\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'subdireccion_dependencia' => '/Subdirecci[oó]n dependencia:\s*(.*?)(?:\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'escalafon' => '/Escalaf[oó]n:\s*(.*?)(?:\s+Calidad jur[ií]dica:|$)/iu',
            'calidad_juridica' => '/Calidad jur[ií]dica:\s*(.*?)$/iu',
        ];

        if (! isset($patrones[$campo])) {
            return null;
        }

        if (preg_match($patrones[$campo], $observacion, $coincidencia)) {
            return trim($coincidencia[1] ?? '') ?: null;
        }

        return null;
    }

    private function onlyRun(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    private function dv(string $value): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', $value) ?? '');
    }
}
