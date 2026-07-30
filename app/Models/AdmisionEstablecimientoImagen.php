<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AdmisionEstablecimientoImagen extends Model
{
    protected $table = 'admision_establecimiento_imagenes';

    protected $fillable = [
        'admision_establecimiento_id',
        'imagen_path',
        'original_name',
        'mime_type',
        'tamano_bytes',
        'texto_alternativo',
        'titulo',
        'descripcion',
        'es_portada',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'es_portada' => 'boolean',
            'orden' => 'integer',
            'tamano_bytes' => 'integer',
        ];
    }

    public function admisionEstablecimiento(): BelongsTo
    {
        return $this->belongsTo(AdmisionEstablecimiento::class);
    }

    public function url(): string
    {
        return Storage::disk(config('admision.media_disk', 'public'))->url($this->imagen_path);
    }
}
