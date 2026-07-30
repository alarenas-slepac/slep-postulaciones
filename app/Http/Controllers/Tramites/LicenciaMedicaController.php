<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\LicenciaMedica;
use App\Models\LicenciaMedicaHistorial;
use App\Models\ReemplazoPersonal;
use App\Services\LicenciasMedicas\LicenciaFolio;
use App\Services\LicenciasMedicas\LicenciaPdfExtractor;
use App\Services\LicenciasMedicas\LicenciaFuncionarioResolver;
use App\Services\LicenciasMedicas\LicenciaDiasLaboralesService;
use App\Services\LicenciasMedicas\RutNormalizer;
use App\Services\LicenciasMedicas\LicenciaSeguimientoImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LicenciaMedicaController extends Controller
{
    public function index(Request $request)
    {
        $query = LicenciaMedica::query()->latest('id');

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('folio_licencia', 'like', "%{$q}%")
                    ->orWhere('rut_formateado', 'like', "%{$q}%")
                    ->orWhere('rut_normalizado', 'like', "%{$q}%")
                    ->orWhere('nombre_funcionario', 'like', "%{$q}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado_actual', $request->estado);
        }

        if ($request->filled('origen')) {
            $query->where('origen_ingreso', $request->origen);
        }

        if ($request->filled('mes')) {
            $query->whereMonth('fecha_inicio', (int) $request->mes);
        }

        if ($request->filled('anio')) {
            $query->whereYear('fecha_inicio', (int) $request->anio);
        }

        $licencias = $query->paginate(20)->withQueryString();

        $metricas = [
            'total' => LicenciaMedica::count(),
            'mes' => LicenciaMedica::whereBetween('fecha_inicio', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'digitales' => LicenciaMedica::where('origen_ingreso', 'digital_pdf')->count(),
            'escaneadas' => LicenciaMedica::where('origen_ingreso', 'escaneada_manual')->count(),
            'sin_asociacion' => LicenciaMedica::where('fuente_asociacion_funcionario', 'sin_asociacion')->count(),
            'administracion_central' => LicenciaMedica::where('tipo_dependencia', 'administracion_central')->count(),
            'establecimiento' => LicenciaMedica::where('tipo_dependencia', 'establecimiento')->count(),
        ];

        return view('tramites.licencias-medicas.index', compact('licencias', 'metricas'));
    }

    public function create(Request $request)
    {
        $tipoDocumento = $request->input('tipo_documento');
        $extracted = session('licencia_medica_extracted', []);
        $archivoTemporal = session('licencia_medica_archivo_temporal');

        return view('tramites.licencias-medicas.create', compact('tipoDocumento', 'extracted', 'archivoTemporal'));
    }

    public function extractDigital(Request $request, LicenciaPdfExtractor $extractor)
    {
        $request->validate([
            'archivo_licencia' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'archivo_licencia.required' => 'Debe adjuntar la licencia medica digital en PDF.',
            'archivo_licencia.mimes' => 'La licencia digital debe estar en formato PDF.',
        ]);

        $file = $request->file('archivo_licencia');
        $path = $file->store('licencias_medicas/tmp', 'local');
        $absolute = Storage::disk('local')->path($path);
        $result = $extractor->extract($absolute);

        $request->session()->put('licencia_medica_extracted', $result);
        $request->session()->put('licencia_medica_archivo_temporal', [
            'path' => $path,
            'nombre' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return redirect()
            ->route('tramites.licencias-medicas.create', ['tipo_documento' => 'digital'])
            ->with('success', 'PDF digital procesado. Revise y confirme los datos antes de guardar.');
    }



    public function descartarCarga(Request $request)
    {
        $tmp = $request->session()->get('licencia_medica_archivo_temporal');

        if (is_array($tmp) && !empty($tmp['path']) && Storage::disk('local')->exists($tmp['path'])) {
            Storage::disk('local')->delete($tmp['path']);
        }

        $request->session()->forget([
            'licencia_medica_extracted',
            'licencia_medica_archivo_temporal',
        ]);

        $tipoDocumento = $request->input('tipo_documento');
        $params = in_array($tipoDocumento, ['digital', 'escaneada'], true)
            ? ['tipo_documento' => $tipoDocumento]
            : [];

        return redirect()
            ->route('tramites.licencias-medicas.create', $params)
            ->with('success', 'Carga anterior descartada. Puede iniciar nuevamente el ingreso de la licencia médica.');
    }

    public function store(Request $request)
    {
        $this->normalizarFechasFormulario($request, [
            'fecha_emision',
            'fecha_recepcion',
            'fecha_inicio',
            'fecha_termino',
        ]);

        $validator = Validator::make($request->all(), [
            'tipo_documento_ingreso' => ['required', Rule::in(['digital', 'escaneada'])],
            'tipo_ingreso_licencia' => ['required', Rule::in(['1', '2', '3', '4'])],
            'cuerpo_licencia' => ['required', 'digits_between:5,12'],
            'dv_licencia' => ['required', 'regex:/^[0-9Kk]$/'],
            'rut_funcionario_input' => ['required', 'string', 'max:20'],
            'nombre_funcionario' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_termino' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_recepcion' => ['nullable', 'date'],
            'dias_solicitados' => ['nullable', 'integer', 'min:1', 'max:365'],
            'tipo_licencia' => ['nullable', Rule::in(['1', '2', '3', '4', '5', '6', '7'])],
            'sistema_salud' => ['nullable', Rule::in(['FONASA', 'ISAPRE'])],
            'institucion_salud' => ['nullable', 'string', 'max:150', 'required_if:sistema_salud,ISAPRE'],
            'archivo_licencia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'archivo_temporal_path' => ['nullable', 'string'],
        ], [
            'tipo_documento_ingreso.required' => 'Debe seleccionar si la licencia es digital o escaneada.',
            'tipo_ingreso_licencia.required' => 'Debe indicar el tipo de ingreso del folio de licencia.',
            'tipo_ingreso_licencia.in' => 'El tipo de ingreso debe ser 1, 2, 3 o 4.',
            'cuerpo_licencia.required' => 'Debe indicar el cuerpo de la licencia médica.',
            'cuerpo_licencia.digits_between' => 'El cuerpo de la licencia debe contener solo números entre 5 y 12 dígitos.',
            'dv_licencia.required' => 'Debe indicar el DV de la licencia médica.',
            'dv_licencia.regex' => 'El DV de la licencia debe ser un número o K.',
            'rut_funcionario_input.required' => 'Debe indicar el RUT del funcionario.',
            'nombre_funcionario.required' => 'Debe indicar el nombre del funcionario.',
            'fecha_inicio.required' => 'Debe indicar la fecha de inicio del reposo.',
            'fecha_inicio.date' => 'La fecha de inicio no tiene un formato válido.',
            'fecha_termino.date' => 'La fecha de término no tiene un formato válido.',
            'fecha_termino.after_or_equal' => 'La fecha de término no puede ser anterior a la fecha de inicio.',
            'institucion_salud.required_if' => 'Para licencias de ISAPRE debe indicar la institución de salud.',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $folioData = LicenciaFolio::fromParts(
            $request->input('tipo_ingreso_licencia'),
            $request->input('cuerpo_licencia'),
            $request->input('dv_licencia')
        );

        $tipo = $folioData['tipo_ingreso_licencia'];
        $cuerpo = $folioData['cuerpo_licencia'];
        $dvLicencia = $folioData['dv_licencia'];
        $folio = $folioData['folio_licencia'];

        if ($folioData['valido'] !== true) {
            return back()->withInput()->withErrors(['folio' => 'El folio de licencia médica no es válido. Revise tipo de ingreso, cuerpo y DV. El DV 0 es válido y debe aceptarse.']);
        }

        $exists = LicenciaMedica::where('tipo_ingreso_licencia', $tipo)
            ->where('cuerpo_licencia', $cuerpo)
            ->where('dv_licencia', $dvLicencia)
            ->exists();

        if ($exists) {
            return back()->withInput()->withErrors(['folio' => 'Ya existe una licencia medica registrada con este folio.']);
        }

        if ($request->tipo_documento_ingreso === 'digital') {
            $tmp = session('licencia_medica_archivo_temporal');
            if (!$tmp || empty($tmp['path']) || !Storage::disk('local')->exists($tmp['path'])) {
                return back()->withInput()->withErrors(['archivo_licencia' => 'Debe cargar y procesar el PDF digital antes de guardar la licencia.']);
            }
        }

        if ($request->tipo_documento_ingreso === 'escaneada' && !$request->hasFile('archivo_licencia')) {
            return back()->withInput()->withErrors(['archivo_licencia' => 'Debe adjuntar el archivo escaneado como respaldo antes de guardar.']);
        }

        $rut = RutNormalizer::normalize($request->rut_funcionario_input);
        if (!$rut['rut']) {
            return back()->withInput()->withErrors(['rut_funcionario_input' => 'El RUT del funcionario no es valido.']);
        }

        $archivo = $this->storeArchivoLicencia($request, $folio, $rut['normalizado']);
        $asociacion = app(LicenciaFuncionarioResolver::class)->resolve($rut['normalizado'], $rut['rut'], $request->input('establecimiento_nombre'), $request->input('comuna'));
        $fechaInicio = Carbon::parse($request->fecha_inicio);
        $fechaTermino = $request->filled('fecha_termino')
            ? Carbon::parse($request->fecha_termino)
            : ($request->filled('dias_solicitados') ? $fechaInicio->copy()->addDays(((int) $request->dias_solicitados) - 1) : null);

        $calculoDias = app(LicenciaDiasLaboralesService::class)->calcular(
            $fechaInicio->format('Y-m-d'),
            $fechaTermino?->format('Y-m-d')
        );
        $diasCorridos = $calculoDias['dias_corridos'];
        $diasLaborales = $calculoDias['dias_laborales'];
        $origen = $request->tipo_documento_ingreso === 'digital' ? 'digital_pdf' : 'escaneada_manual';
        $extraccion = session('licencia_medica_extracted');

        $licencia = DB::transaction(function () use ($request, $tipo, $cuerpo, $dvLicencia, $folio, $rut, $archivo, $asociacion, $fechaInicio, $fechaTermino, $diasCorridos, $diasLaborales, $origen, $extraccion) {
            $licencia = LicenciaMedica::create([
                'tipo_ingreso_licencia' => $tipo,
                'cuerpo_licencia' => $cuerpo,
                'dv_licencia' => $dvLicencia,
                'folio_licencia' => $folio,
                'rut_funcionario' => $rut['rut'],
                'dv_funcionario' => $rut['dv'],
                'rut_normalizado' => $rut['normalizado'],
                'rut_formateado' => $rut['formateado'],
                'nombre_funcionario' => $request->nombre_funcionario,
                'sexo' => $request->sexo,
                'edad' => $request->edad,
                'tipo_dependencia' => $asociacion['tipo_dependencia'] ?? 'sin_asociacion',
                'establecimiento_id' => $asociacion['establecimiento_id'],
                'establecimiento_nombre' => $asociacion['establecimiento_nombre'],
                'comuna' => $asociacion['comuna'],
                'subdireccion' => $asociacion['subdireccion'] ?? null,
                'unidad_departamento' => $asociacion['unidad_departamento'] ?? null,
                'cargo' => $asociacion['cargo'] ?? null,
                'grado' => $asociacion['grado'] ?? null,
                'escalafon' => $asociacion['escalafon'] ?? null,
                'calidad_juridica' => ($asociacion['calidad_juridica'] ?? null) ?: $request->calidad_juridica,
                'estamento' => ($asociacion['estamento'] ?? null) ?: $request->estamento,
                'correo_funcionario' => $asociacion['correo_funcionario'] ?? null,
                'fecha_emision' => $request->fecha_emision,
                'fecha_recepcion' => $request->fecha_recepcion,
                'fecha_inicio' => $fechaInicio->format('Y-m-d'),
                'fecha_termino' => $fechaTermino?->format('Y-m-d'),
                'dias_solicitados' => $request->dias_solicitados,
                'dias_corridos' => $diasCorridos,
                'dias_laborales' => $diasLaborales,
                'tipo_licencia' => $request->tipo_licencia,
                'tipo_licencia_glosa' => $request->tipo_licencia_glosa,
                'sistema_salud' => $request->sistema_salud,
                'institucion_salud' => $this->normalizarInstitucionSalud($request->sistema_salud, $request->institucion_salud),
                'tipo_reposo' => $request->tipo_reposo,
                'lugar_reposo' => $request->lugar_reposo,
                'direccion_reposo' => $request->direccion_reposo,
                'telefono' => $request->telefono,
                'correo_trabajador' => $request->correo_trabajador,
                'rut_empleador' => $request->rut_empleador,
                'nombre_empleador' => $request->nombre_empleador,
                'estado_actual' => $request->estado_actual ?: 'Ingresada',
                'estado_notificacion' => 'sin_notificacion',
                'estado_alerta' => 'sin_alerta',
                'origen_ingreso' => $origen,
                'tipo_documento_ingreso' => $request->tipo_documento_ingreso,
                'archivo_licencia_path' => $archivo['path'] ?? null,
                'archivo_licencia_nombre' => $archivo['nombre'] ?? null,
                'archivo_licencia_mime' => $archivo['mime'] ?? null,
                'archivo_licencia_size' => $archivo['size'] ?? null,
                'extraccion_pdf_estado' => $request->tipo_documento_ingreso === 'digital' ? ($extraccion['estado'] ?? null) : null,
                'extraccion_pdf_json' => $request->tipo_documento_ingreso === 'digital' ? ($extraccion ?: null) : null,
                'extraccion_pdf_confianza' => $request->tipo_documento_ingreso === 'digital' ? ($extraccion['confianza'] ?? null) : null,
                'fuente_asociacion_funcionario' => $asociacion['fuente'],
                'periodo_reemplazos_usado' => $asociacion['periodo'],
                'observaciones' => $request->observaciones,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            LicenciaMedicaHistorial::create([
                'licencia_medica_id' => $licencia->id,
                'accion' => 'creacion',
                'descripcion' => 'Ingreso de licencia medica ' . ($request->tipo_documento_ingreso === 'digital' ? 'digital con respaldo PDF' : 'escaneada/manual con respaldo'),
                'datos_nuevos' => $licencia->toArray(),
                'user_id' => $request->user()->id,
                'created_at' => now(),
            ]);

            return $licencia;
        });

        session()->forget(['licencia_medica_extracted', 'licencia_medica_archivo_temporal']);

        return redirect()->route('tramites.licencias-medicas.show', $licencia)->with('success', 'Licencia medica registrada correctamente.');
    }


    public function importarSeguimientoForm()
    {
        return view('tramites.licencias-medicas.importar-seguimiento');
    }

    public function importarSeguimiento(Request $request, LicenciaSeguimientoImportService $importer)
    {
        $request->validate([
            'archivo_seguimiento' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
            'tipo_ingreso_default' => ['required', Rule::in(['1', '2', '3', '4'])],
        ], [
            'archivo_seguimiento.required' => 'Debe adjuntar el archivo Excel de seguimiento de licencias medicas.',
            'archivo_seguimiento.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
            'tipo_ingreso_default.required' => 'Debe indicar el tipo de ingreso por defecto para construir el folio historico.',
        ]);

        $file = $request->file('archivo_seguimiento');
        $storedPath = $file->store('licencias_medicas/importaciones/seguimiento/' . now()->format('Y/m'), 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $resultado = $importer->import(
                $absolutePath,
                $request->user()->id,
                $file->getClientOriginalName(),
                $storedPath,
                $request->input('tipo_ingreso_default', '3')
            );
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors(['archivo_seguimiento' => 'No se pudo procesar la planilla completa. Revise que el archivo no esté dañado y vuelva a intentar. Detalle técnico: ' . $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('tramites.licencias-medicas.importar-seguimiento')
            ->with('success', 'Importacion de seguimiento procesada correctamente.')
            ->with('import_result', $resultado);
    }

    public function show(LicenciaMedica $licenciaMedica)
    {
        $licenciaMedica->load(['historial.usuario']);
        return view('tramites.licencias-medicas.show', ['licencia' => $licenciaMedica]);
    }

    public function descargarArchivo(LicenciaMedica $licenciaMedica)
    {
        abort_unless($licenciaMedica->archivo_licencia_path && Storage::disk('local')->exists($licenciaMedica->archivo_licencia_path), 404);
        return Storage::disk('local')->download($licenciaMedica->archivo_licencia_path, $licenciaMedica->archivo_licencia_nombre ?: ('licencia_' . $licenciaMedica->folio_licencia . '.pdf'));
    }


    private function normalizarFechasFormulario(Request $request, array $campos): void
    {
        $merge = [];

        foreach ($campos as $campo) {
            $valor = trim((string) $request->input($campo, ''));
            if ($valor === '') {
                continue;
            }

            $normalizada = $this->normalizarFechaFormulario($valor);
            if ($normalizada) {
                $merge[$campo] = $normalizada;
            }
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    private function normalizarFechaFormulario(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor);
                if ($fecha) {
                    return $fecha->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Probar siguiente formato.
            }
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }


    private function normalizarInstitucionSalud(?string $sistemaSalud, ?string $institucionSalud): ?string
    {
        $sistemaSalud = strtoupper(trim((string) $sistemaSalud));
        $institucionSalud = trim((string) $institucionSalud);

        if ($sistemaSalud === 'FONASA') {
            return $institucionSalud !== '' ? $institucionSalud : 'FONASA';
        }

        if ($sistemaSalud === 'ISAPRE') {
            return $institucionSalud !== '' ? $institucionSalud : null;
        }

        return $institucionSalud !== '' ? $institucionSalud : null;
    }

    private function storeArchivoLicencia(Request $request, string $folio, string $rutNormalizado): array
    {
        $safeFolio = str_replace('-', '_', $folio);
        $baseDir = 'licencias_medicas/originales/' . now()->format('Y/m') . '/' . $rutNormalizado;

        if ($request->tipo_documento_ingreso === 'digital') {
            $tmp = session('licencia_medica_archivo_temporal');
            if ($tmp && isset($tmp['path']) && Storage::disk('local')->exists($tmp['path'])) {
                $extension = pathinfo($tmp['nombre'] ?? '', PATHINFO_EXTENSION) ?: 'pdf';
                $target = $baseDir . '/' . $safeFolio . '_digital.' . strtolower($extension);
                Storage::disk('local')->move($tmp['path'], $target);
                return [
                    'path' => $target,
                    'nombre' => $tmp['nombre'] ?? basename($target),
                    'mime' => $tmp['mime'] ?? 'application/pdf',
                    'size' => $tmp['size'] ?? null,
                ];
            }
        }

        if ($request->hasFile('archivo_licencia')) {
            $file = $request->file('archivo_licencia');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $target = $file->storeAs($baseDir, $safeFolio . '_' . $request->tipo_documento_ingreso . '.' . $extension, 'local');
            return [
                'path' => $target,
                'nombre' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        return [];
    }

    private function resolverAsociacionReemplazos(?string $rutNormalizado, ?string $rutCuerpo, ?string $establecimientoManual, ?string $comunaManual): array
    {
        $base = [
            'establecimiento_id' => null,
            'establecimiento_nombre' => $establecimientoManual,
            'comuna' => $comunaManual,
            'calidad_juridica' => null,
            'estamento' => null,
            'fuente' => 'sin_asociacion',
            'periodo' => null,
        ];

        try {
            $periodo = ReemplazoPersonal::query()
                ->select('anio', 'mes')
                ->whereNotNull('anio')
                ->whereNotNull('mes')
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->first();

            if (!$periodo) return $base;

            $rutDigits = preg_replace('/\D/', '', (string) $rutCuerpo);
            $rutNorm = preg_replace('/[^0-9K]/', '', strtoupper((string) $rutNormalizado));

            $registro = ReemplazoPersonal::query()
                ->with('establecimiento')
                ->where('anio', $periodo->anio)
                ->where('mes', $periodo->mes)
                ->where(function ($q) use ($rutDigits, $rutNorm) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$rutNorm])
                      ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') LIKE ?", [$rutDigits . '%']);
                })
                ->first();

            if (!$registro) {
                $base['periodo'] = sprintf('%04d-%02d', $periodo->anio, $periodo->mes);
                return $base;
            }

            return [
                'establecimiento_id' => $registro->establecimiento_id,
                'establecimiento_nombre' => optional($registro->establecimiento)->nombre ?: $establecimientoManual,
                'comuna' => optional($registro->establecimiento)->comuna ?: $comunaManual,
                'calidad_juridica' => $registro->tipocontrato,
                'estamento' => $registro->escalafon ?: $registro->estatuto,
                'fuente' => 'reemplazos_personal_mes_reciente',
                'periodo' => sprintf('%04d-%02d', $periodo->anio, $periodo->mes),
            ];
        } catch (\Throwable $e) {
            return $base;
        }
    }

    public function recalcularDias(LicenciaMedica $licenciaMedica, LicenciaDiasLaboralesService $diasService)
    {
        if (! $licenciaMedica->fecha_inicio || ! $licenciaMedica->fecha_termino) {
            return back()->withErrors(['dias' => 'La licencia debe tener fecha de inicio y fecha de término para recalcular días laborales.']);
        }

        $anteriores = [
            'dias_corridos' => $licenciaMedica->dias_corridos,
            'dias_laborales' => $licenciaMedica->dias_laborales,
        ];

        $calculo = $diasService->calcular(
            $licenciaMedica->fecha_inicio->format('Y-m-d'),
            $licenciaMedica->fecha_termino->format('Y-m-d')
        );

        $licenciaMedica->update([
            'dias_corridos' => $calculo['dias_corridos'],
            'dias_laborales' => $calculo['dias_laborales'],
            'updated_by' => auth()->id(),
        ]);

        LicenciaMedicaHistorial::create([
            'licencia_medica_id' => $licenciaMedica->id,
            'accion' => 'recalculo_dias_laborales',
            'descripcion' => 'Recalculo de días corridos y laborales usando feriados activos del módulo de licencias médicas.',
            'datos_anteriores' => $anteriores,
            'datos_nuevos' => [
                'dias_corridos' => $calculo['dias_corridos'],
                'dias_laborales' => $calculo['dias_laborales'],
                'feriados_descontados' => $calculo['feriados_descontados'],
            ],
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'Días corridos y laborales recalculados correctamente.');
    }
}

