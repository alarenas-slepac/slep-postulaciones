<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class CentroOperacionesTicket extends Model
{
    protected $table = 'centro_operaciones_tickets';

    protected $fillable = [
        'numero',
        'codigo_validacion',
        'documento_hash',
        'documento_emitido_en',
        'incidencia_id',
        'configuracion_id',
        'unidad_departamento',
        'subdireccion_dependencia',
        'responsable_funcionario_ac_id',
        'segunda_subdireccion_responsable',
        'segundo_responsable_funcionario_ac_id',
        'creado_por_id',
        'vence_en',
        'estado',
        'notificado_responsable_en',
        'escalado_en',
        'resuelto_en',
        'resuelto_por_id',
        'resolucion',
    ];

    protected function casts(): array
    {
        return [
            'vence_en' => 'datetime',
            'notificado_responsable_en' => 'datetime',
            'escalado_en' => 'datetime',
            'resuelto_en' => 'datetime',
            'documento_emitido_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CentroOperacionesTicket $ticket) {
            if (Schema::hasColumn($ticket->getTable(), 'codigo_validacion') && ! $ticket->codigo_validacion) {
                $ticket->codigo_validacion = 'TCO-'.strtoupper(bin2hex(random_bytes(10)));
            }
        });
    }

    public function incidencia(): BelongsTo { return $this->belongsTo(CentroOperacionesIncidencia::class, 'incidencia_id'); }
    public function configuracion(): BelongsTo { return $this->belongsTo(CentroOperacionesIncidenteConfiguracion::class, 'configuracion_id'); }
    public function responsable(): BelongsTo { return $this->belongsTo(FuncionarioAcAutorizado::class, 'responsable_funcionario_ac_id'); }
    public function segundoResponsable(): BelongsTo { return $this->belongsTo(FuncionarioAcAutorizado::class, 'segundo_responsable_funcionario_ac_id'); }
    public function creadoPor(): BelongsTo { return $this->belongsTo(User::class, 'creado_por_id')->withTrashed(); }
    public function resueltoPor(): BelongsTo { return $this->belongsTo(User::class, 'resuelto_por_id')->withTrashed(); }
    public function firmaResolucion(): HasOne { return $this->hasOne(CentroOperacionesTicketFirma::class, 'ticket_id'); }
    public function imagenes(): HasMany { return $this->hasMany(CentroOperacionesTicketImagen::class, 'ticket_id')->oldest(); }
}
