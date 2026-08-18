<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Mail\OrdenTrabajoCreada;
use App\Mail\OrdenTrabajoCorreoInstitucionalSoporteTi;
use App\Mail\ContratoTrabajoFirmadoEnviado;
use App\Mail\ResolucionDocenteFirmadaEnviada;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoDeudaPension;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazoJornada;
use App\Models\SolicitudReemplazoObservacion;
use App\Models\PostulantProfile;
use App\Models\User;
use App\Models\Establecimiento;
use App\Models\FuncionarioAcAutorizado;
use App\Models\AaeeValorHora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Mail\SolicitudReemplazoAprobadaUatpPendientePlani;
use App\Mail\SolicitudReemplazoObservacionSlep;
use App\Mail\SolicitudReemplazoRechazadaPlani;
use App\Mail\SolicitudReemplazoRechazadaUatp;
use App\Mail\SolicitudReemplazoValidadaPlani;
use Illuminate\Support\Carbon;
use App\Support\NotificationAudit;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\RestrictedRutService;
use App\Services\SolicitudReemplazoAutorizacionDocenteService;
use App\Services\ResolucionDocenteDocxService;

class SolicitudReemplazoGestionController extends Controller
{
    private const MAX_REPLACEMENT_WEEKLY_HOURS = 44.0;

        public function index(Request $request)
    {
        $user = $request->user();

        $canUatp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_uatp'])
            : false;

        $canGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp'])
            : false;

        $canPlani = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'supervisor_plani'])
            : false;

        $canViewPendientesValidacion = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'supervisor_plani', 'coordinador_gdp', 'funcionario_slep'])
            : false;

        $isFuncionarioSlep = method_exists($user, 'hasRole')
            ? $user->hasRole('funcionario_slep')
            : false;

        $isOnlyPlani = (method_exists($user, 'hasRole') ? $user->hasRole('supervisor_plani') : false)
            && !$canUatp
            && !$canGdp
            && !$isFuncionarioSlep
            && !(method_exists($user, 'hasRole') ? $user->hasRole('admin') : false);

        $showOtras = true;

        $baseAll = SolicitudReemplazo::query()
            ->with([
                'establecimiento',
                'funcionarioTitular',
                'postulante.user',
                'contratoPostulante.user',
                'areaDesempeno',
                'jornadas',
                'derivadaA',
                'uatpDecisionUser',
                'planiDecisionUser',
                'planiReaperturaUser',
                'observacionSlepUser',
            ]);

        $pendientesQ = (clone $baseAll)->where('estado', 'pendiente_uatp');

        if ($request->filled('p_numero')) {
            $pendientesQ->where('numero_solicitud', 'like', '%' . trim($request->string('p_numero')) . '%');
        }
        if ($request->filled('p_establecimiento_id')) {
            $pendientesQ->where('establecimiento_id', (int) $request->input('p_establecimiento_id'));
        }
        if ($request->filled('p_titular')) {
            $t = '%' . trim($request->string('p_titular')) . '%';
            $pendientesQ->whereHas('funcionarioTitular', function ($q) use ($t) {
                $q->where('rut', 'like', $t)->orWhere('nombre', 'like', $t);
            });
        }
        if ($request->filled('p_desde')) {
            $pendientesQ->whereDate('created_at', '>=', $request->date('p_desde')->format('Y-m-d'));
        }
        if ($request->filled('p_hasta')) {
            $pendientesQ->whereDate('created_at', '<=', $request->date('p_hasta')->format('Y-m-d'));
        }

        $pendientesUatp = $pendientesQ
            ->orderBy('created_at', 'asc')
            ->paginate(20, ['*'], 'pendientes_page')
            ->withQueryString();

        $pendientesValidacion = null;
        if ($canViewPendientesValidacion) {
            $validacionQ = (clone $baseAll)->where('estado', 'pendiente_validacion');

            if ($request->filled('v_numero')) {
                $validacionQ->where('numero_solicitud', 'like', '%' . trim($request->string('v_numero')) . '%');
            }
            if ($request->filled('v_establecimiento_id')) {
                $validacionQ->where('establecimiento_id', (int) $request->input('v_establecimiento_id'));
            }
            if ($request->filled('v_titular')) {
                $t = '%' . trim($request->string('v_titular')) . '%';
                $validacionQ->whereHas('funcionarioTitular', function ($q) use ($t) {
                    $q->where('rut', 'like', $t)->orWhere('nombre', 'like', $t);
                });
            }
            if ($request->filled('v_desde')) {
                $validacionQ->whereDate('uatp_decision_at', '>=', $request->date('v_desde')->format('Y-m-d'));
            }
            if ($request->filled('v_hasta')) {
                $validacionQ->whereDate('uatp_decision_at', '<=', $request->date('v_hasta')->format('Y-m-d'));
            }

            $pendientesValidacion = $validacionQ
                ->orderBy('uatp_decision_at', 'asc')
                ->orderBy('created_at', 'asc')
                ->paginate(20, ['*'], 'validacion_page')
                ->withQueryString();
        }

        $otras = null;
        if ($showOtras) {
            $otrasQ = (clone $baseAll)->where('estado', '<>', 'pendiente_uatp');

            if ($isOnlyPlani) {
                $otrasQ->where('estado', '<>', 'pendiente_validacion');
            }

            if ($isFuncionarioSlep && !$canUatp && !$canGdp && !$canPlani) {
                $otrasQ->where('derivada_a_user_id', $user->id)
                    ->whereIn('estado', ['derivada_slep', 'aceptada', 'cerrado']);
            }

            if ($request->filled('o_numero')) {
                $otrasQ->where('numero_solicitud', 'like', '%' . trim($request->string('o_numero')) . '%');
            }
            if ($request->filled('o_establecimiento_id')) {
                $otrasQ->where('establecimiento_id', (int) $request->input('o_establecimiento_id'));
            }
            if ($request->filled('o_estado')) {
                $otrasQ->where('estado', $request->string('o_estado'));
            }
            if ($request->filled('o_desde')) {
                $otrasQ->whereDate('created_at', '>=', $request->date('o_desde')->format('Y-m-d'));
            }
            if ($request->filled('o_hasta')) {
                $otrasQ->whereDate('created_at', '<=', $request->date('o_hasta')->format('Y-m-d'));
            }
            if ($request->filled('o_titular')) {
                $t = '%' . trim($request->string('o_titular')) . '%';
                $otrasQ->whereHas('funcionarioTitular', function ($q) use ($t) {
                    $q->where('rut', 'like', $t)->orWhere('nombre', 'like', $t);
                });
            }
            if ($request->filled('o_reemplazo')) {
                $t = '%' . trim($request->string('o_reemplazo')) . '%';
                $otrasQ->where(function ($q) use ($t) {
                    $q->whereHas('postulante.user', function ($qq) use ($t) {
                        $qq->where('rut', 'like', $t)
                            ->orWhere('nombres', 'like', $t)
                            ->orWhere('apellido_paterno', 'like', $t)
                            ->orWhere('apellido_materno', 'like', $t);
                    })->orWhereHas('contratoPostulante.user', function ($qq) use ($t) {
                        $qq->where('rut', 'like', $t)
                            ->orWhere('nombres', 'like', $t)
                            ->orWhere('apellido_paterno', 'like', $t)
                            ->orWhere('apellido_materno', 'like', $t);
                    });
                });
            }
            if ($request->filled('o_derivada_a')) {
                $otrasQ->where('derivada_a_user_id', (int) $request->input('o_derivada_a'));
            }

            $otras = $otrasQ
                ->orderBy('created_at', 'desc')
                ->paginate(20, ['*'], 'otras_page')
                ->withQueryString();
        }

        $establecimientos = Establecimiento::query()
            ->orderBy('nombre_establecimiento', 'asc')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'sala_cuna']);

        $destinatarios = User::query()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['funcionario_slep', 'coordinador_gdp']);
            })
            ->orderBy('apellido_paterno')
            ->orderBy('apellido_materno')
            ->orderBy('nombres')
            ->get(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'])
            ->map(function ($u) {
                $u->full_name = trim(($u->apellido_paterno ?? '') . ' ' . ($u->apellido_materno ?? '') . ' ' . ($u->nombres ?? ''));
                return $u;
            });

        $canCollapsePendientesUatp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep', 'supervisor_plani'])
            : false;

        $canCollapsePendientesValidacion = $canViewPendientesValidacion;
        $canCollapseOtras = $showOtras;

        $showResumenSlep = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp'])
            : false;

        $resumenSlepPorUsuario = collect();
        $totalesSlepEstados = ['derivada_slep' => 0, 'aceptada' => 0, 'cerrado' => 0, 'anulada' => 0];

        if ($showResumenSlep) {
            $agg = SolicitudReemplazo::query()
                ->select('derivada_a_user_id', 'estado', DB::raw('COUNT(*) as total'))
                ->whereNotNull('derivada_a_user_id')
                ->whereIn('estado', ['derivada_slep', 'aceptada', 'cerrado', 'anulada'])
                ->groupBy('derivada_a_user_id', 'estado')
                ->get();

            $userIds = $agg->pluck('derivada_a_user_id')->filter()->unique()->values();
            $usersMap = User::query()
                ->whereIn('id', $userIds)
                ->get(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'])
                ->keyBy('id');

            $tmp = [];
            foreach ($agg as $row) {
                $uid = (int) $row->derivada_a_user_id;
                $estado = (string) $row->estado;
                $cnt = (int) $row->total;

                if (!isset($tmp[$uid])) {
                    $u = $usersMap->get($uid);
                    $full = $u ? trim(($u->apellido_paterno ?? '') . ' ' . ($u->apellido_materno ?? '') . ' ' . ($u->nombres ?? '')) : ('Usuario #' . $uid);
                    $tmp[$uid] = [
                        'user_id' => $uid,
                        'user_name' => $full,
                        'rut' => $u?->rut,
                        'derivada_slep' => 0,
                        'aceptada' => 0,
                        'cerrado' => 0,
                        'anulada' => 0,
                        'total' => 0,
                    ];
                }

                if (array_key_exists($estado, $totalesSlepEstados)) {
                    $tmp[$uid][$estado] = $cnt;
                    $totalesSlepEstados[$estado] += $cnt;
                }
            }

            foreach ($tmp as $uid => $row) {
                $row['total'] = (int) $row['derivada_slep'] + (int) $row['aceptada'] + (int) $row['cerrado'] + (int) $row['anulada'];
                $tmp[$uid] = $row;
            }

            $resumenSlepPorUsuario = collect(array_values($tmp))
                ->sort(function ($a, $b) {
                    $cmp = ($b['derivada_slep'] <=> $a['derivada_slep']);
                    if ($cmp !== 0) return $cmp;
                    $cmp = ($b['total'] <=> $a['total']);
                    if ($cmp !== 0) return $cmp;
                    return strcmp((string) $a['user_name'], (string) $b['user_name']);
                })
                ->values();
        }

        return view('gestion.solicitudes-reemplazo.index', [
            'pendientesUatp' => $pendientesUatp,
            'pendientesValidacion' => $pendientesValidacion,
            'otras' => $otras,
            'showOtras' => $showOtras,
            'canUatp' => $canUatp,
            'canGdp' => $canGdp,
            'canPlani' => $canPlani,
            'canViewPendientesValidacion' => $canViewPendientesValidacion,
            'isFuncionarioSlep' => $isFuncionarioSlep,
            'establecimientos' => $establecimientos,
            'destinatarios' => $destinatarios,
            'canCollapsePendientesUatp' => $canCollapsePendientesUatp,
            'canCollapsePendientesValidacion' => $canCollapsePendientesValidacion,
            'canCollapseOtras' => $canCollapseOtras,
            'isOnlyPlani' => $isOnlyPlani,
            'showResumenSlep' => $showResumenSlep,
            'resumenSlepPorUsuario' => $resumenSlepPorUsuario,
            'totalesSlepEstados' => $totalesSlepEstados,
        ]);
    }


    public function exportar(Request $request)
    {
        $user = $request->user();
        $canExport = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp'])
            : false;

        abort_unless($canExport, 403);

        $scope = (string) $request->query('scope', 'gdp');
        if (!in_array($scope, ['validacion', 'uatp', 'gdp'], true)) {
            $scope = 'gdp';
        }

        $query = $this->buildSolicitudesExportQuery($request, $scope);
        $scopeLabel = match ($scope) {
            'validacion' => 'validacion_planificacion',
            'uatp' => 'pendientes_uatp',
            default => 'gestion_gdp',
        };

        $filename = 'solicitudes_reemplazo_' . $scopeLabel . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query, $scope) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

            $headers = [
                'N solicitud',
                'Estado',
                'Fecha solicitud',
                'Fecha inicio',
                'Fecha termino',
                'RBD',
                'Establecimiento',
                'Titular RUT',
                'Titular nombre',
                'Estatuto titular',
                'Area desempeno',
                'Horas titular cronologicas',
                'Horas titular pedagogicas',
                'Horas reemplazo cronologicas',
                'Horas reemplazo pedagogicas',
            ];

            if ($scope === 'gdp') {
                array_push($headers,
                    'Horas reemplazo efectivo Subvencion General Basica',
                    'Horas reemplazo efectivo Subvencion General Media',
                    'Horas reemplazo efectivo SEP Basica',
                    'Horas reemplazo efectivo SEP Media',
                    'Horas reemplazo efectivo PIE Basica',
                    'Horas reemplazo efectivo PIE Media',
                );
            }

            array_push($headers,
                'Reemplazo RUT',
                'Reemplazo nombre',
                'Tipo reemplazo',
                'Fecha aprobacion UATP',
                'Usuario UATP',
                'Fecha validacion Planificacion',
                'Usuario Planificacion',
                'Derivada a',
                'Fecha derivacion',
                'Fecha cierre',
                'Observaciones',
                'Justificacion UATP',
                'Motivo rechazo Planificacion',
                'Observacion SLEP',
            );

            fputcsv($handle, $headers, ';');

            $query->chunk(500, function ($rows) use ($handle, $scope) {
                foreach ($rows as $s) {
                    $titular = $s->funcionarioTitular;
                    $postulante = $s->postulante ?? $s->contratoPostulante;
                    $postulanteUser = $postulante?->user;

                    $row = [
                        $s->numero_solicitud,
                        $this->estadoSolicitudLabel($s->estado),
                        optional($s->created_at)->format('d-m-Y H:i'),
                        optional($s->fecha_inicio)->format('d-m-Y'),
                        optional($s->fecha_termino)->format('d-m-Y'),
                        $s->establecimiento?->rbd,
                        $s->establecimiento?->nombre_establecimiento,
                        $this->formatRutChile($titular?->rut ?: $s->rut_titular_normalizado),
                        $this->uppercaseExport($titular?->nombre),
                        $titular?->estatuto,
                        $s->areaDesempeno?->nombre,
                        $this->formatDecimalExport($s->horas_aula_cronologicas_titular),
                        $this->formatDecimalExport($s->horas_aula_pedagogicas_titular),
                        $this->formatDecimalExport($s->horas_aula_cronologicas_reemplazo),
                        $this->formatDecimalExport($s->horas_aula_pedagogicas_reemplazo),
                    ];

                    if ($scope === 'gdp') {
                        array_push($row, ...array_values($this->replacementEffectiveHoursExport($s)));
                    }

                    array_push($row,
                        $this->formatRutChile($postulanteUser?->rut ?: $s->rut_reemplazo_normalizado),
                        $this->userNombreCompletoExport($postulanteUser),
                        $s->tipo_reemplazo,
                        optional($s->uatp_decision_at)->format('d-m-Y H:i'),
                        $this->userNombreExport($s->uatpDecisionUser),
                        optional($s->plani_decision_at)->format('d-m-Y H:i'),
                        $this->userNombreExport($s->planiDecisionUser),
                        $this->userNombreExport($s->derivadaA),
                        optional($s->derivada_at)->format('d-m-Y H:i'),
                        optional($s->cerrado_at)->format('d-m-Y H:i'),
                        $this->cleanCsvText($s->observaciones),
                        $this->cleanCsvText($s->justificacion_tecnica_uatp),
                        $this->cleanCsvText($s->plani_motivo_rechazo),
                        $this->cleanCsvText($s->observacion_slep),
                    );

                    fputcsv($handle, $row, ';');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(SolicitudReemplazo $solicitud)
    {
        $user = request()->user();

        $canUatp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_uatp'])
            : false;

        $canPlani = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'supervisor_plani'])
            : false;

        $solicitud->load([
            'establecimiento',
            'funcionarioTitular',
            'postulante.user',
            'areaDesempeno',
            'jornadas',
            'ordenTrabajoCreadaPor',
            'observacionSlepUser',
            'uatpDecisionUser',
            'planiDecisionUser',
            'planiReaperturaUser',
            'contratoTrabajoFirmadoSubidoPor',
            'contratoTrabajoFirmadoEnviadoPor',
            'cerradoPor',
            'reemplazoAjusteUser',
            'observacionesFlujo.user',
            'autorizacionDocente.solicitadoPor',
            'autorizacionDocente.numeroRegistradoPor',
            'autorizacionDocente.estadoActualizadoPor',
            'deudaPension.postulante.user',
            'derivadaA.roles',
        ]);

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isFuncionarioSlep = method_exists($user, 'hasRole')
            ? $user->hasRole('funcionario_slep')
            : false;

        $deudaPension = $solicitud->deudaPension;
        $deudaPensionBloqueaFlujo = $this->deudaPensionBloqueaFlujo($solicitud);
        $canCrearOt = $solicitud->estado === 'derivada_slep'
            && ! $deudaPensionBloqueaFlujo
            && (
                $isAdmin
                || $isGdp
                || ($isFuncionarioSlep && (int) $solicitud->derivada_a_user_id === (int) $user->id)
            );

        $lockedByOtContrato = $this->hasOrdenOrContrato($solicitud);
        $canSlepActions = in_array($solicitud->estado, ['derivada_slep', 'aceptada'], true)
            && (
                $solicitud->estado === 'aceptada'
                || !$lockedByOtContrato
            )
            && (
                $isAdmin
                || $isGdp
                || ($isFuncionarioSlep && (int) $solicitud->derivada_a_user_id === (int) $user->id)
            );

        $canInformarObservacion = $solicitud->estado === 'derivada_slep'
            && (
                $isAdmin
                || $isGdp
                || ($isFuncionarioSlep && (int) $solicitud->derivada_a_user_id === (int) $user->id)
            );

        $estadosConBitacora = [
            'pendiente_uatp', 'rechazada_uatp', 'pendiente_validacion', 'rechazada_plani',
            'pendiente_gdp', 'derivada_slep', 'aceptada',
        ];
        $canRegistrarSolicitudObservacion = in_array((string) $solicitud->estado, $estadosConBitacora, true)
            && (
                $isAdmin
                || $isGdp
                || ($isFuncionarioSlep && in_array((string) $solicitud->estado, ['derivada_slep', 'aceptada'], true)
                    && (int) $solicitud->derivada_a_user_id === (int) $user->id)
                || ($canUatp && in_array((string) $solicitud->estado, ['pendiente_uatp', 'rechazada_uatp'], true))
                || ($canPlani && in_array((string) $solicitud->estado, ['pendiente_validacion', 'rechazada_plani'], true))
            );
        $canDerivarGestion = $solicitud->estado === 'derivada_slep'
            && !$lockedByOtContrato
            && $isFuncionarioSlep
            && (int) $solicitud->derivada_a_user_id === (int) $user->id;
        $canCerrarSinOt = $solicitud->estado === 'derivada_slep'
            && !$lockedByOtContrato
            && ($isAdmin || $isGdp);
        $responsablesGestion = ($canDerivarGestion || $canCerrarSinOt)
            ? User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'coordinador_gdp', 'coordinador_gdp_admin']))
                ->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres')->get()
            : collect();

        $canPlaniReview = $solicitud->estado === 'pendiente_validacion' && $canPlani;
        $canReabrirRoles = ['admin', 'funcionario_slep', 'supervisor_plani', 'coordinador_gdp'];
        $canReabrirPlanificacion = $solicitud->estado === 'rechazada_plani'
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($canReabrirRoles);
        $canReabrirUatp = $solicitud->estado === 'rechazada_uatp'
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($canReabrirRoles);
        $canAdjustReplacement = $this->canAdjustReplacementSchedule($user, $solicitud);

        $hasContratoBase = !empty($solicitud->contrato_trabajo_docx_path);
        $canGestionarContratoFirmado = $solicitud->estado === 'aceptada'
            && $hasContratoBase
            && (
                $isAdmin
                || $isGdp
                || ($isFuncionarioSlep && (int) $solicitud->derivada_a_user_id === (int) $user->id)
            );

        $titularEsDocente = $this->estamentoFromEstatuto($solicitud->funcionarioTitular?->estatuto) === 'docente';
        $activeRole = method_exists($user, 'activeRoleName') ? (string) $user->activeRoleName() : '';
        $tieneFuncionarioSlepAsignado = $solicitud->derivada_a_user_id
            && $solicitud->derivadaA
            && method_exists($solicitud->derivadaA, 'hasRole')
            && $solicitud->derivadaA->hasRole('funcionario_slep');
        $canVerDeudaPension = in_array($activeRole, ['admin', 'funcionario_slep'], true)
            && $tieneFuncionarioSlepAsignado
            && ($activeRole === 'admin' || (int) $solicitud->derivada_a_user_id === (int) $user->id);
        $canGestionarDeudaPension = $canVerDeudaPension
            && $solicitud->estado === 'derivada_slep';
        $canGestionarAutorizacionDocente = in_array($activeRole, ['admin', 'coordinador_uatp'], true)
            && $titularEsDocente
            && (bool) $solicitud->propone_reemplazo
            && $solicitud->postulante?->user !== null;
        $autorizacionDocente = $solicitud->autorizacionDocente;
        $autorizacionDocenteService = app(SolicitudReemplazoAutorizacionDocenteService::class);
        $puedeAprobarUatpPorAutorizacionDocente = $autorizacionDocenteService
            ->cumpleRegistroParaAprobacionUatp($solicitud);
        $documentoTituloPostulante = $canGestionarAutorizacionDocente
            ? $autorizacionDocenteService->documentoTitulo($solicitud)
            : null;
        $autorizacionDocenteRequiereReligion = $canGestionarAutorizacionDocente
            && $autorizacionDocenteService->esAreaReligion($solicitud);
        $estadosRevisionConAntecedentes = ['pendiente_uatp', 'rechazada_uatp', 'pendiente_validacion', 'rechazada_plani'];
        $estadosHistorialConAntecedentes = array_merge($estadosRevisionConAntecedentes, ['derivada_slep', 'aceptada', 'cerrado', 'cerrada']);
        $esRolRevisionHistorial = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'coordinador_uatp', 'supervisor_plani']);

        // Durante la revisión se mantiene la visibilidad actual para todos los perfiles autorizados.
        // UATP y Planificación además pueden consultar el historial una vez que la solicitud avanzó o fue cerrada.
        $mostrarSolicitudesAnterioresRelacionadas = in_array((string) $solicitud->estado, $estadosRevisionConAntecedentes, true)
            || ($esRolRevisionHistorial && in_array((string) $solicitud->estado, $estadosHistorialConAntecedentes, true));
        $solicitudesAnterioresRelacionadas = $mostrarSolicitudesAnterioresRelacionadas
            ? $this->solicitudesAnterioresRelacionadas($solicitud)
            : collect();

        $canCerrarSolicitudDocente = $solicitud->estado === 'aceptada'
            && $titularEsDocente
            && !$this->hasContratoTrabajoAsociado($solicitud)
            && !empty($solicitud->resolucion_docente_firmada_pdf_path)
            && !empty($solicitud->resolucion_docente_notificada_at)
            && (
                $isAdmin
                || $isGdp
                || ($isFuncionarioSlep && (int) $solicitud->derivada_a_user_id === (int) $user->id)
            );

        $canRetornarDerivadaSlep = in_array((string) $solicitud->estado, ['aceptada', 'cerrado', 'cerrada'], true)
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'coordinador_gdp']);
        $tieneFiniquitoAsociado = $this->hasFiniquitoAsociado($solicitud);

        $candidatos = collect();
        if ($canCrearOt && !$solicitud->propone_reemplazo) {
            $tit = $solicitud->funcionarioTitular;
            $expectedEstamento = $this->estamentoFromEstatuto($tit?->estatuto);

            if ($expectedEstamento) {
                $busyIds = SolicitudReemplazo::query()
                    ->whereNotNull('postulant_profile_id')
                    ->where('id', '<>', $solicitud->id)
                    ->whereIn('estado', ['aceptada'])
                    ->whereDate('fecha_inicio', '<=', $solicitud->fecha_termino)
                    ->whereDate('fecha_termino', '>=', $solicitud->fecha_inicio)
                    ->pluck('postulant_profile_id')
                    ->all();

                $isEpSelected = ($solicitud->areaDesempeno?->slug === 'educadora_de_parvulos');
                $epAreaIds = [];

                if ($isEpSelected) {
                    $epAreaIds = \App\Models\AreaDesempeno::query()
                        ->activos()
                        ->where('slug', 'educadora_de_parvulos')
                        ->pluck('id')
                        ->map(fn($x) => (int) $x)
                        ->all();
                }

                $q = User::query()
                    ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'])
                    ->with([
                        'postulantProfile:id,user_id,estamento,area_desempeno_id',
                        'postulantProfile.areaDesempeno:id,nombre,slug,estamento',
                        'documents:id,user_id,document_type_id,path,status',
                    ])
                    ->whereHas('postulantProfile', function ($qq) use ($expectedEstamento, $busyIds, $isEpSelected, $epAreaIds) {
                        $qq->where(function ($w) use ($expectedEstamento, $isEpSelected, $epAreaIds) {
                            $w->where('estamento', $expectedEstamento);

                            if ($isEpSelected && !empty($epAreaIds)) {
                                $w->orWhereIn('area_desempeno_id', $epAreaIds);
                            }
                        });

                        if (!empty($busyIds)) {
                            $qq->whereNotIn('id', $busyIds);
                        }
                    });

                if (method_exists(User::class, 'scopeRole')) {
                    $q->role(['postulante', 'funcionario']);
                }

                $users = $q->orderBy('apellido_paterno')
                    ->orderBy('apellido_materno')
                    ->orderBy('nombres')
                    ->limit(2500)
                    ->get()
                    ->filter(fn($u) => $u->postulantProfile)
                    ->values();

                $types = \App\Models\DocumentType::query()
                    ->orderBy('sort_order')
                    ->orderBy('label')
                    ->get();

                $epOk = [];
                $epMissing = [];
                $baseOk = [];
                $baseMissing = [];

                foreach ($users as $u) {
                    $name = trim("{$u->nombres} {$u->apellido_paterno} {$u->apellido_materno}");
                    $rutFmt = $this->formatRutChile($u->rut);
                    $baseText = trim($name . ' ' . $rutFmt);

                    $docsOk = $this->userHasAllRequiredDocsUploaded($u, $types);

                    $item = [
                        'id' => (int) $u->postulantProfile->id,
                        'text' => $docsOk ? $baseText : ($baseText . ' (documentos sin cargar)'),
                    ];

                    $isEp = $isEpSelected && !empty($epAreaIds)
                        && in_array((int) $u->postulantProfile->area_desempeno_id, $epAreaIds, true);

                    if ($isEp) {
                        if ($docsOk) $epOk[] = $item;
                        else $epMissing[] = $item;
                    } else {
                        if ($docsOk) $baseOk[] = $item;
                        else $baseMissing[] = $item;
                    }
                }

                $candidatos = collect(array_merge($epOk, $epMissing, $baseOk, $baseMissing));
            }
        }

        return view('gestion.solicitudes-reemplazo.show', [
            's' => $solicitud,
            'canUatp' => $canUatp,
            'canPlani' => $canPlani,
            'canPlaniReview' => $canPlaniReview,
            'canReabrirPlanificacion' => $canReabrirPlanificacion,
            'canReabrirUatp' => $canReabrirUatp,
            'canAdjustReplacement' => $canAdjustReplacement,
            'canCrearOt' => $canCrearOt,
            'canSlepActions' => $canSlepActions,
            'lockedByOtContrato' => $lockedByOtContrato,
            'candidatosOt' => $candidatos,
            'canInformarObservacion' => $canInformarObservacion,
            'canRegistrarSolicitudObservacion' => $canRegistrarSolicitudObservacion,
            'canDerivarGestion' => $canDerivarGestion,
            'canCerrarSinOt' => $canCerrarSinOt,
            'responsablesGestion' => $responsablesGestion,
            'canGestionarContratoFirmado' => $canGestionarContratoFirmado,
            'canCerrarSolicitudDocente' => $canCerrarSolicitudDocente,
            'canRetornarDerivadaSlep' => $canRetornarDerivadaSlep,
            'tieneFiniquitoAsociado' => $tieneFiniquitoAsociado,
            'mostrarSolicitudesAnterioresRelacionadas' => $mostrarSolicitudesAnterioresRelacionadas,
            'solicitudesAnterioresRelacionadas' => $solicitudesAnterioresRelacionadas,
            'canGestionarAutorizacionDocente' => $canGestionarAutorizacionDocente,
            'autorizacionDocente' => $autorizacionDocente,
            'puedeAprobarUatpPorAutorizacionDocente' => $puedeAprobarUatpPorAutorizacionDocente,
            'documentoTituloPostulante' => $documentoTituloPostulante,
            'autorizacionDocenteRequiereReligion' => $autorizacionDocenteRequiereReligion,
            'canGestionarDeudaPension' => $canGestionarDeudaPension,
            'deudaPension' => $deudaPension,
            'deudaPensionBloqueaFlujo' => $deudaPensionBloqueaFlujo,
            'canVerDeudaPension' => $canVerDeudaPension,
        ]);
    }

    private function solicitudesAnterioresRelacionadas(SolicitudReemplazo $solicitud)
    {
        $solicitud->loadMissing([
            'funcionarioTitular:id,rut,nombre',
            'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
            'contratoPostulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
        ]);

        $rutTitularFuente = $solicitud->funcionarioTitular?->rut
            ?: $solicitud->rut_titular_normalizado;
        $rutTitularComparable = $this->rutComparable($rutTitularFuente);

        $postulanteActual = $solicitud->contratoPostulante ?: $solicitud->postulante;
        $rutReemplazoFuente = $postulanteActual?->user?->rut
            ?: $solicitud->rut_reemplazo_normalizado;
        $rutReemplazoComparable = $this->rutComparable($rutReemplazoFuente);
        $postulanteProfileIds = collect([
            $solicitud->postulant_profile_id,
            $solicitud->contrato_trabajo_postulant_profile_id,
            $postulanteActual?->id,
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($rutTitularComparable === '' && blank($solicitud->reemplazo_personal_id)) {
            return collect();
        }

        $idsTitularMismoRut = collect();
        if ($rutTitularComparable !== '') {
            $idsTitularMismoRut = ReemplazoPersonal::query()
                ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rutTitularComparable])
                ->pluck('id');
        }

        return SolicitudReemplazo::query()
            ->with([
                'establecimiento:id,rbd,nombre_establecimiento,comuna',
                'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                'contratoPostulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                'funcionarioTitular:id,rut,nombre',
            ])
            ->where('id', '<>', $solicitud->id)
            ->whereIn('estado', ['aceptada', 'cerrado', 'cerrada'])
            ->where(function ($q) use ($solicitud, $rutTitularComparable, $idsTitularMismoRut) {
                if ($rutTitularComparable !== '') {
                    $q->where(function ($qq) use ($rutTitularComparable, $idsTitularMismoRut) {
                        if ($idsTitularMismoRut->isNotEmpty()) {
                            $qq->whereIn('reemplazo_personal_id', $idsTitularMismoRut->all());
                        }

                        $qq->orWhereHas('funcionarioTitular', function ($w) use ($rutTitularComparable) {
                            $w->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rutTitularComparable]);
                        });

                        $qq->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(COALESCE(rut_titular_normalizado, ''), '.', ''), '-', ''), ' ', '')) = ?", [$rutTitularComparable]);
                    });

                    return;
                }

                $q->where('reemplazo_personal_id', $solicitud->reemplazo_personal_id);
            })
            ->when($rutReemplazoComparable !== '' || $postulanteProfileIds->isNotEmpty(), function ($q) use ($rutReemplazoComparable, $postulanteProfileIds) {
                $q->where(function ($w) use ($rutReemplazoComparable, $postulanteProfileIds) {
                    if ($postulanteProfileIds->isNotEmpty()) {
                        $ids = $postulanteProfileIds->all();
                        $w->whereIn('postulant_profile_id', $ids)
                            ->orWhereIn('contrato_trabajo_postulant_profile_id', $ids);
                    }

                    if ($rutReemplazoComparable !== '') {
                        $w->orWhereRaw(
                            "UPPER(REPLACE(REPLACE(REPLACE(COALESCE(rut_reemplazo_normalizado, ''), '.', ''), '-', ''), ' ', '')) = ?",
                            [$rutReemplazoComparable]
                        )->orWhereHas('postulante.user', function ($u) use ($rutReemplazoComparable) {
                            $u->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rutReemplazoComparable]);
                        })->orWhereHas('contratoPostulante.user', function ($u) use ($rutReemplazoComparable) {
                            $u->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rutReemplazoComparable]);
                        });
                    }
                });
            })
            ->where(function ($q) use ($solicitud) {
                if ($solicitud->fecha_inicio) {
                    $q->whereDate('fecha_inicio', '<=', $solicitud->fecha_inicio)
                        ->orWhereDate('fecha_termino', '<=', $solicitud->fecha_inicio);
                } else {
                    $q->where('id', '<', $solicitud->id);
                }

                $q->orWhere('id', '<', $solicitud->id);
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    private function rutComparable(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
    }

    public function cerrarSolicitudDocente(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isFuncionarioSlep = method_exists($user, 'hasRole') ? $user->hasRole('funcionario_slep') : false;

        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep']), 403);

        if (!$isAdmin && !$isGdp && $isFuncionarioSlep) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        $solicitud->loadMissing(['funcionarioTitular']);

        abort_unless($solicitud->estado === 'aceptada', 403);
        abort_unless($this->estamentoFromEstatuto($solicitud->funcionarioTitular?->estatuto) === 'docente', 403);
        abort_unless(!$this->hasContratoTrabajoAsociado($solicitud), 403);
        abort_unless($this->resolucionDocenteCompleta($solicitud), 403);

        DB::transaction(function () use ($solicitud, $user) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            $s->loadMissing(['funcionarioTitular']);

            abort_unless($s->estado === 'aceptada', 403);
            abort_unless($this->estamentoFromEstatuto($s->funcionarioTitular?->estatuto) === 'docente', 403);
            abort_unless(!$this->hasContratoTrabajoAsociado($s), 403);
            abort_unless($this->resolucionDocenteCompleta($s), 403);

            $s->forceFill([
                'estado' => 'cerrado',
                'cerrado_por_user_id' => $user->id,
                'cerrado_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', 'Solicitud docente cerrada correctamente.');
    }

    public function slepGenerarResolucionDocente(Request $request, SolicitudReemplazo $solicitud, ResolucionDocenteDocxService $service)
    {
        $this->authorizeResolucionDocente($request, $solicitud);
        abort_unless($solicitud->estado === 'aceptada', 403);
        $solicitud->loadMissing(['funcionarioTitular', 'postulante.user']);
        abort_unless($this->estamentoFromEstatuto($solicitud->funcionarioTitular?->estatuto) === 'docente', 403);
        $path = $service->generateAndStore($solicitud);
        $solicitud->forceFill([
            'resolucion_docente_docx_path' => $path,
            'resolucion_docente_generada_por_user_id' => $request->user()->id,
            'resolucion_docente_generada_at' => now(),
            'resolucion_docente_firmada_pdf_path' => null,
            'resolucion_docente_firmada_subida_por_user_id' => null,
            'resolucion_docente_firmada_subida_at' => null,
            'resolucion_docente_notificada_por_user_id' => null,
            'resolucion_docente_notificada_at' => null,
        ])->save();
        return back()->with('status', 'Resolución docente generada. Descárgala, revísala y luego carga el PDF firmado.');
    }

    public function downloadResolucionDocente(SolicitudReemplazo $solicitud)
    {
        abort_if(empty($solicitud->resolucion_docente_docx_path), 404);
        abort_unless(Storage::disk('local')->exists($solicitud->resolucion_docente_docx_path), 404);
        return Storage::disk('local')->download($solicitud->resolucion_docente_docx_path, "RESOLUCION_DOCENTE_{$solicitud->numero_solicitud}.docx");
    }

    public function slepSubirResolucionDocenteFirmada(Request $request, SolicitudReemplazo $solicitud)
    {
        $this->authorizeResolucionDocente($request, $solicitud);
        $data = $request->validate(['resolucion_docente_firmada_pdf' => ['required', 'file', 'mimes:pdf', 'max:15360']]);
        $solicitud->loadMissing(['establecimiento', 'funcionarioTitular', 'areaDesempeno', 'postulante.user']);
        $recipients = $this->buildContratoFirmadoRecipients($solicitud);
        $hasEstab = collect($recipients)->contains(fn ($r) => $r['role'] === 'funcionario_estab');
        $hasPost = collect($recipients)->contains(fn ($r) => str_starts_with($r['role'], 'postulante_'));
        if (!$hasEstab || !$hasPost) return back()->withErrors(['resolucion_docente_firmada_pdf' => 'Se requiere un correo válido del establecimiento y del postulante.']);
        $dir = "resoluciones-docentes/solicitudes/{$solicitud->id}/firmadas";
        $path = $data['resolucion_docente_firmada_pdf']->storeAs($dir, "RESOLUCION_DOCENTE_FIRMADA_{$solicitud->numero_solicitud}_" . now()->format('Ymd_His') . '.pdf', 'local');
        $solicitud->forceFill(['resolucion_docente_firmada_pdf_path' => $path, 'resolucion_docente_firmada_subida_por_user_id' => $request->user()->id, 'resolucion_docente_firmada_subida_at' => now(), 'resolucion_docente_notificada_por_user_id' => null, 'resolucion_docente_notificada_at' => null])->save();
        try {
            foreach ($recipients as $recipient) NotificationAudit::sendMail($recipient['email'], new ResolucionDocenteFirmadaEnviada($solicitud->fresh(['establecimiento', 'funcionarioTitular', 'areaDesempeno', 'postulante.user']), $recipient['label']), ['event_key' => 'solicitud_reemplazo.resolucion_docente_firmada_enviada', 'description' => 'Envío de resolución docente firmada', 'subject' => "Resolución docente firmada reemplazo {$solicitud->numero_solicitud}", 'recipient_name' => $recipient['label'], 'related' => $solicitud]);
        } catch (\Throwable $e) { report($e); return back()->withErrors(['resolucion_docente_firmada_pdf' => 'El PDF se cargó, pero falló la notificación. Puedes reintentar el envío.']); }
        $solicitud->forceFill(['resolucion_docente_notificada_por_user_id' => $request->user()->id, 'resolucion_docente_notificada_at' => now()])->save();
        return back()->with('status', $solicitud->estado === 'aceptada'
            ? 'Resolución docente firmada cargada y notificada. Ahora puedes cerrar la solicitud.'
            : 'Resolución docente firmada cargada y notificada para la solicitud cerrada.');
    }

    public function downloadResolucionDocenteFirmada(SolicitudReemplazo $solicitud)
    {
        abort_if(empty($solicitud->resolucion_docente_firmada_pdf_path), 404);
        abort_unless(Storage::disk('local')->exists($solicitud->resolucion_docente_firmada_pdf_path), 404);
        return response()->file(Storage::disk('local')->path($solicitud->resolucion_docente_firmada_pdf_path), ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="RESOLUCION_DOCENTE_FIRMADA.pdf"']);
    }

    private function authorizeResolucionDocente(Request $request, SolicitudReemplazo $solicitud): void
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep']), 403);
        if (!$user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin'])) abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        abort_unless(in_array((string) $solicitud->estado, ['aceptada', 'cerrado', 'cerrada'], true), 403);
        $solicitud->loadMissing('funcionarioTitular');
        abort_unless($this->estamentoFromEstatuto($solicitud->funcionarioTitular?->estatuto) === 'docente', 403);
    }

    private function resolucionDocenteCompleta(SolicitudReemplazo $s): bool
    {
        return !empty($s->resolucion_docente_firmada_pdf_path) && !empty($s->resolucion_docente_notificada_at) && Storage::disk('local')->exists($s->resolucion_docente_firmada_pdf_path);
    }



    /**
     * Devuelve una solicitud aceptada o cerrada a la etapa derivada_slep.
     * Elimina las referencias y archivos de Orden de Trabajo y Contrato para
     * reiniciar controladamente la gestión operativa desde GDP.
     */
    public function retornarDerivadaSlep(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        abort_unless(
            $user
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'coordinador_gdp']),
            403
        );

        if (!in_array((string) $solicitud->estado, ['aceptada', 'cerrado', 'cerrada'], true)) {
            return back()->withErrors([
                'retorno_derivada_slep_motivo' => 'Sólo se pueden devolver solicitudes aceptadas o cerradas.',
            ]);
        }

        if ($this->hasFiniquitoAsociado($solicitud)) {
            return back()->withErrors([
                'retorno_derivada_slep_motivo' => 'No es posible reiniciar el flujo porque la solicitud tiene un finiquito generado, firmado o pagado. Primero debe regularizarse ese antecedente.',
            ]);
        }

        $data = $request->validate([
            'retorno_derivada_slep_motivo' => ['required', 'string', 'min:10', 'max:5000'],
            'confirmar_reinicio_derivada_slep' => ['accepted'],
        ], [
            'retorno_derivada_slep_motivo.required' => 'Debe indicar el motivo administrativo del reinicio.',
            'retorno_derivada_slep_motivo.min' => 'El motivo administrativo debe tener al menos 10 caracteres.',
            'confirmar_reinicio_derivada_slep.accepted' => 'Debe confirmar que se eliminarán la Orden de Trabajo y el Contrato asociados.',
        ], [
            'retorno_derivada_slep_motivo' => 'motivo administrativo',
            'confirmar_reinicio_derivada_slep' => 'confirmación de reinicio',
        ]);

        $archivosAEliminar = [];
        $resultado = DB::transaction(function () use ($solicitud, $user, $data, &$archivosAEliminar) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            if (!in_array((string) $s->estado, ['aceptada', 'cerrado', 'cerrada'], true)) {
                abort(422, 'La solicitud cambió de estado y ya no puede devolverse a derivada_slep.');
            }

            if ($this->hasFiniquitoAsociado($s)) {
                abort(422, 'La solicitud tiene un finiquito asociado y no puede reiniciarse.');
            }

            $estadoOrigen = (string) $s->estado;
            $teniaOrdenTrabajo = !empty($s->orden_trabajo_pdf_path)
                || !empty($s->orden_trabajo_creada_at)
                || !empty($s->orden_trabajo_creada_por_user_id);
            $teniaContrato = $this->hasContratoTrabajoAsociado($s);

            foreach ([$s->orden_trabajo_pdf_path, $s->contrato_trabajo_docx_path, $s->contrato_trabajo_firmado_pdf_path] as $path) {
                $path = trim((string) $path);
                if ($path !== '') {
                    $archivosAEliminar[$path] = $path;
                }
            }

            $s->forceFill([
                'estado' => 'derivada_slep',
                'fecha_inicio_trabajo' => null,
                'orden_trabajo_pdf_path' => null,
                'orden_trabajo_creada_por_user_id' => null,
                'orden_trabajo_creada_at' => null,
                'contrato_trabajo_docx_path' => null,
                'contrato_trabajo_postulant_profile_id' => null,
                'contrato_trabajo_fecha_inicio_trabajo' => null,
                'contrato_trabajo_is_final' => false,
                'contrato_trabajo_subido_por_user_id' => null,
                'contrato_trabajo_subido_at' => null,
                'contrato_trabajo_firmado_pdf_path' => null,
                'contrato_trabajo_firmado_subido_por_user_id' => null,
                'contrato_trabajo_firmado_subido_at' => null,
                'contrato_trabajo_firmado_enviado_por_user_id' => null,
                'contrato_trabajo_firmado_enviado_at' => null,
                'cerrado_por_user_id' => null,
                'cerrado_at' => null,
                'aaee_categoria' => null,
            ])->save();

            $motivo = trim((string) $data['retorno_derivada_slep_motivo']);
            $documentosEliminados = [];
            if ($teniaOrdenTrabajo) {
                $documentosEliminados[] = 'Orden de Trabajo';
            }
            if ($teniaContrato) {
                $documentosEliminados[] = 'Contrato de Trabajo';
            }

            SolicitudReemplazoObservacion::create([
                'solicitud_reemplazo_id' => $s->id,
                'etapa' => 'gdp',
                'accion' => 'retorno_derivada_slep',
                'estado_origen' => $estadoOrigen,
                'estado_destino' => 'derivada_slep',
                'motivo' => $motivo,
                'observacion' => $motivo . (!empty($documentosEliminados)
                    ? "\nDocumentos reiniciados: " . implode(', ', $documentosEliminados) . '.'
                    : "\nLa solicitud no registraba archivos de Orden de Trabajo ni Contrato."),
                'user_id' => $user->id,
            ]);

            return [
                'estado_origen' => $estadoOrigen,
                'tenia_orden_trabajo' => $teniaOrdenTrabajo,
                'tenia_contrato' => $teniaContrato,
            ];
        });

        foreach ($archivosAEliminar as $path) {
            try {
                if (Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $detalle = [];
        if (!empty($resultado['tenia_orden_trabajo'])) {
            $detalle[] = 'Orden de Trabajo eliminada';
        }
        if (!empty($resultado['tenia_contrato'])) {
            $detalle[] = 'Contrato asociado eliminado';
        }

        $mensaje = 'Solicitud devuelta a la etapa Derivada SLEP.';
        if (!empty($detalle)) {
            $mensaje .= ' ' . implode(' y ', $detalle) . '.';
        }

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', $mensaje);
    }

    public function actualizarJornadaReemplazo(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless($this->canAdjustReplacementSchedule($user, $solicitud), 403);

        $solicitud->loadMissing(['funcionarioTitular', 'jornadas']);

        $rows = $solicitud->jornadas->sortBy('id')->values();
        abort_if($rows->isEmpty(), 422, 'La solicitud no tiene distribución de jornada disponible para ajustar.');

        $titularEsDocente = $this->estamentoFromEstatuto($solicitud->funcionarioTitular?->estatuto) === 'docente';

        $rules = [
            'jornadas' => ['required', 'array'],
            'reemplazo_ajuste_observacion' => ['required', 'string', 'min:5', 'max:2000'],
        ];

        $rules['horas_aula_cronologicas_reemplazo'] = [$titularEsDocente ? 'required' : 'nullable', 'numeric', 'min:0'];
        $rules['horas_aula_pedagogicas_reemplazo'] = [$titularEsDocente ? 'required' : 'nullable', 'numeric', 'min:0'];

        $data = $request->validate($rules, [
            'reemplazo_ajuste_observacion.required' => 'Debe indicar la observación del ajuste realizado.',
            'reemplazo_ajuste_observacion.min' => 'La observación del ajuste debe tener al menos 5 caracteres.',
        ]);

        $jornadasIn = (array) ($data['jornadas'] ?? []);
        $nuevoTotal = 0.0;
        $updates = [];

        foreach ($rows as $row) {
            $fin = (string) $row->financiamiento;
            $k2 = $this->sanitizeFinKey($fin);
            $basicaRaw = $jornadasIn[$fin]['basica'] ?? $jornadasIn[$k2]['basica'] ?? null;
            $mediaRaw = $jornadasIn[$fin]['media'] ?? $jornadasIn[$k2]['media'] ?? null;

            if ($basicaRaw === null || $mediaRaw === null || !is_numeric($basicaRaw) || !is_numeric($mediaRaw)) {
                return back()->withErrors(['jornadas' => "Debe completar la jornada del reemplazo para {$fin}."])->withInput();
            }

            $basica = round((float) $basicaRaw, 2);
            $media = round((float) $mediaRaw, 2);

            if ($basica < 0 || $media < 0) {
                return back()->withErrors(['jornadas' => "En {$fin}: las horas del reemplazo no pueden ser negativas."])->withInput();
            }

            $total = round($basica + $media, 2);
            $nuevoTotal = round($nuevoTotal + $total, 2);
            $updates[] = [
                'model' => $row,
                'reemplazo_basica' => $basica,
                'reemplazo_media' => $media,
                'reemplazo_total' => $total,
            ];
        }

        if ($nuevoTotal > self::MAX_REPLACEMENT_WEEKLY_HOURS) {
            return back()
                ->withErrors([
                    'jornadas' => 'La distribución completa de la jornada del reemplazo no puede superar las 44 horas semanales.',
                ])
                ->withInput();
        }

        $horasCron = 0.0;
        $horasPed = 0.0;
        if ($titularEsDocente) {
            $horasCron = (float) $request->input('horas_aula_cronologicas_reemplazo', 0);
            $horasPed = (float) $request->input('horas_aula_pedagogicas_reemplazo', 0);

        }

        DB::transaction(function () use ($updates, $solicitud, $user, $horasCron, $horasPed, $titularEsDocente, $data) {
            foreach ($updates as $update) {
                $update['model']->update([
                    'reemplazo_basica' => $update['reemplazo_basica'],
                    'reemplazo_media' => $update['reemplazo_media'],
                    'reemplazo_total' => $update['reemplazo_total'],
                ]);
            }

            $solicitud->update([
                'horas_aula_cronologicas_reemplazo' => $titularEsDocente ? $horasCron : 0,
                'horas_aula_pedagogicas_reemplazo' => $titularEsDocente ? $horasPed : 0,
                'reemplazo_ajuste_observacion' => trim((string) $data['reemplazo_ajuste_observacion']),
                'reemplazo_ajuste_user_id' => $user->id,
                'reemplazo_ajuste_role' => $this->resolveReplacementAdjustmentRole($user, $solicitud),
                'reemplazo_ajuste_at' => now(),
            ]);
        });

        return back()->with('status', 'Jornada del reemplazo actualizada correctamente.');
    }

    public function informarObservacion(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);
        abort_unless($solicitud->estado === 'derivada_slep', 403);

        $data = $request->validate([
            'observacion_slep' => ['required', 'string', 'max:5000'],
        ]);

        $solicitud->forceFill([
            'observacion_slep' => trim((string) $data['observacion_slep']),
            'observacion_slep_user_id' => $user->id,
            'observacion_slep_at' => now(),
        ])->save();

        if (!empty($solicitud->contacto_email)) {
            NotificationAudit::sendMail($solicitud->contacto_email, new SolicitudReemplazoObservacionSlep($solicitud->fresh()), [
                'event_key' => 'solicitud_reemplazo.observacion_slep',
                'description' => 'Notificación de observación SLEP informada',
                'subject' => "Solicitud de reemplazo {$solicitud->numero_solicitud} - Observación SLEP",
                'related' => $solicitud,
                'context' => ['numero_solicitud' => $solicitud->numero_solicitud],
            ]);
        }

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', 'Observación informada correctamente.');
    }

    public function registrarObservacionSolicitud(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_uatp', 'coordinador_gdp', 'coordinador_gdp_admin', 'supervisor_plani', 'funcionario_slep']), 403);
        abort_unless(in_array((string) $solicitud->estado, ['pendiente_uatp', 'rechazada_uatp', 'pendiente_validacion', 'rechazada_plani', 'pendiente_gdp', 'derivada_slep', 'aceptada'], true), 403);
        $allowed = $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin'])
            || ($user->hasRole('coordinador_uatp') && in_array((string) $solicitud->estado, ['pendiente_uatp', 'rechazada_uatp'], true))
            || ($user->hasRole('supervisor_plani') && in_array((string) $solicitud->estado, ['pendiente_validacion', 'rechazada_plani'], true))
            || ($user->hasRole('funcionario_slep') && in_array((string) $solicitud->estado, ['derivada_slep', 'aceptada'], true) && (int) $solicitud->derivada_a_user_id === (int) $user->id);
        abort_unless($allowed, 403);
        $data = $request->validate(['observacion' => ['required', 'string', 'min:3', 'max:5000']], [], ['observacion' => 'observación o gestión realizada']);
        SolicitudReemplazoObservacion::create([
            'solicitud_reemplazo_id' => $solicitud->id,
            'etapa' => 'solicitud', 'accion' => 'observacion_gestion',
            'estado_origen' => $solicitud->estado, 'estado_destino' => $solicitud->estado,
            'observacion' => trim((string) $data['observacion']), 'user_id' => $user->id,
        ]);
        return back()->with('status', 'Observación de la solicitud registrada correctamente.');
    }

    public function derivarCasoGestion(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasRole') && $user->hasRole('funcionario_slep'), 403);
        abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        abort_unless($solicitud->estado === 'derivada_slep' && !$this->hasOrdenOrContrato($solicitud), 403);
        $data = $request->validate(['responsable_user_id' => ['required', 'integer', 'exists:users,id'], 'observacion' => ['required', 'string', 'min:3', 'max:5000']]);
        $responsable = User::query()->whereKey($data['responsable_user_id'])->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'coordinador_gdp', 'coordinador_gdp_admin']))->firstOrFail();
        SolicitudReemplazoObservacion::create([
            'solicitud_reemplazo_id' => $solicitud->id,
            'etapa' => 'solicitud', 'accion' => 'derivacion_gestion',
            'estado_origen' => $solicitud->estado, 'estado_destino' => $solicitud->estado,
            'observacion' => trim((string) $data['observacion']), 'user_id' => $user->id,
        ]);
        $solicitud->forceFill(['derivada_a_user_id' => $responsable->id])->save();
        return back()->with('status', 'Caso derivado para revisión y cierre.');
    }

    public function cerrarSolicitudSinOt(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin']), 403);
        abort_unless($solicitud->estado === 'derivada_slep' && !$this->hasOrdenOrContrato($solicitud), 403);
        $data = $request->validate(['observacion' => ['required', 'string', 'min:3', 'max:5000']], [], ['observacion' => 'observación de cierre']);
        DB::transaction(function () use ($solicitud, $user, $data) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
            abort_unless($s->estado === 'derivada_slep' && !$this->hasOrdenOrContrato($s), 403);
            SolicitudReemplazoObservacion::create([
                'solicitud_reemplazo_id' => $s->id,
                'etapa' => 'solicitud', 'accion' => 'cierre_sin_ot',
                'estado_origen' => $s->estado, 'estado_destino' => 'cerrado',
                'observacion' => trim((string) $data['observacion']), 'user_id' => $user->id,
            ]);
            $s->forceFill(['estado' => 'cerrado', 'cerrado_por_user_id' => $user->id, 'cerrado_at' => now()])->save();
        });
        return back()->with('status', 'Solicitud cerrada con observación administrativa, sin Orden de Trabajo.');
    }

    /**
     * AJAX: Buscar postulantes para asignar a la Orden de Trabajo cuando la solicitud NO viene con propuesta.
     * Replican el selector "Postulante propuesto" (funcionario_estab):
     * - Render bonito (docs X/Y + %)
     * - Agrupar: ✅ Cumplen (100%) / ⚠️ Conflicto de periodo / ⛔ Faltan documentos
     * - Caso especial EP (slug educadora_de_parvulos): solo EP, sin filtrar por estamento
     * - No EP: filtrar por estamento, NO filtrar por área, ordenar priorizando misma área del titular
     */
    public function ajaxPostulantesOt(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isFuncionarioSlep = method_exists($user, 'hasRole') ? $user->hasRole('funcionario_slep') : false;

        $isAllowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
            : false;

        abort_unless($isAllowed, 403);
        abort_unless(in_array($solicitud->estado, ['derivada_slep', 'aceptada'], true), 403);

        // Funcionario SLEP: solo si está asignado (Admin/GDP no requieren asignación)
        if (!$isAdmin && !$isGdp && $isFuncionarioSlep) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        // Por defecto, este AJAX se usa para OT cuando NO hay propuesta.
        // También se reutiliza para reasignación (modo=reasignar) incluso si la solicitud venía con propuesta.
        $mode = (string) $request->query('mode', 'ot');
        if ($solicitud->propone_reemplazo && $mode !== 'reasignar') {
            return response()->json(['results' => []]);
        }

        $term = trim((string) $request->query('term', ''));
        $tokens = array_values(array_filter(preg_split('/\s+/', $term)));

        $solicitud->loadMissing(['funcionarioTitular', 'areaDesempeno']);

        $areaId = (int) ($solicitud->areaDesempeno?->id ?? 0);
        $isEpSelected = ($solicitud->areaDesempeno?->slug === 'educadora_de_parvulos');
        $epAreaIds = [];

        if ($isEpSelected) {
            $epAreaIds = \App\Models\AreaDesempeno::query()
                ->activos()
                ->where('slug', 'educadora_de_parvulos')
                ->pluck('id')
                ->map(fn($x) => (int) $x)
                ->all();
        }

        $expectedEstamento = null;
        if (!$isEpSelected) {
            $expectedEstamento = $this->estamentoFromEstatuto($solicitud->funcionarioTitular?->estatuto);
            if (!in_array($expectedEstamento, ['docente', 'asistente'], true)) {
                return response()->json(['results' => []]);
            }
        }

        // Referencia informativa: postulantes con solicitud aceptada que se cruza en el período efectivo.
        // Ya no bloquea por sí sola; el bloqueo se define por el total de horas del mismo período.
        $selectedInicioTrabajo = (string) $request->query('fecha_inicio_trabajo', '');
        $busyIds = [];
        $busyQuery = $this->buildAcceptedPeriodConflictQuery(
            SolicitudReemplazo::query(),
            $solicitud,
            $selectedInicioTrabajo !== '' ? $selectedInicioTrabajo : null
        );
        if ($busyQuery) {
            $busyIds = $busyQuery
                ->whereNotNull('postulant_profile_id')
                ->pluck('postulant_profile_id')
                ->map(fn($x) => (int) $x)
                ->all();
        }
        $busySet = array_fill_keys($busyIds, true);
        $requestedHours = $this->resolveSolicitudRequestedHours($solicitud);
        $hourBlockedSet = $this->buildMaxHoursBlockedSet(
            $solicitud,
            $selectedInicioTrabajo !== '' ? $selectedInicioTrabajo : null,
            $requestedHours
        );

        $q = User::query()
            ->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'])
            ->with([
                // Campos necesarios para reglas de documentos
                'postulantProfile:id,user_id,estamento,area_desempeno_id,cargos_funcion,genero,nivel_estudios,mencion',
                'postulantProfile.areaDesempeno:id,nombre,slug,estamento',
                'documents:id,user_id,document_type_id,path,status',
            ])
            ->whereHas('postulantProfile', function ($qq) use ($expectedEstamento, $isEpSelected, $epAreaIds) {
                if ($isEpSelected) {
                    // EP: solo área Educadora de Párvulos (sin filtrar por estamento)
                    if (!empty($epAreaIds)) {
                        $qq->whereIn('area_desempeno_id', $epAreaIds);
                    } else {
                        $qq->whereRaw('1=0');
                    }
                } else {
                    // Normal: filtrar por estamento
                    $qq->where('estamento', $expectedEstamento);
                }
            });

        if (method_exists(User::class, 'scopeRole')) {
            $q->role(['postulante', 'funcionario']);
        }

        if (app(RestrictedRutService::class)->isAvailable()) {
            $q->whereNotIn('rut', app(RestrictedRutService::class)->courtRestrictedRutsQuery());
        }

        foreach ($tokens as $tok) {
            $tokClean = strtoupper(preg_replace('/[^0-9K]/', '', $tok));
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

        $types = \App\Models\DocumentType::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $cvTypeId = (int) optional($types->firstWhere('slug', 'curriculum'))->id;

        $okItems = [];
        $conflictItems = [];
        $missingItems = [];

        foreach ($candidatos as $u) {
            if (!$u->postulantProfile) continue;

            $ppId = (int) $u->postulantProfile->id;

            $name = trim("{$u->nombres} {$u->apellido_paterno} {$u->apellido_materno}");
            $rutFmt = $this->formatRutChile($u->rut);
            $label = trim($name . ' ' . $rutFmt);
            $restriction = app(RestrictedRutService::class)->restrictionContextForUser($u);

            $required = \App\Support\DocumentRules::requiredTypesFromCatalog($u, $types);
            $requiredIds = $required->pluck('id')->all();

            $uploadedDocs = $u->documents
                ? $u->documents->whereIn('document_type_id', $requiredIds)
                : collect();

            $uploaded = (int) $uploadedDocs->count();

            $uploadedIdsSet = $uploadedDocs->pluck('document_type_id')->map(fn($x) => (int) $x)->flip();
            $missingDocs = $required
                ->filter(fn($t) => !isset($uploadedIdsSet[(int) $t->id]))
                ->pluck('label')
                ->values()
                ->all();

            $total = max(0, count($requiredIds));
            $percent = $total > 0 ? (int) round($uploaded * 100 / $total) : 100;
            $docsEligible = ($percent >= 100);

            $periodConflict = isset($busySet[$ppId]);
            $hourConflict = isset($hourBlockedSet[$ppId]);

            $item = [
                'id' => $ppId,
                'text' => $label,
                'label' => $label,
                'area' => (string) (optional($u->postulantProfile->areaDesempeno)->nombre ?? '—'),
                'area_desempeno_id' => (int) ($u->postulantProfile->area_desempeno_id ?? 0),
                'uploaded' => (int) $uploaded,
                'total_required' => (int) $total,
                'percent' => (int) $percent,
                'eligible' => $docsEligible,
                'missing_docs' => $missingDocs,
                'has_cv' => $cvTypeId > 0
                    ? (bool) ($u->documents?->firstWhere('document_type_id', $cvTypeId)?->path)
                    : false,
                'period_conflict' => (bool) $periodConflict,
                'conflict_reason' => $periodConflict ? 'Tiene solicitud aceptada en el mismo período' : null,
                'hour_conflict' => (bool) $hourConflict,
                'hour_conflict_reason' => $hourConflict ? 'Supera el máximo permitido de 44 horas considerando solicitudes vigentes.' : null,
                'manual_restriction' => (bool) ($restriction['manual_active'] ?? false),
                'manual_restriction_comment' => (string) ($restriction['manual_comment'] ?? ''),
                'manual_restriction_start' => $restriction['manual_start'] ?? null,
                'manual_restriction_end' => $restriction['manual_end'] ?? null,

                // Ordenamiento
                '_same_area' => ($areaId > 0 && (int) ($u->postulantProfile->area_desempeno_id ?? 0) === $areaId),
                '_area_sort' => mb_strtolower((string) (optional($u->postulantProfile->areaDesempeno)->nombre ?? ''), 'UTF-8'),
            ];

            // Disabled: si faltan docs o supera el máximo de horas.
            // El conflicto de período aceptado se informa, pero no bloquea por sí solo:
            // sólo se restringe cuando la suma total de jornadas del mismo período supera 44 horas.
            $item['disabled'] = (!$docsEligible) || $hourConflict;

            if (!$docsEligible) {
                $missingItems[] = $item;
            } elseif ($hourConflict) {
                $conflictItems[] = $item;
            } else {
                $okItems[] = $item;
            }
        }

        // -----------------------------
        // Ordenamiento
        // -----------------------------
        $cmpOk = function (array $a, array $b): int {
            $sa = !empty($a['_same_area']);
            $sb = !empty($b['_same_area']);
            if ($sa !== $sb) return $sa ? -1 : 1;
            $aa = (string)($a['_area_sort'] ?? '');
            $ab = (string)($b['_area_sort'] ?? '');
            if ($aa !== $ab) return $aa <=> $ab;
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        };

        $cmpConflict = $cmpOk;

        $cmpMissing = function (array $a, array $b): int {
            $sa = !empty($a['_same_area']);
            $sb = !empty($b['_same_area']);
            if ($sa !== $sb) return $sa ? -1 : 1;
            $aa = (string)($a['_area_sort'] ?? '');
            $ab = (string)($b['_area_sort'] ?? '');
            if ($aa !== $ab) return $aa <=> $ab;
            $pa = (int)($a['percent'] ?? 0);
            $pb = (int)($b['percent'] ?? 0);
            if ($pa !== $pb) return $pb <=> $pa;
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        };

        usort($okItems, $cmpOk);
        usort($conflictItems, $cmpConflict);
        usort($missingItems, $cmpMissing);

        foreach ($okItems as &$it) { unset($it['_same_area'], $it['_area_sort']); }
        unset($it);
        foreach ($conflictItems as &$it) { unset($it['_same_area'], $it['_area_sort']); }
        unset($it);
        foreach ($missingItems as &$it) { unset($it['_same_area'], $it['_area_sort']); }
        unset($it);

        // Limitar a 60, priorizando: OK -> Conflicto -> Faltan
        $max = 60;
        $okItems = array_slice($okItems, 0, $max);
        $remaining = $max - count($okItems);

        $conflictItems = array_slice($conflictItems, 0, max(0, $remaining));
        $remaining -= count($conflictItems);

        $missingItems = array_slice($missingItems, 0, max(0, $remaining));

        $groups = [];
        $hourConflictItems = array_values(array_filter($conflictItems, fn (array $it) => !empty($it['hour_conflict'])));
        if (!empty($okItems)) {
            $groups[] = ['text' => '✅ Cumplen (100%)', 'children' => $okItems];
        }
        if (!empty($hourConflictItems)) {
            $groups[] = ['text' => '⛔ Supera 44 horas vigentes (no seleccionable)', 'children' => $hourConflictItems];
        }
        if (!empty($missingItems)) {
            $groups[] = ['text' => '⛔ Faltan documentos (no seleccionable)', 'children' => $missingItems];
        }

        return response()->json(['results' => $groups]);
    }


    public function slepCrearOrdenTrabajo(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isAllowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
            : false;

        abort_unless($isAllowed, 403);
        abort_unless($solicitud->estado === 'derivada_slep', 403);

        // Restricción de asignación solo para funcionario_slep (Admin/GDP no requieren estar asignados)
        if (!$isAdmin && !$isGdp) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        if ($this->deudaPensionBloqueaFlujo($solicitud)) {
            return back()->withErrors([
                'deuda_pension' => 'La solicitud tiene una deuda de pensión de alimentos activa. Debes completar el expediente y enviarlo a Remuneraciones antes de generar la Orden de Trabajo.',
            ])->withInput();
        }

        $inicioMin = $solicitud->fecha_inicio?->toDateString();
        $inicioMax = $solicitud->fecha_termino?->toDateString();

        $rules = [
            'fecha_inicio_trabajo' => ['required', 'date'],
        ];

        if ($inicioMin) {
            $rules['fecha_inicio_trabajo'][] = 'after_or_equal:' . $inicioMin;
        }
        if ($inicioMax) {
            $rules['fecha_inicio_trabajo'][] = 'before_or_equal:' . $inicioMax;
        }

        if (!$solicitud->propone_reemplazo) {
            $rules['postulant_profile_id'] = ['required', 'integer', 'exists:postulant_profiles,id'];
        }

        $data = $request->validate($rules, [], [
            'fecha_inicio_trabajo' => 'fecha de inicio de trabajo',
            'postulant_profile_id' => 'postulante',
        ]);

        // Determinar postulante definitivo
        $postulantProfileId = (int) ($solicitud->postulant_profile_id ?? 0);
        if (!$solicitud->propone_reemplazo) {
            $postulantProfileId = (int) $data['postulant_profile_id'];
        }
        if ($postulantProfileId <= 0) {
            return back()->withErrors(['postulant_profile_id' => 'No hay postulante asociado a la solicitud.'])->withInput();
        }

        // Validación de compatibilidad (área + estamento del titular)
        $solicitud->loadMissing(['funcionarioTitular', 'areaDesempeno']);
        $tit = $solicitud->funcionarioTitular;
        $expectedEstamento = $this->estamentoFromEstatuto($tit?->estatuto);
        if (!$expectedEstamento) {
            return back()->withErrors(['postulant_profile_id' => 'No se pudo determinar el estamento del funcionario titular (estatuto).'])->withInput();
        }

        $pp = PostulantProfile::query()->with('user')->find($postulantProfileId);
        if (!$pp) {
            return back()->withErrors(['postulant_profile_id' => 'Postulante no encontrado.'])->withInput();
        }
        if (app(RestrictedRutService::class)->hasCourtRestrictionPostulantProfile($pp)) {
            return back()->withErrors(['postulant_profile_id' => 'El postulante seleccionado mantiene una restricción judicial vigente para ejercer y no puede ser asignado.'])->withInput();
        }

        $isEpSelected = ($solicitud->areaDesempeno?->slug === 'educadora_de_parvulos');
        $epAreaIds = [];

        if ($isEpSelected) {
            $epAreaIds = \App\Models\AreaDesempeno::query()
                ->activos()
                ->where('slug', 'educadora_de_parvulos')
                ->pluck('id')
                ->map(fn($x) => (int) $x)
                ->all();
        }

        $isOk = ((string) $pp->estamento === (string) $expectedEstamento)
            || ($isEpSelected && !empty($epAreaIds) && in_array((int) $pp->area_desempeno_id, $epAreaIds, true));

        if (!$isOk) {
            return back()->withErrors([
                'postulant_profile_id' => 'El postulante seleccionado no cumple con el área de desempeño y/o estamento requerido.',
            ])->withInput();
        }

        // Disponibilidad: no debe tener otra solicitud aceptada en el mismo período efectivo
        // Se permite coincidencia de período mientras la suma de jornadas aceptadas
        // del mismo período, más la solicitud actual, no supere 44 horas.
        $requestedHours = $this->resolveSolicitudRequestedHours($solicitud);
        if ($this->wouldExceedMaxActiveHours($postulantProfileId, $solicitud, (string) ($data['fecha_inicio_trabajo'] ?? null), $requestedHours)) {
            return back()->withErrors([
                'postulant_profile_id' => 'El postulante supera el máximo permitido de 44 horas considerando sus solicitudes vigentes.',
            ])->withInput();
        }

        DB::transaction(function () use ($solicitud, $user, $postulantProfileId, $data) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            abort_unless($s->estado === 'derivada_slep', 403);

            if ($this->deudaPensionBloqueaFlujo($s)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'deuda_pension' => 'La solicitud continúa bloqueada por deuda de pensión de alimentos pendiente de envío a Remuneraciones.',
                ]);
            }

            // Si no venía con propuesta, asignamos el postulante seleccionado
            if (!$s->propone_reemplazo) {
                $s->postulant_profile_id = $postulantProfileId;
            }

            $s->fecha_inicio_trabajo = Carbon::parse($data['fecha_inicio_trabajo'])->toDateString();
            $s->orden_trabajo_creada_por_user_id = $user->id;
            $s->orden_trabajo_creada_at = now();

            // Persistimos antes de generar la OT para asegurar que el PDF lea los datos actuales
            // (postulante, fecha inicio trabajo y funcionario que la elaboró).
            $s->save();

            $pdfService = app(\App\Services\OrdenTrabajoPdfService::class);
            $path = $pdfService->generateAndStore($s);

            $s->orden_trabajo_pdf_path = $path;
            $s->estado = 'aceptada';

            $s->save();
        });

        // Notificar al funcionario establecimiento
        $solicitud->refresh();
        if (!empty($solicitud->contacto_email)) {
            NotificationAudit::sendMail($solicitud->contacto_email, new OrdenTrabajoCreada($solicitud), [
                'event_key' => 'solicitud_reemplazo.orden_trabajo_creada',
                'description' => 'Notificación de orden de trabajo creada',
                'subject' => "Orden de trabajo creada — Solicitud {$solicitud->numero_solicitud}",
                'related' => $solicitud,
                'context' => ['numero_solicitud' => $solicitud->numero_solicitud],
            ]);
        }

        foreach ($this->buildOrdenTrabajoReemplazanteRecipients($solicitud) as $recipient) {
            NotificationAudit::sendMail($recipient['email'], new \App\Mail\OrdenTrabajoGenerada($solicitud->fresh([
                'establecimiento',
                'funcionarioTitular',
                'areaDesempeno',
                'postulante.user',
                'jornadas',
            ])), [
                'event_key' => 'solicitud_reemplazo.orden_trabajo_notificada_reemplazante',
                'description' => 'Notificación de orden de trabajo al reemplazante',
                'subject' => "Orden de Trabajo generada - Solicitud #{$solicitud->numero_solicitud}",
                'recipient_name' => $recipient['label'],
                'related' => $solicitud,
                'context' => [
                    'numero_solicitud' => $solicitud->numero_solicitud,
                    'recipient_role' => $recipient['role'],
                ],
            ]);
        }

        $soporteTiCc = [];
        if (!empty($solicitud->contacto_email)) {
            $soporteTiCc[] = $solicitud->contacto_email;
        }

        NotificationAudit::sendMail('ti@slepandaliencosta.gob.cl', new OrdenTrabajoCorreoInstitucionalSoporteTi($solicitud->fresh([
            'establecimiento',
            'funcionarioTitular',
            'areaDesempeno',
            'postulante.user',
            'jornadas',
        ])), [
            'event_key' => 'solicitud_reemplazo.orden_trabajo_soporte_ti_correo_institucional',
            'description' => 'Solicitud a Soporte TI para creación de correo institucional',
            'subject' => "Solicitud de creación de correo institucional — OT {$solicitud->numero_solicitud}",
            'recipient_name' => 'Soporte TI',
            'related' => $solicitud,
            'cc' => $soporteTiCc,
            'context' => [
                'numero_solicitud' => $solicitud->numero_solicitud,
                'cc_establecimiento' => $soporteTiCc,
            ],
        ]);

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', "Orden de trabajo creada para la solicitud {$solicitud->numero_solicitud}.");
    }

    /**
     * SLEP: Anular solicitud con motivo obligatorio.
     * - derivada_slep: bloquea si ya existe Orden de Trabajo y/o Contrato asociado.
     * - aceptada: permite anular para corrección operativa.
     */
    public function slepAnular(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isAllowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
            : false;

        abort_unless($isAllowed, 403);
        abort_unless(in_array($solicitud->estado, ['derivada_slep', 'aceptada'], true), 403);

        // Restricción de asignación solo para funcionario_slep (Admin/GDP no requieren estar asignados)
        if (!$isAdmin && !$isGdp) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        $data = $request->validate([
            'anulada_motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'anulada_motivo' => 'motivo de anulación',
        ]);

        // En derivada_slep se mantiene el bloqueo si ya existe OT/Contrato.
        if ($solicitud->estado === 'derivada_slep' && $this->hasOrdenOrContrato($solicitud)) {
            return back()->withErrors(['anulada_motivo' => 'No es posible anular: la solicitud ya tiene Orden y/o Contrato generado.']);
        }

        DB::transaction(function () use ($solicitud, $user, $data) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            abort_unless(in_array($s->estado, ['derivada_slep', 'aceptada'], true), 403);
            if ($s->estado === 'derivada_slep') {
                abort_unless(!$this->hasOrdenOrContrato($s), 403);
            }

            $s->estado = 'anulada';
            $s->anulada_motivo = trim((string) $data['anulada_motivo']);
            $s->anulada_by = (int) $user->id;
            $s->anulada_at = now();
            $s->save();
        });

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', "Solicitud {$solicitud->numero_solicitud} anulada.");
    }

    /**
     * SLEP: Reasignar postulante (cambiar postulant_profile_id) con motivo obligatorio.
     * - derivada_slep: bloquea si ya existe Orden y/o Contrato generado.
     * - aceptada: permite reasignar; si existe OT se regenera y si existe contrato se limpia para evitar inconsistencias.
     */
    public function slepReasignarPostulante(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isAllowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
            : false;

        abort_unless($isAllowed, 403);
        abort_unless(in_array($solicitud->estado, ['derivada_slep', 'aceptada'], true), 403);

        // Restricción de asignación solo para funcionario_slep (Admin/GDP no requieren estar asignados)
        if (!$isAdmin && !$isGdp) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        // En derivada_slep se mantiene el bloqueo si ya existe OT/Contrato.
        if ($solicitud->estado === 'derivada_slep' && $this->hasOrdenOrContrato($solicitud)) {
            return back()->withErrors(['reasignacion_postulante_motivo' => 'No es posible reasignar: la solicitud ya tiene Orden y/o Contrato generado.']);
        }

        $data = $request->validate([
            'postulant_profile_id' => ['required', 'integer', 'exists:postulant_profiles,id'],
            'reasignacion_postulante_motivo' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'postulant_profile_id' => 'postulante',
            'reasignacion_postulante_motivo' => 'motivo de reasignación',
        ]);

        // Validar compatibilidad y disponibilidad usando la misma lógica que OT
        $postulantProfileId = (int) $data['postulant_profile_id'];

        $solicitud->loadMissing(['funcionarioTitular', 'areaDesempeno']);
        $tit = $solicitud->funcionarioTitular;
        $expectedEstamento = $this->estamentoFromEstatuto($tit?->estatuto);
        if (!$expectedEstamento) {
            return back()->withErrors(['postulant_profile_id' => 'No se pudo determinar el estamento del funcionario titular (estatuto).'])->withInput();
        }

        $pp = PostulantProfile::query()->with('user')->find($postulantProfileId);
        if (!$pp) {
            return back()->withErrors(['postulant_profile_id' => 'Postulante no encontrado.'])->withInput();
        }
        if (app(RestrictedRutService::class)->hasCourtRestrictionPostulantProfile($pp)) {
            return back()->withErrors(['postulant_profile_id' => 'El postulante seleccionado mantiene una restricción judicial vigente para ejercer y no puede ser reasignado.'])->withInput();
        }

        $isEpSelected = ($solicitud->areaDesempeno?->slug === 'educadora_de_parvulos');
        $epAreaIds = [];
        if ($isEpSelected) {
            $epAreaIds = \App\Models\AreaDesempeno::query()
                ->activos()
                ->where('slug', 'educadora_de_parvulos')
                ->pluck('id')
                ->map(fn($x) => (int) $x)
                ->all();
        }

        $isOk = ((string) $pp->estamento === (string) $expectedEstamento)
            || ($isEpSelected && !empty($epAreaIds) && in_array((int) $pp->area_desempeno_id, $epAreaIds, true));

        if (!$isOk) {
            return back()->withErrors([
                'postulant_profile_id' => 'El postulante seleccionado no cumple con el área de desempeño y/o estamento requerido.',
            ])->withInput();
        }

        // Se permite coincidencia de período mientras la suma de jornadas aceptadas
        // del mismo período, más la solicitud actual, no supere 44 horas.
        $requestedHours = $this->resolveSolicitudRequestedHours($solicitud);
        if ($this->wouldExceedMaxActiveHours($postulantProfileId, $solicitud, (string) ($data['fecha_inicio_trabajo'] ?? null), $requestedHours)) {
            return back()->withErrors([
                'postulant_profile_id' => 'El postulante supera el máximo permitido de 44 horas considerando sus solicitudes vigentes.',
            ])->withInput();
        }

        $result = DB::transaction(function () use ($solicitud, $user, $postulantProfileId, $data) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            abort_unless(in_array($s->estado, ['derivada_slep', 'aceptada'], true), 403);
            if ($s->estado === 'derivada_slep') {
                abort_unless(!$this->hasOrdenOrContrato($s), 403);
            }

            $hadAcceptedOt = $s->estado === 'aceptada' && (!empty($s->orden_trabajo_pdf_path) || !empty($s->orden_trabajo_creada_at));
            $hadContrato = !empty($s->contrato_trabajo_docx_path)
                || !empty($s->contrato_trabajo_postulant_profile_id)
                || !empty($s->contrato_trabajo_subido_at)
                || !empty($s->contrato_trabajo_subido_por_user_id)
                || !empty($s->contrato_trabajo_fecha_inicio_trabajo)
                || !empty($s->contrato_trabajo_firmado_pdf_path)
                || !empty($s->contrato_trabajo_firmado_subido_at)
                || !empty($s->contrato_trabajo_firmado_enviado_at);

            $prev = (int) ($s->postulant_profile_id ?? 0);
            $s->reasignacion_postulante_from = $prev > 0 ? $prev : null;
            $s->postulant_profile_id = $postulantProfileId;
            $s->reasignacion_postulante_motivo = trim((string) $data['reasignacion_postulante_motivo']);
            $s->reasignacion_postulante_by = (int) $user->id;
            $s->reasignacion_postulante_at = now();

            if ($hadContrato) {
                $s->contrato_trabajo_docx_path = null;
                $s->contrato_trabajo_postulant_profile_id = null;
                $s->contrato_trabajo_fecha_inicio_trabajo = null;
                $s->contrato_trabajo_is_final = false;
                $s->contrato_trabajo_subido_por_user_id = null;
                $s->contrato_trabajo_subido_at = null;
            }

            $s->save();

            if ($hadAcceptedOt && !empty($s->fecha_inicio_trabajo)) {
                $pdfService = app(\App\Services\OrdenTrabajoPdfService::class);
                $path = $pdfService->generateAndStore($s);
                $s->orden_trabajo_pdf_path = $path;
                $s->save();
            }

            return [
                'hadAcceptedOt' => $hadAcceptedOt,
                'hadContrato' => $hadContrato,
            ];
        });

        $status = 'Postulante reasignado correctamente.';
        if (!empty($result['hadAcceptedOt'])) {
            $status .= ' Se regeneró la Orden de Trabajo';
            if (!empty($result['hadContrato'])) {
                $status .= ' y se limpió el contrato asociado para que puedas generarlo nuevamente';
            }
            $status .= '.';
        }

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', $status);
    }


    private function canAdjustReplacementSchedule($user, SolicitudReemplazo $solicitud): bool
    {
        if (!$user || !method_exists($user, 'hasAnyRole')) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return in_array($solicitud->estado, ['pendiente_uatp', 'pendiente_validacion'], true);
        }

        if ($solicitud->estado === 'pendiente_uatp' && $user->hasRole('coordinador_uatp')) {
            return true;
        }

        if ($solicitud->estado === 'pendiente_validacion' && $user->hasRole('supervisor_plani')) {
            return true;
        }

        return false;
    }

    private function sanitizeFinKey(string $fin): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/u', '_', $fin);
    }

    private function resolveReplacementAdjustmentRole($user, SolicitudReemplazo $solicitud): string
    {
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('coordinador_uatp')) {
                return 'coordinador_uatp';
            }
            if ($user->hasRole('supervisor_plani')) {
                return 'supervisor_plani';
            }
        }

        return $solicitud->estado === 'pendiente_validacion' ? 'supervisor_plani' : 'coordinador_uatp';
    }

    private function buildAcceptedPeriodConflictQuery($query, SolicitudReemplazo $solicitud, ?string $overrideInicioTrabajo = null)
    {
        $currentStart = $this->resolveEffectiveStartDate($solicitud, $overrideInicioTrabajo);
        $currentEnd = $this->resolveEffectiveEndDate($solicitud);

        if (!$currentStart || !$currentEnd) {
            return null;
        }

        return $query
            ->where('id', '<>', $solicitud->id)
            ->whereIn('estado', ['aceptada'])
            ->whereRaw('DATE(COALESCE(fecha_inicio_trabajo, fecha_inicio)) <= ?', [$currentEnd])
            ->whereDate('fecha_termino', '>=', $currentStart);
    }

    private function resolveEffectiveStartDate(SolicitudReemplazo $solicitud, ?string $overrideInicioTrabajo = null): ?string
    {
        if ($overrideInicioTrabajo !== null && trim($overrideInicioTrabajo) !== '') {
            return Carbon::parse($overrideInicioTrabajo)->toDateString();
        }

        if (!empty($solicitud->fecha_inicio_trabajo)) {
            return Carbon::parse($solicitud->fecha_inicio_trabajo)->toDateString();
        }

        if (!empty($solicitud->fecha_inicio)) {
            return Carbon::parse($solicitud->fecha_inicio)->toDateString();
        }

        return null;
    }

    private function resolveEffectiveEndDate(SolicitudReemplazo $solicitud): ?string
    {
        if (!empty($solicitud->fecha_termino)) {
            return Carbon::parse($solicitud->fecha_termino)->toDateString();
        }

        return null;
    }

    private function buildMaxHoursBlockedSet(SolicitudReemplazo $solicitud, ?string $overrideInicioTrabajo = null, float $requestedHours = 0): array
    {
        $startDate = $this->resolveEffectiveStartDate($solicitud, $overrideInicioTrabajo);
        $endDate = $this->resolveEffectiveEndDate($solicitud);

        if (!$startDate || !$endDate) {
            return [];
        }

        $rows = SolicitudReemplazo::query()
            ->select('solicitudes_reemplazo.id', 'solicitudes_reemplazo.postulant_profile_id')
            ->join('solicitud_reemplazo_jornadas as srj', 'srj.solicitud_reemplazo_id', '=', 'solicitudes_reemplazo.id')
            ->where('solicitudes_reemplazo.id', '<>', $solicitud->id)
            ->whereIn('solicitudes_reemplazo.estado', ['aceptada'])
            ->whereNotNull('solicitudes_reemplazo.postulant_profile_id')
            ->whereRaw('DATE(COALESCE(solicitudes_reemplazo.fecha_inicio_trabajo, solicitudes_reemplazo.fecha_inicio)) <= ?', [$endDate])
            ->whereDate('solicitudes_reemplazo.fecha_termino', '>=', $startDate)
            ->groupBy('solicitudes_reemplazo.id', 'solicitudes_reemplazo.postulant_profile_id')
            ->selectRaw('COALESCE(SUM(srj.reemplazo_total), 0) as total_horas')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $profileId = (int) ($row->postulant_profile_id ?? 0);
            if ($profileId <= 0) {
                continue;
            }
            $totals[$profileId] = round((float) ($totals[$profileId] ?? 0) + (float) ($row->total_horas ?? 0), 2);
        }

        $blocked = [];
        foreach ($totals as $profileId => $hours) {
            if (round($hours + max(0, $requestedHours), 2) > 44.0) {
                $blocked[(int) $profileId] = true;
            }
        }

        return $blocked;
    }

    private function wouldExceedMaxActiveHours(int $postulantProfileId, SolicitudReemplazo $solicitud, ?string $overrideInicioTrabajo = null, float $requestedHours = 0): bool
    {
        if ($postulantProfileId <= 0) {
            return false;
        }

        $startDate = $this->resolveEffectiveStartDate($solicitud, $overrideInicioTrabajo);
        $endDate = $this->resolveEffectiveEndDate($solicitud);
        if (!$startDate || !$endDate) {
            return false;
        }

        $activeHours = (float) SolicitudReemplazoJornada::query()
            ->join('solicitudes_reemplazo', 'solicitudes_reemplazo.id', '=', 'solicitud_reemplazo_jornadas.solicitud_reemplazo_id')
            ->where('solicitudes_reemplazo.postulant_profile_id', $postulantProfileId)
            ->where('solicitudes_reemplazo.id', '<>', $solicitud->id)
            ->whereIn('solicitudes_reemplazo.estado', ['aceptada'])
            ->whereRaw('DATE(COALESCE(solicitudes_reemplazo.fecha_inicio_trabajo, solicitudes_reemplazo.fecha_inicio)) <= ?', [$endDate])
            ->whereDate('solicitudes_reemplazo.fecha_termino', '>=', $startDate)
            ->sum('solicitud_reemplazo_jornadas.reemplazo_total');

        return round($activeHours + max(0, $requestedHours), 2) > 44.0;
    }

    private function resolveSolicitudRequestedHours(SolicitudReemplazo $solicitud): float
    {
        $hours = (float) ($solicitud->jornadas()->sum('reemplazo_total') ?? 0);
        return round(max(0, $hours), 2);
    }

    private function hasOrdenOrContrato(SolicitudReemplazo $s): bool
    {
        $hasOt = !empty($s->orden_trabajo_pdf_path) || !empty($s->orden_trabajo_creada_at) || !empty($s->orden_trabajo_creada_por_user_id);
        $hasContrato = $this->hasContratoTrabajoAsociado($s);

        return $hasOt || $hasContrato;
    }

    private function hasContratoTrabajoAsociado(SolicitudReemplazo $s): bool
    {
        return !empty($s->contrato_trabajo_docx_path)
            || !empty($s->contrato_trabajo_postulant_profile_id)
            || !empty($s->contrato_trabajo_fecha_inicio_trabajo)
            || !empty($s->contrato_trabajo_subido_at)
            || !empty($s->contrato_trabajo_subido_por_user_id)
            || !empty($s->contrato_trabajo_firmado_pdf_path)
            || !empty($s->contrato_trabajo_firmado_subido_at)
            || !empty($s->contrato_trabajo_firmado_enviado_at);
    }

    private function deudaPensionBloqueaFlujo(SolicitudReemplazo $solicitud): bool
    {
        $solicitud->loadMissing('deudaPension.postulante.user');
        $deuda = $solicitud->deudaPension;

        return $deuda
            && $deuda->estadoFlujo() !== SolicitudReemplazoDeudaPension::ESTADO_ENVIADO;
    }

    private function hasFiniquitoAsociado(SolicitudReemplazo $s): bool
    {
        return (bool) $s->finiquito_pagado
            || !empty($s->finiquito_estado)
            || !empty($s->finiquito_pdf_path)
            || !empty($s->finiquito_firmado_pdf_path)
            || !empty($s->finiquito_generado_at)
            || !empty($s->finiquito_firmado_cargado_at);
    }



    // =========================
    //  Contrato de Trabajo (AAEE) - Generar / Subir / Descargar
    // =========================

    public function slepGenerarContratoTrabajo(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isAllowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
            : false;

        abort_unless($isAllowed, 403);
        abort_unless($solicitud->estado === 'derivada_slep', 403);

        if (!$isAdmin && !$isGdp) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        if ($this->deudaPensionBloqueaFlujo($solicitud)) {
            return back()->withErrors([
                'deuda_pension' => 'La solicitud tiene una deuda de pensión de alimentos activa. Debes completar el expediente y enviarlo a Remuneraciones antes de generar el Contrato.',
            ])->withInput();
        }

        $solicitud->loadMissing(['funcionarioTitular', 'areaDesempeno', 'establecimiento', 'jornadas', 'postulante.user']);

        $tit = $solicitud->funcionarioTitular;
        $expectedEstamento = $this->estamentoFromEstatuto($tit?->estatuto);
        abort_unless($expectedEstamento === 'asistente', 403); // contrato Word: solo AAEE

        $inicioMin = $solicitud->fecha_inicio?->toDateString();
        $inicioMax = $solicitud->fecha_termino?->toDateString();

        $rules = [
            'fecha_inicio_trabajo' => ['required', 'date'],
        ];
        $rules['aaee_categoria'] = ['required', Rule::in(AaeeValorHora::categorias())];
        if ($inicioMin) $rules['fecha_inicio_trabajo'][] = 'after_or_equal:' . $inicioMin;
        if ($inicioMax) $rules['fecha_inicio_trabajo'][] = 'before_or_equal:' . $inicioMax;

        if (!$solicitud->propone_reemplazo) {
            $rules['postulant_profile_id'] = ['required', 'integer', 'exists:postulant_profiles,id'];
        }

        $data = $request->validate($rules, [], [
            'fecha_inicio_trabajo' => 'fecha de inicio de trabajo',
            'postulant_profile_id' => 'postulante',
            'aaee_categoria' => 'categoría AAEE',
        ]);

        // Determinar postulante
        $postulantProfileId = (int) ($solicitud->postulant_profile_id ?? 0);
        if (!$solicitud->propone_reemplazo) {
            $postulantProfileId = (int) $data['postulant_profile_id'];
        }
        if ($postulantProfileId <= 0) {
            return back()->withErrors(['postulant_profile_id' => 'No hay postulante asociado a la solicitud.'])->withInput();
        }

        // Validación compatibilidad (área + estamento)
        $pp = PostulantProfile::query()->with(['user', 'comuna'])->find($postulantProfileId);
        if (!$pp) {
            return back()->withErrors(['postulant_profile_id' => 'Postulante no encontrado.'])->withInput();
        }


        $isEpSelected = ($solicitud->areaDesempeno?->slug === 'educadora_de_parvulos');
        $epAreaIds = [];

        if ($isEpSelected) {
            $epAreaIds = \App\Models\AreaDesempeno::query()
                ->activos()
                ->where('slug', 'educadora_de_parvulos')
                ->pluck('id')
                ->map(fn($x) => (int) $x)
                ->all();
        }

        $isOk = ((string) $pp->estamento === (string) $expectedEstamento)
            || ($isEpSelected && !empty($epAreaIds) && in_array((int) $pp->area_desempeno_id, $epAreaIds, true));

        if (!$isOk) {
            return back()->withErrors([
                'postulant_profile_id' => 'El postulante seleccionado no cumple con el área de desempeño y/o estamento requerido.',
            ])->withInput();
        }

        // Disponibilidad
        // Se permite coincidencia de período mientras la suma de jornadas aceptadas
        // del mismo período, más la solicitud actual, no supere 44 horas.
        $requestedHours = $this->resolveSolicitudRequestedHours($solicitud);
        if ($this->wouldExceedMaxActiveHours($postulantProfileId, $solicitud, (string) ($data['fecha_inicio_trabajo'] ?? null), $requestedHours)) {
            return back()->withErrors([
                'postulant_profile_id' => 'El postulante supera el máximo permitido de 44 horas considerando sus solicitudes vigentes.',
            ])->withInput();
        }

        // Jornada total (horas)
        $jornadaHoras = 0;
        foreach (($solicitud->jornadas ?? []) as $j) {
            $jornadaHoras += (float) ($j->reemplazo_total ?? 0);
        }
        if ($jornadaHoras <= 0 && $tit?->jornada) {
            $jornadaHoras = (float) $tit->jornada;
        }
        $categoria = (string) ($data['aaee_categoria'] ?? '');

        // Valor hora AAEE: por área de desempeño + categoría
        $valorHora = AaeeValorHora::query()
            ->where('area_desempeno_id', $solicitud->area_desempeno_id)
            ->where('categoria', $categoria)
            ->where('activo', true)
            ->value('valor_hora');

        $monto = null;
        if ($valorHora !== null && $jornadaHoras > 0) {
            $monto = (float) $valorHora * (float) $jornadaHoras;
        }
        $montoRedondeado = $monto !== null ? (int) round($monto, 0, PHP_ROUND_HALF_UP) : null;

        $postUser = $pp->user;
        if ($postUser) {
            $postUser->loadMissing('communes');
        }

        $dias = null;
        if ($solicitud->fecha_inicio && $solicitud->fecha_termino) {
            $dias = $solicitud->fecha_inicio->diffInDays($solicitud->fecha_termino) + 1;
        }

        $values = [
            // Postulante (reemplazante)
            'Nombres' => $postUser?->nombres ?? '',
            'Apellidos' => trim(($postUser?->apellido_paterno ?? '') . ' ' . ($postUser?->apellido_materno ?? '')),
            'RUN' => $this->formatRutChile($postUser?->rut),
            'Fecha_de_Nacimiento' => optional($pp->fecha_nacimiento)->format('d/m/Y') ?? '',
            'Dirección_Particular' => $pp->direccion ?? '',
            // Comuna del postulante (FK postulant_profiles.comuna_id -> communes.name)
            'Comuna_postulante' => $pp->comuna?->name ?? '',

            // Solicitud / Titular
            'Establecimiento_En_El_Que_Se_Desempeña' => $solicitud->establecimiento?->nombre_establecimiento ?? $solicitud->establecimiento?->nombre ?? '',
            'Comuna_establecimiento' => $solicitud->establecimiento?->comuna ?? '',
            'Reemplaza_A' => $tit?->nombre ?? '',
            'días_de_licencia' => (string) ($dias ?? ''),
            'Área_De_Desempeño' => $solicitud->areaDesempeno?->nombre ?? '',
            'Estamento1' => $this->aaeeCategoriaLabel($solicitud->aaee_categoria),
            'JORNADA_horas' => rtrim(rtrim(number_format($jornadaHoras, 2, '.', ''), '0'), '.'),
            'Iniciales_funciona_slep_que_gestiona' => $this->inicialesUsuario($user),

            // Fechas
            'A_CONTAR_DE_' => Carbon::parse($data['fecha_inicio_trabajo'])->format('d/m/Y'),
            'FECHA_DE_TERMINO' => optional($solicitud->fecha_termino)->format('d/m/Y') ?? '',

            // Remuneración (si existe valor hora)
            'sueldo' => $montoRedondeado !== null ? number_format($montoRedondeado, 0, ',', '.') : '',
            'texto_sueldo' => $montoRedondeado !== null ? $this->numeroAPalabrasPesos($montoRedondeado) : '',
        ];

        $service = app(\App\Services\ContratoTrabajoDocxService::class);
        $path = $service->generateAndStore($solicitud, $values, now());

        $solicitud->contrato_trabajo_docx_path = $path;
        $solicitud->contrato_trabajo_postulant_profile_id = $postulantProfileId;
        $solicitud->contrato_trabajo_fecha_inicio_trabajo = Carbon::parse($data['fecha_inicio_trabajo'])->toDateString();
        $solicitud->contrato_trabajo_is_final = false;
        $solicitud->contrato_trabajo_subido_por_user_id = $user->id;
        $solicitud->contrato_trabajo_subido_at = now();
        $solicitud->aaee_categoria = ($categoria ?: null);
        $solicitud->save();

        return back()->with('status', 'Contrato generado. Descárgalo, edítalo y súbelo como versión final antes de crear la Orden de Trabajo.')->withInput();
    }

    public function slepSubirContratoTrabajo(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isAllowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
            : false;

        abort_unless($isAllowed, 403);
        abort_unless($solicitud->estado === 'derivada_slep', 403);

        if (!$isAdmin && !$isGdp) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        if ($this->deudaPensionBloqueaFlujo($solicitud)) {
            return back()->withErrors([
                'deuda_pension' => 'La solicitud tiene una deuda de pensión de alimentos activa. Debes enviar el expediente a Remuneraciones antes de cargar el Contrato final.',
            ])->withInput();
        }

        $solicitud->loadMissing(['funcionarioTitular']);
        $expectedEstamento = $this->estamentoFromEstatuto($solicitud->funcionarioTitular?->estatuto);
        abort_unless($expectedEstamento === 'asistente', 403);

        if (!$solicitud->contrato_trabajo_docx_path || !$solicitud->contrato_trabajo_postulant_profile_id || !$solicitud->contrato_trabajo_fecha_inicio_trabajo) {
            return back()->withErrors(['contrato_docx' => 'Primero debes generar el contrato (borrador) para esta solicitud.']);
        }

        $data = $request->validate([
            'contrato_docx' => ['required', 'file', 'mimes:docx,pdf', 'max:10240'],
        ]);

        $dir = "contratos-trabajo/solicitudes/{$solicitud->id}";
        Storage::disk('local')->makeDirectory($dir);

        $ext = strtolower($data['contrato_docx']->getClientOriginalExtension() ?: 'docx');
        if (!in_array($ext, ['docx', 'pdf'], true)) {
            $ext = 'docx';
        }

        $filename = "CONTRATO_TRABAJO_FINAL_{$solicitud->numero_solicitud}_" . now()->format('Ymd_His') . ".{$ext}";
        $path = $data['contrato_docx']->storeAs($dir, $filename, 'local');

        $solicitud->contrato_trabajo_docx_path = $path;
        $solicitud->contrato_trabajo_is_final = true;
        $solicitud->contrato_trabajo_subido_por_user_id = $user->id;
        $solicitud->contrato_trabajo_subido_at = now();
        $solicitud->save();

        return back()->with('status', 'Contrato subido como versión final. Ya puedes crear la Orden de Trabajo.')
            ->withInput([
                'postulant_profile_id' => $solicitud->contrato_trabajo_postulant_profile_id,
                'fecha_inicio_trabajo' => $solicitud->contrato_trabajo_fecha_inicio_trabajo,
                'aaee_categoria' => $solicitud->aaee_categoria,
            ]);
    }

    public function downloadContratoTrabajo(SolicitudReemplazo $solicitud)
    {
        abort_if(empty($solicitud->contrato_trabajo_docx_path), 404);

        $path = $solicitud->contrato_trabajo_docx_path;
        abort_if(!Storage::disk('local')->exists($path), 404);

        // El contrato puede ser .docx (generado) o .pdf (subido como versión final)
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Si el nombre no refleja la extensión real, intentamos inferir por MIME
        $mimeDetected = Storage::disk('local')->mimeType($path);
        if ($mimeDetected === 'application/pdf') {
            $ext = 'pdf';
        } elseif ($mimeDetected === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            $ext = 'docx';
        }

        if (!in_array($ext, ['docx', 'pdf'], true)) {
            $ext = 'docx';
        }

        $name = "CONTRATO_TRABAJO_{$solicitud->numero_solicitud}.{$ext}";
        $headers = [
            'Content-Type' => $ext === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        return Storage::disk('local')->download($path, $name, $headers);
    }

    public function slepEnviarContratoFirmado(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        $isAdmin = method_exists($user, 'hasRole') ? $user->hasRole('admin') : false;
        $isGdp = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['coordinador_gdp', 'coordinador_gdp_admin'])
            : false;
        $isAllowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['admin', 'coordinador_gdp', 'coordinador_gdp_admin', 'funcionario_slep'])
            : false;

        abort_unless($isAllowed, 403);
        abort_unless($solicitud->estado === 'aceptada', 403);

        if (!$isAdmin && !$isGdp) {
            abort_unless((int) $solicitud->derivada_a_user_id === (int) $user->id, 403);
        }

        if (empty($solicitud->contrato_trabajo_docx_path)) {
            return back()->withErrors([
                'contrato_firmado_pdf' => 'La solicitud todavía no registra contrato base para continuar con este flujo.',
            ]);
        }

        $data = $request->validate([
            'contrato_firmado_pdf' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ], [], [
            'contrato_firmado_pdf' => 'contrato firmado',
        ]);

        $solicitud->loadMissing(['establecimiento', 'funcionarioTitular', 'areaDesempeno', 'postulante.user']);

        $recipients = $this->buildContratoFirmadoRecipients($solicitud);
        if (empty($recipients)) {
            return back()->withErrors([
                'contrato_firmado_pdf' => 'No existen correos válidos para notificar al establecimiento y/o al postulante asignado.',
            ]);
        }

        $dir = "contratos-trabajo/solicitudes/{$solicitud->id}/firmados";
        Storage::disk('local')->makeDirectory($dir);

        $filename = "CONTRATO_TRABAJO_FIRMADO_{$solicitud->numero_solicitud}_" . now()->format('Ymd_His') . '.pdf';
        $path = $data['contrato_firmado_pdf']->storeAs($dir, $filename, 'local');

        $solicitud->forceFill([
            'contrato_trabajo_firmado_pdf_path' => $path,
            'contrato_trabajo_firmado_subido_por_user_id' => $user->id,
            'contrato_trabajo_firmado_subido_at' => now(),
        ])->save();

        try {
            foreach ($recipients as $recipient) {
                NotificationAudit::sendMail($recipient['email'], new ContratoTrabajoFirmadoEnviado($solicitud->fresh([
                    'establecimiento',
                    'funcionarioTitular',
                    'areaDesempeno',
                    'postulante.user',
                ]), $recipient['label']), [
                    'event_key' => 'solicitud_reemplazo.contrato_trabajo_firmado_enviado',
                    'description' => 'Envío de contrato firmado del reemplazo',
                    'subject' => "Contrato firmado reemplazo {$solicitud->numero_solicitud}",
                    'recipient_name' => $recipient['label'],
                    'related' => $solicitud,
                    'context' => [
                        'numero_solicitud' => $solicitud->numero_solicitud,
                        'recipient_role' => $recipient['role'],
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'contrato_firmado_pdf' => 'El contrato firmado se cargó, pero ocurrió un problema al notificar. La solicitud se mantiene abierta para reintentar el envío.',
            ]);
        }

        $solicitud->forceFill([
            'contrato_trabajo_firmado_enviado_por_user_id' => $user->id,
            'contrato_trabajo_firmado_enviado_at' => now(),
            'cerrado_por_user_id' => $user->id,
            'cerrado_at' => now(),
            'estado' => 'cerrado',
        ])->save();

        return back()->with('status', 'Contrato firmado cargado, notificado y solicitud cerrada correctamente.');
    }

    public function downloadContratoFirmado(SolicitudReemplazo $solicitud)
    {
        abort_if(empty($solicitud->contrato_trabajo_firmado_pdf_path), 404);

        $path = $solicitud->contrato_trabajo_firmado_pdf_path;
        abort_if(!Storage::disk('local')->exists($path), 404);

        $name = "CONTRATO_TRABAJO_FIRMADO_{$solicitud->numero_solicitud}.pdf";

        return Storage::disk('local')->download($path, $name, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function buildContratoFirmadoRecipients(SolicitudReemplazo $solicitud): array
    {
        $profile = $solicitud->postulante;
        $postUser = $profile?->user;

        $rows = [
            [
                'email' => $solicitud->contacto_email,
                'label' => $solicitud->contacto_nombre ?: 'Contacto establecimiento',
                'role' => 'funcionario_estab',
            ],
            [
                'email' => $postUser?->email,
                'label' => $postUser?->full_name ?: 'Postulante asignado',
                'role' => 'postulante_user',
            ],
            [
                'email' => $profile?->email_contacto,
                'label' => $postUser?->full_name ?: 'Postulante asignado',
                'role' => 'postulante_contacto',
            ],
        ];

        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $row['email'] = $email;
            $result[] = $row;
        }

        return $result;
    }

    private function buildOrdenTrabajoReemplazanteRecipients(SolicitudReemplazo $solicitud): array
    {
        $profile = $solicitud->postulante;
        $postUser = $profile?->user;

        $rows = [
            [
                'email' => $postUser?->email,
                'label' => $postUser?->full_name ?: 'Postulante asignado',
                'role' => 'postulante_user',
            ],
            [
                'email' => $profile?->email_contacto,
                'label' => $postUser?->full_name ?: 'Postulante asignado',
                'role' => 'postulante_contacto',
            ],
        ];

        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $row['email'] = $email;
            $result[] = $row;
        }

        return $result;
    }

    private function estamentoFromEstatuto(?string $estatuto): ?string
    {
        $e = strtoupper(trim((string) $estatuto));
        if ($e === '') return null;

        if (in_array($e, ['AAEE', 'A.A.E.E', 'ASISTENTE', 'ASISTENTE DE LA EDUCACION', 'ASISTENTE DE LA EDUCACIÓN'], true)) {
            return 'asistente';
        }

        if (in_array($e, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true)) {
            return 'docente';
        }

        if (str_contains($e, 'AAEE') || str_contains($e, 'ASIST')) return 'asistente';
        if (str_contains($e, 'DOC')) return 'docente';

        return null;
    }

    public function uatpAprobar(
        Request $request,
        SolicitudReemplazo $solicitud,
        SolicitudReemplazoAutorizacionDocenteService $autorizacionDocenteService
    ) {
        if ($solicitud->estado !== 'pendiente_uatp') {
            return back()->withErrors(['estado' => 'La solicitud no está en estado Pendiente UATP.']);
        }

        if (! $autorizacionDocenteService->cumpleRegistroParaAprobacionUatp($solicitud)) {
            return back()->withErrors([
                'autorizacion_docente' => 'Debe ingresar el número de registro de la autorización docente antes de aprobar y enviar la solicitud a Validación.',
            ]);
        }

        $data = $request->validate([
            'justificacion_tecnica_uatp' => ['required', 'string', 'min:10', 'max:5000'],
        ], [], [
            'justificacion_tecnica_uatp' => 'justificación técnica de la aprobación',
        ]);

        $solicitud->update([
            'estado' => 'pendiente_validacion',
            'motivo_rechazo' => null,
            'justificacion_tecnica_uatp' => trim((string) $data['justificacion_tecnica_uatp']),
            'plani_motivo_rechazo' => null,
            'plani_decision_user_id' => null,
            'plani_decision_at' => null,
            'uatp_decision_user_id' => $request->user()->id,
            'uatp_decision_at' => now(),
        ]);

        if (!empty($solicitud->contacto_email)) {
            NotificationAudit::sendMail($solicitud->contacto_email, new SolicitudReemplazoAprobadaUatpPendientePlani($solicitud->fresh()), [
                'event_key' => 'solicitud_reemplazo.aprobada_uatp_pendiente_plani',
                'description' => 'Notificación de aprobación UATP y validación Planificación',
                'subject' => "Solicitud de reemplazo {$solicitud->numero_solicitud} (Aprobada por UATP / Pendiente validación)",
                'related' => $solicitud,
                'context' => ['numero_solicitud' => $solicitud->numero_solicitud],
            ]);
        }

        return back()->with('status', "Solicitud {$solicitud->numero_solicitud} aprobada y enviada a validación de Planificación.");
    }

    public function uatpRechazar(Request $request, SolicitudReemplazo $solicitud)
    {
        if ($solicitud->estado !== 'pendiente_uatp') {
            return back()->withErrors(['estado' => 'La solicitud no está en estado Pendiente UATP.']);
        }

        $data = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'min:5', 'max:5000'],
        ], [], [
            'motivo_rechazo' => 'motivo de rechazo',
        ]);

        DB::transaction(function () use ($solicitud, $request, $data) {
            $estadoOrigen = (string) $solicitud->estado;

            $solicitud->update([
                'estado' => 'rechazada_uatp',
                'motivo_rechazo' => $data['motivo_rechazo'],
                'justificacion_tecnica_uatp' => null,
                'plani_motivo_rechazo' => null,
                'plani_decision_user_id' => null,
                'plani_decision_at' => null,
                'uatp_decision_user_id' => $request->user()->id,
                'uatp_decision_at' => now(),
                'devuelta_desde' => 'uatp',
                'retornar_a_etapa' => 'uatp',
                'ultima_observacion_rechazo' => trim((string) $data['motivo_rechazo']),
                'fecha_ultima_devolucion' => now(),
                'usuario_ultima_devolucion_id' => $request->user()->id,
            ]);

            SolicitudReemplazoObservacion::create([
                'solicitud_reemplazo_id' => $solicitud->id,
                'etapa' => 'uatp',
                'accion' => 'rechazo',
                'estado_origen' => $estadoOrigen,
                'estado_destino' => 'rechazada_uatp',
                'motivo' => trim((string) $data['motivo_rechazo']),
                'observacion' => trim((string) $data['motivo_rechazo']),
                'user_id' => $request->user()->id,
            ]);
        });

        if (!empty($solicitud->contacto_email)) {
            NotificationAudit::sendMail($solicitud->contacto_email, new SolicitudReemplazoRechazadaUatp($solicitud), [
                'event_key' => 'solicitud_reemplazo.rechazada_uatp',
                'description' => 'Notificación de rechazo UATP',
                'subject' => "Solicitud de reemplazo {$solicitud->numero_solicitud} (Rechazada por UATP)",
                'related' => $solicitud,
                'context' => ['numero_solicitud' => $solicitud->numero_solicitud],
            ]);
        }

        return back()->with('status', "Solicitud {$solicitud->numero_solicitud} rechazada.");
    }

    public function uatpReabrir(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        abort_unless(
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'funcionario_slep', 'supervisor_plani', 'coordinador_gdp']),
            403
        );

        if ($solicitud->estado !== 'rechazada_uatp') {
            return back()->withErrors(['estado' => 'Sólo se pueden reabrir solicitudes rechazadas por UATP.']);
        }

        $data = $request->validate([
            'uatp_reapertura_motivo' => ['required', 'string', 'min:10', 'max:5000'],
        ], [], [
            'uatp_reapertura_motivo' => 'motivo de reapertura administrativa',
        ]);

        DB::transaction(function () use ($solicitud, $user, $data) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            if ($s->estado !== 'rechazada_uatp') {
                abort(422, 'Sólo se pueden reabrir solicitudes rechazadas por UATP.');
            }

            $s->forceFill([
                'estado' => 'pendiente_uatp',
                'uatp_rechazo_reabierto_motivo' => $s->motivo_rechazo,
                'uatp_reapertura_motivo' => trim((string) $data['uatp_reapertura_motivo']),
                'uatp_reapertura_user_id' => $user->id,
                'uatp_reapertura_at' => now(),
                'motivo_rechazo' => null,
                'justificacion_tecnica_uatp' => null,
                'uatp_decision_user_id' => null,
                'uatp_decision_at' => null,
                'plani_motivo_rechazo' => null,
                'plani_decision_user_id' => null,
                'plani_decision_at' => null,
            ])->save();
        });

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', "Solicitud {$solicitud->numero_solicitud} reabierta administrativamente y devuelta a revisión UATP.");
    }

    public function planiValidar(Request $request, SolicitudReemplazo $solicitud)
    {
        if ($solicitud->estado !== 'pendiente_validacion') {
            return back()->withErrors(['estado' => 'La solicitud no está en estado Pendiente de Validación.']);
        }

        $solicitud->update([
            'estado' => 'pendiente_gdp',
            'plani_motivo_rechazo' => null,
            'plani_decision_user_id' => $request->user()->id,
            'plani_decision_at' => now(),
        ]);

        if (!empty($solicitud->contacto_email)) {
            NotificationAudit::sendMail($solicitud->contacto_email, new SolicitudReemplazoValidadaPlani($solicitud->fresh()), [
                'event_key' => 'solicitud_reemplazo.validada_plani',
                'description' => 'Notificación de validación Planificación',
                'subject' => "Solicitud de reemplazo {$solicitud->numero_solicitud} (Validada por Planificación)",
                'related' => $solicitud,
                'context' => ['numero_solicitud' => $solicitud->numero_solicitud],
            ]);
        }

        return back()->with('status', "Solicitud {$solicitud->numero_solicitud} validada por Planificación y enviada a GDP.");
    }

    public function planiRechazar(Request $request, SolicitudReemplazo $solicitud)
    {
        if ($solicitud->estado !== 'pendiente_validacion') {
            return back()->withErrors(['estado' => 'La solicitud no está en estado Pendiente de Validación.']);
        }

        $data = $request->validate([
            'plani_motivo_rechazo' => ['required', 'string', 'min:5', 'max:5000'],
        ], [], [
            'plani_motivo_rechazo' => 'motivo de rechazo de Planificación',
        ]);

        DB::transaction(function () use ($solicitud, $request, $data) {
            $estadoOrigen = (string) $solicitud->estado;
            $motivo = trim((string) $data['plani_motivo_rechazo']);

            $solicitud->update([
                'estado' => 'rechazada_plani',
                'plani_motivo_rechazo' => $motivo,
                'plani_decision_user_id' => $request->user()->id,
                'plani_decision_at' => now(),
                'devuelta_desde' => 'plani',
                'retornar_a_etapa' => 'plani',
                'ultima_observacion_rechazo' => $motivo,
                'fecha_ultima_devolucion' => now(),
                'usuario_ultima_devolucion_id' => $request->user()->id,
            ]);

            SolicitudReemplazoObservacion::create([
                'solicitud_reemplazo_id' => $solicitud->id,
                'etapa' => 'plani',
                'accion' => 'rechazo',
                'estado_origen' => $estadoOrigen,
                'estado_destino' => 'rechazada_plani',
                'motivo' => $motivo,
                'observacion' => $motivo,
                'user_id' => $request->user()->id,
            ]);
        });

        if (!empty($solicitud->contacto_email)) {
            NotificationAudit::sendMail($solicitud->contacto_email, new SolicitudReemplazoRechazadaPlani($solicitud->fresh()), [
                'event_key' => 'solicitud_reemplazo.rechazada_plani',
                'description' => 'Notificación de rechazo Planificación',
                'subject' => "Solicitud de reemplazo {$solicitud->numero_solicitud} (Rechazada por Planificación)",
                'related' => $solicitud,
                'context' => ['numero_solicitud' => $solicitud->numero_solicitud],
            ]);
        }

        return back()->with('status', "Solicitud {$solicitud->numero_solicitud} rechazada por Planificación.");
    }

    public function planiReabrir(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();

        abort_unless(
            method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'funcionario_slep', 'supervisor_plani', 'coordinador_gdp']),
            403
        );

        if ($solicitud->estado !== 'rechazada_plani') {
            return back()->withErrors(['estado' => 'Sólo se pueden reabrir solicitudes rechazadas por Planificación.']);
        }

        $data = $request->validate([
            'plani_reapertura_motivo' => ['required', 'string', 'min:10', 'max:5000'],
        ], [], [
            'plani_reapertura_motivo' => 'motivo de reapertura administrativa',
        ]);

        DB::transaction(function () use ($solicitud, $user, $data) {
            $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();

            if ($s->estado !== 'rechazada_plani') {
                abort(422, 'Sólo se pueden reabrir solicitudes rechazadas por Planificación.');
            }

            $s->forceFill([
                'estado' => 'pendiente_validacion',
                'plani_rechazo_reabierto_motivo' => $s->plani_motivo_rechazo,
                'plani_reapertura_motivo' => trim((string) $data['plani_reapertura_motivo']),
                'plani_reapertura_user_id' => $user->id,
                'plani_reapertura_at' => now(),
                'plani_motivo_rechazo' => null,
                'plani_decision_user_id' => null,
                'plani_decision_at' => null,
            ])->save();
        });

        return redirect()
            ->route('gestion.solicitudes-reemplazo.show', $solicitud)
            ->with('status', "Solicitud {$solicitud->numero_solicitud} reabierta administrativamente y devuelta a validación de Planificación.");
    }

    public function gdpDerivar(Request $request)
    {
        $data = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'distinct'],
            'derivaciones' => ['required', 'array'],
        ], [], [
            'selected' => 'solicitudes seleccionadas',
        ]);

        $selectedIds = $data['selected'];
        $derivaciones = $data['derivaciones']; // [solicitud_id => user_id]

        DB::transaction(function () use ($selectedIds, $derivaciones, $request) {
            $solicitudes = SolicitudReemplazo::whereIn('id', $selectedIds)->lockForUpdate()->get();

            foreach ($solicitudes as $s) {
                if ($s->estado !== 'pendiente_gdp') {
                    continue; // ignorar las que no correspondan (o puedes lanzar error)
                }

                $userId = $derivaciones[$s->id] ?? null;
                if (!$userId) {
                    // Si está seleccionada pero sin destinatario => error
                    throw new \RuntimeException("Falta destinatario SLEP para la solicitud {$s->numero_solicitud}.");
                }

                $s->update([
                    'estado' => 'derivada_slep',
                    'derivada_a_user_id' => (int)$userId,
                    'derivada_por_user_id' => $request->user()->id,
                    'derivada_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Derivación realizada correctamente.');
    }

public function gdpReasignar(Request $request, SolicitudReemplazo $solicitud)
{
    $user = $request->user();

    $canGdp = method_exists($user, 'hasAnyRole')
        ? $user->hasAnyRole(['admin', 'coordinador_gdp'])
        : false;

    abort_unless($canGdp, 403);

    // Solo reasignable si está derivada a SLEP
    abort_unless($solicitud->estado === 'derivada_slep', 403);

    $data = $request->validate([
        'derivada_a_user_id' => ['required', 'integer', 'exists:users,id'],
    ]);

    $destinatarioId = (int) $data['derivada_a_user_id'];

    // Permitir asignar solo a funcionario_slep o coordinador_gdp
    $isAllowed = User::query()
        ->whereKey($destinatarioId)
        ->whereHas('roles', function ($q) {
            $q->whereIn('name', ['funcionario_slep', 'coordinador_gdp']);
        })
        ->exists();

    abort_unless($isAllowed, 422);

    DB::transaction(function () use ($solicitud, $destinatarioId, $user) {
        $s = SolicitudReemplazo::whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
        abort_unless($s->estado === 'derivada_slep', 403);

        $s->update([
            'derivada_a_user_id' => $destinatarioId,
            'derivada_por_user_id' => (int) $user->id,
            'derivada_at' => now(),
        ]);
    });

    return back()->with('status', 'Derivación actualizada correctamente.');
}

    public function oficioPdf(\App\Models\SolicitudReemplazo $solicitud)
    {
        // Seguridad: solo roles permitidos
        abort_unless(auth()->user()?->hasAnyRole([
            'admin',
            'coordinador_uatp',
            'coordinador_gdp',
            'coordinador_gdp_admin',
            'funcionario_slep',
            'supervisor_plani',
            'funcionario_estab'
        ]), 403);

        // Si es funcionario_estab, solo puede ver solicitudes de su establecimiento
        if (auth()->user()->hasRole('funcionario_estab')) {
            $establecimiento = $this->establecimientoDelUsuario(); // ya lo usas en tu controller
            abort_unless((int)$solicitud->establecimiento_id === (int)$establecimiento->id, 403);
        }

        $path = (string) $solicitud->oficio_pdf_path;
        abort_unless($path !== '', 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $filename = 'oficio_' . ($solicitud->numero_solicitud ?? $solicitud->id) . '.pdf';

        return response()->file($disk->path($path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function respaldoPdf(\App\Models\SolicitudReemplazo $solicitud)
    {
        // Seguridad: solo roles permitidos
        abort_unless(auth()->user()?->hasAnyRole([
            'admin',
            'coordinador_uatp',
            'coordinador_gdp',
            'coordinador_gdp_admin',
            'funcionario_slep',
            'supervisor_plani',
            'funcionario_estab'
        ]), 403);

        // Si es funcionario_estab, solo puede ver solicitudes de su establecimiento
        if (auth()->user()->hasRole('funcionario_estab')) {
            $establecimiento = $this->establecimientoDelUsuario();
            abort_unless((int)$solicitud->establecimiento_id === (int)$establecimiento->id, 403);
        }

        $path = (string) $solicitud->respaldo_pdf_path;
        abort_unless($path !== '', 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $filename = 'respaldo_' . ($solicitud->numero_solicitud ?? $solicitud->id) . '.pdf';

        return response()->file($disk->path($path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function horarioTitularPdf(\App\Models\SolicitudReemplazo $solicitud)
    {
        abort_unless(auth()->user()?->hasAnyRole([
            'admin',
            'coordinador_uatp',
            'coordinador_gdp',
            'coordinador_gdp_admin',
            'funcionario_slep',
            'supervisor_plani',
            'funcionario_estab'
        ]), 403);

        if (auth()->user()->hasRole('funcionario_estab')) {
            $establecimiento = $this->establecimientoDelUsuario();
            abort_unless((int)$solicitud->establecimiento_id === (int)$establecimiento->id, 403);
        }

        $path = (string) $solicitud->horario_titular_pdf_path;
        abort_unless($path !== '', 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        $filename = 'horario_titular_' . ($solicitud->numero_solicitud ?? $solicitud->id) . '.pdf';

        return response()->file($disk->path($path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
    private function establecimientoDelUsuario(): Establecimiento
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Usuario no autenticado.');
        }

        $user->loadMissing('establecimiento');

        if (!$user->establecimiento) {
            abort(403, 'Usuario sin establecimiento asociado.');
        }

        return $user->establecimiento;
    }

    private function aaeeCategoriaLabel(?string $categoria): string
    {
        $c = strtolower(trim((string) $categoria));
        return match ($c) {
            'profesional' => 'Profesional',
            'tecnico', 'técnico' => 'Técnico',
            'administrativo' => 'Administrativo',
            'auxiliar' => 'Auxiliar',
            default => '',
        };
    }

    private function inicialesUsuario($user): string
    {
        if (!$user) return '';
        $nombres = trim((string) ($user->nombres ?? ''));
        $ap = trim((string) ($user->apellido_paterno ?? ''));
        $am = trim((string) ($user->apellido_materno ?? ''));

        $iniNombres = '';
        foreach (preg_split('/\s+/', $nombres, -1, PREG_SPLIT_NO_EMPTY) as $p) {
            $iniNombres .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        $iniAp = $ap !== '' ? mb_strtoupper(mb_substr($ap, 0, 1)) : '';
        $iniAm = $am !== '' ? mb_strtoupper(mb_substr($am, 0, 1)) : '';

        return $iniNombres . $iniAp . $iniAm;
    }

    private function numeroAPalabrasPesos(int $numero): string
    {
        $numero = max(0, $numero);
        $texto = $this->numeroAPalabrasEs($numero);

        // Ajuste masculino para "peso(s)"
        if ($numero === 1) {
            return 'un peso';
        }

        return trim($texto) . ' pesos';
    }

    private function numeroAPalabrasEs(int $n): string
    {
        if ($n === 0) return 'cero';

        $unidades = [
            0 => '',
            1 => 'uno',
            2 => 'dos',
            3 => 'tres',
            4 => 'cuatro',
            5 => 'cinco',
            6 => 'seis',
            7 => 'siete',
            8 => 'ocho',
            9 => 'nueve',
        ];

        $especiales = [
            10 => 'diez',
            11 => 'once',
            12 => 'doce',
            13 => 'trece',
            14 => 'catorce',
            15 => 'quince',
            16 => 'dieciseis',
            17 => 'diecisiete',
            18 => 'dieciocho',
            19 => 'diecinueve',
            20 => 'veinte',
            21 => 'veintiuno',
            22 => 'veintidos',
            23 => 'veintitres',
            24 => 'veinticuatro',
            25 => 'veinticinco',
            26 => 'veintiseis',
            27 => 'veintisiete',
            28 => 'veintiocho',
            29 => 'veintinueve',
        ];

        $decenas = [
            3 => 'treinta',
            4 => 'cuarenta',
            5 => 'cincuenta',
            6 => 'sesenta',
            7 => 'setenta',
            8 => 'ochenta',
            9 => 'noventa',
        ];

        $centenas = [
            1 => 'ciento',
            2 => 'doscientos',
            3 => 'trescientos',
            4 => 'cuatrocientos',
            5 => 'quinientos',
            6 => 'seiscientos',
            7 => 'setecientos',
            8 => 'ochocientos',
            9 => 'novecientos',
        ];

        $convertMenorMil = function (int $num) use ($unidades, $especiales, $decenas, $centenas): string {
            if ($num === 0) return '';
            if ($num === 100) return 'cien';
            $c = intdiv($num, 100);
            $r = $num % 100;
            $out = '';
            if ($c > 0) {
                $out .= $centenas[$c] . ' ';
            }

            if ($r === 0) {
                return trim($out);
            }

            if ($r < 10) {
                return trim($out . $unidades[$r]);
            }

            if ($r < 30) {
                return trim($out . ($especiales[$r] ?? ''));
            }

            $d = intdiv($r, 10);
            $u = $r % 10;

            $out .= $decenas[$d] ?? '';
            if ($u > 0) {
                $out .= ' y ' . $unidades[$u];
            }
            return trim($out);
        };

        // Miles
        if ($n < 1000) {
            return $convertMenorMil($n);
        }

        if ($n < 1000000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;

            $out = '';
            if ($miles === 1) {
                $out = 'mil';
            } else {
                $out = $convertMenorMil($miles) . ' mil';
            }

            if ($resto > 0) {
                $out .= ' ' . $convertMenorMil($resto);
            }
            return trim($out);
        }

        // Millones
        if ($n < 1000000000) {
            $millones = intdiv($n, 1000000);
            $resto = $n % 1000000;

            $out = '';
            if ($millones === 1) {
                $out = 'un millon';
            } else {
                $out = $this->numeroAPalabrasEs($millones) . ' millones';
            }

            if ($resto > 0) {
                $out .= ' ' . $this->numeroAPalabrasEs($resto);
            }
            return trim($out);
        }

        // Miles de millones (hasta 999,999,999,999)
        $milmillones = intdiv($n, 1000000000);
        $resto = $n % 1000000000;

        $out = '';
        if ($milmillones === 1) {
            $out = 'mil millones';
        } else {
            $out = $this->numeroAPalabrasEs($milmillones) . ' mil millones';
        }

        if ($resto > 0) {
            $out .= ' ' . $this->numeroAPalabrasEs($resto);
        }

        return trim($out);
    }


    public function finiquitos(Request $request)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);

        $cutoff = Carbon::today()->subDays(6);
        $estadoFiniquito = (string) $request->query('estado_finiquito', 'pendientes');
        if (!in_array($estadoFiniquito, ['pendientes', 'generados', 'completados', 'todos'], true)) {
            $estadoFiniquito = 'pendientes';
        }

        $baseQuery = SolicitudReemplazo::query()
            ->with($this->relacionesFiniquitos())
            ->whereIn('estado', ['aceptada', 'cerrado'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereNotNull('fecha_termino')
            ->whereDate('fecha_termino', '<=', $cutoff->toDateString());

        if ($estadoFiniquito === 'pendientes') {
            $baseQuery->where(function ($q) {
                $q->whereNull('finiquito_estado')
                    ->orWhereNotIn('finiquito_estado', ['generado', 'completado']);
            });
        } elseif ($estadoFiniquito === 'generados') {
            $baseQuery->where('finiquito_estado', 'generado');
        } elseif ($estadoFiniquito === 'completados') {
            $baseQuery->where('finiquito_estado', 'completado');
        }

        if ($request->filled('numero')) {
            $baseQuery->where('numero_solicitud', 'like', '%' . trim((string) $request->query('numero')) . '%');
        }

        if ($request->filled('establecimiento_id')) {
            $baseQuery->where('establecimiento_id', (int) $request->query('establecimiento_id'));
        }

        if ($request->filled('comuna')) {
            $comuna = trim((string) $request->query('comuna'));
            $baseQuery->whereHas('establecimiento', function ($q) use ($comuna) {
                $q->where('comuna', $comuna);
            });
        }

        if ($request->filled('desde')) {
            $baseQuery->whereDate('fecha_termino', '>=', $request->date('desde')->format('Y-m-d'));
        }

        if ($request->filled('hasta')) {
            $baseQuery->whereDate('fecha_inicio_trabajo', '<=', $request->date('hasta')->format('Y-m-d'));
        }

        if ($request->filled('titular')) {
            $term = '%' . trim((string) $request->query('titular')) . '%';
            $baseQuery->whereHas('funcionarioTitular', function ($q) use ($term) {
                $q->where('rut', 'like', $term)->orWhere('nombre', 'like', $term);
            });
        }

        if ($request->filled('reemplazante')) {
            $term = '%' . trim((string) $request->query('reemplazante')) . '%';
            $rut = $this->rutComparable((string) $request->query('reemplazante'));
            $baseQuery->where(function ($q) use ($term, $rut) {
                if ($rut !== '') {
                    $q->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut_reemplazo_normalizado, '.', ''), '-', ''), ' ', '')) like ?", ['%' . $rut . '%']);
                } else {
                    $q->whereRaw('1 = 0');
                }

                $q->orWhereHas('postulante.user', function ($qq) use ($term, $rut) {
                    $qq->where('rut', 'like', $term)
                        ->orWhere('nombres', 'like', $term)
                        ->orWhere('apellido_paterno', 'like', $term)
                        ->orWhere('apellido_materno', 'like', $term);

                    if ($rut !== '') {
                        $qq->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) like ?", ['%' . $rut . '%']);
                    }
                })->orWhereHas('contratoPostulante.user', function ($qq) use ($term, $rut) {
                    $qq->where('rut', 'like', $term)
                        ->orWhere('nombres', 'like', $term)
                        ->orWhere('apellido_paterno', 'like', $term)
                        ->orWhere('apellido_materno', 'like', $term);

                    if ($rut !== '') {
                        $qq->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) like ?", ['%' . $rut . '%']);
                    }
                });
            });
        }

        $candidatas = $baseQuery
            ->orderByDesc('fecha_termino')
            ->orderByDesc('fecha_inicio_trabajo')
            ->orderByDesc('id')
            ->limit(1500)
            ->get();

        $ruts = $candidatas
            ->map(fn ($s) => $this->rutReemplazanteComparable($s))
            ->filter()
            ->unique()
            ->values();

        $relacionadasPorRut = collect();
        if ($ruts->isNotEmpty()) {
            $relacionadasPorRut = SolicitudReemplazo::query()
                ->with($this->relacionesFiniquitos())
                ->whereIn('estado', ['aceptada', 'cerrado'])
                ->whereNotNull('fecha_inicio_trabajo')
                ->whereNotNull('fecha_termino')
                ->where(function ($q) use ($ruts) {
                    $rutsArray = $ruts->all();
                    $q->whereIn(DB::raw("UPPER(REPLACE(REPLACE(REPLACE(rut_reemplazo_normalizado, '.', ''), '-', ''), ' ', ''))"), $rutsArray)
                        ->orWhereHas('postulante.user', function ($qq) use ($rutsArray) {
                            $qq->whereIn(DB::raw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', ''))"), $rutsArray);
                        })
                        ->orWhereHas('contratoPostulante.user', function ($qq) use ($rutsArray) {
                            $qq->whereIn(DB::raw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', ''))"), $rutsArray);
                        });
                })
                ->get()
                ->groupBy(fn ($s) => $this->rutReemplazanteComparable($s));
        }

        $filtradas = $candidatas->filter(function ($solicitud) use ($relacionadasPorRut, $cutoff) {
            return $this->esSolicitudFinalCadenaFiniquito($solicitud, $relacionadasPorRut, $cutoff);
        })->values();

        $filtradas = $filtradas->map(function ($s) use ($relacionadasPorRut) {
            $continuidad = $this->continuidadFiniquito($s, $relacionadasPorRut);
            $s->categoria_finiquito = $this->categoriaFiniquito($s);
            $s->finiquito_periodo_inicio = $continuidad['inicio'];
            $s->finiquito_periodo_termino = $continuidad['termino'];
            $s->finiquito_cadena_ids = $continuidad['cadena']->pluck('id')->values()->all();
            $s->finiquito_cadena_numeros = $continuidad['cadena']->map(fn ($item) => $item->numero_solicitud ?: ('ID ' . $item->id))->values()->all();
            $s->finiquito_cadena_count = $continuidad['cadena']->count();
            return $s;
        });

        $categoriaGestion = (string) $request->query('categoria', 'asistentes');
        if (! in_array($categoriaGestion, ['asistentes', 'junji', 'docentes', 'todos'], true)) {
            $categoriaGestion = 'asistentes';
        }

        if ($categoriaGestion !== 'todos') {
            $filtradas = $filtradas->filter(fn ($s) => $s->categoria_finiquito === $categoriaGestion)->values();
        }

        $resumenFiniquitos = [
            'total' => $filtradas->count(),
            'pendientes' => $filtradas->filter(fn ($s) => ! in_array((string) $s->finiquito_estado, ['generado', 'completado'], true))->count(),
            'generados' => $filtradas->filter(fn ($s) => (string) $s->finiquito_estado === 'generado')->count(),
            'completados' => $filtradas->filter(fn ($s) => (string) $s->finiquito_estado === 'completado')->count(),
            'asistentes' => $filtradas->filter(fn ($s) => $s->categoria_finiquito === 'asistentes')->count(),
            'junji' => $filtradas->filter(fn ($s) => $s->categoria_finiquito === 'junji')->count(),
            'docentes' => $filtradas->filter(fn ($s) => $s->categoria_finiquito === 'docentes')->count(),
        ];

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $items = $filtradas->slice(($page - 1) * $perPage, $perPage)->values();
        $finiquitos = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $filtradas->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $establecimientos = Establecimiento::query()
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna', 'sala_cuna']);

        $comunas = Establecimiento::query()
            ->whereNotNull('comuna')
            ->where('comuna', '<>', '')
            ->distinct()
            ->orderBy('comuna')
            ->pluck('comuna')
            ->values();

        return view('gestion.solicitudes-reemplazo.finiquitos', [
            'finiquitos' => $finiquitos,
            'establecimientos' => $establecimientos,
            'comunas' => $comunas,
            'cutoff' => $cutoff,
            'estadoFiniquito' => $estadoFiniquito,
            'resumenFiniquitos' => $resumenFiniquitos,
            'categoriaGestion' => $categoriaGestion,
            'firmantesFiniquito' => $this->firmantesFiniquito(),
        ]);
    }


    public function exportarFiniquitosExcel(Request $request)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);

        $cutoff = Carbon::today()->subDays(6);
        $estadoFiniquito = (string) $request->query('estado_finiquito', 'pendientes');
        if (!in_array($estadoFiniquito, ['pendientes', 'generados', 'completados', 'todos'], true)) {
            $estadoFiniquito = 'pendientes';
        }

        $baseQuery = SolicitudReemplazo::query()
            ->with($this->relacionesFiniquitos())
            ->whereIn('estado', ['aceptada', 'cerrado'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereNotNull('fecha_termino')
            ->whereDate('fecha_termino', '<=', $cutoff->toDateString());

        if ($estadoFiniquito === 'pendientes') {
            $baseQuery->where(function ($q) {
                $q->whereNull('finiquito_estado')
                    ->orWhereNotIn('finiquito_estado', ['generado', 'completado']);
            });
        } elseif ($estadoFiniquito === 'generados') {
            $baseQuery->where('finiquito_estado', 'generado');
        } elseif ($estadoFiniquito === 'completados') {
            $baseQuery->where('finiquito_estado', 'completado');
        }

        if ($request->filled('numero')) {
            $baseQuery->where('numero_solicitud', 'like', '%' . trim((string) $request->query('numero')) . '%');
        }

        if ($request->filled('establecimiento_id')) {
            $baseQuery->where('establecimiento_id', (int) $request->query('establecimiento_id'));
        }

        if ($request->filled('comuna')) {
            $comuna = trim((string) $request->query('comuna'));
            $baseQuery->whereHas('establecimiento', function ($q) use ($comuna) {
                $q->where('comuna', $comuna);
            });
        }

        if ($request->filled('desde')) {
            $baseQuery->whereDate('fecha_termino', '>=', $request->date('desde')->format('Y-m-d'));
        }

        if ($request->filled('hasta')) {
            $baseQuery->whereDate('fecha_inicio_trabajo', '<=', $request->date('hasta')->format('Y-m-d'));
        }

        if ($request->filled('titular')) {
            $term = '%' . trim((string) $request->query('titular')) . '%';
            $baseQuery->whereHas('funcionarioTitular', function ($q) use ($term) {
                $q->where('rut', 'like', $term)->orWhere('nombre', 'like', $term);
            });
        }

        if ($request->filled('reemplazante')) {
            $term = '%' . trim((string) $request->query('reemplazante')) . '%';
            $rut = $this->rutComparable((string) $request->query('reemplazante'));
            $baseQuery->where(function ($q) use ($term, $rut) {
                if ($rut !== '') {
                    $q->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut_reemplazo_normalizado, '.', ''), '-', ''), ' ', '')) like ?", ['%' . $rut . '%']);
                } else {
                    $q->whereRaw('1 = 0');
                }

                $q->orWhereHas('postulante.user', function ($qq) use ($term, $rut) {
                    $qq->where('rut', 'like', $term)
                        ->orWhere('nombres', 'like', $term)
                        ->orWhere('apellido_paterno', 'like', $term)
                        ->orWhere('apellido_materno', 'like', $term);

                    if ($rut !== '') {
                        $qq->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) like ?", ['%' . $rut . '%']);
                    }
                })->orWhereHas('contratoPostulante.user', function ($qq) use ($term, $rut) {
                    $qq->where('rut', 'like', $term)
                        ->orWhere('nombres', 'like', $term)
                        ->orWhere('apellido_paterno', 'like', $term)
                        ->orWhere('apellido_materno', 'like', $term);

                    if ($rut !== '') {
                        $qq->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) like ?", ['%' . $rut . '%']);
                    }
                });
            });
        }

        $candidatas = $baseQuery
            ->orderByDesc('fecha_termino')
            ->orderByDesc('fecha_inicio_trabajo')
            ->orderByDesc('id')
            ->limit(5000)
            ->get();

        $ruts = $candidatas
            ->map(fn ($s) => $this->rutReemplazanteComparable($s))
            ->filter()
            ->unique()
            ->values();

        $relacionadasPorRut = collect();
        if ($ruts->isNotEmpty()) {
            $relacionadasPorRut = SolicitudReemplazo::query()
                ->with($this->relacionesFiniquitos())
                ->whereIn('estado', ['aceptada', 'cerrado'])
                ->whereNotNull('fecha_inicio_trabajo')
                ->whereNotNull('fecha_termino')
                ->where(function ($q) use ($ruts) {
                    $rutsArray = $ruts->all();
                    $q->whereIn(DB::raw("UPPER(REPLACE(REPLACE(REPLACE(rut_reemplazo_normalizado, '.', ''), '-', ''), ' ', ''))"), $rutsArray)
                        ->orWhereHas('postulante.user', function ($qq) use ($rutsArray) {
                            $qq->whereIn(DB::raw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', ''))"), $rutsArray);
                        })
                        ->orWhereHas('contratoPostulante.user', function ($qq) use ($rutsArray) {
                            $qq->whereIn(DB::raw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', ''))"), $rutsArray);
                        });
                })
                ->get()
                ->groupBy(fn ($s) => $this->rutReemplazanteComparable($s));
        }

        $filtradas = $candidatas->filter(function ($solicitud) use ($relacionadasPorRut, $cutoff) {
            return $this->esSolicitudFinalCadenaFiniquito($solicitud, $relacionadasPorRut, $cutoff);
        })->values();

        $filtradas = $filtradas->map(function ($s) use ($relacionadasPorRut) {
            $continuidad = $this->continuidadFiniquito($s, $relacionadasPorRut);
            $s->categoria_finiquito = $this->categoriaFiniquito($s);
            $s->finiquito_periodo_inicio = $continuidad['inicio'];
            $s->finiquito_periodo_termino = $continuidad['termino'];
            $s->finiquito_cadena_ids = $continuidad['cadena']->pluck('id')->values()->all();
            $s->finiquito_cadena_numeros = $continuidad['cadena']->map(fn ($item) => $item->numero_solicitud ?: ('ID ' . $item->id))->values()->all();
            $s->finiquito_cadena_count = $continuidad['cadena']->count();
            return $s;
        });

        $categoriaGestion = (string) $request->query('categoria', 'asistentes');
        if (! in_array($categoriaGestion, ['asistentes', 'junji', 'docentes', 'todos'], true)) {
            $categoriaGestion = 'asistentes';
        }

        if ($categoriaGestion !== 'todos') {
            $filtradas = $filtradas->filter(fn ($s) => $s->categoria_finiquito === $categoriaGestion)->values();
        }

        $filename = 'finiquitos_reemplazos_' . $categoriaGestion . '_' . now()->format('Ymd_His') . '.xls';

        return response()
            ->view('gestion.solicitudes-reemplazo.exports.finiquitos', [
                'finiquitos' => $filtradas,
                'categoriaGestion' => $categoriaGestion,
                'estadoFiniquito' => $estadoFiniquito,
                'cutoff' => $cutoff,
                'filtros' => $request->query(),
            ])
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }


    public function generarFiniquitoPdf(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);

        $firmantes = collect($this->firmantesFiniquito())->keyBy('key');

        $validated = $request->validate([
            'finiquito_fecha_emision' => ['required', 'date'],
            'finiquito_monto' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'firmante_key' => ['required', 'string'],
            'finiquito_observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless($firmantes->has($validated['firmante_key']), 422, 'El firmante seleccionado no se encuentra disponible.');
        $firmante = $firmantes->get($validated['firmante_key']);

        $solicitud->loadMissing($this->relacionesFiniquitos());
        $cutoff = Carbon::today()->subDays(6);

        abort_unless(in_array($solicitud->estado, ['aceptada', 'cerrado'], true), 422, 'Sólo se puede generar finiquito en reemplazos aceptados o cerrados.');
        abort_unless($solicitud->fecha_inicio_trabajo && $solicitud->fecha_termino && $solicitud->fecha_termino->lte($cutoff), 422, 'El reemplazo debe tener fecha de inicio de trabajo, fecha de término y más de 6 días desde el término.');

        $rut = $this->rutReemplazanteComparable($solicitud);
        abort_if($rut === '', 422, 'No fue posible identificar el RUT del reemplazante.');

        $relacionadasPorRut = SolicitudReemplazo::query()
            ->with($this->relacionesFiniquitos())
            ->whereIn('estado', ['aceptada', 'cerrado'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereNotNull('fecha_termino')
            ->where(function ($q) use ($rut) {
                $q->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut_reemplazo_normalizado, '.', ''), '-', ''), ' ', '')) = ?", [$rut])
                    ->orWhereHas('postulante.user', function ($qq) use ($rut) {
                        $qq->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rut]);
                    })
                    ->orWhereHas('contratoPostulante.user', function ($qq) use ($rut) {
                        $qq->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rut]);
                    });
            })
            ->get()
            ->groupBy(fn ($s) => $this->rutReemplazanteComparable($s));

        abort_unless($this->esSolicitudFinalCadenaFiniquito($solicitud, $relacionadasPorRut, $cutoff), 422, 'Existe una continuidad o período posterior para el mismo reemplazante y titular. Revise el último período completo antes de generar finiquito.');
        abort_unless($this->aplicaFiniquito($solicitud), 422, 'Esta solicitud corresponde a cese docente no JUNJI y no genera finiquito desde esta acción.');

        $continuidad = $this->continuidadFiniquito($solicitud, $relacionadasPorRut);
        $solicitudFinal = $continuidad['ultima'] ?: $solicitud;
        $solicitudFinal->loadMissing($this->relacionesFiniquitos());

        $monto = (int) round((float) ($validated['finiquito_monto'] ?? 0));
        $fechaEmision = Carbon::parse($validated['finiquito_fecha_emision']);

        $pdf = Pdf::loadView('pdf.finiquito-reemplazo', [
            's' => $solicitudFinal,
            'firmante' => $firmante,
            'monto' => $monto,
            'montoTexto' => mb_strtoupper($this->numeroAPalabrasEs($monto), 'UTF-8'),
            'fechaEmision' => $fechaEmision,
            'nombreReemplazante' => $this->nombreReemplazanteSolo($solicitudFinal),
            'rutReemplazante' => $this->formatRutChile($this->rutReemplazanteComparable($solicitudFinal)),
            'rutTitular' => $solicitudFinal->funcionarioTitular?->rut ?? $solicitudFinal->rut_titular_normalizado,
            'jornada' => $this->jornadaFiniquito($solicitudFinal),
            'periodoInicioFiniquito' => $continuidad['inicio'],
            'periodoTerminoFiniquito' => $continuidad['termino'],
            'solicitudesCadenaFiniquito' => $continuidad['cadena'],
            'causalLegal' => 'Ley 21.109 Art. 33 letra E',
            'glosaCausal' => 'Vencimiento del plazo del contrato',
            'logoDataUri' => $this->finiquitoLogoDataUri('andac'),
            'gobiernoLogoDataUri' => $this->finiquitoLogoDataUri('gobierno'),
        ])->setPaper([0, 0, 612.28, 1009.13], 'portrait');

        $path = 'finiquitos_reemplazos/finiquito_' . $solicitud->id . '_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        $solicitud->forceFill([
            'finiquito_estado' => 'generado',
            'finiquito_monto' => $monto,
            'finiquito_fecha_emision' => $fechaEmision->toDateString(),
            'finiquito_pdf_path' => $path,
            'finiquito_generado_por_user_id' => $user->id,
            'finiquito_generado_at' => now(),
            'finiquito_firmante_nombre' => $firmante['nombre'],
            'finiquito_firmante_rut' => $firmante['rut'],
            'finiquito_firmante_cargo' => $firmante['cargo_documento'],
            'finiquito_firmante_es_subrogante' => (bool) $firmante['es_subrogante'],
            'finiquito_observacion' => $validated['finiquito_observacion'] ?? $solicitud->finiquito_observacion,
        ])->save();

        return redirect()
            ->route('gestion.solicitudes-reemplazo.finiquitos.index', $request->query())
            ->with('status', 'Finiquito PDF generado correctamente. El estado cambió a Generado.');
    }

    public function descargarFiniquitoPdf(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);
        abort_unless($solicitud->finiquito_pdf_path && Storage::disk('public')->exists($solicitud->finiquito_pdf_path), 404);

        $filename = 'finiquito_' . ($solicitud->numero_solicitud ?: $solicitud->id) . '.pdf';
        return Storage::disk('public')->download($solicitud->finiquito_pdf_path, $filename);
    }

    public function cargarFiniquitoFirmado(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);

        $validated = $request->validate([
            'finiquito_firmado_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'finiquito_firmado_observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $solicitud->loadMissing($this->relacionesFiniquitos());
        $cutoff = Carbon::today()->subDays(6);

        abort_unless(in_array($solicitud->estado, ['aceptada', 'cerrado'], true), 422, 'Sólo se puede cargar finiquito firmado en reemplazos aceptados o cerrados.');
        abort_unless($solicitud->fecha_inicio_trabajo && $solicitud->fecha_termino && $solicitud->fecha_termino->lte($cutoff), 422, 'El reemplazo debe tener fecha de inicio de trabajo, fecha de término y más de 6 días desde el término.');
        abort_unless($this->aplicaFiniquito($solicitud), 422, 'Esta solicitud corresponde a cese docente no JUNJI y no genera finiquito desde esta acción.');

        $rut = $this->rutReemplazanteComparable($solicitud);
        abort_if($rut === '', 422, 'No fue posible identificar el RUT del reemplazante.');

        $relacionadasPorRut = SolicitudReemplazo::query()
            ->with($this->relacionesFiniquitos())
            ->whereIn('estado', ['aceptada', 'cerrado'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereNotNull('fecha_termino')
            ->where(function ($q) use ($rut) {
                $q->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut_reemplazo_normalizado, '.', ''), '-', ''), ' ', '')) = ?", [$rut])
                    ->orWhereHas('postulante.user', function ($qq) use ($rut) {
                        $qq->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rut]);
                    })
                    ->orWhereHas('contratoPostulante.user', function ($qq) use ($rut) {
                        $qq->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(rut, '.', ''), '-', ''), ' ', '')) = ?", [$rut]);
                    });
            })
            ->get()
            ->groupBy(fn ($s) => $this->rutReemplazanteComparable($s));

        abort_unless($this->esSolicitudFinalCadenaFiniquito($solicitud, $relacionadasPorRut, $cutoff), 422, 'Existe una continuidad o período posterior para el mismo reemplazante y titular. Revise el último período completo antes de cargar el finiquito firmado.');

        $file = $request->file('finiquito_firmado_pdf');
        $filename = 'finiquito_firmado_' . $solicitud->id . '_' . now()->format('Ymd_His') . '.pdf';
        $path = $file->storeAs('finiquitos_reemplazos/firmados', $filename, 'public');

        $solicitud->forceFill([
            'finiquito_estado' => 'completado',
            'finiquito_firmado_pdf_path' => $path,
            'finiquito_firmado_nombre_original' => $file->getClientOriginalName(),
            'finiquito_firmado_mime' => $file->getClientMimeType() ?: 'application/pdf',
            'finiquito_firmado_size' => $file->getSize(),
            'finiquito_firmado_observacion' => $validated['finiquito_firmado_observacion'] ?? null,
            'finiquito_firmado_cargado_por_user_id' => $user->id,
            'finiquito_firmado_cargado_at' => now(),
        ])->save();

        return redirect()
            ->route('gestion.solicitudes-reemplazo.finiquitos.index', $request->query())
            ->with('status', 'Finiquito firmado cargado correctamente. El estado cambió a Completado.');
    }

    public function descargarFiniquitoFirmado(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);

        $path = (string) ($solicitud->finiquito_firmado_pdf_path ?? '');
        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);

        $filename = 'finiquito_firmado_' . ($solicitud->numero_solicitud ?: $solicitud->id) . '.pdf';
        return Storage::disk('public')->download($path, $filename);
    }

    public function eliminarFiniquitoFirmado(Request $request, SolicitudReemplazo $solicitud)
    {
        $user = $request->user();
        abort_unless(method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep']), 403);
        abort_unless((string) ($solicitud->finiquito_estado ?? '') === 'completado', 422, 'Sólo se puede eliminar el finiquito firmado cuando el estado es Completado.');

        $path = (string) ($solicitud->finiquito_firmado_pdf_path ?? '');
        if ($path !== '' && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $estadoPosterior = $solicitud->finiquito_pdf_path ? 'generado' : null;

        $solicitud->forceFill([
            'finiquito_estado' => $estadoPosterior,
            'finiquito_firmado_pdf_path' => null,
            'finiquito_firmado_nombre_original' => null,
            'finiquito_firmado_mime' => null,
            'finiquito_firmado_size' => null,
            'finiquito_firmado_observacion' => null,
            'finiquito_firmado_cargado_por_user_id' => null,
            'finiquito_firmado_cargado_at' => null,
        ])->save();

        return redirect()
            ->route('gestion.solicitudes-reemplazo.finiquitos.index', $request->query())
            ->with('status', 'Finiquito firmado eliminado correctamente. El estado volvió a ' . ($estadoPosterior === 'generado' ? 'Generado' : 'Pendiente') . '.');
    }


    private function finiquitoLogoDataUri(string $tipo): ?string
    {
        $candidatos = $tipo === 'gobierno'
            ? [
                resource_path('images/logo-gobierno-slep.png'),
                resource_path('branding/pdf/logo-gobierno-slep.png'),
            ]
            : [
                resource_path('images/logo-andaliencosta.png'),
                resource_path('branding/pdf/logo-andaliencosta.png'),
            ];

        foreach ($candidatos as $path) {
            if (! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $extension === 'svg' ? 'image/svg+xml' : 'image/png';

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        }

        return null;
    }

    private function firmantesFiniquito(): array
    {
        $fallback = [[
            'key' => 'titular_default',
            'nombre' => 'RAMON ANGEL JARA ZAVALA',
            'rut' => '9.860.498-7',
            'cargo_documento' => 'Director Ejecutivo',
            'es_subrogante' => false,
            'label' => 'RAMON ANGEL JARA ZAVALA - Director Ejecutivo',
        ]];

        if (! Schema::hasTable('funcionarios_ac_jefaturas_dependencias') || ! Schema::hasTable('funcionarios_ac_autorizados')) {
            return $fallback;
        }

        $registro = DB::table('funcionarios_ac_jefaturas_dependencias')
            ->where(function ($q) {
                $q->where('subdireccion_dependencia', 'Dirección Ejecutiva')
                    ->orWhere('subdireccion_dependencia', 'Direccion Ejecutiva');
            })
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->first();

        if (! $registro) {
            return $fallback;
        }

        $items = [];
        $ids = [
            ['id' => $registro->jefatura_funcionario_ac_id ?? null, 'subrogante' => false, 'key' => 'director'],
            ['id' => $registro->subrogante_1_funcionario_ac_id ?? null, 'subrogante' => true, 'key' => 'subrogante_1'],
            ['id' => $registro->subrogante_2_funcionario_ac_id ?? null, 'subrogante' => true, 'key' => 'subrogante_2'],
            ['id' => $registro->subrogante_3_funcionario_ac_id ?? null, 'subrogante' => true, 'key' => 'subrogante_3'],
        ];

        foreach ($ids as $item) {
            if (empty($item['id'])) {
                continue;
            }
            $funcionario = FuncionarioAcAutorizado::find($item['id']);
            if (! $funcionario) {
                continue;
            }
            $cargo = $item['subrogante'] ? 'Director Ejecutivo (S)' : 'Director Ejecutivo';
            $nombre = mb_strtoupper($funcionario->nombre_completo, 'UTF-8');
            $rut = $this->formatRutChile($funcionario->rut_completo);
            $items[] = [
                'key' => $item['key'] . '_' . $funcionario->id,
                'nombre' => $nombre,
                'rut' => $rut,
                'cargo_documento' => $cargo,
                'es_subrogante' => (bool) $item['subrogante'],
                'label' => $nombre . ' - ' . $cargo,
            ];
        }

        return $items ?: $fallback;
    }

    private function categoriaFiniquito(SolicitudReemplazo $solicitud): string
    {
        if ((bool) ($solicitud->establecimiento?->sala_cuna ?? false)) {
            return 'junji';
        }

        $texto = mb_strtoupper(trim(collect([
            $solicitud->funcionarioTitular?->estatuto ?? '',
            $solicitud->aaee_categoria ?? '',
            $solicitud->tipo_reemplazo ?? '',
        ])->filter()->implode(' ')), 'UTF-8');

        if (str_contains($texto, 'ASISTENTE') || str_contains($texto, 'AAEE')) {
            return 'asistentes';
        }

        return 'docentes';
    }

    private function aplicaFiniquito(SolicitudReemplazo $solicitud): bool
    {
        return in_array($this->categoriaFiniquito($solicitud), ['asistentes', 'junji'], true);
    }

    private function nombreReemplazanteSolo(SolicitudReemplazo $solicitud): string
    {
        $user = $solicitud->contratoPostulante?->user ?: $solicitud->postulante?->user;
        if (! $user) {
            return 'TRABAJADOR(A) SIN NOMBRE REGISTRADO';
        }

        return mb_strtoupper(trim(collect([
            $user->nombres ?? '',
            $user->apellido_paterno ?? '',
            $user->apellido_materno ?? '',
        ])->filter()->implode(' ')), 'UTF-8');
    }

    private function jornadaFiniquito(SolicitudReemplazo $solicitud): string
    {
        $solicitud->loadMissing('jornadas');

        $horas = 0.0;
        foreach (($solicitud->jornadas ?? collect()) as $jornada) {
            if ($jornada->getAttribute('total_horas') !== null) {
                $horas += (float) $jornada->getAttribute('total_horas');
                continue;
            }

            if ($jornada->getAttribute('reemplazo_total') !== null) {
                $horas += (float) $jornada->getAttribute('reemplazo_total');
                continue;
            }

            $horas += (float) $jornada->getAttribute('reemplazo_basica') + (float) $jornada->getAttribute('reemplazo_media');
        }

        if ($horas <= 0) {
            $horas = (float) ($solicitud->horas_aula_cronologicas_reemplazo ?: $solicitud->horas_aula_pedagogicas_reemplazo ?: 0);
        }

        if ($horas > 0) {
            $horasTexto = rtrim(rtrim(number_format($horas, 2, ',', '.'), '0'), ',');
            return 'Jornada de ' . $horasTexto . ' horas cronológicas semanales';
        }

        return 'Jornada informada en la solicitud de reemplazo';
    }

    private function relacionesFiniquitos(): array
    {
        return [
            'establecimiento:id,rbd,nombre_establecimiento,comuna,sala_cuna',
            'funcionarioTitular:id,rut,nombre,estatuto',
            'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
            'contratoPostulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
            'finiquitoGeneradoPor:id,rut,nombres,apellido_paterno,apellido_materno,email',
            'finiquitoFirmadoCargadoPor:id,rut,nombres,apellido_paterno,apellido_materno,email',
        ];
    }

    private function rutReemplazanteComparable(SolicitudReemplazo $solicitud): string
    {
        $rut = $solicitud->contratoPostulante?->user?->rut
            ?: $solicitud->postulante?->user?->rut
            ?: $solicitud->rut_reemplazo_normalizado;

        return $this->rutComparable($rut);
    }

    private function nombreReemplazanteFiniquito(SolicitudReemplazo $solicitud): string
    {
        $user = $solicitud->contratoPostulante?->user ?: $solicitud->postulante?->user;
        if (!$user) {
            return trim((string) ($solicitud->rut_reemplazo_normalizado ?: 'Sin reemplazante registrado'));
        }

        $nombre = trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '') . ' ' . ($user->nombres ?? ''));
        return trim($nombre . (!empty($user->rut) ? ' (' . $this->formatRutChile($user->rut) . ')' : ''));
    }

    private function esSolicitudFinalCadenaFiniquito(SolicitudReemplazo $solicitud, $relacionadasPorRut, Carbon $cutoff): bool
    {
        $rut = $this->rutReemplazanteComparable($solicitud);
        if ($rut === '' || ! $solicitud->fecha_inicio_trabajo || ! $solicitud->fecha_termino) {
            return false;
        }

        if ($solicitud->fecha_termino->gt($cutoff)) {
            return false;
        }

        $grupo = $this->grupoContinuidadFiniquito($solicitud, $relacionadasPorRut);
        if ($grupo->isEmpty()) {
            return false;
        }

        return ! $this->buscarSolicitudPosteriorContinuidad($solicitud, $grupo);
    }

    private function continuidadFiniquito(SolicitudReemplazo $solicitud, $relacionadasPorRut): array
    {
        $grupo = $this->grupoContinuidadFiniquito($solicitud, $relacionadasPorRut);
        $cadena = collect();
        $actual = $solicitud;
        $visitados = [];

        while ($actual && ! isset($visitados[(int) $actual->id])) {
            $actual->loadMissing($this->relacionesFiniquitos());
            $cadena->push($actual);
            $visitados[(int) $actual->id] = true;

            $anterior = null;
            if (! empty($actual->solicitud_anterior_id)) {
                $anterior = $grupo->firstWhere('id', (int) $actual->solicitud_anterior_id);
                if ($anterior && ! $this->mismaLlaveContinuidadFiniquito($actual, $anterior)) {
                    $anterior = null;
                }
            }

            if (! $anterior) {
                $anterior = $this->buscarSolicitudAnteriorContinuidad($actual, $grupo, array_keys($visitados));
            }

            $actual = $anterior;
        }

        $cadena = $cadena
            ->unique('id')
            ->sortBy(fn ($s) => $s->fecha_inicio_trabajo?->format('Y-m-d') . '-' . str_pad((string) $s->id, 12, '0', STR_PAD_LEFT))
            ->values();

        $primera = $cadena->first() ?: $solicitud;
        $ultima = $cadena
            ->sortByDesc(fn ($s) => $s->fecha_termino?->format('Y-m-d') . '-' . $s->fecha_inicio_trabajo?->format('Y-m-d') . '-' . str_pad((string) $s->id, 12, '0', STR_PAD_LEFT))
            ->first() ?: $solicitud;

        return [
            'cadena' => $cadena,
            'primera' => $primera,
            'ultima' => $ultima,
            'inicio' => $primera->fecha_inicio_trabajo ?: $solicitud->fecha_inicio_trabajo,
            'termino' => $ultima->fecha_termino ?: $solicitud->fecha_termino,
        ];
    }

    private function grupoContinuidadFiniquito(SolicitudReemplazo $solicitud, $relacionadasPorRut): \Illuminate\Support\Collection
    {
        $rut = $this->rutReemplazanteComparable($solicitud);
        return collect($relacionadasPorRut->get($rut, collect()))
            ->filter(fn ($s) => $s->fecha_inicio_trabajo !== null && $s->fecha_termino !== null)
            ->values();
    }

    private function buscarSolicitudAnteriorContinuidad(SolicitudReemplazo $actual, \Illuminate\Support\Collection $grupo, array $visitados = []): ?SolicitudReemplazo
    {
        return $grupo
            ->filter(function ($posible) use ($actual, $visitados) {
                if ((int) $posible->id === (int) $actual->id || in_array((int) $posible->id, array_map('intval', $visitados), true)) {
                    return false;
                }

                if (! $this->mismaLlaveContinuidadFiniquito($actual, $posible)) {
                    return false;
                }

                return $this->fechasConectanContinuidad($posible->fecha_termino, $actual->fecha_inicio_trabajo);
            })
            ->sortByDesc(fn ($s) => $s->fecha_termino?->format('Y-m-d') . '-' . $s->fecha_inicio_trabajo?->format('Y-m-d') . '-' . str_pad((string) $s->id, 12, '0', STR_PAD_LEFT))
            ->first();
    }

    private function buscarSolicitudPosteriorContinuidad(SolicitudReemplazo $actual, \Illuminate\Support\Collection $grupo): ?SolicitudReemplazo
    {
        return $grupo
            ->filter(function ($posible) use ($actual) {
                if ((int) $posible->id === (int) $actual->id) {
                    return false;
                }

                if (! $this->mismaLlaveContinuidadFiniquito($actual, $posible)) {
                    return false;
                }

                if ((int) ($posible->solicitud_anterior_id ?? 0) === (int) $actual->id) {
                    return true;
                }

                return $this->fechasConectanContinuidad($actual->fecha_termino, $posible->fecha_inicio_trabajo);
            })
            ->sortBy(fn ($s) => $s->fecha_inicio_trabajo?->format('Y-m-d') . '-' . str_pad((string) $s->id, 12, '0', STR_PAD_LEFT))
            ->first();
    }

    private function mismaLlaveContinuidadFiniquito(SolicitudReemplazo $a, SolicitudReemplazo $b): bool
    {
        $postulanteA = $this->postulanteContinuidadFiniquito($a);
        $postulanteB = $this->postulanteContinuidadFiniquito($b);
        $rutTitularA = $this->rutTitularComparable($a);
        $rutTitularB = $this->rutTitularComparable($b);

        return $postulanteA !== null
            && $postulanteB !== null
            && (int) $postulanteA === (int) $postulanteB
            && $rutTitularA !== ''
            && $rutTitularA === $rutTitularB;
    }

    private function postulanteContinuidadFiniquito(SolicitudReemplazo $solicitud): ?int
    {
        $id = $solicitud->contrato_trabajo_postulant_profile_id ?: $solicitud->postulant_profile_id;
        return $id ? (int) $id : null;
    }

    private function rutTitularComparable(SolicitudReemplazo $solicitud): string
    {
        $rut = $solicitud->funcionarioTitular?->rut ?: $solicitud->rut_titular_normalizado;
        return $this->rutComparable($rut);
    }

    private function fechasConectanContinuidad($fechaTerminoAnterior, $fechaInicioActual): bool
    {
        if (! $fechaTerminoAnterior || ! $fechaInicioActual) {
            return false;
        }

        $termino = Carbon::parse($fechaTerminoAnterior)->startOfDay();
        $inicio = Carbon::parse($fechaInicioActual)->startOfDay();
        $diferencia = $termino->diffInDays($inicio, false);

        return $diferencia >= 0 && $diferencia <= 1;
    }




    private function buildSolicitudesExportQuery(Request $request, string $scope)
    {
        $query = SolicitudReemplazo::query()
            ->with([
                'establecimiento',
                'funcionarioTitular',
                'postulante.user',
                'contratoPostulante.user',
                'areaDesempeno',
                'derivadaA',
                'uatpDecisionUser',
                'planiDecisionUser',
                'jornadas',
            ]);

        if ($scope === 'uatp') {
            $query->where('estado', 'pendiente_uatp');
            $this->applySolicitudesExportFilters($query, $request, 'p');
            return $query->orderBy('created_at', 'asc');
        }

        if ($scope === 'validacion') {
            $query->where('estado', 'pendiente_validacion');
            $this->applySolicitudesExportFilters($query, $request, 'v');
            return $query->orderBy('uatp_decision_at', 'asc')->orderBy('created_at', 'asc');
        }

        $query->where('estado', '<>', 'pendiente_uatp');
        $this->applySolicitudesExportFilters($query, $request, 'o');

        return $query->orderBy('created_at', 'desc');
    }

    private function applySolicitudesExportFilters($query, Request $request, string $prefix): void
    {
        if ($request->filled($prefix . '_numero')) {
            $query->where('numero_solicitud', 'like', '%' . trim((string) $request->input($prefix . '_numero')) . '%');
        }

        if ($request->filled($prefix . '_establecimiento_id')) {
            $query->where('establecimiento_id', (int) $request->input($prefix . '_establecimiento_id'));
        }

        if ($request->filled($prefix . '_estado')) {
            $query->where('estado', (string) $request->input($prefix . '_estado'));
        }

        if ($request->filled($prefix . '_desde')) {
            $dateField = $prefix === 'v' ? 'uatp_decision_at' : 'created_at';
            $query->whereDate($dateField, '>=', $request->date($prefix . '_desde')->format('Y-m-d'));
        }

        if ($request->filled($prefix . '_hasta')) {
            $dateField = $prefix === 'v' ? 'uatp_decision_at' : 'created_at';
            $query->whereDate($dateField, '<=', $request->date($prefix . '_hasta')->format('Y-m-d'));
        }

        if ($request->filled($prefix . '_titular')) {
            $term = '%' . trim((string) $request->input($prefix . '_titular')) . '%';
            $query->whereHas('funcionarioTitular', function ($q) use ($term) {
                $q->where('rut', 'like', $term)
                    ->orWhere('nombre', 'like', $term);
            });
        }

        if ($prefix === 'o' && $request->filled('o_reemplazo')) {
            $term = '%' . trim((string) $request->input('o_reemplazo')) . '%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('postulante.user', function ($qq) use ($term) {
                    $qq->where('rut', 'like', $term)
                        ->orWhere('nombres', 'like', $term)
                        ->orWhere('apellido_paterno', 'like', $term)
                        ->orWhere('apellido_materno', 'like', $term);
                })->orWhereHas('contratoPostulante.user', function ($qq) use ($term) {
                    $qq->where('rut', 'like', $term)
                        ->orWhere('nombres', 'like', $term)
                        ->orWhere('apellido_paterno', 'like', $term)
                        ->orWhere('apellido_materno', 'like', $term);
                });
            });
        }

        if ($prefix === 'o' && $request->filled('o_derivada_a')) {
            $query->where('derivada_a_user_id', (int) $request->input('o_derivada_a'));
        }
    }

    private function estadoSolicitudLabel(?string $estado): string
    {
        return match ($estado) {
            'pendiente_uatp' => 'Pendiente UATP',
            'pendiente_validacion' => 'Pendiente de Validacion',
            'pendiente_gdp' => 'Pendiente GDP',
            'derivada_slep' => 'Derivada a SLEP',
            'aceptada' => 'Aceptada',
            'cerrado' => 'Cerrado',
            'rechazada' => 'Rechazada',
            'rechazada_uatp' => 'Rechazada UATP',
            'rechazada_plani' => 'Rechazada Planificacion',
            'rechazada_gdp' => 'Rechazada GDP',
            'anulada' => 'Anulada',
            default => (string) $estado,
        };
    }

    private function userNombreExport($user): string
    {
        if (!$user) {
            return '';
        }

        $nombre = trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '') . ' ' . ($user->nombres ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($user->name ?? ''));
        }

        return trim($nombre . (!empty($user->rut) ? ' (' . $user->rut . ')' : ''));
    }

    private function userNombreCompletoExport($user): string
    {
        if (!$user) {
            return '';
        }

        $nombre = trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '') . ' ' . ($user->nombres ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($user->name ?? ''));
        }

        return $this->uppercaseExport($nombre);
    }

    private function uppercaseExport($value): string
    {
        return mb_strtoupper($this->cleanCsvText($value), 'UTF-8');
    }

    private function replacementEffectiveHoursExport(SolicitudReemplazo $solicitud): array
    {
        $hours = [
            'general_basica' => '',
            'general_media' => '',
            'sep_basica' => '',
            'sep_media' => '',
            'pie_basica' => '',
            'pie_media' => '',
        ];

        if (!in_array((string) $solicitud->estado, ['aceptada', 'cerrado', 'cerrada'], true)) {
            return $hours;
        }

        $totals = array_fill_keys(array_keys($hours), 0.0);

        foreach (($solicitud->jornadas ?? collect()) as $jornada) {
            $financing = $this->replacementFinancingExportKey($jornada->financiamiento);
            if ($financing === null) {
                continue;
            }

            $totals[$financing . '_basica'] += (float) ($jornada->reemplazo_basica ?? 0);
            $totals[$financing . '_media'] += (float) ($jornada->reemplazo_media ?? 0);
        }

        return array_map(fn (float $value): string => $this->formatDecimalExport($value), $totals);
    }

    private function replacementFinancingExportKey(?string $financing): ?string
    {
        $normalized = mb_strtoupper(trim((string) $financing), 'UTF-8');
        $normalized = strtr($normalized, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);
        $normalized = trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $normalized));

        if (preg_match('/(^| )(SEP|S E P)($| )/', $normalized) === 1) {
            return 'sep';
        }

        if (preg_match('/(^| )(PIE|P I E)($| )/', $normalized) === 1) {
            return 'pie';
        }

        if (str_contains($normalized, 'GENERAL') || preg_match('/(^| )GRAL($| )/', $normalized) === 1) {
            return 'general';
        }

        return null;
    }

    private function formatDecimalExport($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, ',', '');
    }

    private function cleanCsvText($value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) ($value ?? '')));
    }

private function userHasAllRequiredDocsUploaded(\App\Models\User $u, $types): bool
    {
        // Para el tag "(documentos sin cargar)" consideramos solo existencia de archivo (path),
        // no el estado de revisión.
        $u->loadMissing(['postulantProfile', 'documents']);

        $docsByType = $u->documents->keyBy('document_type_id');
        $required = $types->filter(fn(\App\Models\DocumentType $t) => $t->isRequiredForUser($u));

        foreach ($required as $t) {
            $doc = $docsByType->get($t->id);
            if (!$doc || blank($doc->path)) {
                return false;
            }
        }
        return true;
    }


    private function formatRutChile(?string $rut): string
    {
        $rut = trim((string) $rut);
        if ($rut === '') {
            return '';
        }

        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));

        if (mb_strlen($rut) < 2) {
            return $rut;
        }

        $dv = mb_substr($rut, -1);
        $cuerpo = mb_substr($rut, 0, -1);

        $cuerpoFormateado = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $cuerpo);

        return $cuerpoFormateado . '-' . $dv;
    }
}
