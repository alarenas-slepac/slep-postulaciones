<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\CargaFamiliar;
use App\Models\CargaFamiliarSolicitud;
use App\Models\CometidoFuncionario;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Models\User;
use App\Support\Rut;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([
                'query' => $term,
                'results' => [],
                'message' => 'Escribe al menos 2 caracteres para buscar.',
            ]);
        }

        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        $results = collect()
            ->merge($this->moduleResults($user, $activeRole, $term))
            ->merge($this->cargasSolicitudesResults($user, $activeRole, $term))
            ->merge($this->cargasVigentesResults($user, $activeRole, $term))
            ->merge($this->cometidosResults($user, $activeRole, $term))
            ->merge($this->funcionariosResults($user, $activeRole, $term))
            ->merge($this->establecimientosResults($user, $activeRole, $term))
            ->unique(fn (array $item) => ($item['type'] ?? '') . '|' . ($item['url'] ?? '') . '|' . ($item['title'] ?? ''))
            ->take(12)
            ->values()
            ->all();

        return response()->json([
            'query' => $term,
            'results' => $results,
            'message' => empty($results) ? 'Sin resultados para el rol activo.' : null,
        ]);
    }

    private function moduleResults($user, ?string $activeRole, string $term): array
    {
        $items = [];
        foreach (SlepUiRegistry::menuGroups($user, $activeRole) as $group => $entries) {
            foreach ($entries as $entry) {
                $items[] = $entry + ['group' => $group];
            }
        }
        foreach (SlepUiRegistry::quickModules($user, $activeRole) as $entry) {
            $items[] = $entry + ['group' => 'Acceso rápido'];
        }

        return collect($items)
            ->filter(fn ($entry) => $this->matches($term, [$entry['label'] ?? '', $entry['route'] ?? '', $entry['group'] ?? '']))
            ->filter(fn ($entry) => Route::has($entry['route'] ?? ''))
            ->map(fn ($entry) => $this->result('module', 'Módulo', $entry['label'], $entry['group'] ?? 'Acceso del sistema', route($entry['route']), $entry['icon'] ?? 'bi-grid'))
            ->values()
            ->all();
    }

    private function cargasSolicitudesResults($user, ?string $activeRole, string $term): array
    {
        if (!Schema::hasTable('cargas_familiares_solicitudes') || !Route::has('tramites.cargas-familiares.show')) {
            return [];
        }

        $query = CargaFamiliarSolicitud::query()->with('user')->latest();
        if (!$this->canReviewCargas($activeRole)) {
            if (!in_array($activeRole, ['postulante', 'funcionario', 'funcionario_ac'], true)) {
                return [];
            }
            $query->where('user_id', $user->id);
        }

        $this->whereSolicitudCargaMatches($query, $term);

        return $query->limit(5)->get()->map(function (CargaFamiliarSolicitud $solicitud) {
            $beneficiario = (array) ($solicitud->beneficiario_snapshot ?? []);
            $name = trim((string) ($beneficiario['nombre_completo'] ?? $beneficiario['nombre'] ?? $solicitud->user?->nombre_completo ?? $solicitud->user?->email ?? 'Solicitud'));
            return $this->result(
                'request',
                'Carga familiar',
                'Solicitud #' . $solicitud->id . ' - ' . $name,
                $solicitud->tipo_solicitud_label . ' · ' . $solicitud->estado_label,
                route('tramites.cargas-familiares.show', $solicitud),
                'bi-people'
            );
        })->all();
    }

    private function cargasVigentesResults($user, ?string $activeRole, string $term): array
    {
        if (!Schema::hasTable('cargas_familiares') || !Route::has('tramites.cargas-familiares.index')) {
            return [];
        }

        $query = CargaFamiliar::query()->latest();
        if (!$this->canReviewCargas($activeRole)) {
            if (!in_array($activeRole, ['postulante', 'funcionario', 'funcionario_ac'], true)) {
                return [];
            }
            $query->where('user_id', $user->id);
        }

        $this->whereLikeAny($query, $term, [
            'beneficiario_rut_completo',
            'beneficiario_run_normalizado',
            'beneficiario_nombres',
            'beneficiario_apellido_paterno',
            'beneficiario_apellido_materno',
            'causante_rut_completo',
            'causante_run_normalizado',
            'causante_nombres',
            'causante_apellido_paterno',
            'causante_apellido_materno',
            'codigo_siagf',
            'estado_carga',
        ]);

        return $query->limit(4)->get()->map(function (CargaFamiliar $carga) {
            $title = $carga->causante_nombre_completo ?: ($carga->beneficiario_nombre_completo ?: 'Carga familiar');
            $subtitle = trim(collect([$carga->causante_rut_completo, $carga->parentesco, $carga->estado_carga_label])->filter()->implode(' · '));
            return $this->result('record', 'Carga vigente', $title, $subtitle, route('tramites.cargas-familiares.index'), 'bi-person-heart');
        })->all();
    }

    private function cometidosResults($user, ?string $activeRole, string $term): array
    {
        if (!Schema::hasTable('cometidos_funcionarios') || !Route::has('tramites.cometidos-funcionarios.show')) {
            return [];
        }

        $query = CometidoFuncionario::query()->with('establecimiento')->latest();
        $this->scopeCometidos($query, $user, $activeRole);
        $this->whereLikeAny($query, $term, [
            'id',
            'funcionario_rut',
            'funcionario_nombre',
            'estado',
            'rbd',
            'institucion_destino',
            'destino',
            'numero_resolucion_cometido',
            'cdp_referencia',
        ]);

        return $query->limit(5)->get()->map(function (CometidoFuncionario $cometido) {
            $subtitle = trim(collect([
                $cometido->estado_label ?? Str::headline(str_replace('_', ' ', (string) $cometido->estado)),
                $cometido->establecimiento?->nombre_establecimiento,
                $cometido->fecha_desde?->format('d-m-Y'),
            ])->filter()->implode(' · '));
            return $this->result('request', 'Cometido funcionario', 'Cometido #' . $cometido->id . ' - ' . ($cometido->funcionario_nombre ?: 'Funcionario'), $subtitle, route('tramites.cometidos-funcionarios.show', $cometido), 'bi-briefcase');
        })->all();
    }

    private function funcionariosResults($user, ?string $activeRole, string $term): array
    {
        $results = [];
        if (Schema::hasTable('users') && $this->canSearchUsers($activeRole)) {
            $query = User::query()->latest();
            $this->whereLikeAny($query, $term, ['rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'email']);
            $results = array_merge($results, $query->limit(4)->get()->map(function (User $found) {
                return $this->result('person', 'Usuario', $found->nombre_completo ?: $found->email, trim(collect([$found->rut, $found->email])->filter()->implode(' · ')), route('dashboard'), 'bi-person-badge');
            })->all());
        }

        if (Schema::hasTable('reemplazos_personal') && in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp', 'funcionario_estab'], true)) {
            $query = ReemplazoPersonal::query()->with('establecimiento')->latest();
            if ($activeRole === 'funcionario_estab') {
                $user->loadMissing('establecimiento');
                if (!$user->establecimiento) {
                    return $results;
                }
                $query->where('establecimiento_id', $user->establecimiento->id);
            }
            $this->whereLikeAny($query, $term, ['rut', 'nombre', 'rbd', 'estatuto', 'escalafon']);
            $results = array_merge($results, $query->limit(4)->get()->map(function (ReemplazoPersonal $funcionario) {
                $url = Route::has('reemplazos.index') ? route('reemplazos.index', ['q' => $funcionario->rut ?: $funcionario->nombre]) : route('dashboard');
                return $this->result('person', 'Funcionario padrón', $funcionario->nombre ?: 'Funcionario', trim(collect([$funcionario->rut, $funcionario->establecimiento?->nombre_establecimiento])->filter()->implode(' · ')), $url, 'bi-person-lines-fill');
            })->all());
        }

        return $results;
    }

    private function establecimientosResults($user, ?string $activeRole, string $term): array
    {
        if (!Schema::hasTable('establecimientos')) {
            return [];
        }

        $query = Establecimiento::query()->orderBy('nombre_establecimiento');
        if ($activeRole === 'funcionario_estab') {
            $user->loadMissing('establecimiento');
            if (!$user->establecimiento) {
                return [];
            }
            $query->where('id', $user->establecimiento->id);
        } elseif (!in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp', 'coordinador_uatp', 'supervisor_plani', 'coordinador_plani'], true)) {
            return [];
        }

        $this->whereLikeAny($query, $term, ['nombre_establecimiento', 'rbd', 'comuna', 'clasificacion']);

        return $query->limit(4)->get()->map(function (Establecimiento $establecimiento) use ($activeRole) {
            $url = ($activeRole === 'admin' && Route::has('admin.establecimientos.index')) ? route('admin.establecimientos.index', ['q' => $establecimiento->rbd]) : route('dashboard');
            return $this->result('school', 'Establecimiento', $establecimiento->nombre_establecimiento, trim(collect(['RBD ' . $establecimiento->rbd, $establecimiento->comuna])->filter()->implode(' · ')), $url, 'bi-building');
        })->all();
    }

    private function whereSolicitudCargaMatches(Builder $query, string $term): void
    {
        $normalizedRut = Rut::normalize($term);
        $query->where(function (Builder $q) use ($term, $normalizedRut) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
            $q->where('id', 'like', $like)
                ->orWhere('tipo_solicitud', 'like', $like)
                ->orWhere('estado', 'like', $like)
                ->orWhereHas('user', function (Builder $userQuery) use ($like, $normalizedRut) {
                    $userQuery->where('nombres', 'like', $like)
                        ->orWhere('apellido_paterno', 'like', $like)
                        ->orWhere('apellido_materno', 'like', $like)
                        ->orWhere('email', 'like', $like);
                    if ($normalizedRut) {
                        $userQuery->orWhere('rut', 'like', '%' . $normalizedRut . '%');
                    }
                });
        });
    }

    private function whereLikeAny(Builder $query, string $term, array $columns): void
    {
        $normalizedRut = Rut::normalize($term);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        $rutLike = $normalizedRut ? '%' . $normalizedRut . '%' : null;
        $table = $query->getModel()->getTable();

        $query->where(function (Builder $q) use ($columns, $like, $rutLike, $table) {
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }
                $q->orWhere($column, 'like', $like);
                if ($rutLike && Str::contains($column, ['rut', 'run'])) {
                    $q->orWhere($column, 'like', $rutLike);
                }
            }
        });
    }

    private function scopeCometidos(Builder $query, $user, ?string $activeRole): void
    {
        if ($activeRole === 'funcionario_estab') {
            $user->loadMissing('establecimiento');
            if (!$user->establecimiento) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->where('establecimiento_id', $user->establecimiento->id);
            return;
        }

        if ($activeRole === 'coordinador_uatp') {
            $query->whereIn('estado', ['en_revision_uatp', 'observado_uatp', 'rechazado_uatp']);
            return;
        }

        if (in_array($activeRole, ['supervisor_plani', 'coordinador_plani'], true)) {
            $query->where(function ($q) {
                $q->whereIn('estado', ['en_revision_cdp', 'en_revision_cdp_rendicion', 'cdp_observado_rendicion', 'cdp_rechazado_rendicion'])
                    ->orWhereIn('estado_viatico', ['en_revision_cdp'])
                    ->orWhereIn('estado_reembolso', ['en_revision_cdp_rendicion', 'cdp_observado_rendicion', 'cdp_rechazado_rendicion']);
            });
            return;
        }

        if (in_array($activeRole, ['coordinador_gdp', 'funcionario_slep'], true)) {
            $query->where(function ($q) {
                $q->whereIn('estado', ['en_gdp_resolucion', 'autorizado_sin_gasto', 'resolucion_cometido_emitida', 'pendiente_rendicion', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'])
                    ->orWhereIn('estado_viatico', ['en_gdp_resolucion']);
            });
            return;
        }

        if ($activeRole === 'funcionario_daf') {
            $query->where(function ($q) {
                $q->whereIn('estado', ['en_daf_viatico', 'viatico_pagado', 'en_revision_daf_rendicion', 'rendicion_observada_daf', 'rendicion_rechazada_daf', 'rendicion_autorizada_daf', 'en_pago_reembolso', 'reembolso_pagado'])
                    ->orWhereIn('estado_viatico', ['en_daf_viatico', 'viatico_pagado'])
                    ->orWhereIn('estado_reembolso', ['en_revision_daf_rendicion', 'rendicion_observada_daf', 'rendicion_rechazada_daf', 'rendicion_autorizada_daf', 'en_pago_reembolso', 'reembolso_pagado']);
            });
            return;
        }

        if ($activeRole !== 'admin') {
            $query->whereRaw('1 = 0');
        }
    }

    private function canReviewCargas(?string $activeRole): bool
    {
        return in_array($activeRole, ['admin', 'funcionario_slep', 'coordinador_gdp'], true);
    }

    private function canSearchUsers(?string $activeRole): bool
    {
        return $activeRole === 'admin';
    }

    private function matches(string $term, array $values): bool
    {
        $needle = Str::lower(Str::ascii($term));
        foreach ($values as $value) {
            if (Str::contains(Str::lower(Str::ascii((string) $value)), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function result(string $type, string $badge, ?string $title, ?string $subtitle, string $url, string $icon): array
    {
        return [
            'type' => $type,
            'badge' => $badge,
            'title' => $title ?: $badge,
            'subtitle' => $subtitle ?: '',
            'url' => $url,
            'icon' => $icon,
        ];
    }
}
