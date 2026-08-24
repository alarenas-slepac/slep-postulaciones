<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionEstablecimientoAvanceExport;
use App\Http\Controllers\Controller;
use App\Models\DotacionDocenteExclusion;
use App\Models\DotacionProporcionExcepcion;
use App\Models\Establecimiento;
use App\Support\DotacionAsignaturaResumenCalculator;
use App\Support\DotacionEstablecimientoAvanceCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DotacionEstablecimientoController extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'];

    public function index(Request $request)
    {
        $activeRole = $this->authorizeDotacionAccess($request);
        $user = $request->user();

        if ($request->boolean('informe_avance')) {
            return $this->informeAvance($request, $activeRole);
        }

        $anio = (int) $request->query('anio', now()->year);
        $q = trim((string) $request->query('q', ''));
        $comuna = trim((string) $request->query('comuna', ''));

        $establecimientos = Establecimiento::query()
            ->where(function ($query) {
                $query->whereNull('sala_cuna')
                    ->orWhere('sala_cuna', false);
            })
            ->when($this->isEstablecimientoRole($activeRole), fn ($query) => $query->where('id', (int) ($user->establecimiento_id ?? 0)))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('rbd', 'like', "%{$q}%")
                        ->orWhere('comuna', 'like', "%{$q}%");
                });
            })
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->paginate(20)
            ->withQueryString();

        $establecimientos->getCollection()->transform(function ($establecimiento) use ($anio) {
            $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio, false);
            $establecimiento->dotacion_establecimiento_resumen = $data['resumen'];
            $establecimiento->dotacion_establecimiento_bloques = $data['bloques'];
            return $establecimiento;
        });

        $comunas = Establecimiento::query()
            ->where(function ($query) {
                $query->whereNull('sala_cuna')
                    ->orWhere('sala_cuna', false);
            })
            ->when($this->isEstablecimientoRole($activeRole), fn ($query) => $query->where('id', (int) ($user->establecimiento_id ?? 0)))
            ->whereNotNull('comuna')
            ->orderBy('comuna')
            ->distinct()
            ->pluck('comuna')
            ->filter()
            ->values();

        return view('admin.dotacion-establecimiento.index', [
            'establecimientos' => $establecimientos,
            'comunas' => $comunas,
            'anio' => $anio,
            'q' => $q,
            'comuna' => $comuna,
            'activeRole' => $activeRole,
        ]);
    }

    private function informeAvance(Request $request, string $activeRole)
    {
        abort_unless(in_array($activeRole, ['admin', 'coordinador_uatp'], true), 403);

        $anio = max(2020, min(2100, (int) $request->query('anio', now()->year)));
        $establecimientoId = (int) $request->query('establecimiento_id', 0);
        $comuna = trim((string) $request->query('comuna', ''));

        $baseQuery = Establecimiento::query()
            ->where(function ($query) {
                $query->whereNull('sala_cuna')
                    ->orWhere('sala_cuna', false);
            });

        $filteredQuery = (clone $baseQuery)
            ->when($establecimientoId > 0, fn ($query) => $query->whereKey($establecimientoId))
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento');

        if ($request->boolean('export_excel')) {
            $establecimientosExport = (clone $filteredQuery)->get();

            return (new DotacionEstablecimientoAvanceExport())->download(
                $establecimientosExport,
                $anio,
                [
                    'comuna' => $comuna,
                    'establecimiento_id' => $establecimientoId,
                ],
                $request->user()
            );
        }

        if ($request->boolean('export_pdf')) {
            $this->preparePdfExportRuntime();

            // Procesar en lotes pequeños evita mantener simultáneamente en memoria
            // todos los modelos y sus relaciones mientras se calcula el informe.
            $avances = collect();
            (clone $filteredQuery)
                ->select(['id', 'rbd', 'nombre_establecimiento', 'comuna', 'sala_cuna'])
                ->chunk(8, function ($establecimientos) use (&$avances, $anio): void {
                    foreach ($establecimientos as $establecimiento) {
                        try {
                            $avances->push(DotacionEstablecimientoAvanceCalculator::build($establecimiento, $anio));
                        } catch (\Throwable $exception) {
                            report($exception);
                            $avances->push(DotacionEstablecimientoAvanceCalculator::error($establecimiento, $anio));
                        } finally {
                            $establecimiento->unsetRelations();
                        }
                    }

                    unset($establecimientos);
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                });

            $resumenGlobal = DotacionEstablecimientoAvanceCalculator::resumenGlobal($avances);
            unset($avances);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            $establecimientoSeleccionado = $establecimientoId > 0
                ? (clone $baseQuery)->find($establecimientoId)
                : null;
            $generatedAt = now();
            $generatedBy = $request->user();

            $filenameParts = ['dotacion_avance_global', (string) $anio];
            if ($comuna !== '') {
                $filenameParts[] = Str::slug($comuna, '_');
            }
            if ($establecimientoSeleccionado) {
                $filenameParts[] = Str::slug((string) $establecimientoSeleccionado->nombre_establecimiento, '_');
            }

            return Pdf::loadView('admin.dotacion-establecimiento.avance-pdf', [
                'anio' => $anio,
                'comuna' => $comuna,
                'establecimientoSeleccionado' => $establecimientoSeleccionado,
                'resumenGlobal' => $resumenGlobal,
                'generatedAt' => $generatedAt,
                'generatedBy' => $generatedBy,
            ])->setOptions($this->pdfOptions())
                ->setPaper('letter', 'landscape')
                ->download(implode('_', $filenameParts).'.pdf');
        }

        $opcionesEstablecimientos = (clone $baseQuery)
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna']);

        $comunas = (clone $baseQuery)
            ->whereNotNull('comuna')
            ->orderBy('comuna')
            ->distinct()
            ->pluck('comuna')
            ->filter()
            ->values();

        $establecimientos = (clone $filteredQuery)
            ->paginate(20)
            ->withQueryString();

        $establecimientos->getCollection()->transform(function (Establecimiento $establecimiento) use ($anio) {
            try {
                $establecimiento->dotacion_avance = DotacionEstablecimientoAvanceCalculator::build($establecimiento, $anio);
            } catch (\Throwable $exception) {
                report($exception);
                $establecimiento->dotacion_avance = DotacionEstablecimientoAvanceCalculator::error($establecimiento, $anio);
            }

            return $establecimiento;
        });

        $resumenPagina = DotacionEstablecimientoAvanceCalculator::resumen(
            $establecimientos->getCollection()->map(fn ($establecimiento) => $establecimiento->dotacion_avance)
        );

        return view('admin.dotacion-establecimiento.avance', [
            'establecimientos' => $establecimientos,
            'opcionesEstablecimientos' => $opcionesEstablecimientos,
            'comunas' => $comunas,
            'anio' => $anio,
            'establecimientoId' => $establecimientoId,
            'comuna' => $comuna,
            'resumenPagina' => $resumenPagina,
            'activeRole' => $activeRole,
        ]);
    }

    public function show(Request $request, Establecimiento $establecimiento)
    {
        $activeRole = $this->authorizeDotacionAccess($request);
        $this->authorizeEstablecimientoScope($request, $establecimiento);
        $anio = (int) $request->query('anio', now()->year);
        $tab = in_array($request->query('tab'), ['docentes', 'asignacion', 'asignaturas', 'cursos-combinados'], true) ? $request->query('tab') : 'resumen';
        $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio);
        $proporcionExcepcionTableReady = Schema::hasTable('dotacion_proporcion_excepciones');
        $proporcionExcepcion = $proporcionExcepcionTableReady
            ? DotacionProporcionExcepcion::query()
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)
                ->first()
            : null;
        $canManageProporcionExcepcion = in_array($activeRole, ['admin', 'coordinador_uatp'], true);
        $docenteExclusionesTableReady = Schema::hasTable('dotacion_docente_exclusiones');

        $asignaturasFiltros = [
            'q' => trim((string) $request->query('asignatura_q', '')),
            'proporcion' => trim((string) $request->query('asignatura_proporcion', '')),
            'estado' => trim((string) $request->query('asignatura_estado', '')),
            'titulares' => trim((string) $request->query('asignatura_titulares', '')),
        ];
        $asignaturasItems = DotacionAsignaturaResumenCalculator::filtrar(
            data_get($data, 'asignaturas.items', []),
            $asignaturasFiltros
        );
        $asignaturas = [
            'items' => $asignaturasItems,
            'resumen' => DotacionAsignaturaResumenCalculator::resumen($asignaturasItems),
            'resumen_total' => data_get($data, 'asignaturas.resumen', []),
            'opciones' => data_get($data, 'asignaturas.opciones', []),
        ];

        return view('admin.dotacion-establecimiento.show', [
            'establecimiento' => $establecimiento,
            'anio' => $anio,
            'activeRole' => $activeRole,
            'tab' => $tab,
            'resumen' => $data['resumen'],
            'cursos' => $data['cursos'],
            'bloques' => $data['bloques'],
            'bloquesContratoDotacion' => $data['bloques_contrato_dotacion'] ?? $data['bloques'],
            'docentes' => $data['docentes'],
            'asignacion' => $data['asignacion'] ?? [],
            'asignaturas' => $asignaturas,
            'asignaturasFiltros' => $asignaturasFiltros,
            'cursosCombinados' => $data['cursos_combinados'] ?? [],
            'canManageCursosCombinados' => in_array($activeRole, ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'], true),
            'proporcionExcepcion' => $proporcionExcepcion,
            'proporcionExcepcionTableReady' => $proporcionExcepcionTableReady,
            'canManageProporcionExcepcion' => $canManageProporcionExcepcion,
            'docenteExclusionesTableReady' => $docenteExclusionesTableReady,
            'canManageDocenteExclusiones' => in_array($activeRole, $this->allowedRoles, true),
            'motivosExclusionDocente' => DotacionDocenteExclusion::MOTIVOS,
            'alertas' => $data['alertas'],
        ]);
    }

    public function pdf(Request $request, Establecimiento $establecimiento)
    {
        $activeRole = $this->authorizeDotacionAccess($request);
        $this->authorizeEstablecimientoScope($request, $establecimiento);
        $this->preparePdfExportRuntime();

        $anio = (int) $request->query('anio', now()->year);
        $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio);
        $generatedAt = now();
        $generatedBy = $request->user();

        $filename = 'dotacion_establecimiento_'
            . ($establecimiento->rbd ?: $establecimiento->id)
            . '_'
            . Str::slug((string) $establecimiento->nombre_establecimiento, '_')
            . '_'
            . $anio
            . '.pdf';

        $pdfData = [
            'establecimiento' => $establecimiento,
            'anio' => $anio,
            'activeRole' => $activeRole,
            'resumen' => $data['resumen'],
            'cursos' => $data['cursos'],
            'bloques' => $data['bloques'],
            'bloquesContratoDotacion' => $data['bloques_contrato_dotacion'] ?? $data['bloques'],
            'docentes' => $data['docentes'],
            // El PDF utiliza solamente las necesidades del plan; no se conserva
            // el árbol completo de asignación, que puede ser considerablemente mayor.
            'necesidadesPlan' => data_get($data, 'asignacion.necesidades.plan_estudio', []),
            'proporcionExcepcion' => $data['proporcion_excepcion'] ?? null,
            'cursosCombinados' => $data['cursos_combinados'] ?? [],
            'asignaturasResumen' => [
                'resumen' => data_get($data, 'asignaturas.resumen', []),
                'items' => collect(data_get($data, 'asignaturas.items', []))->map(function ($item) {
                    unset($item['detalle']);
                    return $item;
                })->values(),
            ],
            'alertas' => $data['alertas'],
            'generatedAt' => $generatedAt,
            'generatedBy' => $generatedBy,
        ];

        unset($data);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return Pdf::loadView('admin.dotacion-establecimiento.pdf', $pdfData)
            ->setOptions($this->pdfOptions())
            ->setPaper('letter', 'landscape')
            ->download($filename);
    }

    /**
     * El informe global procesa todos los establecimientos y luego renderiza
     * el consolidado con DomPDF. En producción el límite PHP por defecto es
     * de 30 segundos, insuficiente para este proceso territorial.
     */
    private function preparePdfExportRuntime(): void
    {
        $seconds = 240;

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        @ini_set('max_execution_time', (string) $seconds);
        // Algunos hostings mantienen un límite administrativo de 128 MB y no
        // permiten elevarlo. El informe ya no depende de que este cambio sea aceptado.
        @ini_set('memory_limit', '512M');

        try {
            DB::connection()->disableQueryLog();
        } catch (\Throwable $exception) {
            // No interrumpir el informe si la conexión no permite modificar el query log.
        }

        if (function_exists('gc_enable')) {
            gc_enable();
        }
    }

    /**
     * Opciones austeras para DomPDF. Helvetica utiliza las fuentes base de PDF
     * y evita cargar en memoria la familia DejaVu completa durante el reflow.
     */
    private function pdfOptions(): array
    {
        return [
            'defaultFont' => 'Helvetica',
            'dpi' => 72,
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => false,
        ];
    }

    private function authorizeDotacionAccess(Request $request): string
    {
        $activeRole = $this->activeRole($request);
        abort_unless(in_array($activeRole, $this->allowedRoles, true), 403);
        if ($this->isEstablecimientoRole($activeRole)) {
            abort_unless((int) ($request->user()->establecimiento_id ?? 0) > 0, 403, 'Usuario sin establecimiento asociado.');
        }
        return $activeRole;
    }

    private function activeRole(Request $request): ?string
    {
        $user = $request->user();
        return $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
    }

    private function isEstablecimientoRole(?string $activeRole): bool
    {
        return $activeRole === 'funcionario_directivo_estab';
    }

    private function authorizeEstablecimientoScope(Request $request, Establecimiento $establecimiento): void
    {
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404, 'El establecimiento no participa en el proceso de dotación establecimiento.');

        if ($this->isEstablecimientoRole($this->activeRole($request))) {
            abort_unless((int) $establecimiento->id === (int) ($request->user()->establecimiento_id ?? 0), 403);
        }
    }
}
