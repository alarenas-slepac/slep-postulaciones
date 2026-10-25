<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DotacionPal2027Consolidado
{
    public function __construct(private readonly DotacionPal2027 $formato) {}

    public function establecimientos(): Builder
    {
        // Mismo universo del listado de Dotación; no hereda filtros ni paginación.
        return Establecimiento::query()->where(fn ($q) => $q->whereNull('sala_cuna')->orWhere('sala_cuna', false))
            ->orderBy('rbd')->orderBy('id');
    }

    public function reportes(array $tablas): \Generator
    {
        foreach ($this->establecimientos()->lazy(10) as $ee) {
            try {
                $reporte = $this->formato->construir($ee, $this->formato->datos($ee), $tablas);
                if ($reporte['cursos'] === []) {
                    $reporte['cursos'][] = ['A' => (string) $ee->nombre_establecimiento, 'B' => (string) $ee->rbd,
                        'C' => 'Sin cursos registrados para 2027; revisar SGA', 'D' => 0, 'E' => 0, 'F' => null, 'H' => null];
                }
            } catch (\Throwable $error) {
                report($error);
                throw ValidationException::withMessages(['pal' => 'No se pudo calcular el RBD '.$ee->rbd.'. Se canceló el consolidado completo; revise ese establecimiento y vuelva a descargar.']);
            } finally { $ee->unsetRelations(); }
            yield $reporte + ['rbd' => (string) $ee->rbd];
            unset($reporte);
        }
    }
}
