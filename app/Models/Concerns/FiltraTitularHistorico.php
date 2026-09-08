<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/** Filtros explícitos: no altera relaciones ni aplica restricciones globales. */
trait FiltraTitularHistorico
{
    public function scopeTitularCoincide(Builder $query, string $patron): Builder
    {
        return $this->filtrarTitularHistorico($query, function (Builder $source, Closure $column) use ($patron) {
            $source->where(function (Builder $fields) use ($column, $patron) {
                $fields->whereRaw('LOWER('.$column('rut').') LIKE ?', [mb_strtolower($patron)])
                    ->orWhereRaw('LOWER('.$column('nombre').') LIKE ?', [mb_strtolower($patron)]);
            });
        });
    }

    public function scopeTitularDocente(Builder $query): Builder
    {
        return $this->filtrarTitularHistorico($query, function (Builder $source, Closure $column) {
            $estatuto = 'UPPER(TRIM('.$column('estatuto').'))';
            $source->where(function (Builder $fields) use ($estatuto) {
                $fields->whereRaw($estatuto.' IN (?, ?, ?)', ['DOCENTE', 'PROFESOR', 'PROFESORA'])
                    ->orWhereRaw($estatuto.' LIKE ?', ['%DOC%']);
            });
        });
    }

    public function scopeTitularRutComparable(Builder $query, string $rut): Builder
    {
        $rut = mb_strtoupper(preg_replace('/[.\-\s]+/u', '', $rut));
        $normalizar = static fn (string $column): string => "UPPER(REPLACE(REPLACE(REPLACE($column, '.', ''), '-', ''), ' ', ''))";

        return $this->filtrarTitularHistorico(
            $query,
            fn (Builder $source, Closure $column) => $source->whereRaw($normalizar($column('rut')).' = ?', [$rut]),
            // El RUT alternativo del documento solo se usa cuando NO hay copia.
            function (Builder $legacy) use ($normalizar, $rut) {
                $column = $legacy->getQuery()->getGrammar()->wrap($this->qualifyColumn('rut_titular_normalizado'));
                $legacy->orWhereRaw($normalizar($column).' = ?', [$rut]);
            },
        );
    }

    private function filtrarTitularHistorico(Builder $query, Closure $predicate, ?Closure $legacyExtra = null): Builder
    {
        $legacy = function (Builder $branch) use ($predicate, $legacyExtra) {
            $branch->where(function (Builder $alternatives) use ($predicate, $legacyExtra) {
                $alternatives->whereHas('funcionarioTitular', function (Builder $personal) use ($predicate) {
                    $grammar = $personal->getQuery()->getGrammar();
                    $predicate($personal, fn (string $field): string => $grammar->wrap($personal->qualifyColumn($field)));
                });
                if ($legacyExtra !== null) {
                    $legacyExtra($alternatives);
                }
            });
        };

        if (! $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'padron_personal_snapshot')) {
            return $query->where($legacy);
        }

        $snapshot = $this->qualifyColumn('padron_personal_snapshot');
        return $query->where(function (Builder $sources) use ($snapshot, $predicate, $legacy) {
            $sources->where(function (Builder $historical) use ($snapshot, $predicate) {
                $historical->whereNotNull($snapshot)
                    ->where($snapshot.'->version', 1)
                    ->whereColumn($snapshot.'->personal->id', $this->qualifyColumn('reemplazo_personal_id'));
                $grammar = $historical->getQuery()->getGrammar();
                $predicate($historical, fn (string $field): string => $grammar->wrap($snapshot.'->personal->'.$field));
            })->orWhere(function (Builder $current) use ($snapshot, $legacy) {
                $current->whereNull($snapshot);
                $legacy($current);
            });
        });
    }
}
