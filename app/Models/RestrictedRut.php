<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestrictedRut extends Model
{
    protected $fillable = [
        'rut_normalized',
        'display_name',
    ];

    public function courtRecord(): HasOne
    {
        return $this->hasOne(RestrictedRutCourtRecord::class);
    }

    public function manualRecord(): HasOne
    {
        return $this->hasOne(RestrictedRutManualRecord::class);
    }

    public function getRutFormattedAttribute(): string
    {
        $rut = strtoupper((string) $this->rut_normalized);
        if ($rut === '' || strlen($rut) < 2) {
            return $rut;
        }

        return substr($rut, 0, -1) . '-' . substr($rut, -1);
    }
}
