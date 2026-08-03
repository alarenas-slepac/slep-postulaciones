<?php

namespace App\Support\Messaging;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EstablecimientoDirectory
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function items(array $filters = []): Collection
    {
        if (! self::columnsAvailable()) {
            return collect();
        }

        $search = trim((string) ($filters['q'] ?? ''));
        $comuna = trim((string) ($filters['comuna'] ?? ''));

        return Establecimiento::query()
            ->select([
                'id',
                'rbd',
                'nombre_establecimiento',
                'comuna',
                'director_nombre',
                'director_contacto',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(' ', '%', $search) . '%';

                $query->where(function ($query) use ($like) {
                    $query->where('nombre_establecimiento', 'like', $like)
                        ->orWhere('rbd', 'like', $like)
                        ->orWhere('director_nombre', 'like', $like)
                        ->orWhere('director_contacto', 'like', $like);
                });
            })
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get()
            ->map(function (Establecimiento $establecimiento) {
                $name = trim((string) $establecimiento->nombre_establecimiento);

                return [
                    'id' => (int) $establecimiento->id,
                    'rbd' => $establecimiento->rbd,
                    'name' => $name !== '' ? $name : 'Establecimiento sin nombre',
                    'comuna' => trim((string) $establecimiento->comuna),
                    'director_nombre' => trim((string) $establecimiento->director_nombre),
                    'director_contacto' => trim((string) $establecimiento->director_contacto),
                    'initials' => self::initials($name),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function comunas(): Collection
    {
        if (! self::columnsAvailable()) {
            return collect();
        }

        return Establecimiento::query()
            ->whereNotNull('comuna')
            ->where('comuna', '<>', '')
            ->distinct()
            ->orderBy('comuna')
            ->pluck('comuna');
    }

    private static function columnsAvailable(): bool
    {
        try {
            return Schema::hasTable('establecimientos')
                && Schema::hasColumns('establecimientos', [
                    'rbd',
                    'nombre_establecimiento',
                    'comuna',
                    'director_nombre',
                    'director_contacto',
                ]);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function initials(string $name): string
    {
        return collect(preg_split('/\s+/', $name) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('') ?: 'EE';
    }
}
