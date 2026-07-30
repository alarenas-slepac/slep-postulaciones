<?php

namespace App\Http\Controllers\FuncionarioEstab;

use App\Http\Controllers\Controller;
use App\Support\PdfBranding;
use App\Mail\SolicitudReemplazoCreada;
use App\Models\Establecimiento;
use App\Models\PostulantProfile;
use App\Models\ReemplazoPersonal;
use App\Models\ReemplazoPersonalBloqueo;
use App\Models\PermisoSinGoceExcepcion;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoJornada;
use App\Models\SolicitudReemplazoObservacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\NotificationAudit;
use Illuminate\Support\Facades\Storage;
use App\Models\AreaDesempeno;
use App\Models\DocumentType;
use App\Models\UserDocument;
use App\Models\User;
use App\Support\DocumentRules;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\RestrictedRutService;
use App\Support\ReemplazoSolicitudReglaMinima;

class SolicitudReemplazoController extends Controller
{
    public function index(Request $request)
    {
        $establecimiento = $this->establecimientoDelUsuario();

        $estado = $request->query('estado', '');
        $vigencia = $request->query('vigencia', 'todos'); // activos|caducados|todos

        $q = SolicitudReemplazo::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->with(['funcionarioTitular', 'postulante', 'areaDesempeno', 'observacionSlepUser'])
            ->orderByDesc('created_at');

        if ($estado !== '') {
            $q->where('estado', $estado);
        }

        $hoy = now()->startOfDay();

        if ($vigencia === 'activos') {
            $q->whereDate('fecha_inicio', '<=', $hoy)
                ->whereDate('fecha_termino', '>=', $hoy);
        } elseif ($vigencia === 'caducados') {
            $q->whereDate('fecha_termino', '<', $hoy);
        }

        $solicitudes = $q->paginate(15)->withQueryString();

        return view('funcionario.solicitudes-reemplazo.index', compact('establecimiento', 'solicitudes', 'estado', 'vigencia'));
    }

    public function create(Request $request)
    {
        $establecimiento = $this->establecimientoDelUsuario();
        
        // Áreas de desempeño bloqueadas por sobredotación (para alert/bloqueo del botón Enviar)
        $areasBloqueadasIds = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('establecimiento_area_desempeno')) {
            $areasBloqueadasIds = \Illuminate\Support\Facades\DB::table('establecimiento_area_desempeno')
                ->where('establecimiento_id', (int) $establecimiento->id)
                ->where('bloqueada', 1)
                ->pluck('area_desempeno_id')
                ->map(fn($v) => (int) $v)
                ->all();
        }

        $user = auth()->user();

        return view('funcionario.solicitudes-reemplazo.create', [
            'establecimiento' => $establecimiento,
            'fechaSolicitud' => cl_date(now(), 'd/m/Y'),
            'horaSolicitud' => cl_time(now(), 'H:i'),
            'contactoNombre' => $this->nombreCompletoUsuario($user),
            'contactoEmail' => $user->email ?? '—',

            // ✅ URLs para el JS (relativas => evita mixed content http/https)
                        // Áreas bloqueadas por sobredotación (ids)
            'areasBloqueadasIds' => $areasBloqueadasIds,

'urls' => [
                'funcionarios' => route('funcionario.solicitudes-reemplazo.ajax.funcionarios', [], false),
                'funcionarioDetalleTpl' => route(
                    'funcionario.solicitudes-reemplazo.ajax.funcionario.detalle',
                    ['reemplazoPersonal' => '___ID___'],
                    false
                ),
                'areasDesempeno' => route('funcionario.solicitudes-reemplazo.ajax.areas-desempeno', [], false),
                'postulantes' => route('funcionario.solicitudes-reemplazo.ajax.postulantes', [], false),
                'reglaMinima' => route('funcionario.solicitudes-reemplazo.ajax.regla-minima', [], false),

                'postulantePerfilViewTpl' => route(
                    'funcionario.solicitudes-reemplazo.postulante.perfil.view',
                    ['postulantProfile' => '___ID___'],
                    false
                ),
                'postulantePerfilPdfTpl' => route(
                    'funcionario.solicitudes-reemplazo.postulante.perfil.pdf',
                    ['postulantProfile' => '___ID___'],
                    false
                ),

                'postulanteCvViewTpl' => route(
                    'funcionario.solicitudes-reemplazo.postulante.cv.view',
                    ['postulantProfile' => '___ID___'],
                    false
                ),
            ],
        ]);
    }



    /**
     * Solo editable si pertenece al establecimiento y estado permitido
     */
    private function assertEditableByEstablecimiento(SolicitudReemplazo $solicitud, int $establecimientoId): void
    {
        abort_unless((int)$solicitud->establecimiento_id === (int)$establecimientoId, 403);

        $editable = in_array($solicitud->estado, ['pendiente_uatp', 'rechazada_uatp', 'rechazada_plani'], true);
        abort_unless($editable, 403);
    }

    /**
     * Si tu front sanitiza financiamiento en el name="jornadas[...]", soportamos esa llave.
     */
    private function sanitizeFinKey(string $fin): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/u', '_', $fin);
    }


    public function store(Request $request)
    {
        $establecimiento = $this->establecimientoDelUsuario();

        $validTipos = $this->tiposReemplazoValidos();
        $tiposDeshabilitados = $this->tiposReemplazoDeshabilitados();
        $messages = [
            'postulant_profile_id.required' => 'Debe seleccionar un postulante cuando propone reemplazo.',
            'oficio_pdf.max' => 'El archivo Oficio Solicitud de Reemplazo no puede superar 10 MB.',
            'oficio_pdf.mimes' => 'El archivo Oficio Solicitud de Reemplazo debe estar en formato PDF.',
            'respaldo_pdf.max' => 'El archivo de Respaldo no puede superar 10 MB.',
            'respaldo_pdf.mimes' => 'El archivo de Respaldo debe estar en formato PDF.',
            'horario_titular_pdf.max' => 'El archivo Horario Titular no puede superar 10 MB.',
            'horario_titular_pdf.mimes' => 'El archivo Horario Titular debe estar en formato PDF.',
        ];

        $titularValidacion = null;
        if ($request->filled('reemplazo_personal_id')) {
            $titularValidacion = ReemplazoPersonal::query()
                ->where('id', (int) $request->reemplazo_personal_id)
                ->where('establecimiento_id', $establecimiento->id)
                ->first();
        }
        $titularEsDocente = $this->funcionarioTitularEsDocente($titularValidacion?->estatuto);

        $request->validate([
            'contacto_fono' => ['required', 'string', 'max:30'],

            'reemplazo_personal_id' => ['required', 'integer', 'exists:reemplazos_personal,id'],
            'tipo_reemplazo' => ['required', 'string', 'in:' . implode(',', $validTipos)],
            'tipo_reemplazo_otro' => ['nullable', 'string', 'max:255'],
            'area_desempeno_id' => ['required', 'integer', 'exists:areas_desempeno,id'],

            'fecha_inicio' => ['required', 'date_format:d/m/Y'],
            'fecha_termino' => ['required', 'date_format:d/m/Y'],

            'propone_reemplazo' => ['nullable', 'in:0,1'],
            'continuidad' => ['nullable', 'in:0,1'],

            'postulant_profile_id' => [
                Rule::requiredIf(fn() => $request->boolean('propone_reemplazo')),
                'integer',
                'exists:postulant_profiles,id',
            ],

            'oficio_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'respaldo_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'horario_titular_pdf' => [Rule::requiredIf($titularEsDocente), 'nullable', 'file', 'mimes:pdf', 'max:10240'],

            'observaciones' => ['nullable', 'string', 'max:5000'],
            'correccion_establecimiento_observacion' => ['nullable', 'string', 'max:5000'],
            'jornadas' => ['required', 'array'], // financiamiento => {basica, media}
            'horas_aula_cronologicas_titular' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'horas_aula_pedagogicas_titular' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'horas_aula_cronologicas_reemplazo' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'horas_aula_pedagogicas_reemplazo' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'declaracion_responsabilidad_aceptada' => ['accepted'],
        ], array_merge($messages, [
            'declaracion_responsabilidad_aceptada.accepted' => 'Debe aceptar la declaración de responsabilidad del director del establecimiento.',
        ]));

        if (in_array($request->tipo_reemplazo, $tiposDeshabilitados, true)) {
            return back()->withErrors(['tipo_reemplazo' => 'El tipo de reemplazo seleccionado no se encuentra disponible para nuevas solicitudes.'])->withInput();
        }

        if ($request->tipo_reemplazo === 'Otras' && trim((string)$request->tipo_reemplazo_otro) === '') {
            return back()->withErrors(['tipo_reemplazo_otro' => 'Debes detallar el caso en "Otras".'])->withInput();
        }

        $inicio = Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
        $termino = Carbon::createFromFormat('d/m/Y', $request->fecha_termino)->startOfDay();

        if ($termino->lt($inicio)) {
            return back()->withErrors(['fecha_termino' => 'La fecha término debe ser mayor o igual a la fecha inicio.'])->withInput();
        }

        $propone = $request->input('propone_reemplazo', '0') === '1';
        $selectedProfile = null;

        if ($propone && !$request->filled('postulant_profile_id')) {
            return back()->withErrors(['postulant_profile_id' => 'Debes seleccionar un postulante para proponer reemplazo.'])->withInput();
        }

        if ($propone && !$request->filled('continuidad')) {
            return back()->withErrors(['continuidad' => 'Debes indicar continuidad del reemplazo.'])->withInput();
        }

        if ($propone) {
            $selectedProfile = PostulantProfile::query()->with('user')->find((int) $request->postulant_profile_id);
            if (!$selectedProfile) {
                return back()->withErrors(['postulant_profile_id' => 'Postulante no encontrado.'])->withInput();
            }
            if (app(RestrictedRutService::class)->hasCourtRestrictionPostulantProfile($selectedProfile)) {
                return back()->withErrors(['postulant_profile_id' => 'El postulante seleccionado mantiene una restricción judicial vigente para ejercer y no puede ser propuesto.'])->withInput();
            }
        }

        // Seguridad: validar que el funcionario (reemplazos_personal) pertenezca al establecimiento del usuario
        $titular = ReemplazoPersonal::where('id', $request->reemplazo_personal_id)
            ->where('establecimiento_id', $establecimiento->id)
            ->firstOrFail();
        $titularEsDocente = $this->funcionarioTitularEsDocente($titular->estatuto);

        if ($this->titularTieneBloqueoIndividualActivo($titular)) {
            return back()
                ->withErrors(['reemplazo_personal_id' => 'El docente titular seleccionado se encuentra bloqueado para solicitudes de reemplazo.'])
                ->withInput();
        }

        if ($request->tipo_reemplazo === $this->tipoPermisoSinGoce() && !$this->titularPuedeSolicitarPermisoSinGoce($titular)) {
            return back()
                ->withErrors(['tipo_reemplazo' => 'Permiso sin goce de sueldo sólo está habilitado para titulares docentes autorizados por GDP.'])
                ->withInput();
        }

        // Validar que el área de desempeño coincida con el estamento del titular (derivado de su estatuto)
        $expectedEstamento = $this->estamentoFromEstatuto($titular->estatuto);
        if (!$expectedEstamento) {
            return back()->withErrors(['area_desempeno_id' => 'No se pudo determinar el estamento del funcionario (estatuto).'])->withInput();
        }

        $area = AreaDesempeno::find((int)$request->area_desempeno_id);
        if (!$area || $area->estamento !== $expectedEstamento) {
            return back()->withErrors(['area_desempeno_id' => 'El área de desempeño seleccionada no corresponde al estatuto del funcionario.'])->withInput();
        }

        $resultadoRegla = app(ReemplazoSolicitudReglaMinima::class)->evaluar(
            $establecimiento,
            $titular,
            $selectedProfile,
            $inicio,
            $termino,
            $request->input('continuidad') === '1',
            null,
            $area
        );

        if (!$resultadoRegla['permitido']) {
            return back()
                ->withErrors(['fecha_termino' => $resultadoRegla['mensaje'] ?? 'La solicitud no cumple la regla mínima de reemplazo.'])
                ->withInput();
        }

        // Obtener distribución titular (snapshot) para validar máximos y guardar
        $distTitular = $this->distribucionJornadaTitular($establecimiento->id, $titular);
        // Validación horas propuestas (no superar titular)
        $jornadasIn = $request->input('jornadas', []);
        foreach ($distTitular as $row) {
            $fin = $row['financiamiento'];

            $basicaIn = $this->jornadaIn($jornadasIn, $fin, 'basica', (float)$row['basica']);
            $mediaIn  = $this->jornadaIn($jornadasIn, $fin, 'media',  (float)$row['media']);

            if ($basicaIn < 0 || $mediaIn < 0) {
                return back()->withErrors(['jornadas' => 'Las horas no pueden ser negativas.'])->withInput();
            }

            if ($basicaIn > (float)$row['basica']) {
                return back()->withErrors(['jornadas' => "En {$fin}: HRS BÁSICA no puede exceder {$row['basica']}"])->withInput();
            }

            if ($mediaIn > (float)$row['media']) {
                return back()->withErrors(['jornadas' => "En {$fin}: HRS MEDIA no puede exceder {$row['media']}"])->withInput();
            }
        }

        $titularTotalHoras = $this->sumDistribucionTotal($distTitular);
        $reemplazoTotalHoras = $this->sumJornadasReemplazo($distTitular, $jornadasIn);

        $horasAulaCronologicasTitular = 0.0;
        $horasAulaPedagogicasTitular = 0.0;
        $horasAulaCronologicasReemplazo = 0.0;
        $horasAulaPedagogicasReemplazo = 0.0;

        if ($titularEsDocente) {
            $horasAulaCronologicasTitular = $this->requestDecimal($request, 'horas_aula_cronologicas_titular');
            $horasAulaPedagogicasTitular = $this->requestDecimal($request, 'horas_aula_pedagogicas_titular');
            $horasAulaCronologicasReemplazo = $this->requestDecimal($request, 'horas_aula_cronologicas_reemplazo');
            $horasAulaPedagogicasReemplazo = $this->requestDecimal($request, 'horas_aula_pedagogicas_reemplazo');

            if ($horasAulaCronologicasTitular > $titularTotalHoras) {
                return back()->withErrors(['horas_aula_cronologicas_titular' => 'Las Horas Aula Cronológicas del titular no pueden exceder el total de horas del titular.'])->withInput();
            }

            if ($horasAulaCronologicasReemplazo > $reemplazoTotalHoras) {
                return back()->withErrors(['horas_aula_cronologicas_reemplazo' => 'Las Horas Aula Cronológicas del reemplazo no pueden exceder el total de horas del reemplazo.'])->withInput();
            }
        }

        $user = auth()->user();

        $solicitud = null;

        DB::transaction(function () use (
            $establecimiento,
            $titular,
            $request,
            $inicio,
            $termino,
            $propone,
            $user,
            $distTitular,
            $horasAulaCronologicasTitular,
            $horasAulaPedagogicasTitular,
            $horasAulaCronologicasReemplazo,
            $horasAulaPedagogicasReemplazo,
            $titularEsDocente,
            $resultadoRegla,
            &$solicitud
        ) {
            $anio = now()->year;

            // correlativo por año (con lock)
            $max = SolicitudReemplazo::where('anio', $anio)->lockForUpdate()->max('correlativo');
            $next = (int)$max + 1;

            if ($next > 99999) {
                throw new \RuntimeException('Se alcanzó el máximo de solicitudes para el año.');
            }

            $numero = str_pad((string)$next, 5, '0', STR_PAD_LEFT) . '-' . $anio;

            $solicitud = SolicitudReemplazo::create([
                'establecimiento_id' => $establecimiento->id,
                'reemplazo_personal_id' => $titular->id,
                'postulant_profile_id' => $propone ? (int)$request->postulant_profile_id : null,
                'area_desempeno_id' => (int)$request->area_desempeno_id,

                'anio' => $anio,
                'correlativo' => $next,
                'numero_solicitud' => $numero,

                'contacto_nombre' => $this->nombreCompletoUsuario($user),
                'contacto_fono' => $request->contacto_fono,
                'contacto_email' => ($user->email ?? $request->input('contacto_email', '')),

                'tipo_reemplazo' => $request->tipo_reemplazo,
                'tipo_reemplazo_otro' => $request->tipo_reemplazo === 'Otras' ? $request->tipo_reemplazo_otro : null,

                'fecha_inicio' => $inicio->toDateString(),
                'fecha_termino' => $termino->toDateString(),

                'propone_reemplazo' => $propone,
                'continuidad' => $propone ? ($request->input('continuidad') === '1') : null,
                'es_continuidad' => (bool) ($resultadoRegla['es_continuidad'] ?? false),
                'solicitud_anterior_id' => $resultadoRegla['solicitud_anterior_id'] ?? null,
                'continuidad_validada_at' => !empty($resultadoRegla['es_continuidad']) ? now() : null,
                'regla_minima_aplicada' => $resultadoRegla['regla_minima_aplicada'] ?? null,
                'regla_minima_excepcion' => $resultadoRegla['regla_minima_excepcion'] ?? null,
                'rut_titular_normalizado' => $resultadoRegla['rut_titular_normalizado'] ?? null,
                'rut_reemplazo_normalizado' => $resultadoRegla['rut_reemplazo_normalizado'] ?? null,

                'observaciones' => $request->observaciones,
                'horas_aula_cronologicas_titular' => $horasAulaCronologicasTitular,
                'horas_aula_pedagogicas_titular' => $horasAulaPedagogicasTitular,
                'horas_aula_cronologicas_reemplazo' => $horasAulaCronologicasReemplazo,
                'horas_aula_pedagogicas_reemplazo' => $horasAulaPedagogicasReemplazo,
                'declaracion_responsabilidad_aceptada' => true,
                'estado' => 'pendiente_uatp',
            ]);

            // Archivos
            $dir = "reemplazos/solicitudes/{$solicitud->id}";

            $oficioPath = $request->file('oficio_pdf')->store($dir, 'local');
            $respaldoPath = $request->file('respaldo_pdf')->store($dir, 'local');
            $horarioTitularPath = $titularEsDocente && $request->hasFile('horario_titular_pdf')
                ? $request->file('horario_titular_pdf')->store($dir, 'local')
                : null;

            $solicitud->update([
                'oficio_pdf_path' => $oficioPath,
                'respaldo_pdf_path' => $respaldoPath,
                'horario_titular_pdf_path' => $horarioTitularPath,
            ]);

            // Jornadas: snapshot titular + propuesta
            $jornadasIn = $request->input('jornadas', []);

            foreach ($distTitular as $row) {
                $fin = $row['financiamiento'];

                $basicaIn = $this->jornadaIn($jornadasIn, $fin, 'basica', (float)$row['basica']);
                $mediaIn  = $this->jornadaIn($jornadasIn, $fin, 'media',  (float)$row['media']);
                $totalIn  = $basicaIn + $mediaIn;

                SolicitudReemplazoJornada::create([
                    'solicitud_reemplazo_id' => $solicitud->id,
                    'financiamiento' => $fin,

                    'titular_basica' => (float)$row['basica'],
                    'titular_media'  => (float)$row['media'],
                    'titular_total'  => (float)$row['total'],

                    // ✅ SIEMPRE guardamos la propuesta de horas (por defecto tu UI la iguala al titular)
                    'reemplazo_basica' => $basicaIn,
                    'reemplazo_media'  => $mediaIn,
                    'reemplazo_total'  => $totalIn,
                ]);
            }
        });

        // Email + PDF resumen (requiere motor PDF, ver sección 7)
        NotificationAudit::sendMail($solicitud->contacto_email, new SolicitudReemplazoCreada($solicitud), [
            'event_key' => 'solicitud_reemplazo.created',
            'description' => 'Notificación de solicitud de reemplazo creada',
            'subject' => "Solicitud de reemplazo {$solicitud->numero_solicitud} (Pendiente UATP)",
            'related' => $solicitud,
            'context' => ['numero_solicitud' => $solicitud->numero_solicitud],
        ]);

        return redirect()
            ->route('funcionario.solicitudes-reemplazo.index')
            ->with('status', "Solicitud enviada (#{$solicitud->numero_solicitud}). Quedó pendiente de aprobación UATP.");
    }

    public function edit(SolicitudReemplazo $solicitud)
    {
        $establecimiento = $this->establecimientoDelUsuario();

        
        // Áreas de desempeño bloqueadas por sobredotación (para alert/bloqueo del botón Enviar)
        $areasBloqueadasIds = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('establecimiento_area_desempeno')) {
            $areasBloqueadasIds = \Illuminate\Support\Facades\DB::table('establecimiento_area_desempeno')
                ->where('establecimiento_id', (int) $establecimiento->id)
                ->where('bloqueada', 1)
                ->pluck('area_desempeno_id')
                ->map(fn($v) => (int) $v)
                ->all();
        }
// Seguridad: solo del establecimiento
        abort_unless((int) $solicitud->establecimiento_id === (int) $establecimiento->id, 403);

        // Solo editable en estos estados
        abort_unless(in_array($solicitud->estado, ['pendiente_uatp', 'rechazada_uatp', 'rechazada_plani', 'rechazada'], true), 403);

        $user = auth()->user();

        // ✅ Cargar user del postulante (rut/nombres están en users)
        $solicitud->load([
            'funcionarioTitular',
            'postulante.user',
            'jornadas',
            'areaDesempeno',
        ]);

        $initialFuncionario = null;
        if ($solicitud->funcionarioTitular) {
            $initialFuncionario = [
                'id' => $solicitud->reemplazo_personal_id,
                'text' => trim(($solicitud->funcionarioTitular->rut ?? '') . ' - ' . ($solicitud->funcionarioTitular->nombre ?? '')),
            ];
        }

        $initialPostulante = null;
        $pu = $solicitud->postulante?->user; // ✅ aquí está rut/nombres/apellidos
        if ($solicitud->postulante && $pu) {
            $nombre = trim(
                ($pu->nombres ?? '') . ' ' .
                    ($pu->apellido_paterno ?? '') . ' ' .
                    ($pu->apellido_materno ?? '')
            );

            // Si tu rut en users está guardado SIN guion, se mostrará igual (tal como pediste)
            $rut = trim((string) ($pu->rut ?? ''));

            $initialPostulante = [
                'id' => $solicitud->postulant_profile_id,
                'text' => trim($rut . ' - ' . $nombre),
                // ✅ Para deshabilitar "Ver CV" cuando no exista el documento
                'has_cv' => $this->userHasCvUploaded((int) $pu->id),
            ];
        }

        // Jornadas guardadas (financiamiento -> basica/media)
        $jmap = $solicitud->jornadas
            ->mapWithKeys(fn($j) => [
                (string) $j->financiamiento => [
                    'basica' => (float) $j->reemplazo_basica,
                    'media'  => (float) $j->reemplazo_media,
                ],
            ])->all();

        return view('funcionario.solicitudes-reemplazo.create', [
            'establecimiento' => $establecimiento,

            // (si quieres que la cabecera refleje la solicitud original)
            'fechaSolicitud' => cl_date($solicitud->created_at ?? now(), 'd/m/Y'),
            'horaSolicitud'  => cl_time($solicitud->created_at ?? now(), 'H:i'),

            'contactoNombre' => $this->nombreCompletoUsuario($user),
            'contactoEmail'  => $user->email ?? '—',

            'solicitud' => $solicitud,
            'isEdit' => true, // ✅ CLAVE para que tu JS haga el boot
            'initial' => [
                'funcionario' => $initialFuncionario,
                'area_desempeno_id' => $solicitud->area_desempeno_id,
                'postulante' => $initialPostulante,
                'propone_reemplazo' => (bool) $solicitud->propone_reemplazo,
                'continuidad' => is_null($solicitud->continuidad) ? null : (bool) $solicitud->continuidad,
                'jornadas' => $jmap,
                'horas_aula_cronologicas_titular' => (float) ($solicitud->horas_aula_cronologicas_titular ?? 0),
                'horas_aula_pedagogicas_titular' => (float) ($solicitud->horas_aula_pedagogicas_titular ?? 0),
                'horas_aula_cronologicas_reemplazo' => (float) ($solicitud->horas_aula_cronologicas_reemplazo ?? 0),
                'horas_aula_pedagogicas_reemplazo' => (float) ($solicitud->horas_aula_pedagogicas_reemplazo ?? 0),
                'declaracion_responsabilidad_aceptada' => (bool) ($solicitud->declaracion_responsabilidad_aceptada ?? false),
            ],

                        // Áreas bloqueadas por sobredotación (ids)
            'areasBloqueadasIds' => $areasBloqueadasIds,

'urls' => [
                'funcionarios' => route('funcionario.solicitudes-reemplazo.ajax.funcionarios'),
                'funcionarioDetalleTpl' => route('funcionario.solicitudes-reemplazo.ajax.funcionario.detalle', ['reemplazoPersonal' => '___ID___']),
                'areasDesempeno' => route('funcionario.solicitudes-reemplazo.ajax.areas-desempeno'),
                'postulantes' => route('funcionario.solicitudes-reemplazo.ajax.postulantes'),
                'reglaMinima' => route('funcionario.solicitudes-reemplazo.ajax.regla-minima'),
                'postulantePerfilViewTpl' => route('funcionario.solicitudes-reemplazo.postulante.perfil.view', ['postulantProfile' => '___ID___'], false),
                'postulantePerfilPdfTpl' => route('funcionario.solicitudes-reemplazo.postulante.perfil.pdf', ['postulantProfile' => '___ID___'], false),
                'postulanteCvViewTpl' => route('funcionario.solicitudes-reemplazo.postulante.cv.view', ['postulantProfile' => '___ID___'], false),
            ],
        ]);
    }

    /**
     * CV (Currículum Vitae) subido por el usuario.
     *
     * Nota: se considera "subido" cuando existe UserDocument y tiene path no vacío.
     * No validamos existencia física del archivo aquí para no penalizar performance del flujo.
     */
    private function userHasCvUploaded(int $userId): bool
    {
        $cvTypeId = (int) DocumentType::query()->where('slug', 'curriculum')->value('id');
        if ($cvTypeId <= 0) return false;

        return UserDocument::query()
            ->where('user_id', $userId)
            ->where('document_type_id', $cvTypeId)
            ->whereNotNull('path')
            ->where('path', '<>', '')
            ->exists();
    }


    public function update(Request $request, SolicitudReemplazo $solicitud)
    {
        $establecimiento = $this->establecimientoDelUsuario();

        $this->assertEditableByEstablecimiento($solicitud, $establecimiento->id);

        $validTipos = $this->tiposReemplazoValidos();
        $tiposDeshabilitados = $this->tiposReemplazoDeshabilitados();

        $titularValidacion = null;
        if ($request->filled('reemplazo_personal_id')) {
            $titularValidacion = ReemplazoPersonal::query()
                ->where('id', (int) $request->reemplazo_personal_id)
                ->where('establecimiento_id', $establecimiento->id)
                ->first();
        }
        $titularEsDocente = $this->funcionarioTitularEsDocente($titularValidacion?->estatuto);
        $horarioTitularRequiredOnUpdate = $titularEsDocente && blank($solicitud->horario_titular_pdf_path);

        $request->validate([
            'contacto_fono' => ['required', 'string', 'max:30'],

            'reemplazo_personal_id' => ['required', 'integer', 'exists:reemplazos_personal,id'],
            'area_desempeno_id' => ['required', 'integer', 'exists:areas_desempeno,id'],

            'tipo_reemplazo' => ['required', 'string', 'in:' . implode(',', $validTipos)],
            'tipo_reemplazo_otro' => ['nullable', 'string', 'max:255'],

            'fecha_inicio' => ['required', 'date_format:d/m/Y'],
            'fecha_termino' => ['required', 'date_format:d/m/Y'],

            'propone_reemplazo' => ['nullable', 'in:0,1'],
            'continuidad' => ['nullable', 'in:0,1'],
            'postulant_profile_id' => ['nullable', 'integer', 'exists:postulant_profiles,id'],

            // En edición NO obligamos a re-subir
            'oficio_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'respaldo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'horario_titular_pdf' => [Rule::requiredIf($horarioTitularRequiredOnUpdate), 'nullable', 'file', 'mimes:pdf', 'max:10240'],

            'observaciones' => ['nullable', 'string', 'max:5000'],
            'correccion_establecimiento_observacion' => ['nullable', 'string', 'max:5000'],
            'jornadas' => ['required', 'array'],
            'horas_aula_cronologicas_titular' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'horas_aula_pedagogicas_titular' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'horas_aula_cronologicas_reemplazo' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'horas_aula_pedagogicas_reemplazo' => [Rule::requiredIf($titularEsDocente), 'nullable', 'numeric', 'min:0'],
            'declaracion_responsabilidad_aceptada' => ['accepted'],
            'action' => ['nullable', 'in:guardar,reenviar'],
        ], [
            'declaracion_responsabilidad_aceptada.accepted' => 'Debe aceptar la declaración de responsabilidad del director del establecimiento.',
            'oficio_pdf.max' => 'El archivo Oficio Solicitud de Reemplazo no puede superar 10 MB.',
            'oficio_pdf.mimes' => 'El archivo Oficio Solicitud de Reemplazo debe estar en formato PDF.',
            'respaldo_pdf.max' => 'El archivo de Respaldo no puede superar 10 MB.',
            'respaldo_pdf.mimes' => 'El archivo de Respaldo debe estar en formato PDF.',
            'horario_titular_pdf.max' => 'El archivo Horario Titular no puede superar 10 MB.',
            'horario_titular_pdf.mimes' => 'El archivo Horario Titular debe estar en formato PDF.',
        ]);

        if (in_array($request->tipo_reemplazo, $tiposDeshabilitados, true) && $request->tipo_reemplazo !== $solicitud->tipo_reemplazo) {
            return back()->withErrors(['tipo_reemplazo' => 'El tipo de reemplazo seleccionado no se encuentra disponible para nuevas solicitudes.'])->withInput();
        }

        if ($request->tipo_reemplazo === 'Otras' && trim((string)$request->tipo_reemplazo_otro) === '') {
            return back()->withErrors(['tipo_reemplazo_otro' => 'Debes detallar el caso en "Otras".'])->withInput();
        }

        $inicio = Carbon::createFromFormat('d/m/Y', $request->fecha_inicio)->startOfDay();
        $termino = Carbon::createFromFormat('d/m/Y', $request->fecha_termino)->startOfDay();

        if ($termino->lt($inicio)) {
            return back()->withErrors(['fecha_termino' => 'La fecha término debe ser mayor o igual a la fecha inicio.'])->withInput();
        }

        $propone = $request->input('propone_reemplazo', '0') === '1';
        $selectedProfile = null;

        if ($propone && !$request->filled('postulant_profile_id')) {
            return back()->withErrors(['postulant_profile_id' => 'Debes seleccionar un postulante para proponer reemplazo.'])->withInput();
        }
        if ($propone && !$request->filled('continuidad')) {
            return back()->withErrors(['continuidad' => 'Debes indicar continuidad del reemplazo.'])->withInput();
        }

        if ($propone) {
            $selectedProfile = PostulantProfile::query()->with('user')->find((int) $request->postulant_profile_id);
            if (!$selectedProfile) {
                return back()->withErrors(['postulant_profile_id' => 'Postulante no encontrado.'])->withInput();
            }
            if (app(RestrictedRutService::class)->hasCourtRestrictionPostulantProfile($selectedProfile)) {
                return back()->withErrors(['postulant_profile_id' => 'El postulante seleccionado mantiene una restricción judicial vigente para ejercer y no puede ser propuesto.'])->withInput();
            }
        }

        // Seguridad: titular pertenece al establecimiento
        $titular = ReemplazoPersonal::where('id', $request->reemplazo_personal_id)
            ->where('establecimiento_id', $establecimiento->id)
            ->firstOrFail();
        $titularEsDocente = $this->funcionarioTitularEsDocente($titular->estatuto);

        if ($this->titularTieneBloqueoIndividualActivo($titular)) {
            return back()
                ->withErrors(['reemplazo_personal_id' => 'El docente titular seleccionado se encuentra bloqueado para solicitudes de reemplazo.'])
                ->withInput();
        }

        if ($request->tipo_reemplazo === $this->tipoPermisoSinGoce()
            && ((int) $titular->id !== (int) $solicitud->reemplazo_personal_id || $request->tipo_reemplazo !== $solicitud->tipo_reemplazo)
            && !$this->titularPuedeSolicitarPermisoSinGoce($titular)) {
            return back()
                ->withErrors(['tipo_reemplazo' => 'Permiso sin goce de sueldo sólo está habilitado para titulares docentes autorizados por GDP.'])
                ->withInput();
        }

        $expectedEstamento = $this->estamentoFromEstatuto($titular->estatuto);
        if (!$expectedEstamento) {
            return back()->withErrors(['area_desempeno_id' => 'No se pudo determinar el estamento del funcionario (estatuto).'])->withInput();
        }

        $area = AreaDesempeno::find((int)$request->area_desempeno_id);
        if (!$area || $area->estamento !== $expectedEstamento) {
            return back()->withErrors(['area_desempeno_id' => 'El área de desempeño seleccionada no corresponde al estatuto del funcionario.'])->withInput();
        }

        $resultadoRegla = app(ReemplazoSolicitudReglaMinima::class)->evaluar(
            $establecimiento,
            $titular,
            $selectedProfile,
            $inicio,
            $termino,
            $request->input('continuidad') === '1',
            (int) $solicitud->id,
            $area
        );

        if (!$resultadoRegla['permitido']) {
            return back()
                ->withErrors(['fecha_termino' => $resultadoRegla['mensaje'] ?? 'La solicitud no cumple la regla mínima de reemplazo.'])
                ->withInput();
        }

        // Snapshot titular para validar máximos
        $distTitular = $this->distribucionJornadaTitular($establecimiento->id, $titular);
        $jornadasIn = $request->input('jornadas', []);

        foreach ($distTitular as $row) {
            $fin = $row['financiamiento'];

            // soporta clave “limpia” (por si el front la sanitiza)
            $k2 = $this->sanitizeFinKey($fin);

            $basicaIn = (float)($jornadasIn[$fin]['basica'] ?? $jornadasIn[$k2]['basica'] ?? 0);
            $mediaIn  = (float)($jornadasIn[$fin]['media']  ?? $jornadasIn[$k2]['media']  ?? 0);

            if ($basicaIn < 0 || $mediaIn < 0) {
                return back()->withErrors(['jornadas' => 'Las horas no pueden ser negativas.'])->withInput();
            }
            if ($basicaIn > (float)$row['basica']) {
                return back()->withErrors(['jornadas' => "En {$fin}: HRS BÁSICA no puede exceder {$row['basica']}"])->withInput();
            }
            if ($mediaIn > (float)$row['media']) {
                return back()->withErrors(['jornadas' => "En {$fin}: HRS MEDIA no puede exceder {$row['media']}"])->withInput();
            }
        }

        $titularTotalHoras = $this->sumDistribucionTotal($distTitular);
        $reemplazoTotalHoras = $this->sumJornadasReemplazo($distTitular, $jornadasIn);

        $horasAulaCronologicasTitular = 0.0;
        $horasAulaPedagogicasTitular = 0.0;
        $horasAulaCronologicasReemplazo = 0.0;
        $horasAulaPedagogicasReemplazo = 0.0;

        if ($titularEsDocente) {
            $horasAulaCronologicasTitular = $this->requestDecimal($request, 'horas_aula_cronologicas_titular');
            $horasAulaPedagogicasTitular = $this->requestDecimal($request, 'horas_aula_pedagogicas_titular');
            $horasAulaCronologicasReemplazo = $this->requestDecimal($request, 'horas_aula_cronologicas_reemplazo');
            $horasAulaPedagogicasReemplazo = $this->requestDecimal($request, 'horas_aula_pedagogicas_reemplazo');

            if ($horasAulaCronologicasTitular > $titularTotalHoras) {
                return back()->withErrors(['horas_aula_cronologicas_titular' => 'Las Horas Aula Cronológicas del titular no pueden exceder el total de horas del titular.'])->withInput();
            }

            if ($horasAulaCronologicasReemplazo > $reemplazoTotalHoras) {
                return back()->withErrors(['horas_aula_cronologicas_reemplazo' => 'Las Horas Aula Cronológicas del reemplazo no pueden exceder el total de horas del reemplazo.'])->withInput();
            }
        }

        $action = $request->input('action', 'guardar');
        $estadoAnterior = (string) $solicitud->estado;
        $retornarAEtapa = (string) ($solicitud->retornar_a_etapa ?: match ($estadoAnterior) {
            'rechazada_plani' => 'plani',
            'rechazada_uatp' => 'uatp',
            default => 'uatp',
        });
        $estadoReenvio = $retornarAEtapa === 'plani' ? 'pendiente_validacion' : 'pendiente_uatp';
        $correccionObservacion = trim((string) ($request->input('correccion_establecimiento_observacion') ?: $request->input('observaciones', '')));

        DB::transaction(function () use ($request, $solicitud, $titular, $inicio, $termino, $propone, $distTitular, $jornadasIn, $action, $estadoAnterior, $retornarAEtapa, $estadoReenvio, $correccionObservacion, $horasAulaCronologicasTitular, $horasAulaPedagogicasTitular, $horasAulaCronologicasReemplazo, $horasAulaPedagogicasReemplazo, $titularEsDocente, $resultadoRegla) {

            // Archivos (si vienen, reemplazan)
            $dir = "reemplazos/solicitudes/{$solicitud->id}";

            $updatePaths = [];
            $archivosReemplazados = [];

            if ($request->hasFile('oficio_pdf')) {
                if ($solicitud->oficio_pdf_path) Storage::disk('local')->delete($solicitud->oficio_pdf_path);
                $updatePaths['oficio_pdf_path'] = $request->file('oficio_pdf')->store($dir, 'local');
                $archivosReemplazados[] = 'Oficio Solicitud de Reemplazo';
            }

            if ($request->hasFile('respaldo_pdf')) {
                if ($solicitud->respaldo_pdf_path) Storage::disk('local')->delete($solicitud->respaldo_pdf_path);
                $updatePaths['respaldo_pdf_path'] = $request->file('respaldo_pdf')->store($dir, 'local');
                $archivosReemplazados[] = 'Respaldo';
            }

            if ($titularEsDocente && $request->hasFile('horario_titular_pdf')) {
                if ($solicitud->horario_titular_pdf_path) Storage::disk('local')->delete($solicitud->horario_titular_pdf_path);
                $updatePaths['horario_titular_pdf_path'] = $request->file('horario_titular_pdf')->store($dir, 'local');
                $archivosReemplazados[] = 'Horario Titular';
            }

            $solicitud->update(array_merge([
                'reemplazo_personal_id' => $titular->id,
                'area_desempeno_id' => (int)$request->area_desempeno_id,

                'postulant_profile_id' => $propone ? (int)$request->postulant_profile_id : null,
                'tipo_reemplazo' => $request->tipo_reemplazo,
                'tipo_reemplazo_otro' => $request->tipo_reemplazo === 'Otras' ? $request->tipo_reemplazo_otro : null,

                'fecha_inicio' => $inicio->toDateString(),
                'fecha_termino' => $termino->toDateString(),

                'propone_reemplazo' => $propone,
                'continuidad' => $propone ? ($request->input('continuidad') === '1') : null,
                'es_continuidad' => (bool) ($resultadoRegla['es_continuidad'] ?? false),
                'solicitud_anterior_id' => $resultadoRegla['solicitud_anterior_id'] ?? null,
                'continuidad_validada_at' => !empty($resultadoRegla['es_continuidad']) ? now() : null,
                'regla_minima_aplicada' => $resultadoRegla['regla_minima_aplicada'] ?? null,
                'regla_minima_excepcion' => $resultadoRegla['regla_minima_excepcion'] ?? null,
                'rut_titular_normalizado' => $resultadoRegla['rut_titular_normalizado'] ?? null,
                'rut_reemplazo_normalizado' => $resultadoRegla['rut_reemplazo_normalizado'] ?? null,

                'contacto_fono' => $request->contacto_fono,
                'observaciones' => $request->observaciones,
                'horas_aula_cronologicas_titular' => $horasAulaCronologicasTitular,
                'horas_aula_pedagogicas_titular' => $horasAulaPedagogicasTitular,
                'horas_aula_cronologicas_reemplazo' => $horasAulaCronologicasReemplazo,
                'horas_aula_pedagogicas_reemplazo' => $horasAulaPedagogicasReemplazo,
                'declaracion_responsabilidad_aceptada' => true,
            ], $updatePaths));

            // Jornadas: reemplazamos snapshot completo
            $solicitud->jornadas()->delete();

            foreach ($distTitular as $row) {
                $fin = $row['financiamiento'];
                $k2 = $this->sanitizeFinKey($fin);

                $basicaIn = (float)($jornadasIn[$fin]['basica'] ?? $jornadasIn[$k2]['basica'] ?? 0);
                $mediaIn  = (float)($jornadasIn[$fin]['media']  ?? $jornadasIn[$k2]['media']  ?? 0);
                $totalIn  = $basicaIn + $mediaIn;

                SolicitudReemplazoJornada::create([
                    'solicitud_reemplazo_id' => $solicitud->id,
                    'financiamiento' => $fin,

                    'titular_basica' => (float)$row['basica'],
                    'titular_media' => (float)$row['media'],
                    'titular_total' => (float)$row['total'],

                    // ✅ ahora SIEMPRE se guardan horas de reemplazo (haya o no propuesta)
                    'reemplazo_basica' => $basicaIn,
                    'reemplazo_media' => $mediaIn,
                    'reemplazo_total' => $totalIn,
                ]);
            }

            // Reenviar corrección: vuelve a la etapa que observó/rechazó sin borrar autorizaciones previas.
            if ($action === 'reenviar') {
                $flujoUpdate = [
                    'estado' => $estadoReenvio,
                    'devuelta_desde' => null,
                    'retornar_a_etapa' => null,
                    'corregida_establecimiento_at' => now(),
                    'corregida_establecimiento_user_id' => auth()->id(),
                    'correccion_establecimiento_observacion' => $correccionObservacion !== '' ? $correccionObservacion : null,
                    'derivada_a_user_id' => null,
                    'derivada_por_user_id' => null,
                    'derivada_at' => null,
                ];

                if ($retornarAEtapa === 'uatp') {
                    $flujoUpdate['justificacion_tecnica_uatp'] = null;
                    $flujoUpdate['uatp_decision_user_id'] = null;
                    $flujoUpdate['uatp_decision_at'] = null;
                    $flujoUpdate['plani_motivo_rechazo'] = null;
                    $flujoUpdate['plani_decision_user_id'] = null;
                    $flujoUpdate['plani_decision_at'] = null;
                } elseif ($retornarAEtapa === 'plani') {
                    // UATP ya autorizó previamente: se conserva justificación y usuario/fecha de autorización UATP.
                    $flujoUpdate['plani_decision_user_id'] = null;
                    $flujoUpdate['plani_decision_at'] = null;
                }

                $solicitud->update($flujoUpdate);

                $observacionHistorial = $correccionObservacion !== '' ? $correccionObservacion : null;
                if (!empty($archivosReemplazados)) {
                    $detalleArchivos = 'Archivos reemplazados: ' . implode(', ', $archivosReemplazados) . '.';
                    $observacionHistorial = trim(($observacionHistorial ? $observacionHistorial . "\n" : '') . $detalleArchivos);
                }

                SolicitudReemplazoObservacion::create([
                    'solicitud_reemplazo_id' => $solicitud->id,
                    'etapa' => 'establecimiento',
                    'accion' => 'correccion',
                    'estado_origen' => $estadoAnterior,
                    'estado_destino' => $estadoReenvio,
                    'motivo' => $correccionObservacion !== '' ? $correccionObservacion : 'Corrección reenviada por establecimiento.',
                    'observacion' => $observacionHistorial,
                    'user_id' => auth()->id(),
                ]);
            }
        });

        return redirect()
            ->route('funcionario.solicitudes-reemplazo.index')
            ->with(
                'status',
                $action === 'reenviar'
                    ? ($retornarAEtapa === 'plani'
                        ? "Solicitud {$solicitud->numero_solicitud} reenviada a Planificación."
                        : "Solicitud {$solicitud->numero_solicitud} reenviada a UATP.")
                    : "Solicitud {$solicitud->numero_solicitud} actualizada."
            );
    }

    public function postulantePerfilView(PostulantProfile $postulantProfile, Request $request)
    {
        $this->assertPuedeVerPerfilPostulante($postulantProfile, $request);

        return $this->renderPerfilPostulantePdf($postulantProfile, inline: true);
    }

    public function postulantePerfilPdf(PostulantProfile $postulantProfile, Request $request)
    {
        $this->assertPuedeVerPerfilPostulante($postulantProfile, $request);

        return $this->renderPerfilPostulantePdf($postulantProfile, inline: false);
    }

    /**
     * Ver CV (Currículum Vitae) del postulante en línea.
     *
     * Nota: no se elimina ninguna ruta existente (p.ej. perfil.pdf).
     */
    public function postulanteCvView(PostulantProfile $postulantProfile, Request $request)
    {
        $this->assertPuedeVerPerfilPostulante($postulantProfile, $request);

        $cvType = DocumentType::query()->where('slug', 'curriculum')->first();
        abort_unless($cvType, 404);

        $doc = UserDocument::query()
            ->where('user_id', (int) $postulantProfile->user_id)
            ->where('document_type_id', (int) $cvType->id)
            ->first();

        $path = $doc?->path;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Currículum Vitae no disponible.');

        // Mostrar inline (misma experiencia que ver PDFs en el navegador)
        $downloadName = $doc?->original_name ?: basename($path);

        return Storage::disk('public')->response(
            $path,
            $downloadName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . str_replace('"', '', $downloadName) . '"',
            ]
        );
    }

    /**
     * Seguridad mínima: si es funcionario_estab exigimos que el postulante coincida con el área seleccionada.
     * (El frontend enviará ?area_desempeno_id=...)
     */
    private function assertPuedeVerPerfilPostulante(PostulantProfile $profile, Request $request): void
    {
        $auth = auth()->user();

        if ($auth?->hasRole('funcionario_estab')) {
            // asegura que tiene establecimiento asociado (no rompe tu lógica)
            $this->establecimientoDelUsuario();

            $areaId = (int) $request->query('area_desempeno_id', 0);
            if ($areaId <= 0 || (int) $profile->area_desempeno_id !== $areaId) {
                abort(403);
            }
        }
    }

    private function renderPerfilPostulantePdf(PostulantProfile $profile, bool $inline)
    {
        $profile->load(['comuna']);
        $user = $profile->user()->firstOrFail();
        $user->load(['communes']);

        // --- Región legible ---
        $regiones   = config('chile.regiones', []);
        $regionName = $profile->region_code ? ($regiones[$profile->region_code] ?? $profile->region_code) : '';

        // --- RUT formateado (sin puntos, con guion) ---
        $rutRaw = (string)($user->rut ?? '');
        $rutSan = strtoupper(preg_replace('/[^0-9Kk]/', '', $rutRaw));
        if ($rutSan !== '') {
            $dv     = substr($rutSan, -1);
            $cuerpo = substr($rutSan, 0, -1);
            $rutFmt = $cuerpo . '-' . $dv; // ej: 12345678-K
        } else {
            $rutFmt = 'ID' . $user->id;
        }

        // --- Nacionalidad + bandera (data URI) ---
        $nationalities = collect(config('nacionalidades', []));
        $val   = (string) ($profile->nacionalidad ?? '');
        $match = $nationalities->first(function ($n) use ($val) {
            return strcasecmp($n['value'] ?? '', $val) === 0
                || strcasecmp($n['iso'] ?? '', $val) === 0
                || strcasecmp($n['abbr'] ?? '', $val) === 0
                || strcasecmp($n['name'] ?? '', $val) === 0;
        });
        $nacName = $match['name'] ?? ($val ?: null);
        $iso2    = strtolower($match['iso'] ?? $match['value'] ?? '');

        $flagDataUrl = null;
        if ($iso2) {
            $candidates = [
                public_path("flags-svg/{$iso2}.svg"),
                public_path("flags/{$iso2}.png"),
            ];
            foreach ($candidates as $p) {
                if (is_file($p)) {
                    $mime = str_ends_with(strtolower($p), '.svg') ? 'image/svg+xml' : 'image/png';
                    $flagDataUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
                    break;
                }
            }
        }

        // --- Foto miniatura absoluta (si existe) ---
        $fotoThumbAbs = null;
        if (!empty($profile->foto_thumb_path) && Storage::disk('public')->exists($profile->foto_thumb_path)) {
            $fotoThumbAbs = Storage::disk('public')->path($profile->foto_thumb_path);
        }

        // --- Marca/colores ---
        $brand = PdfBranding::profileBrand();

        $data = [
            'user'         => $user,
            'profile'      => $profile,
            'rutFmt'       => $rutFmt,
            'regionName'   => $regionName,
            'communes'     => $user->communes,
            'brand'        => $brand,
            'fotoThumbAbs' => $fotoThumbAbs,
            'generatedAt'  => now(),
            'nacName'      => $nacName,
            'flagDataUrl'  => $flagDataUrl,
        ];

        $pdf = Pdf::loadView('pdf.profile', $data)->setPaper('letter', 'portrait');

        $fileRut  = preg_replace('/[^0-9Kk-]/', '', $rutFmt);
        $filename = "PERFIL_{$fileRut}.pdf";

        return $inline
            ? $pdf->stream($filename, ['Attachment' => false])
            : $pdf->download($filename);
    }

    // ---------- AJAX ----------

    public function ajaxFuncionarios(Request $request)
    {
        $establecimiento = $this->establecimientoDelUsuario();
        $term = trim((string) $request->query('term', ''));

        // Normaliza término para buscar rut aunque venga con puntos/guion
        $termRut = strtoupper(preg_replace('/[^0-9Kk]/', '', $term));

        // Subquery: 1 id (el más reciente) por rut
        $idsUltimoPorRut = ReemplazoPersonal::query()
            ->selectRaw('MAX(id) as id')
            ->where('establecimiento_id', $establecimiento->id)
            ->whereNotNull('rut')
            ->where('rut', '<>', '')
            ->when($term !== '', function ($q) use ($term, $termRut) {
                $q->where(function ($qq) use ($term, $termRut) {
                    $qq->where('nombre', 'like', "%{$term}%")
                        ->orWhere('rut', 'like', "%{$term}%");

                    if ($termRut !== '') {
                        $qq->orWhereRaw(
                            "REPLACE(REPLACE(UPPER(rut),'.',''),'-','') LIKE ?",
                            ["%{$termRut}%"]
                        );
                    }
                });
            })
            ->groupBy('rut');

        $items = ReemplazoPersonal::query()
            ->with('bloqueoActivo')
            ->whereIn('id', $idsUltimoPorRut)     // <- sigue siendo 1 por rut
            ->orderBy('nombre')
            ->limit(30)
            ->get(['id', 'rut', 'nombre', 'estatuto']);       // <- ahora sí traemos id

        return response()->json([
            'results' => $items->map(function ($x) {
                $bloqueado = $this->titularTieneBloqueoIndividualActivo($x);

                return [
                    'id'   => $x->id,                 // ✅ vuelve a ser numérico
                    'text' => "{$x->rut} - {$x->nombre}" . ($bloqueado ? ' (Bloqueado)' : ''),
                    'label' => "{$x->rut} - {$x->nombre}",
                    'disabled' => $bloqueado,
                    'bloqueado' => $bloqueado,
                    'bloqueo_motivo' => $x->bloqueoActivo?->motivo,
                ];
            })->values(),
        ]);
    }


    public function ajaxFuncionarioDetalle(\App\Models\ReemplazoPersonal $reemplazoPersonal)
    {
        $establecimiento = $this->establecimientoDelUsuario();

        abort_unless((int)$reemplazoPersonal->establecimiento_id === (int)$establecimiento->id, 403);

        $reemplazoPersonal->loadMissing('bloqueoActivo');
        $dist = $this->distribucionJornadaTitular($establecimiento->id, $reemplazoPersonal);
        $bloqueado = $this->titularTieneBloqueoIndividualActivo($reemplazoPersonal);

        $nombreFull = (string)$reemplazoPersonal->nombre;
        [$nombres, $apP, $apM] = $this->guessNombreApellidos($nombreFull);

        return response()->json([
            'funcionario' => [
                'id' => $reemplazoPersonal->id,
                'rut' => $reemplazoPersonal->rut,
                'nombre_full' => $nombreFull,
                'nombres' => $nombres,
                'apellido_paterno' => $apP,
                'apellido_materno' => $apM,
                'estatuto' => $reemplazoPersonal->estatuto,
                'estamento' => $this->estamentoFromEstatuto($reemplazoPersonal->estatuto), // ✅ clave
                'escalafon' => $reemplazoPersonal->escalafon,
                'bloqueado' => $bloqueado,
                'bloqueo_motivo' => $reemplazoPersonal->bloqueoActivo?->motivo,
                'permiso_sin_goce_autorizado' => $this->titularPuedeSolicitarPermisoSinGoce($reemplazoPersonal),
            ],
            'distribucion' => $dist,
            'totales' => [
                'basica' => collect($dist)->sum('basica'),
                'media'  => collect($dist)->sum('media'),
                'total'  => collect($dist)->sum('total'),
            ],
        ]);
    }


    public function ajaxReglaMinima(Request $request)
    {
        $establecimiento = $this->establecimientoDelUsuario();

        $request->validate([
            'reemplazo_personal_id' => ['nullable', 'integer'],
            'postulant_profile_id' => ['nullable', 'integer'],
            'area_desempeno_id' => ['nullable', 'integer'],
            'fecha_inicio' => ['nullable', 'date_format:d/m/Y'],
            'fecha_termino' => ['nullable', 'date_format:d/m/Y'],
            'continuidad' => ['nullable', 'in:0,1'],
            'solicitud_id' => ['nullable', 'integer'],
        ]);

        if (!$request->filled('reemplazo_personal_id') || !$request->filled('fecha_inicio') || !$request->filled('fecha_termino')) {
            return response()->json([
                'evaluable' => false,
                'permitido' => true,
                'mensaje' => null,
            ]);
        }

        $titular = ReemplazoPersonal::query()
            ->where('id', (int) $request->query('reemplazo_personal_id'))
            ->where('establecimiento_id', (int) $establecimiento->id)
            ->first();

        if (!$titular) {
            return response()->json([
                'evaluable' => true,
                'permitido' => false,
                'mensaje' => 'Funcionario titular no encontrado para el establecimiento.',
            ], 422);
        }

        $postulante = null;
        if ($request->filled('postulant_profile_id')) {
            $postulante = PostulantProfile::query()
                ->with('user')
                ->find((int) $request->query('postulant_profile_id'));
        }

        $area = null;
        if ($request->filled('area_desempeno_id')) {
            $area = AreaDesempeno::find((int) $request->query('area_desempeno_id'));
        }

        $inicio = Carbon::createFromFormat('d/m/Y', (string) $request->query('fecha_inicio'))->startOfDay();
        $termino = Carbon::createFromFormat('d/m/Y', (string) $request->query('fecha_termino'))->startOfDay();

        $resultado = app(ReemplazoSolicitudReglaMinima::class)->evaluar(
            $establecimiento,
            $titular,
            $postulante,
            $inicio,
            $termino,
            $request->query('continuidad') === '1',
            $request->filled('solicitud_id') ? (int) $request->query('solicitud_id') : null,
            $area
        );

        return response()->json(array_merge(['evaluable' => true], $resultado));
    }

        public function ajaxPostulantes(Request $request)
    {
        $term      = trim((string) $request->query('term', ''));
        $areaId    = (int) $request->query('area_desempeno_id', 0);
        $estamento = trim((string) $request->query('estamento', ''));

        // -----------------------------
        // Área del titular (seleccionada) y regla especial EP
        // -----------------------------
        $selectedArea = null;
        $isEpSelected = false;
        $epAreaIds = [];

        if ($areaId > 0) {
            $selectedArea = AreaDesempeno::query()->activos()->find($areaId);
            if ($selectedArea) {
                $isEpSelected = ($selectedArea->slug === 'educadora_de_parvulos');
            }
        }

        // ✅ Caso especial: si el área del titular es Educadora de Párvulos,
        // mostramos SOLO postulantes EP (docentes y asistentes) y NO filtramos por estamento.
        if ($isEpSelected) {
            $epAreaIds = AreaDesempeno::query()
                ->activos()
                ->where('slug', 'educadora_de_parvulos')
                ->pluck('id')
                ->map(fn($x) => (int) $x)
                ->all();
        } else {
            // ✅ Estamento requerido (docente|asistente). Si no viene, intentamos derivirlo desde el área seleccionada.
            if ($estamento === '' && $selectedArea) {
                $estamento = (string) $selectedArea->estamento;
            }

            if (!in_array($estamento, ['docente', 'asistente'], true)) {
                return response()->json(['results' => []]);
            }
        }

        // tokens: "juan perez" => obliga a coincidir ambos
        $tokens = array_values(array_filter(preg_split('/\s+/', $term)));

        $q = User::query()
            ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'])
            ->with([
                // ⚠️ Importante: para calcular requerimientos de documentos (y %)
                // usamos DocumentRules/DocumentType::isRequiredForUser(), que depende
                // de varios campos del perfil (nivel_estudios, cargo, área, etc.).
                // Si no se cargan aquí, Eloquent deja esos atributos como null y las
                // reglas se evalúan mal (ej: asistente con Enseñanza Media termina
                // exigiendo "Título" y no "Licencia Media").
                // Nota: en producción no existe la columna `area_desempeno_nombre` en postulant_profiles.
                // El nombre/slug del área se obtiene vía la relación `postulantProfile.areaDesempeno`.
                'postulantProfile:id,user_id,estamento,area_desempeno_id,cargos_funcion,genero,nivel_estudios,mencion',
                'postulantProfile.areaDesempeno:id,nombre,slug,estamento',
                'documents:id,user_id,document_type_id,path,status',
            ])
            ->whereHas('postulantProfile', function ($qq) use ($estamento, $isEpSelected, $epAreaIds) {
                if ($isEpSelected) {
                    // ✅ EP: sólo área Educadora de Párvulos (sin filtrar por estamento)
                    if (!empty($epAreaIds)) {
                        $qq->whereIn('area_desempeno_id', $epAreaIds);
                    } else {
                        $qq->whereRaw('1=0');
                    }
                } else {
                    // ✅ Normal: filtra solo por estamento
                    $qq->where('estamento', $estamento);
                }
            });

        // Si usas Spatie y quieres limitar a rol postulante
        if (method_exists(User::class, 'scopeRole')) {
            $q->role(['postulante', 'funcionario']);
        }

        if (app(RestrictedRutService::class)->isAvailable()) {
            $q->whereNotIn('rut', app(RestrictedRutService::class)->courtRestrictedRutsQuery());
        }

        foreach ($tokens as $tok) {
            $tokClean = strtoupper(preg_replace('/[^0-9K]/', '', $tok)); // limpia puntos/guion

            $q->where(function ($qq) use ($tok, $tokClean) {
                $qq->where('nombres', 'like', "%{$tok}%")
                    ->orWhere('apellido_paterno', 'like', "%{$tok}%")
                    ->orWhere('apellido_materno', 'like', "%{$tok}%");

                if ($tokClean !== '') {
                    $qq->orWhere('rut', 'like', "%{$tokClean}%");
                } else {
                    $qq->orWhere('rut', 'like', "%{$tok}%");
                }
            });
        }

        $candidatos = $q->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->limit(300)
            ->get();

        $types = DocumentType::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        // ✅ CV: usado para habilitar/deshabilitar el botón "Ver CV" en el select
        $cvTypeId = (int) optional($types->firstWhere('slug', 'curriculum'))->id;

        $eligibleItems = [];
        $ineligibleItems = [];

        foreach ($candidatos as $u) {
            if (!$u->postulantProfile) continue;

            $name = trim("{$u->nombres} {$u->apellido_paterno} {$u->apellido_materno}");
            $rutFmt = $this->formatRut($u->rut, false); // XXXXXXXX-X (sin puntos)
            $label = trim($name . ' ' . $rutFmt);
            $restriction = app(RestrictedRutService::class)->restrictionContextForUser($u);

            // ✅ Progreso REAL: mismo set de requeridos que "Mis Documentos"
            // y conteo de "subidos" alineado con la vista de revisión de documentos (administración):
            // se considera "subido" si existe el registro UserDocument (no necesariamente path).
            $required    = DocumentRules::requiredTypesFromCatalog($u, $types);
            $requiredIds = $required->pluck('id')->all();

            $uploadedDocs = $u->documents
                ? $u->documents->whereIn('document_type_id', $requiredIds)
                : collect();

            $uploaded = (int) $uploadedDocs->count();

            // Lista de faltantes (labels legibles) para diagnóstico (consola) y UX
            $uploadedIdsSet = $uploadedDocs->pluck('document_type_id')->map(fn($x) => (int) $x)->flip();
            $missingDocs = $required
                ->filter(fn($t) => !isset($uploadedIdsSet[(int) $t->id]))
                ->pluck('label')
                ->values()
                ->all();

            $total   = max(0, count($requiredIds));
            $percent = $total > 0 ? (int) round($uploaded * 100 / $total) : 100;
            $eligible = ($percent >= 100);

            $item = [
                'id'             => (int) $u->postulantProfile->id,
                'text'           => $label, // fallback / selection
                'label'          => $label,
                'area'           => (string) (optional($u->postulantProfile->areaDesempeno)->nombre ?? '—'),
                'area_desempeno_id' => (int) ($u->postulantProfile->area_desempeno_id ?? 0),
                'uploaded'       => (int) $uploaded,
                'total_required' => (int) $total,
                'percent'        => (int) $percent,
                'eligible'       => $eligible,
                'disabled'       => !$eligible,
                'missing_docs'   => $missingDocs,
                'has_cv'         => $cvTypeId > 0
                    ? (bool) ($u->documents?->firstWhere('document_type_id', $cvTypeId)?->path)
                    : false,
                'manual_restriction' => (bool) ($restriction['manual_active'] ?? false),
                'manual_restriction_comment' => (string) ($restriction['manual_comment'] ?? ''),
                'manual_restriction_start' => $restriction['manual_start'] ?? null,
                'manual_restriction_end' => $restriction['manual_end'] ?? null,

                // Campos internos para ordenar (no se exponen al frontend)
                '_same_area'      => ($areaId > 0 && (int) ($u->postulantProfile->area_desempeno_id ?? 0) === $areaId),
                '_area_sort'      => mb_strtolower((string) (optional($u->postulantProfile->areaDesempeno)->nombre ?? ''), 'UTF-8'),
            ];

            if ($eligible) $eligibleItems[] = $item;
            else $ineligibleItems[] = $item;
        }

        // -----------------------------
        // Ordenamiento:
        // - Mantener grupos: Cumplen (100%) primero, luego Faltan.
        // - Dentro de cada grupo: primero misma área que el titular, luego otras áreas ordenadas por área.
        // - En "Faltan": ordenar por % desc dentro del mismo segmento.
        // - Caso EP: como sólo mostramos EP, el orden por área es estable.
        // -----------------------------
        $cmpEligible = function (array $a, array $b): int {
            $sa = !empty($a['_same_area']);
            $sb = !empty($b['_same_area']);
            if ($sa !== $sb) return $sa ? -1 : 1;
            $aa = (string)($a['_area_sort'] ?? '');
            $ab = (string)($b['_area_sort'] ?? '');
            if ($aa !== $ab) return $aa <=> $ab;
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        };

        $cmpIneligible = function (array $a, array $b): int {
            $sa = !empty($a['_same_area']);
            $sb = !empty($b['_same_area']);
            if ($sa !== $sb) return $sa ? -1 : 1;
            $aa = (string)($a['_area_sort'] ?? '');
            $ab = (string)($b['_area_sort'] ?? '');
            if ($aa !== $ab) return $aa <=> $ab;
            $pa = (int)($a['percent'] ?? 0);
            $pb = (int)($b['percent'] ?? 0);
            if ($pa !== $pb) return $pb <=> $pa; // desc
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        };

        usort($eligibleItems, $cmpEligible);
        usort($ineligibleItems, $cmpIneligible);

        foreach ($eligibleItems as &$it) { unset($it['_same_area'], $it['_area_sort']); }
        unset($it);
        foreach ($ineligibleItems as &$it) { unset($it['_same_area'], $it['_area_sort']); }
        unset($it);

        $max = 60;
        $eligibleItems = array_slice($eligibleItems, 0, $max);
        $remaining = $max - count($eligibleItems);
        $ineligibleItems = array_slice($ineligibleItems, 0, max(0, $remaining));

        $groups = [];
        if (!empty($eligibleItems)) {
            $groups[] = [
                'text' => '✅ Cumplen (100%)',
                'children' => $eligibleItems,
            ];
        }
        if (!empty($ineligibleItems)) {
            $groups[] = [
                'text' => '⚠️ Faltan documentos',
                'children' => $ineligibleItems,
            ];
        }

        return response()->json(['results' => $groups]);
}

    public function ajaxAreasDesempeno(Request $request)
    {
        $term = (string) $request->query('term', '');
        $estamento = (string) $request->query('estamento', '');

        $q = AreaDesempeno::query()->select(['id', 'nombre']);

        if ($estamento !== '') {
            $q->where('estamento', $estamento);
        }

        if ($term !== '') {
            $q->where('nombre', 'like', "%{$term}%");
        }

        $results = $q->orderBy('nombre')
            ->get()
            ->map(fn($a) => ['id' => $a->id, 'text' => $a->nombre])
            ->values();

        return response()->json(['results' => $results]);
    }

    // ---------- Helpers ----------

    private function establecimientoDelUsuario(): Establecimiento
    {
        $user = auth()->user();

        // Trae la relación si no está cargada
        $user->loadMissing('establecimiento');

        if (!$user->establecimiento) {
            abort(403, 'Usuario sin establecimiento asociado.');
        }

        return $user->establecimiento;
    }

    private function distribucionJornadaTitular(int $establecimientoId, ReemplazoPersonal $titular): array
    {
        // Usa periodo más reciente (anio/mes) si existen
        $base = ReemplazoPersonal::query()
            ->where('establecimiento_id', $establecimientoId)
            ->where('rut', $titular->rut);

        $last = (clone $base)->orderByDesc('anio')->orderByDesc('mes')->first(['anio', 'mes']);

        if ($last && $last->anio && $last->mes) {
            $base->where('anio', $last->anio)->where('mes', $last->mes);
        }

        $rows = $base->get(['financiamiento', 'jornada', 'jornada_basica', 'jornada_media']);

        $dist = $rows->groupBy('financiamiento')->map(function ($g, $fin) {
            $basica = (float)$g->sum('jornada_basica');
            $media  = (float)$g->sum('jornada_media');
            $total  = (float)$g->sum('jornada');
            // si jornada total no está consistente, fuerza basica+media
            if ($total <= 0) $total = $basica + $media;

            return [
                'financiamiento' => (string)$fin,
                'basica' => $basica,
                'media'  => $media,
                'total'  => $total,
            ];
        })->values()->all();

        // Orden preferido
        $order = ['SUBV GRAL', 'PIE', 'SEP', 'PRO-RETENCION'];
        usort($dist, fn($a, $b) => (array_search($a['financiamiento'], $order) ?? 999) <=> (array_search($b['financiamiento'], $order) ?? 999));

        return $dist;
    }

    private function guessNombreApellidos(string $full): array
    {
        // Tu tabla reemplazos_personal solo tiene "nombre" (no separa paterno/materno)
        // Esto es un “mejor esfuerzo”.
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        $parts = array_values(array_filter($parts));

        if (count($parts) < 3) {
            return [$full, '—', '—'];
        }

        $apM = array_pop($parts);
        $apP = array_pop($parts);
        $nombres = implode(' ', $parts);

        return [$nombres, $apP, $apM];
    }
    private function nombreCompletoUsuario($user): string
    {
        $full = trim(collect([
            $user->nombres ?? null,
            $user->apellido_paterno ?? null,
            $user->apellido_materno ?? null,
        ])->filter(fn($v) => trim((string)$v) !== '')->implode(' '));

        if ($full !== '') return $full;

        // fallback si en tu User usas "name" o "apellidos" en vez de paterno/materno
        $full = trim((string)($user->name ?? ''));
        if ($full !== '') return $full;

        $full = trim(collect([
            $user->nombres ?? null,
            $user->apellidos ?? null,
        ])->filter(fn($v) => trim((string)$v) !== '')->implode(' '));

        return $full !== '' ? $full : '—';
    }
    private function estamentoFromEstatuto(?string $estatuto): ?string
    {
        $e = strtoupper(trim((string) $estatuto));
        if ($e === '') return null;

        // Casos típicos en dotación
        if (in_array($e, ['AAEE', 'A.A.E.E', 'ASISTENTE', 'ASISTENTE DE LA EDUCACION', 'ASISTENTE DE LA EDUCACIÓN'], true)) {
            return 'asistente';
        }

        if (in_array($e, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true)) {
            return 'docente';
        }

        // Heurística por si vienen variantes
        if (str_contains($e, 'AAEE') || str_contains($e, 'ASIST')) return 'asistente';
        if (str_contains($e, 'DOC')) return 'docente';

        return null;
    }

    private function funcionarioTitularEsDocente(?string $estatuto): bool
    {
        $e = strtoupper(trim((string) $estatuto));
        if ($e === '') {
            return false;
        }

        return in_array($e, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($e, 'DOC');
    }

    private function titularTieneBloqueoIndividualActivo(ReemplazoPersonal $titular): bool
    {
        if (!$this->funcionarioTitularEsDocente($titular->estatuto)) {
            return false;
        }

        if ($titular->relationLoaded('bloqueoActivo')) {
            return $titular->bloqueoActivo !== null;
        }

        return ReemplazoPersonalBloqueo::query()
            ->where('reemplazo_personal_id', $titular->id)
            ->where('activo', true)
            ->exists();
    }
    private function tiposReemplazoValidos(): array
    {
        return [
            'Licencia Médica (General)',
            'Licencia Médica (Pre y/o Post Natal y/o Parental)',
            'Permiso Postnatal Parental',
            'Permiso sin goce de sueldo',
            'Permiso Horas de Lactancia',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Sumario Administrativo',
            'Otras',
        ];
    }

    private function tiposReemplazoDeshabilitados(): array
    {
        return [
            'Permiso Horas de Lactancia',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Otras',
        ];
    }

    private function tipoPermisoSinGoce(): string
    {
        return 'Permiso sin goce de sueldo';
    }

    private function titularPuedeSolicitarPermisoSinGoce(ReemplazoPersonal $titular): bool
    {
        if (!$this->funcionarioTitularEsDocente($titular->estatuto)) {
            return false;
        }

        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $titular->rut));
        if ($rut === '') {
            return false;
        }

        return PermisoSinGoceExcepcion::query()
            ->where('rut_normalizado', $rut)
            ->where('activo', true)
            ->exists();
    }

    private function userHasAllRequiredDocsApproved(User $u, $types): bool
    {
        $u->loadMissing(['documents']);

        $docsByType = $u->documents->keyBy('document_type_id');
        $required = $types->filter(fn(DocumentType $t) => $t->isRequiredForUser($u));

        foreach ($required as $t) {
            $doc = $docsByType->get($t->id);
            if (!$doc || $doc->status !== 'approved') {
                return false;
            }
        }

        return true;
    }

    private function userHasAllRequiredDocsUploaded(User $u, $types): bool
    {
        // Para el tag "(documentos sin cargar)" consideramos solo existencia de archivo (path),
        // no el estado de revisión (approved/rejected).
        $u->loadMissing(['documents']);

        $docsByType = $u->documents->keyBy('document_type_id');
        $required = $types->filter(fn(DocumentType $t) => $t->isRequiredForUser($u));

        foreach ($required as $t) {
            $doc = $docsByType->get($t->id);
            if (!$doc || blank($doc->path)) {
                return false;
            }
        }

        return true;
    }

    private function formatRut(?string $rut, bool $withDots = true): string
    {
        $rut = strtoupper(trim((string) $rut));
        $rut = preg_replace('/[^0-9K]/', '', $rut); // solo números y K

        if ($rut === '' || strlen($rut) < 2) {
            return $rut;
        }

        $dv = substr($rut, -1);
        $body = substr($rut, 0, -1);

        // Mantener ceros a la izquierda si existen
        if ($withDots) {
            $body = number_format((int) $body, 0, '', '.'); // 12345678 -> 12.345.678
            // OJO: number_format pierde ceros a la izquierda. Si eso te importa, usa el bloque alternativo abajo.
        }

        return "{$body}-{$dv}";
    }

    private function finKey(string $fin): string
    {
        // Debe ser idéntico a escapeAttr() del JS:
        // String(s).replace(/[^a-zA-Z0-9_\-]/g, '_')
        return preg_replace('/[^a-zA-Z0-9_\-]/u', '_', (string) $fin);
    }

    private function jornadaIn(array $jornadasIn, string $fin, string $campo, float $default = 0.0): float
    {
        $k = $this->finKey($fin);

        $raw = $jornadasIn[$k][$campo] ?? $jornadasIn[$fin][$campo] ?? null;

        if ($raw === null || $raw === '') {
            return $default;
        }

        // Por si llega con coma decimal
        $raw = str_replace(',', '.', (string) $raw);

        return is_numeric($raw) ? (float) $raw : $default;
    }

    private function requestDecimal(Request $request, string $key): float
    {
        $raw = $request->input($key);

        if ($raw === null || $raw === '') {
            return 0.0;
        }

        $raw = str_replace(',', '.', (string) $raw);

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function sumDistribucionTotal(array $distTitular): float
    {
        return (float) collect($distTitular)->sum(fn ($row) => (float) ($row['total'] ?? 0));
    }

    private function sumJornadasReemplazo(array $distTitular, array $jornadasIn): float
    {
        $total = 0.0;

        foreach ($distTitular as $row) {
            $fin = (string) ($row['financiamiento'] ?? '');
            $total += $this->jornadaIn($jornadasIn, $fin, 'basica', 0.0);
            $total += $this->jornadaIn($jornadasIn, $fin, 'media', 0.0);
        }

        return (float) $total;
    }
}
