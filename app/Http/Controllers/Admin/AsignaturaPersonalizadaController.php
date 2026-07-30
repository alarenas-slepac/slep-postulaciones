<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsignaturaPersonalizadaController extends Controller
{
    public function index(Request $request)
    {
        $anio = trim((string) $request->query('anio', (string) now()->year));
        $establecimientoId = trim((string) $request->query('establecimiento_id', ''));
        $cursoId = trim((string) $request->query('curso_id', ''));
        $q = trim((string) $request->query('q', ''));

        $items = DB::table('establecimiento_planes_estudio_asignaturas as detalle')
            ->join('establecimiento_planes_estudio as config', 'config.id', '=', 'detalle.establecimiento_plan_estudio_id')
            ->join('establecimientos as establecimiento', 'establecimiento.id', '=', 'config.establecimiento_id')
            ->leftJoin('establecimiento_cursos as ec', 'ec.id', '=', 'config.establecimiento_curso_id')
            ->leftJoin('cursos as curso', 'curso.id', '=', 'config.curso_id')
            ->leftJoin('planes_estudio as plan', 'plan.id', '=', 'config.plan_estudio_id')
            ->leftJoin('planes_estudio_bloques as bloque', 'bloque.id', '=', 'detalle.plan_estudio_bloque_id')
            ->where(function ($query) {
                $query->where('detalle.origen', 'personalizada')
                    ->orWhereNotNull('detalle.nombre_asignatura_personalizada');
            })
            ->whereNotNull('detalle.nombre_asignatura_personalizada')
            ->where('detalle.nombre_asignatura_personalizada', '<>', '')
            ->when($anio !== '', fn ($query) => $query->where('config.anio', (int) $anio))
            ->when($establecimientoId !== '', fn ($query) => $query->where('config.establecimiento_id', (int) $establecimientoId))
            ->when($cursoId !== '', fn ($query) => $query->where('config.curso_id', (int) $cursoId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('detalle.nombre_asignatura_personalizada', 'like', "%{$q}%")
                        ->orWhere('detalle.observacion', 'like', "%{$q}%")
                        ->orWhere('establecimiento.rbd', 'like', "%{$q}%")
                        ->orWhere('establecimiento.nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('establecimiento.comuna', 'like', "%{$q}%")
                        ->orWhere('ec.nombre_seccion', 'like', "%{$q}%")
                        ->orWhere('curso.nombre', 'like', "%{$q}%")
                        ->orWhere('plan.nombre_plan', 'like', "%{$q}%")
                        ->orWhere('bloque.nombre', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('config.anio')
            ->orderBy('establecimiento.comuna')
            ->orderBy('establecimiento.nombre_establecimiento')
            ->orderBy('curso.orden')
            ->orderBy('ec.letra')
            ->orderBy('detalle.nombre_asignatura_personalizada')
            ->select([
                'detalle.id',
                'detalle.nombre_asignatura_personalizada as nombre',
                'detalle.horas_semanales',
                'detalle.horas_anuales',
                'detalle.observacion',
                'detalle.origen',
                'config.id as configuracion_id',
                'config.anio',
                'config.estado as configuracion_estado',
                'establecimiento.rbd',
                'establecimiento.nombre_establecimiento as establecimiento_nombre',
                'establecimiento.comuna as establecimiento_comuna',
                'ec.nombre_seccion',
                'ec.regimen_jec',
                'ec.matricula',
                'curso.nombre as curso_nombre',
                'plan.nombre_plan as plan_nombre',
                'bloque.nombre as bloque_nombre',
                'bloque.tipo_bloque',
            ])
            ->paginate(25)
            ->withQueryString();

        $resumen = DB::table('establecimiento_planes_estudio_asignaturas as detalle')
            ->join('establecimiento_planes_estudio as config', 'config.id', '=', 'detalle.establecimiento_plan_estudio_id')
            ->where(function ($query) {
                $query->where('detalle.origen', 'personalizada')
                    ->orWhereNotNull('detalle.nombre_asignatura_personalizada');
            })
            ->whereNotNull('detalle.nombre_asignatura_personalizada')
            ->where('detalle.nombre_asignatura_personalizada', '<>', '')
            ->when($anio !== '', fn ($query) => $query->where('config.anio', (int) $anio))
            ->selectRaw('COUNT(*) as total_registros')
            ->selectRaw('COUNT(DISTINCT config.establecimiento_id) as total_establecimientos')
            ->selectRaw('COALESCE(SUM(detalle.horas_semanales), 0) as total_horas_semanales')
            ->first();

        return view('admin.asignaturas-personalizadas.index', [
            'items' => $items,
            'establecimientos' => $this->establecimientosAgrupados(),
            'cursos' => Curso::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
            'anio' => $anio,
            'establecimientoId' => $establecimientoId,
            'cursoId' => $cursoId,
            'q' => $q,
            'resumen' => $resumen,
        ]);
    }

    private function establecimientosAgrupados()
    {
        return Establecimiento::query()
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])
            ->groupBy(fn ($establecimiento) => $establecimiento->comuna ?: 'Sin comuna');
    }
}
