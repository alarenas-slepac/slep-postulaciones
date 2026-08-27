<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitaVotacion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const EN_TRASLADO = 'en_traslado';

    public const EN_VOTACION = 'en_votacion';

    public const FINALIZADA = 'finalizada';

    public const SUSPENDIDA = 'suspendida';

    public const ESTADOS = [self::PENDIENTE, self::EN_TRASLADO, self::EN_VOTACION, self::FINALIZADA, self::SUSPENDIDA];

    protected $table = 'visitas_votacion';

    protected $fillable = ['ruta_votacion_id', 'estado', 'inicio_traslado_at', 'inicio_votacion_at', 'fin_votacion_at', 'iniciada_por', 'finalizada_por', 'observacion'];

    protected function casts(): array
    {
        return ['inicio_traslado_at' => 'datetime', 'inicio_votacion_at' => 'datetime', 'fin_votacion_at' => 'datetime'];
    }

    public function ruta(): BelongsTo
    {
        return $this->belongsTo(RutaVotacion::class, 'ruta_votacion_id');
    }
}
