<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\BitacoraVotacion;
use App\Models\IncidenciaVotacion;
use App\Models\JornadaVotacion;
use App\Models\User;
use App\Support\Votaciones\CoordenadasEstablecimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelVotacionController extends Controller
{
    public function index(Request $request): View
    {
        $jornadas = JornadaVotacion::query()->latest('fecha')->get(['id', 'nombre', 'slug', 'fecha', 'estado']);
        $jornada = $this->jornadaSeleccionada($request);

        if (! $jornada) {
            return view('votaciones.admin.dashboard', compact('jornadas', 'jornada'));
        }

        $jornada->load([
            'procesos:id,codigo,nombre',
            'grupos.encargado:id,nombres,apellido_paterno,apellido_materno,email',
            'grupos.rutas.establecimiento:id,rbd,nombre_establecimiento,comuna,latitud,longitud',
            'grupos.rutas.visita',
            'incidencias' => fn ($query) => $query->where('estado', IncidenciaVotacion::ABIERTA),
        ]);

        $resumen = $this->resumen($jornada);
        $grupos = $jornada->grupos->map(fn ($grupo) => $this->resumenGrupo($grupo));

        return view('votaciones.admin.dashboard', compact('jornadas', 'jornada', 'resumen', 'grupos'));
    }

    public function incidencias(Request $request): View
    {
        $query = IncidenciaVotacion::query()->with([
            'jornada:id,nombre,slug',
            'grupo:id,nombre,numero',
            'ruta.establecimiento:id,rbd,nombre_establecimiento,comuna',
            'reportadaPor:id,nombres,apellido_paterno,apellido_materno',
            'resueltaPor:id,nombres,apellido_paterno,apellido_materno',
        ]);

        $this->filtrarJornada($query, $request);
        $query->when($request->filled('estado'), fn ($q) => $q->where('estado', (string) $request->string('estado')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', (string) $request->string('tipo')))
            ->when($request->filled('grupo'), fn ($q) => $q->where('grupo_votacion_id', $request->integer('grupo')));

        $jornadas = JornadaVotacion::query()->latest('fecha')->get(['id', 'nombre', 'slug']);
        $jornada = $request->filled('jornada')
            ? JornadaVotacion::where('slug', (string) $request->string('jornada'))->first()
            : null;
        $grupos = $jornada?->grupos()->get(['id', 'nombre', 'numero']) ?? collect();
        $totales = (clone $query)
            ->selectRaw("SUM(estado = 'abierta') as abiertas, SUM(estado = 'resuelta') as resueltas, SUM(estado = 'abierta' AND tipo IN ('problema_traslado', 'establecimiento_cerrado', 'proceso_suspendido')) as criticas")
            ->first();
        $incidencias = $query->latest()->paginate(25)->withQueryString();

        return view('votaciones.admin.incidencias', compact('incidencias', 'jornadas', 'jornada', 'grupos', 'totales'));
    }

    public function bitacora(Request $request): View
    {
        $query = BitacoraVotacion::query()->with([
            'jornada:id,nombre,slug',
            'grupo:id,nombre,numero',
            'ruta.establecimiento:id,rbd,nombre_establecimiento',
            'usuario:id,nombres,apellido_paterno,apellido_materno',
        ]);

        $this->filtrarJornada($query, $request);
        $query->when($request->filled('grupo'), fn ($q) => $q->where('grupo_votacion_id', $request->integer('grupo')))
            ->when($request->filled('evento'), fn ($q) => $q->where('evento', (string) $request->string('evento')))
            ->when($request->filled('usuario'), fn ($q) => $q->where('user_id', $request->integer('usuario')))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('created_at', (string) $request->string('fecha')))
            ->when($request->filled('establecimiento'), function ($q) use ($request) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $request->string('establecimiento')).'%';
                $q->whereHas('ruta.establecimiento', fn ($establecimiento) => $establecimiento
                    ->where('nombre_establecimiento', 'like', $term)
                    ->orWhere('rbd', 'like', $term));
            });

        $eventos = $query->latest('created_at')->paginate(40)->withQueryString();
        $jornadas = JornadaVotacion::query()->latest('fecha')->get(['id', 'nombre', 'slug']);
        $jornada = $request->filled('jornada')
            ? JornadaVotacion::where('slug', (string) $request->string('jornada'))->first()
            : null;
        $grupos = $jornada?->grupos()->get(['id', 'nombre', 'numero']) ?? collect();
        $tiposEvento = BitacoraVotacion::query()->distinct()->orderBy('evento')->pluck('evento');
        $usuarios = User::query()
            ->whereIn('id', BitacoraVotacion::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('nombres')
            ->orderBy('apellido_paterno')
            ->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno']);

        return view('votaciones.admin.bitacora', compact('eventos', 'jornadas', 'jornada', 'grupos', 'tiposEvento', 'usuarios'));
    }

    private function jornadaSeleccionada(Request $request): ?JornadaVotacion
    {
        if ($request->filled('jornada')) {
            return JornadaVotacion::where('slug', (string) $request->string('jornada'))->firstOrFail();
        }

        return JornadaVotacion::query()
            ->orderByRaw("CASE estado WHEN 'en_curso' THEN 1 WHEN 'publicada' THEN 2 WHEN 'borrador' THEN 3 WHEN 'suspendida' THEN 4 ELSE 5 END")
            ->latest('fecha')
            ->first();
    }

    private function resumen(JornadaVotacion $jornada): array
    {
        $rutas = $jornada->grupos->flatMap->rutas;
        $incidenciasAbiertas = $jornada->incidencias;
        $rutasConIncidencia = $incidenciasAbiertas->pluck('ruta_votacion_id')->filter()->unique();
        $rutasConProblemas = $rutas->filter(fn ($ruta) => $rutasConIncidencia->contains($ruta->id)
            || ! CoordenadasEstablecimiento::sonValidas($ruta->establecimiento->latitud, $ruta->establecimiento->longitud)
        )->count();

        return [
            'total_grupos' => $jornada->grupos->count(),
            'grupos_votando' => $jornada->grupos->where('estado', 'en_votacion')->count(),
            'grupos_traslado' => $jornada->grupos->where('estado', 'en_traslado')->count(),
            'grupos_finalizados' => $jornada->grupos->where('estado', 'finalizado')->count(),
            'establecimientos_atendidos' => $rutas->filter(fn ($ruta) => $ruta->visita?->estado === 'finalizada')->count(),
            'establecimientos_pendientes' => $rutas->reject(fn ($ruta) => $ruta->visita?->estado === 'finalizada')->count(),
            'incidencias_abiertas' => $incidenciasAbiertas->count(),
            'rutas_con_problemas' => $rutasConProblemas,
        ];
    }

    private function resumenGrupo($grupo): array
    {
        $rutas = $grupo->rutas->values();
        $actual = $rutas->first(fn ($ruta) => in_array($ruta->visita?->estado, ['en_traslado', 'en_votacion'], true));
        $finalizadas = $rutas->filter(fn ($ruta) => $ruta->visita?->estado === 'finalizada')->count();
        $proxima = $actual
            ? $rutas->first(fn ($ruta) => $ruta->orden > $actual->orden && $ruta->visita?->estado !== 'finalizada')
            : $rutas->first(fn ($ruta) => $ruta->visita?->estado !== 'finalizada');

        return [
            'modelo' => $grupo,
            'actual' => $actual,
            'proxima' => $proxima,
            'finalizadas' => $finalizadas,
            'total' => $rutas->count(),
            'porcentaje' => $rutas->isEmpty() ? 0 : (int) round(($finalizadas / $rutas->count()) * 100),
        ];
    }

    private function filtrarJornada(Builder $query, Request $request): void
    {
        $query->when($request->filled('jornada'), function ($q) use ($request) {
            $q->whereHas('jornada', fn ($jornada) => $jornada->where('slug', (string) $request->string('jornada')));
        });
    }
}
