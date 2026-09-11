<?php

namespace App\Http\Controllers\Reemplazos;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\PadronRevision;
use App\Services\Padron\PadronRevisionService;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronDependenciasService;
use App\Services\Padron\PadronResolucionService;
use App\Services\Padron\PadronBajaAsignacionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PersonalImportController extends Controller
{
    private const CHUNK_SIZE = 200;
    private const UPSERT_BUFFER_SIZE = 500;

    public function __construct()
    {
        // Seguridad adicional: aunque la ruta esté protegida, aquí también queda asegurado.
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function create(Request $request)
    {
        // La plantilla se descarga mediante la misma ruta ya existente del importador.
        // Esto evita depender de una ruta adicional que pueda perderse al consolidar routes/web.php.
        if ($request->boolean('descargar_plantilla')) {
            return $this->plantilla();
        }

        if ($request->filled('revision')) {
            $request->validate(['revision' => ['integer', 'min:1'], 'q' => ['nullable', 'string', 'max:100'], 'accion_filtro' => ['nullable', 'string', 'max:50'], 'conflictos_page' => ['nullable', 'integer', 'min:1'],
                'page' => ['nullable', 'integer', 'min:1'], 'solo_filas' => ['nullable', 'boolean'],
                'fila_revision' => ['nullable', 'integer', 'min:1'], 'pendientes_page' => ['nullable', 'integer', 'min:1'],
                'avanzar_caso' => ['nullable', 'boolean'],
                'caso_rut' => ['nullable', 'string', 'max:32'], 'caso_establecimiento' => ['nullable', 'integer', 'min:1']]);
            $service = app(PadronRevisionService::class);
            $service->assertInstalled();
            $revision = PadronRevision::findOrFail($request->integer('revision'));
            if ($request->boolean('solo_filas')) {
                // Leer filas no necesita recalcular el plan global ni su huella.
                // Guardar decisiones y aplicar conservan su revalidación completa.
                return response()->view('reemplazos.personal.partials.filas-revision',
                    $this->datosFilas($revision, $request))
                    ->header('Cache-Control', 'private, no-store')->header('X-Padron-Filas', '1');
            }
            $aplicador = app(PadronAplicacionService::class);
            $disponible = $aplicador->disponible();
            $plan = $revision->aplicada_at ? null : $aplicador->plan($revision);
            $conflictos = $plan['conflictos'] ?? null;
            $ultimaPaginaConflictos = max(1, count($conflictos['grupos'] ?? []));
            $paginaConflictos = min($ultimaPaginaConflictos, max(1, $request->integer('conflictos_page', 1)));
            if ($request->filled('caso_rut') && $request->filled('caso_establecimiento')) {
                $pendiente = collect($conflictos['grupos'] ?? [])->search(fn ($grupo) => (! $request->boolean('avanzar_caso') || $grupo['bloqueante'])
                    && $grupo['rut'] === $request->query('caso_rut')
                    && $grupo['establecimiento_id'] === $request->integer('caso_establecimiento'));
                if ($pendiente !== false) {
                    $paginaConflictos = $pendiente + 1;
                } elseif ($request->boolean('avanzar_caso')) {
                    // Caso resuelto: volver al primer bloqueo restante de la cola.
                    $paginaConflictos = 1;
                    foreach (['q', 'accion_filtro', 'page', 'caso_rut', 'caso_establecimiento'] as $parametro) {
                        $request->query->remove($parametro);
                    }
                }
            }
            $conflictosPaginados = new \Illuminate\Pagination\LengthAwarePaginator(
                array_slice($conflictos['grupos'] ?? [], $paginaConflictos - 1, 1),
                count($conflictos['grupos'] ?? []), 1, $paginaConflictos,
                ['path' => $request->url(), 'pageName' => 'conflictos_page', 'query' => ['revision' => $revision->id], 'fragment' => 'conflictos-asignaciones'],
            );
            $datosFilas = $this->datosFilas($revision, $request);
            $enlacesBloqueos = [];
            foreach (array_slice($plan['errores'] ?? [], 0, 10) as $indice => $mensaje) {
                if (preg_match('/^Fila (\d+)(?:[: ]|$)/u', $mensaje, $match)) {
                    $fila = $revision->filas()->where('fila_excel', (int) $match[1])->value('id');
                } elseif (preg_match('/^ID (\d+):/u', $mensaje, $match)) {
                    $fila = $revision->filas()->where('personal_id', (int) $match[1])->orderBy('id')->value('id');
                } else {
                    $fila = null;
                }
                if ($fila) {
                    $enlacesBloqueos[$indice] = route('reemplazos.personal.import', ['revision' => $revision->id, 'fila_revision' => $fila]).'#filas-padron';
                }
            }
            return view('reemplazos.personal.revision', array_merge($datosFilas, [
                'revision' => $revision,
                'paginaConflictos' => $paginaConflictos,
                'aplicacionDisponible' => $disponible,
                'bloqueos' => $plan['errores'] ?? [],
                'enlacesBloqueos' => $enlacesBloqueos,
                'confirmacionHash' => $plan['confirmacion_hash'] ?? null,
                'conflictos' => $conflictos, 'conflictosPaginados' => $conflictosPaginados,
                'cambiosAplicados' => $revision->aplicada_at ? DB::table('padron_personal_cambios')->where('padron_revision_id', $revision->id)->count() : 0,
                'liberacionInstalada' => app(PadronBajaAsignacionesService::class)->instalado(),
                'asignacionesLiberadas' => $revision->aplicada_at && app(PadronBajaAsignacionesService::class)->instalado()
                    ? DB::table('padron_asignacion_cambios')->where('padron_revision_id', $revision->id)->count() : 0,
                'autorizaciones' => DB::table('padron_revision_autorizaciones')->where('padron_revision_id', $revision->id)->get()->keyBy('rut'),
            ]));
        }

        return view('reemplazos.personal.import');
    }

    private function datosFilas(PadronRevision $revision, Request $request): array
    {
        $resolucion = app(PadronResolucionService::class);
        $disponible = $resolucion->disponible();
        $historial = $disponible ? $resolucion->historial($revision) : collect();
        $decisiones = $historial->keyBy('padron_revision_fila_id');
        // El acceso directo siempre pertenece a esta revisión, incluso en la carga parcial.
        $filaObjetivo = $request->filled('fila_revision')
            ? $revision->filas()->select(['id', 'rut'])->findOrFail($request->integer('fila_revision')) : null;
        if ($filaObjetivo) {
            $request->query->set('q', $filaObjetivo->rut);
            $request->query->remove('accion_filtro');
        }
        $mostrar = $filaObjetivo !== null || $request->filled('q');
        $query = $revision->filas()->when(! $mostrar, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($mostrar, function ($q) use ($request, $filaObjetivo) {
                if ($filaObjetivo) {
                    // Sin RUT no se mezclan registros de identidades desconocidas.
                    return $filaObjetivo->rut ? $q->where('rut', $filaObjetivo->rut) : $q->whereKey($filaObjetivo->id);
                }
                $term = trim((string) $request->query('q'));
                $q->where(fn ($sub) => $sub->where('rut', 'like', '%'.$term.'%')->orWhere('nombre', 'like', '%'.$term.'%'));
            })->when($request->filled('accion_filtro'), fn ($q) => $q->where('accion', $request->query('accion_filtro')));
        if ($filaObjetivo && ! $request->filled('page')) {
            $request->query->set('page', intdiv((clone $query)->where('id', '<', $filaObjetivo->id)->count(), 50) + 1);
        }
        $filas = $query->orderBy('id')->paginate(50, ['*'], 'page', $request->integer('page', 1))
            ->withPath(route('reemplazos.personal.import'))->appends($request->except(['solo_filas', 'avanzar_caso']))->fragment('filas-padron');
        $ids = [];
        foreach ($filas as $fila) {
            $ids[] = $fila->personal_id;
            $ids[] = $decisiones->get($fila->id)?->personal_id;
            foreach ($fila->candidatos ?? [] as $candidato) {
                $ids[] = $candidato['id'];
            }
        }
        $resumen = $resolucion->resumen($revision->filas()->orderBy('id')->get(['id', 'fila_excel', 'accion', 'personal_id', 'rut', 'datos->tipocontrato as tipo_contrato']), $decisiones);
        $idsPendientes = array_keys(array_filter($resumen['estados'], fn ($estado) => $estado === 'pendiente'));
        $paginaPendientes = min(max(1, (int) ceil(count($idsPendientes) / 10)), $request->integer('pendientes_page', 1));
        $pendientes = new \Illuminate\Pagination\LengthAwarePaginator(
            $revision->filas()->whereIn('id', array_slice($idsPendientes, ($paginaPendientes - 1) * 10, 10))
                ->orderBy('id')->get(['id', 'fila_excel', 'personal_id', 'rut', 'nombre']),
            count($idsPendientes), 10, $paginaPendientes,
            ['path' => route('reemplazos.personal.import'), 'pageName' => 'pendientes_page',
                'query' => ['revision' => $revision->id], 'fragment' => 'pendientes-correspondencia'],
        );
        return [
            'revision' => $revision, 'filas' => $filas, 'mostrarFilas' => $mostrar,
            'paginaConflictos' => $request->integer('conflictos_page', 1),
            'casoRut' => $request->query('caso_rut'), 'casoEstablecimiento' => $request->query('caso_establecimiento'),
            'resolucionDisponible' => $disponible, 'decisiones' => $decisiones,
            'historialDecisiones' => $historial->groupBy('padron_revision_fila_id'),
            'resumenResolucion' => $resumen, 'pendientesCorrespondencia' => $pendientes,
            'dependenciasHistoricas' => app(PadronDependenciasService::class)->paraPersonal($ids),
            'obsoleta' => ! $revision->aplicada_at && app(PadronRevisionService::class)->stale($revision),
        ];
    }

    public function plantilla()
    {
        $headers = [
            'rut',
            'nombre',
            'FECHA_NACIMIENTO',
            'Fecha_Ingreso',
            'Fecha_Termino',
            'tipocontrato',
            'FINANCIAMIENTO',
            'estatuto',
            'escalafon',
            'anio',
            'mes',
            'jornada',
            'Jornada_Basica',
            'Jornada_Media',
            'RBD',
            'Bienios',
            'Tramo',
            'fecha_antiguedad',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Padron Reemplazos');

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $header);
        }

        $sheet->fromArray([
            ['12345678-9', 'NOMBRE APELLIDO APELLIDO', '1980-01-01', '2020-03-01', null, 'CONTRATA', 'SEP', 'DOCENTE', 'DOCENTE AULA', 2026, 7, 44, 30, 14, 12345, 8, 'Avanzado'],
            ['11111111-1', 'NOMBRE ASISTENTE EDUCACION', '1975-05-10', '2019-03-01', null, 'CONTRATA', 'REGULAR', 'ASISTENTE EDUCACION', 'INSPECTOR', 2026, 7, 44, 0, 0, 12345, 4, null],
        ], null, 'A2');

        $headerRange = 'A1:R1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        $sheet->getStyle('A1:R3')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A2:R3')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('R:R')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($headerRange);

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instrucciones');
        $instructions->fromArray([
            ['Plantilla padrón de reemplazos'],
            ['Usar la hoja "Padron Reemplazos" para la carga. No modificar los nombres de encabezados.'],
            ['La carga debe contener un solo periodo mensual: todas las filas con el mismo anio y mes.'],
            ['Bienios es obligatorio como encabezado y se guardará como número entero si viene informado.'],
            ['Tramo se procesa si viene informado; se recomienda completarlo para docentes. Para no docentes puede quedar vacío.'],
            ['Valores sugeridos para Tramo: Acceso, Inicial, Temprano, Avanzado, Experto I, Experto II, Sin tramo.'],
            ['fecha_antiguedad: fecha YYYY-MM-DD o DD/MM/YYYY. Si está ausente o vacía no se propone borrar la fecha existente.'],
            ['La previsualización revisa el padrón completo sin modificar IDs, contratos ni asignaciones.'],
        ], null, 'A1');
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructions->getColumnDimension('A')->setWidth(120);
        $instructions->getStyle('A1:A9')->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'plantilla_padron_reemplazos_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmp);

        return response()->download($tmp, 'plantilla_padron_reemplazos.xlsx')->deleteFileAfterSend(true);
    }

    public function store(Request $request)
    {
        $request->validate(['accion' => ['required', 'in:previsualizar,autorizar_exceso,resolver,resolver_varias,aplicar,confirmar_baja_asignaciones,retirar_baja_asignaciones,confirmar_traslado_asignaciones,retirar_traslado_asignaciones']]);
        if (in_array($request->input('accion'), ['confirmar_traslado_asignaciones', 'retirar_traslado_asignaciones'], true)) {
            $data = $request->validate([
                'revision' => ['required', 'integer', 'min:1'], 'rut' => ['required', 'string', 'max:32'],
                'origen' => ['required', 'integer', 'min:1'], 'destino' => ['required', 'integer', 'min:1'],
                'alcance_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'decision_anterior' => ['required', 'integer', 'min:0'],
                'justificacion' => ['required', 'string', 'min:10', 'max:2000'], 'confirmar_alcance' => ['accepted'],
                'conflictos_page' => ['nullable', 'integer', 'min:1'],
            ]);
            $revision = PadronRevision::findOrFail($data['revision']);
            $confirmar = $request->input('accion') === 'confirmar_traslado_asignaciones';
            app(PadronBajaAsignacionesService::class)->registrarTraslado($revision, $data['rut'], (int) $data['origen'], (int) $data['destino'],
                $data['alcance_hash'], (int) $data['decision_anterior'], $data['justificacion'], (int) $request->user()->id, $confirmar);
            return redirect()->to(route('reemplazos.personal.import', [
                'revision' => $revision->id, 'conflictos_page' => $data['conflictos_page'] ?? 1,
            ]).'#conflictos-asignaciones')->with('status', $confirmar
                ? 'Traslado y liberación diferida confirmados. Las asignaciones de origen seguirán activas hasta aplicar el padrón.'
                : 'Confirmación de traslado retirada. No se modificaron contratos ni asignaciones.');
        }
        if (in_array($request->input('accion'), ['confirmar_baja_asignaciones', 'retirar_baja_asignaciones'], true)) {
            $data = $request->validate([
                'revision' => ['required', 'integer', 'min:1'], 'rut' => ['required', 'string', 'max:32'],
                'alcance_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
                'decision_anterior' => ['required', 'integer', 'min:0'],
                'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
                'confirmar_alcance' => ['accepted'],
                'conflictos_page' => ['nullable', 'integer', 'min:1'],
            ]);
            $revision = PadronRevision::findOrFail($data['revision']);
            $confirmar = $request->input('accion') === 'confirmar_baja_asignaciones';
            app(PadronBajaAsignacionesService::class)->registrar($revision, $data['rut'], $data['alcance_hash'],
                (int) $data['decision_anterior'], $data['justificacion'], (int) $request->user()->id, $confirmar);
            return redirect()->to(route('reemplazos.personal.import', [
                'revision' => $revision->id, 'conflictos_page' => $data['conflictos_page'] ?? 1,
            ]).'#conflictos-asignaciones')->with('status', $confirmar
                ? 'Baja y liberación diferida confirmadas. Solo se registró la decisión; los contratos y las asignaciones siguen sin cambios hasta aplicar el padrón.'
                : 'Confirmación de liberación retirada. No se modificaron contratos ni asignaciones.');
        }
        if (in_array($request->input('accion'), ['resolver', 'resolver_varias', 'aplicar'], true)) {
            $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
            $revision = PadronRevision::findOrFail($data['revision']);
            $aplicador = app(PadronAplicacionService::class);
            if (in_array($request->input('accion'), ['resolver', 'resolver_varias'], true)) {
                $contexto = $request->validate([
                    'q' => ['nullable', 'string', 'max:100'], 'accion_filtro' => ['nullable', 'string', 'max:50'],
                    'page' => ['nullable', 'integer', 'min:1'], 'conflictos_page' => ['nullable', 'integer', 'min:1'],
                    'caso_rut' => ['nullable', 'string', 'max:32'], 'caso_establecimiento' => ['nullable', 'integer', 'min:1'],
                ]);
                if ($request->input('accion') === 'resolver') {
                    $data = $request->validate([
                        'fila' => ['required', 'integer', 'min:1'], 'personal_id' => ['required', 'integer', 'min:0'],
                        'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
                        'decision_anterior' => ['required', 'integer', 'min:0'],
                    ]);
                    $aplicador->resolver($revision, $data['fila'], $data['personal_id'] ? (int) $data['personal_id'] : null, $data['justificacion'], (int) $request->user()->id, (int) $data['decision_anterior']);
                    $message = 'Decisión de conciliación registrada. No se modificaron contratos, vigencias ni asignaciones.';
                } else {
                    $data = $request->validate([
                        'rut' => ['required', 'string', 'max:32'],
                        'decisiones' => ['required', 'array', 'min:1', 'max:50'],
                        'decisiones.*' => ['required', 'array'],
                        'decisiones.*.fila' => ['required', 'integer', 'min:1', 'distinct'],
                        'decisiones.*.personal_id' => ['required', 'integer', 'min:0'],
                        'decisiones.*.justificacion' => ['required', 'string', 'min:10', 'max:2000'],
                        'decisiones.*.decision_anterior' => ['required', 'integer', 'min:0'],
                    ]);
                    $entradas = array_map(static function (array $entrada): array {
                        $entrada['personal_id'] = (int) $entrada['personal_id'] === 0 ? null : (int) $entrada['personal_id'];
                        return $entrada;
                    }, $data['decisiones']);
                    $cantidad = app(PadronResolucionService::class)->resolverVarias($revision, $data['rut'], $entradas, (int) $request->user()->id);
                    $message = $cantidad.' decisiones registradas en conjunto. No se modificaron contratos, vigencias ni asignaciones.';
                }
                // Mantener el contexto permite resolver las demás filas del RUT.
                if (! empty($contexto['caso_rut']) && ! empty($contexto['caso_establecimiento'])) {
                    $contexto['avanzar_caso'] = 1;
                }
                return redirect()->to(route('reemplazos.personal.import', ['revision' => $revision->id] + $contexto).($contexto ? '#filas-padron' : ''))
                    ->with('status', $message);
            } else {
                $request->validate(['confirmar_aplicacion' => ['accepted']]);
                @set_time_limit(240);
                $aplicador->aplicar($revision, (int) $request->user()->id, (string) $request->string('confirmacion_hash'));
                $message = 'Padrón aplicado. Los IDs y las referencias históricas se conservaron. Solo se inactivaron las asignaciones incluidas en bajas con liberación confirmada.';
            }
            return redirect()->route('reemplazos.personal.import', ['revision' => $revision->id])->with('status', $message);
        }
        if ($request->input('accion') === 'previsualizar') {
            $request->validate([
                'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
                'padron_completo' => ['accepted'],
            ]);
            @set_time_limit(240);
            $file = $request->file('excel');
            $revision = app(PadronRevisionService::class)->create($file->getRealPath(), $file->getClientOriginalName(), (int) $request->user()->id);
            return redirect()->route('reemplazos.personal.import', ['revision' => $revision->id]);
        }
        if ($request->input('accion') === 'autorizar_exceso') {
            $data = $request->validate([
                'revision' => ['required', 'integer'], 'rut' => ['required', 'string', 'max:32'],
                'justificacion' => ['required', 'string', 'min:10', 'max:2000'],
            ]);
            $revision = PadronRevision::findOrFail($data['revision']);
            app(PadronRevisionService::class)->authorize($revision, $data['rut'], $data['justificacion'], (int) $request->user()->id);
            return redirect()->route('reemplazos.personal.import', ['revision' => $revision->id])
                ->with('status', 'Excepción registrada para esta revisión. El padrón no se ha modificado.');
        }
    }

    // Conservado para compatibilidad de implementación. No está expuesto por
    // rutas: el flujo nuevo solo previsualiza hasta implementar aplicación segura.
    private function importLegacy(Request $request)
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [
            'excel.required' => 'Debes seleccionar un archivo Excel.',
            'excel.mimes'    => 'El archivo debe ser .xlsx o .xls.',
        ]);

        $file = $request->file('excel');
        $originalName = $file?->getClientOriginalName() ?: 'padron-personal.xlsx';

        $disk = 'local';
        $dir  = 'imports/reemplazos';

        $storedPath = null;

        // Columnas requeridas (exactas)
        $requiredHeaders = [
            'rut',
            'nombre',
            'FECHA_NACIMIENTO',
            'Fecha_Ingreso',
            'Fecha_Termino',
            'tipocontrato',
            'FINANCIAMIENTO',
            'estatuto',
            'escalafon',
            'anio',
            'mes',
            'jornada',
            'Jornada_Basica',
            'Jornada_Media',
            'RBD',
            'Bienios',
        ];

        // Pre-carga de establecimientos por RBD (para resolver establecimiento_id)
        $establecimientosByRbd = Establecimiento::query()
            ->select('id', 'rbd')
            ->get()
            ->keyBy('rbd');

        try {
            if (!$file->isValid()) {
                return back()->withErrors(['excel' => 'Error al subir el archivo. Intenta nuevamente.']);
            }

            Storage::disk($disk)->makeDirectory($dir);

            $storedPath = $file->store($dir, $disk);

            if (!$storedPath || !Storage::disk($disk)->exists($storedPath)) {
                return back()->withErrors([
                    'excel' => 'No se pudo guardar el archivo en storage. Revisa permisos de storage/app y configuración del disk local.',
                ]);
            }

            $fullPath = Storage::disk($disk)->path($storedPath);
            $fullPath = realpath($fullPath) ?: $fullPath;

            $reader = IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $worksheetInfo = $reader->listWorksheetInfo($fullPath);
            $firstSheetInfo = $worksheetInfo[0] ?? null;

            if (!$firstSheetInfo) {
                return back()->withErrors([
                    'excel' => 'No fue posible leer la hoja principal del Excel.',
                ]);
            }

            $highestColumn = $firstSheetInfo['lastColumnLetter'] ?? 'A';
            $highestRow = (int) ($firstSheetInfo['totalRows'] ?? 0);
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow < 1) {
                return back()->withErrors([
                    'excel' => 'El Excel no contiene filas para importar.',
                ]);
            }

            $headerSpreadsheet = null;
            $headerFilter = new PersonalImportReadFilter(1, 1);
            $reader->setReadFilter($headerFilter);
            $headerSpreadsheet = $reader->load($fullPath);
            $headerSheet = $headerSpreadsheet->getSheet(0);

            // Encabezados fila 1
            $headerRow = $headerSheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0] ?? [];
            $headerRow = array_map(function ($v) {
                if (is_string($v)) {
                    return trim($v);
                }
                if (is_null($v)) {
                    return '';
                }
                return trim((string) $v);
            }, $headerRow);

            $headerSpreadsheet->disconnectWorksheets();
            unset($headerSpreadsheet, $headerSheet);

            // Mapa header => índice de columna (1-based)
            $colMap = [];
            foreach ($headerRow as $idx0 => $header) {
                if ($header !== '') {
                    $colMap[$header] = $idx0 + 1;
                }
            }

            $missing = [];
            foreach ($requiredHeaders as $h) {
                if (!array_key_exists($h, $colMap)) {
                    $missing[] = $h;
                }
            }

            if (!empty($missing)) {
                return back()->withErrors([
                    'excel' => 'Faltan columnas requeridas en el Excel: ' . implode(', ', $missing),
                ]);
            }

            $tramoHeader = $this->findFirstHeader($colMap, [
                'Tramo',
                'TRAMO',
                'tramo',
                'Tramo Docente',
                'TRAMO DOCENTE',
                'TRAMO_DOCENTE',
                'tramo_docente',
            ]);

            $headersToRead = $requiredHeaders;
            if ($tramoHeader !== null) {
                $headersToRead[] = $tramoHeader;
            }

            $allowedColumns = array_values(array_unique(array_map(
                static fn (string $header): int => $colMap[$header],
                $headersToRead
            )));

            $now = now();

            $totalRows = 0;
            $skippedEmpty = 0;
            $skippedRbdNotFound = 0;
            $inserted = 0;
            $updated  = 0;

            $rbdNotFoundList = [];

            // Para asegurar "carga mensual" consistente dentro del mismo archivo:
            $fileAnio = null;
            $fileMes  = null;

            $buffer = [];
            $backfillBieniosByRut = [];

            for ($chunkStart = 2; $chunkStart <= $highestRow; $chunkStart += self::CHUNK_SIZE) {
                $chunkEnd = min($highestRow, $chunkStart + self::CHUNK_SIZE - 1);

                $chunkFilter = new PersonalImportReadFilter($chunkStart, $chunkEnd, $allowedColumns);
                $reader->setReadFilter($chunkFilter);
                $spreadsheet = $reader->load($fullPath);
                $sheet = $spreadsheet->getSheet(0);

                for ($row = $chunkStart; $row <= $chunkEnd; $row++) {
                    $totalRows++;

                    $rut  = $this->cellString($sheet, $colMap['rut'], $row);
                    $rbdV = $this->cellString($sheet, $colMap['RBD'], $row);

                    // Si no hay rut o RBD, omitimos
                    if ($rut === '' || $rbdV === '') {
                        $skippedEmpty++;
                        continue;
                    }

                    $rbd = (int) preg_replace('/\D+/', '', $rbdV);
                    if ($rbd <= 0) {
                        $skippedEmpty++;
                        continue;
                    }

                    $nombre = $this->cellString($sheet, $colMap['nombre'], $row);

                    $fechaNacimiento = $this->parseDateFlexible(
                        $this->cellRaw($sheet, $colMap['FECHA_NACIMIENTO'], $row)
                    );

                    $fechaIngreso = $this->parseDateFlexible(
                        $this->cellRaw($sheet, $colMap['Fecha_Ingreso'], $row)
                    );

                    $fechaTermino = $this->parseDateFlexible(
                        $this->cellRaw($sheet, $colMap['Fecha_Termino'], $row)
                    );

                    $tipocontrato   = $this->cellString($sheet, $colMap['tipocontrato'], $row);
                    $financiamiento = $this->cellString($sheet, $colMap['FINANCIAMIENTO'], $row);
                    $estatuto       = $this->cellString($sheet, $colMap['estatuto'], $row);
                    $escalafon      = $this->cellString($sheet, $colMap['escalafon'], $row);
                    $tramo          = $tramoHeader !== null
                        ? $this->normalizarTramo($this->cellString($sheet, $colMap[$tramoHeader], $row))
                        : null;

                    $anioRaw = $this->cellRaw($sheet, $colMap['anio'], $row);
                    $mesRaw  = $this->cellRaw($sheet, $colMap['mes'], $row);

                    $anio = is_numeric($anioRaw) ? (int) $anioRaw : (int) trim((string) $anioRaw);
                    $mes  = is_numeric($mesRaw)  ? (int) $mesRaw  : (int) trim((string) $mesRaw);

                    if ($anio <= 0 || $mes <= 0 || $mes > 12) {
                        $skippedEmpty++;
                        continue;
                    }

                    if ($fileAnio === null && $fileMes === null) {
                        $fileAnio = $anio;
                        $fileMes  = $mes;
                    } elseif ($anio !== $fileAnio || $mes !== $fileMes) {
                        $spreadsheet->disconnectWorksheets();
                        unset($spreadsheet, $sheet);

                        return back()->withErrors([
                            'excel' => "El Excel contiene más de un período. Encontré {$fileAnio}/{$fileMes} y también {$anio}/{$mes}. Debe ser una carga mensual única.",
                        ]);
                    }

                    $jornada = $this->cellNumeric($sheet, $colMap['jornada'], $row);
                    $jBasica = $this->cellNumeric($sheet, $colMap['Jornada_Basica'], $row);
                    $jMedia  = $this->cellNumeric($sheet, $colMap['Jornada_Media'], $row);
                    $bienios = $this->cellNumeric($sheet, $colMap['Bienios'], $row);
                    if ($bienios !== null) {
                        $backfillBieniosByRut[$rut] = (int) round($bienios);
                    }

                    $establecimiento = $establecimientosByRbd->get($rbd);
                    if (!$establecimiento) {
                        $skippedRbdNotFound++;
                        $rbdNotFoundList[$rbd] = true;
                        continue;
                    }

                    $hashInput = implode('|', [
                        mb_strtolower(trim($rut)),
                        $rbd,
                        $anio,
                        $mes,
                        $fechaIngreso ? $fechaIngreso->format('Y-m-d') : '',
                        mb_strtolower(trim($tipocontrato)),
                        mb_strtolower(trim($financiamiento)),
                        mb_strtolower(trim($estatuto)),
                        mb_strtolower(trim($escalafon)),
                        (string) ($jornada ?? ''),
                        (string) ($jBasica ?? ''),
                        (string) ($jMedia ?? ''),
                    ]);

                    $rowHash = hash('sha256', $hashInput);

                    $buffer[] = [
                        'row_hash'           => $rowHash,
                        'establecimiento_id' => $establecimiento->id,
                        'rut'                => $rut,
                        'nombre'             => $nombre,
                        'fecha_nacimiento'   => $fechaNacimiento?->format('Y-m-d'),
                        'fecha_ingreso'      => $fechaIngreso?->format('Y-m-d'),
                        'fecha_termino'      => $fechaTermino?->format('Y-m-d'),
                        'tipocontrato'       => $tipocontrato,
                        'financiamiento'     => $financiamiento,
                        'estatuto'           => $estatuto,
                        'escalafon'          => $escalafon,
                        'anio'               => $anio,
                        'mes'                => $mes,
                        'jornada'            => $jornada,
                        'jornada_basica'     => $jBasica,
                        'jornada_media'      => $jMedia,
                        'rbd'                => $rbd,
                        'bienios'            => $bienios,
                        'tramo'              => $tramo,
                        'source_filename'    => $originalName,
                        'created_by'         => $request->user()?->id,
                        'imported_at'        => $now,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];

                    if (count($buffer) >= self::UPSERT_BUFFER_SIZE) {
                        [$i, $u] = $this->flushUpsert($buffer);
                        $inserted += $i;
                        $updated  += $u;
                        $buffer = [];
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $sheet, $chunkFilter);
                gc_collect_cycles();
            }

            if (!empty($buffer)) {
                [$i, $u] = $this->flushUpsert($buffer);
                $inserted += $i;
                $updated  += $u;
            }

            $backfilledBienios = $this->backfillBieniosForRuts($backfillBieniosByRut);

            $rbdNotFoundList = array_keys($rbdNotFoundList);
            sort($rbdNotFoundList);

            $summary = [
                'archivo' => $originalName,
                'periodo' => $fileAnio && $fileMes ? str_pad((string) $fileMes, 2, '0', STR_PAD_LEFT) . '/' . $fileAnio : null,
                'leidas' => $totalRows,
                'candidatas' => $inserted + $updated,
                'insertadas' => $inserted,
                'actualizadas' => $updated,
                'omitidas_vacias' => $skippedEmpty,
                'omitidas_sin_estab' => $skippedRbdNotFound,
                'omitidas_duplicadas' => 0,
                'rbds_faltantes' => $rbdNotFoundList,
                'bienios_backfill' => $backfilledBienios,
            ];

            return redirect()
                ->route('reemplazos.personal.import')
                ->with('status', 'Carga finalizada correctamente.')
                ->with('import_stats', [
                    'total_rows'          => $totalRows,
                    'inserted'            => $inserted,
                    'updated'             => $updated,
                    'skipped_empty'       => $skippedEmpty,
                    'skipped_rbd_missing' => $skippedRbdNotFound,
                    'rbd_missing_list'    => $rbdNotFoundList,
                    'periodo'             => $fileAnio && $fileMes ? "{$fileMes}/{$fileAnio}" : null,
                    'backfilled_bienios'  => $backfilledBienios,
                ])
                ->with('import_summary', $summary);
        } catch (\Throwable $e) {
            Log::error('Error import reemplazos personal', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'excel' => 'Ocurrió un error al procesar el Excel: ' . $e->getMessage(),
            ]);
        } finally {
            if (!empty($storedPath)) {
                try {
                    Storage::disk($disk)->delete($storedPath);
                } catch (\Throwable $e) {
                    // no interrumpir flujo por fallas de limpieza
                }
            }
        }
    }

    private function backfillBieniosForRuts(array $bieniosByRut): int
    {
        if (empty($bieniosByRut)) {
            return 0;
        }

        $updated = 0;

        foreach (array_chunk($bieniosByRut, 200, true) as $chunk) {
            foreach ($chunk as $rut => $bienios) {
                if ($rut === '' || $bienios === null) {
                    continue;
                }

                $updated += DB::table('reemplazos_personal')
                    ->where('rut', $rut)
                    ->whereNull('bienios')
                    ->update(['bienios' => (int) $bienios]);
            }
        }

        return $updated;
    }

    /**
     * Upsert idempotente por row_hash.
     * Retorna [inserted, updated] (estimado consultando existentes antes del upsert).
     */
    private function flushUpsert(array $rows): array
    {
        $hashes = array_column($rows, 'row_hash');

        $existing = DB::table('reemplazos_personal')
            ->whereIn('row_hash', $hashes)
            ->pluck('row_hash')
            ->all();

        $existingSet = array_fill_keys($existing, true);
        $existingCount = 0;

        foreach ($hashes as $h) {
            if (isset($existingSet[$h])) {
                $existingCount++;
            }
        }

        $updated  = $existingCount;
        $inserted = count($rows) - $existingCount;

        DB::table('reemplazos_personal')->upsert(
            $rows,
            ['row_hash'],
            [
                'establecimiento_id',
                'rut',
                'nombre',
                'fecha_nacimiento',
                'fecha_ingreso',
                'fecha_termino',
                'tipocontrato',
                'financiamiento',
                'estatuto',
                'escalafon',
                'anio',
                'mes',
                'jornada',
                'jornada_basica',
                'jornada_media',
                'rbd',
                'bienios',
                'tramo',
                'source_filename',
                'imported_at',
                'updated_at',
            ]
        );

        return [$inserted, $updated];
    }

    private function findFirstHeader(array $colMap, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $colMap)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizarTramo(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value, 'UTF-8');
    }

    private function cellRaw($sheet, int $col, int $row)
    {
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        return $sheet->getCell($coord)->getValue();
    }

    private function cellString($sheet, int $col, int $row): string
    {
        $v = $this->cellRaw($sheet, $col, $row);

        if (is_null($v)) {
            return '';
        }
        if (is_string($v)) {
            return trim($v);
        }

        if (is_numeric($v)) {
            if ((int) $v == $v) {
                return (string) ((int) $v);
            }
            return trim((string) $v);
        }

        return trim((string) $v);
    }

    private function cellNumeric($sheet, int $col, int $row): ?float
    {
        $v = $this->cellRaw($sheet, $col, $row);

        if (is_null($v) || $v === '') {
            return null;
        }

        if (is_numeric($v)) {
            return (float) $v;
        }

        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * Parse flexible de fecha: soporta:
     * - Excel serial number (numérico)
     * - string "dd/mm/yyyy" (a veces con espacios)
     * - string "yyyy-mm-dd"
     */
    private function parseDateFlexible($value): ?Carbon
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                return Carbon::instance($dt)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $s)) {
                return Carbon::createFromFormat('d/m/Y', $s)->startOfDay();
            }
        } catch (\Throwable $e) {
            // sigue
        }

        try {
            if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $s)) {
                return Carbon::createFromFormat('Y-m-d', $s)->startOfDay();
            }
        } catch (\Throwable $e) {
            // sigue
        }

        try {
            return Carbon::parse($s)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

class PersonalImportReadFilter implements IReadFilter
{
    /** @var array<int, true> */
    private array $allowedColumnMap;

    public function __construct(
        private int $startRow,
        private int $endRow,
        array $allowedColumns = []
    ) {
        $this->allowedColumnMap = [];

        foreach ($allowedColumns as $columnIndex) {
            $this->allowedColumnMap[(int) $columnIndex] = true;
        }
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row !== 1 && ($row < $this->startRow || $row > $this->endRow)) {
            return false;
        }

        if (empty($this->allowedColumnMap)) {
            return true;
        }

        $columnIndex = Coordinate::columnIndexFromString($columnAddress);

        return isset($this->allowedColumnMap[$columnIndex]);
    }
}
