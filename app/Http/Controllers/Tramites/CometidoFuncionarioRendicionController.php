<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Mail\BaseMailable;
use App\Mail\CometidoFuncionarioNotificationMail;
use App\Models\CometidoFuncionario;
use App\Models\CometidoFuncionarioDocumento;
use App\Models\CometidoFuncionarioRendicion;
use App\Models\CometidoFuncionarioResolucionReembolso;
use App\Models\CometidoFuncionarioInforme;
use App\Models\CometidoNotificacionConfiguracion;
use App\Models\User;
use App\Models\ViaticoReembolsoValor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CometidoFuncionarioRendicionController extends Controller
{
    public function panel(Request $request, CometidoFuncionario $cometido)
    {
        $cometido->loadMissing(['cdpMontos.catalogoValor', 'catalogoValorCdp', 'funcionarioPadron', 'informeCometidoActual', 'documentosGenerados']);

        $rendicion = CometidoFuncionarioRendicion::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        $resolucion = CometidoFuncionarioResolucionReembolso::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        $informeCometido = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        $informeAprobado = $this->informeCometidoAprobado($cometido, $informeCometido);
        $puedeCompletarInforme = $this->puedeCompletarInformeReembolso($cometido, $informeCometido);

        return view('tramites.cometidos-funcionarios.rendicion-reembolso', [
            'cometido' => $cometido,
            'rendicion' => $rendicion,
            'resolucion' => $resolucion,
            'informeCometido' => $informeCometido,
            'informeCometidoAprobado' => $informeAprobado,
            'puedeCompletarInformeCometido' => $puedeCompletarInforme,
            'documentosRendicionVisibles' => $this->documentosRendicionVisibles($cometido, $rendicion),
            'topeReembolsoCdp' => $this->topeReembolsoCdp($cometido),
            'referenciaReembolso' => $this->referenciaReembolsoDesdeViatico($cometido),
            'puedeRendir' => $this->puedeRendir($cometido),
            'bloqueaDafPorInforme' => $this->bloqueaDafPorInforme($cometido, $rendicion, $informeCometido),
            'puedeRevisarDaf' => $this->userHasAnyRole(['funcionario_daf', 'admin']),
            'puedeRevisarCdp' => $this->userHasAnyRole(['supervisor_plani', 'coordinador_plani', 'admin']),
            'puedeJuridica' => $this->userHasAnyRole(['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica', 'admin']),
            'puedeRegistrarPago' => $this->userHasAnyRole(['funcionario_daf', 'coordinador_gdp', 'admin']),
            'puedeRegistrarContabilidad' => $this->userHasAnyRole(['funcionario_daf', 'admin']),
        ]);
    }

    public function enviarRendicion(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->puedeRendir($cometido), 403);

        $data = $request->validate([
            'monto_rendido' => ['required', 'integer', 'min:0'],
            'observacion_establecimiento' => ['nullable', 'string', 'max:5000'],
            'documentos_respaldo.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            'comprobantes' => ['nullable', 'array'],
            'comprobantes.*.archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'comprobantes.*.fecha_documento' => ['nullable', 'date'],
            'comprobantes.*.monto_documento' => ['nullable', 'integer', 'min:1'],
            'comprobantes.*.detalle_gasto' => ['nullable', 'string', 'max:2000'],
        ]);

        $paths = $this->storeComprobantes($request, $cometido);
        if (empty($paths)) {
            $paths = $this->storeFiles($request, 'documentos_respaldo', $cometido, 'rendicion');
        }

        $rendicion = CometidoFuncionarioRendicion::query()->create([
            'cometido_funcionario_id' => $cometido->id,
            'monto_rendido' => $data['monto_rendido'],
            'estado' => 'rendicion_enviada',
            'observacion_establecimiento' => $data['observacion_establecimiento'] ?? null,
            'documentos_respaldo' => $paths,
            'fecha_envio_rendicion' => now(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $informe = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();
        $informeAprobado = $this->informeCometidoAprobado($cometido, $informe);

        $requiereRexCgrAc = $cometido->esAdministracionCentral()
            && ! (bool) $cometido->solicita_viatico
            && ($cometido->estado === 'en_gdp_rex_cgr' || $cometido->estado_reembolso === 'en_gdp_rex_cgr');
        $estadoPosteriorRendicion = $this->estadoPosteriorRendicionConInforme($cometido, $informeAprobado, $requiereRexCgrAc);
        $this->actualizarEstadoCometido($cometido, $estadoPosteriorRendicion);
        $this->sincronizarDocumentosRendicionCometido($cometido, $rendicion, $paths);

        $this->registrarHistorial(
            $cometido,
            $estadoPosteriorRendicion,
            $cometido->esAdministracionCentral()
                ? ($requiereRexCgrAc
                    ? 'Rendición de reembolso enviada por el funcionario AC y mantenida en GDP para emisión de REX cometido CGR.'
                    : 'Rendición de reembolso enviada por el funcionario AC y derivada a revisión DAF.')
                : 'Rendición de reembolso enviada por el establecimiento y derivada a GDP para emisión de REX cometido CGR.',
            $data['observacion_establecimiento'] ?? null
        );

        if (! $cometido->esAdministracionCentral()) {
            $this->notificarRol('funcionario_slep', 'Rendición enviada requiere REX cometido CGR', 'El establecimiento envió la rendición de gastos de un cometido con reembolso. GDP debe emitir la Resolución Exenta para CGR antes de la revisión de rendición por parte de DAF. Se adjunta expediente vigente con cometido, citación o invitación, documentos complementarios y rendición enviada.', $cometido, 'Emitir REX cometido CGR', 'Pendiente REX CGR', 'expediente_aprobado');
        } elseif ($requiereRexCgrAc) {
            $this->notificarRol('funcionario_slep', 'Rendición AC enviada requiere REX cometido CGR', 'El funcionario AC envió la rendición de gastos de un cometido con reembolso. GDP debe emitir la Resolución Exenta para CGR antes de la revisión de rendición por parte de DAF. Se adjunta expediente vigente con cometido firmado, citación o invitación, documentos complementarios y rendición enviada.', $cometido, 'Emitir REX cometido CGR', 'Pendiente REX CGR', 'expediente_aprobado');
        }

        if (! $informeAprobado) {
            $this->notificarUsuarioId($cometido->user_id, 'Debe completar o regularizar Informe de Cometido', 'La rendición fue enviada, pero DAF no podrá revisar el reembolso hasta que el Informe de Cometido esté enviado y aprobado por jefatura.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Completar informe', 'Informe pendiente', 'rendicion_lista');
        } elseif (! $requiereRexCgrAc && $estadoPosteriorRendicion === 'en_revision_daf_rendicion') {
            $this->notificarRol('funcionario_daf', 'Rendición e informe listos para revisión DAF', 'La rendición fue enviada y el Informe de Cometido se encuentra aprobado por jefatura. DAF puede revisar la rendición de reembolso.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Revisar rendición', 'Rendición lista', 'rendicion_lista');
        }

        return redirect()
            ->route('tramites.cometidos-funcionarios.rendicion.panel', $cometido)
            ->with('success', $cometido->esAdministracionCentral()
                ? ($requiereRexCgrAc ? 'Rendición enviada correctamente. La solicitud continúa en GDP para emisión de REX cometido CGR.' : 'Rendición enviada correctamente. La solicitud quedó en revisión DAF.')
                : 'Rendición enviada correctamente. La solicitud quedó en GDP para emisión de REX cometido CGR.');
    }



    public function rectificarRendicion(Request $request, CometidoFuncionario $cometido)
    {
        $rendicion = $this->rendicionActual($cometido);

        if (! in_array((string) $rendicion->estado, ['rendicion_observada_daf', 'rendicion_rectificada_pendiente_daf'], true)) {
            throw ValidationException::withMessages([
                'rectificacion' => 'La rendición sólo puede rectificarse cuando DAF la ha observado.',
            ]);
        }

        $user = Auth::user();
        $activeRole = session('active_role') ?? session('rol_activo') ?? ($user->rol ?? null);
        $esSolicitanteAc = $cometido->esAdministracionCentral()
            && $activeRole === 'funcionario_ac'
            && (int) $cometido->user_id === (int) Auth::id();
        $esEstablecimiento = ! $cometido->esAdministracionCentral() && $activeRole === 'funcionario_estab';

        abort_unless($this->userHasAnyRole(['admin']) || $esSolicitanteAc || $esEstablecimiento, 403);

        $data = $request->validate([
            'monto_rendido' => ['required', 'integer', 'min:0'],
            'observacion_rectificacion' => ['required', 'string', 'max:5000'],
            'documentos_respaldo.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            'comprobantes' => ['nullable', 'array'],
            'comprobantes.*.archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'comprobantes.*.fecha_documento' => ['nullable', 'date'],
            'comprobantes.*.monto_documento' => ['nullable', 'integer', 'min:1'],
            'comprobantes.*.detalle_gasto' => ['nullable', 'string', 'max:2000'],
        ]);

        $documentos = $this->storeComprobantes($request, $cometido);
        if (empty($documentos)) {
            $documentos = $this->storeFiles($request, 'documentos_respaldo', $cometido, 'rendicion-rectificada');
        }

        if (empty($documentos)) {
            throw ValidationException::withMessages([
                'documentos_respaldo' => 'Debe adjuntar al menos un documento fiscal o comprobante rectificado.',
            ]);
        }

        $fill = [
            'estado' => 'rendicion_rectificada_pendiente_daf',
            'monto_rendido' => (int) $data['monto_rendido'],
            'documentos_respaldo' => $documentos,
            'observacion_rectificacion' => $data['observacion_rectificacion'],
            'fecha_ultima_rectificacion' => now(),
            'rectificado_por' => Auth::id(),
            'updated_by' => Auth::id(),
        ];

        if (Schema::hasColumn($rendicion->getTable(), 'rectificacion_count')) {
            $fill['rectificacion_count'] = (int) ($rendicion->rectificacion_count ?? 0) + 1;
        }

        if (Schema::hasColumn($rendicion->getTable(), 'monto_autorizado_daf')) {
            $fill['monto_autorizado_daf'] = null;
        }
        if (Schema::hasColumn($rendicion->getTable(), 'documento_daf_path')) {
            $fill['documento_daf_path'] = null;
        }
        if (Schema::hasColumn($rendicion->getTable(), 'fecha_revision_daf')) {
            $fill['fecha_revision_daf'] = null;
        }

        $rendicion->forceFill($fill)->save();
        $this->sincronizarDocumentosRendicionCometido($cometido, $rendicion->refresh(), $documentos);

        $estadoPosterior = $this->requiereInformeParaDaf($cometido)
            && ! $this->informeCometidoAprobado($cometido)
                ? 'rendicion_enviada_pendiente_informe'
                : 'en_revision_daf_rendicion';

        $this->actualizarEstadoCometido($cometido, $estadoPosterior);
        $this->registrarHistorial(
            $cometido,
            'rendicion_rectificada_pendiente_daf',
            'El solicitante rectificó los documentos fiscales de la rendición observada por DAF. La rendición queda nuevamente disponible para revisión DAF.',
            $data['observacion_rectificacion']
        );

        return redirect()
            ->route('tramites.cometidos-funcionarios.rendicion.panel', $cometido)
            ->with('success', 'Rendición rectificada correctamente. Quedó nuevamente disponible para revisión DAF.');
    }

    public function verDocumentoRespaldo(CometidoFuncionario $cometido, CometidoFuncionarioRendicion $rendicion, int $index)
    {
        [$path, $filename] = $this->resolverDocumentoRespaldo($cometido, $rendicion, $index);

        $headers = [];
        $mime = Storage::disk('public')->mimeType($path);
        if ($mime) {
            $headers['Content-Type'] = $mime;
        }

        return Storage::disk('public')->response($path, $filename, $headers);
    }

    public function descargarDocumentoRespaldo(CometidoFuncionario $cometido, CometidoFuncionarioRendicion $rendicion, int $index)
    {
        [$path, $filename] = $this->resolverDocumentoRespaldo($cometido, $rendicion, $index);

        return Storage::disk('public')->download($path, $filename);
    }

    public function observarDaf(Request $request, CometidoFuncionario $cometido)
    {
        $informe = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        if ($this->requiereInformeParaDaf($cometido) && ! $this->informeCometidoAprobado($cometido, $informe)) {
            throw ValidationException::withMessages([
                'informe_cometido' => 'DAF no puede revisar la rendición hasta que el funcionario envíe el Informe de Cometido y la jefatura lo apruebe.',
            ]);
        }

        $data = $request->validate([
            'observacion_daf' => ['required', 'string', 'max:5000'],
        ]);

        $rendicion = $this->rendicionActual($cometido);
        $rendicion->update([
            'estado' => 'rendicion_observada_daf',
            'observacion_daf' => $data['observacion_daf'],
            'fecha_revision_daf' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->actualizarEstadoCometido($cometido, 'rendicion_observada_daf');
        $this->registrarHistorial($cometido, 'rendicion_observada_daf', 'DAF observó la rendición del reembolso.', $data['observacion_daf']);
        $this->notificarUsuarioId($cometido->user_id, 'Rendición observada por DAF', 'DAF observó la rendición del reembolso. Debe revisar la observación y rectificar los documentos fiscales o antecedentes solicitados.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Rectificar rendición', 'Rendición observada', 'rendicion_lista');

        return back()->with('success', 'Rendición observada correctamente.');
    }

    public function rechazarDaf(Request $request, CometidoFuncionario $cometido)
    {
        $informe = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        if ($this->requiereInformeParaDaf($cometido) && ! $this->informeCometidoAprobado($cometido, $informe)) {
            throw ValidationException::withMessages([
                'informe_cometido' => 'DAF no puede revisar la rendición hasta que el funcionario envíe el Informe de Cometido y la jefatura lo apruebe.',
            ]);
        }

        $data = $request->validate([
            'observacion_daf' => ['required', 'string', 'max:5000'],
        ]);

        $rendicion = $this->rendicionActual($cometido);
        $rendicion->update([
            'estado' => 'rendicion_rechazada_daf',
            'monto_autorizado_daf' => 0,
            'observacion_daf' => $data['observacion_daf'],
            'fecha_revision_daf' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->actualizarEstadoCometido($cometido, 'rendicion_rechazada_daf');
        $this->registrarHistorial($cometido, 'rendicion_rechazada_daf', 'DAF rechazó la rendición del reembolso.', $data['observacion_daf']);
        $this->notificarUsuarioId($cometido->user_id, 'Rendición rechazada por DAF', 'DAF rechazó la rendición del reembolso. Revise la observación registrada en la plataforma para su regularización administrativa.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Ver rendición', 'Rendición rechazada', 'rendicion_lista');

        return back()->with('success', 'Rendición rechazada correctamente.');
    }

    public function autorizarDaf(Request $request, CometidoFuncionario $cometido)
    {
        $informe = CometidoFuncionarioInforme::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        if ($this->requiereInformeParaDaf($cometido) && ! $this->informeCometidoAprobado($cometido, $informe)) {
            throw ValidationException::withMessages([
                'informe_cometido' => 'DAF no puede revisar la rendición hasta que el funcionario envíe el Informe de Cometido y la jefatura lo apruebe.',
            ]);
        }

        $data = $request->validate([
            'monto_autorizado_daf' => ['required', 'integer', 'min:0'],
            'observacion_daf' => ['nullable', 'string', 'max:5000'],
            'documento_daf' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $tope = $this->topeReembolsoCdp($cometido);
        if ($tope !== null && (int) $data['monto_autorizado_daf'] > $tope) {
            throw ValidationException::withMessages([
                'monto_autorizado_daf' => 'El monto autorizado por DAF no puede superar el tope de reembolso aprobado en CDP.',
            ]);
        }

        $rendicion = $this->rendicionActual($cometido);
        $documentoDaf = $this->storeFile($request, 'documento_daf', $cometido, 'daf');
        $montoAutorizado = (int) $data['monto_autorizado_daf'];
        $estadoRendicion = $montoAutorizado > 0
            ? 'en_revision_cdp_rendicion'
            : 'cerrado_sin_pago_reembolso';

        $rendicion->update([
            'estado' => $estadoRendicion,
            'monto_autorizado_daf' => $montoAutorizado,
            'observacion_daf' => $data['observacion_daf'] ?? null,
            'documento_daf_path' => $documentoDaf,
            'fecha_revision_daf' => now(),
            'updated_by' => Auth::id(),
        ]);

        if ($montoAutorizado > 0) {
            $this->actualizarEstadoCometido($cometido, 'en_revision_cdp_rendicion');
            $this->registrarHistorial(
                $cometido,
                'en_revision_cdp_rendicion',
                'DAF autorizó la rendición y derivó a Planificación para emitir CDP del reembolso.',
                $data['observacion_daf'] ?? null
            );
        } else {
            $this->actualizarEstadoCometido($cometido, 'cerrado_sin_pago_reembolso');
            $this->registrarHistorial($cometido, 'cerrado_sin_pago_reembolso', 'DAF autorizó monto $0 para reembolso. No se deriva a Planificación ni Jurídica.', $data['observacion_daf'] ?? null);
            $this->cerrarCometidoSiFlujoFinalizado($cometido, 'Cierre automático posterior a la autorización de rendición con monto $0.');
        }

        if ($montoAutorizado > 0) {
            $this->notificarRol(['supervisor_plani', 'coordinador_plani'], 'Rendición autorizada por DAF: requiere CDP de reembolso', 'DAF autorizó la rendición de reembolso. Planificación debe emitir el CDP correspondiente para continuar hacia Jurídica.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Emitir CDP reembolso', 'CDP reembolso', 'rendicion_lista');
        } else {
            $this->notificarUsuarioId($cometido->user_id, 'Rendición autorizada con monto $0', 'DAF autorizó la rendición con monto $0. No se deriva a CDP, Jurídica ni pago.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Ver rendición', 'Cierre sin pago', 'rendicion_lista');
        }

        return back()->with('success', 'Rendición autorizada correctamente. Si el monto es mayor a $0, queda en revisión de Planificación para CDP.');
    }

    public function observarCdp(Request $request, CometidoFuncionario $cometido)
    {
        $data = $request->validate([
            'observacion_cdp' => ['required', 'string', 'max:5000'],
        ]);

        $rendicion = $this->rendicionActual($cometido);
        $rendicion->update([
            'estado' => 'cdp_observado_rendicion',
            'observacion_cdp' => $data['observacion_cdp'],
            'fecha_revision_cdp' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->actualizarEstadoCometido($cometido, 'cdp_observado_rendicion');
        $this->registrarHistorial($cometido, 'cdp_observado_rendicion', 'Planificación observó el CDP de rendición de reembolso.', $data['observacion_cdp']);

        return back()->with('success', 'Observación CDP registrada correctamente.');
    }

    public function rechazarCdp(Request $request, CometidoFuncionario $cometido)
    {
        $data = $request->validate([
            'observacion_cdp' => ['required', 'string', 'max:5000'],
        ]);

        $rendicion = $this->rendicionActual($cometido);
        $rendicion->update([
            'estado' => 'cdp_rechazado_rendicion',
            'monto_cdp_reembolso' => 0,
            'observacion_cdp' => $data['observacion_cdp'],
            'fecha_revision_cdp' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->actualizarEstadoCometido($cometido, 'cdp_rechazado_rendicion');
        $this->registrarHistorial($cometido, 'cdp_rechazado_rendicion', 'Planificación rechazó el CDP asociado a la rendición de reembolso.', $data['observacion_cdp']);

        return back()->with('success', 'CDP de rendición rechazado correctamente.');
    }

    public function autorizarCdp(Request $request, CometidoFuncionario $cometido)
    {
        $data = $request->validate([
            'referencia_cdp_reembolso' => ['required', 'string', 'max:150'],
            'monto_cdp_reembolso' => ['required', 'integer', 'min:1'],
            'observacion_cdp' => ['nullable', 'string', 'max:5000'],
            'documento_cdp_reembolso' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $rendicion = $this->rendicionActual($cometido);
        if ((int) $data['monto_cdp_reembolso'] > (int) $rendicion->monto_autorizado_daf) {
            throw ValidationException::withMessages([
                'monto_cdp_reembolso' => 'El monto CDP no puede superar el monto autorizado por DAF para la rendición.',
            ]);
        }

        $documentoCdp = $this->storeFile($request, 'documento_cdp_reembolso', $cometido, 'cdp-rendicion');
        $montoCdp = (int) $data['monto_cdp_reembolso'];

        $rendicion->update([
            'estado' => 'cdp_reembolso_aprobado',
            'monto_cdp_reembolso' => $montoCdp,
            'referencia_cdp_reembolso' => $data['referencia_cdp_reembolso'],
            'documento_cdp_reembolso_path' => $documentoCdp,
            'observacion_cdp' => $data['observacion_cdp'] ?? null,
            'fecha_revision_cdp' => now(),
            'updated_by' => Auth::id(),
        ]);

        CometidoFuncionarioResolucionReembolso::query()->updateOrCreate(
            ['cometido_funcionario_id' => $cometido->id, 'rendicion_id' => $rendicion->id],
            [
                'estado' => 'en_juridica_resolucion_reembolso',
                'monto_resolucion' => null,
                'fecha_envio_juridica' => now(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );

        $this->actualizarEstadoCometido($cometido, 'en_juridica_resolucion_reembolso');
        $this->registrarHistorial(
            $cometido,
            'en_juridica_resolucion_reembolso',
            'Planificación aprobó el CDP de la rendición y derivó a Jurídica para resolución de pago de reembolso.',
            $data['observacion_cdp'] ?? null
        );

        $this->notificarRol(['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica'], 'CDP de reembolso aprobado: requiere resolución de pago', 'Planificación aprobó el CDP de la rendición de reembolso. Jurídica debe emitir la resolución de pago correspondiente.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Emitir resolución', 'Jurídica reembolso', 'expediente_completo');

        return back()->with('success', 'CDP aprobado correctamente. La solicitud fue derivada a Jurídica para resolución de pago.');
    }

    public function observarJuridica(Request $request, CometidoFuncionario $cometido)
    {
        $data = $request->validate([
            'observacion_juridica' => ['required', 'string', 'max:5000'],
        ]);

        $resolucion = $this->resolucionActual($cometido);
        $resolucion->update([
            'estado' => 'observada_juridica_reembolso',
            'observacion_juridica' => $data['observacion_juridica'],
            'updated_by' => Auth::id(),
        ]);

        $this->actualizarEstadoCometido($cometido, 'observada_juridica_reembolso');
        $this->registrarHistorial($cometido, 'observada_juridica_reembolso', 'Jurídica observó antecedentes de la resolución de pago de reembolso.', $data['observacion_juridica']);

        return back()->with('success', 'Observación jurídica registrada correctamente.');
    }

    public function emitirResolucion(Request $request, CometidoFuncionario $cometido)
    {
        $data = $request->validate([
            'numero_resolucion' => ['required', 'string', 'max:100'],
            'fecha_resolucion' => ['required', 'date'],
            'monto_resolucion' => ['required', 'integer', 'min:0'],
            'observacion_juridica' => ['nullable', 'string', 'max:5000'],
            'documento_resolucion' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $resolucion = $this->resolucionActual($cometido);
        $rendicion = $resolucion->rendicion ?: $this->rendicionActual($cometido);
        $montoMaximoResolucion = (int) ($rendicion->monto_cdp_reembolso ?: $rendicion->monto_autorizado_daf);
        if ((int) $data['monto_resolucion'] > $montoMaximoResolucion) {
            throw ValidationException::withMessages([
                'monto_resolucion' => 'El monto de la resolución no puede superar el monto aprobado por CDP para la rendición.',
            ]);
        }

        $documento = $this->storeFile($request, 'documento_resolucion', $cometido, 'juridica');
        $resolucion->update([
            'numero_resolucion' => $data['numero_resolucion'],
            'fecha_resolucion' => $data['fecha_resolucion'],
            'monto_resolucion' => (int) $data['monto_resolucion'],
            'documento_resolucion_path' => $documento,
            'estado' => 'resolucion_reembolso_emitida',
            'observacion_juridica' => $data['observacion_juridica'] ?? null,
            'fecha_emision_resolucion' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->actualizarEstadoCometido($cometido, 'en_daf_contable_reembolso');
        $this->registrarHistorial($cometido, 'en_daf_contable_reembolso', 'Jurídica emitió resolución de pago de reembolso y derivó a DAF para registro contable de compromiso y devengo.', $data['observacion_juridica'] ?? null);
        $this->notificarRol('funcionario_daf', 'Resolución de reembolso emitida: registrar compromiso y devengo', 'Jurídica emitió la resolución de pago de reembolso. DAF debe registrar el compromiso y devengo contable antes de habilitar el pago.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Registrar contabilidad', 'DAF contable', 'expediente_completo');

        return back()->with('success', 'Resolución de reembolso registrada correctamente. El trámite quedó en DAF contable.');
    }

    public function registrarContabilidad(Request $request, CometidoFuncionario $cometido)
    {
        $data = $request->validate([
            'folio_compromiso_contable' => ['required', 'string', 'max:100'],
            'fecha_compromiso_contable' => ['required', 'date'],
            'folio_devengo_contable' => ['required', 'string', 'max:100'],
            'fecha_devengo_contable' => ['required', 'date'],
            'documento_contable' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
            'observacion_contable' => ['nullable', 'string', 'max:5000'],
        ]);

        $resolucion = $this->resolucionActual($cometido);
        $resolucionEmitida = $resolucion
            && in_array($resolucion->estado, ['resolucion_reembolso_emitida', 'contabilidad_reembolso_registrada', 'reembolso_pagado'], true)
            && ($resolucion->numero_resolucion || $resolucion->documento_resolucion_path || $resolucion->fecha_resolucion);

        if (! $resolucionEmitida) {
            throw ValidationException::withMessages([
                'folio_compromiso_contable' => 'No se puede registrar compromiso y devengo antes de que Jurídica emita la resolución de reembolso.',
            ]);
        }

        $documento = $this->storeFile($request, 'documento_contable', $cometido, 'contabilidad-reembolso');
        $resolucion->forceFill([
            'estado' => 'contabilidad_reembolso_registrada',
            'folio_compromiso_contable' => $data['folio_compromiso_contable'],
            'fecha_compromiso_contable' => $data['fecha_compromiso_contable'],
            'folio_devengo_contable' => $data['folio_devengo_contable'],
            'fecha_devengo_contable' => $data['fecha_devengo_contable'],
            'documento_contable_path' => $documento ?: $resolucion->documento_contable_path,
            'observacion_contable' => $data['observacion_contable'] ?? null,
            'usuario_contable_id' => Auth::id(),
            'fecha_registro_contable' => now(),
            'updated_by' => Auth::id(),
        ])->save();

        $this->actualizarEstadoCometido($cometido, 'en_pago_reembolso');
        $this->registrarHistorial(
            $cometido,
            'en_pago_reembolso',
            'DAF registró compromiso y devengo del reembolso y habilitó el pago.',
            trim(implode("\n", array_filter([
                'Folio compromiso: ' . $data['folio_compromiso_contable'],
                'Fecha compromiso: ' . $data['fecha_compromiso_contable'],
                'Folio devengo: ' . $data['folio_devengo_contable'],
                'Fecha devengo: ' . $data['fecha_devengo_contable'],
                $data['observacion_contable'] ?? null,
            ]))) ?: null
        );

        $this->notificarRol('funcionario_daf', 'Reembolso habilitado para pago', 'DAF registró compromiso y devengo del reembolso. El pago queda habilitado y debe registrarse con su comprobante correspondiente.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Registrar pago', 'Pago reembolso', 'daf_contable');

        return back()->with('success', 'Registro contable del reembolso guardado correctamente. El pago queda habilitado.');
    }

    public function registrarPago(Request $request, CometidoFuncionario $cometido)
    {
        abort_unless($this->userHasAnyRole(['admin', 'funcionario_daf']), 403);
        abort_unless(in_array($cometido->estado, ['en_pago_reembolso'], true) || $cometido->estado_reembolso === 'en_pago_reembolso', 403);

        $data = $request->validate([
            'monto_pagado_reembolso' => ['required', 'integer', 'min:0'],
            'fecha_pago_reembolso' => ['required', 'date'],
            'observacion_pago' => ['nullable', 'string', 'max:5000'],
            'documento_pago' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $resolucion = $this->resolucionActual($cometido);
        $resolucionEmitida = $resolucion
            && in_array($resolucion->estado, ['contabilidad_reembolso_registrada', 'reembolso_pagado'], true)
            && ($resolucion->numero_resolucion || $resolucion->documento_resolucion_path || $resolucion->fecha_resolucion);

        if (! $resolucionEmitida) {
            throw ValidationException::withMessages([
                'monto_pagado_reembolso' => 'No se puede registrar el pago del reembolso antes de que Jurídica emita la resolución y DAF registre compromiso y devengo.',
            ]);
        }

        if (Schema::hasColumn($resolucion->getTable(), 'folio_devengo_contable') && empty($resolucion->folio_devengo_contable)) {
            throw ValidationException::withMessages([
                'monto_pagado_reembolso' => 'Debe registrar primero compromiso y devengo del reembolso antes de informar el pago.',
            ]);
        }

        if ((int) $data['monto_pagado_reembolso'] > (int) $resolucion->monto_resolucion) {
            throw ValidationException::withMessages([
                'monto_pagado_reembolso' => 'El monto pagado no puede superar el monto de la resolución de reembolso.',
            ]);
        }

        $documento = $this->storeFile($request, 'documento_pago', $cometido, 'pago');
        $resolucion->update([
            'estado' => 'reembolso_pagado',
            'monto_pagado_reembolso' => (int) $data['monto_pagado_reembolso'],
            'fecha_pago_reembolso' => $data['fecha_pago_reembolso'],
            'documento_pago_path' => $documento ?: $resolucion->documento_pago_path,
            'observacion_pago' => $data['observacion_pago'] ?? null,
            'usuario_pago_id' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->actualizarEstadoCometido($cometido, 'reembolso_pagado');
        $this->registrarHistorial(
            $cometido,
            'reembolso_pagado',
            'DAF/Finanzas registró el pago del reembolso posterior al devengo contable.',
            trim(implode("\n", array_filter([
                'Monto pagado reembolso: $' . number_format((int) $data['monto_pagado_reembolso'], 0, ',', '.'),
                'Fecha pago reembolso: ' . $data['fecha_pago_reembolso'],
                'Folio devengo asociado: ' . ($resolucion->folio_devengo_contable ?: 'no informado'),
                $documento ? 'Documento pago adjunto: ' . basename($documento) : null,
                $data['observacion_pago'] ?? null,
            ]))) ?: null
        );

        $cerradoAutomaticamente = $this->cerrarCometidoSiFlujoFinalizado(
            $cometido,
            'Cierre automático posterior al registro de pago de reembolso.'
        );

        $this->notificarUsuarioId($cometido->user_id, 'Pago de reembolso registrado', 'DAF/Finanzas registró el pago del reembolso asociado al cometido. El comprobante queda disponible en el expediente documental.', $cometido->fresh(['documentos', 'documentosGenerados', 'pasajeAereo']), 'Ver expediente', 'Reembolso pagado', 'pago_registrado');

        return back()->with('success', $cerradoAutomaticamente
            ? 'Pago de reembolso registrado correctamente. El cometido fue cerrado automáticamente al finalizar el flujo financiero.'
            : 'Pago de reembolso registrado correctamente. El trámite queda disponible para cierre cuando no existan etapas pendientes.');
    }

    public function cerrar(Request $request, CometidoFuncionario $cometido)
    {
        $data = $request->validate([
            'observacion_cierre' => ['nullable', 'string', 'max:5000'],
        ]);

        $rendicion = CometidoFuncionarioRendicion::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        $resolucion = CometidoFuncionarioResolucionReembolso::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        $pendientes = $this->pendientesParaCierre($cometido, $rendicion, $resolucion);
        if (! empty($pendientes)) {
            throw ValidationException::withMessages([
                'observacion_cierre' => 'No es posible cerrar el trámite porque existen etapas pendientes: ' . implode(', ', $pendientes) . '.',
            ]);
        }

        DB::transaction(function () use ($cometido, $rendicion, $data) {
            if ($rendicion && Schema::hasColumn($rendicion->getTable(), 'estado')) {
                $rendicion->forceFill([
                    'estado' => 'rendicion_cerrada',
                    'updated_by' => Auth::id(),
                ])->save();
            }

            $this->actualizarEstadoCometido($cometido, 'cerrado');
            $this->registrarHistorial(
                $cometido,
                'cerrado',
                'Rendición de reembolso cerrada y trámite de cometido funcionario cerrado.',
                $data['observacion_cierre'] ?? null
            );
        });

        $cometido->refresh();
        $this->notificarCierreAlEstablecimiento($cometido, $data['observacion_cierre'] ?? null);

        return redirect()
            ->route('tramites.cometidos-funcionarios.show', $cometido)
            ->with('success', 'Rendición cerrada, trámite finalizado y establecimiento notificado por correo.');
    }

    /**
     * Determina si aún existen componentes del flujo paralelo que impiden cerrar el cometido.
     * La rendición sólo puede cerrar el trámite completo cuando viático y reembolso están finalizados.
     */
    private function pendientesParaCierre(
        CometidoFuncionario $cometido,
        ?CometidoFuncionarioRendicion $rendicion,
        ?CometidoFuncionarioResolucionReembolso $resolucion
    ): array {
        $pendientes = [];

        $viaticoSolicitado = (bool) ($cometido->solicita_viatico ?? false);
        $montoViatico = (int) ($cometido->cdp_viatico_total ?? 0);
        $viaticoRequierePago = $viaticoSolicitado && $montoViatico > 0;

        if ($viaticoRequierePago && empty($cometido->fecha_pago_viatico) && ($cometido->estado ?? null) !== 'viatico_pagado') {
            $pendientes[] = 'pago de viático';
        }

        $reembolsoSolicitado = (bool) ($cometido->solicita_reembolso ?? false);
        if ($reembolsoSolicitado) {
            if (! $rendicion) {
                $pendientes[] = 'rendición de reembolso';
            } elseif (! $resolucion || $resolucion->estado !== 'reembolso_pagado' || $resolucion->monto_pagado_reembolso === null || empty($resolucion->fecha_pago_reembolso)) {
                $pendientes[] = 'pago de reembolso';
            }
        }

        return array_values(array_unique($pendientes));
    }

    private function notificarCierreAlEstablecimiento(CometidoFuncionario $cometido, ?string $observacionCierre = null): void
    {
        $emails = $this->correosEstablecimiento($cometido);
        if ($emails === []) {
            $this->registrarHistorial($cometido, 'notificacion_cierre_sin_destinatarios', 'No se encontraron correos de establecimiento para notificar el cierre del cometido.', null);
            return;
        }

        $establecimiento = $cometido->establecimiento?->nombre_establecimiento
            ?? $cometido->establecimiento?->nombre
            ?? 'establecimiento';

        $subject = 'Cometido funcionario cerrado - ' . ($cometido->funcionario_nombre ?? 'Funcionario/a');
        $lines = [
            'Se informa que el cometido funcionario #' . $cometido->id . ' fue cerrado en la plataforma.',
            'Funcionario/a: ' . ($cometido->funcionario_nombre ?? 'No informado') . ($cometido->funcionario_rut ? ' - RUT ' . $cometido->funcionario_rut : ''),
            'Establecimiento: ' . $establecimiento . '.',
            'El flujo de rendición y pago de reembolso no mantiene etapas pendientes. El trámite quedó en estado Cerrado.',
        ];

        if ($observacionCierre) {
            $lines[] = 'Observación de cierre: ' . $observacionCierre;
        }

        $url = route('tramites.cometidos-funcionarios.show', $cometido);

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new BaseMailable(
                    $subject,
                    'Cierre de cometido funcionario',
                    $lines,
                    'Ver cometido funcionario',
                    $url,
                    ['Este correo fue generado automáticamente por la plataforma.'],
                    null
                ));
            } catch (\Throwable $e) {
                Log::warning('No fue posible notificar cierre de cometido funcionario al establecimiento.', [
                    'cometido_funcionario_id' => $cometido->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->registrarHistorial(
            $cometido,
            'notificacion_cierre_establecimiento',
            'Se notificó por correo al establecimiento el cierre del cometido funcionario.',
            implode(', ', $emails)
        );
    }

    private function correosEstablecimiento(CometidoFuncionario $cometido): array
    {
        $emails = [];

        if ($cometido->relationLoaded('solicitante') || $cometido->user_id) {
            $solicitante = $cometido->solicitante;
            if ($solicitante?->email) {
                $emails[] = $solicitante->email;
            }
        }

        if ($cometido->establecimiento_id && Schema::hasTable('users')) {
            $usuarios = User::query()
                ->where('establecimiento_id', $cometido->establecimiento_id)
                ->whereNotNull('email')
                ->where(function ($query) {
                    $query->whereHas('roles', function ($roles) {
                        $roles->whereIn('name', ['funcionario_estab', 'funcionario_directivo_estab']);
                    });
                })
                ->pluck('email')
                ->all();

            $emails = array_merge($emails, $usuarios);
        }

        return collect($emails)
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->unique()
            ->values()
            ->all();
    }


    private function notificarUsuarioId(?int $userId, string $subject, string $body, ?CometidoFuncionario $cometido = null, ?string $actionText = null, ?string $badgeText = null, ?string $attachmentPack = null): void
    {
        $user = $userId ? User::find($userId) : null;
        if (! $user || ! $user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(new CometidoFuncionarioNotificationMail(
                $user->nombre_completo ?? $user->name ?? $user->email,
                $subject,
                $body,
                $cometido,
                $actionText ?: 'Ver cometido',
                $cometido ? route('tramites.cometidos-funcionarios.show', $cometido) : null,
                $badgeText,
                'Esta notificación forma parte de la trazabilidad del trámite de cometido funcionario.',
                $attachmentPack
            ));
        } catch (\Throwable $e) {
            Log::warning('No fue posible enviar notificación de cometido funcionario al usuario.', [
                'cometido_funcionario_id' => $cometido?->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notificarRol($roles, string $subject, string $body, ?CometidoFuncionario $cometido = null, ?string $actionText = null, ?string $badgeText = null, ?string $attachmentPack = null): void
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $clave = CometidoNotificacionConfiguracion::claveParaAsunto($subject) ?: 'notificacion_' . \Illuminate\Support\Str::slug($subject, '_');
        $correos = CometidoNotificacionConfiguracion::correosPorRoles($clave, $roles);
        $url = $cometido ? route('tramites.cometidos-funcionarios.show', $cometido) : null;

        foreach ($correos as $correo) {
            try {
                Mail::to($correo)->send(new CometidoFuncionarioNotificationMail(
                    'Destinatario ' . implode(', ', $roles),
                    $subject,
                    $body,
                    $cometido,
                    $actionText,
                    $url,
                    $badgeText,
                    'Esta notificación fue enviada según la configuración de Notificaciones de Cometidos para la clave ' . $clave . '.',
                    $attachmentPack
                ));
            } catch (\Throwable $e) {
                Log::warning('No fue posible enviar notificación de cometido funcionario.', [
                    'cometido_funcionario_id' => $cometido?->id,
                    'email' => $correo,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resolverDocumentoRespaldo(CometidoFuncionario $cometido, CometidoFuncionarioRendicion $rendicion, int $index): array
    {
        abort_unless((int) $rendicion->cometido_funcionario_id === (int) $cometido->id, 404);
        abort_unless($this->puedeVerDocumentoRespaldo($cometido), 403);

        $documentos = $this->documentosRendicionVisibles($cometido, $rendicion);
        $documento = $documentos[$index] ?? null;
        abort_unless($documento, 404);

        $path = (string) ($documento['path'] ?? '');
        $filename = (string) ($documento['original_name'] ?? basename($path));
        $filename = $filename ?: basename($path);

        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);

        return [$path, $filename];
    }

    private function puedeVerDocumentoRespaldo(CometidoFuncionario $cometido): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $activeRole = session('active_role') ?? session('rol_activo') ?? ($user->rol ?? null);

        if (in_array($activeRole, [
            'admin',
            'funcionario_slep',
            'coordinador_gdp',
            'funcionario_daf',
            'supervisor_plani',
            'coordinador_plani',
            'juridica',
            'juridico',
            'abogado_juridica',
            'coordinador_juridica',
            'funcionario_juridica',
        ], true)) {
            return true;
        }

        if ($cometido->esAdministracionCentral()) {
            return $activeRole === 'funcionario_ac' && (int) $cometido->user_id === (int) $user->id;
        }

        return $activeRole === 'funcionario_estab';
    }


    private function documentosRendicionVisibles(CometidoFuncionario $cometido, ?CometidoFuncionarioRendicion $rendicion): array
    {
        $documentos = [];

        if ($rendicion) {
            $documentos = array_merge($documentos, $this->normalizarDocumentosRendicion($rendicion->documentos_respaldo));
        }

        $tiposRendicion = ['rendicion', 'rendicion_reembolso', 'documento_rendicion', 'comprobante_rendicion', 'comprobante_reembolso'];
        $documentosTabla = CometidoFuncionarioDocumento::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->whereIn('tipo', $tiposRendicion)
            ->orderBy('id')
            ->get();

        foreach ($documentosTabla as $documentoTabla) {
            $documentos[] = [
                'path' => $documentoTabla->path,
                'original_name' => $documentoTabla->nombre_original ?: basename((string) $documentoTabla->path),
                'fecha_documento' => null,
                'monto_documento' => null,
                'detalle_gasto' => 'Documento fiscal de rendición cargado en el expediente.',
                'source' => 'documentos',
            ];
        }

        $unicos = [];
        foreach ($documentos as $documento) {
            $path = trim((string) ($documento['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            if (isset($unicos[$path])) {
                continue;
            }

            $documento['original_name'] = $documento['original_name'] ?? basename($path);
            $unicos[$path] = $documento;
        }

        return array_values($unicos);
    }

    private function normalizarDocumentosRendicion($documentosRaw): array
    {
        if (is_string($documentosRaw)) {
            $decoded = json_decode($documentosRaw, true);
            $documentosRaw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($documentosRaw)) {
            return [];
        }

        if (isset($documentosRaw['path']) || isset($documentosRaw['archivo_path']) || isset($documentosRaw['ruta'])) {
            $documentosRaw = [$documentosRaw];
        }

        $documentos = [];
        foreach (array_values($documentosRaw) as $item) {
            if (is_string($item)) {
                $documentos[] = [
                    'path' => $item,
                    'original_name' => basename($item),
                    'fecha_documento' => null,
                    'monto_documento' => null,
                    'detalle_gasto' => null,
                    'source' => 'rendicion_json',
                ];
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $path = $item['path']
                ?? $item['archivo_path']
                ?? $item['documento_path']
                ?? $item['file_path']
                ?? $item['ruta']
                ?? $item['url']
                ?? null;

            if (! $path) {
                continue;
            }

            $documentos[] = [
                'path' => $path,
                'original_name' => $item['original_name']
                    ?? $item['nombre_original']
                    ?? $item['filename']
                    ?? $item['nombre']
                    ?? basename((string) $path),
                'fecha_documento' => $item['fecha_documento'] ?? null,
                'monto_documento' => $item['monto_documento'] ?? null,
                'detalle_gasto' => $item['detalle_gasto'] ?? null,
                'source' => 'rendicion_json',
            ];
        }

        return $documentos;
    }

    private function sincronizarDocumentosRendicionCometido(CometidoFuncionario $cometido, CometidoFuncionarioRendicion $rendicion, array $documentos): void
    {
        foreach ($this->normalizarDocumentosRendicion($documentos) as $documento) {
            $path = trim((string) ($documento['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            CometidoFuncionarioDocumento::query()->firstOrCreate(
                [
                    'cometido_funcionario_id' => $cometido->id,
                    'tipo' => 'rendicion_reembolso',
                    'path' => $path,
                ],
                [
                    'nombre_original' => $documento['original_name'] ?? basename($path),
                    'mime_type' => Storage::disk('public')->exists($path) ? Storage::disk('public')->mimeType($path) : null,
                    'size' => Storage::disk('public')->exists($path) ? Storage::disk('public')->size($path) : null,
                    'uploaded_by' => Auth::id(),
                ]
            );
        }
    }

    private function rendicionActual(CometidoFuncionario $cometido): CometidoFuncionarioRendicion
    {
        $rendicion = CometidoFuncionarioRendicion::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        if (! $rendicion) {
            abort(404, 'No existe rendición de reembolso para este cometido.');
        }

        return $rendicion;
    }

    private function resolucionActual(CometidoFuncionario $cometido): CometidoFuncionarioResolucionReembolso
    {
        $resolucion = CometidoFuncionarioResolucionReembolso::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        if (! $resolucion) {
            abort(404, 'No existe resolución de reembolso para este cometido.');
        }

        return $resolucion;
    }


    private function referenciaReembolsoDesdeViatico(CometidoFuncionario $cometido): array
    {
        $catalogo = $this->catalogoViaticoDetectado($cometido);
        $rows = $this->rowsViaticoDetectado($cometido, $catalogo);
        $total = collect($rows)->sum('monto');

        if ($total <= 0 && $cometido->cdp_viatico_total !== null) {
            $total = (int) $cometido->cdp_viatico_total;
        }

        return [
            'estamento' => $cometido->cdp_estamento ?: ($catalogo?->estamento ?: $cometido->estamento),
            'cargo_funcion' => $cometido->cdp_cargo_funcion ?: ($catalogo?->cargo_funcion ?: $this->categoriaViaticoAaee($this->textoCategoriaAaeeCometido($cometido))),
            'valor_100' => $catalogo?->valor_100,
            'valor_40' => $catalogo?->valor_40,
            'rows' => $rows,
            'total_referencial' => $total > 0 ? (int) $total : null,
        ];
    }

    private function catalogoViaticoDetectado(CometidoFuncionario $cometido): ?ViaticoReembolsoValor
    {
        $montoViatico = $cometido->cdpMontos
            ->where('tipo', 'viatico')
            ->first(fn ($row) => $row->catalogoValor !== null);

        if ($montoViatico?->catalogoValor) {
            return $montoViatico->catalogoValor;
        }

        if ($cometido->catalogoValorCdp) {
            return $cometido->catalogoValorCdp;
        }

        return $this->catalogoAutomaticoViatico($cometido);
    }

    private function rowsViaticoDetectado(CometidoFuncionario $cometido, ?ViaticoReembolsoValor $catalogo): array
    {
        $guardados = $cometido->cdpMontos
            ->where('tipo', 'viatico')
            ->sortBy('dia_numero')
            ->values();

        if ($guardados->isNotEmpty()) {
            return $guardados->map(function ($row) {
                return [
                    'numero' => (int) ($row->dia_numero ?? 0),
                    'fecha' => optional($row->fecha)->toDateString(),
                    'label' => optional($row->fecha)->format('d-m-Y'),
                    'porcentaje' => (int) ($row->porcentaje ?? 0),
                    'monto' => (int) ($row->monto ?? 0),
                    'valor_diario' => (int) ($row->valor_diario ?? $row->monto ?? 0),
                ];
            })->all();
        }

        if (! $catalogo) {
            return [];
        }

        $dias = $this->diasCometido($cometido);
        $totalDias = count($dias);
        $rows = [];

        foreach ($dias as $index => $dia) {
            $porcentaje = $this->porcentajeAutomaticoViatico($cometido, $totalDias, $index);
            $valorDiario = $porcentaje === 0 ? 0 : ($porcentaje === 100 ? (int) $catalogo->valor_100 : (int) $catalogo->valor_40);
            $rows[] = [
                'numero' => $dia['numero'],
                'fecha' => $dia['fecha'],
                'label' => $dia['label'],
                'porcentaje' => $porcentaje,
                'monto' => $valorDiario,
                'valor_diario' => $valorDiario,
            ];
        }

        return $rows;
    }

    private function catalogoAutomaticoViatico(CometidoFuncionario $cometido): ?ViaticoReembolsoValor
    {
        $fechaReferencia = $cometido->fecha_desde ?: $cometido->fecha_solicitud ?: now();
        $fecha = Carbon::parse($fechaReferencia)->toDateString();

        if ($cometido->esAdministracionCentral()) {
            $reglaAc = $this->reglaCatalogoFuncionarioAc($cometido);
            if ($reglaAc) {
                return $this->catalogoPorEstamentoCargo($reglaAc['estamento'], $reglaAc['cargo_funcion'], $fecha);
            }
        }

        $categoria = $this->categoriaViaticoAaee($this->textoCategoriaAaeeCometido($cometido));
        if (!$categoria) {
            return null;
        }

        return $this->catalogoPorEstamentoCargo('AAEE', $categoria, $fecha);
    }

    private function categoriaViaticoDetectada(CometidoFuncionario $cometido): ?string
    {
        if ($cometido->esAdministracionCentral()) {
            $reglaAc = $this->reglaCatalogoFuncionarioAc($cometido);
            if ($reglaAc) {
                return $reglaAc['label'];
            }
        }

        return $this->categoriaViaticoAaee($this->textoCategoriaAaeeCometido($cometido));
    }

    private function reglaCatalogoFuncionarioAc(CometidoFuncionario $cometido): ?array
    {
        if (! $cometido->esAdministracionCentral()) {
            return null;
        }

        $funcionarioAc = $cometido->relationLoaded('funcionarioAcAutorizado')
            ? $cometido->funcionarioAcAutorizado
            : $cometido->funcionarioAcAutorizado()->first();

        $gradoTexto = trim((string) ($funcionarioAc?->grado ?? ''));
        $grado = $this->extraerGradoNumerico($gradoTexto);

        if ($grado !== null) {
            $tramo = $this->tramoCodigoAdministrativoPorGrado($grado);
            if ($tramo) {
                return [
                    'estamento' => 'Código Administrativo',
                    'cargo_funcion' => $tramo,
                    'label' => 'Código Administrativo / ' . $tramo,
                    'motivo' => 'Funcionario AC con grado ' . $grado . '; se aplica tramo de Código Administrativo.',
                ];
            }
        }

        $escalafon = $this->normaliza(($funcionarioAc?->escalafon ?? '') . ' ' . ($cometido->estamento ?? ''));
        if (str_contains($escalafon, 'DOCENTE')) {
            return [
                'estamento' => 'Docente',
                'cargo_funcion' => 'Docentes',
                'label' => 'Docente / Docentes',
                'motivo' => 'Funcionario AC sin grado y con escalafón Docente; se aplica valor vigente Docente / Docentes.',
            ];
        }

        return null;
    }

    private function extraerGradoNumerico(?string $grado): ?int
    {
        $grado = trim((string) $grado);
        if ($grado === '') {
            return null;
        }

        if (preg_match('/\d+/', $grado, $matches) !== 1) {
            return null;
        }

        $numero = (int) $matches[0];
        return $numero > 0 ? $numero : null;
    }

    private function tramoCodigoAdministrativoPorGrado(int $grado): ?string
    {
        return match (true) {
            $grado >= 1 && $grado <= 4 => '1° al 4°',
            $grado >= 5 && $grado <= 10 => '5° al 10°',
            $grado >= 11 && $grado <= 21 => '11° al 21°',
            $grado >= 22 && $grado <= 31 => '22° al 31°',
            default => null,
        };
    }

    private function catalogoPorEstamentoCargo(string $estamento, string $cargoFuncion, string $fecha): ?ViaticoReembolsoValor
    {
        return ViaticoReembolsoValor::query()
            ->activos()
            ->whereDate('vigente_desde', '<=', $fecha)
            ->whereDate('vigente_hasta', '>=', $fecha)
            ->get()
            ->first(function (ViaticoReembolsoValor $valor) use ($estamento, $cargoFuncion) {
                return $this->normaliza($valor->estamento) === $this->normaliza($estamento)
                    && $this->normaliza($valor->cargo_funcion) === $this->normaliza($cargoFuncion);
            });
    }

    private function textoCategoriaAaeeCometido(CometidoFuncionario $cometido): string
    {
        $funcionarioPadron = $cometido->relationLoaded('funcionarioPadron')
            ? $cometido->funcionarioPadron
            : $cometido->funcionarioPadron()->first();

        return trim(implode(' ', array_filter([
            $cometido->cargo_funcion,
            $cometido->estamento,
            $cometido->calidad_juridica,
            $funcionarioPadron?->escalafon,
            $funcionarioPadron?->estatuto,
            $funcionarioPadron?->tipocontrato,
        ])));
    }

    private function categoriaViaticoAaee(?string $texto): ?string
    {
        $normalizado = $this->normaliza($texto);

        if (str_contains($normalizado, 'JUNJI') || str_contains($normalizado, 'DIRECTORA')) {
            return 'Directora Junji';
        }
        if (str_contains($normalizado, 'PARVULO') || str_contains($normalizado, 'PARVULOS') || str_contains($normalizado, 'EDUCADORA')) {
            return 'Educadora de Párvulos';
        }
        if (str_contains($normalizado, 'PROFESIONAL')) {
            return 'Profesional';
        }
        if (str_contains($normalizado, 'TECNICO')) {
            return 'Técnico';
        }
        if (str_contains($normalizado, 'ADMINISTRATIVO')) {
            return 'Administrativo';
        }
        if (str_contains($normalizado, 'AUXILIAR')) {
            return 'Auxiliar';
        }

        return null;
    }

    private function porcentajeAutomaticoViatico(CometidoFuncionario $cometido, int $totalDias, int $index): int
    {
        if ($totalDias <= 0) {
            return 0;
        }

        if ($totalDias === 1) {
            return $this->cubreTramoAlimentacionViatico($cometido) ? 40 : 0;
        }

        if ((bool) $cometido->contempla_alojamiento) {
            return 40;
        }

        return $index === $totalDias - 1 ? 40 : 100;
    }

    private function cubreTramoAlimentacionViatico(CometidoFuncionario $cometido): bool
    {
        if (! $cometido->hora_salida || ! $cometido->hora_regreso) {
            return false;
        }

        try {
            $fechaBase = $cometido->fecha_desde ? Carbon::parse($cometido->fecha_desde)->toDateString() : now()->toDateString();
            $salida = Carbon::parse($fechaBase . ' ' . $cometido->hora_salida);
            $regreso = Carbon::parse($fechaBase . ' ' . $cometido->hora_regreso);

            if ($regreso->lessThanOrEqualTo($salida)) {
                $regreso->addDay();
            }

            $inicioTramo = Carbon::parse($fechaBase . ' 12:00');
            $finTramo = Carbon::parse($fechaBase . ' 15:00');

            return $salida->lessThanOrEqualTo($inicioTramo) && $regreso->greaterThanOrEqualTo($finTramo);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function diasCometido(CometidoFuncionario $cometido): array
    {
        if (! $cometido->fecha_desde || ! $cometido->fecha_hasta) {
            return [];
        }

        $desde = Carbon::parse($cometido->fecha_desde)->startOfDay();
        $hasta = Carbon::parse($cometido->fecha_hasta)->startOfDay();
        if ($hasta->lt($desde)) {
            return [];
        }

        $dias = [];
        $cursor = $desde->copy();
        $numero = 1;
        while ($cursor->lte($hasta) && $numero <= 120) {
            $dias[] = [
                'numero' => $numero,
                'fecha' => $cursor->toDateString(),
                'label' => $cursor->format('d-m-Y'),
            ];
            $cursor->addDay();
            $numero++;
        }

        return $dias;
    }

    private function normaliza(?string $texto): string
    {
        $texto = Str::ascii(Str::lower((string) $texto));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }

    private function topeReembolsoCdp(CometidoFuncionario $cometido): ?int
    {
        foreach (['total_reembolso_autorizado', 'monto_reembolso_autorizado', 'reembolso_total_autorizado', 'cdp_total_reembolso', 'total_reembolso'] as $field) {
            if (isset($cometido->{$field}) && $cometido->{$field} !== null) {
                return (int) $cometido->{$field};
            }
        }

        if (Schema::hasTable('cometidos_funcionarios_cdp_montos')) {
            try {
                $total = DB::table('cometidos_funcionarios_cdp_montos')
                    ->where('cometido_funcionario_id', $cometido->id)
                    ->where(function ($q) {
                        $q->where('tipo', 'reembolso')->orWhere('beneficio', 'reembolso');
                    })
                    ->sum('monto');
                return $total > 0 ? (int) $total : null;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }


    private function requiereInformeParaDaf(CometidoFuncionario $cometido): bool
    {
        return (bool) $cometido->solicita_reembolso;
    }

    private function informeCometidoAprobado(CometidoFuncionario $cometido, ?CometidoFuncionarioInforme $informe = null): bool
    {
        return (bool) $informe && in_array((string) $informe->estado_informe, ['aprobado_jefatura', 'informe_aprobado', 'aprobado'], true)
            || in_array((string) $cometido->estado_viatico, ['informe_aprobado'], true)
            || in_array((string) $cometido->estado, ['informe_aprobado'], true);
    }

    private function puedeCompletarInformeReembolso(CometidoFuncionario $cometido, ?CometidoFuncionarioInforme $informe = null): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($informe && ! in_array((string) $informe->estado_informe, ['observado_jefatura', 'informe_observado'], true)) {
            return false;
        }

        $activeRole = session('active_role') ?? session('rol_activo') ?? ($user->rol ?? null);
        $esSolicitante = (int) $cometido->user_id === (int) $user->id;

        return ($activeRole === 'admin')
            || ($esSolicitante && in_array($activeRole, ['funcionario_ac', 'funcionario_estab'], true) && $this->cometidoTieneEtapaInformeReembolsoDisponible($cometido));
    }

    private function bloqueaDafPorInforme(CometidoFuncionario $cometido, ?CometidoFuncionarioRendicion $rendicion = null, ?CometidoFuncionarioInforme $informe = null): bool
    {
        return (bool) $rendicion && $this->requiereInformeParaDaf($cometido) && ! $this->informeCometidoAprobado($cometido, $informe);
    }

    private function cometidoTieneEtapaInformeReembolsoDisponible(CometidoFuncionario $cometido): bool
    {
        return (bool) $cometido->solicita_reembolso
            && in_array((string) ($cometido->estado_reembolso ?: $cometido->estado), [
                'pendiente_rendicion',
                'pendiente_rendicion_informe',
                'en_rendicion_reembolso',
                'rendicion_enviada',
                'rendicion_enviada_pendiente_informe',
                'informe_observado',
            ], true);
    }

    private function estadoPosteriorRendicionConInforme(CometidoFuncionario $cometido, bool $informeAprobado, bool $requiereRexCgrAc): string
    {
        if ($cometido->esAdministracionCentral()) {
            if ($requiereRexCgrAc) {
                return 'en_gdp_rex_cgr';
            }

            return $informeAprobado ? 'en_revision_daf_rendicion' : 'rendicion_enviada_pendiente_informe';
        }

        return 'en_gdp_rex_cgr';
    }

    private function puedeRendir(CometidoFuncionario $cometido): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        if ($this->userHasAnyRole(['admin'])) {
            return true;
        }

        $activeRole = session('active_role') ?? session('rol_activo') ?? ($user->rol ?? null);

        if ($cometido->esAdministracionCentral()) {
            return $activeRole === 'funcionario_ac'
                && (int) $cometido->user_id === (int) $user->id
                && in_array((string) ($cometido->estado_reembolso ?? $cometido->estado), ['en_gdp_rex_cgr', 'pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso', 'rendicion_enviada_pendiente_informe', 'rendicion_observada_daf'], true);
        }

        return $activeRole === 'funcionario_estab';
    }

    private function userHasAnyRole(array $roles): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $activeRole = session('active_role') ?? session('rol_activo') ?? ($user->rol ?? null);
        if ($activeRole && in_array($activeRole, $roles, true)) {
            return true;
        }

        foreach (['hasRole', 'hasAnyRole'] as $method) {
            if (method_exists($user, $method)) {
                try {
                    if ($method === 'hasAnyRole' && $user->{$method}($roles)) {
                        return true;
                    }
                    foreach ($roles as $role) {
                        if ($method === 'hasRole' && $user->{$method}($role)) {
                            return true;
                        }
                    }
                } catch (\Throwable $e) {
                    // Compatibility fallback below.
                }
            }
        }

        return false;
    }

    private function storeComprobantes(Request $request, CometidoFuncionario $cometido): array
    {
        $items = (array) $request->input('comprobantes', []);
        $archivos = (array) $request->file('comprobantes', []);
        $documentos = [];

        foreach ($items as $index => $item) {
            $archivo = $archivos[$index]['archivo'] ?? null;
            if (! $archivo) {
                continue;
            }

            $path = $this->putFile($archivo, $cometido, 'rendicion');
            $documentos[] = [
                'path' => $path,
                'original_name' => $archivo->getClientOriginalName(),
                'fecha_documento' => $item['fecha_documento'] ?? null,
                'monto_documento' => isset($item['monto_documento']) ? (int) $item['monto_documento'] : null,
                'detalle_gasto' => $item['detalle_gasto'] ?? null,
            ];
        }

        return $documentos;
    }

    private function storeFiles(Request $request, string $field, CometidoFuncionario $cometido, string $folder): array
    {
        $paths = [];
        foreach ((array) $request->file($field, []) as $file) {
            if (! $file) {
                continue;
            }
            $paths[] = $this->putFile($file, $cometido, $folder);
        }
        return $paths;
    }

    private function storeFile(Request $request, string $field, CometidoFuncionario $cometido, string $folder): ?string
    {
        $file = $request->file($field);
        return $file ? $this->putFile($file, $cometido, $folder) : null;
    }

    private function putFile($file, CometidoFuncionario $cometido, string $folder): string
    {
        $ext = $file->getClientOriginalExtension();
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $filename = now()->format('YmdHis') . '_' . Str::random(8) . '_' . $name . ($ext ? '.' . $ext : '');
        return $file->storeAs("tramites/cometidos-funcionarios/{$cometido->id}/{$folder}", $filename, 'public');
    }

    private function actualizarEstadoCometido(CometidoFuncionario $cometido, string $estado): void
    {
        if (! Schema::hasColumn($cometido->getTable(), 'estado')) {
            return;
        }

        $updates = ['estado' => $estado];
        if ((bool) $cometido->solicita_reembolso && Schema::hasColumn($cometido->getTable(), 'estado_reembolso')) {
            $updates['estado_reembolso'] = $estado;
        }

        $cometido->forceFill($updates)->save();
    }


    private function cerrarCometidoSiFlujoFinalizado(CometidoFuncionario $cometido, ?string $observacion = null): bool
    {
        $cometido->refresh();

        if ((string) $cometido->estado === 'cerrado') {
            return false;
        }

        $estadoAnterior = (string) $cometido->estado;
        $requiereViatico = (bool) $cometido->solicita_viatico;
        $requiereReembolso = (bool) $cometido->solicita_reembolso;

        $viaticoFinalizado = ! $requiereViatico
            || in_array((string) ($cometido->estado_viatico ?? ''), ['viatico_pagado', 'pagado'], true)
            || ! empty($cometido->fecha_pago_viatico);

        $reembolsoFinalizado = ! $requiereReembolso
            || in_array((string) ($cometido->estado_reembolso ?? ''), ['reembolso_pagado', 'cerrado_sin_pago_reembolso'], true)
            || ! empty($cometido->fecha_pago_reembolso);

        if (! $viaticoFinalizado || ! $reembolsoFinalizado) {
            return false;
        }

        $cometido->forceFill(['estado' => 'cerrado'])->save();

        $this->registrarHistorial(
            $cometido,
            'cerrado',
            'Trámite de cometido funcionario cerrado automáticamente al finalizar el flujo financiero',
            $observacion ?: 'Cierre automático por finalización de viático y/o reembolso.',
            $estadoAnterior
        );

        return true;
    }

    private function registrarHistorial(CometidoFuncionario $cometido, string $estadoNuevo, string $accion, ?string $observacion = null, ?string $estadoAnterior = null): void
    {
        if (! Schema::hasTable('cometido_funcionario_historiales')) {
            return;
        }

        $table = 'cometido_funcionario_historiales';
        $payload = [];
        $map = [
            'cometido_funcionario_id' => $cometido->id,
            'estado_anterior' => $estadoAnterior ?? $cometido->getOriginal('estado') ?? null,
            'estado_nuevo' => $estadoNuevo,
            'accion' => $accion,
            'observacion' => $observacion,
            'observaciones' => $observacion,
            'user_id' => Auth::id(),
            'usuario_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($map as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $payload[$column] = $value;
            }
        }

        if (! empty($payload)) {
            try {
                DB::table($table)->insert($payload);
            } catch (\Throwable $e) {
                // La falta de una columna no debe bloquear la acción principal.
            }
        }
    }
}
