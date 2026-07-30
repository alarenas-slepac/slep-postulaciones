<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Models\CometidoFuncionario;
use App\Models\CometidoNotificacionConfiguracion;
use App\Models\CometidoFuncionarioCdpMonto;
use App\Models\CometidoFuncionarioDocumento;
use App\Models\CometidoFuncionarioHistorial;
use App\Models\Commune;
use App\Models\Establecimiento;
use App\Models\FuncionarioViaticoAnexo;
use App\Models\FuncionarioAcAutorizado;
use App\Models\CometidoFuncionarioDocumentoGenerado;
use App\Models\CometidoFuncionarioPasajeAereo;
use App\Models\ReemplazoPersonal;
use App\Models\ViaticoReembolsoValor;
use App\Models\ViaticoDisponibilidadMovimiento;
use App\Models\ViaticoDisponibilidadPresupuestaria;
use App\Models\User;
use App\Mail\CometidoFuncionarioNotificationMail;
use App\Support\RutChile;
use App\Support\Cometidos\BusinessDaysCalculator;
use App\Support\Cometidos\CometidoFuncionarioPdfService;
use App\Support\Cometidos\FuncionarioAcAutorizadorResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ZipArchive;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CometidoFuncionarioController extends Controller
{
    private array $mediosTransporte = [
        'Vehículo particular',
        'Vehículo institucional',
        'Estacionamiento',
        'Microbús',
        'Avión',
        'Taxi',
        'Peaje',
        'Transfer',
        'Avioneta',
        'Embarcación',
        'Bus interprovincial / tren',
        'Otro',
    ];

    private array $motivos = [
        'Asistencia a curso y/o actividad de capacitación',
        'Concurrir a citación',
        'Otras',
        'Por traslado de consejeros',
        'Por traslado de funcionarios',
        'Practicar notificación/es',
        'Reunión en otra dependencia del servicio',
        'Reunión fuera del servicio',
        'Visita inspectiva o de fiscalización',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $usaBandejasInternas = ! in_array($activeRole, ['funcionario_estab', 'funcionario_ac'], true);
        $tabsBandejasInternas = ['ac_por_autorizar', 'ac_autorizados', 'estab_por_autorizar', 'estab_autorizados'];
        $activeCometidosTab = $usaBandejasInternas
            ? (in_array((string) $request->query('tab'), $tabsBandejasInternas, true) ? (string) $request->query('tab') : 'ac_por_autorizar')
            : 'establecimiento';

        $baseQuery = CometidoFuncionario::query()
            ->with(['establecimiento', 'solicitante', 'funcionarioAcAutorizado'])
            ->latest();

        $this->applyIndexRoleScope($baseQuery, $user, $activeRole);

        $puedeFiltrarEstablecimiento = $activeRole !== 'funcionario_estab';
        $establecimientos = collect();

        if ($puedeFiltrarEstablecimiento) {
            $establecimientoIds = (clone $baseQuery)
                ->whereNotNull('establecimiento_id')
                ->select('establecimiento_id')
                ->distinct()
                ->pluck('establecimiento_id')
                ->filter()
                ->values();

            if ($establecimientoIds->isNotEmpty()) {
                $establecimientos = Establecimiento::query()
                    ->whereIn('id', $establecimientoIds)
                    ->orderBy('nombre_establecimiento')
                    ->get(['id', 'nombre_establecimiento', 'rbd']);
            }
        }

        $estados = CometidoFuncionario::ESTADOS;

        $cometidos = null;
        $cometidosPorAutorizar = null;
        $cometidosAutorizados = null;
        $cuentaPorAutorizar = 0;
        $cuentaAutorizados = 0;
        $cometidosAcPorAutorizar = null;
        $cometidosAcAutorizados = null;
        $cometidosEstabPorAutorizar = null;
        $cometidosEstabAutorizados = null;
        $cuentaAcPorAutorizar = 0;
        $cuentaAcAutorizados = 0;
        $cuentaEstabPorAutorizar = 0;
        $cuentaEstabAutorizados = 0;

        if ($usaBandejasInternas) {
            $porAutorizarAcQuery = clone $baseQuery;
            $porAutorizarAcQuery->where('origen_cometido', 'administracion_central');
            if ($activeCometidosTab === 'ac_por_autorizar') {
                $this->applyIndexRequestFilters($porAutorizarAcQuery, $request, $puedeFiltrarEstablecimiento);
            }
            $this->applyBandejaCometidosScope($porAutorizarAcQuery, $user, $activeRole, 'por_autorizar');
            $cuentaAcPorAutorizar = (clone $porAutorizarAcQuery)->count();
            $cometidosAcPorAutorizar = $porAutorizarAcQuery
                ->paginate(10, ['*'], 'ac_por_autorizar_page')
                ->withQueryString();

            $autorizadosAcQuery = clone $baseQuery;
            $autorizadosAcQuery->where('origen_cometido', 'administracion_central');
            if ($activeCometidosTab === 'ac_autorizados') {
                $this->applySeguimientoRequestFilters($autorizadosAcQuery, $request, $puedeFiltrarEstablecimiento);
            }
            $this->applyBandejaCometidosScope($autorizadosAcQuery, $user, $activeRole, 'autorizados');
            $cuentaAcAutorizados = (clone $autorizadosAcQuery)->count();
            $cometidosAcAutorizados = $autorizadosAcQuery
                ->paginate(10, ['*'], 'ac_autorizados_page')
                ->withQueryString();

            $porAutorizarEstabQuery = clone $baseQuery;
            $porAutorizarEstabQuery->where(function ($q) {
                $q->where('origen_cometido', 'establecimiento')
                    ->orWhereNull('origen_cometido');
            });
            if ($activeCometidosTab === 'estab_por_autorizar') {
                $this->applyIndexRequestFilters($porAutorizarEstabQuery, $request, $puedeFiltrarEstablecimiento);
            }
            $this->applyBandejaCometidosScope($porAutorizarEstabQuery, $user, $activeRole, 'por_autorizar');
            $cuentaEstabPorAutorizar = (clone $porAutorizarEstabQuery)->count();
            $cometidosEstabPorAutorizar = $porAutorizarEstabQuery
                ->paginate(10, ['*'], 'estab_por_autorizar_page')
                ->withQueryString();

            $autorizadosEstabQuery = clone $baseQuery;
            $autorizadosEstabQuery->where(function ($q) {
                $q->where('origen_cometido', 'establecimiento')
                    ->orWhereNull('origen_cometido');
            });
            if ($activeCometidosTab === 'estab_autorizados') {
                $this->applySeguimientoRequestFilters($autorizadosEstabQuery, $request, $puedeFiltrarEstablecimiento);
            }
            $this->applyBandejaCometidosScope($autorizadosEstabQuery, $user, $activeRole, 'autorizados');
            $cuentaEstabAutorizados = (clone $autorizadosEstabQuery)->count();
            $cometidosEstabAutorizados = $autorizadosEstabQuery
                ->paginate(10, ['*'], 'estab_autorizados_page')
                ->withQueryString();

            $cometidosPorAutorizar = $cometidosAcPorAutorizar;
            $cometidosAutorizados = $cometidosAcAutorizados;
            $cuentaPorAutorizar = $cuentaAcPorAutorizar + $cuentaEstabPorAutorizar;
            $cuentaAutorizados = $cuentaAcAutorizados + $cuentaEstabAutorizados;
        } else {
            $q = clone $baseQuery;
            $this->applyIndexRequestFilters($q, $request, $puedeFiltrarEstablecimiento);
            $cometidos = $q->paginate(15)->withQueryString();
        }

        return view('tramites.cometidos-funcionarios.index', compact(
            'cometidos',
            'cometidosPorAutorizar',
            'cometidosAutorizados',
            'cometidosAcPorAutorizar',
            'cometidosAcAutorizados',
            'cometidosEstabPorAutorizar',
            'cometidosEstabAutorizados',
            'cuentaPorAutorizar',
            'cuentaAutorizados',
            'cuentaAcPorAutorizar',
            'cuentaAcAutorizados',
            'cuentaEstabPorAutorizar',
            'cuentaEstabAutorizados',
            'usaBandejasInternas',
            'activeCometidosTab',
            'estados',
            'activeRole',
            'establecimientos',
            'puedeFiltrarEstablecimiento'
        ));
    }

    private function applyIndexRequestFilters($query, Request $request, bool $puedeFiltrarEstablecimiento): void
    {
        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        if ($puedeFiltrarEstablecimiento && $request->filled('establecimiento_id')) {
            $query->where('establecimiento_id', (int) $request->query('establecimiento_id'));
        }

        if ($request->filled('nombre')) {
            $nombre = trim((string) $request->query('nombre'));
            $query->where('funcionario_nombre', 'like', '%' . $nombre . '%');
        }

        if ($request->filled('rut')) {
            $rutNormalizado = strtoupper((string) preg_replace('/[^0-9kK]/', '', (string) $request->query('rut')));
            if ($rutNormalizado !== '') {
                $query->whereRaw(
                    "REPLACE(REPLACE(REPLACE(UPPER(COALESCE(funcionario_rut, '')), '.', ''), '-', ''), ' ', '') LIKE ?",
                    ['%' . $rutNormalizado . '%']
                );
            }
        }
    }

    private function applySeguimientoRequestFilters($query, Request $request, bool $puedeFiltrarEstablecimiento): void
    {
        if ($request->filled('seguimiento_estado')) {
            $query->where('estado', $request->query('seguimiento_estado'));
        }

        if ($puedeFiltrarEstablecimiento && $request->filled('seguimiento_establecimiento_id')) {
            $query->where('establecimiento_id', (int) $request->query('seguimiento_establecimiento_id'));
        }

        if ($request->filled('seguimiento_mes')) {
            $mes = (string) $request->query('seguimiento_mes');
            if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
                $inicio = Carbon::createFromFormat('Y-m', $mes)->startOfMonth();
                $termino = (clone $inicio)->endOfMonth();
                $query->whereBetween('fecha_solicitud', [$inicio->toDateString(), $termino->toDateString()]);
            }
        }
    }

    private function applyBandejaCometidosScope($query, $user, ?string $activeRole, string $tipo): void
    {
        if ($tipo === 'por_autorizar') {
            $this->applyBandejaPorAutorizarScope($query, $user, $activeRole);
            return;
        }

        $query->whereNotIn('estado', ['borrador']);
        $query->where(function ($q) use ($user, $activeRole) {
            $q->whereRaw('1 = 1');
        });
        $this->applyBandejaAutorizadosScope($query, $user, $activeRole);
    }

    private function applyBandejaPorAutorizarScope($query, $user, ?string $activeRole): void
    {
        if ($activeRole === 'admin' || $this->userHasAnyRole($user, ['admin'])) {
            $query->where(function ($q) {
                $q->whereIn('estado', [
                        'en_revision_uatp',
                        'en_revision_jefatura_ac',
                        'en_revision_cdp',
                        'pendiente_autorizacion_director_sin_disponibilidad',
                        'en_gdp_resolucion',
                        'en_gdp_rex_cgr',
                        'informe_pendiente_jefatura',
                        'en_daf_viatico',
                        'en_daf_contable_viatico',
                        'en_pago_viatico',
                        'pendiente_rendicion_informe',
                        'rendicion_enviada_pendiente_informe',
                        'en_revision_daf_rendicion',
                        'rendicion_rectificada_pendiente_daf',
                        'en_revision_cdp_rendicion',
                        'en_juridica_resolucion_reembolso',
                        'en_daf_contable_reembolso',
                        'en_pago_reembolso',
                    ])
                    ->orWhere('requiere_pasaje_aereo', true)
                    ->orWhereIn('estado_viatico', ['en_revision_cdp', 'sin_disponibilidad', 'en_gdp_resolucion', 'informe_pendiente_jefatura', 'en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico'])
                    ->orWhereIn('estado_reembolso', [
                        'pendiente_rendicion_informe',
                        'rendicion_enviada_pendiente_informe',
                        'en_revision_daf_rendicion',
                        'rendicion_rectificada_pendiente_daf',
                        'en_revision_cdp_rendicion',
                        'en_juridica_resolucion_reembolso',
                        'en_daf_contable_reembolso',
                        'en_pago_reembolso',
                    ]);
            });
            return;
        }

        if ($activeRole === 'director_ejecutivo' || $this->userHasAnyRole($user, ['director_ejecutivo'])) {
            $this->applyDirectorEjecutivoPorAutorizarScope($query, $user);
            return;
        }

        if ($activeRole === 'coordinador_uatp' || $this->userHasAnyRole($user, ['coordinador_uatp'])) {
            $query->where('estado', 'en_revision_uatp');
            return;
        }

        if ($activeRole === 'funcionario_daf_compra' || $this->userHasAnyRole($user, ['funcionario_daf_compra'])) {
            $query->where('requiere_pasaje_aereo', true)
                ->whereHas('pasajeAereo', function ($q) {
                    $q->whereIn('estado_pasaje', ['pendiente_reserva', 'pendiente_compra']);
                });
            return;
        }

        if (in_array($activeRole, ['supervisor_plani', 'coordinador_plani'], true) || $this->userHasAnyRole($user, ['supervisor_plani', 'coordinador_plani'])) {
            $query->where(function ($q) {
                $q->whereIn('estado', ['en_revision_cdp', 'en_revision_cdp_rendicion'])
                    ->orWhereIn('estado_viatico', ['en_revision_cdp'])
                    ->orWhereIn('estado_reembolso', ['en_revision_cdp_rendicion'])
                    ->orWhereHas('pasajeAereo', function ($pasaje) {
                        $pasaje->where('estado_pasaje', 'pendiente_cdp_pasaje');
                    });
            });
            return;
        }

        if (in_array($activeRole, ['coordinador_gdp', 'funcionario_slep'], true) || $this->userHasAnyRole($user, ['coordinador_gdp', 'funcionario_slep'])) {
            $query->where(function ($q) {
                $q->whereIn('estado', ['en_gdp_resolucion', 'en_gdp_rex_cgr'])
                    ->orWhereIn('estado_viatico', ['en_gdp_resolucion'])
                    ->orWhereIn('estado_reembolso', ['en_gdp_rex_cgr']);
            });
            return;
        }

        if ($activeRole === 'funcionario_daf' || $this->userHasAnyRole($user, ['funcionario_daf'])) {
            $query->where(function ($q) {
                $q->whereIn('estado', ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'en_daf_contable_reembolso', 'en_pago_reembolso'])
                    ->orWhereIn('estado_viatico', ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico'])
                    ->orWhereIn('estado_reembolso', ['en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'en_daf_contable_reembolso', 'en_pago_reembolso']);
            });
            return;
        }


        if (in_array($activeRole, ['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica'], true) || $this->userHasAnyRole($user, ['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica'])) {
            $query->where(function ($q) {
                $q->whereIn('estado', ['en_juridica_resolucion_reembolso', 'observada_juridica_reembolso'])
                    ->orWhereIn('estado_reembolso', ['en_juridica_resolucion_reembolso', 'observada_juridica_reembolso']);
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyBandejaAutorizadosScope($query, $user, ?string $activeRole): void
    {
        if ($activeRole === 'admin' || $this->userHasAnyRole($user, ['admin'])) {
            $query->whereNotIn('estado', [
                'borrador',
                'en_revision_uatp',
                'en_revision_cdp',
                'pendiente_autorizacion_director_sin_disponibilidad',
                'en_gdp_resolucion',
                'en_gdp_rex_cgr',
                'informe_pendiente_jefatura',
                'en_daf_viatico',
                'en_daf_contable_viatico',
                'en_pago_viatico',
                'pendiente_rendicion_informe',
                'rendicion_enviada_pendiente_informe',
                'en_revision_daf_rendicion',
                'rendicion_rectificada_pendiente_daf',
                'en_revision_cdp_rendicion',
                'en_juridica_resolucion_reembolso',
                'en_daf_contable_reembolso',
                'en_pago_reembolso',
            ])->where(function ($q) {
                $q->whereNotIn('estado_viatico', ['en_revision_cdp', 'sin_disponibilidad', 'en_gdp_resolucion', 'informe_pendiente_jefatura', 'en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico'])
                    ->orWhereNull('estado_viatico');
            })->where(function ($q) {
                $q->whereNotIn('estado_reembolso', ['pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'en_revision_cdp_rendicion', 'en_juridica_resolucion_reembolso', 'en_daf_contable_reembolso', 'en_pago_reembolso'])
                    ->orWhereNull('estado_reembolso');
            });
            return;
        }

        if ($activeRole === 'director_ejecutivo' || $this->userHasAnyRole($user, ['director_ejecutivo'])) {
            $this->applyDirectorEjecutivoAutorizadosScope($query, $user);
            return;
        }

        if ($activeRole === 'coordinador_uatp' || $this->userHasAnyRole($user, ['coordinador_uatp'])) {
            $query->whereNotIn('estado', ['borrador', 'en_revision_uatp', 'observado_uatp', 'rechazado_uatp']);
            return;
        }

        if (in_array($activeRole, ['supervisor_plani', 'coordinador_plani'], true) || $this->userHasAnyRole($user, ['supervisor_plani', 'coordinador_plani'])) {
            $query->where(function ($q) {
                $q->where('cdp_aprobado', true)
                    ->orWhereIn('estado', [
                        'pendiente_autorizacion_director_sin_disponibilidad',
                        'reconvertido_a_reembolso_por_sin_disponibilidad',
                        'rechazado_director_sin_disponibilidad',
                        'en_gdp_resolucion',
                        'en_gdp_rex_cgr',
                        'autorizado_sin_gasto',
                        'resolucion_cometido_emitida',
                        'en_daf_viatico',
                        'viatico_pagado',
                        'en_gestion_paralela',
                        'rendicion_autorizada_daf',
                        'en_juridica_resolucion_reembolso',
                        'resolucion_reembolso_emitida',
                        'en_pago_reembolso',
                        'reembolso_pagado',
                        'cerrado_sin_pago_reembolso',
                        'cerrado',
                    ])
                    ->orWhereIn('estado_viatico', ['en_gdp_resolucion', 'en_daf_viatico', 'viatico_pagado'])
                    ->orWhereIn('estado_reembolso', [
                        'en_juridica_resolucion_reembolso',
                        'observada_juridica_reembolso',
                        'resolucion_reembolso_emitida',
                        'en_pago_reembolso',
                        'reembolso_pagado',
                        'cerrado_sin_pago_reembolso',
                        'cerrado',
                    ]);
            })->where(function ($q) {
                $q->whereNotIn('estado_viatico', ['en_revision_cdp'])
                    ->orWhereNull('estado_viatico');
            })->where(function ($q) {
                $q->whereNotIn('estado_reembolso', ['en_revision_cdp_rendicion'])
                    ->orWhereNull('estado_reembolso');
            });
            return;
        }

        if (in_array($activeRole, ['coordinador_gdp', 'funcionario_slep'], true) || $this->userHasAnyRole($user, ['coordinador_gdp', 'funcionario_slep'])) {
            $query->where(function ($q) {
                $q->whereIn('estado', [
                        'resolucion_cometido_emitida',
                        'en_daf_viatico',
                        'viatico_pagado',
                        'informe_pendiente_funcionario',
                        'informe_pendiente_jefatura',
                        'informe_observado',
                        'informe_aprobado',
                        'pendiente_rendicion',
                        'pendiente_rendicion_informe',
                        'rendicion_enviada_pendiente_informe',
                        'en_revision_daf_rendicion',
                        'rendicion_autorizada_daf',
                        'en_revision_cdp_rendicion',
                        'en_juridica_resolucion_reembolso',
                        'resolucion_reembolso_emitida',
                        'en_pago_reembolso',
                        'reembolso_pagado',
                        'cerrado_sin_pago_reembolso',
                        'cerrado',
                    ])
                    ->orWhereIn('estado_viatico', ['en_daf_viatico', 'viatico_pagado']);
            })->where(function ($q) {
                $q->whereNotIn('estado_viatico', ['en_gdp_resolucion'])
                    ->orWhereNull('estado_viatico');
            });
            return;
        }

        if ($activeRole === 'funcionario_daf' || $this->userHasAnyRole($user, ['funcionario_daf'])) {
            $query->where(function ($q) {
                $q->whereIn('estado', [
                        'en_daf_contable_viatico',
                        'en_pago_viatico',
                        'viatico_pagado',
                        'rendicion_rectificada_pendiente_daf',
                        'rendicion_autorizada_daf',
                        'en_revision_cdp_rendicion',
                        'en_juridica_resolucion_reembolso',
                        'resolucion_reembolso_emitida',
                        'en_daf_contable_reembolso',
                        'en_pago_reembolso',
                        'reembolso_pagado',
                        'cerrado_sin_pago_reembolso',
                        'cerrado',
                    ])
                    ->orWhereIn('estado_viatico', ['en_daf_contable_viatico', 'en_pago_viatico', 'viatico_pagado'])
                    ->orWhereIn('estado_reembolso', [
                        'rendicion_autorizada_daf',
                        'en_revision_cdp_rendicion',
                        'en_juridica_resolucion_reembolso',
                        'resolucion_reembolso_emitida',
                        'en_daf_contable_reembolso',
                        'en_pago_reembolso',
                        'reembolso_pagado',
                        'cerrado_sin_pago_reembolso',
                        'cerrado',
                    ]);
            })->where(function ($q) {
                $q->whereNotIn('estado_viatico', ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico'])
                    ->orWhereNull('estado_viatico');
            })->where(function ($q) {
                $q->whereNotIn('estado_reembolso', ['en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'en_daf_contable_reembolso', 'en_pago_reembolso'])
                    ->orWhereNull('estado_reembolso');
            });
            return;
        }

        if (in_array($activeRole, ['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica'], true) || $this->userHasAnyRole($user, ['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica'])) {
            $query->where(function ($q) {
                $q->whereNotIn('estado', ['en_juridica_resolucion_reembolso', 'observada_juridica_reembolso'])
                    ->orWhereNull('estado');
            })->where(function ($q) {
                $q->whereNotIn('estado_reembolso', ['en_juridica_resolucion_reembolso', 'observada_juridica_reembolso'])
                    ->orWhereNull('estado_reembolso');
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyDirectorEjecutivoPorAutorizarScope($query, $user): void
    {
        $directorFuncionarioAc = app(FuncionarioAcAutorizadorResolver::class)->funcionarioParaUsuario($user);

        $query->where(function ($q) use ($directorFuncionarioAc) {
            $q->where('estado', 'pendiente_autorizacion_director_sin_disponibilidad')
                ->orWhere(function ($ac) use ($directorFuncionarioAc) {
                    $ac->where('origen_cometido', 'administracion_central')
                        ->where('estado', 'en_revision_jefatura_ac');

                    $this->applyDirectorEjecutivoJefaturaAcScope($ac, $directorFuncionarioAc);
                });
        });
    }

    private function applyDirectorEjecutivoAutorizadosScope($query, $user): void
    {
        $query->whereNotIn('estado', ['borrador'])
            ->where(function ($q) {
                $q->where('estado', '<>', 'pendiente_autorizacion_director_sin_disponibilidad')
                    ->orWhereNull('estado');
            })
            ->where(function ($q) {
                $q->where(function ($noAc) {
                    $noAc->where('origen_cometido', '<>', 'administracion_central')
                        ->orWhereNull('origen_cometido');
                })->orWhere(function ($ac) {
                    $ac->where('origen_cometido', 'administracion_central')
                        ->where(function ($estado) {
                            $estado->where('estado', '<>', 'en_revision_jefatura_ac')
                                ->orWhereNull('estado');
                        });
                });
            });
    }

    private function applyDirectorEjecutivoJefaturaAcScope($query, ?FuncionarioAcAutorizado $directorFuncionarioAc): void
    {
        $query->where(function ($scope) use ($directorFuncionarioAc) {
            if ($directorFuncionarioAc) {
                $scope->orWhere('jefatura_autorizadora_ac_id', $directorFuncionarioAc->id);
            }

            $scope->orWhereHas('funcionarioAcAutorizado', function ($funcionario) use ($directorFuncionarioAc) {
                $funcionario->where(function ($f) use ($directorFuncionarioAc) {
                    if ($directorFuncionarioAc) {
                        $f->where('id', '<>', $directorFuncionarioAc->id);
                    }

                    $f->whereRaw("UPPER(COALESCE(cargo_funcion, '')) NOT LIKE ?", ['%DIRECTOR%EJECUTIVO%']);
                })->where(function ($f) {
                    $f->where('jefatura', true)
                        ->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(subdireccion_dependencia, ''), 'á', 'A'), 'é', 'E'), 'í', 'I'), 'ó', 'O'), 'ú', 'U'), 'Á', 'A'), 'É', 'E'), 'Í', 'I')) LIKE ?", ['%DIRECCI%N EJECUTIVA%']);
                });
            });
        });
    }

    private function applyIndexRoleScope($query, $user, ?string $activeRole): void
    {
        if ($activeRole === 'funcionario_ac') {
            $funcionarioAc = app(FuncionarioAcAutorizadorResolver::class)->funcionarioParaUsuario($user);
            abort_unless($funcionarioAc, 403, 'No existe funcionario AC autorizado asociado a tu usuario.');
            $query->where('origen_cometido', 'administracion_central')
                ->where(function ($q) use ($funcionarioAc) {
                    $q->where('funcionario_ac_autorizado_id', $funcionarioAc->id)
                        ->orWhere('jefatura_autorizadora_ac_id', $funcionarioAc->id);
                });
            return;
        }

        if ($activeRole === 'funcionario_estab') {
            $establecimiento = $this->establecimientoDelUsuario();
            $query->where('establecimiento_id', $establecimiento->id);
            return;
        }

        $rolesInternosCometidos = [
            'admin',
            'funcionario_slep',
            'coordinador_gdp',
            'coordinador_uatp',
            'director_ejecutivo',
            'supervisor_plani',
            'coordinador_plani',
            'funcionario_daf',
            'funcionario_daf_compra',
            'funcionario_ac',
            'juridica',
            'juridico',
            'abogado_juridica',
            'coordinador_juridica',
            'funcionario_juridica',
        ];

        abort_unless($this->userHasAnyRole($user, $rolesInternosCometidos), 403);

        if ($activeRole !== 'admin') {
            $query->whereNotIn('estado', ['borrador']);
        }
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if (in_array($activeRole, ['funcionario_ac', 'director_ejecutivo'], true)) {
            $funcionarioAc = app(FuncionarioAcAutorizadorResolver::class)->funcionarioParaUsuario($user);
            abort_unless($funcionarioAc, 403, 'No existe un funcionario AC autorizado asociado a tu usuario.');

            return view('tramites.cometidos-funcionarios.form', $this->formDataAc(new CometidoFuncionario([
                'fecha_solicitud' => now(),
                'estado' => 'borrador',
                'origen_cometido' => 'administracion_central',
            ]), $funcionarioAc));
        }

        $establecimiento = $this->establecimientoDelUsuario();
        [$periodo, $funcionarios] = $this->funcionariosUltimoPadron($establecimiento);

        return view('tramites.cometidos-funcionarios.form', $this->formData(new CometidoFuncionario([
            'fecha_solicitud' => now(),
            'estado' => 'borrador',
        ]), $establecimiento, $funcionarios, $periodo));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if (in_array($activeRole, ['funcionario_ac', 'director_ejecutivo'], true)) {
            return $this->storeFuncionarioAc($request);
        }

        $establecimiento = $this->establecimientoDelUsuario();
        [$periodo, $funcionarios] = $this->funcionariosUltimoPadron($establecimiento);
        $funcionario = $this->funcionarioPadronSeleccionado($request, $establecimiento, $funcionarios);
        $data = $this->validatedData($request, $funcionario, $establecimiento, null);
        $estado = $request->input('accion') === 'enviar' ? 'en_revision_uatp' : 'borrador';

        $cometido = DB::transaction(function () use ($request, $data, $establecimiento, $funcionario, $estado) {
            $cometido = CometidoFuncionario::create(array_merge($data, [
                'user_id' => $request->user()->id,
                'establecimiento_id' => $establecimiento->id,
                'rbd' => $establecimiento->rbd ?? $establecimiento->cod_estab ?? null,
                'estado' => $estado,
                'fecha_solicitud' => now()->toDateString(),
                'reemplazo_personal_id' => $funcionario->id,
                'funcionario_rut' => $funcionario->rut,
                'funcionario_nombre' => $funcionario->nombre,
                'calidad_juridica' => $funcionario->tipocontrato,
                'estamento' => $funcionario->estatuto,
                'cargo_funcion' => $funcionario->escalafon,
                'origen_cometido' => 'establecimiento',
            ]));

            $this->guardarCitacion($request, $cometido);
            $this->guardarDocumentosFormulario($request, $cometido);
            $this->registrarHistorial($cometido, null, $estado, $estado === 'en_revision_uatp' ? 'Solicitud enviada por establecimiento' : 'Borrador creado');

            return $cometido;
        });

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', $estado === 'en_revision_uatp' ? 'Solicitud enviada a revisión UATP.' : 'Borrador guardado.');
    }

    public function show(Request $request, CometidoFuncionario $cometido)
    {
        $this->authorizeCometido($request, $cometido);
        $cometido->load(['establecimiento', 'solicitante', 'funcionarioPadron', 'funcionarioAcAutorizado', 'historial.usuario', 'documentos', 'documentosGenerados.firmas', 'firmasDigitales', 'pasajeAereo', 'cdpMontos.catalogoValor', 'informeCometidoActual']);
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        $puedeRevisarUatp = $this->userHasAnyRole($user, ['admin', 'coordinador_uatp']);
        $puedeRevisarJefaturaAc = $this->puedeRevisarJefaturaAc($request, $cometido);
        $puedeGestionarPasajeReserva = $this->userHasAnyRole($user, ['admin', 'funcionario_daf_compra']);
        $puedeGestionarPasajeCdp = $this->userHasAnyRole($user, ['admin', 'supervisor_plani', 'coordinador_plani']);
        $puedeRevisarCdp = $this->userHasAnyRole($user, ['admin', 'supervisor_plani', 'coordinador_plani']);
        $puedeVerBandejaGdp = $this->userHasAnyRole($user, ['admin', 'coordinador_gdp', 'funcionario_slep']);
        $cdpValoresCatalogo = $this->valoresCatalogoCdp();
        $diasCometidoCdp = $this->diasCometido($cometido);
        $viaticoAutomaticoCdp = $this->calcularViaticoAutomaticoCdp($cometido);
        $catalogoReembolsoSugerido = $cometido->solicita_reembolso ? $this->catalogoAutomaticoViatico($cometido) : null;
        $puedeRegenerarSolicitudCometidoPdf = $this->puedeRegenerarSolicitudCometidoPdf($request, $cometido);

        return view('tramites.cometidos-funcionarios.show', compact(
            'cometido',
            'activeRole',
            'puedeRevisarUatp',
            'puedeRevisarJefaturaAc',
            'puedeGestionarPasajeReserva',
            'puedeGestionarPasajeCdp',
            'puedeRevisarCdp',
            'puedeVerBandejaGdp',
            'cdpValoresCatalogo',
            'diasCometidoCdp',
            'viaticoAutomaticoCdp',
            'catalogoReembolsoSugerido',
            'puedeRegenerarSolicitudCometidoPdf'
        ));
    }

    public function edit(Request $request, CometidoFuncionario $cometido)
    {
        $cometido->loadMissing('documentos');
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if (in_array($activeRole, ['funcionario_ac', 'director_ejecutivo'], true)) {
            $funcionarioAc = app(FuncionarioAcAutorizadorResolver::class)->funcionarioParaUsuario($user);
            abort_unless($funcionarioAc && (int) $cometido->funcionario_ac_autorizado_id === (int) $funcionarioAc->id, 403);
            abort_unless($cometido->esEditablePorFuncionarioAc(), 403);
            return view('tramites.cometidos-funcionarios.form', $this->formDataAc($cometido, $funcionarioAc));
        }

        $establecimiento = $this->establecimientoDelUsuario();
        abort_unless((int) $cometido->establecimiento_id === (int) $establecimiento->id, 403);
        abort_unless($cometido->esEditablePorEstablecimiento(), 403);

        [$periodo, $funcionarios] = $this->funcionariosUltimoPadron($establecimiento, $cometido->reemplazo_personal_id);

        return view('tramites.cometidos-funcionarios.form', $this->formData($cometido, $establecimiento, $funcionarios, $periodo));
    }

    public function update(Request $request, CometidoFuncionario $cometido)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if (in_array($activeRole, ['funcionario_ac', 'director_ejecutivo'], true)) {
            return $this->updateFuncionarioAc($request, $cometido);
        }

        $establecimiento = $this->establecimientoDelUsuario();
        abort_unless((int) $cometido->establecimiento_id === (int) $establecimiento->id, 403);
        abort_unless($cometido->esEditablePorEstablecimiento(), 403);

        [$periodo, $funcionarios] = $this->funcionariosUltimoPadron($establecimiento, $cometido->reemplazo_personal_id);
        $funcionario = $this->funcionarioPadronSeleccionado($request, $establecimiento, $funcionarios);
        $data = $this->validatedData($request, $funcionario, $establecimiento, $cometido);
        $estadoAnterior = $cometido->estado;
        $estadoNuevo = $request->input('accion') === 'enviar' ? 'en_revision_uatp' : 'borrador';

        if ($estadoAnterior === 'en_revision_uatp' && $cometido->uatp_decision === null && $cometido->uatp_revisado_at === null) {
            $estadoNuevo = 'en_revision_uatp';
        }

        DB::transaction(function () use ($request, $cometido, $data, $funcionario, $estadoAnterior, $estadoNuevo) {
            $cometido->update(array_merge($data, [
                'estado' => $estadoNuevo,
                'reemplazo_personal_id' => $funcionario->id,
                'funcionario_rut' => $funcionario->rut,
                'funcionario_nombre' => $funcionario->nombre,
                'calidad_juridica' => $funcionario->tipocontrato,
                'estamento' => $funcionario->estatuto,
                'cargo_funcion' => $funcionario->escalafon,
                'uatp_decision' => null,
                'uatp_observacion' => null,
            ]));

            $this->guardarCitacion($request, $cometido);
            $this->guardarDocumentosFormulario($request, $cometido);
            $accion = match (true) {
                $estadoAnterior === 'en_revision_uatp' && $estadoNuevo === 'en_revision_uatp' => 'Solicitud editada por establecimiento antes de intervención UATP',
                $estadoNuevo === 'en_revision_uatp' && $estadoAnterior === 'observado_uatp' => 'Solicitud corregida y reenviada por establecimiento',
                $estadoNuevo === 'en_revision_uatp' => 'Solicitud enviada por establecimiento',
                default => 'Borrador actualizado',
            };
            $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, $accion);
        });

        $mensaje = $estadoNuevo === 'en_revision_uatp'
            ? ($estadoAnterior === 'en_revision_uatp' ? 'Solicitud actualizada. Se mantiene en revisión UATP.' : 'Solicitud enviada a revisión UATP.')
            : 'Borrador actualizado.';

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', $mensaje);
    }

    public function destroy(Request $request, CometidoFuncionario $cometido)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if (in_array($activeRole, ['funcionario_ac', 'director_ejecutivo'], true)) {
            $funcionarioAc = app(FuncionarioAcAutorizadorResolver::class)->funcionarioParaUsuario($user);
            abort_unless($funcionarioAc && (int) $cometido->funcionario_ac_autorizado_id === (int) $funcionarioAc->id, 403);
            abort_unless($cometido->esEliminablePorFuncionarioAc(), 403);
        } else {
            $establecimiento = $this->establecimientoDelUsuario();
            abort_unless((int) $cometido->establecimiento_id === (int) $establecimiento->id, 403);
            abort_unless($cometido->esEliminablePorEstablecimiento(), 403);
        }

        DB::transaction(function () use ($cometido) {
            $cometido->loadMissing('documentos', 'documentosGenerados', 'pasajeAereo');

            foreach ($cometido->documentos as $documento) {
                if ($documento->path) {
                    Storage::delete($documento->path);
                }
            }
            foreach ($cometido->documentosGenerados as $documento) {
                if ($documento->archivo_pdf_path) {
                    Storage::delete($documento->archivo_pdf_path);
                }
            }
            foreach ($cometido->pasajeAereo as $pasaje) {
                foreach (['reserva_archivo_path', 'cdp_archivo_path', 'compra_archivo_path', 'solicitud_pedido_pdf_path'] as $field) {
                    if ($pasaje->{$field}) {
                        Storage::delete($pasaje->{$field});
                    }
                }
            }

            $cometido->documentos()->delete();
            $cometido->documentosGenerados()->delete();
            $cometido->firmasDigitales()->delete();
            $cometido->pasajeAereo()->delete();
            $cometido->cdpMontos()->delete();
            $cometido->historial()->delete();
            $cometido->delete();
        });

        return redirect()
            ->route('tramites.cometidos-funcionarios.index')
            ->with('success', 'Cometido funcionario eliminado correctamente antes de intervención de la unidad revisora.');
    }

    public function aprobarUatp(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'coordinador_uatp']), 403);
        abort_unless($cometido->estado === 'en_revision_uatp', 403);

        $data = $request->validate([
            'observacion' => ['nullable', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $estadoNuevo = $this->estadoPosteriorAprobacionUatp($cometido);
        $esParalelo = $cometido->solicita_viatico && $cometido->solicita_reembolso;
        $soloReembolso = (! $cometido->solicita_viatico) && $cometido->solicita_reembolso;
        $payload = [
            'estado' => $estadoNuevo,
            'uatp_revisado_por' => $request->user()->id,
            'uatp_revisado_at' => now(),
            'uatp_decision' => 'aprobado',
            'uatp_observacion' => $data['observacion'] ?? null,
        ];

        if ($esParalelo) {
            $payload['estado_viatico'] = 'en_revision_cdp';
            $payload['estado_reembolso'] = 'pendiente_rendicion';
        } elseif ($soloReembolso) {
            $payload['estado_viatico'] = null;
            $payload['estado_reembolso'] = 'pendiente_rendicion';
        } else {
            $payload['estado_viatico'] = $cometido->solicita_viatico ? $estadoNuevo : null;
            $payload['estado_reembolso'] = $cometido->solicita_reembolso ? $estadoNuevo : null;
        }

        $accion = $esParalelo
            ? 'UATP aprueba pertinencia pedagógica y bifurca el flujo: CDP inicial sólo para viático y rendición de reembolso habilitada en paralelo'
            : ($soloReembolso
                ? 'UATP aprueba pertinencia pedagógica y habilita rendición de reembolso por establecimiento'
                : ($estadoNuevo === 'en_revision_cdp'
                    ? 'UATP aprueba pertinencia pedagógica y deriva a revisión CDP inicial por viático'
                    : 'UATP aprueba pertinencia pedagógica y deriva a GDP para resolución de cometido'));

        $cometido->update($payload);

        $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, $accion, $data['observacion'] ?? null);
        $this->notificarServiciosGeneralesVehiculoInstitucional($cometido->fresh());

        $mensaje = $esParalelo
            ? 'Solicitud aprobada por UATP. Se activó la gestión paralela: Planificación revisa el CDP inicial sólo de viático y el establecimiento puede iniciar la rendición de reembolso.'
            : ($soloReembolso
                ? 'Solicitud aprobada por UATP. El establecimiento ya puede iniciar la rendición de reembolso.'
                : ($estadoNuevo === 'en_revision_cdp'
                    ? 'Solicitud aprobada por UATP y derivada a Planificación para revisión CDP inicial por viático.'
                    : 'Solicitud aprobada por UATP y derivada a GDP para resolución de cometido.'));

        return back()->with('success', $mensaje);
    }

    public function rechazarUatp(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'coordinador_uatp']), 403);
        abort_unless($cometido->estado === 'en_revision_uatp', 403);

        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $cometido->update([
            'estado' => 'rechazado_uatp',
            'uatp_revisado_por' => $request->user()->id,
            'uatp_revisado_at' => now(),
            'uatp_decision' => 'rechazado',
            'uatp_observacion' => $data['observacion'],
        ]);

        $this->registrarHistorial($cometido, $estadoAnterior, 'rechazado_uatp', 'UATP rechaza solicitud', $data['observacion']);

        return back()->with('success', 'Solicitud rechazada por UATP.');
    }

    public function observarUatp(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'coordinador_uatp']), 403);
        abort_unless($cometido->estado === 'en_revision_uatp', 403);

        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $cometido->update([
            'estado' => 'observado_uatp',
            'uatp_revisado_por' => $request->user()->id,
            'uatp_revisado_at' => now(),
            'uatp_decision' => 'observado',
            'uatp_observacion' => $data['observacion'],
        ]);

        $this->registrarHistorial($cometido, $estadoAnterior, 'observado_uatp', 'UATP observa solicitud', $data['observacion']);

        return back()->with('success', 'Solicitud observada y devuelta al establecimiento.');
    }

    private function estadoPosteriorAprobacionUatp(CometidoFuncionario $cometido): string
    {
        if ($cometido->solicita_viatico && $cometido->solicita_reembolso) {
            return 'en_gestion_paralela';
        }

        if ($cometido->solicita_reembolso && ! $cometido->solicita_viatico) {
            return 'pendiente_rendicion';
        }

        return $cometido->solicita_viatico ? 'en_revision_cdp' : 'en_gdp_resolucion';
    }

    public function aprobarCdp(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'supervisor_plani', 'coordinador_plani']), 403);
        abort_unless($cometido->estado === 'en_revision_cdp' || $cometido->estado_viatico === 'en_revision_cdp', 403);

        $data = $request->validate([
            'cdp_referencia' => ['required', 'string', 'max:255'],
            'cdp_catalogo_valor_id' => ['nullable', 'integer', 'exists:viaticos_reembolsos_valores,id'],
            'archivo_cdp' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'cdp_beneficios_habilitados' => ['sometimes', 'array'],
            'cdp_montos' => ['sometimes', 'array'],
            'cdp_montos.viatico' => ['sometimes', 'array'],
            'cdp_montos.reembolso' => ['sometimes', 'array'],
        ]);

        $esParalelo = $cometido->solicita_viatico && $cometido->solicita_reembolso;

        $catalogoManual = null;
        if (! $cometido->solicita_viatico && $cometido->solicita_reembolso) {
            if (! empty($data['cdp_catalogo_valor_id'])) {
                $catalogoManual = ViaticoReembolsoValor::query()
                    ->activos()
                    ->findOrFail((int) $data['cdp_catalogo_valor_id']);
            } else {
                $catalogoManual = $this->catalogoAutomaticoViatico($cometido);
            }

            if (! $catalogoManual) {
                throw ValidationException::withMessages(['cdp_catalogo_valor_id' => 'Debe seleccionar un valor de catálogo para asignar reembolso.']);
            }
        }

        $catalogoViatico = $cometido->solicita_viatico ? $this->catalogoAutomaticoViatico($cometido) : null;
        if ($cometido->solicita_viatico && !$catalogoViatico) {
            throw ValidationException::withMessages(['cdp_catalogo_valor_id' => 'No fue posible encontrar un valor vigente de viático para el estamento/cargo del funcionario. Revise el escalafón y el catálogo vigente.']);
        }

        $catalogoPrincipal = $catalogoViatico ?: $catalogoManual;
        if (!$catalogoPrincipal) {
            throw ValidationException::withMessages(['cdp_catalogo_valor_id' => 'No fue posible determinar el catálogo para el CDP.']);
        }

        $montosDiarios = $this->prepararMontosDiariosCdp($request, $cometido, $catalogoManual, $catalogoViatico);
        if ($cometido->solicita_viatico && empty($montosDiarios['viatico'])) {
            throw ValidationException::withMessages(['cdp_montos.viatico' => 'Debe asignar valores diarios para el viático solicitado.']);
        }
        if ($cometido->solicita_reembolso && ! $esParalelo && empty($montosDiarios['reembolso'])) {
            throw ValidationException::withMessages(['cdp_montos.reembolso' => 'Debe asignar topes diarios para el reembolso solicitado.']);
        }

        $totalViatico = array_sum(array_column($montosDiarios['viatico'], 'monto'));
        $totalReembolso = $esParalelo ? 0 : array_sum(array_column($montosDiarios['reembolso'], 'monto'));
        $montoTotal = $totalViatico + $totalReembolso;

        if ($cometido->solicita_viatico && $totalViatico > 0) {
            $estadoDisponibilidad = $this->estadoDisponibilidadViaticoCdp($cometido, (int) $totalViatico);
            if (! ($estadoDisponibilidad['suficiente'] ?? false)) {
                $this->derivarADirectorPorSinDisponibilidad(
                    $request,
                    $cometido,
                    (int) $totalViatico,
                    (int) ($estadoDisponibilidad['saldo_disponible'] ?? 0),
                    $data['cdp_referencia'] ?? null,
                    $data['observacion'] ?? null,
                    $catalogoPrincipal
                );

                return back()->with('warning', 'No existe disponibilidad presupuestaria suficiente para pagar viático. El cometido fue derivado al Director Ejecutivo para aprobar reconversión a reembolso o rechazar.');
            }
        }

        if (! $request->hasFile('archivo_cdp')) {
            throw ValidationException::withMessages(['archivo_cdp' => 'Debe adjuntar el certificado CDP de viático antes de aprobar disponibilidad presupuestaria.']);
        }

        $estadoAnterior = $cometido->estado;
        $estadoNuevo = $esParalelo ? 'en_gestion_paralela' : 'en_gdp_resolucion';
        $documentoCdp = null;
        $movimientoDisponibilidad = null;

        DB::transaction(function () use ($request, $cometido, $data, $catalogoPrincipal, $montosDiarios, $totalViatico, $totalReembolso, $montoTotal, $estadoNuevo, $esParalelo, &$documentoCdp, &$movimientoDisponibilidad) {
            if ($cometido->solicita_viatico && $totalViatico > 0) {
                $movimientoDisponibilidad = $this->descontarDisponibilidadViaticoCdp(
                    $cometido,
                    (int) $totalViatico,
                    (int) $request->user()->id,
                    $data['cdp_referencia'] ?? null,
                    $data['observacion'] ?? null
                );
            }

            $documentoCdp = $this->guardarDocumentoTipo($request, $cometido, 'archivo_cdp', 'cdp', 'cometidos-funcionarios/cdp');

            $cometido->cdpMontos()->delete();
            $tiposGuardar = $esParalelo ? ['viatico'] : ['viatico', 'reembolso'];
            foreach ($tiposGuardar as $tipo) {
                foreach ($montosDiarios[$tipo] as $row) {
                    $catalogoValorId = $row['catalogo_valor_id'] ?? $catalogoPrincipal->id;
                    unset($row['catalogo_valor_id']);
                    $cometido->cdpMontos()->create($row + [
                        'tipo' => $tipo,
                        'catalogo_valor_id' => $catalogoValorId,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }

            $cometido->update([
                'estado' => $estadoNuevo,
                'estado_viatico' => $esParalelo ? 'en_gdp_resolucion' : 'en_gdp_resolucion',
                'cdp_revisado_por' => $request->user()->id,
                'cdp_revisado_at' => now(),
                'cdp_aprobado' => true,
                'cdp_referencia' => $data['cdp_referencia'] ?? null,
                'cdp_observacion' => $data['observacion'] ?? null,
                'cdp_catalogo_valor_id' => $catalogoPrincipal->id,
                'cdp_estamento' => $catalogoPrincipal->estamento,
                'cdp_cargo_funcion' => $catalogoPrincipal->cargo_funcion,
                'cdp_viatico_total' => $totalViatico,
                'cdp_reembolso_total_maximo' => $esParalelo ? null : $totalReembolso,
                'cdp_monto_total' => $montoTotal,
                'cdp_monto_asignado_at' => now(),
                'cdp_monto_asignado_by' => $request->user()->id,
            ]);
        });

        $observacion = trim(implode("\n", array_filter([
            'Referencia CDP: ' . $data['cdp_referencia'],
            'Catálogo aplicado: ' . $catalogoPrincipal->estamento . ' / ' . $catalogoPrincipal->cargo_funcion,
            $cometido->solicita_viatico ? 'Viático fijo autorizado: $' . number_format($totalViatico, 0, ',', '.') : null,
            $cometido->solicita_reembolso ? ($esParalelo
                ? 'Reembolso diferido a rendición: no se autoriza monto ni tope en el CDP inicial'
                : 'Reembolso máximo autorizado: $' . number_format($totalReembolso, 0, ',', '.')) : null,
            $esParalelo ? 'Total CDP inicial viático: $' . number_format($montoTotal, 0, ',', '.') : 'Total CDP: $' . number_format($montoTotal, 0, ',', '.'),
            $movimientoDisponibilidad ? 'Disponibilidad viáticos descontada: $' . number_format((int) $movimientoDisponibilidad->monto, 0, ',', '.') . ' / saldo posterior: $' . number_format((int) $movimientoDisponibilidad->saldo_nuevo, 0, ',', '.') : null,
            $documentoCdp ? 'Archivo CDP adjunto: ' . $documentoCdp->nombre_original : null,
            $data['observacion'] ?? null,
        ])));

        $accionCdp = $esParalelo
            ? 'Planificación aprueba CDP inicial sólo del viático; el reembolso queda diferido a rendición y a CDP posterior si DAF autoriza monto rendido'
            : 'Planificación aprueba CDP con asignación diaria de montos y deriva a GDP';
        $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, $accionCdp, $observacion ?: null);

        $mensajeCdp = $esParalelo
            ? 'CDP inicial aprobado sólo para viático. El reembolso queda diferido al flujo de rendición; su CDP se generará después de la aprobación DAF si corresponde.'
            : 'CDP aprobado con montos diarios asignados. La solicitud fue derivada a GDP para resolución de cometido.';

        return back()->with('success', $mensajeCdp);
    }

    private function estadoDisponibilidadViaticoCdp(CometidoFuncionario $cometido, int $monto): array
    {
        $origen = $this->origenDisponibilidadViatico($cometido);
        $fechaBase = $cometido->fecha_desde ? Carbon::parse($cometido->fecha_desde) : now();
        $anio = (int) $fechaBase->year;
        $fecha = $fechaBase->toDateString();

        $disponibilidad = ViaticoDisponibilidadPresupuestaria::query()
            ->where('anio', $anio)
            ->where('activo', true)
            ->whereIn('origen_tipo', [$origen, ViaticoDisponibilidadPresupuestaria::ORIGEN_AMBOS])
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(function ($query) use ($fecha) {
                $query->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha);
            })
            ->orderByRaw('CASE WHEN origen_tipo = ? THEN 0 ELSE 1 END', [$origen])
            ->first();

        $saldo = (int) ($disponibilidad->saldo_disponible ?? 0);

        return [
            'disponibilidad' => $disponibilidad,
            'saldo_disponible' => $saldo,
            'monto_requerido' => $monto,
            'diferencia' => max(0, $monto - $saldo),
            'suficiente' => (bool) ($disponibilidad && $saldo >= $monto),
            'origen' => $origen,
            'anio' => $anio,
        ];
    }

    private function derivarADirectorPorSinDisponibilidad(Request $request, CometidoFuncionario $cometido, int $montoViatico, int $saldoDisponible, ?string $referencia = null, ?string $observacion = null, ?ViaticoReembolsoValor $catalogo = null): void
    {
        $estadoAnterior = $cometido->estado;
        $diferencia = max(0, $montoViatico - $saldoDisponible);
        $fundamento = trim((string) ($observacion ?: 'Planificación informa falta de disponibilidad presupuestaria para emitir CDP de viático.'));

        $payload = [
            'estado' => 'pendiente_autorizacion_director_sin_disponibilidad',
            'estado_viatico' => 'sin_disponibilidad',
            'requiere_autorizacion_director_sin_disponibilidad' => true,
            'estado_autorizacion_director' => 'pendiente',
            'monto_viatico_solicitado_director' => $montoViatico,
            'monto_disponible_director' => $saldoDisponible,
            'diferencia_presupuestaria_director' => $diferencia,
            'fundamento_planificacion_director' => $fundamento,
            'fecha_solicitud_director' => now(),
            'director_user_id' => null,
            'decision_director' => null,
            'observacion_director' => null,
            'fecha_decision_director' => null,
            'cdp_referencia' => $referencia,
            'cdp_observacion' => $fundamento,
            'cdp_aprobado' => null,
            'cdp_catalogo_valor_id' => $catalogo?->id,
            'cdp_estamento' => $catalogo?->estamento,
            'cdp_cargo_funcion' => $catalogo?->cargo_funcion,
            'cdp_viatico_total' => $montoViatico,
            'cdp_monto_total' => $montoViatico,
        ];

        $cometido->forceFill($payload)->save();

        $this->registrarHistorial(
            $cometido,
            $estadoAnterior,
            'pendiente_autorizacion_director_sin_disponibilidad',
            'Planificación deriva a Director Ejecutivo por falta de disponibilidad presupuestaria',
            'Monto viático calculado: $' . number_format($montoViatico, 0, ',', '.') . "\n" .
            'Saldo disponible: $' . number_format($saldoDisponible, 0, ',', '.') . "\n" .
            'Diferencia presupuestaria: $' . number_format($diferencia, 0, ',', '.') . "\n" .
            $fundamento
        );


        $this->notificarRol(
            ['director_ejecutivo'],
            'Cometido requiere decisión del Director Ejecutivo por falta de disponibilidad',
            'Planificación detectó que el cometido funcionario no cuenta con disponibilidad presupuestaria suficiente para pagar viático. Debe resolver si aprueba la reconversión a reembolso o rechaza la continuidad financiera.\n\nMonto viático calculado: $' . number_format($montoViatico, 0, ',', '.') . '\nSaldo disponible: $' . number_format($saldoDisponible, 0, ',', '.') . '\nDiferencia: $' . number_format($diferencia, 0, ',', '.'),
            $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']),
            'Resolver decisión',
            'Decisión Director Ejecutivo',
            'expediente_completo'
        );
    }

    public function aprobarReconversionDirector(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'director_ejecutivo']), 403);
        abort_unless($cometido->estado === 'pendiente_autorizacion_director_sin_disponibilidad', 403);

        $data = $request->validate([
            'observacion_director' => ['required', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $montoOriginal = (int) ($cometido->monto_viatico_solicitado_director ?: $cometido->cdp_viatico_total ?: 0);

        DB::transaction(function () use ($request, $cometido, $data, $montoOriginal) {
            $cometido->forceFill([
                'estado' => 'en_gdp_rex_cgr',
                'estado_viatico' => 'no_pagado_sin_disponibilidad',
                'estado_reembolso' => 'en_gdp_rex_cgr',
                'solicita_viatico' => false,
                'solicita_reembolso' => true,
                'tenia_derecho_viatico_original' => true,
                'monto_viatico_original' => $montoOriginal,
                'viatico_reconvertido_a_reembolso' => true,
                'motivo_reconversion_reembolso' => 'Director Ejecutivo aprueba reconversión a reembolso por falta de disponibilidad presupuestaria para viático.',
                'estado_autorizacion_director' => 'aprobada',
                'decision_director' => 'aprobada_reconversion_reembolso',
                'observacion_director' => $data['observacion_director'],
                'fecha_decision_director' => now(),
                'director_user_id' => $request->user()->id,
                'requiere_autorizacion_director_sin_disponibilidad' => false,
            ])->save();
        });

        $this->registrarHistorial(
            $cometido,
            $estadoAnterior,
            'en_gdp_rex_cgr',
            'Director Ejecutivo aprueba reconversión de viático a reembolso por falta de disponibilidad',
            $data['observacion_director']
        );


        $cometidoNotificacion = $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']);
        $this->notificarRol(
            ['funcionario_slep', 'coordinador_gdp'],
            'Director aprobó reconversión a reembolso: emitir REX CGR',
            'El Director Ejecutivo aprobó reconvertir el cometido desde viático a reembolso por falta de disponibilidad presupuestaria. GDP debe emitir la REX CGR para habilitar el flujo de reembolso.',
            $cometidoNotificacion,
            'Emitir REX CGR',
            'Reconversión aprobada',
            'expediente_completo'
        );
        $this->notificarUsuarioId(
            $cometido->user_id,
            'Cometido reconvertido a reembolso por falta de disponibilidad',
            'El Director Ejecutivo aprobó la reconversión del cometido a flujo de reembolso. No se pagará viático; el trámite continuará con REX CGR, rendición, informe de cometido y revisión DAF.',
            $cometidoNotificacion,
            'Ver cometido',
            'Reconversión a reembolso',
            'expediente_completo'
        );

        return back()->with('success', 'Reconversión aprobada. El cometido fue derivado a GDP para REX CGR y continuará como flujo de reembolso.');
    }

    public function rechazarReconversionDirector(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'director_ejecutivo']), 403);
        abort_unless($cometido->estado === 'pendiente_autorizacion_director_sin_disponibilidad', 403);

        $data = $request->validate([
            'observacion_director' => ['required', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;

        $cometido->forceFill([
            'estado' => 'rechazado_director_sin_disponibilidad',
            'estado_viatico' => 'rechazado_sin_disponibilidad',
            'estado_autorizacion_director' => 'rechazada',
            'decision_director' => 'rechazada',
            'observacion_director' => $data['observacion_director'],
            'fecha_decision_director' => now(),
            'director_user_id' => $request->user()->id,
            'requiere_autorizacion_director_sin_disponibilidad' => false,
        ])->save();

        $this->registrarHistorial(
            $cometido,
            $estadoAnterior,
            'rechazado_director_sin_disponibilidad',
            'Director Ejecutivo rechaza continuidad financiera por falta de disponibilidad',
            $data['observacion_director']
        );


        $cometidoNotificacion = $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']);
        $this->notificarUsuarioId(
            $cometido->user_id,
            'Cometido rechazado por falta de disponibilidad presupuestaria',
            'El Director Ejecutivo rechazó la continuidad financiera del cometido por falta de disponibilidad presupuestaria. Revise la observación registrada en la plataforma.',
            $cometidoNotificacion,
            'Ver cometido',
            'Rechazado Director Ejecutivo',
            'expediente_completo'
        );
        $this->notificarRol(
            ['supervisor_plani', 'coordinador_plani'],
            'Director rechazó continuidad financiera de cometido',
            'El Director Ejecutivo rechazó la continuidad financiera del cometido por falta de disponibilidad presupuestaria. La solicitud queda detenida financieramente.',
            $cometidoNotificacion,
            'Ver cometido',
            'Rechazado',
            'expediente_completo'
        );

        return back()->with('success', 'La continuidad financiera del cometido fue rechazada por falta de disponibilidad presupuestaria.');
    }

    private function descontarDisponibilidadViaticoCdp(CometidoFuncionario $cometido, int $monto, int $userId, ?string $referencia = null, ?string $observacion = null): ?ViaticoDisponibilidadMovimiento
    {
        if ($monto <= 0) {
            return null;
        }

        $existente = ViaticoDisponibilidadMovimiento::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->where('tipo_movimiento', ViaticoDisponibilidadMovimiento::TIPO_COMPROMISO_CDP_VIATICO)
            ->first();

        if ($existente) {
            return $existente;
        }

        $origen = $this->origenDisponibilidadViatico($cometido);
        $fechaBase = $cometido->fecha_desde ? Carbon::parse($cometido->fecha_desde) : now();
        $anio = (int) $fechaBase->year;
        $fecha = $fechaBase->toDateString();

        $disponibilidad = ViaticoDisponibilidadPresupuestaria::query()
            ->where('anio', $anio)
            ->where('activo', true)
            ->whereIn('origen_tipo', [$origen, ViaticoDisponibilidadPresupuestaria::ORIGEN_AMBOS])
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(function ($query) use ($fecha) {
                $query->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha);
            })
            ->orderByRaw('CASE WHEN origen_tipo = ? THEN 0 ELSE 1 END', [$origen])
            ->lockForUpdate()
            ->first();

        if (! $disponibilidad) {
            throw ValidationException::withMessages([
                'cdp_referencia' => 'No existe disponibilidad presupuestaria activa para viáticos del año ' . $anio . ' y origen ' . $this->origenDisponibilidadViaticoLabel($origen) . '. Cree un registro en el mantenedor Viáticos disponibilidad antes de aprobar el CDP.',
            ]);
        }

        if ((int) $disponibilidad->saldo_disponible < $monto) {
            throw ValidationException::withMessages([
                'cdp_referencia' => 'Saldo insuficiente en Viáticos disponibilidad. El trámite debe ser derivado al Director Ejecutivo para reconversión a reembolso o rechazo.',
            ]);
        }

        $saldoAnterior = (int) $disponibilidad->saldo_disponible;
        $disponibilidad->monto_comprometido = (int) $disponibilidad->monto_comprometido + $monto;
        $disponibilidad->saldo_disponible = max(0, $saldoAnterior - $monto);
        if (Schema::hasColumn($disponibilidad->getTable(), 'updated_by')) {
            $disponibilidad->updated_by = $userId;
        }
        $disponibilidad->save();

        return ViaticoDisponibilidadMovimiento::create([
            'viatico_disponibilidad_presupuestaria_id' => $disponibilidad->id,
            'cometido_funcionario_id' => $cometido->id,
            'tipo_movimiento' => ViaticoDisponibilidadMovimiento::TIPO_COMPROMISO_CDP_VIATICO,
            'monto' => $monto,
            'saldo_anterior' => $saldoAnterior,
            'saldo_nuevo' => (int) $disponibilidad->saldo_disponible,
            'referencia' => $referencia,
            'observacion' => $observacion,
            'created_by' => $userId,
        ]);
    }

    private function origenDisponibilidadViatico(CometidoFuncionario $cometido): string
    {
        return $cometido->esAdministracionCentral()
            ? ViaticoDisponibilidadPresupuestaria::ORIGEN_ADMINISTRACION_CENTRAL
            : ViaticoDisponibilidadPresupuestaria::ORIGEN_ESTABLECIMIENTOS;
    }

    private function origenDisponibilidadViaticoLabel(string $origen): string
    {
        return ViaticoDisponibilidadPresupuestaria::origenes()[$origen] ?? ucfirst(str_replace('_', ' ', $origen));
    }

    public function rechazarCdp(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'supervisor_plani', 'coordinador_plani']), 403);
        abort_unless($cometido->estado === 'en_revision_cdp' || $cometido->estado_viatico === 'en_revision_cdp', 403);

        $data = $request->validate([
            'observacion' => ['required', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $esParalelo = $cometido->solicita_viatico && $cometido->solicita_reembolso;
        $estadoNuevo = $esParalelo ? 'en_gestion_paralela' : 'autorizado_sin_gasto';
        $mensajeSinGasto = $esParalelo
            ? 'No se autoriza CDP inicial para viático. El componente de reembolso continúa sujeto a rendición y a su CDP posterior, si DAF autoriza monto rendido.'
            : 'Se autoriza el cometido funcionario, pero no se autoriza incurrir en gasto. No existirá devolución, reembolso ni pago de viático por falta de disponibilidad presupuestaria.';

        DB::transaction(function () use ($request, $cometido, $data, $estadoNuevo, $mensajeSinGasto, $esParalelo) {
            $cometido->cdpMontos()->delete();
            $cometido->update([
            'estado' => $estadoNuevo,
            'estado_viatico' => $esParalelo ? 'cdp_rechazado' : null,
            'cdp_revisado_por' => $request->user()->id,
            'cdp_revisado_at' => now(),
            'cdp_aprobado' => false,
            'cdp_referencia' => null,
            'cdp_observacion' => trim($data['observacion'] . "\n\n" . $mensajeSinGasto),
            'cdp_catalogo_valor_id' => null,
            'cdp_estamento' => null,
            'cdp_cargo_funcion' => null,
            'cdp_viatico_total' => null,
            'cdp_reembolso_total_maximo' => null,
            'cdp_monto_total' => null,
            'cdp_monto_asignado_at' => null,
            'cdp_monto_asignado_by' => null,
            ]);
        });

        $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, 'Planificación rechaza CDP y autoriza cometido sin gasto', trim($data['observacion'] . "\n\n" . $mensajeSinGasto));

        return back()->with('success', 'CDP rechazado. El cometido queda autorizado sin gasto y visible para GDP.');
    }

    private function prepararMontosDiariosCdp(Request $request, CometidoFuncionario $cometido, ?ViaticoReembolsoValor $catalogoManual, ?ViaticoReembolsoValor $catalogoViatico): array
    {
        $fechasEsperadas = collect($this->diasCometido($cometido))
            ->pluck('fecha')
            ->values()
            ->all();

        if (empty($fechasEsperadas)) {
            throw ValidationException::withMessages(['cdp_montos' => 'No fue posible determinar los días del cometido. Revise las fechas desde/hasta.']);
        }

        $input = $request->input('cdp_montos', []);
        $resultado = [
            'viatico' => [],
            'reembolso' => [],
        ];

        $esParalelo = $cometido->solicita_viatico && $cometido->solicita_reembolso;

        foreach (['viatico', 'reembolso'] as $tipo) {
            $solicitado = $tipo === 'viatico' ? (bool) $cometido->solicita_viatico : (bool) $cometido->solicita_reembolso;
            if (!$solicitado || ($esParalelo && $tipo === 'reembolso')) {
                continue;
            }

            $rows = $input[$tipo] ?? [];
            $beneficiosHabilitados = $request->input('cdp_beneficios_habilitados', []);
            $habilitado = (string) ($beneficiosHabilitados[$tipo] ?? '1') === '1';

            if ($tipo === 'viatico') {
                if (!$catalogoViatico) {
                    throw ValidationException::withMessages(['cdp_catalogo_valor_id' => 'No fue posible determinar el catálogo automático para viático.']);
                }

                foreach ($fechasEsperadas as $index => $fecha) {
                    $porcentaje = $habilitado ? $this->porcentajeAutomaticoViatico($cometido, count($fechasEsperadas), $index) : 0;
                    $valorDiario = $this->valorCatalogoPorPorcentaje($catalogoViatico, $porcentaje);
                    if ($porcentaje !== 0 && $valorDiario <= 0) {
                        throw ValidationException::withMessages(['cdp_catalogo_valor_id' => 'El catálogo automático de viático no tiene monto válido para el porcentaje calculado.']);
                    }

                    $resultado[$tipo][] = [
                        'fecha' => $fecha,
                        'dia_numero' => $index + 1,
                        'porcentaje' => $porcentaje,
                        'valor_diario' => $valorDiario,
                        'monto' => $valorDiario,
                        'catalogo_valor_id' => $catalogoViatico->id,
                    ];
                }

                continue;
            }

            if (!$catalogoManual) {
                throw ValidationException::withMessages(['cdp_catalogo_valor_id' => 'Debe seleccionar un valor de catálogo para el reembolso.']);
            }

            foreach ($fechasEsperadas as $index => $fecha) {
                if (!$habilitado) {
                    $resultado[$tipo][] = [
                        'fecha' => $fecha,
                        'dia_numero' => $index + 1,
                        'porcentaje' => 0,
                        'valor_diario' => 0,
                        'monto' => 0,
                        'catalogo_valor_id' => $catalogoManual->id,
                    ];
                    continue;
                }

                $row = $rows[$fecha] ?? null;
                if (!is_array($row)) {
                    throw ValidationException::withMessages(["cdp_montos.$tipo.$fecha" => 'Debe asignar porcentaje para todos los días solicitados.']);
                }

                $porcentaje = (int) (($row['porcentaje'] ?? 0));
                if (!in_array($porcentaje, [100, 60, 40, 0], true)) {
                    throw ValidationException::withMessages(["cdp_montos.$tipo.$fecha.porcentaje" => 'El porcentaje diario debe ser 100%, 60%, 40% o 0%.']);
                }

                $valorDiario = $this->valorCatalogoPorPorcentaje($catalogoManual, $porcentaje);
                if ($porcentaje !== 0 && $valorDiario <= 0) {
                    throw ValidationException::withMessages(["cdp_montos.$tipo.$fecha.porcentaje" => 'El catálogo seleccionado no tiene un valor válido para el porcentaje indicado.']);
                }

                $resultado[$tipo][] = [
                    'fecha' => $fecha,
                    'dia_numero' => $index + 1,
                    'porcentaje' => $porcentaje,
                    'valor_diario' => $valorDiario,
                    'monto' => $valorDiario,
                    'catalogo_valor_id' => $catalogoManual->id,
                ];
            }
        }

        return $resultado;
    }

    public function emitirResolucionGdp(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'coordinador_gdp', 'funcionario_slep']), 403);
        abort_unless(in_array($cometido->estado, ['en_gdp_resolucion', 'en_gdp_rex_cgr', 'autorizado_sin_gasto'], true) || $cometido->estado_viatico === 'en_gdp_resolucion' || $cometido->estado_reembolso === 'en_gdp_rex_cgr', 403);

        $esCometidoSinGasto = ! (bool) $cometido->solicita_viatico && ! (bool) $cometido->solicita_reembolso;

        $data = $request->validate($esCometidoSinGasto ? [
            'observacion' => ['nullable', 'string', 'max:5000'],
        ] : [
            'numero_resolucion_cometido' => ['required', 'string', 'max:255'],
            'fecha_resolucion_cometido' => ['required', 'date'],
            'archivo_resolucion_cometido' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'observacion' => ['nullable', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $esParalelo = $cometido->solicita_viatico && $cometido->solicita_reembolso && $cometido->estado === 'en_gestion_paralela';
        $esRexCgr = $cometido->estado === 'en_gdp_rex_cgr' || $cometido->estado_reembolso === 'en_gdp_rex_cgr';
        $estadoNuevo = $this->estadoPosteriorResolucionGdp($cometido);
        $documentoResolucion = null;

        DB::transaction(function () use ($request, $cometido, $data, $estadoNuevo, $esParalelo, $esRexCgr, $esCometidoSinGasto, &$documentoResolucion) {
            if (! $esCometidoSinGasto) {
                $documentoResolucion = $this->guardarDocumentoTipo($request, $cometido, 'archivo_resolucion_cometido', 'resolucion_cometido', 'cometidos-funcionarios/resoluciones');
            }

            $updates = [
                'estado' => $esRexCgr && $cometido->estado === 'en_gestion_paralela' ? 'en_gestion_paralela' : ($esParalelo ? 'en_gestion_paralela' : $estadoNuevo),
                'estado_viatico' => $esRexCgr ? $cometido->estado_viatico : ($esParalelo ? $estadoNuevo : ($cometido->solicita_viatico ? $estadoNuevo : $cometido->estado_viatico)),
                'estado_reembolso' => ($esRexCgr || (! $esParalelo && $cometido->solicita_reembolso)) ? $estadoNuevo : $cometido->estado_reembolso,
                'gdp_revisado_por' => $request->user()->id,
                'gdp_revisado_at' => now(),
            ];

            if (! $esCometidoSinGasto) {
                $updates['numero_resolucion_cometido'] = $data['numero_resolucion_cometido'];
                $updates['fecha_resolucion_cometido'] = $data['fecha_resolucion_cometido'];
                $updates['archivo_resolucion_cometido_path'] = $documentoResolucion?->path ?: $cometido->archivo_resolucion_cometido_path;
            }

            $cometido->update($updates);
        });

        $observacion = trim(implode("\n", array_filter($esCometidoSinGasto ? [
            'Registro GDP de cometido sin viático ni reembolso.',
            $data['observacion'] ?? null,
        ] : [
            'Resolución cometido: ' . $data['numero_resolucion_cometido'],
            'Fecha resolución: ' . $data['fecha_resolucion_cometido'],
            $documentoResolucion ? 'Archivo resolución adjunto: ' . $documentoResolucion->nombre_original : null,
            $data['observacion'] ?? null,
        ])));

        $accion = match ($estadoNuevo) {
            'informe_pendiente_funcionario' => $esParalelo ? 'GDP emitió REX del componente viático y habilitó informe de cometido; la rendición de reembolso continúa en paralelo' : 'GDP emitió resolución de cometido y habilitó informe de cometido',
            'en_daf_viatico' => $esParalelo ? 'GDP emitió REX del componente viático y derivó a DAF para pago; la rendición de reembolso sigue abierta en paralelo' : 'GDP emitió resolución de cometido y derivó a DAF para pago de viático',
            'en_revision_daf_rendicion' => 'GDP emitió REX cometido CGR y derivó la rendición a revisión DAF',
            'pendiente_rendicion' => $esRexCgr ? 'GDP emitió REX cometido CGR y habilitó rendición de reembolso' : 'GDP emitió resolución de cometido y habilitó rendición de reembolso por establecimiento',
            'resolucion_cometido_emitida' => $esCometidoSinGasto ? 'GDP registró el cometido sin viático ni reembolso y lo dejó disponible para cierre' : 'GDP emitió resolución de cometido',
            'cerrado' => 'GDP emitió resolución de cometido y cerró el trámite sin gestión financiera posterior',
            default => 'GDP emitió resolución de cometido',
        };

        $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, $accion, $observacion ?: null);

        $cometidoNotificacion = $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']);
        if ($estadoNuevo === 'informe_pendiente_funcionario') {
            $this->notificarUsuarioId($cometido->user_id, 'Debe completar Informe de Cometido', 'GDP registró la resolución del cometido. Para continuar el flujo debe completar y enviar el Informe de Cometido en la plataforma.', $cometidoNotificacion, 'Completar informe', 'Informe pendiente', 'expediente_completo');
        }
        if (in_array($estadoNuevo, ['pendiente_rendicion', 'pendiente_rendicion_informe'], true)) {
            $this->notificarUsuarioId($cometido->user_id, 'Rendición e Informe de Cometido disponibles', 'GDP emitió la REX CGR asociada al cometido con reembolso. Puede completar la rendición y el Informe de Cometido en paralelo. La revisión DAF se habilitará cuando la rendición esté enviada y el informe esté aprobado por jefatura.', $cometidoNotificacion, 'Rendir / completar informe', 'Rendición e informe', 'expediente_completo');
        }
        if ($estadoNuevo === 'en_daf_viatico') {
            $this->notificarRol('funcionario_daf', 'Cometido listo para registro contable de viático', 'El Informe de Cometido fue aprobado o no aplica etapa pendiente, y el trámite quedó disponible para registro de compromiso y devengo antes del pago de viático.', $cometidoNotificacion, 'Registrar contabilidad', 'DAF contable', 'expediente_completo');
        }

        if ($esRexCgr) {
            if ($estadoNuevo === 'en_revision_daf_rendicion') {
                $this->notificarRol('funcionario_daf', 'REX cometido CGR emitida: rendición disponible para revisión DAF', 'GDP emitió la Resolución Exenta para CGR asociada al cometido con reembolso. Se adjunta expediente vigente, incluyendo cometido firmado, citación o invitación, documentos complementarios, rendición y REX cometido CGR, para continuar con la revisión DAF de la rendición.', $cometido, 'Revisar rendición', 'Rendición en revisión DAF', 'expediente_aprobado');
            } elseif ($estadoNuevo === 'pendiente_rendicion') {
                $this->notificarUsuarioId($cometido->user_id, 'REX cometido CGR emitida: rendición habilitada', 'GDP emitió la Resolución Exenta para CGR asociada al cometido con reembolso. Se adjunta expediente vigente y queda habilitada la rendición de gastos en la plataforma.', $cometido, 'Rendir gastos', 'Rendición habilitada', 'expediente_aprobado');
            }
        }

        $mensaje = match ($estadoNuevo) {
            'informe_pendiente_funcionario' => $esParalelo ? 'Resolución registrada. El componente viático quedó pendiente de informe de cometido y la rendición de reembolso continúa disponible en paralelo.' : 'Resolución registrada. El funcionario debe enviar informe de cometido antes de continuar al pago del viático.',
            'en_daf_viatico' => $esParalelo ? 'Resolución registrada. El componente viático quedó en DAF para pago y la rendición de reembolso continúa disponible en paralelo.' : 'Resolución registrada. El trámite fue derivado a DAF para pago de viático.',
            'en_revision_daf_rendicion' => 'REX cometido CGR registrada. La rendición fue derivada a DAF para revisión.',
            'pendiente_rendicion' => $esRexCgr ? 'REX cometido CGR registrada. La rendición de gastos quedó habilitada.' : 'Resolución registrada. El establecimiento ya puede rendir el reembolso.',
            'resolucion_cometido_emitida' => $esCometidoSinGasto ? 'Cometido registrado correctamente. El trámite quedó disponible para cierre por GDP.' : 'Resolución registrada correctamente.',
            'cerrado' => 'Resolución registrada. El trámite quedó cerrado por no tener gestión financiera posterior.',
            default => 'Resolución registrada correctamente.',
        };

        return back()->with('success', $mensaje);
    }

    public function registrarContabilidadViatico(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'funcionario_daf']), 403);
        abort_unless($cometido->estado === 'en_daf_viatico' || $cometido->estado_viatico === 'en_daf_viatico', 403);

        $data = $request->validate([
            'folio_compromiso_viatico' => ['required', 'string', 'max:100'],
            'fecha_compromiso_viatico' => ['required', 'date'],
            'folio_devengo_viatico' => ['required', 'string', 'max:100'],
            'fecha_devengo_viatico' => ['required', 'date'],
            'documento_contable_viatico' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            'observacion_contable_viatico' => ['nullable', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $esParalelo = $cometido->solicita_viatico && $cometido->solicita_reembolso && $cometido->estado === 'en_gestion_paralela';
        $documentoContable = null;

        DB::transaction(function () use ($request, $cometido, $data, $esParalelo, &$documentoContable) {
            $documentoContable = $this->guardarDocumentoTipo($request, $cometido, 'documento_contable_viatico', 'contabilidad_viatico', 'cometidos-funcionarios/contabilidad-viatico');

            $cometido->update([
                'estado' => $esParalelo ? 'en_gestion_paralela' : 'en_pago_viatico',
                'estado_viatico' => 'en_pago_viatico',
                'folio_compromiso_viatico' => $data['folio_compromiso_viatico'],
                'fecha_compromiso_viatico' => $data['fecha_compromiso_viatico'],
                'folio_devengo_viatico' => $data['folio_devengo_viatico'],
                'fecha_devengo_viatico' => $data['fecha_devengo_viatico'],
                'documento_contable_viatico_path' => $documentoContable?->path ?: $cometido->documento_contable_viatico_path,
                'observacion_contable_viatico' => $data['observacion_contable_viatico'] ?? null,
                'daf_contable_viatico_user_id' => $request->user()->id,
                'daf_contable_viatico_at' => now(),
            ]);
        });

        $observacion = trim(implode("\n", array_filter([
            'Folio compromiso viático: ' . $data['folio_compromiso_viatico'],
            'Fecha compromiso: ' . $data['fecha_compromiso_viatico'],
            'Folio devengo viático: ' . $data['folio_devengo_viatico'],
            'Fecha devengo: ' . $data['fecha_devengo_viatico'],
            $documentoContable ? 'Documento contable adjunto: ' . $documentoContable->nombre_original : null,
            $data['observacion_contable_viatico'] ?? null,
        ])));

        $this->registrarHistorial($cometido, $estadoAnterior, 'en_pago_viatico', 'DAF registró compromiso y devengo del viático', $observacion ?: null);

        $this->notificarRol('funcionario_daf', 'Viático habilitado para pago', 'DAF registró compromiso y devengo del viático. El pago queda habilitado y debe registrarse con su comprobante correspondiente.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Registrar pago', 'Pago habilitado', 'expediente_completo');

        return back()->with('success', 'Registro contable del viático guardado correctamente. El componente queda habilitado para pago.');
    }

    public function registrarPagoViatico(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'funcionario_daf']), 403);
        abort_unless(in_array($cometido->estado, ['en_pago_viatico'], true) || $cometido->estado_viatico === 'en_pago_viatico', 403);

        if (Schema::hasColumn($cometido->getTable(), 'folio_devengo_viatico') && empty($cometido->folio_devengo_viatico)) {
            throw ValidationException::withMessages([
                'fecha_pago_viatico' => 'Debe registrar primero el compromiso y devengo del viático antes de informar el pago.',
            ]);
        }

        $montoMaximoViatico = (int) ($cometido->cdp_viatico_total ?? $cometido->cdp_monto_total ?? 0);

        $data = $request->validate([
            'monto_pagado_viatico' => ['required', 'integer', 'min:0'],
            'fecha_pago_viatico' => ['required', 'date'],
            'folio_tesoreria_viatico' => ['required', 'string', 'max:100'],
            'archivo_pago_viatico' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            'observacion_pago_viatico' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($montoMaximoViatico > 0 && (int) $data['monto_pagado_viatico'] > $montoMaximoViatico) {
            throw ValidationException::withMessages([
                'monto_pagado_viatico' => 'El monto pagado de viático no puede superar el monto aprobado por CDP.',
            ]);
        }

        $estadoAnterior = $cometido->estado;
        $esParalelo = $cometido->solicita_viatico && $cometido->solicita_reembolso && $cometido->estado === 'en_gestion_paralela';
        $estadoNuevo = 'viatico_pagado';
        $documentoPago = null;

        DB::transaction(function () use ($request, $cometido, $data, $esParalelo, &$documentoPago) {
            $documentoPago = $this->guardarDocumentoTipo($request, $cometido, 'archivo_pago_viatico', 'pago_viatico', 'cometidos-funcionarios/pagos-viatico');

            $cometido->update([
                'estado' => $esParalelo ? 'en_gestion_paralela' : 'viatico_pagado',
                'estado_viatico' => 'viatico_pagado',
                'viatico_finalizado_at' => now(),
                'finanzas_revisado_por' => $request->user()->id,
                'finanzas_revisado_at' => now(),
                'fecha_pago_viatico' => $data['fecha_pago_viatico'],
                'monto_pagado_viatico' => (int) $data['monto_pagado_viatico'],
                'folio_tesoreria_viatico' => trim((string) $data['folio_tesoreria_viatico']),
                'documento_pago_viatico_path' => $documentoPago?->path ?: $cometido->documento_pago_viatico_path,
                'observacion_pago_viatico' => $data['observacion_pago_viatico'] ?? null,
                'usuario_pago_viatico_id' => $request->user()->id,
                'fecha_registro_pago_viatico' => now(),
                'finanzas_observacion' => $data['observacion_pago_viatico'] ?? null,
            ]);
        });

        $observacion = trim(implode("\n", array_filter([
            'Monto pagado viático: $' . number_format((int) $data['monto_pagado_viatico'], 0, ',', '.'),
            'Fecha pago viático: ' . $data['fecha_pago_viatico'],
            'Folio Tesorería: ' . trim((string) $data['folio_tesoreria_viatico']),
            'Folio devengo asociado: ' . ($cometido->folio_devengo_viatico ?: 'no informado'),
            $documentoPago ? 'Archivo pago adjunto: ' . $documentoPago->nombre_original : null,
            $data['observacion_pago_viatico'] ?? null,
        ])));

        $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, 'DAF/Finanzas registró el pago del viático posterior al devengo contable', $observacion ?: null);

        $cerradoAutomaticamente = $this->cerrarCometidoSiFlujoFinalizado(
            $cometido,
            $estadoNuevo,
            'Cierre automático posterior al registro de pago de viático.'
        );

        $this->notificarUsuarioId($cometido->user_id, 'Pago de viático registrado', 'DAF/Finanzas registró el pago del viático asociado al cometido. El comprobante queda disponible en el expediente documental.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Ver expediente', 'Viático pagado', 'pago_registrado');

        return back()->with('success', $cerradoAutomaticamente
            ? 'Pago de viático registrado correctamente. El cometido fue cerrado automáticamente al finalizar el flujo financiero.'
            : ($esParalelo ? 'Pago de viático registrado correctamente. El trámite seguirá abierto hasta completar el flujo de reembolso.' : 'Pago de viático registrado correctamente. El trámite queda disponible para cierre.'));
    }

    public function cerrar(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_daf']), 403);
        $esCierreCometidoSinGasto = ! (bool) $cometido->solicita_viatico
            && ! (bool) $cometido->solicita_reembolso
            && $cometido->estado === 'resolucion_cometido_emitida';

        abort_unless($cometido->listoParaCierre() || $esCierreCometidoSinGasto, 403);

        $data = $request->validate([
            'observacion' => ['nullable', 'string', 'max:5000'],
        ]);

        $estadoAnterior = $cometido->estado;
        $cometido->update(['estado' => 'cerrado']);

        $this->registrarHistorial($cometido, $estadoAnterior, 'cerrado', 'Trámite de cometido funcionario cerrado', $data['observacion'] ?? null);

        return back()->with('success', 'Cometido funcionario cerrado correctamente.');
    }

    private function estadoPosteriorResolucionGdp(CometidoFuncionario $cometido): string
    {
        if ($cometido->estado === 'en_gdp_rex_cgr' || $cometido->estado_reembolso === 'en_gdp_rex_cgr') {
            $rendicionEnviada = \App\Models\CometidoFuncionarioRendicion::query()
                ->where('cometido_funcionario_id', $cometido->id)
                ->exists();
            $informeAprobado = \App\Models\CometidoFuncionarioInforme::query()
                ->where('cometido_funcionario_id', $cometido->id)
                ->whereIn('estado_informe', ['aprobado_jefatura', 'informe_aprobado', 'aprobado'])
                ->exists();

            if ($rendicionEnviada && $informeAprobado) {
                return 'en_revision_daf_rendicion';
            }

            if ($rendicionEnviada && ! $informeAprobado) {
                return 'rendicion_enviada_pendiente_informe';
            }

            return 'pendiente_rendicion_informe';
        }

        if (! (bool) $cometido->solicita_viatico && ! (bool) $cometido->solicita_reembolso) {
            return 'resolucion_cometido_emitida';
        }

        if ($cometido->estado === 'autorizado_sin_gasto') {
            return 'cerrado';
        }

        if ($cometido->solicita_viatico && $cometido->solicita_reembolso && $cometido->estado === 'en_gestion_paralela') {
            return 'informe_pendiente_funcionario';
        }

        if ($cometido->solicita_reembolso) {
            return 'pendiente_rendicion';
        }

        if ($cometido->solicita_viatico) {
            return 'informe_pendiente_funcionario';
        }

        return 'cerrado';
    }

    public function funcionarioDetalle(Request $request, ReemplazoPersonal $reemplazoPersonal)
    {
        $establecimiento = $this->establecimientoDelUsuario();
        abort_unless((int) $reemplazoPersonal->establecimiento_id === (int) $establecimiento->id, 403);

        return response()->json([
            'id' => $reemplazoPersonal->id,
            'nombre' => $reemplazoPersonal->nombre,
            'rut' => $reemplazoPersonal->rut,
            'calidad_juridica' => $reemplazoPersonal->tipocontrato,
            'estamento' => $reemplazoPersonal->estatuto,
            'cargo_funcion' => $reemplazoPersonal->escalafon,
            'es_docente' => $this->esDocente($reemplazoPersonal),
            'es_aaee' => $this->esAaee($reemplazoPersonal),
        ]);
    }

    public function descargarPlantillaFormulario(Request $request)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['funcionario_estab', 'coordinador_uatp', 'admin', 'funcionario_slep', 'coordinador_gdp', 'supervisor_plani', 'coordinador_plani', 'funcionario_daf']), 403);

        $path = resource_path('templates/tramites/cometidos-funcionarios/FORMULARIO COMETIDO 2026 EE.docx');

        abort_unless(is_file($path), 404);

        return response()->download($path, 'FORMULARIO COMETIDO 2026 EE.docx', [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function exportarSeguimientoExcel(Request $request)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        abort_if($activeRole === 'funcionario_estab', 403);

        $query = $this->seguimientoQuery($request, $user, $activeRole)
            ->with(['establecimiento', 'solicitante'])
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id');

        $cometidos = $query->get();
        $filename = 'nomina_cometidos_seguimiento_' . now()->format('Ymd_His') . '.xls';

        $html = '<html><head><meta charset="UTF-8"></head><body>';
        $html .= '<table border="1">';
        $html .= '<thead><tr>';
        foreach (['N° cometido', 'Fecha solicitud', 'Funcionario', 'RUT', 'Estamento', 'Cargo/función', 'Establecimiento', 'RBD', 'Destino', 'Fecha desde', 'Fecha hasta', 'Viático', 'Reembolso', 'Estado'] as $columna) {
            $html .= '<th>' . e($columna) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($cometidos as $cometido) {
            $html .= '<tr>';
            $html .= '<td>' . e($cometido->id) . '</td>';
            $html .= '<td>' . e(optional($cometido->fecha_solicitud)->format('d-m-Y') ?: '') . '</td>';
            $html .= '<td>' . e($cometido->funcionario_nombre ?: '') . '</td>';
            $html .= '<td>' . e($cometido->funcionario_rut ?: '') . '</td>';
            $html .= '<td>' . e($cometido->estamento ?: '') . '</td>';
            $html .= '<td>' . e($cometido->cargo_funcion ?: '') . '</td>';
            $html .= '<td>' . e($cometido->establecimiento->nombre_establecimiento ?? '') . '</td>';
            $html .= '<td>' . e($cometido->rbd ?: ($cometido->establecimiento->rbd ?? '')) . '</td>';
            $html .= '<td>' . e($cometido->destino ?: $cometido->comuna_destino_nombre ?: '') . '</td>';
            $html .= '<td>' . e(optional($cometido->fecha_desde)->format('d-m-Y') ?: '') . '</td>';
            $html .= '<td>' . e(optional($cometido->fecha_hasta)->format('d-m-Y') ?: '') . '</td>';
            $html .= '<td>' . e($cometido->solicita_viatico ? 'Sí' : 'No') . '</td>';
            $html .= '<td>' . e($cometido->solicita_reembolso ? 'Sí' : 'No') . '</td>';
            $html .= '<td>' . e(method_exists($cometido, 'etiquetaEstado') ? $cometido->etiquetaEstado() : $cometido->estado) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        return response("\xEF\xBB\xBF" . $html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function descargarDocumentosSeguimientoZip(Request $request)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        abort_if($activeRole === 'funcionario_estab', 403);

        $cometidos = $this->seguimientoQuery($request, $user, $activeRole)
            ->with(['establecimiento', 'documentos'])
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id')
            ->get();

        abort_if(!class_exists(ZipArchive::class), 500, 'La extensión ZipArchive de PHP no está disponible en el servidor.');

        $tmpDir = storage_path('app/temp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $filename = 'documentos_cometidos_seguimiento_' . now()->format('Ymd_His') . '.zip';
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No fue posible crear el archivo ZIP de documentos.');
        }

        $agregados = 0;
        foreach ($cometidos as $cometido) {
            $establecimiento = $this->nombreCarpetaZip($cometido->establecimiento->nombre_establecimiento ?? 'sin-establecimiento');
            $numero = 'cometido-' . str_pad((string) $cometido->id, 5, '0', STR_PAD_LEFT);
            $base = $establecimiento . '/' . $numero . '/';

            foreach ($cometido->documentos as $documento) {
                if (!$documento->path || !Storage::exists($documento->path)) {
                    continue;
                }

                $nombre = $this->nombreArchivoZip($documento->nombre_original ?: basename($documento->path));
                $rutaInterna = $base . $nombre;
                $contador = 2;
                while ($zip->locateName($rutaInterna) !== false) {
                    $info = pathinfo($nombre);
                    $rutaInterna = $base . ($info['filename'] ?? 'archivo') . '_' . $contador . (isset($info['extension']) ? '.' . $info['extension'] : '');
                    $contador++;
                }

                $zip->addFile(Storage::path($documento->path), $rutaInterna);
                $agregados++;
            }
        }

        if ($agregados === 0) {
            $zip->addFromString('sin_documentos.txt', 'No se encontraron documentos disponibles para los cometidos filtrados.');
        }

        $zip->close();

        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }

    private function seguimientoQuery(Request $request, $user, ?string $activeRole)
    {
        $query = CometidoFuncionario::query();
        $this->applyIndexRoleScope($query, $user, $activeRole);
        $this->applySeguimientoRequestFilters($query, $request, $activeRole !== 'funcionario_estab');
        $this->applyBandejaCometidosScope($query, $user, $activeRole, 'autorizados');

        return $query;
    }

    private function nombreCarpetaZip(string $nombre): string
    {
        $slug = Str::slug($nombre, '_');
        return $slug !== '' ? $slug : 'sin_establecimiento';
    }

    private function nombreArchivoZip(string $nombre): string
    {
        $nombre = str_replace(["\\", "/", ":", "*", "?", '"', "<", ">", "|", "\0", "\r", "\n", "\t"], '_', $nombre);
        $nombre = trim(preg_replace('/_+/', '_', $nombre), " ._\t\n\r\0\x0B");

        return $nombre !== '' ? $nombre : 'archivo';
    }

    public function verDocumento(Request $request, CometidoFuncionario $cometido, CometidoFuncionarioDocumento $documento)
    {
        $this->authorizeCometido($request, $cometido);
        abort_unless((int) $documento->cometido_funcionario_id === (int) $cometido->id, 404);
        abort_unless(Storage::exists($documento->path), 404);

        $absolutePath = Storage::path($documento->path);
        $mime = $documento->mime_type ?: (Storage::mimeType($documento->path) ?: 'application/octet-stream');
        $filename = $documento->nombre_original ?: basename($documento->path);

        if ($request->boolean('download')) {
            return response()->download($absolutePath, $filename, [
                'Content-Type' => $mime,
            ]);
        }

        return response()->file($absolutePath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }

    private function valoresCatalogoCdp()
    {
        return ViaticoReembolsoValor::query()
            ->activos()
            ->orderBy('estamento')
            ->orderBy('cargo_funcion')
            ->orderByDesc('vigente_desde')
            ->get(['id', 'estamento', 'cargo_funcion', 'vigente_desde', 'vigente_hasta', 'valor_100', 'valor_60', 'valor_40']);
    }

    private function diasCometido(CometidoFuncionario $cometido): array
    {
        if (!$cometido->fecha_desde || !$cometido->fecha_hasta) {
            return [];
        }

        $desde = Carbon::parse($cometido->fecha_desde)->startOfDay();
        $hasta = Carbon::parse($cometido->fecha_hasta)->startOfDay();
        if ($hasta->lt($desde)) {
            return [];
        }

        $dias = [];
        $cursor = $desde->copy();
        $numero = 1;
        while ($cursor->lte($hasta) && $numero <= 120) {
            $dias[] = [
                'numero' => $numero,
                'fecha' => $cursor->toDateString(),
                'label' => $cursor->format('d-m-Y'),
            ];
            $cursor->addDay();
            $numero++;
        }

        return $dias;
    }

    private function calcularViaticoAutomaticoCdp(CometidoFuncionario $cometido): array
    {
        $dias = $this->diasCometido($cometido);
        $catalogo = $cometido->solicita_viatico ? $this->catalogoAutomaticoViatico($cometido) : null;
        $rows = [];
        $total = 0;

        foreach ($dias as $index => $dia) {
            $porcentaje = $catalogo ? $this->porcentajeAutomaticoViatico($cometido, count($dias), $index) : 0;
            $valorDiario = 0;
            if ($catalogo && $porcentaje > 0) {
                $valorDiario = $this->valorCatalogoPorPorcentaje($catalogo, $porcentaje);
            }

            $total += $valorDiario;
            $rows[] = [
                'numero' => $dia['numero'],
                'fecha' => $dia['fecha'],
                'label' => $dia['label'],
                'porcentaje' => $porcentaje,
                'monto' => $valorDiario,
                'regla' => $this->reglaViaticoAutomatico($cometido, count($dias), $index, $porcentaje),
            ];
        }

        return [
            'aplica' => (bool) $cometido->solicita_viatico,
            'catalogo' => $catalogo,
            'categoria' => $this->categoriaViaticoDetectada($cometido),
            'rows' => $rows,
            'total' => $total,
            'error' => $cometido->solicita_viatico && !$catalogo ? 'No se encontró un valor vigente para el escalafón/cargo del funcionario.' : null,
        ];
    }

    private function catalogoAutomaticoViatico(CometidoFuncionario $cometido): ?ViaticoReembolsoValor
    {
        $fechaReferencia = $cometido->fecha_desde ?: $cometido->fecha_solicitud ?: now();
        $fecha = Carbon::parse($fechaReferencia)->toDateString();

        if ($cometido->esAdministracionCentral()) {
            $reglaAc = $this->reglaCatalogoFuncionarioAc($cometido);
            if ($reglaAc) {
                return $this->catalogoPorEstamentoCargo($reglaAc['estamento'], $reglaAc['cargo_funcion'], $fecha);
            }
        }

        $categoria = $this->categoriaViaticoAaee($cometido->cargo_funcion ?? $cometido->estamento);
        if (!$categoria) {
            return null;
        }

        return $this->catalogoPorEstamentoCargo('AAEE', $categoria, $fecha);
    }

    private function categoriaViaticoDetectada(CometidoFuncionario $cometido): ?string
    {
        if ($cometido->esAdministracionCentral()) {
            $reglaAc = $this->reglaCatalogoFuncionarioAc($cometido);
            if ($reglaAc) {
                return $reglaAc['label'];
            }
        }

        return $this->categoriaViaticoAaee($cometido->cargo_funcion ?? $cometido->estamento);
    }

    private function reglaCatalogoFuncionarioAc(CometidoFuncionario $cometido): ?array
    {
        if (! $cometido->esAdministracionCentral()) {
            return null;
        }

        $funcionarioAc = $cometido->relationLoaded('funcionarioAcAutorizado')
            ? $cometido->funcionarioAcAutorizado
            : $cometido->funcionarioAcAutorizado()->first();

        $gradoTexto = trim((string) ($funcionarioAc?->grado ?? ''));
        $grado = $this->extraerGradoNumerico($gradoTexto);

        if ($grado !== null) {
            $tramo = $this->tramoCodigoAdministrativoPorGrado($grado);
            if ($tramo) {
                return [
                    'estamento' => 'Código Administrativo',
                    'cargo_funcion' => $tramo,
                    'label' => 'Código Administrativo / ' . $tramo,
                    'motivo' => 'Funcionario AC con grado ' . $grado . '; se aplica tramo de Código Administrativo.',
                ];
            }
        }

        $escalafon = $this->normaliza(($funcionarioAc?->escalafon ?? '') . ' ' . ($cometido->estamento ?? ''));
        if (str_contains($escalafon, 'DOCENTE')) {
            return [
                'estamento' => 'Docente',
                'cargo_funcion' => 'Docentes',
                'label' => 'Docente / Docentes',
                'motivo' => 'Funcionario AC sin grado y con escalafón Docente; se aplica valor vigente Docente / Docentes.',
            ];
        }

        return null;
    }

    private function extraerGradoNumerico(?string $grado): ?int
    {
        $grado = trim((string) $grado);
        if ($grado === '') {
            return null;
        }

        if (preg_match('/\d+/', $grado, $matches) !== 1) {
            return null;
        }

        $numero = (int) $matches[0];
        return $numero > 0 ? $numero : null;
    }

    private function tramoCodigoAdministrativoPorGrado(int $grado): ?string
    {
        return match (true) {
            $grado >= 1 && $grado <= 4 => '1° al 4°',
            $grado >= 5 && $grado <= 10 => '5° al 10°',
            $grado >= 11 && $grado <= 21 => '11° al 21°',
            $grado >= 22 && $grado <= 31 => '22° al 31°',
            default => null,
        };
    }

    private function catalogoPorEstamentoCargo(string $estamento, string $cargoFuncion, string $fecha): ?ViaticoReembolsoValor
    {
        return ViaticoReembolsoValor::query()
            ->activos()
            ->whereDate('vigente_desde', '<=', $fecha)
            ->whereDate('vigente_hasta', '>=', $fecha)
            ->get()
            ->first(function (ViaticoReembolsoValor $valor) use ($estamento, $cargoFuncion) {
                return $this->normaliza($valor->estamento) === $this->normaliza($estamento)
                    && $this->normaliza($valor->cargo_funcion) === $this->normaliza($cargoFuncion);
            });
    }

    private function categoriaViaticoAaee(?string $texto): ?string
    {
        $normalizado = $this->normaliza($texto);

        if (str_contains($normalizado, 'JUNJI') || str_contains($normalizado, 'DIRECTORA')) {
            return 'Directora Junji';
        }
        if (str_contains($normalizado, 'PARVULO') || str_contains($normalizado, 'PARVULOS') || str_contains($normalizado, 'EDUCADORA')) {
            return 'Educadora de Párvulos';
        }
        if (str_contains($normalizado, 'PROFESIONAL')) {
            return 'Profesional';
        }
        if (str_contains($normalizado, 'TECNICO')) {
            return 'Técnico';
        }
        if (str_contains($normalizado, 'ADMINISTRATIVO')) {
            return 'Administrativo';
        }
        if (str_contains($normalizado, 'AUXILIAR')) {
            return 'Auxiliar';
        }

        return null;
    }


    private function valorCatalogoPorPorcentaje(ViaticoReembolsoValor $catalogo, int $porcentaje): int
    {
        return match ($porcentaje) {
            100 => (int) $catalogo->valor_100,
            60 => (int) ($catalogo->valor_60 ?? 0),
            40 => (int) $catalogo->valor_40,
            default => 0,
        };
    }

    private function porcentajeAutomaticoViatico(CometidoFuncionario $cometido, int $totalDias, int $index): int
    {
        if ($totalDias <= 0) {
            return 0;
        }

        if ((string) ($cometido->servicio_contempla_colacion ?? 'no_informado') === 'si') {
            return $index === $totalDias - 1 ? 0 : 60;
        }

        if ($totalDias === 1) {
            return $this->cubreTramoAlimentacionViatico($cometido) ? 40 : 0;
        }

        if ((bool) $cometido->contempla_alojamiento) {
            return 40;
        }

        return $index === $totalDias - 1 ? 40 : 100;
    }

    private function reglaViaticoAutomatico(CometidoFuncionario $cometido, int $totalDias, int $index, int $porcentaje): string
    {
        if ((string) ($cometido->servicio_contempla_colacion ?? 'no_informado') === 'si' && $porcentaje === 0 && $index === $totalDias - 1) {
            return 'Último día con regreso durante el día y colación contemplada: no corresponde viático diario.';
        }

        if ($porcentaje === 60) {
            return 'Día con pernoctación y servicio con colación contemplada: corresponde sólo pernoctación al 60%.';
        }

        if ($totalDias === 1) {
            $horas = $this->duracionHorasUnDia($cometido);
            return $porcentaje === 40
                ? 'Un día sin pernoctación: el horario cubre completamente el tramo de alimentación 12:00 a 15:00 (' . number_format($horas, 1, ',', '.') . ' h), corresponde 40%.'
                : 'Un día sin pernoctación: el horario no cubre completamente el tramo de alimentación 12:00 a 15:00, corresponde 0%.';
        }

        if ((bool) $cometido->contempla_alojamiento) {
            return 'Alojamiento contemplado o provisto: 40% por día.';
        }

        return $index === $totalDias - 1 ? 'Último día sin pernoctación: 40%.' : 'Día inicial/intermedio con pernoctación: 100%.';
    }

    private function cubreTramoAlimentacionViatico(CometidoFuncionario $cometido): bool
    {
        if (!$cometido->hora_salida || !$cometido->hora_regreso) {
            return false;
        }

        try {
            $fechaBase = $cometido->fecha_desde ? Carbon::parse($cometido->fecha_desde)->toDateString() : now()->toDateString();
            $salida = Carbon::parse($fechaBase . ' ' . $cometido->hora_salida);
            $regreso = Carbon::parse($fechaBase . ' ' . $cometido->hora_regreso);

            if ($regreso->lessThanOrEqualTo($salida)) {
                $regreso->addDay();
            }

            $inicioTramo = Carbon::parse($fechaBase . ' 12:00');
            $finTramo = Carbon::parse($fechaBase . ' 15:00');

            return $salida->lessThanOrEqualTo($inicioTramo) && $regreso->greaterThanOrEqualTo($finTramo);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function duracionHorasUnDia(CometidoFuncionario $cometido): float
    {
        if (!$cometido->hora_salida || !$cometido->hora_regreso) {
            return 0.0;
        }

        try {
            $fechaBase = $cometido->fecha_desde ? Carbon::parse($cometido->fecha_desde)->toDateString() : now()->toDateString();
            $salida = Carbon::parse($fechaBase . ' ' . $cometido->hora_salida);
            $regreso = Carbon::parse($fechaBase . ' ' . $cometido->hora_regreso);

            if ($regreso->lessThanOrEqualTo($salida)) {
                $regreso->addDay();
            }

            return max(0, $salida->diffInMinutes($regreso) / 60);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function calcularTopeReferencialReembolsoInicial(CometidoFuncionario $cometido, ?ViaticoReembolsoValor $catalogoManual): int
    {
        if (! $cometido->solicita_reembolso || ! $catalogoManual) {
            return 0;
        }

        $dias = $this->diasCometido($cometido);
        if (empty($dias)) {
            return 0;
        }

        return count($dias) * (int) $catalogoManual->valor_100;
    }


    private function storeFuncionarioAc(Request $request)
    {
        $resolver = app(FuncionarioAcAutorizadorResolver::class);
        $funcionarioAc = $resolver->funcionarioParaUsuario($request->user());
        abort_unless($funcionarioAc, 403, 'No existe un funcionario AC autorizado asociado a tu usuario.');

        $data = $this->validatedDataAc($request, $funcionarioAc, null);
        $estado = $request->input('accion') === 'enviar' ? 'en_revision_jefatura_ac' : 'borrador';

        $cometido = DB::transaction(function () use ($request, $data, $funcionarioAc, $estado, $resolver) {
            $autorizador = $resolver->autorizadorPara($funcionarioAc);
            if ($estado === 'en_revision_jefatura_ac' && ! $autorizador) {
                $mensaje = $resolver->esDirectorEjecutivo($funcionarioAc)
                    ? 'No existe Subrogante 1 configurado en la matriz de Dirección Ejecutiva para autorizar cometidos del Director Ejecutivo.'
                    : 'No se encontró jefatura o subrogante configurado para autorizar este cometido.';
                throw ValidationException::withMessages(['jefatura' => $mensaje]);
            }

            $cometido = CometidoFuncionario::create(array_merge($data, [
                'user_id' => $request->user()->id,
                'establecimiento_id' => null,
                'rbd' => null,
                'reemplazo_personal_id' => null,
                'estado' => $estado,
                'fecha_solicitud' => now()->toDateString(),
                'origen_cometido' => 'administracion_central',
                'funcionario_ac_autorizado_id' => $funcionarioAc->id,
                'funcionario_rut' => $funcionarioAc->rut_completo,
                'funcionario_nombre' => $funcionarioAc->nombre_completo,
                'calidad_juridica' => $funcionarioAc->calidad_juridica,
                'estamento' => $funcionarioAc->escalafon ?? null,
                'cargo_funcion' => $funcionarioAc->cargo_funcion,
                'subdireccion_dependencia_ac' => $funcionarioAc->subdireccion_dependencia ?? null,
                'unidad_departamento_ac' => $funcionarioAc->unidad_departamento ?? null,
                'es_jefatura_ac' => (bool) ($funcionarioAc->jefatura ?? false),
                'estado_autorizacion_jefatura_ac' => $estado === 'en_revision_jefatura_ac' ? 'pendiente' : null,
                'jefatura_autorizadora_ac_id' => $autorizador['funcionario']->id ?? null,
                'autorizado_por_subrogante' => $autorizador['es_subrogante'] ?? false,
            ]));

            $cometido->update(['numero_cometido_interno' => 'CF-AC-' . now()->format('Y') . '-' . str_pad((string) $cometido->id, 6, '0', STR_PAD_LEFT)]);
            $this->guardarCitacion($request, $cometido);

            if ($estado === 'en_revision_jefatura_ac') {
                app(CometidoFuncionarioPdfService::class)->generarSolicitudCometido($cometido, $request->user(), true, $request);
                if (! empty($autorizador['funcionario'])) {
                    $this->notificarFuncionarioAc($autorizador['funcionario'], 'Nueva solicitud de cometido AC pendiente de autorización', 'Existe una solicitud de cometido funcionario AC pendiente de su revisión. Se adjunta el expediente inicial: cometido generado por el sistema con firma electrónica interna del solicitante, citación o invitación y documentos complementarios disponibles.', $cometido, 'Revisar solicitud', 'Pendiente de autorización', 'expediente_solicitud');
                }
            }

            $this->registrarHistorial(
                $cometido,
                null,
                $estado,
                $estado === 'en_revision_jefatura_ac'
                    ? $this->descripcionEnvioAutorizacionAc($funcionarioAc, $autorizador)
                    : 'Borrador AC creado'
            );

            return $cometido;
        });

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', $estado === 'en_revision_jefatura_ac' ? 'Solicitud enviada a autorización de jefatura.' : 'Borrador guardado.');
    }

    private function updateFuncionarioAc(Request $request, CometidoFuncionario $cometido)
    {
        $resolver = app(FuncionarioAcAutorizadorResolver::class);
        $funcionarioAc = $resolver->funcionarioParaUsuario($request->user());
        abort_unless($funcionarioAc && (int) $cometido->funcionario_ac_autorizado_id === (int) $funcionarioAc->id, 403);
        abort_unless($cometido->esEditablePorFuncionarioAc(), 403);

        $data = $this->validatedDataAc($request, $funcionarioAc, $cometido);
        $estadoAnterior = $cometido->estado;
        $estadoNuevo = $request->input('accion') === 'enviar' ? 'en_revision_jefatura_ac' : 'borrador';
        if ($estadoAnterior === 'en_revision_jefatura_ac') {
            $estadoNuevo = 'en_revision_jefatura_ac';
        }

        DB::transaction(function () use ($request, $cometido, $data, $funcionarioAc, $estadoAnterior, $estadoNuevo, $resolver) {
            $autorizador = $resolver->autorizadorPara($funcionarioAc);
            if ($estadoNuevo === 'en_revision_jefatura_ac' && ! $autorizador) {
                $mensaje = $resolver->esDirectorEjecutivo($funcionarioAc)
                    ? 'No existe Subrogante 1 configurado en la matriz de Dirección Ejecutiva para autorizar cometidos del Director Ejecutivo.'
                    : 'No se encontró jefatura o subrogante configurado para autorizar este cometido.';
                throw ValidationException::withMessages(['jefatura' => $mensaje]);
            }

            $cometido->update(array_merge($data, [
                'estado' => $estadoNuevo,
                'funcionario_rut' => $funcionarioAc->rut_completo,
                'funcionario_nombre' => $funcionarioAc->nombre_completo,
                'calidad_juridica' => $funcionarioAc->calidad_juridica,
                'estamento' => $funcionarioAc->escalafon ?? null,
                'cargo_funcion' => $funcionarioAc->cargo_funcion,
                'subdireccion_dependencia_ac' => $funcionarioAc->subdireccion_dependencia ?? null,
                'unidad_departamento_ac' => $funcionarioAc->unidad_departamento ?? null,
                'es_jefatura_ac' => (bool) ($funcionarioAc->jefatura ?? false),
                'estado_autorizacion_jefatura_ac' => $estadoNuevo === 'en_revision_jefatura_ac' ? 'pendiente' : null,
                'jefatura_autorizadora_ac_id' => $autorizador['funcionario']->id ?? null,
                'autorizado_por_subrogante' => $autorizador['es_subrogante'] ?? false,
                'fecha_autorizacion_jefatura_ac' => null,
                'observacion_jefatura_ac' => null,
            ]));

            $this->guardarCitacion($request, $cometido);
            if ($estadoNuevo === 'en_revision_jefatura_ac') {
                app(CometidoFuncionarioPdfService::class)->generarSolicitudCometido($cometido, $request->user(), true, $request);
                if (! empty($autorizador['funcionario'])) {
                    $this->notificarFuncionarioAc($autorizador['funcionario'], 'Solicitud de cometido AC actualizada para autorización', 'El funcionario AC actualizó o corrigió la solicitud de cometido. Se adjunta el expediente vigente: cometido generado por el sistema con firma electrónica interna del solicitante, citación o invitación y documentos complementarios disponibles.', $cometido, 'Revisar solicitud', 'Pendiente de autorización', 'expediente_solicitud');
                }
            }

            $accion = $estadoNuevo === 'en_revision_jefatura_ac'
                ? ($estadoAnterior === 'observado_jefatura_ac'
                    ? 'Solicitud AC corregida y reenviada a jefatura'
                    : $this->descripcionEnvioAutorizacionAc($funcionarioAc, $autorizador))
                : 'Borrador AC actualizado';
            $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, $accion);
        });

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', $estadoNuevo === 'en_revision_jefatura_ac' ? 'Solicitud actualizada y enviada a jefatura.' : 'Borrador actualizado.');
    }

    public function regenerarSolicitudCometidoPdf(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->puedeRegenerarSolicitudCometidoPdf($request, $cometido), 403);

        DB::transaction(function () use ($request, $cometido) {
            app(CometidoFuncionarioPdfService::class)->regenerarSolicitudCometido($cometido, $request->user(), $request);
            $this->registrarHistorial($cometido, $cometido->estado, $cometido->estado, 'PDF de solicitud de cometido regenerado antes de aprobación de jefatura');
        });

        return back()->with('success', 'PDF de solicitud de cometido regenerado correctamente. Revise nuevamente el documento antes de la aprobación de jefatura.');
    }

    public function aprobarJefaturaAc(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->puedeRevisarJefaturaAc($request, $cometido), 403);
        abort_unless($cometido->estado === 'en_revision_jefatura_ac', 403);
        $data = $request->validate(['observacion' => ['nullable', 'string', 'max:5000']]);

        $estadoAnterior = $cometido->estado;
        $estadoNuevo = $this->estadoPosteriorAprobacionAc($cometido);
        DB::transaction(function () use ($request, $cometido, $data, $estadoNuevo) {
            $autorizador = FuncionarioAcAutorizado::find($cometido->jefatura_autorizadora_ac_id);
            $cometido->update([
                'estado' => $estadoNuevo,
                'estado_autorizacion_jefatura_ac' => 'aprobado',
                'fecha_autorizacion_jefatura_ac' => now(),
                'jefatura_autorizadora_user_id' => $request->user()->id,
                'observacion_jefatura_ac' => $data['observacion'] ?? null,
                'estado_viatico' => $cometido->solicita_viatico ? $estadoNuevo : null,
                'estado_reembolso' => $cometido->solicita_reembolso
                    ? ($cometido->solicita_viatico ? 'pendiente_rendicion' : 'en_gdp_rex_cgr')
                    : null,
            ]);
            app(CometidoFuncionarioPdfService::class)->agregarFirmaJefatura($cometido, $autorizador ?: $cometido->funcionarioAcAutorizado, $request->user(), (bool) $cometido->autorizado_por_subrogante, $request);
            $this->notificarUsuarioId($cometido->user_id, 'Cometido funcionario autorizado por jefatura', 'Su cometido funcionario fue autorizado por jefatura. Se adjunta el cometido firmado, la citación o invitación y los documentos complementarios disponibles.', $cometido, 'Ver cometido autorizado', 'Cometido autorizado', 'expediente_aprobado');
            if ($cometido->solicita_viatico) {
                $this->notificarRol(['supervisor_plani', 'coordinador_plani'], 'Cometido AC autorizado requiere CDP de viático', 'La jefatura autorizó un cometido funcionario de Administración Central con derecho a viático. Planificación debe revisar disponibilidad presupuestaria y emitir el certificado CDP del viático. Se adjunta expediente vigente con cometido firmado, citación o invitación y documentos complementarios disponibles.', $cometido, 'Emitir CDP viático', 'Pendiente CDP viático', 'expediente_aprobado');
            }
            if ($cometido->solicita_reembolso && ! $cometido->solicita_viatico) {
                $this->notificarRol('funcionario_slep', 'Cometido AC autorizado requiere REX cometido CGR', 'La jefatura autorizó un cometido funcionario de Administración Central que contempla devolución de gastos/reembolso sin viático. GDP debe emitir la Resolución Exenta para CGR antes de continuar al paso posterior. Se adjunta expediente vigente con cometido firmado, citación o invitación y documentos complementarios disponibles.', $cometido, 'Emitir REX cometido CGR', 'Pendiente REX CGR', 'expediente_aprobado');
            }
            if ($cometido->requiere_pasaje_aereo) {
                $pasaje = CometidoFuncionarioPasajeAereo::firstOrCreate(
                    ['cometido_funcionario_id' => $cometido->id],
                    ['estado_pasaje' => 'pendiente_reserva']
                );
                app(CometidoFuncionarioPdfService::class)->generarSolicitudPedido($cometido, $pasaje, $request->user());
                $cometido->unsetRelation('documentosGenerados');
                $cometido->unsetRelation('pasajeAereo');
                $cometido->load(['documentosGenerados', 'pasajeAereo']);
                $this->notificarRol('funcionario_daf_compra', 'Cometido AC autorizado requiere reserva de pasaje aéreo', 'La jefatura autorizó un cometido funcionario de Administración Central que contempla transporte en avión. Se adjunta expediente para gestión de compra: cometido firmado, citación o invitación, documentos complementarios y Solicitud de Pedido de Pasaje firmada por solicitante y jefatura.', $cometido, 'Gestionar reserva', 'Pasaje pendiente de reserva', 'pasaje_autorizado');
            }
        });

        $this->registrarHistorial($cometido, $estadoAnterior, $estadoNuevo, 'Jefatura AC autoriza cometido', $data['observacion'] ?? null);
        $this->notificarServiciosGeneralesVehiculoInstitucional($cometido->fresh());
        return back()->with('success', $cometido->requiere_pasaje_aereo ? 'Cometido autorizado. Se activó el flujo paralelo de pasaje aéreo.' : 'Cometido autorizado por jefatura.');
    }

    public function observarJefaturaAc(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->puedeRevisarJefaturaAc($request, $cometido), 403);
        abort_unless($cometido->estado === 'en_revision_jefatura_ac', 403);
        $data = $request->validate(['observacion' => ['required', 'string', 'max:5000']]);
        $estadoAnterior = $cometido->estado;
        $cometido->update([
            'estado' => 'observado_jefatura_ac',
            'estado_autorizacion_jefatura_ac' => 'observado',
            'observacion_jefatura_ac' => $data['observacion'],
        ]);
        $this->registrarHistorial($cometido, $estadoAnterior, 'observado_jefatura_ac', 'Jefatura AC observa cometido', $data['observacion']);
        return back()->with('success', 'Solicitud observada y devuelta al funcionario AC.');
    }

    public function rechazarJefaturaAc(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->puedeRevisarJefaturaAc($request, $cometido), 403);
        abort_unless($cometido->estado === 'en_revision_jefatura_ac', 403);
        $data = $request->validate(['observacion' => ['required', 'string', 'max:5000']]);
        $estadoAnterior = $cometido->estado;
        $cometido->update([
            'estado' => 'rechazado_jefatura_ac',
            'estado_autorizacion_jefatura_ac' => 'rechazado',
            'observacion_jefatura_ac' => $data['observacion'],
        ]);
        $this->registrarHistorial($cometido, $estadoAnterior, 'rechazado_jefatura_ac', 'Jefatura AC rechaza cometido', $data['observacion']);
        return back()->with('success', 'Solicitud rechazada por jefatura AC.');
    }

    private function estadoPosteriorAprobacionAc(CometidoFuncionario $cometido): string
    {
        if ($cometido->solicita_viatico && $cometido->solicita_reembolso) {
            return $cometido->esAdministracionCentral() ? 'en_revision_cdp' : 'en_gestion_paralela';
        }
        if ($cometido->solicita_reembolso && ! $cometido->solicita_viatico) {
            return $cometido->esAdministracionCentral() ? 'en_gdp_rex_cgr' : 'pendiente_rendicion';
        }
        return $cometido->solicita_viatico ? 'en_revision_cdp' : 'en_gdp_resolucion';
    }

    public function cargarReservaPasaje(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'funcionario_daf_compra']), 403);
        abort_unless($cometido->requiere_pasaje_aereo, 403);
        $data = $request->validate([
            'archivo_reserva' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'observacion' => ['nullable', 'string', 'max:5000'],
        ]);
        $pasaje = $cometido->pasajeAereo()->firstOrCreate(['cometido_funcionario_id' => $cometido->id], ['estado_pasaje' => 'pendiente_reserva']);
        $file = $request->file('archivo_reserva');
        $path = $file->store('cometidos-funcionarios/pasajes/reservas');
        $pasaje->update([
            'estado_pasaje' => 'pendiente_cdp_pasaje',
            'reserva_usuario_id' => $request->user()->id,
            'reserva_archivo_path' => $path,
            'reserva_nombre_original' => $file->getClientOriginalName(),
            'reserva_fecha' => now(),
            'reserva_observacion' => $data['observacion'] ?? null,
        ]);
        $this->registrarHistorial($cometido, $cometido->estado, $cometido->estado, 'DAF Compra cargó reserva de pasaje aéreo', $data['observacion'] ?? null);
        $this->notificarRol(['supervisor_plani', 'coordinador_plani'], 'Reserva de pasaje aéreo cargada: requiere CDP', 'DAF Compra cargó la reserva de pasaje aéreo asociada al cometido. Se adjunta expediente para revisión presupuestaria: cometido firmado, citación o invitación, documentos complementarios, Solicitud de Pedido de Pasaje firmada por solicitante y jefatura, y reserva realizada.', $cometido, 'Emitir CDP de pasaje', 'CDP de pasaje pendiente', 'pasaje_reserva');
        return back()->with('success', 'Reserva de pasaje cargada. Planificación puede emitir CDP de pasaje.');
    }

    public function cargarCdpPasaje(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'supervisor_plani', 'coordinador_plani']), 403);
        abort_unless($cometido->requiere_pasaje_aereo, 403);
        $data = $request->validate([
            'cdp_referencia' => ['required', 'string', 'max:255'],
            'cdp_fecha' => ['required', 'date'],
            'archivo_cdp_pasaje' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'observacion' => ['nullable', 'string', 'max:5000'],
        ]);
        $pasaje = $cometido->pasajeAereo()->firstOrCreate(['cometido_funcionario_id' => $cometido->id], ['estado_pasaje' => 'pendiente_cdp_pasaje']);
        $file = $request->file('archivo_cdp_pasaje');
        $path = $file->store('cometidos-funcionarios/pasajes/cdp');
        $pasaje->update([
            'estado_pasaje' => 'pendiente_compra',
            'cdp_usuario_id' => $request->user()->id,
            'cdp_referencia' => $data['cdp_referencia'],
            'cdp_fecha' => $data['cdp_fecha'],
            'cdp_archivo_path' => $path,
            'cdp_nombre_original' => $file->getClientOriginalName(),
            'cdp_observacion' => $data['observacion'] ?? null,
        ]);
        $this->registrarHistorial($cometido, $cometido->estado, $cometido->estado, 'Planificación cargó CDP de pasaje aéreo', $data['observacion'] ?? null);
        $this->notificarRol('funcionario_daf_compra', 'CDP emitido para compra de pasaje aéreo', 'Planificación cargó el CDP del pasaje aéreo. Se adjunta expediente completo para continuar la compra: cometido firmado, citación o invitación, documentos complementarios, Solicitud de Pedido de Pasaje firmada por solicitante y jefatura, reserva realizada y CDP de pasaje.', $cometido, 'Registrar compra', 'Pasaje pendiente de compra', 'pasaje_cdp');
        return back()->with('success', 'CDP de pasaje cargado. DAF Compra puede registrar la compra.');
    }

    public function cargarCompraPasaje(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole($request->user(), ['admin', 'funcionario_daf_compra']), 403);
        abort_unless($cometido->requiere_pasaje_aereo, 403);
        $data = $request->validate([
            'proveedor' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'integer', 'min:0'],
            'fecha_compra' => ['required', 'date'],
            'numero_oc' => ['nullable', 'string', 'max:100'],
            'archivo_compra' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'observacion' => ['nullable', 'string', 'max:5000'],
        ]);
        $pasaje = $cometido->pasajeAereo()->firstOrFail();
        $file = $request->file('archivo_compra');
        $path = $file->store('cometidos-funcionarios/pasajes/compras');
        $pasaje->update([
            'estado_pasaje' => 'boleto_disponible',
            'compra_usuario_id' => $request->user()->id,
            'proveedor' => $data['proveedor'],
            'monto' => $data['monto'],
            'fecha_compra' => $data['fecha_compra'],
            'numero_oc' => $data['numero_oc'] ?? null,
            'compra_archivo_path' => $path,
            'compra_nombre_original' => $file->getClientOriginalName(),
            'compra_observacion' => $data['observacion'] ?? null,
            'boleto_disponible_at' => now(),
        ]);
        $this->registrarHistorial($cometido, $cometido->estado, $cometido->estado, 'DAF Compra cargó boleto aéreo comprado', $data['observacion'] ?? null);
        $this->notificarUsuarioId($cometido->user_id, 'Boleto aéreo disponible', 'El boleto aéreo asociado a su cometido funcionario ya se encuentra disponible. Se adjunta expediente final del pasaje: cometido firmado, citación o invitación, documentos complementarios, Solicitud de Pedido de Pasaje firmada por solicitante y jefatura, reserva, CDP y boleto o respaldo de compra.', $cometido, 'Ver boleto', 'Boleto disponible', 'pasaje_boleto');
        return back()->with('success', 'Compra registrada. El boleto aéreo queda disponible para el funcionario solicitante.');
    }

    public function verDocumentoGenerado(Request $request, CometidoFuncionario $cometido, CometidoFuncionarioDocumentoGenerado $documento)
    {
        $this->authorizeCometido($request, $cometido);
        abort_unless((int) $documento->cometido_funcionario_id === (int) $cometido->id, 404);
        abort_unless($documento->archivo_pdf_path && Storage::exists($documento->archivo_pdf_path), 404);

        $filename = Str::slug($documento->tipo_documento . '-' . ($documento->numero_documento ?: $documento->id)) . '.pdf';
        if ($request->boolean('download')) {
            return Storage::download($documento->archivo_pdf_path, $filename);
        }

        return response()->file(Storage::path($documento->archivo_pdf_path));
    }

    public function verBoletoPasaje(Request $request, CometidoFuncionario $cometido)
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $pasaje = $cometido->pasajeAereo()->latest()->first();

        abort_unless($pasaje && $pasaje->compra_archivo_path && Storage::exists($pasaje->compra_archivo_path), 404);

        $esSolicitanteAc = $activeRole === 'funcionario_ac'
            && (int) $cometido->user_id === (int) $user->id
            && $cometido->origen_cometido === 'administracion_central';
        $puedeGestionarCompra = in_array($activeRole, ['admin', 'funcionario_daf_compra'], true)
            || $this->userHasAnyRole($user, ['admin', 'funcionario_daf_compra']);

        abort_unless($esSolicitanteAc || $puedeGestionarCompra, 403);

        if ($request->boolean('download')) {
            return response()->download(Storage::path($pasaje->compra_archivo_path), $pasaje->compra_nombre_original ?: 'boleto-pasaje-aereo');
        }

        return response()->file(Storage::path($pasaje->compra_archivo_path));
    }

    public function validarDocumentoPublico(string $codigo)
    {
        $documento = CometidoFuncionarioDocumentoGenerado::query()
            ->with('firmas')
            ->where('codigo_validacion', $codigo)
            ->first();
        return view('documentos.validar', compact('documento'));
    }


    private function formDataAc(CometidoFuncionario $cometido, FuncionarioAcAutorizado $funcionarioAc): array
    {
        $regiones = config('chile.regiones', []);
        $communesByRegion = Commune::query()
            ->orderBy('region_code')
            ->orderBy('name')
            ->get()
            ->groupBy('region_code')
            ->map(fn($c) => $c->map(fn($x) => ['id' => $x->id, 'name' => $x->name])->values())
            ->toArray();

        return [
            'cometido' => $cometido,
            'establecimiento' => null,
            'funcionarios' => collect(),
            'periodo' => null,
            'funcionarioAc' => $funcionarioAc,
            'origenAc' => true,
            'regiones' => $regiones,
            'communesByRegion' => $communesByRegion,
            'mediosTransporte' => $this->mediosTransporte,
            'motivos' => $this->motivos,
            'bancosPago' => $this->bancosPago(),
            'tiposCuentaPago' => $this->tiposCuentaPago(),
            'viaticosAnexoRutBodies' => [],
        ];
    }

    private function validatedDataAc(Request $request, FuncionarioAcAutorizado $funcionarioAc, ?CometidoFuncionario $cometido = null): array
    {
        $data = $request->validate([
            'region_origen' => ['required', 'string', 'max:20'],
            'comuna_origen_id' => ['required', 'integer', 'exists:communes,id'],
            'es_destino_extranjero' => ['nullable', 'boolean'],
            'region_destino' => ['nullable', 'string', 'max:20'],
            'comuna_destino_id' => ['nullable', 'integer', 'exists:communes,id'],
            'pais_destino' => ['nullable', 'string', 'max:120'],
            'ciudad_destino_extranjero' => ['nullable', 'string', 'max:160'],
            'institucion_destino' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
            'fecha_desde' => ['required', 'date', 'after_or_equal:today'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'hora_salida' => ['required', 'date_format:H:i'],
            'hora_regreso' => ['required', 'date_format:H:i'],
            'medios_transporte' => ['required', 'array', 'min:1'],
            'medios_transporte.*' => ['string', Rule::in($this->mediosTransporte)],
            'medio_transporte_otro' => ['nullable', 'string', 'max:255'],
            'motivo' => ['required', 'string', Rule::in($this->motivos)],
            'motivo_otro' => ['nullable', 'string', 'max:255'],
            'descripcion_actividades' => ['required', 'string', 'min:20', 'max:3000'],
            'archivo_citacion_invitacion' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'solicita_viatico' => ['nullable', 'boolean'],
            'solicita_reembolso' => ['nullable', 'boolean'],
            'contempla_alojamiento' => ['nullable', 'boolean'],
            'servicio_contempla_colacion' => ['nullable', 'string', Rule::in(['si', 'no', 'no_informado'])],
            'solicita_anticipo_viatico' => ['nullable', 'boolean'],
            'banco_pago' => ['nullable', 'string', 'max:120'],
            'tipo_cuenta_pago' => ['nullable', 'string', 'max:80'],
            'numero_cuenta_pago' => ['nullable', 'string', 'max:40'],
            'justificacion_menor_7_dias' => ['nullable', 'string', 'max:5000'],
            'tipo_pasaje_aereo' => ['nullable', 'string', Rule::in(['solo_ida', 'solo_regreso', 'ida_y_regreso'])],
            'declaracion_aceptada' => ['nullable', 'accepted'],
        ]);

        $esExtranjero = $request->boolean('es_destino_extranjero');
        if ($esExtranjero) {
            if (trim((string) $request->input('pais_destino')) === '') {
                throw ValidationException::withMessages(['pais_destino' => 'Debe ingresar país destino cuando el cometido es al extranjero.']);
            }
            if (trim((string) $request->input('ciudad_destino_extranjero')) === '') {
                throw ValidationException::withMessages(['ciudad_destino_extranjero' => 'Debe ingresar ciudad destino cuando el cometido es al extranjero.']);
            }
            $data['region_destino'] = null;
            $data['comuna_destino_id'] = null;
            $comunaDestino = null;
        } else {
            if (empty($data['region_destino']) || empty($data['comuna_destino_id'])) {
                throw ValidationException::withMessages(['comuna_destino_id' => 'Debe seleccionar región y comuna destino para cometidos nacionales.']);
            }
            $comunaDestino = Commune::find($data['comuna_destino_id']);
            if ($comunaDestino && (string) $comunaDestino->region_code !== (string) $data['region_destino']) {
                throw ValidationException::withMessages(['comuna_destino_id' => 'La comuna destino no corresponde a la región seleccionada.']);
            }
            $data['pais_destino'] = null;
            $data['ciudad_destino_extranjero'] = null;
        }

        $comunaOrigen = Commune::find($data['comuna_origen_id']);
        if ($comunaOrigen && (string) $comunaOrigen->region_code !== (string) $data['region_origen']) {
            throw ValidationException::withMessages(['comuna_origen_id' => 'La comuna origen no corresponde a la región seleccionada.']);
        }

        if (in_array('Otro', $data['medios_transporte'] ?? [], true) && trim((string) $request->input('medio_transporte_otro')) === '') {
            throw ValidationException::withMessages(['medio_transporte_otro' => 'Debe especificar el otro medio de transporte.']);
        }
        if (($data['motivo'] ?? '') === 'Otras' && trim((string) $request->input('motivo_otro')) === '') {
            throw ValidationException::withMessages(['motivo_otro' => 'Debe especificar el motivo cuando selecciona Otras.']);
        }

        if (!$request->hasFile('archivo_citacion_invitacion') && !$request->filled('archivo_citacion_invitacion_existente')) {
            throw ValidationException::withMessages(['archivo_citacion_invitacion' => 'Debe adjuntar citación o invitación.']);
        }

        if ($request->input('accion') === 'enviar' && !$request->boolean('declaracion_aceptada')) {
            throw ValidationException::withMessages(['declaracion_aceptada' => 'Debe confirmar la declaración antes de enviar la solicitud.']);
        }

        $requiereAvion = in_array('Avión', $data['medios_transporte'] ?? [], true);
        $diasHabiles = $requiereAvion ? app(BusinessDaysCalculator::class)->between(now(), $data['fecha_desde']) : null;
        if ($requiereAvion && empty($data['tipo_pasaje_aereo'])) {
            throw ValidationException::withMessages(['tipo_pasaje_aereo' => 'Debe seleccionar si el pasaje aéreo requerido es solo ida, solo regreso o ida y regreso.']);
        }
        if ($requiereAvion && empty($funcionarioAc->fecha_nacimiento)) {
            throw ValidationException::withMessages([
                'medios_transporte' => 'El funcionario AC debe tener fecha de nacimiento registrada para solicitar compra de pasaje aéreo.',
            ]);
        }
        if (! $requiereAvion) {
            $data['tipo_pasaje_aereo'] = null;
        }
        if ($requiereAvion && $diasHabiles !== null && $diasHabiles < 7 && trim((string) $request->input('justificacion_menor_7_dias')) === '') {
            throw ValidationException::withMessages(['justificacion_menor_7_dias' => 'Debe justificar la solicitud con compra de pasaje aéreo ingresada con menos de 7 días hábiles de anticipación.']);
        }

        $data['solicita_viatico'] = $request->boolean('solicita_viatico');
        $data['solicita_reembolso'] = $request->boolean('solicita_reembolso');

        $gradoFuncionarioAc = trim((string) ($funcionarioAc->grado ?? ''));
        $gradoFuncionarioAcNormalizado = mb_strtoupper($gradoFuncionarioAc);
        $funcionarioAcSinGrado = $gradoFuncionarioAc === ''
            || in_array($gradoFuncionarioAcNormalizado, ['-', 'SIN GRADO', 'SIN GRADO REGISTRADO', 'S/G', 'NO APLICA', 'N/A'], true)
            || str_contains($gradoFuncionarioAcNormalizado, 'SIN GRADO')
            || ! preg_match('/\d+/', $gradoFuncionarioAc);

        if ($funcionarioAcSinGrado && $data['solicita_viatico']) {
            throw ValidationException::withMessages([
                'solicita_viatico' => 'El funcionario de Administración Central no registra grado, por lo tanto no corresponde viático. Sólo puede solicitar devolución de gastos / reembolso.',
            ]);
        }

        if ($funcionarioAcSinGrado) {
            $data['solicita_viatico'] = false;
        }

        $cometidoAcEnConglomeradoSinViatico = ! $esExtranjero
            && $this->esComunaConglomeradoSinViaticoAc($comunaOrigen?->name ?? null)
            && $this->esComunaConglomeradoSinViaticoAc($comunaDestino?->name ?? null);

        if ($cometidoAcEnConglomeradoSinViatico && $data['solicita_viatico']) {
            throw ValidationException::withMessages([
                'solicita_viatico' => 'No corresponde viático: origen y destino pertenecen al conglomerado de comunas sin derecho a viático, conforme Decreto Exento N° 90/2018 del Ministerio de Hacienda.',
            ]);
        }

        if ($cometidoAcEnConglomeradoSinViatico) {
            $data['solicita_viatico'] = false;
        }

        $data['contempla_alojamiento'] = $data['solicita_viatico'] ? $request->boolean('contempla_alojamiento') : false;
        $data['servicio_contempla_colacion'] = $data['solicita_viatico'] ? ($data['servicio_contempla_colacion'] ?? 'no_informado') : 'no_informado';
        $data = $this->normalizarAnticipoViaticoData($data, $request->boolean('solicita_anticipo_viatico'), $funcionarioAc, null, null);

        if ($data['solicita_viatico'] || $data['solicita_reembolso']) {
            $bancoPago = trim((string) ($data['banco_pago'] ?? ''));
            $tipoCuentaPago = trim((string) ($data['tipo_cuenta_pago'] ?? ''));
            $numeroCuentaPago = trim((string) ($data['numero_cuenta_pago'] ?? ''));
            $errores = [];
            if ($bancoPago === '' || ! in_array($bancoPago, $this->bancosPago(), true)) {
                $errores['banco_pago'] = 'Debe seleccionar banco para pago.';
            }
            if ($tipoCuentaPago === '' || ! in_array($tipoCuentaPago, $this->tiposCuentaPago(), true)) {
                $errores['tipo_cuenta_pago'] = 'Debe seleccionar tipo de cuenta.';
            }
            if ($numeroCuentaPago === '') {
                $errores['numero_cuenta_pago'] = 'Debe ingresar número de cuenta.';
            }
            if ($errores) {
                throw ValidationException::withMessages($errores);
            }
            $data['banco_pago'] = $bancoPago;
            $data['tipo_cuenta_pago'] = $tipoCuentaPago;
            $data['numero_cuenta_pago'] = $numeroCuentaPago;
        } else {
            $data['banco_pago'] = null;
            $data['tipo_cuenta_pago'] = null;
            $data['numero_cuenta_pago'] = null;
        }

        $data['existe_citacion_invitacion'] = true;
        $data['es_destino_extranjero'] = $esExtranjero;
        $data['comuna_origen_nombre'] = $comunaOrigen?->name;
        $data['comuna_destino_nombre'] = $comunaDestino?->name;
        $data['requiere_pasaje_aereo'] = $requiereAvion;
        $data['dias_habiles_anticipacion'] = $diasHabiles;
        $data['declaracion_aceptada'] = $request->input('accion') === 'enviar' ? true : $request->boolean('declaracion_aceptada');
        $data['declaracion_aceptada_at'] = $request->input('accion') === 'enviar' ? now() : null;
        $data['declaracion_texto'] = $request->input('accion') === 'enviar' ? $this->declaracionJuradaTexto() : null;

        unset($data['archivo_citacion_invitacion']);
        return $data;
    }


    private function normalizarAnticipoViaticoData(array $data, bool $solicitaAnticipo, ?FuncionarioAcAutorizado $funcionarioAc = null, ?ReemplazoPersonal $funcionario = null, ?Establecimiento $establecimiento = null): array
    {
        $data['solicita_anticipo_viatico'] = false;
        $data['porcentaje_anticipo_viatico'] = null;
        $data['monto_anticipo_viatico'] = null;
        $data['monto_saldo_viatico'] = null;

        if (! (bool) ($data['solicita_viatico'] ?? false)) {
            if ($solicitaAnticipo) {
                throw ValidationException::withMessages(['solicita_anticipo_viatico' => 'Sólo puede solicitar anticipo cuando el cometido tiene derecho a viático.']);
            }
            return $data;
        }

        if (! $solicitaAnticipo) {
            return $data;
        }

        $dias = $this->diasSolicitadosAnticipo($data['fecha_desde'] ?? null, $data['fecha_hasta'] ?? null);
        if ($dias < 3) {
            throw ValidationException::withMessages(['solicita_anticipo_viatico' => 'El anticipo de viático sólo se habilita para cometidos de 3 días o más.']);
        }

        $temp = new CometidoFuncionario(array_merge($data, [
            'origen_cometido' => $funcionarioAc ? 'administracion_central' : 'establecimiento',
            'funcionario_ac_autorizado_id' => $funcionarioAc?->id,
            'estamento' => $funcionarioAc ? ($funcionarioAc->escalafon ?? null) : ($funcionario->estatuto ?? null),
            'cargo_funcion' => $funcionarioAc ? ($funcionarioAc->cargo_funcion ?? null) : ($funcionario->escalafon ?? null),
        ]));
        if ($funcionarioAc) {
            $temp->setRelation('funcionarioAcAutorizado', $funcionarioAc);
        }

        $totalViatico = (int) (($this->calcularViaticoAutomaticoCdp($temp)['total'] ?? 0));
        if ($totalViatico <= 0) {
            throw ValidationException::withMessages(['solicita_anticipo_viatico' => 'No fue posible calcular un monto de viático para solicitar anticipo.']);
        }

        $montoAnticipo = (int) round($totalViatico * 0.60);
        $data['solicita_anticipo_viatico'] = true;
        $data['porcentaje_anticipo_viatico'] = 60;
        $data['monto_anticipo_viatico'] = $montoAnticipo;
        $data['monto_saldo_viatico'] = max(0, $totalViatico - $montoAnticipo);

        return $data;
    }

    private function diasSolicitadosAnticipo($fechaDesde, $fechaHasta): int
    {
        if (!$fechaDesde || !$fechaHasta) {
            return 0;
        }

        $desde = Carbon::parse($fechaDesde)->startOfDay();
        $hasta = Carbon::parse($fechaHasta)->startOfDay();
        if ($hasta->lt($desde)) {
            return 0;
        }

        return $desde->diffInDays($hasta) + 1;
    }

    private function formData(CometidoFuncionario $cometido, Establecimiento $establecimiento, $funcionarios, ?array $periodo): array
    {
        $regiones = config('chile.regiones', []);
        $communesByRegion = Commune::query()
            ->orderBy('region_code')
            ->orderBy('name')
            ->get()
            ->groupBy('region_code')
            ->map(fn($c) => $c->map(fn($x) => ['id' => $x->id, 'name' => $x->name])->values())
            ->toArray();

        $rutBodies = $funcionarios
            ->map(fn($funcionario) => $this->rutBodyFuncionario($funcionario->rut ?? null))
            ->filter()
            ->unique()
            ->values();

        $viaticosAnexoRutBodies = $rutBodies->isEmpty()
            ? []
            : FuncionarioViaticoAnexo::query()
                ->activos()
                ->whereIn('rut_body', $rutBodies->all())
                ->pluck('rut_body')
                ->all();

        return [
            'cometido' => $cometido,
            'establecimiento' => $establecimiento,
            'funcionarios' => $funcionarios,
            'periodo' => $periodo,
            'regiones' => $regiones,
            'communesByRegion' => $communesByRegion,
            'mediosTransporte' => $this->mediosTransporte,
            'motivos' => $this->motivos,
            'bancosPago' => $this->bancosPago(),
            'tiposCuentaPago' => $this->tiposCuentaPago(),
            'viaticosAnexoRutBodies' => $viaticosAnexoRutBodies,
        ];
    }

    private function bancosPago(): array
    {
        return [
            'Banco de Chile',
            'BancoEstado',
            'Scotiabank',
            'BCI',
            'Corpbanca',
            'Banco BICE',
            'Banco Santander',
            'Banco Itaú',
            'Banco Security',
            'Banco Falabella',
            'Banco Ripley',
            'Rabobank Chile',
            'Banco Consorcio',
            'Banco BBVA',
            'Coopeuch',
            'Tenpo',
            'Tapp Caja Los Andes',
            'Copec Pay',
            'American Express',
            'Mercado Pago',
        ];
    }

    private function tiposCuentaPago(): array
    {
        return ['Cuenta Corriente', 'Cuenta Vista', 'Cuenta RUT', 'Chequera Electrónica'];
    }

    private function validatedData(Request $request, ReemplazoPersonal $funcionario, Establecimiento $establecimiento, ?CometidoFuncionario $cometido = null): array
    {
        $esAaee = $this->esAaee($funcionario);
        $data = $request->validate([
            'reemplazo_personal_id' => ['required', 'integer', 'exists:reemplazos_personal,id'],
            'region_destino' => ['required', 'string', 'max:20'],
            'comuna_destino_id' => ['required', 'integer', 'exists:communes,id'],
            'institucion_destino' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
            'fecha_desde' => ['required', 'date', 'after_or_equal:today'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
            'hora_salida' => ['required', 'date_format:H:i'],
            'hora_regreso' => ['required', 'date_format:H:i'],
            'medios_transporte' => ['required', 'array', 'min:1'],
            'medios_transporte.*' => ['string', Rule::in($this->mediosTransporte)],
            'medio_transporte_otro' => ['nullable', 'string', 'max:255'],
            'motivo' => ['required', 'string', Rule::in($this->motivos)],
            'motivo_otro' => ['nullable', 'string', 'max:255'],
            'descripcion_actividades' => ['required', 'string', 'min:20', 'max:3000'],
            'existe_citacion_invitacion' => ['required', 'boolean'],
            'archivo_citacion_invitacion' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'archivo_oficio' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'archivo_formulario_cometido' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
            'solicita_viatico' => ['nullable', 'boolean'],
            'solicita_reembolso' => ['required', 'boolean'],
            'contempla_alojamiento' => ['nullable', 'boolean'],
            'servicio_contempla_colacion' => ['nullable', 'string', Rule::in(['si', 'no', 'no_informado'])],
            'solicita_anticipo_viatico' => ['nullable', 'boolean'],
            'banco_pago' => ['nullable', 'string', 'max:120'],
            'tipo_cuenta_pago' => ['nullable', 'string', 'max:80'],
            'numero_cuenta_pago' => ['nullable', 'string', 'max:40'],
            'declaracion_aceptada' => ['nullable', 'accepted'],
        ]);

        if (in_array('Otro', $data['medios_transporte'] ?? [], true) && trim((string) $request->input('medio_transporte_otro')) === '') {
            throw ValidationException::withMessages(['medio_transporte_otro' => 'Debe especificar el otro medio de transporte.']);
        }

        if (($data['motivo'] ?? '') === 'Otras' && trim((string) $request->input('motivo_otro')) === '') {
            throw ValidationException::withMessages(['motivo_otro' => 'Debe especificar el motivo cuando selecciona Otras.']);
        }

        if ($request->boolean('existe_citacion_invitacion') && !$request->hasFile('archivo_citacion_invitacion') && !$request->filled('archivo_citacion_invitacion_existente')) {
            throw ValidationException::withMessages(['archivo_citacion_invitacion' => 'Debe adjuntar la citación o invitación si indicó que existe.']);
        }

        if ($request->input('accion') === 'enviar') {
            if (!$request->boolean('declaracion_aceptada')) {
                throw ValidationException::withMessages(['declaracion_aceptada' => 'Debe confirmar que los datos ingresados corresponden y son coincidentes con los documentos de respaldo subidos antes de enviar la solicitud.']);
            }


            if (!$request->hasFile('archivo_formulario_cometido') && !$this->existeDocumentoTipo($cometido, 'formulario_cometido')) {
                throw ValidationException::withMessages(['archivo_formulario_cometido' => 'Debe adjuntar el Formulario de Cometido antes de enviar la solicitud.']);
            }
        }

        $comuna = Commune::find($data['comuna_destino_id']);
        if ($comuna && (string) $comuna->region_code !== (string) $data['region_destino']) {
            throw ValidationException::withMessages(['comuna_destino_id' => 'La comuna destino no corresponde a la región seleccionada.']);
        }

        $puedeSolicitarViatico = $this->puedeSolicitarViatico($funcionario, $establecimiento, $comuna);
        $data['solicita_viatico'] = $puedeSolicitarViatico ? $request->boolean('solicita_viatico') : false;

        $data['solicita_reembolso'] = $request->boolean('solicita_reembolso');
        $data['contempla_alojamiento'] = $data['solicita_viatico'] ? $request->boolean('contempla_alojamiento') : false;
        $data['servicio_contempla_colacion'] = $data['solicita_viatico'] ? ($data['servicio_contempla_colacion'] ?? 'no_informado') : 'no_informado';
        $data = $this->normalizarAnticipoViaticoData($data, $request->boolean('solicita_anticipo_viatico'), null, $funcionario, $establecimiento);

        if ($data['solicita_viatico'] || $data['solicita_reembolso']) {
            $bancoPago = trim((string) ($data['banco_pago'] ?? ''));
            $tipoCuentaPago = trim((string) ($data['tipo_cuenta_pago'] ?? ''));
            $numeroCuentaPago = trim((string) ($data['numero_cuenta_pago'] ?? ''));

            $erroresBancarios = [];
            if ($bancoPago === '' || !in_array($bancoPago, $this->bancosPago(), true)) {
                $erroresBancarios['banco_pago'] = 'Debe seleccionar un banco válido para gestionar el pago.';
            }
            if ($tipoCuentaPago === '' || !in_array($tipoCuentaPago, $this->tiposCuentaPago(), true)) {
                $erroresBancarios['tipo_cuenta_pago'] = 'Debe seleccionar un tipo de cuenta válido para gestionar el pago.';
            }
            if ($numeroCuentaPago === '') {
                $erroresBancarios['numero_cuenta_pago'] = 'Debe ingresar el número de cuenta para gestionar el pago.';
            }
            if ($erroresBancarios) {
                throw ValidationException::withMessages($erroresBancarios);
            }

            $data['banco_pago'] = $bancoPago;
            $data['tipo_cuenta_pago'] = $tipoCuentaPago;
            $data['numero_cuenta_pago'] = $numeroCuentaPago;
        } else {
            $data['banco_pago'] = null;
            $data['tipo_cuenta_pago'] = null;
            $data['numero_cuenta_pago'] = null;
        }

        $data['existe_citacion_invitacion'] = $request->boolean('existe_citacion_invitacion');
        $data['declaracion_aceptada'] = $request->input('accion') === 'enviar' ? true : $request->boolean('declaracion_aceptada');
        $data['declaracion_aceptada_at'] = $request->input('accion') === 'enviar' ? now() : null;
        $data['declaracion_texto'] = $request->input('accion') === 'enviar' ? $this->declaracionJuradaTexto() : null;
        $data['comuna_destino_nombre'] = $comuna?->name;

        unset($data['archivo_citacion_invitacion'], $data['archivo_oficio'], $data['archivo_formulario_cometido'], $data['reemplazo_personal_id']);

        return $data;
    }


    private function declaracionJuradaTexto(): string
    {
        return 'Confirmo que los datos ingresados en esta solicitud de cometido funcionario corresponden y son coincidentes con los documentos de respaldo subidos al sistema; que la información registrada se sustenta en dichos antecedentes y que la solicitud se presenta para revisión UATP sobre la base de esos respaldos.';
    }

    private function funcionarioPadronSeleccionado(Request $request, Establecimiento $establecimiento, $funcionarios): ReemplazoPersonal
    {
        $id = (int) $request->input('reemplazo_personal_id');
        $idsPeriodo = $funcionarios->pluck('id')->map(fn($v) => (int) $v)->all();

        if (!in_array($id, $idsPeriodo, true)) {
            throw ValidationException::withMessages(['reemplazo_personal_id' => 'El funcionario seleccionado no pertenece al último padrón activo/cargado del establecimiento.']);
        }

        return ReemplazoPersonal::query()
            ->where('id', $id)
            ->where('establecimiento_id', $establecimiento->id)
            ->firstOrFail();
    }

    private function funcionariosUltimoPadron(Establecimiento $establecimiento, ?int $includeId = null): array
    {
        $base = ReemplazoPersonal::query()
            ->where('establecimiento_id', $establecimiento->id);

        if (Schema::hasColumn('reemplazos_personal', 'vigente')) {
            $base->where('vigente', true);
        }

        $periodo = (clone $base)
            ->whereNotNull('anio')
            ->whereNotNull('mes')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first(['anio', 'mes']);

        if (!$periodo && Schema::hasColumn('reemplazos_personal', 'vigente')) {
            $base = ReemplazoPersonal::query()->where('establecimiento_id', $establecimiento->id);
            $periodo = (clone $base)->whereNotNull('anio')->whereNotNull('mes')->orderByDesc('anio')->orderByDesc('mes')->first(['anio', 'mes']);
        }

        $q = ReemplazoPersonal::query()->where('establecimiento_id', $establecimiento->id);
        if ($periodo) {
            $q->where('anio', $periodo->anio)->where('mes', $periodo->mes);
        }

        if ($includeId) {
            $q->orWhere(function ($sub) use ($includeId, $establecimiento) {
                $sub->where('id', $includeId)->where('establecimiento_id', $establecimiento->id);
            });
        }

        $funcionarios = $q
            ->orderBy('nombre')
            ->orderBy('rut')
            ->orderByDesc('id')
            ->get();

        $funcionarios = $this->funcionariosUnicosParaSelect($funcionarios, $includeId);

        return [
            $periodo ? ['anio' => (int) $periodo->anio, 'mes' => (int) $periodo->mes] : null,
            $funcionarios,
        ];
    }

    private function funcionariosUnicosParaSelect($funcionarios, ?int $includeId = null)
    {
        if ($funcionarios->isEmpty()) {
            return $funcionarios;
        }

        if ($includeId) {
            $funcionarios = $funcionarios
                ->sortByDesc(fn($funcionario) => (int) $funcionario->id === (int) $includeId ? 1 : 0)
                ->values();
        }

        $vistos = [];
        $unicos = collect();

        foreach ($funcionarios as $funcionario) {
            $clave = $this->claveFuncionarioPadron($funcionario);

            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $unicos->push($funcionario);
        }

        return $unicos
            ->sortBy(fn($funcionario) => mb_strtoupper((string) ($funcionario->nombre ?? '')))
            ->values();
    }

    private function claveFuncionarioPadron(ReemplazoPersonal $funcionario): string
    {
        $rutNormalizado = preg_replace('/[^0-9kK]/', '', (string) ($funcionario->rut ?? ''));

        if ($rutNormalizado !== '') {
            return 'rut:' . mb_strtoupper($rutNormalizado);
        }

        return 'sin-rut:' . mb_strtoupper(trim((string) ($funcionario->nombre ?? ''))) . '|' . mb_strtoupper(trim((string) ($funcionario->estatuto ?? ''))) . '|' . mb_strtoupper(trim((string) ($funcionario->escalafon ?? '')));
    }

    private function guardarCitacion(Request $request, CometidoFuncionario $cometido): void
    {
        if (!$request->hasFile('archivo_citacion_invitacion')) {
            return;
        }

        $documento = $this->guardarDocumentoTipo($request, $cometido, 'archivo_citacion_invitacion', 'citacion_invitacion', 'cometidos-funcionarios/citaciones');

        $cometido->update([
            'archivo_citacion_invitacion_path' => $documento->path,
            'archivo_citacion_invitacion_nombre' => $documento->nombre_original,
        ]);
    }

    private function guardarDocumentosFormulario(Request $request, CometidoFuncionario $cometido): void
    {
        $this->guardarDocumentoTipo($request, $cometido, 'archivo_oficio', 'oficio', 'cometidos-funcionarios/oficios');
        $this->guardarDocumentoTipo($request, $cometido, 'archivo_formulario_cometido', 'formulario_cometido', 'cometidos-funcionarios/formularios');
    }

    private function guardarDocumentoTipo(Request $request, CometidoFuncionario $cometido, string $input, string $tipo, string $directorio): ?CometidoFuncionarioDocumento
    {
        if (!$request->hasFile($input)) {
            return null;
        }

        $file = $request->file($input);
        $path = $file->store($directorio);

        $anteriores = $cometido->documentos()->where('tipo', $tipo)->get();
        foreach ($anteriores as $anterior) {
            if ($anterior->path) {
                Storage::delete($anterior->path);
            }
            $anterior->delete();
        }

        return CometidoFuncionarioDocumento::create([
            'cometido_funcionario_id' => $cometido->id,
            'tipo' => $tipo,
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);
    }

    private function existeDocumentoTipo(?CometidoFuncionario $cometido, string $tipo): bool
    {
        if (!$cometido || !$cometido->exists) {
            return false;
        }

        return $cometido->documentos()->where('tipo', $tipo)->exists();
    }


    private function cerrarCometidoSiFlujoFinalizado(CometidoFuncionario $cometido, string $estadoAnterior, ?string $observacion = null): bool
    {
        $cometido->refresh();

        if ((string) $cometido->estado === 'cerrado') {
            return false;
        }

        $requiereViatico = (bool) $cometido->solicita_viatico;
        $requiereReembolso = (bool) $cometido->solicita_reembolso;

        $viaticoFinalizado = ! $requiereViatico
            || in_array((string) ($cometido->estado_viatico ?? ''), ['viatico_pagado', 'pagado'], true)
            || ! empty($cometido->fecha_pago_viatico);

        $reembolsoFinalizado = ! $requiereReembolso
            || in_array((string) ($cometido->estado_reembolso ?? ''), ['reembolso_pagado', 'cerrado_sin_pago_reembolso'], true)
            || ! empty($cometido->fecha_pago_reembolso);

        if (! $viaticoFinalizado || ! $reembolsoFinalizado) {
            return false;
        }

        $cometido->forceFill(['estado' => 'cerrado'])->save();

        $this->registrarHistorial(
            $cometido,
            $estadoAnterior,
            'cerrado',
            'Trámite de cometido funcionario cerrado automáticamente al finalizar el flujo financiero',
            $observacion ?: 'Cierre automático por finalización de viático y/o reembolso.'
        );

        return true;
    }

    private function registrarHistorial(CometidoFuncionario $cometido, ?string $estadoAnterior, ?string $estadoNuevo, string $accion, ?string $observacion = null): void
    {
        CometidoFuncionarioHistorial::create([
            'cometido_funcionario_id' => $cometido->id,
            'user_id' => auth()->id(),
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'accion' => $accion,
            'observacion' => $observacion,
        ]);
    }

    private function establecimientoDelUsuario(): Establecimiento
    {
        $user = auth()->user();
        $user->loadMissing('establecimiento');

        if (!$user->establecimiento) {
            abort(403, 'Usuario sin establecimiento asociado.');
        }

        return $user->establecimiento;
    }

    private function authorizeCometido(Request $request, CometidoFuncionario $cometido): void
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if ($activeRole === 'funcionario_estab') {
            $establecimiento = $this->establecimientoDelUsuario();
            abort_unless((int) $cometido->establecimiento_id === (int) $establecimiento->id, 403);
            return;
        }

        if ($this->puedeRevisarJefaturaAc($request, $cometido)) {
            return;
        }

        if ($activeRole === 'director_ejecutivo' && $this->directorEjecutivoPuedeVerCometido($user, $cometido)) {
            return;
        }

        if ($activeRole === 'funcionario_ac') {
            $funcionarioAc = app(FuncionarioAcAutorizadorResolver::class)->funcionarioParaUsuario($user);
            abort_unless($funcionarioAc && (int) $cometido->funcionario_ac_autorizado_id === (int) $funcionarioAc->id, 403);
            return;
        }

        abort_unless($this->userHasAnyRole($user, ['admin', 'coordinador_uatp', 'funcionario_slep', 'coordinador_gdp', 'supervisor_plani', 'coordinador_plani', 'funcionario_daf', 'juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica', 'funcionario_daf_compra']), 403);
    }



    private function notificarServiciosGeneralesVehiculoInstitucional(?CometidoFuncionario $cometido): void
    {
        if (! $cometido) {
            return;
        }

        $medios = is_array($cometido->medios_transporte) ? $cometido->medios_transporte : [];
        if (! in_array('Vehículo institucional', $medios, true)) {
            return;
        }

        if ($cometido->ssgg_notificado_vehiculo_at) {
            return;
        }

        $correos = CometidoNotificacionConfiguracion::correosPara(
            'servicios_generales_vehiculo_institucional',
            'johanna.isla@slepandaliencosta.gob.cl'
        );

        if (empty($correos)) {
            return;
        }

        try {
            Mail::to($correos)->send(new CometidoFuncionarioNotificationMail(
                recipientName: 'Servicios Generales',
                title: 'Cometido autorizado requiere coordinación de vehículo institucional',
                messageText: 'Se informa que el cometido funcionario ' . ($cometido->numero_cometido_interno ?: ('#' . $cometido->id)) . ' fue autorizado y contempla uso de vehículo institucional. Se solicita revisar antecedentes del viaje, fechas, horarios, origen, destino y coordinar la disponibilidad del vehículo institucional según corresponda.',
                cometido: $cometido,
                actionText: 'Ver cometido',
                actionUrl: route('tramites.cometidos-funcionarios.show', $cometido),
                badgeText: 'Vehículo institucional',
                footerNote: 'Correo automático enviado a Servicios Generales por uso de vehículo institucional en cometido funcionario.',
                attachmentPack: 'expediente_aprobado'
            ));

            $cometido->update([
                'ssgg_notificado_vehiculo_at' => now(),
                'ssgg_notificado_vehiculo_email' => implode(', ', $correos),
                'ssgg_notificado_vehiculo_por' => auth()->id(),
            ]);

            $this->registrarHistorial(
                $cometido,
                $cometido->estado,
                $cometido->estado,
                'Notificación enviada a Servicios Generales por vehículo institucional',
                'Destinatario(s): ' . implode(', ', $correos)
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notificarRol($roles, string $subject, string $body, ?CometidoFuncionario $cometido = null, ?string $actionText = null, ?string $badgeText = null, ?string $attachmentPack = null): void
    {
        try {
            $roles = is_array($roles) ? $roles : [$roles];
            $clave = CometidoNotificacionConfiguracion::claveParaAsunto($subject) ?: 'notificacion_' . Str::slug($subject, '_');
            $correos = CometidoNotificacionConfiguracion::correosPorRoles($clave, $roles);

            foreach ($correos as $correo) {
                Mail::to($correo)->send(new CometidoFuncionarioNotificationMail(
                    recipientName: $this->nombreDestinatarioFallbackRol($roles),
                    title: $subject,
                    messageText: $body,
                    cometido: $cometido,
                    actionText: $actionText ?: 'Ver cometido',
                    actionUrl: $cometido ? route('tramites.cometidos-funcionarios.show', $cometido) : route('tramites.cometidos-funcionarios.index'),
                    badgeText: $badgeText,
                    footerNote: 'Esta notificación fue enviada según la configuración de Notificaciones de Cometidos para la clave ' . $clave . '.',
                    attachmentPack: $attachmentPack
                ));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function correosFallbackPorRol(array $roles): array
    {
        $rolesNormalizados = collect($roles)
            ->map(fn ($rol) => strtolower(trim((string) $rol)))
            ->filter()
            ->values();

        if ($rolesNormalizados->contains('director_ejecutivo')) {
            return CometidoNotificacionConfiguracion::correosPara(
                'director_ejecutivo_notificacion',
                (string) env('DIRECTOR_EJECUTIVO_EMAIL', '')
            );
        }

        return [];
    }

    private function nombreDestinatarioFallbackRol(array $roles): string
    {
        $rolesNormalizados = collect($roles)
            ->map(fn ($rol) => strtolower(trim((string) $rol)))
            ->filter()
            ->values();

        if ($rolesNormalizados->contains('director_ejecutivo')) {
            return 'Director Ejecutivo';
        }

        return 'Destinatario';
    }

    private function notificarUsuarioId(?int $userId, string $subject, string $body, ?CometidoFuncionario $cometido = null, ?string $actionText = null, ?string $badgeText = null, ?string $attachmentPack = null): void
    {
        $excluir = [];

        try {
            $user = $userId ? User::find($userId) : null;
            if ($user && $user->email) {
                $excluir[] = $user->email;
                Mail::to($user->email)->send(new CometidoFuncionarioNotificationMail(
                    recipientName: $this->nombreDestinatarioNotificacion($user),
                    title: $subject,
                    messageText: $body,
                    cometido: $cometido,
                    actionText: $actionText ?: 'Ver cometido',
                    actionUrl: $cometido ? route('tramites.cometidos-funcionarios.show', $cometido) : route('tramites.cometidos-funcionarios.index'),
                    badgeText: $badgeText,
                    footerNote: 'Esta notificación forma parte de la trazabilidad del trámite de cometido funcionario.',
                    attachmentPack: $attachmentPack
                ));
            }

            $this->notificarCopiasConfiguradasPorAsunto($subject, $body, $cometido, $actionText, $badgeText, $attachmentPack, $excluir);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notificarFuncionarioAc(?FuncionarioAcAutorizado $funcionario, string $subject, string $body, ?CometidoFuncionario $cometido = null, ?string $actionText = null, ?string $badgeText = null, ?string $attachmentPack = null): void
    {
        if (! $funcionario) {
            return;
        }

        $excluir = [];

        try {
            $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($funcionario->rut_normalizado ?: ($funcionario->run . $funcionario->dv))));
            $user = $rut !== '' ? User::query()->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(COALESCE(rut, '')), '.', ''), '-', ''), ' ', '') = ?", [$rut])->first() : null;
            if ($user && $user->email) {
                $excluir[] = $user->email;
                Mail::to($user->email)->send(new CometidoFuncionarioNotificationMail(
                    recipientName: $this->nombreDestinatarioNotificacion($user, $funcionario->nombre_completo ?? null),
                    title: $subject,
                    messageText: $body,
                    cometido: $cometido,
                    actionText: $actionText ?: 'Ver cometido',
                    actionUrl: $cometido ? route('tramites.cometidos-funcionarios.show', $cometido) : route('tramites.cometidos-funcionarios.index'),
                    badgeText: $badgeText,
                    footerNote: 'Esta notificación forma parte de la trazabilidad del trámite de cometido funcionario.',
                    attachmentPack: $attachmentPack
                ));
            }

            $this->notificarCopiasConfiguradasPorAsunto($subject, $body, $cometido, $actionText, $badgeText, $attachmentPack, $excluir);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notificarCopiasConfiguradasPorAsunto(string $subject, string $body, ?CometidoFuncionario $cometido = null, ?string $actionText = null, ?string $badgeText = null, ?string $attachmentPack = null, array $excluir = []): void
    {
        $excluirNormalizados = collect($excluir)
            ->map(fn ($correo) => strtolower(trim((string) $correo)))
            ->filter()
            ->all();

        foreach (CometidoNotificacionConfiguracion::correosAdicionalesPorAsunto($subject) as $correo) {
            if (in_array(strtolower(trim((string) $correo)), $excluirNormalizados, true)) {
                continue;
            }

            Mail::to($correo)->send(new CometidoFuncionarioNotificationMail(
                recipientName: 'Copia notificación cometido',
                title: $subject,
                messageText: $body,
                cometido: $cometido,
                actionText: $actionText ?: 'Ver cometido',
                actionUrl: $cometido ? route('tramites.cometidos-funcionarios.show', $cometido) : route('tramites.cometidos-funcionarios.index'),
                badgeText: $badgeText,
                footerNote: 'Copia adicional enviada según la configuración de Notificaciones de Cometidos.',
                attachmentPack: $attachmentPack
            ));
        }
    }

    private function nombreDestinatarioNotificacion(?User $user, ?string $fallback = null): string
    {
        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $fallback = trim((string) $fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        $email = trim((string) ($user->email ?? ''));
        return $email !== '' ? Str::before($email, '@') : 'usuario/a';
    }

    private function descripcionEnvioAutorizacionAc(FuncionarioAcAutorizado $funcionarioAc, ?array $autorizador): string
    {
        $tipo = (string) ($autorizador['tipo_autorizacion'] ?? '');

        if ($tipo === 'subrogante_1_director_ejecutivo') {
            return 'Solicitud enviada por Director Ejecutivo a Subrogante 1 para autorización';
        }

        if ($tipo === 'director_autoriza_jefatura') {
            return 'Solicitud enviada por jefatura/subdirección a Director Ejecutivo para autorización';
        }

        if (($autorizador['es_subrogante'] ?? false) === true) {
            $nivel = $autorizador['nivel_subrogancia'] ?? null;
            return $nivel ? 'Solicitud enviada a subrogante ' . $nivel . ' para autorización' : 'Solicitud enviada a subrogante para autorización';
        }

        return (bool) ($funcionarioAc->jefatura ?? false)
            ? 'Solicitud enviada por jefatura AC a autorización superior'
            : 'Solicitud enviada por funcionario AC a autorización de jefatura';
    }

    private function puedeRegenerarSolicitudCometidoPdf(Request $request, CometidoFuncionario $cometido): bool
    {
        $user = $request->user();
        if (! $user || ! $cometido->esAdministracionCentral()) {
            return false;
        }

        if ($cometido->estado !== 'en_revision_jefatura_ac') {
            return false;
        }

        if (in_array((string) ($cometido->estado_autorizacion_jefatura_ac ?? ''), ['aprobado', 'rechazado'], true)) {
            return false;
        }

        if ($this->userHasAnyRole($user, ['admin'])) {
            return true;
        }

        if ((int) $cometido->user_id === (int) $user->id) {
            return true;
        }

        return $this->puedeRevisarJefaturaAc($request, $cometido);
    }

    private function directorEjecutivoPuedeVerCometido($user, CometidoFuncionario $cometido): bool
    {
        if (! $user || ! $this->userHasAnyRole($user, ['director_ejecutivo'])) {
            return false;
        }

        if ((int) ($cometido->user_id ?? 0) === (int) $user->id) {
            return true;
        }

        if ((string) ($cometido->estado ?? '') !== 'borrador') {
            return true;
        }

        if (! $cometido->esAdministracionCentral()) {
            return false;
        }

        $directorFuncionarioAc = app(FuncionarioAcAutorizadorResolver::class)->funcionarioParaUsuario($user);

        return $directorFuncionarioAc
            && (int) ($cometido->funcionario_ac_autorizado_id ?? 0) === (int) $directorFuncionarioAc->id;
    }

    private function puedeRevisarJefaturaAc(Request $request, CometidoFuncionario $cometido): bool
    {
        $user = $request->user();
        if (! $user || ! $cometido->esAdministracionCentral()) {
            return false;
        }
        if ($this->userHasAnyRole($user, ['admin'])) {
            return true;
        }
        if (! in_array($cometido->estado, ['en_revision_jefatura_ac', 'observado_jefatura_ac', 'rechazado_jefatura_ac', 'aprobado_jefatura_ac'], true)
            && empty($cometido->jefatura_autorizadora_ac_id)) {
            return false;
        }
        if ($this->userHasAnyRole($user, ['director_ejecutivo']) && $this->directorEjecutivoPuedeAutorizarComoJefatura($user, $cometido)) {
            return true;
        }

        $autorizador = FuncionarioAcAutorizado::find($cometido->jefatura_autorizadora_ac_id);
        return $autorizador ? app(FuncionarioAcAutorizadorResolver::class)->usuarioPuedeAutorizar($user, $autorizador) : false;
    }

    private function directorEjecutivoPuedeAutorizarComoJefatura($user, CometidoFuncionario $cometido): bool
    {
        if (! $user || ! $cometido->esAdministracionCentral()) {
            return false;
        }

        if (! in_array($cometido->estado, ['en_revision_jefatura_ac', 'observado_jefatura_ac', 'rechazado_jefatura_ac', 'aprobado_jefatura_ac'], true)) {
            return false;
        }

        $resolver = app(FuncionarioAcAutorizadorResolver::class);
        $funcionarioSolicitante = $cometido->funcionarioAcAutorizado;
        if (! $funcionarioSolicitante && $cometido->funcionario_ac_autorizado_id) {
            $funcionarioSolicitante = FuncionarioAcAutorizado::find($cometido->funcionario_ac_autorizado_id);
        }

        if ($funcionarioSolicitante && $resolver->esDirectorEjecutivo($funcionarioSolicitante)) {
            return false;
        }

        $directorFuncionarioAc = $resolver->funcionarioParaUsuario($user);
        if ($directorFuncionarioAc && (int) $cometido->jefatura_autorizadora_ac_id === (int) $directorFuncionarioAc->id) {
            return true;
        }

        if ($funcionarioSolicitante && (bool) ($funcionarioSolicitante->jefatura ?? false)) {
            return true;
        }

        if ($funcionarioSolicitante && $this->normaliza($funcionarioSolicitante->subdireccion_dependencia ?? '') === 'DIRECCION EJECUTIVA') {
            return true;
        }

        return false;
    }

    private function userHasAnyRole($user, array $roles): bool
    {
        $roles = collect($roles)
            ->map(fn ($role) => $this->normalizaRol($role))
            ->filter()
            ->values()
            ->all();

        if (empty($roles) || !$user || !method_exists($user, 'activeRoleName')) {
            return false;
        }

        $activeRole = $this->normalizaRol($user->activeRoleName());

        return $activeRole !== '' && in_array($activeRole, $roles, true);
    }

    private function normalizaRol(?string $role): string
    {
        return strtolower(trim((string) $role));
    }


    private function puedeSolicitarViatico(ReemplazoPersonal $funcionario, Establecimiento $establecimiento, ?Commune $comunaDestino): bool
    {
        if (!$this->funcionarioTieneViaticoAnexoActivo($funcionario)) {
            return false;
        }

        $comunaOrigen = $this->normalizaComuna($establecimiento->comuna ?? '');
        $destino = $this->normalizaComuna($comunaDestino?->name ?? '');

        return $destino !== ''
            && $comunaOrigen !== ''
            && $destino !== $comunaOrigen;
    }

    private function funcionarioTieneViaticoAnexoActivo(ReemplazoPersonal $funcionario): bool
    {
        $rutBody = $this->rutBodyFuncionario($funcionario->rut ?? null);
        if (!$rutBody) {
            return false;
        }

        return FuncionarioViaticoAnexo::query()
            ->activos()
            ->where('rut_body', $rutBody)
            ->exists();
    }

    private function rutBodyFuncionario(?string $rut): ?string
    {
        $normalized = RutChile::normalize($rut);

        return $normalized['rut_body'] ?? null;
    }

    private function esDocente(ReemplazoPersonal $funcionario): bool
    {
        $texto = $this->normaliza($funcionario->estatuto . ' ' . $funcionario->escalafon);
        return str_contains($texto, 'DOCENTE') || str_contains($texto, 'PROFESOR') || str_contains($texto, 'PROFESORA');
    }

    private function esAaee(ReemplazoPersonal $funcionario): bool
    {
        $texto = $this->normaliza($funcionario->estatuto . ' ' . $funcionario->escalafon);
        return str_contains($texto, 'AAEE')
            || str_contains($texto, 'ASISTENTE DE LA EDUCACION')
            || str_contains($texto, 'ASISTENTES DE LA EDUCACION')
            || str_contains($texto, 'ASISTENTE')
            || str_contains($texto, 'PARADOCENTE')
            || str_contains($texto, 'ADMINISTRATIVO')
            || str_contains($texto, 'AUXILIAR');
    }


    private function esComunaConglomeradoSinViaticoAc(?string $comuna): bool
    {
        return in_array($this->normalizaComuna($comuna), [
            'CONCEPCION',
            'SAN PEDRO DE LA PAZ',
            'CHIGUAYANTE',
            'PENCO',
            'TALCAHUANO',
            'HUALPEN',
            'CORONEL',
            'ISLA SANTA MARIA',
            'LOTA',
        ], true);
    }

    private function normalizaComuna(?string $value): string
    {
        $value = $this->normaliza($value);
        $value = preg_replace('/[\.\-_,;:]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        if (in_array($value, ['STA JUANA', 'SANTA JUANA'], true)) {
            return 'SANTA JUANA';
        }
        if (in_array($value, ['ISLA SANTA MARIA', 'ISLA STA MARIA', 'SANTA MARIA', 'STA MARIA'], true)) {
            return 'ISLA SANTA MARIA';
        }

        return $value ?: '';
    }

    private function normaliza(?string $value): string
    {
        $value = strtoupper(trim((string) $value));
        $value = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'], ['A', 'E', 'I', 'O', 'U', 'U', 'N'], $value);
        return preg_replace('/\s+/', ' ', $value) ?: '';
    }
}
