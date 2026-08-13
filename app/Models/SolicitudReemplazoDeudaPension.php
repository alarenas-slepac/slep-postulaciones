<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReemplazoDeudaPension extends Model
{
    public const ESTADO_PENDIENTE_DOCUMENTOS = 'pendiente_documentos';
    public const ESTADO_PENDIENTE_POSTULANTE = 'pendiente_postulante';
    public const ESTADO_PENDIENTE_SLEP = 'pendiente_slep';
    public const ESTADO_PENDIENTE_DECLARACION = 'pendiente_declaracion';
    public const ESTADO_LISTO_ENVIO = 'listo_envio';
    public const ESTADO_ENVIADO = 'enviado_remuneraciones';

    protected $table = 'solicitud_reemplazo_deudas_pension';

    protected $fillable = [
        'solicitud_reemplazo_id',
        'postulant_profile_id',
        'estado',
        'activado_por_user_id',
        'activado_at',
        'certificado_deuda_path',
        'certificado_deuda_nombre_original',
        'certificado_deuda_mime',
        'certificado_deuda_size',
        'certificado_subido_por_user_id',
        'certificado_subido_at',
        'resolucion_path',
        'resolucion_nombre_original',
        'resolucion_mime',
        'resolucion_size',
        'valor_cuota_alimentaria',
        'observacion_postulante',
        'resolucion_subida_por_user_id',
        'resolucion_subida_at',
        'correo_destino',
        'enviado_por_user_id',
        'enviado_at',
    ];

    protected function casts(): array
    {
        return [
            'activado_at' => 'datetime',
            'certificado_deuda_size' => 'integer',
            'certificado_subido_at' => 'datetime',
            'resolucion_size' => 'integer',
            'valor_cuota_alimentaria' => 'decimal:2',
            'resolucion_subida_at' => 'datetime',
            'enviado_at' => 'datetime',
        ];
    }

    public static function estados(): array
    {
        return [
            self::ESTADO_PENDIENTE_DOCUMENTOS => 'Pendiente de documentos',
            self::ESTADO_PENDIENTE_POSTULANTE => 'Pendiente del postulante',
            self::ESTADO_PENDIENTE_SLEP => 'Pendiente de certificado SLEP',
            self::ESTADO_PENDIENTE_DECLARACION => 'Pendiente de declaración jurada',
            self::ESTADO_LISTO_ENVIO => 'Listo para enviar',
            self::ESTADO_ENVIADO => 'Enviado a Remuneraciones',
        ];
    }

    public function solicitud()
    {
        return $this->belongsTo(SolicitudReemplazo::class, 'solicitud_reemplazo_id');
    }

    public function postulante()
    {
        return $this->belongsTo(PostulantProfile::class, 'postulant_profile_id');
    }

    public function activadoPor()
    {
        return $this->belongsTo(User::class, 'activado_por_user_id');
    }

    public function certificadoSubidoPor()
    {
        return $this->belongsTo(User::class, 'certificado_subido_por_user_id');
    }

    public function resolucionSubidaPor()
    {
        return $this->belongsTo(User::class, 'resolucion_subida_por_user_id');
    }

    public function enviadoPor()
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }

    public function declaracionCargoPublicoActual(): ?UserDocument
    {
        $user = $this->postulante?->user;

        if (! $user) {
            return null;
        }

        if ($user->relationLoaded('documents')) {
            return $user->documents
                ->filter(fn (UserDocument $documento) => $documento->type?->slug === 'declaracion_cargo_publico')
                ->sortByDesc('updated_at')
                ->first();
        }

        return $user->documents()
            ->whereHas('type', fn ($query) => $query->where('slug', 'declaracion_cargo_publico'))
            ->latest('updated_at')
            ->first();
    }

    public function estadoFlujo(?UserDocument $declaracion = null): string
    {
        $declaracion ??= $this->declaracionCargoPublicoActual();

        if (! $this->certificado_deuda_path && ! $this->resolucion_path) {
            return self::ESTADO_PENDIENTE_DOCUMENTOS;
        }

        if (! $this->resolucion_path || $this->valor_cuota_alimentaria === null || (float) $this->valor_cuota_alimentaria <= 0) {
            return self::ESTADO_PENDIENTE_POSTULANTE;
        }

        if (! $this->certificado_deuda_path) {
            return self::ESTADO_PENDIENTE_SLEP;
        }

        if (! $declaracion?->path) {
            return self::ESTADO_PENDIENTE_DECLARACION;
        }

        if ($this->enviado_at) {
            $hayDocumentoNuevo = collect([
                $this->certificado_subido_at,
                $this->resolucion_subida_at,
                $declaracion->updated_at,
            ])->filter()->contains(fn ($fecha) => $fecha->gt($this->enviado_at));

            if (! $hayDocumentoNuevo) {
                return self::ESTADO_ENVIADO;
            }
        }

        return self::ESTADO_LISTO_ENVIO;
    }

    public function getEstadoFlujoLabelAttribute(): string
    {
        $estado = $this->estadoFlujo();

        return self::estados()[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
    }

    public function sincronizarEstado(?UserDocument $declaracion = null): string
    {
        $estado = $this->estadoFlujo($declaracion);

        if ($this->estado !== $estado) {
            $this->forceFill(['estado' => $estado])->save();
        }

        return $estado;
    }
}
