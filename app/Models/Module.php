<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = ['key', 'name', 'section', 'icon', 'sort'];
}
