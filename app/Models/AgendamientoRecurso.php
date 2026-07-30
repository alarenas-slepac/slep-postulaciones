<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendamientoRecurso extends Model
{
    use HasFactory;

    protected $table = 'agendamientos_recursos';

    protected $fillable = [
        'recurso_catalogo_id',
        'tipo_recurso',
        'titulo',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'solicitante_user_id',
        'solicitado_by',
        'solicitante_nombre',
        'solicitante_email',
        'unidad',
        'lugar_uso',
        'cantidad_participantes',
        'motivo',
        'requiere_proyector',
        'requiere_apoyo_tecnico',
        'responsable_retiro',
        'responsable_devolucion',
        'estado',
        'observaciones',
        'created_by',
        'updated_by',
        'anulado_by',
        'anulado_at',
        'motivo_anulacion',
        'aprobado_by',
        'aprobado_at',
        'rechazado_by',
        'rechazado_at',
        'motivo_rechazo',
    ];

    protected $casts = [
        'fecha' => 'date',
        'requiere_proyector' => 'boolean',
        'requiere_apoyo_tecnico' => 'boolean',
        'cantidad_participantes' => 'integer',
        'anulado_at' => 'datetime',
        'aprobado_at' => 'datetime',
        'rechazado_at' => 'datetime',
    ];

    public const RECURSO_PROYECTOR = 'proyector';
    public const RECURSO_SALA_GDP = 'sala_gdp';

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_VIGENTE = 'vigente';
    public const ESTADO_APROBADO = 'aprobado';
    public const ESTADO_RECHAZADO = 'rechazado';
    public const ESTADO_ANULADO = 'anulado';
    public const ESTADO_FINALIZADO = 'finalizado';

    public static function recursos(): array
    {
        return [
            self::RECURSO_PROYECTOR => 'Proyector',
            self::RECURSO_SALA_GDP => 'Sala de Reuniones GDP',
        ];
    }

    public static function estados(): array
    {
        return [
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_VIGENTE => 'Vigente',
            self::ESTADO_APROBADO => 'Aprobado',
            self::ESTADO_RECHAZADO => 'Rechazado',
            self::ESTADO_ANULADO => 'Anulado',
            self::ESTADO_FINALIZADO => 'Finalizado',
        ];
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(AgendamientoRecursoCatalogo::class, 'recurso_catalogo_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_by');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_by');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_by');
    }

    public function rechazador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_by');
    }

    public function getTipoRecursoLabelAttribute(): string
    {
        return $this->recurso?->nombre
            ?? self::recursos()[$this->tipo_recurso]
            ?? ucfirst((string) $this->tipo_recurso);
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::estados()[$this->estado] ?? ucfirst((string) $this->estado);
    }

    public function getHorarioAttribute(): string
    {
        return substr((string) $this->hora_inicio, 0, 5) . ' - ' . substr((string) $this->hora_termino, 0, 5);
    }

    public function getBadgeClassAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_PENDIENTE => 'warning',
            self::ESTADO_RECHAZADO => 'danger',
            self::ESTADO_ANULADO => 'secondary',
            self::ESTADO_APROBADO => 'success',
            default => ($this->recurso?->tipo === AgendamientoRecursoCatalogo::TIPO_SALA ? 'success' : 'primary'),
        };
    }

    public function estaActivoParaDisponibilidad(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_VIGENTE, self::ESTADO_APROBADO], true);
    }
}
