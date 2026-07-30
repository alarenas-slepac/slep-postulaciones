<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Commune extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'region_code',
    ];

    // Relación con usuarios (pivot commune_user)
    public function users()
    {
        return $this->belongsToMany(User::class, 'commune_user', 'commune_id', 'user_id');
    }
}
