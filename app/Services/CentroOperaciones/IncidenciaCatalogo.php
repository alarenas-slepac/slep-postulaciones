<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesIncidenteConfiguracion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class IncidenciaCatalogo
{
    /** @var Collection<string, array<string, mixed>>|null */
    private ?Collection $catalogo = null;

    /** @return Collection<string, array<string, mixed>> */
    public function todos(): Collection
    {
        if ($this->catalogo !== null) {
            return $this->catalogo;
        }

        $catalogo = collect(config('centro_operaciones.incidencias', []));

        if (! Schema::hasTable('centro_operaciones_incidente_configuraciones')
            || ! Schema::hasColumn('centro_operaciones_incidente_configuraciones', 'nombre')) {
            return $this->catalogo = $catalogo;
        }

        CentroOperacionesIncidenteConfiguracion::query()
            ->orderBy('nombre')
            ->get()
            ->each(function (CentroOperacionesIncidenteConfiguracion $configuracion) use ($catalogo) {
                $base = $catalogo->get($configuracion->tipo, []);
                $catalogo->put($configuracion->tipo, array_merge($base, [
                    'label' => $configuracion->nombre
                        ?: ($base['label'] ?? Str::headline($configuracion->tipo)),
                    'severity' => $configuracion->severidad
                        ?: ($base['severity'] ?? 'alerta'),
                    'active' => $configuracion->activo,
                ]));
            });

        return $this->catalogo = $catalogo;
    }

    /** @return Collection<string, array<string, mixed>> */
    public function activos(): Collection
    {
        return $this->todos()->reject(fn (array $incidencia) =>
            (bool) ($incidencia['automatic'] ?? false)
            || (array_key_exists('active', $incidencia) && ! $incidencia['active'])
        );
    }

    public function nombre(string $tipo): string
    {
        return (string) data_get($this->todos()->get($tipo), 'label', Str::headline($tipo));
    }

    public function severidad(string $tipo): string
    {
        return (string) data_get($this->todos()->get($tipo), 'severity', 'alerta');
    }
}
