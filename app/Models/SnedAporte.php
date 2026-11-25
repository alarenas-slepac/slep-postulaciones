<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnedAporte extends Model
{
    protected $table = 'sned_aportes';

    protected $guarded = [];

    protected $casts = [
        'incentivo' => 'array',
        'docentes' => 'integer',
        'aaee' => 'integer',
    ];

    public function fondoIncentivo(): int
    {
        return intdiv($this->docentes + 5, 10);
    }
}
