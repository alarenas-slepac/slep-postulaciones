<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Models\CometidoFuncionario;
use App\Models\CometidoFuncionarioHistorial;
use App\Models\CometidoFuncionarioInforme;
use App\Models\CometidoFuncionarioRendicion;
use App\Models\CometidoNotificacionConfiguracion;
use App\Models\User;
use App\Mail\CometidoFuncionarioNotificationMail;
use App\Support\Cometidos\CometidoFuncionarioPdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CometidoFuncionarioInformeController extends Controller
{
    public function __construct(private CometidoFuncionarioPdfService $pdfService)
    {
    }

    public function create(Request $request, CometidoFuncionario $cometido)
    {
        $this->autorizarFuncionario($request, $cometido);
        $this->validarEtapaDisponible($cometido);

        $cometido->loadMissing(['establecimiento', 'funcionarioAcAutorizado', 'funcionarioPadron', 'documentos', 'documentosGenerados']);
        $informe = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first() ?: new CometidoFuncionarioInforme([
                'fecha_desde_real' => $cometido->fecha_desde,
                'fecha_hasta_real' => $cometido->fecha_hasta,
                'hora_salida_real' => $cometido->hora_salida,
                'hora_regreso_real' => $cometido->hora_regreso,
            ]);

        return view('tramites.cometidos-funcionarios.informe', compact('cometido', 'informe'));
    }

    public function store(Request $request, CometidoFuncionario $cometido)
    {
        $this->autorizarFuncionario($request, $cometido);
        $this->validarEtapaDisponible($cometido);

        $data = $request->validate([
            'fecha_desde_real' => ['required', 'date'],
            'fecha_hasta_real' => ['required', 'date', 'after_or_equal:fecha_desde_real'],
            'hora_salida_real' => ['required', 'date_format:H:i'],
            'hora_regreso_real' => ['required', 'date_format:H:i'],
            'justificacion_cambio_fechas' => ['nullable', 'string', 'max:5000'],
            'organismos_autoridades_relatores' => ['required', 'string', 'max:5000'],
            'descripcion_actividades_realizadas' => ['required', 'string', 'max:12000'],
            'resultados_obtenidos' => ['required', 'string', 'max:12000'],
            'opiniones_propuestas' => ['required', 'string', 'max:12000'],
        ]);

        $cambioFechasHoras = $this->hayCambioFechasHoras($cometido, $data);
        if ($cambioFechasHoras && trim((string) ($data['justificacion_cambio_fechas'] ?? '')) === '') {
            return back()->withErrors([
                'justificacion_cambio_fechas' => 'Debe justificar los cambios de fechas u horarios respecto de la solicitud original.',
            ])->withInput();
        }

        $requiereNuevoCometido = $this->generaDiferenciaDiasAFavor($cometido, $data);
        $estadoAnterior = $cometido->estado;

        DB::transaction(function () use ($request, $cometido, $data, $requiereNuevoCometido, $estadoAnterior) {
            $informe = CometidoFuncionarioInforme::updateOrCreate(
                ['cometido_funcionario_id' => $cometido->id],
                array_merge($data, [
                    'estado_informe' => 'enviado_pendiente_jefatura',
                    'requiere_nuevo_cometido_diferencia' => $requiereNuevoCometido,
                    'fecha_envio' => now(),
                    'fecha_revision_jefatura' => null,
                    'jefatura_revisora_id' => null,
                    'decision_jefatura' => null,
                    'observacion_jefatura' => null,
                    'user_id_envia' => $request->user()->id,
                    'observacion_sistema' => $requiereNuevoCometido
                        ? 'El informe registra una diferencia de días u horarios a favor del funcionario. Debe evaluarse la generación de un nuevo cometido por la diferencia.'
                        : null,
                ])
            );

            $rendicionEnviada = CometidoFuncionarioRendicion::query()
                ->where('cometido_funcionario_id', $cometido->id)
                ->exists();
            $esInformeReembolso = $cometido->solicita_reembolso && ! $cometido->solicita_viatico;
            $estadoCometido = $esInformeReembolso
                ? ($rendicionEnviada ? 'rendicion_enviada_pendiente_informe' : 'pendiente_rendicion_informe')
                : ($cometido->solicita_viatico && $cometido->solicita_reembolso ? 'en_gestion_paralela' : 'informe_pendiente_jefatura');

            $cometido->update([
                'estado' => $estadoCometido,
                'estado_viatico' => $esInformeReembolso ? $cometido->estado_viatico : 'informe_pendiente_jefatura',
                'estado_reembolso' => $esInformeReembolso ? $estadoCometido : $cometido->estado_reembolso,
            ]);

            $documento = $this->pdfService->generarInformeCometido($cometido->fresh(['funcionarioAcAutorizado', 'establecimiento', 'solicitante']), $informe, $request->user(), $request);

            CometidoFuncionarioHistorial::create([
                'cometido_funcionario_id' => $cometido->id,
                'user_id' => $request->user()->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'informe_pendiente_jefatura',
                'accion' => 'Funcionario envió informe de cometido',
                'observacion' => ($requiereNuevoCometido
                    ? 'El informe requiere evaluar nuevo cometido por diferencia de días u horarios a favor del funcionario.'
                    : 'Informe de cometido enviado a revisión de jefatura.') . ' PDF generado: ' . ($documento->numero_documento ?: 'Informe de cometido') . '.',
            ]);
        });

        $this->notificarJefaturaInforme($cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']));

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', 'Informe de cometido enviado a revisión de jefatura.');
    }


    public function regenerarPdf(Request $request, CometidoFuncionario $cometido)
    {
        $this->autorizarRegeneracionPdf($request, $cometido);

        $informe = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        abort_unless($informe, 404, 'No existe informe de cometido para regenerar.');

        $documento = $this->pdfService->generarInformeCometido(
            $cometido->fresh(['funcionarioAcAutorizado', 'establecimiento', 'solicitante']),
            $informe,
            $request->user(),
            $request
        );

        CometidoFuncionarioHistorial::create([
            'cometido_funcionario_id' => $cometido->id,
            'user_id' => $request->user()->id,
            'estado_anterior' => $cometido->estado,
            'estado_nuevo' => $cometido->estado,
            'accion' => 'PDF de informe de cometido regenerado',
            'observacion' => 'PDF de informe de cometido regenerado manualmente: ' . ($documento->numero_documento ?: 'Informe de cometido') . '.',
        ]);

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', 'PDF de informe de cometido regenerado correctamente.');
    }

    public function aprobarJefatura(Request $request, CometidoFuncionario $cometido)
    {
        $this->autorizarJefatura($request, $cometido);
        $informe = $this->informePendienteJefatura($cometido);
        $estadoAnterior = $cometido->estado;

        DB::transaction(function () use ($request, $cometido, $informe, $estadoAnterior) {
            $rendicionEnviada = CometidoFuncionarioRendicion::query()
                ->where('cometido_funcionario_id', $cometido->id)
                ->exists();

            [$estado, $estadoViatico, $estadoReembolso] = $this->estadoPosteriorAprobacionJefatura($cometido, $rendicionEnviada);

            $informe->update([
                'estado_informe' => 'aprobado_jefatura',
                'fecha_revision_jefatura' => now(),
                'jefatura_revisora_id' => $request->user()->id,
                'decision_jefatura' => 'aprobado',
                'observacion_jefatura' => $request->input('observacion_jefatura'),
            ]);

            $cometido->update([
                'estado' => $estado,
                'estado_viatico' => $estadoViatico,
                'estado_reembolso' => $estadoReembolso,
            ]);

            $documento = $this->pdfService->firmarInformeCometidoJefatura($cometido->fresh(['funcionarioAcAutorizado', 'establecimiento', 'solicitante']), $informe, $request->user(), $request);

            CometidoFuncionarioHistorial::create([
                'cometido_funcionario_id' => $cometido->id,
                'user_id' => $request->user()->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estado,
                'accion' => 'Jefatura aprobó informe de cometido',
                'observacion' => 'Informe aprobado y firmado electrónicamente por jefatura. PDF actualizado: ' . ($documento->numero_documento ?: 'Informe de cometido') . '.',
            ]);
        });

        $cometidoNotificacion = $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']);
        $estadoFinal = (string) $cometidoNotificacion->estado;
        $this->notificarFuncionario($cometidoNotificacion, 'Informe de Cometido aprobado por jefatura', 'La jefatura aprobó el Informe de Cometido. El trámite continuará según corresponda: DAF contable para viático o revisión DAF de la rendición si se trata de reembolso.', 'Informe aprobado', 'expediente_completo');
        if ($estadoFinal === 'en_revision_daf_rendicion') {
            $this->notificarRol('funcionario_daf', 'Rendición lista para revisión DAF', 'La rendición fue enviada y el Informe de Cometido ya se encuentra aprobado por jefatura. DAF puede continuar con la revisión de la rendición.', $cometidoNotificacion, 'Revisar rendición', 'Rendición lista', 'rendicion_lista');
        }
        if ($estadoFinal === 'en_daf_viatico') {
            $this->notificarRol('funcionario_daf', 'Cometido listo para DAF contable de viático', 'El Informe de Cometido fue aprobado por jefatura. DAF debe registrar compromiso y devengo del viático antes de pago.', $cometidoNotificacion, 'Registrar contabilidad', 'DAF contable', 'expediente_completo');
        }

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', 'Informe de cometido aprobado por jefatura.');
    }

    public function observarJefatura(Request $request, CometidoFuncionario $cometido)
    {
        $this->autorizarJefatura($request, $cometido);
        $data = $request->validate([
            'observacion_jefatura' => ['required', 'string', 'max:5000'],
        ]);
        $informe = $this->informePendienteJefatura($cometido);
        $estadoAnterior = $cometido->estado;

        DB::transaction(function () use ($request, $cometido, $informe, $data, $estadoAnterior) {
            $informe->update([
                'estado_informe' => 'observado_jefatura',
                'fecha_revision_jefatura' => now(),
                'jefatura_revisora_id' => $request->user()->id,
                'decision_jefatura' => 'observado',
                'observacion_jefatura' => $data['observacion_jefatura'],
            ]);

            $estadoReembolso = $cometido->solicita_reembolso ? 'informe_observado' : $cometido->estado_reembolso;
            $cometido->update([
                'estado' => 'informe_observado',
                'estado_viatico' => $cometido->solicita_viatico ? 'informe_observado' : $cometido->estado_viatico,
                'estado_reembolso' => $estadoReembolso,
            ]);

            CometidoFuncionarioHistorial::create([
                'cometido_funcionario_id' => $cometido->id,
                'user_id' => $request->user()->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'informe_observado',
                'accion' => 'Jefatura observó informe de cometido',
                'observacion' => $data['observacion_jefatura'],
            ]);
        });

        $this->notificarFuncionario($cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Informe de Cometido observado por jefatura', 'La jefatura observó el Informe de Cometido. Debe revisar la observación, corregir el informe y reenviarlo para una nueva revisión.', 'Informe observado', 'informe_cometido');

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('warning', 'Informe observado. El funcionario debe corregirlo y reenviarlo.');
    }

    public function rechazarJefatura(Request $request, CometidoFuncionario $cometido)
    {
        $this->autorizarJefatura($request, $cometido);
        $data = $request->validate([
            'observacion_jefatura' => ['required', 'string', 'max:5000'],
        ]);
        $informe = $this->informePendienteJefatura($cometido);
        $estadoAnterior = $cometido->estado;

        DB::transaction(function () use ($request, $cometido, $informe, $data, $estadoAnterior) {
            $informe->update([
                'estado_informe' => 'rechazado_jefatura',
                'fecha_revision_jefatura' => now(),
                'jefatura_revisora_id' => $request->user()->id,
                'decision_jefatura' => 'rechazado',
                'observacion_jefatura' => $data['observacion_jefatura'],
            ]);

            $cometido->update([
                'estado' => 'informe_rechazado',
                'estado_viatico' => $cometido->solicita_viatico ? 'informe_rechazado' : $cometido->estado_viatico,
                'estado_reembolso' => $cometido->solicita_reembolso ? 'informe_rechazado' : $cometido->estado_reembolso,
            ]);

            CometidoFuncionarioHistorial::create([
                'cometido_funcionario_id' => $cometido->id,
                'user_id' => $request->user()->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'informe_rechazado',
                'accion' => 'Jefatura rechazó informe de cometido',
                'observacion' => $data['observacion_jefatura'],
            ]);
        });

        $this->notificarFuncionario($cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Informe de Cometido rechazado por jefatura', 'La jefatura rechazó el Informe de Cometido. El flujo queda detenido hasta su regularización administrativa.', 'Informe rechazado', 'informe_cometido');

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('danger', 'Informe rechazado por jefatura.');
    }


    private function autorizarRegeneracionPdf(Request $request, CometidoFuncionario $cometido): void
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if ($activeRole === 'admin') {
            return;
        }

        abort_unless(in_array($activeRole, ['funcionario_ac', 'funcionario_estab'], true), 403);

        $esFuncionarioSolicitante = (int) $cometido->user_id === (int) $user->id;
        $esJefaturaRevisora = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->where('jefatura_revisora_id', $user->id)
            ->exists();

        abort_unless($esFuncionarioSolicitante || $esJefaturaRevisora, 403);
    }

    private function autorizarFuncionario(Request $request, CometidoFuncionario $cometido): void
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if ($activeRole === 'admin') {
            return;
        }

        abort_unless(in_array($activeRole, ['funcionario_ac', 'funcionario_estab'], true), 403);
        abort_unless((int) $cometido->user_id === (int) $user->id, 403);
    }

    private function autorizarJefatura(Request $request, CometidoFuncionario $cometido): void
    {
        $user = $request->user();
        $activeRole = method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        if ($activeRole === 'admin') {
            return;
        }

        abort_unless(in_array($activeRole, ['funcionario_ac', 'funcionario_estab'], true), 403);
        abort_unless((int) $cometido->user_id !== (int) $user->id, 403);
    }

    private function validarEtapaDisponible(CometidoFuncionario $cometido): void
    {
        $etapasInformeViatico = in_array($cometido->estado_viatico, ['informe_pendiente_funcionario', 'informe_observado'], true)
            || in_array($cometido->estado, ['informe_pendiente_funcionario', 'en_daf_viatico', 'informe_observado'], true);

        $etapasInformeReembolso = (bool) $cometido->solicita_reembolso
            && in_array((string) ($cometido->estado_reembolso ?: $cometido->estado), [
                'pendiente_rendicion',
                'pendiente_rendicion_informe',
                'en_rendicion_reembolso',
                'rendicion_enviada',
                'rendicion_enviada_pendiente_informe',
                'informe_observado',
            ], true);

        abort_unless($etapasInformeViatico || $etapasInformeReembolso, 403);
    }

    private function informePendienteJefatura(CometidoFuncionario $cometido): CometidoFuncionarioInforme
    {
        $informe = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        abort_unless($informe && in_array((string) $informe->estado_informe, ['enviado_pendiente_jefatura', 'pendiente_jefatura'], true), 404);

        return $informe;
    }

    private function estadoPosteriorAprobacionJefatura(CometidoFuncionario $cometido, bool $rendicionEnviada): array
    {
        $estadoViatico = $cometido->estado_viatico;
        $estadoReembolso = $cometido->estado_reembolso;

        if ($cometido->solicita_viatico) {
            $estadoViatico = 'informe_aprobado';
        }

        if ($cometido->solicita_reembolso) {
            $estadoReembolso = $rendicionEnviada ? 'en_revision_daf_rendicion' : 'pendiente_rendicion_informe';
        }

        if ($cometido->solicita_viatico && $cometido->solicita_reembolso) {
            return [$rendicionEnviada ? 'en_revision_daf_rendicion' : 'en_gestion_paralela', $estadoViatico, $estadoReembolso];
        }

        if ($cometido->solicita_reembolso) {
            return [$rendicionEnviada ? 'en_revision_daf_rendicion' : 'pendiente_rendicion_informe', $estadoViatico, $estadoReembolso];
        }

        return ['en_daf_viatico', $estadoViatico, $estadoReembolso];
    }


    private function notificarJefaturaInforme(CometidoFuncionario $cometido): void
    {
        $destinatarios = collect();
        foreach (['jefatura_autorizadora_user_id', 'uatp_revisado_por'] as $campo) {
            if (! empty($cometido->{$campo})) {
                $usuario = User::find($cometido->{$campo});
                if ($usuario && $usuario->email) {
                    $destinatarios->push($usuario);
                }
            }
        }

        if ($destinatarios->isEmpty()) {
            $roles = $cometido->esAdministracionCentral() ? ['coordinador_gdp'] : ['coordinador_uatp'];
            $this->notificarRol($roles, 'Informe de Cometido pendiente de revisión de jefatura', 'El funcionario envió el Informe de Cometido. Corresponde que la jefatura lo revise, apruebe, observe o rechace en la plataforma.', $cometido, 'Revisar informe', 'Informe pendiente', 'informe_cometido');
            return;
        }

        foreach ($destinatarios->unique('email') as $usuario) {
            try {
                Mail::to($usuario->email)->send(new CometidoFuncionarioNotificationMail(
                    $usuario->nombre_completo ?? $usuario->name ?? $usuario->email,
                    'Informe de Cometido pendiente de revisión de jefatura',
                    'El funcionario envió el Informe de Cometido. Corresponde revisar, aprobar, observar o rechazar el informe en la plataforma.',
                    $cometido,
                    'Revisar informe',
                    route('tramites.cometidos-funcionarios.show', $cometido),
                    'Informe pendiente',
                    'Esta notificación forma parte de la trazabilidad del trámite de cometido funcionario.',
                    'informe_cometido'
                ));
            } catch (\Throwable $e) {
                Log::warning('No fue posible notificar revisión de informe de cometido.', ['cometido_funcionario_id' => $cometido->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function notificarFuncionario(CometidoFuncionario $cometido, string $subject, string $body, string $badgeText, string $attachmentPack): void
    {
        $usuario = User::find($cometido->user_id);
        if (! $usuario || ! $usuario->email) {
            return;
        }

        try {
            Mail::to($usuario->email)->send(new CometidoFuncionarioNotificationMail(
                $usuario->nombre_completo ?? $usuario->name ?? $usuario->email,
                $subject,
                $body,
                $cometido,
                'Ver cometido',
                route('tramites.cometidos-funcionarios.show', $cometido),
                $badgeText,
                'Esta notificación forma parte de la trazabilidad del trámite de cometido funcionario.',
                $attachmentPack
            ));
        } catch (\Throwable $e) {
            Log::warning('No fue posible notificar al funcionario sobre informe de cometido.', ['cometido_funcionario_id' => $cometido->id, 'error' => $e->getMessage()]);
        }
    }

    private function notificarRol($roles, string $subject, string $body, CometidoFuncionario $cometido, string $actionText, string $badgeText, string $attachmentPack): void
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $clave = CometidoNotificacionConfiguracion::claveParaAsunto($subject) ?: 'notificacion_' . \Illuminate\Support\Str::slug($subject, '_');
        $correos = CometidoNotificacionConfiguracion::correosPorRoles($clave, $roles);

        foreach ($correos as $correo) {
            try {
                Mail::to($correo)->send(new CometidoFuncionarioNotificationMail(
                    'Destinatario ' . implode(', ', $roles),
                    $subject,
                    $body,
                    $cometido,
                    $actionText,
                    route('tramites.cometidos-funcionarios.show', $cometido),
                    $badgeText,
                    'Esta notificación fue enviada según la configuración de Notificaciones de Cometidos para la clave ' . $clave . '.',
                    $attachmentPack
                ));
            } catch (\Throwable $e) {
                Log::warning('No fue posible notificar por rol sobre informe de cometido.', ['cometido_funcionario_id' => $cometido->id, 'email' => $correo, 'error' => $e->getMessage()]);
            }
        }
    }

    private function hayCambioFechasHoras(CometidoFuncionario $cometido, array $data): bool
    {
        return (string) Carbon::parse($cometido->fecha_desde)->toDateString() !== (string) Carbon::parse($data['fecha_desde_real'])->toDateString()
            || (string) Carbon::parse($cometido->fecha_hasta)->toDateString() !== (string) Carbon::parse($data['fecha_hasta_real'])->toDateString()
            || substr((string) $cometido->hora_salida, 0, 5) !== substr((string) $data['hora_salida_real'], 0, 5)
            || substr((string) $cometido->hora_regreso, 0, 5) !== substr((string) $data['hora_regreso_real'], 0, 5);
    }

    private function generaDiferenciaDiasAFavor(CometidoFuncionario $cometido, array $data): bool
    {
        $diasOriginales = Carbon::parse($cometido->fecha_desde)->startOfDay()->diffInDays(Carbon::parse($cometido->fecha_hasta)->startOfDay()) + 1;
        $diasReales = Carbon::parse($data['fecha_desde_real'])->startOfDay()->diffInDays(Carbon::parse($data['fecha_hasta_real'])->startOfDay()) + 1;

        return $diasReales > $diasOriginales;
    }
}
