<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudReemplazoProrroga extends Model
{
    protected $table = 'solicitud_reemplazo_prorrogas';

    public const ESTAMENTOS = ['docente' => 'Docentes', 'aaee' => 'AAEE', 'junji' => 'JUNJI'];

    protected $fillable = [
        'anio_diciembre', 'estamento', 'rut', 'vigencia_desde', 'vigencia_hasta', 'antecedentes',
        'numero_resolucion', 'fecha_resolucion', 'resolucion_path', 'resolucion_nombre',
        'observacion', 'registrada_por_id',
        'orden_trabajo_pdf_path', 'orden_trabajo_emitida_por_id', 'orden_trabajo_emitida_at',
    ];

    protected function casts(): array
    {
        return [
            'anio_diciembre' => 'integer',
            'vigencia_desde' => 'date',
            'vigencia_hasta' => 'date',
            'fecha_resolucion' => 'date',
            'antecedentes' => 'array',
            'orden_trabajo_emitida_at' => 'datetime',
        ];
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_id');
    }

    public function correlativoOrdenTrabajo(): HasOne
    {
        return $this->hasOne(OrdenTrabajoCorrelativo::class, 'origen_id')
            ->where('origen', OrdenTrabajoCorrelativo::PRORROGA);
    }
}
