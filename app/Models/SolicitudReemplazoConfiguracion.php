<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReemplazoConfiguracion extends Model
{
    public const CORREO_AUTORIZACIONES_DOCENTES = 'correo_autorizaciones_docentes';

    protected $table = 'solicitud_reemplazo_configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'nombre',
        'descripcion',
        'activo',
        'updated_by',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function actualizadoPor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function correoAutorizacionesDocentes(): ?string
    {
        $correo = static::query()
            ->where('clave', self::CORREO_AUTORIZACIONES_DOCENTES)
            ->where('activo', true)
            ->value('valor');

        $correo = trim((string) $correo);

        return filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : null;
    }
}
