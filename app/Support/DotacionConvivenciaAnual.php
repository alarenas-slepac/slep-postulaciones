<?php

namespace App\Support;

use App\Models\DotacionFuncionRegla;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DotacionConvivenciaAnual
{
    public const TABLE = 'dotacion_convivencia_horas';
    public const CODIGO = 'encargado_convivencia';
    public const ROLES = ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    public static function puedeConfigurar(?string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public static function horas(int $establecimientoId, int $anio): ?float
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }
        $horas = DB::table(self::TABLE)->where('establecimiento_id', $establecimientoId)->where('anio', $anio)->value('horas');

        return $horas === null ? null : (float) $horas;
    }

    public static function filas(int $anio): Collection
    {
        $establecimientos = Establecimiento::query()->orderBy('rbd')->orderBy('id')->get();
        $definidas = Schema::hasTable(self::TABLE)
            ? DB::table(self::TABLE)->where('anio', $anio)->pluck('horas', 'establecimiento_id') : collect();
        $matriculas = DB::table('establecimiento_cursos')->where('anio', $anio)->where('activo', true)
            ->groupBy('establecimiento_id')->selectRaw('establecimiento_id, SUM(matricula) as total')->pluck('total', 'establecimiento_id');
        $reglas = DotacionFuncionRegla::query()->where('codigo', self::CODIGO)->get();
        $asignadas = DB::table('dotacion_docente_asignaciones')->where('anio', $anio)->where('estado', 'activa')
            ->where('tipo_asignacion', 'funcion_tecnico_pedagogica')
            ->where(function ($query) use ($reglas): void {
                $query->whereIn('dotacion_funcion_regla_id', $reglas->pluck('id'))
                    ->orWhereIn('asignatura_nombre', ['Encargado(a) de Convivencia Escolar', 'Coordinador(a) de Convivencia Educativa']);
            })->groupBy('establecimiento_id')->selectRaw('establecimiento_id, SUM(horas_contrato) as total')->pluck('total', 'establecimiento_id');
        $base = (float) ($reglas->firstWhere('vigente', true)?->horas_fijas ?? 44);

        return $establecimientos->map(function (Establecimiento $establecimiento) use ($anio, $definidas, $matriculas, $asignadas, $base): array {
            $id = $establecimiento->id;
            $historicas = DotacionFuncionesNormativas2027::horasConfiguradas($establecimiento, $anio);
            $horas = $definidas->get($id) ?? $asignadas->get($id) ?? $historicas[self::CODIGO] ?? $base;

            return [
                'establecimiento_id' => $id,
                'rbd' => $establecimiento->rbd,
                'nombre' => $establecimiento->nombre_establecimiento,
                'matricula' => (int) $matriculas->get($id, 0),
                'horas' => (float) $horas,
                'asignadas' => (float) $asignadas->get($id, 0),
                'carga_anual' => $definidas->has($id),
            ];
        });
    }
}
