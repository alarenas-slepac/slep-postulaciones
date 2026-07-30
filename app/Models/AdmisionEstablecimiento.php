<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdmisionEstablecimiento extends Model
{
    public const ESTADO_BORRADOR = 'borrador';
    public const ESTADO_PUBLICADO = 'publicado';

    protected $table = 'admision_establecimientos';

    protected $fillable = [
        'establecimiento_id',
        'slug',
        'estado',
        'destacado',
        'orden',
        'sello_educativo',
        'descripcion_corta',
        'director_nombre',
        'director_resena',
        'director_foto_path',
        'logo_path',
        'sitio_web_url',
        'facebook_url',
        'instagram_url',
        'direccion_publica',
        'sector',
        'telefono_publico',
        'email_publico',
        'publicado_at',
        'publicado_por',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'destacado' => 'boolean',
            'orden' => 'integer',
            'publicado_at' => 'datetime',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(AdmisionEstablecimientoImagen::class)
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function portada(): HasOne
    {
        return $this->hasOne(AdmisionEstablecimientoImagen::class)
            ->where('es_portada', true)
            ->orderBy('orden')
            ->orderBy('id');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query
            ->where('estado', self::ESTADO_PUBLICADO)
            ->whereNotNull('publicado_at');
    }

    public function isPublicado(): bool
    {
        return $this->estado === self::ESTADO_PUBLICADO && $this->publicado_at !== null;
    }

    public function logoUrl(): ?string
    {
        return $this->mediaUrl($this->logo_path);
    }

    public function directorFotoUrl(): ?string
    {
        return $this->mediaUrl($this->director_foto_path);
    }

    public static function slugBase(Establecimiento $establecimiento): string
    {
        $rbd = trim((string) $establecimiento->rbd);
        $base = Str::slug(trim((string) $establecimiento->nombre_establecimiento) . '-' . $rbd);

        return $base !== '' ? $base : 'establecimiento-' . $establecimiento->id;
    }

    public static function uniqueSlugFor(Establecimiento $establecimiento, ?int $ignoreId = null): string
    {
        $base = static::slugBase($establecimiento);
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '<>', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('admision.media_disk', 'public'))->url($path);
    }
}
