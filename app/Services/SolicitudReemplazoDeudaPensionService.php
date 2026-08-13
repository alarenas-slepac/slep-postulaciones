<?php

namespace App\Services;

use App\Mail\DeudaPensionAlimentosRemuneracionesMail;
use App\Models\SolicitudReemplazoConfiguracion;
use App\Models\SolicitudReemplazoDeudaPension;
use App\Models\User;
use App\Support\NotificationAudit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SolicitudReemplazoDeudaPensionService
{
    public function enviarARemuneraciones(SolicitudReemplazoDeudaPension $deuda, User $usuario): void
    {
        $deuda->loadMissing(['solicitud.establecimiento', 'postulante.user']);
        $declaracion = $deuda->declaracionCargoPublicoActual();
        $errores = [];

        if ($deuda->estadoFlujo($declaracion) === SolicitudReemplazoDeudaPension::ESTADO_ENVIADO) {
            $errores[] = 'El expediente vigente ya fue enviado a Remuneraciones.';
        }

        if (! $deuda->certificado_deuda_path || ! Storage::disk('local')->exists($deuda->certificado_deuda_path)) {
            $errores[] = 'Falta el certificado de deuda de pensión de alimentos cargado por SLEP.';
        }
        if (! $deuda->resolucion_path || ! Storage::disk('local')->exists($deuda->resolucion_path)) {
            $errores[] = 'Falta la resolución o dictamen actualizado cargado por el postulante.';
        }
        if ($deuda->valor_cuota_alimentaria === null || (float) $deuda->valor_cuota_alimentaria <= 0) {
            $errores[] = 'Falta informar un valor válido de cuota alimentaria.';
        }
        if (! $declaracion?->path || ! Storage::disk('public')->exists($declaracion->path)) {
            $errores[] = 'Falta la declaración jurada vigente para ejercer cargo público.';
        }

        $correo = SolicitudReemplazoConfiguracion::correoEncargadaRemuneracionesDeudaPension();
        if (! $correo) {
            $errores[] = 'El correo de la encargada de remuneraciones no está configurado o se encuentra deshabilitado.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages(['envio' => $errores]);
        }

        $numero = $deuda->solicitud?->numero_solicitud ?: $deuda->solicitud_reemplazo_id;
        $asunto = "Antecedentes de deuda de pensión de alimentos – Solicitud {$numero}";

        NotificationAudit::sendMail(
            $correo,
            new DeudaPensionAlimentosRemuneracionesMail($deuda, $declaracion),
            [
                'event_key' => 'solicitud_reemplazo.deuda_pension.enviada_remuneraciones',
                'description' => 'Envío de antecedentes de postulante con deuda de pensión de alimentos',
                'subject' => $asunto,
                'recipient_name' => 'Encargada de remuneraciones',
                'related' => $deuda,
                'triggered_by_user_id' => $usuario->id,
                'context' => [
                    'solicitud_reemplazo_id' => $deuda->solicitud_reemplazo_id,
                    'numero_solicitud' => $numero,
                    'postulant_profile_id' => $deuda->postulant_profile_id,
                    'declaracion_user_document_id' => $declaracion->id,
                ],
            ]
        );

        $deuda->forceFill([
            'estado' => SolicitudReemplazoDeudaPension::ESTADO_ENVIADO,
            'correo_destino' => $correo,
            'enviado_por_user_id' => $usuario->id,
            'enviado_at' => now(),
        ])->save();
    }
}
