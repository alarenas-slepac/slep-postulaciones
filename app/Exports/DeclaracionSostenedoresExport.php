<?php

namespace App\Exports;

use App\Models\DeclaracionSostenedor;

class DeclaracionSostenedoresExport
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['RBD','RUT','Nombres','Apellido Paterno','Apellido Materno','Horas','Parvularia','Básica','Media','Estamento','Función','Tipo Título','Nombre Título','Institución','Fecha Titulación','País'];
    }

    public function rows(): array
    {
        return $this->query()->get([
            'rbd', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'horas_contratadas',
            'educacion_parvularia', 'ensenanza_basica', 'ensenanza_media', 'estamento', 'nombre_funcion', 'tipo_titulo', 'nombre_titulo',
            'institucion_educacional', 'fecha_titulacion', 'pais_titulo',
        ])->map(function ($r) {
            return [
                $r->rbd,
                $r->rut,
                $r->nombres,
                $r->apellido_paterno,
                $r->apellido_materno,
                $r->horas_contratadas,
                $r->educacion_parvularia ? 'SI' : 'NO',
                $r->ensenanza_basica ? 'SI' : 'NO',
                $r->ensenanza_media ? 'SI' : 'NO',
                $r->estamento,
                $r->nombre_funcion,
                $r->tipo_titulo,
                $r->nombre_titulo,
                $r->institucion_educacional,
                $r->fecha_titulacion,
                $r->pais_titulo,
            ];
        })->all();
    }

    protected function query()
    {
        $query = DeclaracionSostenedor::query()->orderBy('rbd');

        if (!empty($this->filters['rut'])) {
            $query->where('rut', 'like', '%' . trim((string) $this->filters['rut']) . '%');
        }

        if (!empty($this->filters['nombre'])) {
            $nombre = trim((string) $this->filters['nombre']);
            $query->where(function ($q) use ($nombre) {
                $q->where('nombres', 'like', '%' . $nombre . '%')
                    ->orWhere('apellido_paterno', 'like', '%' . $nombre . '%')
                    ->orWhere('apellido_materno', 'like', '%' . $nombre . '%');
            });
        }

        if (!empty($this->filters['establecimiento'])) {
            $query->where('rbd', trim((string) $this->filters['establecimiento']));
        }

        $tab = strtolower(trim((string) ($this->filters['tab'] ?? '')));
        if ($tab === 'docentes') {
            $query->where('estamento', 'DOCENTE');
        } elseif (in_array($tab, ['asistentes', 'asistente'], true)) {
            $query->where('estamento', 'ASISTENTE');
        }

        return $query;
    }
}
