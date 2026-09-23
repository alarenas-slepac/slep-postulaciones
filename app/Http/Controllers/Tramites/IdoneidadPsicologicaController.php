<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Models\IdoneidadPsicologicaFuncionario;
use App\Models\IdoneidadPsicologicaSolicitud;
use App\Services\IdoneidadPsicologica\IdoneidadPsicologicaNominaPdfRenderer;
use App\Services\IdoneidadPsicologica\IdoneidadPsicologicaPadronService;
use App\Support\RutChile;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class IdoneidadPsicologicaController extends Controller
{
    private const ROLES_GESTION = ['admin', 'coordinador_gdp', 'funcionario_slep'];

    public function index(Request $request)
    {
        $this->autorizar($request);

        $query = IdoneidadPsicologicaSolicitud::query()
            ->with(['solicitante'])
            ->withCount([
                'funcionarios',
                'funcionarios as funcionarios_solicitados_count' => fn ($q) => $q->where('estado', 'solicitado'),
                'funcionarios as funcionarios_aceptados_count' => fn ($q) => $q->where('estado', 'aceptado'),
                'funcionarios as funcionarios_rechazados_count' => fn ($q) => $q->where('estado', 'rechazado'),
            ]);

        $estado = (string) $request->query('estado', '');
        if (array_key_exists($estado, IdoneidadPsicologicaSolicitud::ESTADOS)) {
            $query->where('estado', $estado);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_inicio', '>=', $request->query('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_termino', '<=', $request->query('hasta'));
        }

        $resumenBase = IdoneidadPsicologicaSolicitud::query();
        $resumen = [
            'procesos' => (clone $resumenBase)->count(),
            'solicitadas' => (clone $resumenBase)->where('estado', 'solicitada')->count(),
            'resueltas' => (clone $resumenBase)->where('estado', 'resuelta')->count(),
            'personas_pendientes' => IdoneidadPsicologicaFuncionario::query()->where('estado', 'solicitado')->count(),
        ];

        return view('tramites.idoneidad-psicologica.index', [
            'solicitudes' => $query->latest('solicitada_at')->latest()->paginate(12)->withQueryString(),
            'estados' => IdoneidadPsicologicaSolicitud::ESTADOS,
            'resumen' => $resumen,
        ]);
    }

    public function create(Request $request, IdoneidadPsicologicaPadronService $padron)
    {
        $this->autorizar($request);

        $buscando = $request->boolean('buscar');
        $fechas = ['fecha_inicio' => '', 'fecha_termino' => ''];
        $funcionarios = collect();

        if ($buscando) {
            $fechas = $this->validarFechas($request);
            $funcionarios = $padron->funcionariosElegibles(
                Carbon::parse($fechas['fecha_inicio']),
                Carbon::parse($fechas['fecha_termino'])
            );
            $funcionarios->each(function ($funcionario) use ($padron): void {
                $funcionario->setAttribute('rut', $padron->formatoRut($funcionario->rut));
                $funcionario->setAttribute('escalafon', $funcionario->cargo_idoneidad);
            });
        }

        return view('tramites.idoneidad-psicologica.create', compact('buscando', 'fechas', 'funcionarios'));
    }

    public function store(Request $request, IdoneidadPsicologicaPadronService $padron): RedirectResponse
    {
        $this->autorizar($request);
        $fechas = $this->validarFechas($request);
        $data = $request->validate([
            'funcionarios' => ['required', 'array', 'min:1'],
            'funcionarios.*' => ['string', 'max:80', 'distinct'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], ['funcionarios.min' => 'Selecciona al menos un funcionario para generar la solicitud.']);

        $elegibles = $padron->funcionariosElegibles(Carbon::parse($fechas['fecha_inicio']), Carbon::parse($fechas['fecha_termino']))
            ->keyBy(fn ($funcionario) => (string) $funcionario->idoneidad_key);
        $seleccionados = collect($data['funcionarios'])->map(fn ($key) => (string) $key)->unique()->map(fn (string $key) => $elegibles->get($key));

        if ($seleccionados->contains(fn ($funcionario) => $funcionario === null)) {
            throw ValidationException::withMessages(['funcionarios' => 'Uno o más funcionarios ya no pertenecen a una fuente vigente o no cumplen las condiciones del proceso. Actualiza la búsqueda antes de enviar.']);
        }

        $solicitud = DB::transaction(function () use ($request, $fechas, $data, $seleccionados, $padron) {
            $solicitud = IdoneidadPsicologicaSolicitud::create([
                'fecha_inicio' => $fechas['fecha_inicio'],
                'fecha_termino' => $fechas['fecha_termino'],
                'estado' => 'solicitada',
                'observacion' => $data['observacion'] ?? null,
                'solicitada_por' => $request->user()->id,
                'solicitada_at' => now(),
                'actualizada_por' => $request->user()->id,
            ]);

            foreach ($seleccionados as $funcionario) {
                $solicitud->funcionarios()->create([
                    'reemplazo_personal_id' => $funcionario->id,
                    'establecimiento_id' => $funcionario->establecimiento_id,
                    'rbd' => $funcionario->establecimiento?->rbd,
                    'establecimiento_nombre' => $funcionario->establecimiento?->nombre_establecimiento,
                    'comuna' => $funcionario->establecimiento?->comuna,
                    'rut' => $padron->formatoRut($funcionario->rut),
                    'rut_normalizado' => $padron->rutNormalizado($funcionario->rut),
                    'nombre' => $funcionario->nombre,
                    'estamento' => $funcionario->estatuto,
                    'cargo_funcion' => $funcionario->cargo_idoneidad,
                    'cargo_clave' => $funcionario->cargo_clave_idoneidad,
                    'cargo_origen' => $funcionario->cargo_origen_idoneidad,
                    'solicitud_reemplazo_id' => $funcionario->solicitud_reemplazo_idoneidad,
                    'tipo_contrato' => $funcionario->tipocontrato,
                    'fecha_ingreso' => $funcionario->fecha_ingreso,
                    'fecha_termino' => $funcionario->fecha_termino,
                    'estado' => 'solicitado',
                ]);
            }

            return $solicitud;
        });

        return redirect()->route('tramites.idoneidad-psicologica.show', $solicitud)
            ->with('success', 'Solicitud creada. La nómina quedó en estado Solicitado y está lista para descargar.');
    }

    public function show(Request $request, IdoneidadPsicologicaSolicitud $solicitud)
    {
        $this->autorizar($request);
        $solicitud->load(['solicitante', 'funcionarios' => fn ($query) => $query
            ->orderBy('establecimiento_nombre')->orderBy('nombre')->orderBy('id')]);

        $conteos = [
            'solicitado' => $solicitud->funcionarios->where('estado', 'solicitado')->count(),
            'aceptado' => $solicitud->funcionarios->where('estado', 'aceptado')->count(),
            'rechazado' => $solicitud->funcionarios->where('estado', 'rechazado')->count(),
        ];

        return view('tramites.idoneidad-psicologica.show', compact('solicitud', 'conteos'));
    }

    public function actualizarFuncionario(Request $request, IdoneidadPsicologicaSolicitud $solicitud, IdoneidadPsicologicaFuncionario $funcionario): RedirectResponse
    {
        $this->autorizar($request);
        abort_unless((int) $funcionario->solicitud_id === (int) $solicitud->id, 404);

        $data = $request->validate([
            'estado' => ['required', Rule::in(['aceptado', 'rechazado'])],
            'observacion_resultado' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $solicitud, $funcionario, $data): void {
            $funcionario->update([
                'estado' => $data['estado'],
                'observacion_resultado' => $data['observacion_resultado'] ?? null,
                'resultado_registrado_por' => $request->user()->id,
                'resultado_registrado_at' => now(),
            ]);
            $this->actualizarEstadoSolicitud($solicitud, $request->user()->id);
        });

        return back()->with('success', 'Resultado de ' . $funcionario->nombre . ' actualizado.');
    }

    public function plantillaResultados(Request $request, IdoneidadPsicologicaSolicitud $solicitud)
    {
        $this->autorizar($request);
        $solicitud->load('funcionarios');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resultados idoneidad');
        $sheet->fromArray(['RUT', 'ESTADO', 'OBSERVACION'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0F2FE');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:C1');
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(60);

        $row = 2;
        foreach ($solicitud->funcionarios as $funcionario) {
            $sheet->fromArray([RutChile::format($funcionario->rut), '', ''], null, "A{$row}");
            $row++;
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, "idoneidad_psicologica_resultados_{$solicitud->id}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importarResultados(Request $request, IdoneidadPsicologicaSolicitud $solicitud, IdoneidadPsicologicaPadronService $padron): RedirectResponse
    {
        $this->autorizar($request);
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], ['archivo.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).']);

        $cambios = $this->leerResultadosExcel($request->file('archivo')->getRealPath(), $solicitud, $padron);

        DB::transaction(function () use ($request, $solicitud, $cambios): void {
            foreach ($cambios as $cambio) {
                IdoneidadPsicologicaFuncionario::query()
                    ->where('solicitud_id', $solicitud->id)
                    ->where('rut_normalizado', $cambio['rut_normalizado'])
                    ->update([
                        'estado' => $cambio['estado'],
                        'observacion_resultado' => $cambio['observacion'],
                        'resultado_registrado_por' => $request->user()->id,
                        'resultado_registrado_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
            $this->actualizarEstadoSolicitud($solicitud, $request->user()->id);
        });

        $personasActualizadas = collect($cambios)->sum(fn (array $cambio): int => (int) $cambio['coincidencias']);

        return back()->with('success', "Carga masiva aplicada: {$personasActualizadas} registro(s) actualizado(s).");
    }

    public function nominaPdf(Request $request, IdoneidadPsicologicaSolicitud $solicitud)
    {
        $this->autorizar($request);

        return redirect()->route('tramites.idoneidad-psicologica.oficio.configurar', $solicitud);
    }

    public function configurarOficio(Request $request, IdoneidadPsicologicaSolicitud $solicitud)
    {
        $this->autorizar($request);

        $usuario = $request->user();
        $datos = [
            'director_regional_nombre' => 'VICTOR VALENZUELA ALVAREZ',
            'director_regional_cargo' => 'Director Regional Servicio de Salud Concepción',
            'director_ejecutivo_nombre' => 'RAMÓN ÁNGEL JARA ZAVALA',
            'director_ejecutivo_cargo' => 'Director Ejecutivo',
            'firmante_nombres' => 'Ramón Ángel',
            'firmante_apellidos' => 'Jara Zavala',
            'visador_1_nombres' => 'Camilo Eduardo',
            'visador_1_apellidos' => 'Manríquez Bocaz',
            'visador_2_nombres' => 'Makarena Fabiola',
            'visador_2_apellidos' => 'Paredes Aguilera',
            'visador_3_nombres' => 'René Alfredo',
            'visador_3_apellidos' => 'White Sánchez',
            'visador_4_nombres' => 'Karla Ariely',
            'visador_4_apellidos' => 'Muñoz Labarca',
            'contacto_nombres' => (string) $usuario?->nombres,
            'contacto_apellidos' => trim(implode(' ', array_filter([
                $usuario?->apellido_paterno,
                $usuario?->apellido_materno,
            ]))),
            'contacto_email' => (string) $usuario->email,
        ];

        return view('tramites.idoneidad-psicologica.oficio', compact('solicitud', 'datos'));
    }

    public function previsualizarOficio(Request $request, IdoneidadPsicologicaSolicitud $solicitud)
    {
        $this->autorizar($request);

        return $this->generarOficio($request, $solicitud, false);
    }

    public function descargarOficio(Request $request, IdoneidadPsicologicaSolicitud $solicitud)
    {
        $this->autorizar($request);

        return $this->generarOficio($request, $solicitud, true);
    }

    private function generarOficio(Request $request, IdoneidadPsicologicaSolicitud $solicitud, bool $descargar)
    {
        $datos = $this->validarDatosOficio($request);
        $solicitud->load(['solicitante', 'funcionarios' => fn ($query) => $query
            ->orderBy('establecimiento_nombre')->orderBy('nombre')->orderBy('id')]);

        $pdf = Pdf::loadView('pdf.idoneidad-psicologica-nomina', [
            'solicitud' => $solicitud,
            'fechaEmision' => now(),
            'datosOficio' => $datos,
            'logoDataUri' => $this->dataUri(resource_path('branding/idoneidad-psicologica/logo-oficio.png')),
            'fuenteRegularDataUri' => $this->dataUri(resource_path('fonts/certificados/century-gothic-regular.ttf'), 'font/ttf'),
            'fuenteBoldDataUri' => $this->dataUri(resource_path('fonts/certificados/century-gothic-bold.ttf'), 'font/ttf'),
            'nominaCanvas' => true,
        ])->setPaper([0, 0, 612, 964], 'portrait');
        $pdf->render();
        app(IdoneidadPsicologicaNominaPdfRenderer::class)->agregarNominaYFirma(
            $pdf->getDomPDF(),
            $solicitud->funcionarios,
            $datos
        );

        $nombreArchivo = "oficio_idoneidad_psicologica_{$solicitud->id}.pdf";

        return $descargar ? $pdf->download($nombreArchivo) : $pdf->stream($nombreArchivo);
    }

    private function validarDatosOficio(Request $request): array
    {
        $reglas = [
            'director_regional_nombre' => ['required', 'string', 'max:180'],
            'director_regional_cargo' => ['required', 'string', 'max:220'],
            'director_ejecutivo_nombre' => ['required', 'string', 'max:180'],
            'director_ejecutivo_cargo' => ['required', 'string', 'max:220'],
            'firmante_nombres' => ['required', 'string', 'max:120'],
            'firmante_apellidos' => ['required', 'string', 'max:120'],
            'contacto_nombres' => ['required', 'string', 'max:120'],
            'contacto_apellidos' => ['required', 'string', 'max:120'],
            'contacto_email' => ['required', 'email', 'max:190'],
        ];

        for ($numero = 1; $numero <= 4; $numero++) {
            $reglas["visador_{$numero}_nombres"] = ['nullable', 'string', 'max:120', "required_with:visador_{$numero}_apellidos"];
            $reglas["visador_{$numero}_apellidos"] = ['nullable', 'string', 'max:120', "required_with:visador_{$numero}_nombres"];
        }

        $datos = $request->validate($reglas, [], [
            'director_regional_nombre' => 'nombre del Director Regional',
            'director_regional_cargo' => 'cargo del Director Regional',
            'director_ejecutivo_nombre' => 'nombre del Director Ejecutivo',
            'director_ejecutivo_cargo' => 'cargo del Director Ejecutivo',
            'firmante_nombres' => 'nombres del firmante',
            'firmante_apellidos' => 'apellidos del firmante',
            'contacto_nombres' => 'nombres del contacto de Gestión de Personas',
            'contacto_apellidos' => 'apellidos del contacto de Gestión de Personas',
            'contacto_email' => 'correo del contacto de Gestión de Personas',
        ]);

        $iniciales = [
            $this->inicialesPorPartes($datos['firmante_nombres'], $datos['firmante_apellidos']),
        ];
        for ($numero = 1; $numero <= 4; $numero++) {
            $nombres = trim((string) ($datos["visador_{$numero}_nombres"] ?? ''));
            $apellidos = trim((string) ($datos["visador_{$numero}_apellidos"] ?? ''));
            if ($nombres !== '' && $apellidos !== '') {
                $iniciales[] = $this->inicialesPorPartes($nombres, $apellidos);
            }
        }

        $datos['contacto_nombre'] = $this->nombreCompletoPartes($datos['contacto_nombres'], $datos['contacto_apellidos']);
        $datos['director_ejecutivo_cargo_firma'] = $this->cargoSoloFirmante($datos['director_ejecutivo_cargo']);
        $inicialesContacto = $this->inicialesPorPartes($datos['contacto_nombres'], $datos['contacto_apellidos']);
        $iniciales[] = $inicialesContacto;
        $iniciales[] = mb_strtolower($inicialesContacto);
        $datos['iniciales_firmantes_visadores'] = implode('/', $iniciales);

        return $datos;
    }

    private function nombreCompletoPartes(string $nombres, string $apellidos): string
    {
        return trim($nombres . ' ' . $apellidos);
    }

    private function cargoSoloFirmante(string $cargo): string
    {
        $marcaServicio = 'servicio local de educación pública';
        $posicionServicio = mb_stripos($cargo, $marcaServicio);
        $cargoSolo = trim($posicionServicio === false ? $cargo : mb_substr($cargo, 0, $posicionServicio));

        return $cargoSolo !== '' ? $cargoSolo : trim($cargo);
    }

    private function inicialesPorPartes(string $nombres, string $apellidos): string
    {
        $primerNombre = preg_split('/\s+/u', trim($nombres), -1, PREG_SPLIT_NO_EMPTY)[0] ?? '';
        $partes = array_merge([$primerNombre], preg_split('/\s+/u', trim($apellidos), -1, PREG_SPLIT_NO_EMPTY));

        return mb_strtoupper(implode('', array_map(
            static fn (string $parte): string => mb_substr($parte, 0, 1),
            $partes
        )));
    }

    private function dataUri(string $ruta, string $mime = 'image/png'): ?string
    {
        if (! File::exists($ruta)) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode(File::get($ruta));
    }

    private function validarFechas(Request $request): array
    {
        return $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_termino' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ], [
            'fecha_inicio.required' => 'Indica la fecha de inicio del período.',
            'fecha_termino.required' => 'Indica la fecha de término del período.',
            'fecha_termino.after_or_equal' => 'La fecha de término debe ser igual o posterior a la fecha de inicio.',
        ]);
    }

    private function leerResultadosExcel(string $path, IdoneidadPsicologicaSolicitud $solicitud, IdoneidadPsicologicaPadronService $padron): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no contiene resultados para procesar.']);
        }

        $headers = array_map(fn ($value): string => $this->normalizarEncabezado((string) $value), array_shift($rows));
        $rutIndex = array_search('RUT', $headers, true);
        $estadoIndex = array_search('ESTADO', $headers, true);
        $observacionIndex = array_search('OBSERVACION', $headers, true);
        if ($rutIndex === false || $estadoIndex === false) {
            throw ValidationException::withMessages(['archivo' => 'La planilla debe incluir las columnas RUT y ESTADO. Descarga la plantilla desde esta solicitud.']);
        }

        $cambios = [];
        $errores = [];
        foreach ($rows as $rowNumber => $row) {
            $rut = trim((string) ($row[$rutIndex] ?? ''));
            $estado = $this->normalizarEstado((string) ($row[$estadoIndex] ?? ''));
            $observacion = $observacionIndex === false ? null : trim((string) ($row[$observacionIndex] ?? ''));
            if ($rut === '' && $estado === '' && $observacion === '') {
                continue;
            }

            $normalizado = RutChile::normalize($rut);
            if (! $normalizado || ($normalizado['status'] ?? null) === 'invalid_dv') {
                $errores[] = 'Fila ' . ($rowNumber + 2) . ': el RUT no es válido.';
                continue;
            }
            if (! in_array($estado, ['aceptado', 'rechazado'], true)) {
                $errores[] = 'Fila ' . ($rowNumber + 2) . ': el estado debe ser Aceptado o Rechazado.';
                continue;
            }
            if (mb_strlen((string) $observacion) > 2000) {
                $errores[] = 'Fila ' . ($rowNumber + 2) . ': la observación no puede superar 2.000 caracteres.';
                continue;
            }

            $rutNormalizado = $padron->rutNormalizado($rut);
            $coincidencias = $solicitud->funcionarios()->where('rut_normalizado', $rutNormalizado)->count();
            if ($coincidencias === 0) {
                $errores[] = 'Fila ' . ($rowNumber + 2) . ': el RUT ' . $rut . ' no pertenece a esta solicitud.';
                continue;
            }
            if (isset($cambios[$rutNormalizado])) {
                $errores[] = 'Fila ' . ($rowNumber + 2) . ': el RUT ' . $rut . ' está repetido en la planilla.';
                continue;
            }

            $cambios[$rutNormalizado] = [
                'rut_normalizado' => $rutNormalizado,
                'estado' => $estado,
                'observacion' => $observacion !== '' ? $observacion : null,
                'coincidencias' => $coincidencias,
            ];
        }

        if ($errores) {
            throw ValidationException::withMessages(['archivo' => $errores]);
        }
        if (! $cambios) {
            throw ValidationException::withMessages(['archivo' => 'No se encontraron filas con resultados para actualizar.']);
        }

        return array_values($cambios);
    }

    private function actualizarEstadoSolicitud(IdoneidadPsicologicaSolicitud $solicitud, int $userId): void
    {
        $pendientes = $solicitud->funcionarios()->where('estado', 'solicitado')->exists();
        $solicitud->update([
            'estado' => $pendientes ? 'solicitada' : 'resuelta',
            'actualizada_por' => $userId,
        ]);
    }

    private function normalizarEncabezado(string $value): string
    {
        $value = strtr(mb_strtoupper(trim($value)), [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);

        return preg_replace('/[^A-Z0-9]+/', '_', $value) ?: '';
    }

    private function normalizarEstado(string $value): string
    {
        return strtolower(strtr(trim($value), [
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ]));
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user() && $request->user()->hasAnyRole(self::ROLES_GESTION), 403);
    }
}
