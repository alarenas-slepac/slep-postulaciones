<?php

namespace App\Models;

use App\Support\RutChile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdoneidadPsicologicaFuncionario extends Model
{
    public const ESTADOS = [
        'solicitado' => 'Solicitado',
        'aceptado' => 'Aceptado',
        'rechazado' => 'Rechazado',
    ];

    protected $table = 'idoneidad_psicologica_funcionarios';

    protected $fillable = [
        'solicitud_id',
        'reemplazo_personal_id',
        'establecimiento_id',
        'rbd',
        'establecimiento_nombre',
        'comuna',
        'rut',
        'rut_normalizado',
        'nombre',
        'estamento',
        'cargo_funcion',
        'cargo_clave',
        'cargo_origen',
        'solicitud_reemplazo_id',
        'tipo_contrato',
        'fecha_ingreso',
        'fecha_termino',
        'estado',
        'observacion_resultado',
        'resultado_registrado_por',
        'resultado_registrado_at',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_termino' => 'date',
        'resultado_registrado_at' => 'datetime',
    ];

    public function getRutAttribute(?string $value): string
    {
        return RutChile::format($value);
    }

    public function getComunaAttribute(?string $value): ?string
    {
        $comuna = trim((string) $value);
        $normalizada = mb_strtoupper($comuna);

        return match ($normalizada) {
            'SAN PEDRO', 'SAN PEDRO DE LA PAZ' => 'San Pedro de la Paz',
            'STA. JUANA', 'STA JUANA', 'SANTA JUANA' => 'Santa Juana',
            default => $comuna !== '' ? $comuna : null,
        };
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(IdoneidadPsicologicaSolicitud::class, 'solicitud_id');
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function funcionarioPadron(): BelongsTo
    {
        return $this->belongsTo(ReemplazoPersonal::class, 'reemplazo_personal_id');
    }

    public function resultadoRegistradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resultado_registrado_por');
    }
}
