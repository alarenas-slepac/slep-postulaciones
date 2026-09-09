<?php

namespace App\Services\Padron;

use App\Models\ReemplazoPersonal;
use App\Models\ReemplazoPersonalBloqueo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** El bloqueo pertenece a la persona; el ID/RBD del registro conservan su origen. */
class PadronBloqueoService
{
    private function rut(?string $rut): string
    {
        return strtoupper(str_replace(['.', '-', ' '], '', trim((string) $rut)));
    }

    private function rutSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(UPPER(TRIM($column)), '.', ''), '-', ''), ' ', '')";
    }

    public function paraFuncionario(ReemplazoPersonal $personal): Builder
    {
        return $this->paraGrupo(collect([$personal]));
    }

    private function paraGrupo(Collection $personal): Builder
    {
        $ids = $personal->pluck('id')->filter()->all();
        $ruts = $personal->map(fn ($p) => $this->rut($p->rut))->filter()->unique()->values()->all();
        $hasRut = Schema::hasColumn('reemplazos_personal_bloqueos', 'rut');
        return ReemplazoPersonalBloqueo::query()->where('activo', true)->where(function ($q) use ($ids, $ruts, $hasRut) {
            $q->whereIn('reemplazo_personal_id', $ids);
            if ($ruts) {
                $q->orWhereIn('reemplazo_personal_id', ReemplazoPersonal::query()->select('id')
                    ->whereIn(DB::raw($this->rutSql('rut')), $ruts));
                if ($hasRut) {
                    $q->orWhereIn(DB::raw($this->rutSql('rut')), $ruts);
                }
            }
        });
    }

    /** Conteo SQL antes de paginar; admite la fuente mensual archivada con el mismo alias. */
    public function filtrarBloqueados(Builder $query): Builder
    {
        $hasRut = Schema::hasColumn('reemplazos_personal_bloqueos', 'rut');
        return $query->whereExists(function ($q) use ($hasRut) {
            $q->selectRaw('1')->from('reemplazos_personal_bloqueos as bloqueo_persona')
                ->where('bloqueo_persona.activo', true)->where(function ($same) use ($hasRut) {
                    $same->whereColumn('bloqueo_persona.reemplazo_personal_id', 'reemplazos_personal.id');
                    $same->orWhereExists(function ($linked) {
                        $linked->selectRaw('1')->from('reemplazos_personal as contrato_bloqueado')
                            ->whereColumn('contrato_bloqueado.id', 'bloqueo_persona.reemplazo_personal_id')
                            ->whereRaw($this->rutSql('reemplazos_personal.rut')." <> ''")
                            ->whereRaw($this->rutSql('contrato_bloqueado.rut').' = '.$this->rutSql('reemplazos_personal.rut'));
                    });
                    if ($hasRut) {
                        $same->orWhere(function ($rut) {
                            $rut->whereRaw($this->rutSql('reemplazos_personal.rut')." <> ''")
                                ->whereRaw($this->rutSql('bloqueo_persona.rut').' = '.$this->rutSql('reemplazos_personal.rut'));
                        });
                    }
                });
        });
    }

    /** Carga acotada a la página; sin caché persistente ni una consulta por funcionario. */
    public function cargar(Collection $personal): void
    {
        if ($personal->isEmpty()) { return; }
        $blocks = $this->paraGrupo($personal)->orderByDesc('id')->get();
        $linked = ReemplazoPersonal::query()->whereIn('id', $blocks->pluck('reemplazo_personal_id'))
            ->pluck('rut', 'id');
        $porId = []; $porRut = [];
        foreach ($blocks as $block) {
            $porId[$block->reemplazo_personal_id] ??= $block;
            foreach ([$block->rut, $linked[$block->reemplazo_personal_id] ?? null] as $raw) {
                $rut = $this->rut($raw);
                if ($rut !== '') { $porRut[$rut] ??= $block; }
            }
        }
        foreach ($personal as $row) {
            $block = $porRut[$this->rut($row->rut)] ?? $porId[$row->id] ?? null;
            $row->setRelation('bloqueoFuncionario', $block);
            // Compatibilidad con la presentación existente; no cambia la FK del bloqueo.
            $row->setRelation('bloqueoActivo', $block);
        }
    }

    public function bloqueado(ReemplazoPersonal $personal): bool
    {
        if (! $personal->relationLoaded('bloqueoFuncionario')) { $this->cargar(collect([$personal])); }
        return $personal->getRelation('bloqueoFuncionario') !== null;
    }
}
