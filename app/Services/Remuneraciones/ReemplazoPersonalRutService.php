<?php

namespace App\Services\Remuneraciones;

use App\Models\FuncionarioAcAutorizado;
use App\Models\ReemplazoPersonal;
use App\Support\RutChile;
use Illuminate\Support\Facades\Schema;

class ReemplazoPersonalRutService
{
    public const ORIGEN_ADMINISTRACION_CENTRAL = 'administracion_central';

    public const ORIGEN_ESTABLECIMIENTO = 'establecimiento';

    public static function opcionesOrigen(): array
    {
        return [
            self::ORIGEN_ADMINISTRACION_CENTRAL => 'Administración Central',
            self::ORIGEN_ESTABLECIMIENTO => 'Establecimiento',
        ];
    }

    public function normalizar(?string $rut): ?string
    {
        $normalizado = RutChile::normalize($rut);

        if (! $normalizado || $normalizado['status'] !== 'ok') {
            return null;
        }

        return strtoupper($normalizado['rut_body'].'-'.$normalizado['rut_dv']);
    }

    public function buscar(?string $rut): ?array
    {
        $rutNormalizado = $this->normalizar($rut);
        if (! $rutNormalizado) {
            return null;
        }

        $rutPlano = str_replace('-', '', $rutNormalizado);
        $funcionarioAc = $this->buscarFuncionarioAc($rutNormalizado, $rutPlano);

        if ($funcionarioAc) {
            return $funcionarioAc;
        }

        if (! Schema::hasTable('reemplazos_personal')) {
            return null;
        }

        $registro = ReemplazoPersonal::query()
            ->where(function ($query) use ($rutNormalizado, $rutPlano) {
                $query->where('rut', $rutNormalizado)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$rutPlano]);
            })
            ->whereNotNull('nombre')
            ->where('nombre', '!=', '')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderByDesc('id')
            ->first();

        if (! $registro) {
            return null;
        }

        $nombre = trim(preg_replace('/\s+/', ' ', (string) $registro->nombre) ?: '');
        if ($nombre === '') {
            return null;
        }

        return [
            'rut' => $rutNormalizado,
            'nombre' => $nombre,
            'periodo' => sprintf('%04d-%02d', $registro->anio, $registro->mes),
            'fuente' => 'el padrón de reemplazos personal',
            'origen' => self::ORIGEN_ESTABLECIMIENTO,
        ];
    }

    private function buscarFuncionarioAc(string $rutNormalizado, string $rutPlano): ?array
    {
        $tabla = 'funcionarios_ac_autorizados';
        if (! Schema::hasTable($tabla)) {
            return null;
        }

        $columnasRut = collect(['run_normalizado', 'rut_normalizado'])
            ->filter(fn (string $columna) => Schema::hasColumn($tabla, $columna))
            ->values();

        if ($columnasRut->isEmpty()) {
            return null;
        }

        $registro = FuncionarioAcAutorizado::query()
            ->where(function ($query) use ($columnasRut, $rutPlano) {
                foreach ($columnasRut as $columna) {
                    $query->orWhere($columna, $rutPlano);
                }
            })
            ->first();

        if (! $registro) {
            return null;
        }

        $nombre = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
            $registro->nombres,
            $registro->apellido_paterno,
            $registro->apellido_materno,
        ], fn ($parte) => trim((string) $parte) !== ''))) ?: '');

        if ($nombre === '') {
            return null;
        }

        $periodo = trim((string) $registro->periodo_nomina);

        return [
            'rut' => $rutNormalizado,
            'nombre' => $nombre,
            'periodo' => $periodo !== '' ? $periodo : null,
            'fuente' => 'funcionarios autorizados de Administración Central',
            'origen' => self::ORIGEN_ADMINISTRACION_CENTRAL,
        ];
    }
}
