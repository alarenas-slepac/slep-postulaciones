<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenTrabajoCorrelativo extends Model
{
    protected $table = 'orden_trabajo_correlativos';

    public const UPDATED_AT = null;

    protected $fillable = ['origen', 'origen_id'];

    public const REEMPLAZO = 'reemplazo';

    public const PRORROGA = 'prorroga';

    public static function reservar(string $origen, int $id): self
    {
        return self::query()->firstOrCreate(['origen' => $origen, 'origen_id' => $id]);
    }
}
