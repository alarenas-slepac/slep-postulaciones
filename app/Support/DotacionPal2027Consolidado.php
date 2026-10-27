<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DotacionPal2027Consolidado
{
    public function __construct(private readonly DotacionPal2027 $formato, private readonly DotacionPalPersonal2027 $personal) {}

    public function establecimientos(): Builder
    {
        // No hereda filtros. Añade jardines con personal histórico AAEE/JUNJI.
        return Establecimiento::query()->where(function ($q): void {
            $q->whereNull('sala_cuna')->orWhere('sala_cuna', false);
            if ($this->personal->disponible()) { $q->orWhereIn('rbd', $this->personal->fuente()->select('rbd')); }
        })
            ->orderBy('rbd')->orderBy('id');
    }

    public function reportes(array $tablas): \Generator
    {
        foreach ($this->establecimientos()->lazy(10) as $ee) {
            try {
                // Los jardines sólo aportan personal VTF; no se les proyecta un
                // plan escolar ni una nómina docente que no forman parte del proceso.
                $reporte = $ee->sala_cuna ? ['cursos' => [], 'docentes' => []]
                    : $this->formato->construir($ee, $this->formato->datos($ee), $tablas);
                $reporte += $this->personal->construir($ee);
                if ($reporte['cursos'] === []) {
                    $reporte['cursos'][] = ['A' => (string) $ee->nombre_establecimiento, 'B' => (string) $ee->rbd,
                        'C' => $ee->sala_cuna ? 'Jardín VTF · referencia de personal 08/2026'
                            : 'Sin cursos registrados para 2027; revisar SGA',
                        'D' => $ee->sala_cuna ? null : 0, 'E' => $ee->sala_cuna ? null : 0, 'F' => null, 'H' => null];
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
