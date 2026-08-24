<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionProporcionExcepcion;
use App\Models\Establecimiento;
use App\Services\DotacionProporcionRecalculationService;
use App\Support\DocenteHorasNoLectivasCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DotacionProporcionExcepcionController extends Controller
{
    private array $allowedRoles = ['admin', 'coordinador_uatp', 'supervisor_plani'];

    public function store(
        Request $request,
        Establecimiento $establecimiento,
        DotacionProporcionRecalculationService $recalculationService
    ): RedirectResponse {
        $this->authorizeAction($request, $establecimiento);
        abort_unless(Schema::hasTable('dotacion_proporcion_excepciones'), 500, 'Debe ejecutar las migraciones antes de habilitar la excepción 60/40.');

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'justificacion' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $result = DB::transaction(function () use ($request, $establecimiento, $recalculationService, $data): array {
            $exception = DotacionProporcionExcepcion::query()->firstOrNew([
                'establecimiento_id' => $establecimiento->id,
                'anio' => (int) $data['anio'],
            ]);

            if (! $exception->exists) {
                $exception->created_by = $request->user()?->id;
            }

            $exception->fill([
                'proporcion' => DocenteHorasNoLectivasCalculator::PROPORCION_PRIORITARIOS,
                'alcance' => 'todos_los_niveles',
                'justificacion' => trim((string) $data['justificacion']),
                'activa' => true,
                'updated_by' => $request->user()?->id,
            ])->save();

            DocenteHorasNoLectivasCalculator::clearExceptionCache((int) $establecimiento->id, (int) $data['anio']);
            $result = $recalculationService->recalculate($establecimiento, (int) $data['anio'], $request->user()?->id);
            $this->saveRecalculationResult($exception, $result, $request->user()?->id);

            return $result;
        });

        return redirect()->route('admin.dotacion-establecimiento.show', [
            $establecimiento,
            'anio' => (int) $data['anio'],
            'tab' => 'resumen',
        ])->with('success', sprintf(
            'Excepción 60/40 habilitada para todos los niveles. Se revisaron %d asignaciones y se actualizaron %d.',
            $result['total'],
            $result['actualizadas']
        ));
    }

    public function destroy(
        Request $request,
        Establecimiento $establecimiento,
        DotacionProporcionRecalculationService $recalculationService
    ): RedirectResponse {
        $this->authorizeAction($request, $establecimiento);
        abort_unless(Schema::hasTable('dotacion_proporcion_excepciones'), 500, 'Debe ejecutar las migraciones antes de administrar la excepción 60/40.');
        $anio = max(2020, min(2100, (int) $request->input('anio', now()->year)));

        $exception = DotacionProporcionExcepcion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->firstOrFail();

        $result = DB::transaction(function () use ($request, $establecimiento, $recalculationService, $exception, $anio): array {
            $exception->update([
                'activa' => false,
                'updated_by' => $request->user()?->id,
            ]);

            DocenteHorasNoLectivasCalculator::clearExceptionCache((int) $establecimiento->id, $anio);
            $result = $recalculationService->recalculate($establecimiento, $anio, $request->user()?->id);
            $this->saveRecalculationResult($exception, $result, $request->user()?->id);

            return $result;
        });

        return redirect()->route('admin.dotacion-establecimiento.show', [
            $establecimiento,
            'anio' => $anio,
            'tab' => 'resumen',
        ])->with('success', sprintf(
            'Excepción 60/40 desactivada. Se restableció la regla ordinaria y se actualizaron %d asignaciones.',
            $result['actualizadas']
        ));
    }

    public function recalculate(
        Request $request,
        Establecimiento $establecimiento,
        DotacionProporcionRecalculationService $recalculationService
    ): RedirectResponse {
        $this->authorizeAction($request, $establecimiento);
        abort_unless(Schema::hasTable('dotacion_proporcion_excepciones'), 500, 'Debe ejecutar las migraciones antes de administrar la excepción 60/40.');
        $anio = max(2020, min(2100, (int) $request->input('anio', now()->year)));

        $exception = DotacionProporcionExcepcion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('activa', true)
            ->firstOrFail();

        $result = DB::transaction(function () use ($request, $establecimiento, $recalculationService, $exception, $anio): array {
            DocenteHorasNoLectivasCalculator::clearExceptionCache((int) $establecimiento->id, $anio);
            $result = $recalculationService->recalculate($establecimiento, $anio, $request->user()?->id);
            $this->saveRecalculationResult($exception, $result, $request->user()?->id);

            return $result;
        });

        return redirect()->route('admin.dotacion-establecimiento.show', [
            $establecimiento,
            'anio' => $anio,
            'tab' => 'resumen',
        ])->with('success', sprintf(
            'Recalculación 60/40 completada: %d asignaciones revisadas, %d actualizadas y %d omitidas.',
            $result['total'],
            $result['actualizadas'],
            $result['omitidas']
        ));
    }

    private function saveRecalculationResult(
        DotacionProporcionExcepcion $exception,
        array $result,
        ?int $userId
    ): void {
        $exception->forceFill([
            'ultima_recalculacion_total' => (int) ($result['total'] ?? 0),
            'ultima_recalculacion_at' => now(),
            'updated_by' => $userId,
        ])->save();
    }

    private function authorizeAction(Request $request, Establecimiento $establecimiento): void
    {
        $user = $request->user();
        $activeRole = $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        abort_unless(in_array($activeRole, $this->allowedRoles, true), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404, 'El establecimiento no participa en el proceso de dotación establecimiento.');
    }
}
