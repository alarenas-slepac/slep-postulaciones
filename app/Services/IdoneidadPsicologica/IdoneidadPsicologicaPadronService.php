<?php

namespace App\Services\IdoneidadPsicologica;

use App\Models\IdoneidadPsicologicaFuncionario;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use App\Services\Padron\PadronVigenciaService;
use App\Support\RutChile;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IdoneidadPsicologicaPadronService
{
    /**
     * Personas elegibles del último padrón vigente y de solicitudes de reemplazo.
     * Cada persona se evalúa una sola vez por cargo: si ese cargo ya fue
     * solicitado, aceptado o rechazado, no vuelve a quedar disponible.
     */
    public function funcionariosElegibles(CarbonInterface $fechaInicio, CarbonInterface $fechaTermino): Collection
    {
        $funcionariosPadron = app(PadronVigenciaService::class)->consultaActual()
            ->with('establecimiento:id,rbd,nombre_establecimiento,comuna')
            ->whereNotNull('fecha_ingreso')
            ->whereDate('fecha_ingreso', '>=', $fechaInicio->toDateString())
            ->whereDate('fecha_ingreso', '<=', $fechaTermino->toDateString())
            ->where(function ($query): void {
                foreach (['PLAZO FIJO', 'REEMPLAZ', 'SUPLEN'] as $term) {
                    $query->orWhereRaw("UPPER(COALESCE(tipocontrato, '')) LIKE ?", ["%{$term}%"]);
                }
            })
            ->orderBy('establecimiento_id')
            ->orderBy('rut')
            ->orderByDesc('fecha_ingreso')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ReemplazoPersonal $funcionario): bool => $this->esAaee($funcionario))
            ->unique(fn (ReemplazoPersonal $funcionario): string => $this->claveFuncionario($funcionario))
            ->values();

        $funcionariosSolicitud = $this->funcionariosDesdeSolicitudes($fechaInicio, $fechaTermino);
        $funcionarios = $funcionariosPadron->concat($funcionariosSolicitud)->values();

        if ($funcionarios->isEmpty()) {
            return $funcionarios;
        }

        $ruts = $funcionarios->map(fn (ReemplazoPersonal $funcionario): string => $this->rutNormalizado($funcionario->rut))
            ->filter()
            ->unique()
            ->values();
        $perfilesPorRut = $this->perfilesPorRut($ruts);
        $solicitudesReemplazoPorRut = $this->solicitudesReemplazoPorRut($ruts, $fechaInicio, $fechaTermino);
        $cargosYaSolicitados = $this->cargosYaSolicitadosPorRut($ruts);

        return $funcionarios
            ->map(function (ReemplazoPersonal $funcionario) use ($perfilesPorRut, $solicitudesReemplazoPorRut, $cargosYaSolicitados): ?ReemplazoPersonal {
                $rut = $this->rutNormalizado($funcionario->rut);
                $cargo = $funcionario->cargo_clave_idoneidad
                    ? [
                        'nombre' => $funcionario->cargo_idoneidad,
                        'clave' => $funcionario->cargo_clave_idoneidad,
                        'origen' => $funcionario->cargo_origen_idoneidad,
                        'solicitud_reemplazo_id' => $funcionario->solicitud_reemplazo_idoneidad,
                    ]
                    : $this->resolverCargo($funcionario, $perfilesPorRut->get($rut), $solicitudesReemplazoPorRut->get($rut));

                if ($cargo === null || $cargo['clave'] === '') {
                    return null;
                }
                if (in_array($cargo['clave'], $cargosYaSolicitados->get($rut, []), true)) {
                    return null;
                }

                $funcionario->setAttribute('cargo_idoneidad', $cargo['nombre']);
                $funcionario->setAttribute('cargo_clave_idoneidad', $cargo['clave']);
                $funcionario->setAttribute('cargo_origen_idoneidad', $cargo['origen']);
                $funcionario->setAttribute('solicitud_reemplazo_idoneidad', $cargo['solicitud_reemplazo_id']);
                $funcionario->setAttribute('idoneidad_key', $funcionario->idoneidad_key ?: 'padron:' . $funcionario->id);

                return $funcionario;
            })
            ->filter()
            ->unique(fn (ReemplazoPersonal $funcionario): string => $this->claveFuncionario($funcionario))
            ->sortBy(fn (ReemplazoPersonal $funcionario): string => mb_strtoupper((string) ($funcionario->establecimiento?->nombre_establecimiento ?? '')) . '|' . mb_strtoupper((string) $funcionario->nombre))
            ->values();
    }

    private function funcionariosDesdeSolicitudes(CarbonInterface $fechaInicio, CarbonInterface $fechaTermino): Collection
    {
        if (! Schema::hasTable('solicitudes_reemplazo')
            || ! Schema::hasTable('reemplazos_personal')
            || ! Schema::hasTable('postulant_profiles')
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('areas_desempeno')) {
            return collect();
        }

        return SolicitudReemplazo::query()
            ->with([
                'establecimiento:id,rbd,nombre_establecimiento,comuna',
                'funcionarioTitular:id,estatuto,escalafon',
                'areaDesempeno:id,nombre',
                'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno',
                'contratoPostulante.user:id,rut,nombres,apellido_paterno,apellido_materno',
            ])
            ->whereIn('estado', ['aceptada', 'cerrado', 'cerrada'])
            ->whereRaw('DATE(COALESCE(fecha_inicio_trabajo, fecha_inicio)) <= ?', [$fechaTermino->toDateString()])
            ->whereDate('fecha_termino', '>=', $fechaInicio->toDateString())
            ->orderByRaw('COALESCE(fecha_inicio_trabajo, fecha_inicio) DESC')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (SolicitudReemplazo $solicitud): bool => $solicitud->funcionarioTitular !== null
                && $this->esAaee($solicitud->funcionarioTitular))
            ->map(fn (SolicitudReemplazo $solicitud): ?ReemplazoPersonal => $this->funcionarioDesdeSolicitud($solicitud))
            ->filter()
            ->values();
    }

    private function funcionarioDesdeSolicitud(SolicitudReemplazo $solicitud): ?ReemplazoPersonal
    {
        $perfil = $solicitud->contratoPostulante ?: $solicitud->postulante;
        $usuario = $perfil?->user;
        $rutOriginal = $usuario?->rut ?: $solicitud->rut_reemplazo_normalizado;
        $rut = $this->rutNormalizado($rutOriginal);
        $nombre = trim(implode(' ', array_filter([
            $usuario?->nombres,
            $usuario?->apellido_paterno,
            $usuario?->apellido_materno,
        ])));
        $cargo = trim((string) $solicitud->areaDesempeno?->nombre);

        if ($rut === '' || $nombre === '' || $cargo === '') {
            return null;
        }

        $titular = $solicitud->funcionarioTitular;
        $funcionario = new ReemplazoPersonal();
        $funcionario->forceFill([
            'establecimiento_id' => $solicitud->establecimiento_id,
            'rut' => $this->formatoRut($rutOriginal),
            'nombre' => $nombre,
            'fecha_ingreso' => $solicitud->fecha_inicio_trabajo ?: $solicitud->fecha_inicio,
            'fecha_termino' => $solicitud->fecha_termino,
            'tipocontrato' => 'Reemplazo',
            'estatuto' => $titular?->estatuto,
            'escalafon' => $cargo,
        ]);
        $funcionario->setRelation('establecimiento', $solicitud->establecimiento);
        $funcionario->setAttribute('idoneidad_key', 'solicitud:' . $solicitud->id);
        $funcionario->setAttribute('cargo_idoneidad', $cargo);
        $funcionario->setAttribute('cargo_clave_idoneidad', $this->claveCargo($cargo));
        $funcionario->setAttribute('cargo_origen_idoneidad', 'Solicitud de reemplazo #' . $solicitud->numero_solicitud);
        $funcionario->setAttribute('solicitud_reemplazo_idoneidad', $solicitud->id);

        return $funcionario;
    }

    public function claveFuncionario(ReemplazoPersonal $funcionario): string
    {
        return (int) $funcionario->establecimiento_id . '|' . $this->rutNormalizado($funcionario->rut);
    }

    public function rutNormalizado(?string $rut): string
    {
        return (string) (RutChile::normalize($rut)['rut_body'] ?? strtoupper(preg_replace('/[^0-9K]/i', '', (string) $rut)));
    }

    public function formatoRut(?string $rut): string
    {
        return RutChile::format($rut);
    }

    private function perfilesPorRut(Collection $ruts): Collection
    {
        if ($ruts->isEmpty()
            || ! Schema::hasTable('users')
            || ! Schema::hasTable('postulant_profiles')
            || ! Schema::hasTable('areas_desempeno')) {
            return collect();
        }

        return User::query()
            ->with('postulantProfile.areaDesempeno:id,nombre')
            ->whereIn(DB::raw($this->rutCuerpoSql('rut')), $ruts->all())
            ->get()
            ->filter(fn (User $user): bool => $user->postulantProfile?->areaDesempeno !== null)
            ->keyBy(fn (User $user): string => $this->rutNormalizado($user->rut));
    }

    private function solicitudesReemplazoPorRut(Collection $ruts, CarbonInterface $fechaInicio, CarbonInterface $fechaTermino): Collection
    {
        if ($ruts->isEmpty()
            || ! Schema::hasTable('solicitudes_reemplazo')
            || ! Schema::hasTable('areas_desempeno')) {
            return collect();
        }

        $rutSql = $this->rutCuerpoSql("COALESCE(rut_reemplazo_normalizado, '')");

        return SolicitudReemplazo::query()
            ->with([
                'areaDesempeno:id,nombre',
                'postulante.user:id,rut',
                'contratoPostulante.user:id,rut',
            ])
            ->whereIn('estado', ['aceptada', 'cerrado', 'cerrada'])
            ->whereRaw('DATE(COALESCE(fecha_inicio_trabajo, fecha_inicio)) <= ?', [$fechaTermino->toDateString()])
            ->whereDate('fecha_termino', '>=', $fechaInicio->toDateString())
            ->where(function ($query) use ($ruts, $rutSql): void {
                $query->whereIn(DB::raw($rutSql), $ruts->all())
                    ->orWhereHas('postulante.user', function ($userQuery) use ($ruts): void {
                        $userQuery->whereIn(DB::raw($this->rutCuerpoSql('rut')), $ruts->all());
                    })
                    ->orWhereHas('contratoPostulante.user', function ($userQuery) use ($ruts): void {
                        $userQuery->whereIn(DB::raw($this->rutCuerpoSql('rut')), $ruts->all());
                    });
            })
            ->orderByRaw('COALESCE(fecha_inicio_trabajo, fecha_inicio) DESC')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (SolicitudReemplazo $solicitud): bool => $solicitud->areaDesempeno !== null)
            ->groupBy(fn (SolicitudReemplazo $solicitud): string => $this->rutSolicitudReemplazo($solicitud))
            ->map(fn (Collection $solicitudes): SolicitudReemplazo => $solicitudes->first());
    }

    private function cargosYaSolicitadosPorRut(Collection $ruts): Collection
    {
        if ($ruts->isEmpty()
            || ! Schema::hasTable('idoneidad_psicologica_funcionarios')
            || ! Schema::hasColumn('idoneidad_psicologica_funcionarios', 'cargo_funcion')) {
            return collect();
        }

        return IdoneidadPsicologicaFuncionario::query()
            ->whereIn('rut_normalizado', $ruts->all())
            ->get(['rut_normalizado', 'cargo_clave', 'cargo_funcion'])
            ->groupBy('rut_normalizado')
            ->map(fn (Collection $funcionarios): array => $funcionarios
                ->map(fn (IdoneidadPsicologicaFuncionario $funcionario): string => $funcionario->cargo_clave ?: $this->claveCargo($funcionario->cargo_funcion))
                ->filter()
                ->unique()
                ->values()
                ->all());
    }

    private function resolverCargo(ReemplazoPersonal $funcionario, ?User $perfil, ?SolicitudReemplazo $solicitudReemplazo): ?array
    {
        if ($this->esReemplazoOSuplencia($funcionario)) {
            $nombre = trim((string) $solicitudReemplazo?->areaDesempeno?->nombre);

            return $nombre === '' ? null : [
                'nombre' => $nombre,
                'clave' => $this->claveCargo($nombre),
                'origen' => 'Solicitud de reemplazo #' . $solicitudReemplazo->numero_solicitud,
                'solicitud_reemplazo_id' => $solicitudReemplazo->id,
            ];
        }

        $nombre = trim((string) ($perfil?->postulantProfile?->areaDesempeno?->nombre ?: $funcionario->escalafon));

        return $nombre === '' ? null : [
            'nombre' => $nombre,
            'clave' => $this->claveCargo($nombre),
            'origen' => $perfil?->postulantProfile?->areaDesempeno ? 'Perfil del funcionario' : 'Escalafón del padrón vigente',
            'solicitud_reemplazo_id' => null,
        ];
    }

    private function rutSolicitudReemplazo(SolicitudReemplazo $solicitud): string
    {
        return $this->rutNormalizado(
            $solicitud->contratoPostulante?->user?->rut
                ?: $solicitud->postulante?->user?->rut
                ?: $solicitud->rut_reemplazo_normalizado
        );
    }

    private function esAaee(ReemplazoPersonal $funcionario): bool
    {
        $texto = $this->normalizarTexto($funcionario->estatuto . ' ' . $funcionario->escalafon);

        return str_contains($texto, 'AAEE')
            || str_contains($texto, 'A A E E')
            || str_contains($texto, 'ASISTENTE DE LA EDUCACION')
            || str_contains($texto, 'ASISTENTES DE LA EDUCACION')
            || str_contains($texto, 'ASISTENTE')
            || str_contains($texto, 'PARADOCENTE')
            || str_contains($texto, 'ADMINISTRATIVO')
            || str_contains($texto, 'AUXILIAR');
    }

    private function esReemplazoOSuplencia(ReemplazoPersonal $funcionario): bool
    {
        $contrato = $this->normalizarTexto($funcionario->tipocontrato);

        return str_contains($contrato, 'REEMPLAZ') || str_contains($contrato, 'SUPLEN');
    }

    private function claveCargo(?string $cargo): string
    {
        return preg_replace('/[^A-Z0-9]+/', '_', trim($this->normalizarTexto($cargo)), -1) ?: '';
    }

    private function rutCuerpoSql(string $column): string
    {
        $limpio = "UPPER(REPLACE(REPLACE(REPLACE({$column}, '.', ''), '-', ''), ' ', ''))";

        return "SUBSTR({$limpio}, 1, LENGTH({$limpio}) - 1)";
    }

    private function normalizarTexto(?string $texto): string
    {
        return strtr(mb_strtoupper(trim((string) $texto)), [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);
    }
}
