<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionDocenteDistribucion;
use App\Services\Dotacion\DocentesDisposicionService;
use App\Support\DotacionDisposicionDatos;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DotacionDisposicionController extends Controller
{
    private const ESTADOS = ['pendiente' => 'Pendiente', 'parcial' => 'Parcialmente distribuido', 'asignado' => 'Distribuido', 'regularizar' => 'Requiere regularizar'];

    public function __construct(private DocentesDisposicionService $servicio) {}

    private function autorizar(Request $request): void
    {
        abort_unless(in_array($request->user()?->activeRoleName(), DocentesDisposicionService::ROLES, true), 403);
        abort_unless(Schema::hasTable('dotacion_docente_distribuciones'), 503, 'Ejecute la migración de Docentes a disposición para habilitar esta sección.');
        abort_unless(\App\Services\Dotacion\DotacionRevisionService::disponible(), 503, 'Ejecute la migración de revisiones anuales de dotación.');
    }

    public function index(Request $request)
    {
        $this->autorizar($request);
        $filtros = $request->validate([
            'anio' => ['sometimes', 'integer', 'between:2020,2100'],
            'bloque' => ['sometimes', Rule::in(array_keys(DotacionDisposicionDatos::BLOQUES))],
            'q' => ['nullable', 'string', 'max:150'], 'origen_id' => ['nullable', 'integer'],
            'tipo' => ['nullable', Rule::in(['titular', 'contrata'])], 'estado' => ['nullable', Rule::in(array_keys(self::ESTADOS))],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $anio = (int) ($filtros['anio'] ?? now()->year);
        $bloque = $filtros['bloque'] ?? 'plan_estudio';
        $filas = $this->servicio->listado($anio)->filter(function ($f) use ($filtros): bool {
            $q = mb_strtolower(trim($filtros['q'] ?? ''));
            $rutBuscado = preg_match('/^[0-9.k\s-]+$/i', $q) ? \App\Support\DotacionEstablecimientoCalculator::normalizeRut($q) : '';
            return (! $q || str_contains(mb_strtolower(implode(' ', [$f['nombre'], $f['rut'], $f['titulo'], $f['rbd'], $f['establecimiento']])), $q)
                || ($rutBuscado !== '' && str_contains($f['rut'], $rutBuscado)))
                && (empty($filtros['origen_id']) || $f['origen_id'] === (int) $filtros['origen_id'])
                && (empty($filtros['tipo']) || $f['tipo_horas'] === $filtros['tipo'])
                && (empty($filtros['estado']) || $f['estado'] === $filtros['estado']);
        });
        $conteos = $filas->countBy('bloque');
        $filas = $filas->where('bloque', $bloque)->values();
        $items = new LengthAwarePaginator($filas->forPage((int) $request->query('page', 1), 20)->values(), $filas->count(), 20,
            (int) $request->query('page', 1), ['path' => $request->url(), 'query' => $request->query()]);
        return view('admin.dotacion-establecimiento.disposicion.index', [
            'anio' => $anio, 'bloque' => $bloque, 'filtros' => $filtros, 'items' => $items, 'conteos' => $conteos,
            'bloques' => DotacionDisposicionDatos::BLOQUES, 'estados' => self::ESTADOS,
            'establecimientos' => DocentesDisposicionService::establecimientos($anio)->orderBy('nombre_establecimiento')->get(),
            'resumen' => ['horas' => $filas->sum('horas'), 'distribuidas' => $filas->sum('distribuidas'), 'saldo' => $filas->sum('saldo')],
        ]);
    }

    public function gestionar(Request $request)
    {
        $this->autorizar($request);
        $datos = $request->validate($this->reglasOrigen() + ['corregir' => ['nullable', 'integer', 'min:1']]);
        $fila = $this->servicio->detalle($datos);
        abort_unless($fila, 404);
        $corregir = empty($datos['corregir']) ? null : $fila['historial']->firstWhere('id', (int) $datos['corregir']);
        abort_if(! empty($datos['corregir']) && (! $corregir || $corregir->anulada_at), 404);
        $fila['historial'] = new \Illuminate\Database\Eloquent\Collection($fila['historial']->all());
        $fila['historial']->load(['destino', 'creador', 'anulador']);
        return view('admin.dotacion-establecimiento.disposicion.gestionar', [
            'fila' => $fila, 'anio' => (int) $datos['anio'], 'bloques' => DotacionDisposicionDatos::BLOQUES,
            'corregir' => $corregir, 'maximo' => $fila['finalizado'] ? max(0, round($fila['horas'] - $fila['distribuidas'] + (float) ($corregir?->horas ?? 0), 2)) : 0,
            'destinos' => $this->servicio->destinos((int) $datos['anio'], $fila['bloque'], $fila['origen_id']),
        ]);
    }

    public function store(Request $request)
    {
        return $this->guardar($request);
    }

    public function update(Request $request, int $distribucion)
    {
        return $this->guardar($request, $distribucion);
    }

    private function guardar(Request $request, ?int $id = null)
    {
        $this->autorizar($request);
        $datos = $request->validate($this->reglasOrigen() + [
            'destino_id' => ['required', 'integer'], 'horas' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'solicitud_uuid' => ['required', 'uuid'], 'observacion' => ['nullable', 'string', 'max:3000'],
            'motivo_correccion' => [$id ? 'required' : 'nullable', 'string', 'min:10', 'max:1000'],
        ]);
        $this->servicio->guardar($datos, (int) $request->user()->id, $id);
        return redirect()->route('admin.dotacion-establecimiento.disposicion.gestionar', $this->origen($datos))
            ->with('success', 'Distribución registrada. El contrato y el establecimiento de origen se conservan.');
    }

    public function anular(Request $request, int $distribucion)
    {
        $this->autorizar($request);
        $datos = $request->validate(['anio' => ['required', 'integer', 'between:2020,2100'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        $registro = DotacionDocenteDistribucion::where('anio', $datos['anio'])->findOrFail($distribucion);
        $this->servicio->anular($distribucion, (int) $datos['anio'], $datos['motivo'], (int) $request->user()->id);
        return redirect()->route('admin.dotacion-establecimiento.disposicion.gestionar', $this->origen($registro->antecedente))
            ->with('success', 'Distribución anulada. Las horas se liberaron en el saldo y se conservó el historial.');
    }

    private function reglasOrigen(): array
    {
        return ['anio' => ['required', 'integer', 'between:2020,2100'], 'origen_id' => ['required', 'integer'],
            'rut' => ['required', 'string', 'max:32'], 'bloque' => ['required', Rule::in(array_keys(DotacionDisposicionDatos::BLOQUES))],
            'tipo_horas' => ['required', Rule::in(['titular', 'contrata'])]];
    }

    private function origen(array $datos): array
    {
        return array_intersect_key($datos, $this->reglasOrigen());
    }
}
