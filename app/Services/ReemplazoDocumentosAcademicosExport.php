<?php

namespace App\Services;

use App\Support\StreamingXlsxWriter;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ReemplazoDocumentosAcademicosExport
{
    public const DOCUMENTS = [
        'titulo' => 'Título profesional o técnico',
        'titulo_mencion' => 'Título con mención',
        'cert_semestres_horas' => 'Certificado de semestres/horas',
        'licencia_media' => 'Licencia de Enseñanza Media',
    ];

    private const STATES = ['aceptada', 'cerrado', 'cerrada'];
    private const NORMALIZED_RUT = "UPPER(REPLACE(REPLACE(REPLACE(TRIM(u.rut), '.', ''), '-', ''), ' ', ''))";

    private function baseQuery(): Builder
    {
        // Mismo reemplazante que la gestión: perfil actual, con fallback al
        // perfil de contrato histórico si el actual ya no existe. No se utiliza
        // el titular ni una propuesta anterior a una reasignación.
        return DB::table('solicitudes_reemplazo as s')
            ->whereIn('s.estado', self::STATES)
            ->leftJoin('postulant_profiles as actual', 'actual.id', '=', 's.postulant_profile_id')
            ->leftJoin('postulant_profiles as p', 'p.id', '=', DB::raw('COALESCE(actual.id, s.contrato_trabajo_postulant_profile_id)'))
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id');
    }

    public function requestsQuery(): Builder
    {
        return $this->baseQuery()->whereNotNull('u.id');
    }

    public function missingProfilesQuery(): Builder
    {
        return $this->baseQuery()->whereNull('u.id')->select('s.id', 's.numero_solicitud', 's.estado');
    }

    public function peopleQuery(): Builder
    {
        return $this->requestsQuery()
            ->selectRaw('MIN(u.id) as person_id, '.self::NORMALIZED_RUT.' as rut_normalizado')
            ->groupByRaw(self::NORMALIZED_RUT)
            // Si falta RUT no se agrupan personas diferentes.
            ->groupByRaw('CASE WHEN u.rut IS NULL OR '.self::NORMALIZED_RUT." = '' THEN u.id ELSE NULL END")
            ->orderBy('rut_normalizado')->orderBy('person_id');
    }

    private function recordsQuery(): Builder
    {
        return $this->requestsQuery()
            ->leftJoin('establecimientos as e', 'e.id', '=', 's.establecimiento_id')
            ->leftJoin('areas_desempeno as a', 'a.id', '=', 's.area_desempeno_id')
            ->leftJoin('areas_desempeno as pa', 'pa.id', '=', 'p.area_desempeno_id')
            ->select([
                's.id as request_id', 's.numero_solicitud', 's.estado', 's.anio',
                'e.rbd', 'e.nombre_establecimiento', 'a.nombre as area_solicitud', 'pa.nombre as area_perfil',
                'u.id as user_id', 'u.rut', 'u.nombres', 'u.apellido_paterno', 'u.apellido_materno',
                'p.estamento', 'p.nivel_estudios', 'p.fecha_titulacion', 'p.semestres', 'p.horas_totales',
            ])->selectRaw(self::NORMALIZED_RUT.' as rut_normalizado');
    }

    public function preview(object $persona): array
    {
        $query = $this->recordsQuery();
        if (filled($persona->rut_normalizado)) {
            $query->whereRaw(self::NORMALIZED_RUT.' = ?', [$persona->rut_normalizado]);
        } else {
            $query->where('u.id', $persona->person_id);
        }
        $person = $this->person($query->orderBy('s.id')->get());
        $person['disponibles'] = $this->documents($person['user_ids'])->filter(fn ($document) => $this->sourcePath($document->path) !== null)->count();

        return $person;
    }

    /** Lee solicitudes en lotes; solo conserva las de una persona cada vez. */
    private function people(): Generator
    {
        $currentKey = null;
        $rows = collect();
        foreach ($this->recordsQuery()->orderBy('rut_normalizado')->orderBy('u.id')->orderBy('s.id')->lazy(200) as $row) {
            $key = filled($row->rut_normalizado) ? 'rut:'.$row->rut_normalizado : 'user:'.$row->user_id;
            if ($currentKey !== null && $key !== $currentKey) {
                yield $this->person($rows);
                $rows = collect();
            }
            $currentKey = $key;
            $rows->push($row);
        }
        if ($rows->isNotEmpty()) {
            yield $this->person($rows);
        }
    }

    private function person(Collection $rows): array
    {
        $last = $rows->sortByDesc('request_id')->first();
        $name = trim(implode(' ', array_filter([$last->nombres, $last->apellido_paterno, $last->apellido_materno])));
        $rut = $last->rut_normalizado ?: '';

        return [
            'nombre' => $name ?: 'Sin nombre registrado',
            'rut' => $rut === '' ? '' : substr($rut, 0, -1).'-'.substr($rut, -1),
            'user_ids' => $rows->pluck('user_id')->unique()->values()->all(),
            'carpeta' => 'personas/'.(preg_match('/^[0-9]+[0-9K]$/D', $rut) ? $rut : 'usuario-'.$last->user_id).'_'.mb_substr(Str::slug($name ?: 'sin-nombre'), 0, 80),
            'solicitudes' => $rows->all(),
            'numero_solicitudes' => $rows->map(fn ($r) => $r->numero_solicitud ?: 'ID '.$r->request_id)->unique()->implode(' | '),
            'rbd' => $rows->pluck('rbd')->filter()->unique()->implode(' | '),
            'establecimiento' => $rows->pluck('nombre_establecimiento')->filter()->unique()->implode(' | '),
            'area' => $rows->map(fn ($r) => $r->area_solicitud ?: $r->area_perfil)->filter()->unique()->implode(' | '),
            'estamento' => match ($last->estamento) { 'docente' => 'Docente', 'asistente' => 'Asistente de la Educación', default => $last->estamento },
            'nivel' => $last->nivel_estudios,
            'fecha_titulacion' => $last->fecha_titulacion,
            'semestres' => $last->semestres === null ? null : (int) $last->semestres,
            'horas_totales' => $last->horas_totales === null ? null : (int) $last->horas_totales,
        ];
    }

    private function documents(array $users): Collection
    {
        // Exporta la carga vigente de cada tipo, cualquiera sea su revisión o
        // su visibilidad actual en el perfil. Conserva documentos históricos.
        return DB::table('user_documents as d')->join('document_types as t', 't.id', '=', 'd.document_type_id')
            ->whereIn('d.user_id', $users)->whereIn('t.slug', array_keys(self::DOCUMENTS))
            ->select('d.id', 'd.path', 'd.status', 't.slug')
            ->orderByDesc('d.updated_at')->orderByDesc('d.id')->get()->unique('slug')->keyBy('slug');
    }

    private function sourcePath(?string $path): ?string
    {
        if (! $path || str_contains($path, "\0")) {
            return null;
        }
        try {
            $disk = Storage::disk('public');
            $root = realpath($disk->path(''));
            $source = realpath($disk->path($path));
            if ($root === false || $source === false || ! is_file($source) || ! is_readable($source)) {
                return null;
            }
            $prefix = rtrim(str_replace('\\', '/', $root), '/').'/';
            $normalized = str_replace('\\', '/', $source);

            return str_starts_with(PHP_OS_FAMILY === 'Windows' ? strtolower($normalized) : $normalized, PHP_OS_FAMILY === 'Windows' ? strtolower($prefix) : $prefix) ? $source : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function generate(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP no está disponible.');
        }
        $disk = Storage::disk('local');
        $token = (string) Str::uuid();
        $directory = 'exports/reemplazos-academicos/'.$token;
        $zipRelative = $directory.'.zip';
        if (! $disk->makeDirectory($directory)) {
            throw new RuntimeException('No fue posible preparar la exportación.');
        }
        $zipPath = $disk->path($zipRelative);
        $zip = new ZipArchive;
        $opened = false;
        try {
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible crear el ZIP.');
            }
            $opened = true;
            $xlsxPath = $disk->path($directory.'/nomina.xlsx');
            $book = new StreamingXlsxWriter($xlsxPath);
            $nomina = $book->addSheet('Nómina', ['Nombre completo', 'RUT', 'Solicitudes asociadas', 'RBD', 'Establecimiento', 'Área de desempeño', 'Estamento', 'Nivel de estudios', 'Fecha de titulación', 'Semestres', 'Horas totales'], [40, 16, 40, 20, 55, 35, 28, 30, 22, 14, 16]);
            $solicitudes = $book->addSheet('Solicitudes', ['RUT', 'Nombre completo', 'ID solicitud', 'N° solicitud', 'Año', 'Estado', 'RBD', 'Establecimiento', 'Área de desempeño'], [16, 40, 16, 22, 12, 18, 14, 55, 35]);
            $documentos = $book->addSheet('Documentos', ['RUT', 'Nombre completo', 'Documento', 'Disponibilidad', 'Estado de revisión', 'Archivo en ZIP'], [16, 40, 40, 25, 20, 70]);
            foreach ($this->people() as $person) {
                $date = $this->graduationDate($person['fecha_titulacion']);
                $book->appendRow($nomina, [$person['nombre'], $person['rut'], $person['numero_solicitudes'], $person['rbd'], $person['establecimiento'], $person['area'], $person['estamento'], $person['nivel'], $date, $person['semestres'], $person['horas_totales']]);
                foreach ($person['solicitudes'] as $row) {
                    $book->appendRow($solicitudes, [$person['rut'], $person['nombre'], (int) $row->request_id, $row->numero_solicitud, $row->anio === null ? null : (int) $row->anio, $row->estado === 'aceptada' ? 'Aceptada' : 'Cerrada', (string) ($row->rbd ?? ''), $row->nombre_establecimiento, $row->area_solicitud ?: $row->area_perfil]);
                }
                $docs = $this->documents($person['user_ids']);
                foreach (self::DOCUMENTS as $slug => $label) {
                    $doc = $docs->get($slug);
                    $entry = '';
                    $availability = $doc ? 'Archivo no disponible' : 'No cargado';
                    $source = $doc ? $this->sourcePath($doc->path) : null;
                    if ($source !== null) {
                        // Copia temporal: un reemplazo concurrente del perfil no
                        // puede eliminar un archivo que ZIP todavía debe leer.
                        $copy = $disk->path($directory.'/documento-'.$doc->id.'.pdf');
                        if (@copy($source, $copy) && is_file($copy) && filesize($copy) > 0) {
                            $entry = $person['carpeta'].'/'.Str::slug($label).'.pdf';
                            if (! $zip->addFile($copy, $entry)) {
                                throw new RuntimeException('No fue posible agregar un documento al ZIP.');
                            }
                            $availability = 'Incluido';
                        }
                    }
                    $book->appendRow($documentos, [$person['rut'], $person['nombre'], $label, $availability, $doc?->status, $entry]);
                }
            }
            $incidencias = $book->addSheet('Sin perfil disponible', ['ID solicitud', 'N° solicitud', 'Estado', 'Observación'], [16, 24, 20, 65]);
            foreach ($this->missingProfilesQuery()->orderBy('s.id')->lazy(200) as $row) {
                $book->appendRow($incidencias, [(int) $row->id, $row->numero_solicitud, $row->estado, 'No existe un perfil de reemplazante disponible para asociar documentos.']);
            }
            $book->close();
            if (! $zip->addFile($xlsxPath, 'nomina_reemplazos.xlsx')) {
                throw new RuntimeException('No fue posible finalizar el ZIP.');
            }
            $closed = $zip->close();
            $opened = false;
            if (! $closed) {
                throw new RuntimeException('No fue posible finalizar el ZIP.');
            }
            clearstatcache(true, $zipPath);
            if (! is_file($zipPath) || filesize($zipPath) === 0) {
                throw new RuntimeException('La exportación está vacía.');
            }

            return $zipPath;
        } catch (\Throwable $error) {
            if ($opened) {
                try {
                    $zip->close();
                } catch (\Throwable) {
                    // Conserva el error original y continúa retirando temporales.
                }
            }
            $disk->delete($zipRelative);
            throw $error;
        } finally {
            unset($book);
            $disk->deleteDirectory($directory);
        }
    }

    private function graduationDate(?string $value): ?string
    {
        if (! $value || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return $parts[3].'-'.$parts[2].'-'.$parts[1];
    }
}
