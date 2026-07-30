<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionFuncionario extends Model
{
    use HasFactory;

    protected $table = 'liquidaciones_funcionarios';

    protected $fillable = [
        'liquidacion_carga_id',
        'rut_original',
        'rut_normalizado',
        'nombre',
        'mes',
        'anio',
        'dominio',
        'pagina_origen',
        'archivo_pdf_path',
        'es_reemplazo',
        'tipo_contrato_detectado',
        'fecha_inicio',
        'fecha_termino',
        'texto_detectado_resumen',
    ];

    protected $casts = [
        'mes' => 'integer',
        'anio' => 'integer',
        'pagina_origen' => 'integer',
        'es_reemplazo' => 'boolean',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(LiquidacionCarga::class, 'liquidacion_carga_id');
    }

    public function mesNombre(): string
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ][$this->mes] ?? (string) $this->mes;
    }
}
