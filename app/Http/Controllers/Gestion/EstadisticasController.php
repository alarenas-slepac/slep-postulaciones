<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EstadisticasController extends Controller
{
    public function index(Request $request)
    {
        $establecimientos = Establecimiento::query()
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento']);

        $establecimientosById = $establecimientos->keyBy('id');

        $establecimientoId = $request->filled('establecimiento_id')
            ? (int) $request->input('establecimiento_id')
            : null;

        $query = SolicitudReemplazo::query()
            ->select([
                'id',
                'establecimiento_id',
                'estado',
                'fecha_inicio',
                'fecha_termino',
                'fecha_inicio_trabajo',
                'reemplazo_personal_id',
                'contacto_nombre',
                'contacto_email',
            ]);

        if ($establecimientoId) {
            $query->where('establecimiento_id', $establecimientoId);
        }

        $solicitudes = $query->get();

        $totalSolicitudes = $solicitudes->count();
        $diasSolicitados = $solicitudes->sum(fn (SolicitudReemplazo $s) => $this->inclusiveDays($s->fecha_inicio, $s->fecha_termino));
        $diasAutorizados = $solicitudes->sum(fn (SolicitudReemplazo $s) => $this->inclusiveDays($s->fecha_inicio_trabajo, $s->fecha_termino));

        $estadoDefinitions = $this->estadoDefinitions();
        $estadoOrder = array_keys($estadoDefinitions);

        $porEstado = $solicitudes
            ->groupBy(fn (SolicitudReemplazo $s) => (string) ($s->estado ?: 'sin_estado'))
            ->map(function ($items, string $estado) use ($totalSolicitudes, $estadoDefinitions, $estadoOrder) {
                $total = $items->count();
                $position = array_search($estado, $estadoOrder, true);

                return [
                    'estado' => $estado,
                    'label' => $estadoDefinitions[$estado] ?? $this->estadoLabel($estado),
                    'total' => $total,
                    'porcentaje' => $totalSolicitudes > 0 ? round(($total / $totalSolicitudes) * 100, 1) : 0,
                    'order' => $position === false ? PHP_INT_MAX : $position,
                ];
            })
            ->sortBy([
                ['order', 'asc'],
                ['total', 'desc'],
                ['label', 'asc'],
            ])
            ->values()
            ->map(function (array $row) {
                unset($row['order']);

                return $row;
            });

        $estadoChart = [
            'labels' => $porEstado->pluck('label')->values()->all(),
            'series' => $porEstado->pluck('total')->values()->all(),
        ];

        $diasChart = [
            'labels' => ['Solicitados', 'Autorizados'],
            'series' => [$diasSolicitados, $diasAutorizados],
        ];

        if ($establecimientoId) {
            $rankingLimit = 5;
            $rankingMode = 'funcionarios';
            $rankingTitle = 'Top 5 funcionarios con más reemplazos solicitados';
            $rankingSubtitle = 'Ranking del establecimiento filtrado agrupado por reemplazo_personal_id.';
            $rankingRows = $this->buildTopFuncionarios($solicitudes, $rankingLimit);
        } else {
            $rankingLimit = 10;
            $rankingMode = 'establecimientos';
            $rankingTitle = 'Top 10 establecimientos que más solicitan reemplazos';
            $rankingSubtitle = 'Ranking general agrupado por establecimiento_id.';
            $rankingRows = $this->buildTopEstablecimientos($solicitudes, $establecimientosById, $rankingLimit);
        }

        $rankingChart = [
            'labels' => $rankingRows->pluck('chart_label')->values()->all(),
            'series' => $rankingRows->pluck('total')->values()->all(),
        ];

        $establecimientoActual = $establecimientoId
            ? $establecimientosById->get($establecimientoId)
            : null;

        return view('gestion.estadisticas.index', [
            'establecimientos' => $establecimientos,
            'establecimientoId' => $establecimientoId,
            'establecimientoActual' => $establecimientoActual,
            'totalSolicitudes' => $totalSolicitudes,
            'diasSolicitados' => $diasSolicitados,
            'diasAutorizados' => $diasAutorizados,
            'porEstado' => $porEstado,
            'estadoChart' => $estadoChart,
            'diasChart' => $diasChart,
            'rankingMode' => $rankingMode,
            'rankingLimit' => $rankingLimit,
            'rankingTitle' => $rankingTitle,
            'rankingSubtitle' => $rankingSubtitle,
            'rankingRows' => $rankingRows,
            'rankingChart' => $rankingChart,
            'title' => 'Estadísticas',
        ]);
    }

    private function buildTopEstablecimientos(Collection $solicitudes, Collection $establecimientosById, int $limit): Collection
    {
        return $solicitudes
            ->filter(fn (SolicitudReemplazo $s) => !empty($s->establecimiento_id))
            ->groupBy(fn (SolicitudReemplazo $s) => (int) $s->establecimiento_id)
            ->map(function (Collection $items, int $establecimientoId) use ($establecimientosById) {
                $establecimiento = $establecimientosById->get($establecimientoId);
                $nombre = $this->resolveEstablecimientoNombre($establecimiento);
                $rbd = $establecimiento?->rbd;

                return [
                    'nombre' => $nombre,
                    'detalle' => $rbd ? 'RBD ' . $rbd : 'ID ' . $establecimientoId,
                    'total' => $items->count(),
                    'chart_label' => $nombre,
                ];
            })
            ->sortByDesc('total')
            ->take($limit)
            ->values();
    }

    private function buildTopFuncionarios(Collection $solicitudes, int $limit): Collection
    {
        $funcionarioIds = $solicitudes
            ->pluck('reemplazo_personal_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $funcionariosById = ReemplazoPersonal::query()
            ->whereIn('id', $funcionarioIds)
            ->get(['id', 'nombre', 'rut'])
            ->keyBy('id');

        return $solicitudes
            ->filter(fn (SolicitudReemplazo $s) => !empty($s->reemplazo_personal_id))
            ->groupBy(fn (SolicitudReemplazo $s) => (int) $s->reemplazo_personal_id)
            ->map(function (Collection $items, int $funcionarioId) use ($funcionariosById) {
                $funcionario = $funcionariosById->get($funcionarioId);
                $nombre = trim((string) ($funcionario?->nombre ?? '')) ?: 'Funcionario sin nombre';
                $rut = trim((string) ($funcionario?->rut ?? ''));

                return [
                    'nombre' => $nombre,
                    'detalle' => $rut !== '' ? $rut : 'ID ' . $funcionarioId,
                    'total' => $items->count(),
                    'chart_label' => $nombre,
                ];
            })
            ->sortByDesc('total')
            ->take($limit)
            ->values();
    }

    private function resolveEstablecimientoNombre($establecimiento): string
    {
        $nombre = trim((string) ($establecimiento->establecimiento ?? ''));

        if ($nombre !== '') {
            return $nombre;
        }

        $nombre = trim((string) ($establecimiento->nombre_establecimiento ?? ''));

        if ($nombre !== '') {
            return $nombre;
        }

        $nombre = trim((string) ($establecimiento->nombre ?? ''));

        return $nombre !== '' ? $nombre : 'Establecimiento sin nombre';
    }

    private function inclusiveDays($inicio, $termino): int
    {
        if (!$inicio || !$termino) {
            return 0;
        }

        if ($inicio->gt($termino)) {
            return 0;
        }

        return $inicio->diffInDays($termino) + 1;
    }

    private function estadoDefinitions(): array
    {
        return [
            'pendiente_uatp' => 'Pendiente UATP',
            'pendiente_validacion' => 'Pendiente de Validación',
            'pendiente_gdp' => 'Pendiente GDP',
            'derivada_slep' => 'Derivada a SLEP',
            'aceptada' => 'Aceptada',
            'cerrado' => 'Cerrado',
            'rechazada_uatp' => 'Rechazada UATP',
            'rechazada_plani' => 'Rechazada Planificación',
            'rechazada_gdp' => 'Rechazada GDP',
            'rechazada' => 'Rechazada',
            'anulada' => 'Anulada',
            'sin_estado' => 'Sin estado',
        ];
    }

    private function estadoLabel(string $estado): string
    {
        return $this->estadoDefinitions()[$estado]
            ?? str($estado)->replace('_', ' ')->title()->toString();
    }
}
