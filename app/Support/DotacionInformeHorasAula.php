<?php

namespace App\Support;

use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Horas pedagógicas requeridas por los planes, agrupadas para el Excel. */
class DotacionInformeHorasAula
{
    public const HOJAS = [
        'parvularia' => 'Educación Parvularia',
        'basica_1_6' => 'Básica 1° a 6°',
        'basica_7_8' => 'Básica 7° y 8°',
        'media' => 'Media 1° y 2°',
        'media_hc' => 'Media HC 3° y 4°',
        'media_tp' => 'Media TP 3° y 4°',
        'epja' => 'EPJA',
        'especial' => 'Especial (Laborales)',
    ];

    public const ASIGNATURAS_BASICA_1_6 = [
        'lenguaje y comunicacion' => 'Lenguaje y Comunicación',
        'matematica' => 'Matemática',
        'historia geografia y ciencias sociales' => 'Historia, Geografía y Ciencias Sociales',
        'artes visuales' => 'Artes Visuales',
        'musica' => 'Música',
        'educacion fisica y salud' => 'Educación Física y Salud',
        'orientacion' => 'Orientación',
        'tecnologia' => 'Tecnología',
        'religion' => 'Religión',
        'ciencias naturales' => 'Ciencias Naturales',
        'idioma extranjero ingles' => 'Idioma Extranjero Inglés',
    ];

    public function __construct(private readonly DotacionInformePlanesNiveles $niveles) {}

    public function filas(array $datos): array
    {
        $secciones = $this->secciones($datos);
        if ($secciones->isEmpty()) {
            return [];
        }

        $necesidades = collect(data_get($datos, 'asignacion.necesidades.plan_estudio', []))
            ->filter(fn ($fila) => empty($fila['dotacion_curso_combinado_id']))
            ->groupBy('establecimiento_curso_id');
        $filas = [];
        $miembrosCombinados = [];
        foreach (collect(data_get($datos, 'cursos_combinados.grupos', []))->where('activo', true) as $grupo) {
            $miembros = collect($grupo['miembros'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->unique()
                ->mapWithKeys(fn ($id) => [$id => $secciones->get($id)])->filter();
            $nivelesGrupo = $miembros->pluck('hoja')->filter()->unique();
            // Un plan compartido entre enseñanzas se registra una sola vez.
            $hoja = collect(array_keys(self::HOJAS))->first(fn ($key) => $nivelesGrupo->contains($key));
            if ($hoja === null) {
                continue;
            }

            $miembrosCombinados += array_fill_keys($miembros->keys()->all(), true);
            $plan = collect($grupo['asignaturas'] ?? []);
            $nombre = 'Curso combinado «'.$grupo['nombre'].'» · '.$miembros->pluck('seccion')->implode(' + ');
            $filas[$hoja][] = $this->fila($nombre, $miembros, $plan, $plan->isEmpty() ? 'Plan pendiente de configurar.' : '', combinado: true);
        }

        $independientes = $secciones->except(array_keys($miembrosCombinados))->filter(fn ($seccion) => $seccion['hoja'] !== null)
            ->groupBy(['hoja', 'nivel']);
        foreach ($independientes as $hoja => $niveles) {
            foreach ($niveles as $seccionesNivel) {
                $plan = $seccionesNivel->flatMap(fn ($seccion) => $necesidades->get($seccion['id'], []));
                $pendientes = $seccionesNivel->reject(fn ($seccion) => $necesidades->has($seccion['id']))->pluck('seccion');
                $nombre = $seccionesNivel->pluck('seccion')->unique()->implode(' / ');
                $nota = $pendientes->isEmpty() ? '' : 'Plan pendiente para '.$pendientes->implode(', ').'.';
                $filas[$hoja][] = $this->fila($nombre, $seccionesNivel, $plan, $nota);
            }
        }

        foreach ($filas as &$filasHoja) {
            usort($filasHoja, fn ($a, $b) => $a['orden'] <=> $b['orden'] ?: strnatcasecmp($a['curso'], $b['curso']));
        }
        unset($filasHoja);

        return $filas;
    }

    private function secciones(array $datos): Collection
    {
        $niveles = [];
        foreach (data_get($datos, 'cursos.rows', []) as $nivel => $fila) {
            foreach ($fila['detalles'] ?? [] as $detalle) {
                $niveles[(int) $detalle['establecimiento_curso_id']] = $nivel;
            }
        }

        if ($niveles === []) {
            return collect();
        }

        return EstablecimientoCurso::query()->whereKey(array_keys($niveles))
            ->with('curso:id,codigo,nombre,nivel_educativo,modalidad,orden')
            ->orderBy('curso_id')->orderBy('letra')->orderBy('id')
            ->get(['id', 'curso_id', 'nombre_seccion', 'letra', 'regimen_jec'])
            ->mapWithKeys(function (EstablecimientoCurso $seccion) use ($niveles): array {
                $nivel = $niveles[$seccion->id];
                $tipo = $this->niveles->clasificar($seccion);
                $hoja = match ($tipo) {
                    'basica' => in_array($nivel, ['7B', '8B'], true) ? 'basica_7_8' : 'basica_1_6',
                    'media' => in_array($nivel, ['1M', '2M'], true) ? 'media' : null,
                    default => $tipo,
                };
                $nombre = trim((string) $seccion->nombre_seccion)
                    ?: trim(($seccion->curso?->nombre ?? 'Curso').' '.$seccion->letra);

                return [$seccion->id => [
                    'id' => $seccion->id, 'hoja' => $hoja,
                    'nivel' => in_array($hoja, ['epja', 'especial'], true) ? $seccion->curso_id : $nivel,
                    'seccion' => $nombre, 'orden' => $seccion->curso?->orden ?? 9999,
                    'regimen' => $seccion->regimen_jec,
                ]];
            });
    }

    private function fila(string $nombre, Collection $secciones, Collection $plan, string $nota, bool $combinado = false): array
    {
        $asignaturas = [];
        $libre = 0.0;
        foreach ($plan as $necesidad) {
            $horas = max(0.0, (float) ($necesidad['horas_plan_requeridas'] ?? 0));
            $horasLibres = $necesidad['curso_combinado_horas_libre_disposicion']
                ?? (($necesidad['subtipo_asignacion'] ?? null) === 'libre_disposicion'
                    ? $horas : ($necesidad['horas_libre_disposicion_consolidada'] ?? 0));
            $horasLibres = min($horas, max(0.0, (float) $horasLibres));
            $libre += $horasLibres;
            $comun = round($horas - $horasLibres, 2);
            if ($comun <= 0) {
                continue;
            }

            $titulo = trim((string) (
                collect($necesidad['curso_combinado_desglose'] ?? [])->firstWhere('bloque', 'Plan común')['nombre']
                ?? $necesidad['asignatura_nombre'] ?? $necesidad['titulo'] ?? ''
            )) ?: 'Plan común sin desglose';
            $clave = Str::of($titulo)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
            $clave = match ($clave) {
                'matematicas' => 'matematica',
                'ingles', 'ingles idioma extranjero' => 'idioma extranjero ingles',
                default => $clave,
            };
            $asignaturas[$clave] ??= ['nombre' => self::ASIGNATURAS_BASICA_1_6[$clave] ?? $titulo, 'horas' => 0.0];
            $asignaturas[$clave]['horas'] = round($asignaturas[$clave]['horas'] + $comun, 2);
        }

        $regimenes = $secciones->flatMap(fn ($seccion) => $this->regimenes($seccion['regimen']))->unique();

        return [
            'curso' => $nombre, 'orden' => $secciones->min('orden'), 'nota' => $nota,
            'cursos' => $combinado ? 1 : $secciones->count(),
            'jec' => collect(['Con JEC', 'Sin JEC', 'No aplica', 'Sin información'])
                ->filter(fn ($regimen) => $regimenes->contains($regimen))->implode(' / '),
            'asignaturas' => $asignaturas, 'libre' => $plan->isEmpty() ? null : round($libre, 2),
        ];
    }

    private function regimenes(?string $texto): array
    {
        $regimen = Str::of($texto ?? '')->ascii()->upper()->squish()->toString();
        $con = str_contains($regimen, 'CON JEC') || str_contains($regimen, 'CON Y SIN JEC');
        $sin = str_contains($regimen, 'SIN JEC');

        return match (true) {
            $con && $sin => ['Con JEC', 'Sin JEC'],
            $sin => ['Sin JEC'],
            str_contains($regimen, 'JEC') => ['Con JEC'],
            $regimen === 'NO APLICA' => ['No aplica'],
            default => ['Sin información'],
        };
    }
}
