<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/** Identidad técnica independiente: no tiene cuenta ni roles de acceso al SGA. */
class FuncionarioApiIntegration extends Authenticatable
{
    use HasApiTokens;

    public const ABILITY = 'funcionarios:consultar';

    protected $fillable = ['name', 'enabled', 'requests_per_minute'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'requests_per_minute' => 'integer'];
    }
}
