<?php

namespace App\Http\Controllers;

use App\Models\CometidoFuncionario;
use App\Models\CometidoFuncionarioRendicion;
use App\Support\ProfileChecklist;
use App\Support\SlepUiRegistry;
use App\Models\FuncionarioAcAutorizado;
use App\Support\Cometidos\FuncionarioAcAutorizadorResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Muestra el dashboard según el rol activo del usuario autenticado.
     */
    public function index()
    {
        $user = Auth::user();
        $activeRole = $user->activeRoleName();

        if (in_array($activeRole, ['postulante', 'funcionario'], true)) {
            $user->loadMissing(['postulantProfile', 'communes']);
            $check = ['percent' => 0, 'total' => 0, 'complete' => 0, 'missing' => [], 'ok' => false];

            try {
                $calc = ProfileChecklist::compute($user);
                $check = array_merge($check, (array) $calc);
                $check['percent'] = (int) ($check['percent'] ?? 0);
                $check['ok'] = array_key_exists('ok', $check) ? (bool) $check['ok'] : ($check['percent'] === 100);
            } catch (\Throwable $e) {
                Log::warning('[Dashboard] ProfileChecklist::compute failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            return view('dashboard.postulante', [
                'user' => $user,
                'check' => $check,
                'accountRole' => $activeRole,
                'activeRole' => $activeRole,
            ])->with('title', 'Mi Panel');
        }

        // El dashboard no debe bloquear por lista cerrada de roles: los permisos
        // finos se resuelven en menús, módulos y acciones internas. Esto evita
        // errores 403 cuando se agregan roles operativos nuevos, como DAF Compra.
        if (blank($activeRole)) {
            abort(403, 'Usuario sin rol activo para dashboard.');
        }

        return view('dashboard.slep', [
            'title' => SlepUiRegistry::dashboardTitle($activeRole)['title'],
            'user' => $user,
            'activeRole' => $activeRole,
            'dashboardContext' => SlepUiRegistry::dashboardTitle($activeRole),
            'quickModules' => SlepUiRegistry::quickModules($user, $activeRole),
            'cometidoMetrics' => $this->cometidoMetricsFor($user, $activeRole),
            'recentCometidos' => $this->recentCometidosFor($user, $activeRole),
        ]);
    }

    private function cometidoMetricsFor($user, string $activeRole): array
    {
        $query = $this->cometidoQueryFor($user, $activeRole);

        if (!$query) {
            return [
                'total' => 0,
                'pendientes' => 0,
                'observados' => 0,
                'viatico' => 0,
                'reembolso' => 0,
                'monto_cdp' => 0,
                'monto_viaticos' => 0,
                'monto_reembolsos' => 0,
                'monto_total_cometidos' => 0,
            ];
        }

        $cometidoIds = (clone $query)->pluck('id');
        $totalViaticos = (int) CometidoFuncionario::query()
            ->whereIn('id', $cometidoIds)
            ->sum('cdp_viatico_total');

        $totalReembolsos = (int) CometidoFuncionarioRendicion::with('resolucion')
            ->whereIn('cometido_funcionario_id', $cometidoIds)
            ->get()
            ->groupBy('cometido_funcionario_id')
            ->map(function ($rendiciones) {
                $rendicion = $rendiciones->sortByDesc('id')->first();
                $resolucion = $rendicion?->resolucion;

                foreach ([
                    $resolucion?->monto_pagado_reembolso,
                    $resolucion?->monto_resolucion,
                    $rendicion?->monto_cdp_reembolso,
                    $rendicion?->monto_autorizado_daf,
                ] as $monto) {
                    if ($monto !== null) {
                        return (int) $monto;
                    }
                }

                return 0;
            })
            ->sum();

        return [
            'total' => (clone $query)->count(),
            'pendientes' => (clone $query)->whereIn('estado', $this->pendingStatesFor($activeRole))->count(),
            'observados' => (clone $query)->whereIn('estado', ['observado_uatp', 'rechazado_uatp', 'cdp_rechazado'])->count(),
            'viatico' => (clone $query)->where('solicita_viatico', true)->count(),
            'reembolso' => (clone $query)->where('solicita_reembolso', true)->count(),
            'monto_cdp' => (int) ((clone $query)->sum('cdp_monto_total') ?? 0),
            'monto_viaticos' => $totalViaticos,
            'monto_reembolsos' => $totalReembolsos,
            'monto_total_cometidos' => $totalViaticos + $totalReembolsos,
        ];
    }

    private function recentCometidosFor($user, string $activeRole)
    {
        $query = $this->cometidoQueryFor($user, $activeRole);

        if (!$query) {
            return collect();
        }

        return $query->with('establecimiento')->latest()->limit(6)->get();
    }

    private function cometidoQueryFor($user, string $activeRole)
    {
        $query = CometidoFuncionario::query();

        if ($activeRole === 'funcionario_estab') {
            $user->loadMissing('establecimiento');
            if (!$user->establecimiento) {
                return null;
            }

            return $query->where('establecimiento_id', $user->establecimiento->id);
        }

        if ($activeRole === 'coordinador_uatp') {
            return $query->whereIn('estado', ['en_revision_uatp', 'observado_uatp', 'rechazado_uatp']);
        }

        if (in_array($activeRole, ['supervisor_plani', 'coordinador_plani'], true)) {
            return $query->where(function ($q) {
                $q->whereIn('estado', ['en_revision_cdp', 'pendiente_autorizacion_director_sin_disponibilidad', 'en_revision_cdp_rendicion', 'cdp_observado_rendicion', 'cdp_rechazado_rendicion'])
                    ->orWhereIn('estado_viatico', ['en_revision_cdp'])
                    ->orWhereIn('estado_reembolso', ['en_revision_cdp_rendicion', 'cdp_observado_rendicion', 'cdp_rechazado_rendicion']);
            });
        }

        if (in_array($activeRole, ['coordinador_gdp', 'funcionario_slep'], true)) {
            return $query->where(function ($q) {
                $q->whereIn('estado', ['en_gdp_resolucion', 'en_gdp_rex_cgr', 'autorizado_sin_gasto', 'resolucion_cometido_emitida', 'informe_pendiente_funcionario', 'informe_pendiente_jefatura', 'informe_observado', 'informe_aprobado', 'pendiente_rendicion', 'pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'])
                    ->orWhereIn('estado_viatico', ['en_gdp_resolucion', 'informe_pendiente_funcionario', 'informe_pendiente_jefatura', 'informe_aprobado']);
            });
        }

        if ($activeRole === 'funcionario_daf') {
            return $query->where(function ($q) {
                $q->whereIn('estado', ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'viatico_pagado', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'rendicion_observada_daf', 'rendicion_rechazada_daf', 'rendicion_autorizada_daf', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado'])
                    ->orWhereIn('estado_viatico', ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'viatico_pagado'])
                    ->orWhereIn('estado_reembolso', ['en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'rendicion_observada_daf', 'rendicion_rechazada_daf', 'rendicion_autorizada_daf', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado']);
            });
        }

        if ($activeRole === 'funcionario_daf_compra') {
            return $query->whereHas('pasajeAereo', function ($q) {
                $q->whereIn('estado_pasaje', ['pendiente_reserva', 'pendiente_compra', 'boleto_disponible']);
            });
        }
        if ($activeRole === 'director_ejecutivo') {
            return $this->directorEjecutivoCometidosQuery($query, $user);
        }


        if ($activeRole === 'funcionario_juridica') {
            return $query->where(function ($q) {
                $q->whereIn('estado', ['en_juridica_resolucion_reembolso', 'observada_juridica_reembolso', 'resolucion_reembolso_emitida'])
                    ->orWhereIn('estado_reembolso', ['en_juridica_resolucion_reembolso', 'observada_juridica_reembolso', 'resolucion_reembolso_emitida']);
            });
        }

        if ($activeRole === 'admin') {
            return $query;
        }

        return null;
    }

    private function directorEjecutivoCometidosQuery($query, $user)
    {
        return $query->whereNotIn('estado', ['borrador']);
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

    private function pendingStatesFor(string $activeRole): array
    {
        return match ($activeRole) {
            'funcionario_estab' => ['borrador', 'observado_uatp'],
            'coordinador_uatp' => ['en_revision_uatp'],
            'supervisor_plani', 'coordinador_plani' => ['en_revision_cdp', 'en_revision_cdp_rendicion', 'en_gestion_paralela'],
            'director_ejecutivo' => ['en_revision_jefatura_ac', 'pendiente_autorizacion_director_sin_disponibilidad'],
            'coordinador_gdp', 'funcionario_slep' => ['en_gdp_resolucion', 'en_gdp_rex_cgr', 'autorizado_sin_gasto', 'informe_pendiente_jefatura', 'pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe', 'en_gestion_paralela'],
            'funcionario_daf' => ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'en_gestion_paralela'],
            'funcionario_daf_compra' => ['pendiente_reserva', 'pendiente_compra'],
            'funcionario_juridica' => ['en_juridica_resolucion_reembolso'],
            'admin' => ['en_revision_uatp', 'en_revision_cdp', 'pendiente_autorizacion_director_sin_disponibilidad', 'en_gestion_paralela', 'en_gdp_resolucion', 'en_gdp_rex_cgr', 'informe_pendiente_jefatura', 'en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'pendiente_rendicion', 'pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'en_revision_cdp_rendicion', 'en_juridica_resolucion_reembolso', 'en_daf_contable_reembolso', 'en_pago_reembolso'],
            default => [],
        };
    }
}
