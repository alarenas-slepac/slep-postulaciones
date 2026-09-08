<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Establecimiento;

class ReemplazoPersonal extends Model
{
    protected $table = 'reemplazos_personal';

    protected $fillable = [
        'establecimiento_id',
        'rbd',
        'rut',
        'nombre',
        'fecha_nacimiento',
        'fecha_ingreso',
        'fecha_antiguedad',
        'fecha_termino',
        'tipocontrato',
        'financiamiento',
        'estatuto',
        'escalafon',
        'anio',
        'mes',
        'jornada',
        'jornada_basica',
        'jornada_media',
        'bienios',
        'tramo',
        'row_hash',
        'source_filename',
        'created_by',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_antiguedad' => 'date',
        'fecha_termino' => 'date',
        'anio' => 'integer',
        'mes' => 'integer',
        'rbd' => 'integer',
        'jornada' => 'integer',
        'jornada_basica' => 'integer',
        'jornada_media' => 'integer',
        'bienios' => 'integer',
        'vigente' => 'boolean',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function bloqueoActivo(): HasOne
    {
        return $this->hasOne(ReemplazoPersonalBloqueo::class, 'reemplazo_personal_id')
            ->where('activo', true)
            ->latestOfMany();
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function scopeDelEstablecimiento($q, int $establecimientoId)
    {
        return $q->where('establecimiento_id', $establecimientoId);
    }

    /** Alcance explícito: nunca filtra relaciones o consultas históricas globales. */
    public function scopePadronVigente($q, ?int $anio = null)
    {
        $table = $this->getTable();
        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'vigente')) {
            $q->where($table.'.vigente', true);
        }
        if ($anio !== null) {
            $q->where($table.'.anio', $anio);
        }
        $q->whereRaw($table.'.anio * 100 + '.$table.'.mes = (SELECT MAX(p.anio * 100 + p.mes) FROM reemplazos_personal p WHERE p.establecimiento_id = '.$table.'.establecimiento_id'.($anio !== null ? ' AND p.anio = ?' : '').')', $anio !== null ? [$anio] : []);
        if (\Illuminate\Support\Facades\Schema::hasColumn('padron_revisiones', 'aplicada_at')) {
            $q->whereRaw($table.'.anio * 100 + '.$table.'.mes >= COALESCE((SELECT MAX(r.anio * 100 + r.mes) FROM padron_revisiones r WHERE r.aplicada_at IS NOT NULL'.($anio !== null ? ' AND r.anio = ?' : '').'), 0)', $anio !== null ? [$anio] : []);
        }
        return $q;
    }

    public function scopeSinReemplazoSuplencia($q)
    {
        return $q->whereRaw("UPPER(COALESCE(tipocontrato, '')) NOT LIKE ?", ['%REEMPLAZ%'])
            ->whereRaw("UPPER(COALESCE(tipocontrato, '')) NOT LIKE ?", ['%SUPLEN%']);
    }

    public function scopeFuncionarios($q)
    {
        return $q->where('tipo', 'FUNCIONARIO'); // ajusta
    }

    public function scopeReemplazosActivos($q)
    {
        // ejemplo: ajusta a tus columnas reales
        return $q->where('tipo', 'REEMPLAZO')
            ->where('activo', 1);
    }
}
