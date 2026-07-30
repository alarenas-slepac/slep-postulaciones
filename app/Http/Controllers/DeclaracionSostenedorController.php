<?php

namespace App\Http\Controllers;

use App\Exports\DeclaracionSostenedoresExport;
use App\Imports\SostenedoresImport;
use App\Imports\TitulosCatalogoImport;
use App\Jobs\GenerateDeclaracionDocumentosExport;
use App\Models\DeclaracionDocumentosExport;
use App\Models\DeclaracionSostenedor;
use App\Models\FuncionCatalogo;
use App\Models\InstitucionCatalogo;
use App\Models\TituloCatalogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DeclaracionSostenedorController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $isDeclaracionAdmin = $this->hasDeclaracionAdminAccess($user, $activeRole);
        $isFuncionarioEstab = $activeRole === 'funcionario_estab';

        abort_unless($isDeclaracionAdmin || $isFuncionarioEstab, 403);

        $tab = $this->resolveDeclaracionTab($request);

        $baseQuery = $this->buildDeclaracionFilteredQuery($request, $isDeclaracionAdmin, $isFuncionarioEstab, $user);

        $counts = [
            'docentes' => (clone $baseQuery)->where('estamento', 'DOCENTE')->count(),
            'asistentes' => (clone $baseQuery)->where('estamento', 'ASISTENTE')->count(),
        ];
        $stats = $this->buildTabStats((clone $baseQuery), $tab);
        $reporteEstablecimientos = $this->buildEstablecimientoProgressReport((clone $baseQuery), $tab);

        $query = (clone $baseQuery)->with(['funcionCatalogo:id,nombre', 'institucionCatalogo:id,nombre'])
            ->orderBy('rbd')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres');
        if ($tab === 'docentes') {
            $query->where('estamento', 'DOCENTE');
        } else {
            $query->where('estamento', 'ASISTENTE');
        }

        $registros = $query->paginate(50)->withQueryString();
        $titulos = TituloCatalogo::orderBy('nombre')->pluck('nombre');
        $funcionesAsistente = FuncionCatalogo::query()
            ->get(['id', 'nombre'])
            ->sortBy(function ($item) {
                $normalized = $this->normalizeComparableText((string) $item->nombre) ?? '';
                $isOtro = $normalized === 'otro';
                return sprintf('%d-%s', $isOtro ? 1 : 0, $normalized);
            }, SORT_NATURAL)
            ->values();
        $paises = $this->nacionalidades();
        $instructivoActual = $this->buildDeclaracionInstructivo($tab);
        $tiposTituloAsistente = $this->tiposTituloAsistente();
        $institucionesCatalogo = InstitucionCatalogo::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $establecimientos = collect();
        $exportacionesDocumentos = collect();
        if ($isDeclaracionAdmin) {
            $establecimientos = DB::table('establecimientos')
                ->select('cod_estab', 'nombre_establecimiento')
                ->orderBy('nombre_establecimiento')
                ->get();

            $exportacionesDocumentos = DeclaracionDocumentosExport::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(10)
                ->get();
        }

        $tituloVista = 'Declaración de Sostenedores';
        if ($isFuncionarioEstab) {
            $tituloVista = 'Declaración Establecimiento 2026';
        }

        return view('declaracion.index', compact('registros', 'titulos', 'funcionesAsistente', 'institucionesCatalogo', 'paises', 'tiposTituloAsistente', 'establecimientos', 'tituloVista', 'isDeclaracionAdmin', 'isFuncionarioEstab', 'tab', 'counts', 'stats', 'reporteEstablecimientos', 'instructivoActual', 'exportacionesDocumentos'));
    }

    public function destroy($id)
    {
        $this->authorizeDeclaracionAdmin();
        $registro = DeclaracionSostenedor::findOrFail($id);
        $this->deleteArchivo($registro->certificado_titulo);
        $this->deleteArchivo($registro->certificado_antecedentes);
        $registro->delete();
        return back()->with('success', 'Registro eliminado.');
    }

    public function actualizarFecha(Request $request, $id)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        if ($this->shouldLockTituloData($registro)) {
            $this->clearTituloData($registro);
            return back()->with('success', 'Fecha limpiada porque el tipo de título es Ninguno.');
        }

        $data = $request->validate(['fecha_titulacion' => 'nullable|date']);
        $registro->update($data);
        return back()->with('success', 'Fecha actualizada.');
    }

    public function informar(Request $request, $id)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);
        $data = $request->validate(['observacion_funcionario' => 'nullable|string|max:200']);
        $registro->update($data);
        return back()->with('success', 'Observación guardada.');
    }

    public function verCertificado($id, string $tipo)
    {
        abort_unless(in_array($tipo, ['titulo', 'antecedentes'], true), 404);
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canViewRegistro($registro), 403);
        $campo = 'certificado_' . $tipo;
        abort_unless($registro->{$campo}, 404);
        abort_unless(Storage::disk('local')->exists($registro->{$campo}), 404);
        return response()->file(Storage::disk('local')->path($registro->{$campo}));
    }

    public function subirCertificado(Request $request, $id, string $tipo)
    {
        abort_unless(in_array($tipo, ['titulo', 'antecedentes'], true), 404);
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        if ($tipo === 'titulo' && $this->shouldLockTituloData($registro)) {
            $this->clearTituloData($registro);
            return back()->with('error', 'No puede subir certificado de título cuando Tipo Título es Ninguno.');
        }

        $request->validate([
            'certificado' => 'required|file|mimes:pdf|max:10240',
        ], [
            'certificado.mimes' => 'El archivo debe estar en formato PDF.',
            'certificado.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $campo = 'certificado_' . $tipo;
        $this->deleteArchivo($registro->{$campo});

        $archivo = $request->file('certificado');
        $path = $this->buildDeclaracionCertificadoPath($registro, $archivo, $tipo);
        Storage::disk('local')->putFileAs(
            dirname($path),
            $archivo,
            basename($path)
        );

        $registro->update([$campo => $path]);

        return back()->with('success', 'Certificado de ' . $tipo . ' subido correctamente.');
    }

    public function actualizarTitulo(Request $request, $id)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        if ($this->shouldLockTituloData($registro)) {
            $this->clearTituloData($registro);
            return back()->with('success', 'Nombre de título limpiado porque el tipo de título es Ninguno.');
        }

        $data = $request->validate(['nombre_titulo' => 'nullable|string|max:255']);
        $nombreTitulo = trim((string) ($data['nombre_titulo'] ?? ''));
        $nombreTitulo = $nombreTitulo !== '' ? preg_replace('/\s+/', ' ', $nombreTitulo) : null;
        $registro->nombre_titulo = $nombreTitulo;
        $registro->titulo_catalogo_id = $this->resolveTituloCatalogoId($nombreTitulo);
        $registro->save();
        return back()->with('success', 'Título actualizado.');
    }

    public function actualizarInstitucion(Request $request, $id)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        if ($this->shouldLockTituloData($registro)) {
            $this->clearTituloData($registro);
            return back()->with('success', 'Institución limpiada porque el tipo de título es Ninguno.');
        }

        $data = $request->validate([
            'institucion_catalogo_selector' => ['nullable', 'string', 'max:50'],
            'institucion_educacional_otra' => ['nullable', 'string', 'max:255'],
        ]);

        $selector = trim((string) ($data['institucion_catalogo_selector'] ?? ''));
        $otraInstitucion = preg_replace('/\s+/', ' ', trim((string) ($data['institucion_educacional_otra'] ?? ''))) ?: null;

        if ($selector === '__otro__') {
            if ($otraInstitucion === null) {
                throw ValidationException::withMessages([
                    'institucion_educacional_otra' => 'Debe ingresar la institución cuando selecciona Otro.',
                ]);
            }

            $registro->institucion_catalogo_id = null;
            $registro->institucion_educacional = $otraInstitucion;
            $registro->save();

            return back()->with('success', 'Institución actualizada.');
        }

        if ($selector === '') {
            $registro->institucion_catalogo_id = null;
            $registro->institucion_educacional = null;
            $registro->save();

            return back()->with('success', 'Institución actualizada.');
        }

        $institucion = InstitucionCatalogo::findOrFail((int) $selector);
        $registro->institucion_catalogo_id = $institucion->id;
        $registro->institucion_educacional = $institucion->nombre;
        $registro->save();

        return back()->with('success', 'Institución actualizada.');
    }

    public function actualizarPais(Request $request, $id)
    {
        return $this->actualizarCampoTexto($request, $id, 'pais_titulo', 'País actualizado.', 100);
    }

    public function actualizarFuncion(Request $request, $id)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        if (mb_strtoupper((string) $registro->estamento) !== 'ASISTENTE') {
            return back()->with('error', 'La función solo aplica a registros del estamento asistente.');
        }

        $data = $request->validate([
            'funcion_catalogo_id' => ['nullable', 'integer', 'exists:funciones_catalogo,id'],
            'nombre_funcion_otro' => ['nullable', 'string', 'max:255'],
        ]);

        $funcion = !empty($data['funcion_catalogo_id'])
            ? FuncionCatalogo::find((int) $data['funcion_catalogo_id'])
            : null;

        $nombreFuncionOtro = preg_replace('/\s+/', ' ', trim((string) ($data['nombre_funcion_otro'] ?? ''))) ?: null;

        if ($funcion && $this->isOtroFuncion($funcion) && $nombreFuncionOtro === null) {
            throw ValidationException::withMessages([
                'nombre_funcion_otro' => 'Debe ingresar la función cuando selecciona Otro.',
            ]);
        }

        $registro->funcion_catalogo_id = $funcion?->id;
        if ($funcion && $this->isOtroFuncion($funcion)) {
            $registro->nombre_funcion = $nombreFuncionOtro;
        } else {
            $registro->nombre_funcion = $funcion?->nombre;
        }
        $registro->save();

        return redirect()->route('declaracion.index', $this->buildDeclaracionIndexQueryFromRequest($request))
            ->with('success', 'Función actualizada.');
    }


    public function actualizarTipoTitulo(Request $request, $id)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        if (mb_strtoupper((string) $registro->estamento) !== 'ASISTENTE') {
            return back()->with('error', 'El tipo de título solo aplica a registros del estamento asistente.');
        }

        $validated = $request->validate([
            'tipo_titulo' => ['nullable', 'string', 'max:20', 'in:Ninguno,Profesional,Técnico'],
        ]);

        $registro->tipo_titulo = $validated['tipo_titulo'] ?? null;
        if ($this->shouldLockTituloData($registro)) {
            $this->clearTituloData($registro, false);
        } else {
            $registro->save();
        }

        return back()->with('success', 'Tipo de título actualizado.');
    }

    public function actualizarDatosLaborales(Request $request, $id)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        $validated = $request->validate([
            'horas_contratadas' => ['nullable', 'integer', 'min:0', 'max:999'],
            'educacion_parvularia' => ['nullable', 'in:0,1'],
            'ensenanza_basica' => ['nullable', 'in:0,1'],
            'ensenanza_media' => ['nullable', 'in:0,1'],
        ]);

        $registro->horas_contratadas = array_key_exists('horas_contratadas', $validated)
            ? ($validated['horas_contratadas'] === null || $validated['horas_contratadas'] === '' ? null : (int) $validated['horas_contratadas'])
            : $registro->horas_contratadas;
        $registro->educacion_parvularia = $request->boolean('educacion_parvularia');
        $registro->ensenanza_basica = $request->boolean('ensenanza_basica');
        $registro->ensenanza_media = $request->boolean('ensenanza_media');
        $registro->save();

        return back()->with('success', 'Horas y niveles actualizados.');
    }

    public function actualizarRbd(Request $request, $id)
    {
        $this->authorizeDeclaracionAdmin();
        $data = $request->validate(['rbd' => ['required', 'string', 'max:50']]);
        $registro = DeclaracionSostenedor::findOrFail($id);
        $registro->update($data);
        return back()->with('success', 'RBD actualizado correctamente.');
    }

    public function confirmarRegistro($id)
    {
        $registro = DeclaracionSostenedor::with(['funcionCatalogo:id,nombre'])->findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);

        $errores = $this->getErroresConfirmacion($registro);
        if (!empty($errores)) {
            return back()->with('error', 'No se pudo confirmar el registro. ' . implode(' ', $errores));
        }

        if (!$registro->confirma_registro) {
            $registro->confirma_registro = true;
            $registro->save();
        }

        return back()->with('success', 'Registro confirmado.');
    }

    public function instructivoPdf(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $isDeclaracionAdmin = $this->hasDeclaracionAdminAccess($user, $activeRole);
        $isFuncionarioEstab = $activeRole === 'funcionario_estab';

        abort_unless($isDeclaracionAdmin || $isFuncionarioEstab, 403);

        $tab = strtolower(trim((string) $request->query('tab', 'docentes')));
        if (!in_array($tab, ['docentes', 'asistentes'], true)) {
            $tab = 'docentes';
        }

        $instructivo = $this->buildDeclaracionInstructivo($tab);
        $pdf = Pdf::loadView('pdf.declaracion-instructivo', [
            'instructivo' => $instructivo,
            'tab' => $tab,
            'tituloVista' => 'Declaración Establecimiento 2026',
        ])->setPaper('letter', 'portrait');

        $filename = 'declaracion_establecimiento_2026_instructivo_' . $tab . '.pdf';

        return $pdf->download($filename);
    }

    public function exportar(Request $request)
    {
        $this->authorizeDeclaracionAdmin();
        $filename = 'declaracion_sostenedores_' . now()->format('Ymd_His') . '.xlsx';
        $export = new DeclaracionSostenedoresExport($request->all());

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($export->headings(), null, 'A1');
        $rows = $export->rows();
        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportarDocumentos(Request $request)
    {
        $this->authorizeDeclaracionAdmin();

        $tabInput = trim((string) $request->input('tab_export', $request->input('tab', '')));
        $tab = in_array($tabInput, ['docentes', 'asistentes'], true) ? $tabInput : 'docentes';
        $filtros = [
            'rut' => trim((string) $request->input('rut', '')),
            'nombre' => trim((string) $request->input('nombre', '')),
            'establecimiento' => trim((string) $request->input('establecimiento', '')),
        ];

        $filename = 'declaracion_documentos_' . $tab . '_' . now()->format('Ymd_His') . '_' . uniqid() . '.zip';

        $exportacion = DeclaracionDocumentosExport::create([
            'user_id' => Auth::id(),
            'tab' => $tab,
            'filtros_json' => $filtros,
            'status' => 'pending',
            'file_name' => $filename,
        ]);

        GenerateDeclaracionDocumentosExport::dispatch($exportacion->id);

        return back()->with('success', 'La exportación de documentos se puso en cola. Recarga esta pantalla en unos minutos para descargar el archivo cuando quede listo.');
    }

    public function descargarExportacionDocumentos(int $id)
    {
        $this->authorizeDeclaracionAdmin();

        $exportacion = DeclaracionDocumentosExport::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if ($exportacion->status !== 'completed' || empty($exportacion->file_path)) {
            return back()->with('error', 'La exportación aún no está lista para descarga.');
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($exportacion->file_path)) {
            $exportacion->update([
                'status' => 'error',
                'error_message' => 'El archivo generado ya no existe en storage.',
            ]);

            return back()->with('error', 'El archivo generado ya no se encuentra disponible.');
        }

        return response()->download($disk->path($exportacion->file_path), $exportacion->file_name ?: basename($exportacion->file_path));
    }

    public function exportarPendientesDocumentos(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $isDeclaracionAdmin = $this->hasDeclaracionAdminAccess($user, $activeRole);

        abort_unless($isDeclaracionAdmin, 403);

        $tab = $this->resolveDeclaracionTab($request);
        $baseQuery = $this->buildDeclaracionFilteredQuery($request, true, false, $user);

        $query = (clone $baseQuery)
            ->where('estamento', $tab === 'docentes' ? 'DOCENTE' : 'ASISTENTE')
            ->orderBy('rbd')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres');

        if ($tab === 'docentes') {
            $query->where(function ($q) {
                $q->whereNull('certificado_titulo')
                    ->orWhere('certificado_titulo', '')
                    ->orWhereNull('certificado_antecedentes')
                    ->orWhere('certificado_antecedentes', '');
            });
        } else {
            $query->where(function ($q) {
                $q->whereNull('tipo_titulo')
                    ->orWhere('tipo_titulo', '<>', 'Ninguno');
            })->where(function ($q) {
                $q->whereNull('certificado_titulo')
                    ->orWhere('certificado_titulo', '')
                    ->orWhereNull('certificado_antecedentes')
                    ->orWhere('certificado_antecedentes', '');
            });
        }

        $registros = $query->get([
            'id',
            'numero',
            'rbd',
            'rut',
            'nombres',
            'apellido_paterno',
            'apellido_materno',
            'estamento',
            'tipo_titulo',
            'nombre_titulo',
            'institucion_educacional',
            'nombre_funcion',
            'certificado_titulo',
            'certificado_antecedentes',
            'confirma_registro',
        ]);

        $establecimientos = DB::table('establecimientos')
            ->select('cod_estab', 'nombre_establecimiento')
            ->get()
            ->keyBy(function ($item) {
                return (string) $item->cod_estab;
            });

        $rows = [];
        foreach ($registros as $registro) {
            $faltantes = [];

            if (blank($registro->certificado_antecedentes)) {
                $faltantes[] = 'Certificado de antecedentes';
            }

            if (blank($registro->certificado_titulo)) {
                $faltantes[] = 'Certificado de título';
            }

            $nombreCompleto = trim(implode(' ', array_filter([
                $registro->nombres,
                $registro->apellido_paterno,
                $registro->apellido_materno,
            ])));

            $establecimiento = $establecimientos->get((string) $registro->rbd);

            $rows[] = [
                $registro->rbd,
                $establecimiento->nombre_establecimiento ?? '',
                $registro->rut,
                $nombreCompleto,
                $registro->estamento,
                $registro->tipo_titulo ?: '',
                $registro->nombre_titulo ?: '',
                $registro->institucion_educacional ?: '',
                $registro->nombre_funcion ?: '',
                blank($registro->certificado_titulo) ? 'No' : 'Si',
                blank($registro->certificado_antecedentes) ? 'No' : 'Si',
                implode(', ', $faltantes),
                $registro->confirma_registro ? 'Si' : 'No',
            ];
        }

        $filename = 'declaracion_pendientes_documentos_' . $tab . '_' . now()->format('Ymd_His') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pendientes');
        $sheet->fromArray([
            'RBD',
            'Establecimiento',
            'RUT',
            'Nombre completo',
            'Estamento',
            'Tipo de titulo',
            'Nombre titulo',
            'Institucion educacional',
            'Funcion',
            'Certificado titulo cargado',
            'Certificado antecedentes cargado',
            'Documentos faltantes',
            'Registro confirmado',
        ], null, 'A1');

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportarReporteEstablecimientos(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $isDeclaracionAdmin = $this->hasDeclaracionAdminAccess($user, $activeRole);
        $isFuncionarioEstab = $activeRole === 'funcionario_estab';

        abort_unless($isDeclaracionAdmin || $isFuncionarioEstab, 403);

        $tab = $this->resolveDeclaracionTab($request);

        $baseQuery = $this->buildDeclaracionFilteredQuery($request, $isDeclaracionAdmin, $isFuncionarioEstab, $user);

        $rows = $this->buildEstablecimientoProgressReport((clone $baseQuery), $tab);
        $filename = 'declaracion_reporte_establecimientos_' . $tab . '_' . now()->format('Ymd_His') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte');
        $sheet->fromArray([
            'RBD',
            'Nombre establecimiento',
            'Confirmados',
            'Total funcionarios',
            '% confirmados',
            'Documentos cargados',
            'Documentos requeridos',
            '% documentos',
            '% avance general',
        ], null, 'A1');

        if (!empty($rows)) {
            $sheet->fromArray(array_map(function (array $row) {
                return [
                    $row['rbd'] ?? '',
                    $row['nombre_establecimiento'] ?? 'Sin nombre de establecimiento',
                    $row['confirmados'] ?? 0,
                    $row['total_funcionarios'] ?? 0,
                    ($row['porcentaje_confirmados'] ?? 0) / 100,
                    $row['documentos_cargados'] ?? 0,
                    $row['documentos_requeridos'] ?? 0,
                    ($row['porcentaje_documentos'] ?? 0) / 100,
                    ($row['porcentaje_general'] ?? 0) / 100,
                ];
            }, $rows), null, 'A2');

            $lastRow = count($rows) + 1;
            foreach (['E', 'H', 'I'] as $column) {
                $sheet->getStyle($column . '2:' . $column . $lastRow)
                    ->getNumberFormat()
                    ->setFormatCode('0%');
            }
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function buildTabStats($query, string $tab): array
    {
        if ($tab === 'docentes') {
            $query->where('estamento', 'DOCENTE');
        } else {
            $query->where('estamento', 'ASISTENTE');
        }

        $registros = $query->get([
            'id',
            'estamento',
            'confirma_registro',
            'certificado_titulo',
            'certificado_antecedentes',
            'nombre_titulo',
            'titulo_catalogo_id',
            'institucion_educacional',
            'institucion_catalogo_id',
            'fecha_titulacion',
            'pais_titulo',
            'tipo_titulo',
        ]);

        $totalFuncionarios = $registros->count();
        $confirmados = $registros->filter(function (DeclaracionSostenedor $registro) {
            return (bool) $registro->confirma_registro;
        })->count();

        $documentosRequeridos = 0;
        $documentosCargados = 0;

        foreach ($registros as $registro) {
            $documentosRequeridos += $registro->documentosRequeridosParaEstadisticaCount();
            $documentosCargados += $registro->documentosCargadosParaEstadisticaCount();
        }

        $porcentajeConfirmados = $totalFuncionarios > 0
            ? (int) round(($confirmados / $totalFuncionarios) * 100)
            : 0;

        $porcentajeDocumentos = $documentosRequeridos > 0
            ? (int) round(($documentosCargados / $documentosRequeridos) * 100)
            : 0;

        $unidadesCompletadas = $confirmados + $documentosCargados;
        $unidadesTotales = $totalFuncionarios + $documentosRequeridos;
        $porcentajeGeneral = $unidadesTotales > 0
            ? (int) round(($unidadesCompletadas / $unidadesTotales) * 100)
            : 0;

        return [
            'total_funcionarios' => $totalFuncionarios,
            'confirmados' => $confirmados,
            'documentos_requeridos' => $documentosRequeridos,
            'documentos_cargados' => $documentosCargados,
            'porcentaje_confirmados' => $porcentajeConfirmados,
            'porcentaje_documentos' => $porcentajeDocumentos,
            'porcentaje_general' => $porcentajeGeneral,
            'unidades_completadas' => $unidadesCompletadas,
            'unidades_totales' => $unidadesTotales,
        ];
    }

    protected function getErroresConfirmacion(DeclaracionSostenedor $registro): array
    {
        $errores = [];

        if (!$registro->hasNivelSeleccionado()) {
            $errores[] = 'Debe marcar al menos uno de los niveles PARV, BÁSICA o MEDIA.';
        }

        if ($registro->isDocente()) {
            if (blank($registro->certificado_titulo)) {
                $errores[] = 'Debe subir el Cert. Título.';
            }
            if (blank($registro->certificado_antecedentes)) {
                $errores[] = 'Debe subir el Cert. Antecedentes.';
            }
            if ($registro->horas_contratadas === null || $registro->horas_contratadas === '') {
                $errores[] = 'Debe completar HORAS.';
            }
            if (blank($registro->nombre_titulo)) {
                $errores[] = 'Debe completar Nombre Título.';
            }
            if (blank($registro->institucion_educacional)) {
                $errores[] = 'Debe completar Institución.';
            }
            if (blank($registro->fecha_titulacion)) {
                $errores[] = 'Debe completar Fecha Titulación.';
            }
            if (blank($registro->pais_titulo)) {
                $errores[] = 'Debe seleccionar País.';
            }

            return $errores;
        }

        if ($registro->isAsistente()) {
            if ($registro->requiereCertificadoAntecedentesParaConfirmacion() && blank($registro->certificado_antecedentes)) {
                $errores[] = 'Debe subir el Cert. Antecedentes.';
            }
            if ($registro->requiereCertificadoTituloParaConfirmacion() && blank($registro->certificado_titulo)) {
                $errores[] = 'Debe subir el Cert. Título cuando Tipo Título es distinto de Ninguno.';
            }
            if (!$registro->tieneFuncionSeleccionada()) {
                $errores[] = 'Debe seleccionar una Función.';
            } elseif ($registro->funcionEsOtro() && !$registro->tieneTextoFuncionOtro()) {
                $errores[] = 'Debe ingresar la Función cuando selecciona Otro.';
            }
        }

        return $errores;
    }


    protected function buildEstablecimientoProgressReport($query, string $tab): array
    {
        $tab = in_array($tab, ['docentes', 'asistentes'], true) ? $tab : 'docentes';

        $registros = (clone $query)
            ->where('estamento', $tab === 'docentes' ? 'DOCENTE' : 'ASISTENTE')
            ->orderBy('rbd')
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->get();

        if ($registros->isEmpty()) {
            return [];
        }

        $nombresEstablecimientos = DB::table('establecimientos')
            ->select('cod_estab', 'nombre_establecimiento')
            ->get()
            ->mapWithKeys(function ($item) {
                return [(string) $item->cod_estab => (string) $item->nombre_establecimiento];
            });

        return $registros
            ->groupBy(function ($registro) {
                return (string) ($registro->rbd ?? '');
            })
            ->map(function ($items, $rbd) use ($nombresEstablecimientos) {
                $totalFuncionarios = $items->count();
                $confirmados = $items->filter(fn ($registro) => (bool) $registro->confirma_registro)->count();
                $documentosRequeridos = $items->sum(fn ($registro) => $registro->documentosRequeridosParaEstadisticaCount());
                $documentosCargados = $items->sum(fn ($registro) => $registro->documentosCargadosParaEstadisticaCount());

                $unidadesTotales = $totalFuncionarios + $documentosRequeridos;
                $unidadesCompletadas = $confirmados + $documentosCargados;

                return [
                    'rbd' => $rbd,
                    'nombre_establecimiento' => $nombresEstablecimientos->get((string) $rbd, 'Sin nombre de establecimiento'),
                    'total_funcionarios' => $totalFuncionarios,
                    'confirmados' => $confirmados,
                    'documentos_requeridos' => $documentosRequeridos,
                    'documentos_cargados' => $documentosCargados,
                    'porcentaje_confirmados' => $totalFuncionarios > 0 ? (int) round(($confirmados / $totalFuncionarios) * 100) : 0,
                    'porcentaje_documentos' => $documentosRequeridos > 0 ? (int) round(($documentosCargados / $documentosRequeridos) * 100) : 0,
                    'porcentaje_general' => $unidadesTotales > 0 ? (int) round(($unidadesCompletadas / $unidadesTotales) * 100) : 0,
                ];
            })
            ->sortBy([
                ['rbd', 'asc'],
                ['nombre_establecimiento', 'asc'],
            ])
            ->values()
            ->all();
    }

    protected function canViewRegistro(DeclaracionSostenedor $registro): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if ($this->hasDeclaracionAdminAccess($user, $activeRole)) {
            return true;
        }

        if ($activeRole === 'funcionario_estab') {
            $userRbd = $this->getUserRbd($user);
            return $userRbd && (string) $userRbd === (string) $registro->rbd;
        }

        return false;
    }

    protected function canEditRegistro(DeclaracionSostenedor $registro): bool
    {
        return $this->canViewRegistro($registro);
    }

    protected function buildDeclaracionIndexQueryFromRequest(Request $request): array
    {
        $query = [];

        $tab = (string) $request->input('tab', 'docentes');
        $query['tab'] = in_array($tab, ['docentes', 'asistentes'], true) ? $tab : 'docentes';

        $page = (int) $request->input('page', 0);
        if ($page > 0) {
            $query['page'] = $page;
        }

        $establecimiento = trim((string) $request->input('establecimiento', ''));
        if ($establecimiento !== '') {
            $query['establecimiento'] = $establecimiento;
        }

        return $query;
    }

    protected function authorizeDeclaracionAdmin(): void
    {
        $user = Auth::user();
        $activeRole = $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        if (!$this->hasDeclaracionAdminAccess($user, $activeRole)) {
            abort(403);
        }
    }


    protected function resolveDeclaracionTab(Request $request): string
    {
        $tab = strtolower(trim((string) $request->query('tab', 'docentes')));

        return in_array($tab, ['docentes', 'asistentes'], true) ? $tab : 'docentes';
    }

    protected function buildDeclaracionFilteredQuery(Request $request, bool $isDeclaracionAdmin, bool $isFuncionarioEstab, $user)
    {
        $query = DeclaracionSostenedor::query();

        if ($isFuncionarioEstab) {
            $userRbd = $this->getUserRbd($user);
            abort_if(!$userRbd, 403, 'Tu usuario no tiene un establecimiento asignado.');
            $query->where('rbd', $userRbd);
        }

        if ($request->filled('rut')) {
            $query->where('rut', 'like', '%' . trim((string) $request->rut) . '%');
        }

        if ($request->filled('nombre')) {
            $nombre = trim((string) $request->nombre);
            $query->where(function ($q) use ($nombre) {
                $q->where('nombres', 'like', '%' . $nombre . '%')
                    ->orWhere('apellido_paterno', 'like', '%' . $nombre . '%')
                    ->orWhere('apellido_materno', 'like', '%' . $nombre . '%');
            });
        }

        if ($isDeclaracionAdmin && $request->filled('establecimiento')) {
            $query->where('rbd', trim((string) $request->establecimiento));
        }

        return $query;
    }

    protected function hasDeclaracionAdminAccess($user, ?string $activeRole): bool
    {
        if (!$user || !method_exists($user, 'canModule')) {
            return false;
        }

        return in_array($activeRole, ['admin'], true)
            && $user->canModule('declaracion', $activeRole);
    }

    protected function getUserRbd($user): ?string
    {
        if ($user && $user->relationLoaded('establecimiento') && $user->establecimiento) {
            return (string) ($user->establecimiento->rbd ?? $user->establecimiento->cod_estab ?? null);
        }
        if ($user && method_exists($user, 'establecimiento') && $user->establecimiento) {
            return (string) ($user->establecimiento->rbd ?? $user->establecimiento->cod_estab ?? null);
        }
        foreach (['rbd', 'cod_estab', 'establecimiento_rbd'] as $field) {
            if (!empty($user->{$field})) {
                return (string) $user->{$field};
            }
        }
        return null;
    }


    protected function resolveFuncionCatalogoId(?string $nombreFuncion): ?int
    {
        $nombreFuncion = $this->normalizeComparableText($nombreFuncion);
        if ($nombreFuncion === null) {
            return null;
        }

        $funcion = FuncionCatalogo::query()->get(['id', 'nombre'])->first(function ($item) use ($nombreFuncion) {
            return $this->normalizeComparableText((string) $item->nombre) === $nombreFuncion;
        });

        return $funcion?->id;
    }

    protected function resolveTituloCatalogoId(?string $nombreTitulo): ?int
    {
        $nombreTitulo = $this->normalizeComparableText($nombreTitulo);
        if ($nombreTitulo === null) {
            return null;
        }

        $titulo = TituloCatalogo::query()->get(['id', 'nombre'])->first(function ($item) use ($nombreTitulo) {
            return $this->normalizeComparableText((string) $item->nombre) === $nombreTitulo;
        });

        return $titulo?->id;
    }

    protected function resolveInstitucionCatalogoId(?string $nombreInstitucion): ?int
    {
        $nombreInstitucion = $this->normalizeComparableText($nombreInstitucion);
        if ($nombreInstitucion === null) {
            return null;
        }

        $institucion = InstitucionCatalogo::query()->get(['id', 'nombre'])->first(function ($item) use ($nombreInstitucion) {
            return $this->normalizeComparableText((string) $item->nombre) === $nombreInstitucion;
        });

        return $institucion?->id;
    }

    protected function normalizeComparableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return (string) \Illuminate\Support\Str::of($value)
            ->lower()
            ->ascii()
            ->replace(['_', '-', '.', 'º', '°', '#'], ' ')
            ->squish();
    }

    protected function isOtroFuncion(FuncionCatalogo $funcion): bool
    {
        return $this->normalizeComparableText((string) $funcion->nombre) === 'otro';
    }

    protected function deleteArchivo(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    protected function buildDeclaracionCertificadoPath(DeclaracionSostenedor $registro, $archivo, string $tipo): string
    {
        $rbdSegment = $this->sanitizePathSegment((string) ($registro->rbd ?? 'sin-rbd'), 'sin-rbd');
        $rutSegment = $this->sanitizePathSegment((string) ($registro->rut ?? 'sin-rut'), 'sin-rut');
        $tipoSegment = $tipo === 'antecedentes' ? 'antecedentes' : 'titulo';
        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        $extension = $extension !== '' ? $extension : 'pdf';

        $relativeDir = 'declaracion/' . $rbdSegment . '/' . $rutSegment;
        $filename = $rutSegment . '-' . $tipoSegment . '.' . $extension;

        return $relativeDir . '/' . $filename;
    }

    protected function sanitizePathSegment(?string $value, string $fallback = 'archivo'): string
    {
        $value = trim((string) $value);
        $value = (string) \Illuminate\Support\Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-');

        return $value !== '' ? $value : $fallback;
    }

    protected function buildDeclaracionInstructivo(string $tab): array
    {
        $tab = in_array($tab, ['docentes', 'asistentes'], true) ? $tab : 'docentes';

        $base = [
            'docentes' => [
                'titulo' => 'Instrucciones - Docentes',
                'subtitulo' => 'Completa y confirma solo cuando el registro quede totalmente respaldado.',
                'secciones' => [
                    [
                        'titulo' => 'Documentos obligatorios',
                        'items' => [
                            'Sube Cert. Título en formato PDF.',
                            'Sube Cert. Antecedentes en formato PDF.',
                            'Cada archivo debe pesar como máximo 10 MB.',
                        ],
                    ],
                    [
                        'titulo' => 'Datos mínimos para completar',
                        'items' => [
                            'Completa HORAS con un valor entero.',
                            'Selecciona al menos uno entre PARV, BÁSICA y MEDIA.',
                            'Selecciona Nombre Título desde el catálogo con buscador.',
                            'Selecciona Institución desde el catálogo con buscador; si eliges Otro, escribe la institución.',
                            'Completa Fecha Titulación y País.',
                        ],
                    ],
                    [
                        'titulo' => 'Confirmación del registro',
                        'items' => [
                            'El botón Confirmar se habilita solo cuando existan ambos certificados y todos los campos obligatorios estén completos.',
                            'Una vez confirmado, la fila quedará destacada visualmente en verde claro.',
                        ],
                    ],
                    [
                        'titulo' => 'Si no aparece un funcionario en el registro',
                        'items' => [
                            'Si no encuentras a un funcionario en la nómina del establecimiento, informa su RUT, nombre completo y función para gestionar la incorporación al establecimiento.',
                            'Envía el correo a alonso.larenas@slepandaliencosta.gob.cl o abel.munoz@slepandaliencosta.gob.cl, con copia a karla.munoz@slepandaliencosta.gob.cl.',
                        ],
                    ],
                ],
            ],
            'asistentes' => [
                'titulo' => 'Instrucciones - Asistentes',
                'subtitulo' => 'Completa función, antecedentes y datos de título solo cuando corresponda.',
                'secciones' => [
                    [
                        'titulo' => 'Documentos obligatorios',
                        'items' => [
                            'Sube Cert. Antecedentes en formato PDF para todos los asistentes.',
                            'Si Tipo Título es distinto de Ninguno, también debes subir Cert. Título en formato PDF.',
                            'Cada archivo debe pesar como máximo 10 MB.',
                        ],
                    ],
                    [
                        'titulo' => 'Datos mínimos para completar',
                        'items' => [
                            'Selecciona al menos uno entre PARV, BÁSICA y MEDIA.',
                            'Selecciona una Función del catálogo.',
                            'Si eliges Otro en Función, escribe la función en el cuadro de texto adicional.',
                            'Si Tipo Título es Ninguno, los campos de título quedan bloqueados y no se exige Cert. Título.',
                            'Si Tipo Título es Profesional o Técnico, completa Nombre Título, Institución, Fecha Titulación y País.',
                        ],
                    ],
                    [
                        'titulo' => 'Confirmación del registro',
                        'items' => [
                            'El botón Confirmar se habilita solo cuando el registro cumpla las validaciones del estamento.',
                            'Si falta algo, el sistema mostrará una pista visual bajo el botón Confirmar.',
                        ],
                    ],
                    [
                        'titulo' => 'Si no aparece un funcionario en el registro',
                        'items' => [
                            'Si no encuentras a un funcionario en la nómina del establecimiento, informa su RUT, nombre completo y función para gestionar la incorporación al establecimiento.',
                            'Envía el correo a alonso.larenas@slepandaliencosta.gob.cl o abel.munoz@slepandaliencosta.gob.cl, con copia a karla.munoz@slepandaliencosta.gob.cl.',
                        ],
                    ],
                ],
            ],
        ];

        return $base[$tab];
    }

    protected function actualizarCampoTexto(Request $request, int $id, string $campo, string $success, int $max)
    {
        $registro = DeclaracionSostenedor::findOrFail($id);
        abort_unless($this->canEditRegistro($registro), 403);
        $data = $request->validate([$campo => 'nullable|string|max:' . $max]);
        $registro->update($data);
        return back()->with('success', $success);
    }

    protected function shouldLockTituloData(DeclaracionSostenedor $registro): bool
    {
        return mb_strtoupper((string) $registro->estamento) === 'ASISTENTE'
            && (string) ($registro->tipo_titulo ?? '') === 'Ninguno';
    }

    protected function clearTituloData(DeclaracionSostenedor $registro, bool $preserveTipoTitulo = true): void
    {
        $updates = [
            'nombre_titulo' => null,
            'titulo_catalogo_id' => null,
            'institucion_educacional' => null,
            'institucion_catalogo_id' => null,
            'fecha_titulacion' => null,
        ];

        if (!$preserveTipoTitulo) {
            $updates['tipo_titulo'] = $registro->tipo_titulo;
        }

        $registro->fill($updates);
        $registro->save();
    }


    private function tiposTituloAsistente(): array
    {
        return [
            '' => '<Seleccione>',
            'Ninguno' => 'Ninguno',
            'Profesional' => 'Profesional',
            'Técnico' => 'Técnico',
        ];
    }

    private function nacionalidades(): array
    {
        return [
            'Chile' => 'Chile',
            'Argentina' => 'Argentina',
            'Perú' => 'Perú',
            'Bolivia' => 'Bolivia',
            'Brasil' => 'Brasil',
            'Uruguay' => 'Uruguay',
            'Paraguay' => 'Paraguay',
            'Colombia' => 'Colombia',
            'Venezuela' => 'Venezuela',
            'Ecuador' => 'Ecuador',
            'México' => 'México',
            'España' => 'España',
            'Estados Unidos' => 'Estados Unidos',
            'Italia' => 'Italia',
            'Francia' => 'Francia',
            'Alemania' => 'Alemania',
            'China' => 'China',
            'Japón' => 'Japón',
            'Corea del Sur' => 'Corea del Sur',
            'Otro' => 'Otro',
        ];
    }
}
