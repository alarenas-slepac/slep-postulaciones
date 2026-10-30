<?php

namespace App\Services\Maternidad;

use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MaternidadPersonalResolver
{
    public function buscar(string $rut): array
    {
        $rut = MaternidadRut::normalizar($rut);
        $clave = str_replace('-', '', $rut);
        $resultados = [];
        $usuario = Schema::hasTable('users') && Schema::hasColumn('users', 'rut')
            ? User::query()->whereRaw($this->sqlRut('rut').' = ?', [$clave])->first() : null;

        if (Schema::hasTable('reemplazos_personal')) {
            $filas = ReemplazoPersonal::query()->with('establecimiento')
                ->whereRaw($this->sqlRut('rut').' = ?', [$clave])
                ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('id')->limit(200)->get();
            // Conservar vínculos distintos sin repetir cada mes de la misma contratación.
            $vistas = [];
            foreach ($filas as $fila) {
                if (! $fila->establecimiento) continue;
                $key = implode('|', [$fila->establecimiento_id, $fila->tipocontrato, $fila->fecha_ingreso?->toDateString(), $fila->fecha_termino?->toDateString()]);
                if (isset($vistas[$key])) continue;
                $vistas[$key] = true;
                $nombre = $this->nombre($fila, $usuario);
                $resultados[] = $this->candidato('padron', $fila->id, $rut, $nombre, $fila->establecimiento,
                    $this->estamento($fila->estatuto.' '.$fila->escalafon), (string) $fila->tipocontrato,
                    $fila->fecha_ingreso?->toDateString(), $fila->fecha_termino?->toDateString(),
                    'Padrón '.sprintf('%02d/%04d', $fila->mes, $fila->anio));
            }
        }
        if (Schema::hasTable('solicitudes_reemplazo') && Schema::hasColumn('solicitudes_reemplazo', 'rut_reemplazo_normalizado')) {
            $relaciones = ['establecimiento'];
            $conPerfiles = Schema::hasTable('postulant_profiles') && Schema::hasTable('users');
            if ($conPerfiles) {
                $relaciones[] = 'postulante.user';
                if (Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_postulant_profile_id')) $relaciones[] = 'contratoPostulante.user';
            }
            $solicitudes = SolicitudReemplazo::query()->with($relaciones)
                ->where(function ($q) use ($clave, $conPerfiles) {
                    $q->whereRaw($this->sqlRut('rut_reemplazo_normalizado').' = ?', [$clave]);
                    if ($conPerfiles) {
                        $q->orWhereHas('postulante.user', fn ($u) => $u->whereRaw($this->sqlRut('rut').' = ?', [$clave]));
                        if (Schema::hasColumn('solicitudes_reemplazo', 'contrato_trabajo_postulant_profile_id')) {
                            $q->orWhereHas('contratoPostulante.user', fn ($u) => $u->whereRaw($this->sqlRut('rut').' = ?', [$clave]));
                        }
                    }
                })->orderByDesc('fecha_inicio')->orderByDesc('id')->limit(100)->get();
            foreach ($solicitudes as $solicitud) {
                if (! $solicitud->establecimiento || in_array($solicitud->estado, ['anulada', 'rechazada', 'rechazada_uatp', 'rechazada_plani'], true)) continue;
                $perfil = $conPerfiles ? (($solicitud->relationLoaded('contratoPostulante') ? $solicitud->contratoPostulante : null) ?? $solicitud->postulante) : null;
                $persona = $perfil?->user;
                $rutFuente = $persona?->rut ?: $solicitud->rut_reemplazo_normalizado;
                if (str_replace(['.', '-', ' '], '', strtoupper((string) $rutFuente)) !== $clave) continue;
                $nombre = $this->nombre($persona ?? $usuario, $usuario);
                $estamento = $this->estamento((string) ($perfil?->estamento ?? ''));
                $resultados[] = $this->candidato('reemplazo', $solicitud->id, $rut, $nombre, $solicitud->establecimiento,
                    $estamento, 'REEMPLAZO',
                    ($solicitud->contrato_trabajo_fecha_inicio_trabajo ? \Carbon\CarbonImmutable::parse($solicitud->contrato_trabajo_fecha_inicio_trabajo)->toDateString() : null)
                        ?? $solicitud->fecha_inicio_trabajo?->toDateString() ?? $solicitud->fecha_inicio?->toDateString(),
                    $solicitud->fecha_termino?->toDateString(), 'Solicitud '.$solicitud->numero_solicitud);
            }
        }
        return array_slice($resultados, 0, 50);
    }

    public function resolver(string $rut, ?string $fuente, ?string $rbd = null): array
    {
        $candidatos = collect($this->buscar($rut));
        if ($fuente) $candidatos = $candidatos->where('fuente', $fuente);
        if ($rbd !== null && trim($rbd) !== '') $candidatos = $candidatos->filter(fn ($c) => (string) $c['establecimiento_rbd'] === ltrim(trim($rbd), '0'));
        if ($candidatos->isEmpty()) throw ValidationException::withMessages(['rut' => 'No se encontró un vínculo válido para ese RUT y selección en padrón o solicitudes de reemplazo.']);
        if (! $fuente && $candidatos->pluck('establecimiento_id')->unique()->count() > 1) {
            throw ValidationException::withMessages(['fuente' => 'El RUT tiene vínculos en varios establecimientos. Seleccione uno o indique su RBD en el Excel.']);
        }
        return $candidatos->first();
    }

    private function candidato(string $origen, int $id, string $rut, array $nombre, $establecimiento, ?string $estamento, string $calidad, ?string $inicio, ?string $fin, string $descripcion): array
    {
        return $nombre + ['rut' => $rut, 'fuente' => $origen.':'.$id, 'fuente_personal' => $origen, 'fuente_personal_id' => $id,
            'establecimiento_id' => (int) $establecimiento->id, 'establecimiento_nombre' => $establecimiento->nombre_establecimiento,
            'establecimiento_rbd' => (string) $establecimiento->rbd, 'comuna' => $establecimiento->comuna,
            'estamento' => $estamento, 'calidad_juridica' => $calidad ?: null,
            'fecha_ingreso' => $inicio, 'fecha_termino' => $fin,
            'descripcion' => $descripcion.' · RBD '.$establecimiento->rbd.' · '.$establecimiento->nombre_establecimiento.' · '.$calidad];
    }

    private function nombre($fila, $usuario): array
    {
        foreach ([$fila, $usuario] as $persona) {
            if (trim((string) ($persona?->nombres ?? '')) !== '' && trim((string) ($persona?->apellido_paterno ?? '')) !== '') {
                return ['nombres' => trim($persona->nombres), 'apellido_paterno' => trim($persona->apellido_paterno),
                    'apellido_materno' => trim((string) $persona->apellido_materno), 'nombre_requiere_revision' => false];
            }
        }
        $nombre = Str::squish((string) ($fila?->nombre ?? $fila?->name ?? ''));
        if (str_contains($nombre, ',')) {
            [$apellidos, $nombres] = array_pad(explode(',', $nombre, 2), 2, '');
            $partes = preg_split('/\s+/u', trim($apellidos), -1, PREG_SPLIT_NO_EMPTY);
        } else {
            // Convención de la carga de reemplazos_personal: apellidos y luego nombres.
            $partes = preg_split('/\s+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY);
            $nombres = null;
        }
        $apellido = function () use (&$partes): string {
            $salida = [];
            while ($partes) {
                $parte = array_shift($partes); $salida[] = $parte;
                if (! in_array(Str::upper(Str::ascii($parte)), ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'SAN', 'SANTA'], true)) break;
            }
            return implode(' ', $salida);
        };
        $paterno = $apellido(); $materno = count($partes) > ($nombres === null ? 1 : 0) ? $apellido() : '';
        $nombres ??= implode(' ', $partes);
        return ['nombres' => trim($nombres), 'apellido_paterno' => $paterno, 'apellido_materno' => $materno, 'nombre_requiere_revision' => true];
    }

    private function estamento(string $valor): ?string
    {
        $valor = Str::upper(Str::ascii($valor));
        if (str_contains($valor, 'ASIST') || str_contains($valor, 'AAEE') || str_contains($valor, 'EDUCACION NO DOCENTE')) return 'asistente';
        if (str_contains($valor, 'DOCENTE') || str_contains($valor, 'PROFESOR') || str_contains($valor, 'EDUCADORA')) return 'docente';
        return null;
    }

    private function sqlRut(string $columna): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(COALESCE({$columna}, ''), '.', ''), '-', ''), ' ', ''))";
    }
}
