<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TituloCatalogo extends Model
{
    protected $table = 'titulos_catalogo';
    protected $fillable = ['nombre'];
}
