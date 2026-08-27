<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use Illuminate\Support\Facades\Cache;

class EstadoPublicoVotacionService
{
    public function obtener(JornadaVotacion $jornada): array
    {
        return Cache::remember("votaciones.publica.{$jornada->id}", 5, function () use ($jornada) {
            $jornada->load(['procesos:id,codigo,nombre', 'grupos.rutas' => fn ($q) => $q->where('activa', true)->orderBy('orden'), 'grupos.rutas.establecimiento.admisionPerfil', 'grupos.rutas.visita', 'incidencias' => fn ($q) => $q->where('publica', true)->whereNotNull('mensaje_publico')->where('estado', 'abierta')]);

            return [
                'jornada' => ['nombre' => $jornada->nombre, 'slug' => $jornada->slug, 'fecha' => $jornada->fecha->toDateString(), 'estado' => $jornada->estado, 'descripcion' => $jornada->descripcion, 'procesos' => $jornada->procesos->map(fn ($p) => ['codigo' => $p->codigo, 'nombre' => $p->nombre])->values()],
                'grupos' => $jornada->grupos->map(fn ($grupo) => [
                    'id' => $grupo->id, 'numero' => $grupo->numero, 'nombre' => $grupo->nombre, 'estado' => $grupo->estado,
                    'rutas' => $grupo->rutas->map(function ($ruta) {
                        $e = $ruta->establecimiento;
                        $v = $ruta->visita;

                        return ['id' => $ruta->id, 'orden' => $ruta->orden, 'rbd' => $e->rbd, 'nombre' => $e->nombre_establecimiento, 'comuna' => $e->comuna, 'latitud' => $e->latitud !== null ? (float) $e->latitud : null, 'longitud' => $e->longitud !== null ? (float) $e->longitud : null, 'logo_url' => $e->admisionPerfil?->logoUrl() ?? asset(config('brand.logo_principal', 'branding/01_logo_principal.png')), 'estado' => $v?->estado ?? 'pendiente', 'inicio_votacion' => $v?->inicio_votacion_at?->timezone(config('votaciones.timezone'))->format('H:i'), 'fin_votacion' => $v?->fin_votacion_at?->timezone(config('votaciones.timezone'))->format('H:i')];
                    })->values(),
                ])->values(),
                'incidencias' => $jornada->incidencias->map(fn ($i) => ['id' => $i->id, 'grupo_id' => $i->grupo_votacion_id, 'tipo' => $i->tipo, 'mensaje' => $i->mensaje_publico, 'creada_at' => $i->created_at->timezone(config('votaciones.timezone'))->format('H:i')])->values(),
                'actualizado_at' => now(config('votaciones.timezone'))->toIso8601String(),
            ];
        });
    }
}
