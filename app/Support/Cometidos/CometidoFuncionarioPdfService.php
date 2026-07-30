<?php

namespace App\Support\Cometidos;

use App\Models\CometidoFuncionario;
use App\Models\CometidoFuncionarioDocumentoGenerado;
use App\Models\CometidoFuncionarioFirma;
use App\Models\CometidoFuncionarioInforme;
use App\Models\CometidoFuncionarioPasajeAereo;
use App\Models\CometidoFuncionarioRendicion;
use App\Models\CometidoFuncionarioResolucionReembolso;
use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use App\Models\ViaticoReembolsoValor;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CometidoFuncionarioPdfService
{
    public function generarSolicitudCometido(CometidoFuncionario $cometido, ?User $user = null, bool $firmarSolicitante = false, ?Request $request = null): CometidoFuncionarioDocumentoGenerado
    {
        $cometido->loadMissing(['firmasDigitales', 'documentosGenerados', 'funcionarioAcAutorizado']);
        $numero = $cometido->numero_cometido_interno ?: $this->numeroCometido($cometido);
        if (! $cometido->numero_cometido_interno) {
            $cometido->update(['numero_cometido_interno' => $numero]);
        }

        $documento = $this->documentoBase($cometido, 'solicitud_cometido', $numero, $user);

        if ($firmarSolicitante && $user) {
            $this->registrarFirma($cometido, $documento, $user, 'solicitante', false, $request);
        }

        return $this->renderPdf($documento, 'pdf.cometidos.solicitud-cometido', [
            'cometido' => $cometido->fresh(['firmasDigitales', 'funcionarioAcAutorizado']),
            'documento' => $documento->fresh('firmas'),
            'validacionUrl' => route('documentos.validar', $documento->codigo_validacion),
            'qrDataUri' => SimpleQrCode::dataUri(route('documentos.validar', $documento->codigo_validacion)),
            'logoDataUri' => $this->logoDataUri(),
            'viaticoPdfData' => $this->viaticoPdfData($cometido),
        ]);
    }

    public function regenerarSolicitudCometido(CometidoFuncionario $cometido, ?User $user = null, ?Request $request = null): CometidoFuncionarioDocumentoGenerado
    {
        $cometido->loadMissing(['firmasDigitales', 'documentosGenerados', 'funcionarioAcAutorizado']);
        $numero = $cometido->numero_cometido_interno ?: $this->numeroCometido($cometido);
        if (! $cometido->numero_cometido_interno) {
            $cometido->update(['numero_cometido_interno' => $numero]);
        }

        $documento = $this->documentoBase($cometido, 'solicitud_cometido', $numero, $user);

        if (! $documento->firmas()->where('tipo_firma', 'solicitante')->exists()) {
            $solicitante = $user && (int) $user->id === (int) $cometido->user_id ? $user : User::find($cometido->user_id);
            $this->registrarFirma($cometido, $documento, $solicitante, 'solicitante', false, $request, $cometido->funcionarioAcAutorizado);
        }

        return $this->renderPdf($documento, 'pdf.cometidos.solicitud-cometido', [
            'cometido' => $cometido->fresh(['firmasDigitales', 'funcionarioAcAutorizado']),
            'documento' => $documento->fresh('firmas'),
            'validacionUrl' => route('documentos.validar', $documento->codigo_validacion),
            'qrDataUri' => SimpleQrCode::dataUri(route('documentos.validar', $documento->codigo_validacion)),
            'logoDataUri' => $this->logoDataUri(),
            'viaticoPdfData' => $this->viaticoPdfData($cometido),
        ]);
    }

    public function agregarFirmaJefatura(CometidoFuncionario $cometido, FuncionarioAcAutorizado $autorizador, ?User $user, bool $esSubrogante, ?Request $request = null): void
    {
        $documento = $cometido->documentosGenerados()->where('tipo_documento', 'solicitud_cometido')->latest()->first();
        if (! $documento) {
            $documento = $this->generarSolicitudCometido($cometido, $user, false, $request);
        }

        $this->registrarFirma($cometido, $documento, $user, $esSubrogante ? 'subrogante_autorizador' : 'jefatura_autorizadora', $esSubrogante, $request, $autorizador);
        $this->renderPdf($documento, 'pdf.cometidos.solicitud-cometido', [
            'cometido' => $cometido->fresh(['firmasDigitales', 'funcionarioAcAutorizado']),
            'documento' => $documento->fresh('firmas'),
            'validacionUrl' => route('documentos.validar', $documento->codigo_validacion),
            'qrDataUri' => SimpleQrCode::dataUri(route('documentos.validar', $documento->codigo_validacion)),
            'logoDataUri' => $this->logoDataUri(),
            'viaticoPdfData' => $this->viaticoPdfData($cometido),
        ]);
    }

    public function generarSolicitudPedido(CometidoFuncionario $cometido, CometidoFuncionarioPasajeAereo $pasaje, ?User $user = null): CometidoFuncionarioDocumentoGenerado
    {
        $numero = $pasaje->numero_solicitud_pedido ?: $this->numeroSolicitudPedido($pasaje);
        if (! $pasaje->numero_solicitud_pedido) {
            $pasaje->update(['numero_solicitud_pedido' => $numero]);
        }

        $documento = $this->documentoBase($cometido, 'solicitud_pedido_pasaje', $numero, $user);
        $this->sincronizarFirmasSolicitudCometidoEnSolicitudPedido($cometido, $documento, $user);

        $documento = $this->renderPdf($documento, 'pdf.cometidos.solicitud-pedido-pasaje', [
            'cometido' => $cometido->fresh(['firmasDigitales', 'funcionarioAcAutorizado']),
            'pasaje' => $pasaje->fresh(),
            'documento' => $documento->fresh('firmas'),
            'validacionUrl' => route('documentos.validar', $documento->codigo_validacion),
            'qrDataUri' => SimpleQrCode::dataUri(route('documentos.validar', $documento->codigo_validacion)),
            'logoDataUri' => $this->logoDataUri(),
        ]);
        $pasaje->update(['solicitud_pedido_pdf_path' => $documento->archivo_pdf_path]);

        return $documento;
    }


    public function generarInformeCometido(CometidoFuncionario $cometido, CometidoFuncionarioInforme $informe, ?User $user = null, ?Request $request = null): CometidoFuncionarioDocumentoGenerado
    {
        $cometido->loadMissing(['firmasDigitales', 'documentosGenerados', 'funcionarioAcAutorizado', 'establecimiento', 'solicitante']);
        $numeroBase = $cometido->numero_cometido_interno ?: $this->numeroCometido($cometido);
        if (! $cometido->numero_cometido_interno) {
            $cometido->update(['numero_cometido_interno' => $numeroBase]);
        }

        $numero = 'INF-' . $numeroBase;
        $documento = $this->documentoBase($cometido, 'informe_cometido', $numero, $user);

        if (! $documento->firmas()->where('tipo_firma', 'funcionario_informe')->exists()) {
            $solicitante = $user && (int) $user->id === (int) $cometido->user_id
                ? $user
                : User::find($cometido->user_id);

            $this->registrarFirma($cometido, $documento, $solicitante, 'funcionario_informe', false, $request, $cometido->funcionarioAcAutorizado);
        }

        $documentoFirmas = $documento->fresh('firmas');

        return $this->renderPdf($documento, 'pdf.cometidos.informe-cometido', [
            'cometido' => $cometido->fresh(['firmasDigitales', 'funcionarioAcAutorizado', 'establecimiento', 'solicitante']),
            'informe' => $informe->fresh(['enviadoPor', 'jefaturaRevisora']),
            'documento' => $documentoFirmas,
            'validacionUrl' => route('documentos.validar', $documento->codigo_validacion),
            'qrDataUri' => SimpleQrCode::dataUri(route('documentos.validar', $documento->codigo_validacion)),
            'logoDataUri' => $this->logoDataUri(),
            'financialPdfData' => $this->informeFinancialData($cometido),
            'jefaturaPdfData' => $this->informeJefaturaPdfData($cometido, $informe, $documentoFirmas),
        ]);
    }

    public function firmarInformeCometidoJefatura(CometidoFuncionario $cometido, CometidoFuncionarioInforme $informe, User $jefatura, ?Request $request = null): CometidoFuncionarioDocumentoGenerado
    {
        $cometido->loadMissing(['firmasDigitales', 'documentosGenerados', 'funcionarioAcAutorizado', 'establecimiento', 'solicitante']);
        $documento = $cometido->documentosGenerados()->where('tipo_documento', 'informe_cometido')->latest()->first();

        if (! $documento) {
            $documento = $this->generarInformeCometido($cometido, $informe, $jefatura, $request);
        }

        if (! $documento->firmas()->where('tipo_firma', 'jefatura_informe')->exists()) {
            $this->registrarFirma($cometido, $documento, $jefatura, 'jefatura_informe', false, $request, null);
        }

        $documentoFirmas = $documento->fresh('firmas');

        return $this->renderPdf($documento, 'pdf.cometidos.informe-cometido', [
            'cometido' => $cometido->fresh(['firmasDigitales', 'funcionarioAcAutorizado', 'establecimiento', 'solicitante']),
            'informe' => $informe->fresh(['enviadoPor', 'jefaturaRevisora']),
            'documento' => $documentoFirmas,
            'validacionUrl' => route('documentos.validar', $documento->codigo_validacion),
            'qrDataUri' => SimpleQrCode::dataUri(route('documentos.validar', $documento->codigo_validacion)),
            'logoDataUri' => $this->logoDataUri(),
            'financialPdfData' => $this->informeFinancialData($cometido),
            'jefaturaPdfData' => $this->informeJefaturaPdfData($cometido, $informe, $documentoFirmas),
        ]);
    }

    public function registrarFirma(CometidoFuncionario $cometido, CometidoFuncionarioDocumentoGenerado $documento, ?User $user, string $tipoFirma, bool $esSubrogante, ?Request $request = null, ?FuncionarioAcAutorizado $funcionarioAc = null): CometidoFuncionarioFirma
    {
        if ($documento->firmas()->where('tipo_firma', $tipoFirma)->exists()) {
            return $documento->firmas()->where('tipo_firma', $tipoFirma)->latest()->first();
        }

        $funcionarioAc = $funcionarioAc ?: $cometido->funcionarioAcAutorizado;
        $nombre = $funcionarioAc?->nombre_completo ?: ($user?->nombre_completo ?? $user?->display_name ?? $user?->email ?? 'Usuario');
        $rut = $funcionarioAc?->rut_completo ?: ($user?->rut ?? null);
        $token = Str::random(50);

        $firma = CometidoFuncionarioFirma::create([
            'cometido_funcionario_id' => $cometido->id,
            'documento_generado_id' => $documento->id,
            'user_id' => $user?->id,
            'funcionario_ac_autorizado_id' => $funcionarioAc?->id,
            'tipo_firma' => $tipoFirma,
            'rol_firmante' => method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null,
            'nombre_firmante' => $nombre,
            'rut_firmante' => $rut,
            'cargo_firmante' => $funcionarioAc?->cargo_funcion,
            'dependencia_firmante' => $funcionarioAc?->subdireccion_dependencia,
            'es_subrogante' => $esSubrogante,
            'ip_firma' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'fecha_firma' => now(),
            'token_firma' => $token,
            'hash_firma' => hash('sha256', implode('|', [$cometido->id, $documento->id, $tipoFirma, $rut, now()->timestamp, $token])),
        ]);

        return $firma;
    }

    private function documentoBase(CometidoFuncionario $cometido, string $tipo, string $numero, ?User $user): CometidoFuncionarioDocumentoGenerado
    {
        $documento = $cometido->documentosGenerados()->where('tipo_documento', $tipo)->latest()->first();
        if (! $documento) {
            $documento = CometidoFuncionarioDocumentoGenerado::create([
                'cometido_funcionario_id' => $cometido->id,
                'tipo_documento' => $tipo,
                'numero_documento' => $numero,
                'codigo_validacion' => strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4)),
                'token_validacion' => Str::random(64),
                'estado' => 'vigente',
                'emitido_por_user_id' => $user?->id,
                'emitido_at' => now(),
            ]);
        }

        return $documento;
    }

    private function sincronizarFirmasSolicitudCometidoEnSolicitudPedido(CometidoFuncionario $cometido, CometidoFuncionarioDocumentoGenerado $documentoPedido, ?User $user): void
    {
        $documentoSolicitud = $cometido->documentosGenerados()
            ->where('tipo_documento', 'solicitud_cometido')
            ->latest()
            ->with('firmas')
            ->first();

        $tiposRequeridos = ['solicitante', 'jefatura_autorizadora', 'subrogante_autorizador'];
        $firmasBase = collect();

        if ($documentoSolicitud) {
            $firmasBase = $documentoSolicitud->firmas
                ->whereIn('tipo_firma', $tiposRequeridos)
                ->sortBy(function (CometidoFuncionarioFirma $firma) {
                    return $firma->tipo_firma === 'solicitante' ? 0 : 1;
                })
                ->values();
        }

        if ($firmasBase->where('tipo_firma', 'solicitante')->isEmpty()) {
            $solicitanteUser = $user && (int) $user->id === (int) $cometido->user_id
                ? $user
                : User::find($cometido->user_id);

            $this->registrarFirma($cometido, $documentoPedido, $solicitanteUser, 'solicitante', false, null, $cometido->funcionarioAcAutorizado);
        }

        foreach ($firmasBase as $firmaBase) {
            if ($documentoPedido->firmas()->where('tipo_firma', $firmaBase->tipo_firma)->exists()) {
                continue;
            }

            $token = Str::random(50);
            CometidoFuncionarioFirma::create([
                'cometido_funcionario_id' => $cometido->id,
                'documento_generado_id' => $documentoPedido->id,
                'user_id' => $firmaBase->user_id,
                'funcionario_ac_autorizado_id' => $firmaBase->funcionario_ac_autorizado_id,
                'tipo_firma' => $firmaBase->tipo_firma,
                'rol_firmante' => $firmaBase->rol_firmante,
                'nombre_firmante' => $firmaBase->nombre_firmante,
                'rut_firmante' => $firmaBase->rut_firmante,
                'cargo_firmante' => $firmaBase->cargo_firmante,
                'dependencia_firmante' => $firmaBase->dependencia_firmante,
                'es_subrogante' => (bool) $firmaBase->es_subrogante,
                'ip_firma' => $firmaBase->ip_firma,
                'user_agent' => $firmaBase->user_agent,
                'fecha_firma' => $firmaBase->fecha_firma ?: now(),
                'token_firma' => $token,
                'hash_firma' => hash('sha256', implode('|', [$cometido->id, $documentoPedido->id, $firmaBase->tipo_firma, $firmaBase->rut_firmante, now()->timestamp, $token])),
            ]);
        }
    }


    private function viaticoPdfData(CometidoFuncionario $cometido): array
    {
        $cometido->loadMissing(['funcionarioAcAutorizado']);
        $fechaReferencia = $cometido->fecha_desde ?: $cometido->fecha_solicitud ?: now();
        $fecha = Carbon::parse($fechaReferencia)->toDateString();
        $esReembolso = (bool) $cometido->solicita_reembolso && ! (bool) $cometido->solicita_viatico;
        $catalogo = $cometido->solicita_viatico ? $this->catalogoAutomaticoViaticoPdf($cometido, $fecha) : null;
        $dias = $this->diasCometidoPdf($cometido);
        $diasConPernoctar = 0;
        $diasSinPernoctar = 0;
        $diasServicioColacion = 0;
        $totalConPernoctar = 0;
        $totalSinPernoctar = 0;
        $totalServicioColacion = 0;
        $total = 0;

        foreach ($dias as $index => $dia) {
            $porcentaje = ($catalogo && ! $esReembolso) ? $this->porcentajeAutomaticoViaticoPdf($cometido, count($dias), $index) : 0;
            $monto = 0;
            if ($catalogo && $porcentaje > 0) {
                $monto = $this->valorCatalogoPorPorcentajePdf($catalogo, $porcentaje);
            }

            if ($porcentaje === 100) {
                $diasConPernoctar++;
                $totalConPernoctar += $monto;
            } elseif ($porcentaje === 60) {
                $diasServicioColacion++;
                $totalServicioColacion += $monto;
            } elseif ($porcentaje === 40) {
                $diasSinPernoctar++;
                $totalSinPernoctar += $monto;
            }
            $total += $monto;
        }

        return [
            'mostrar' => (bool) ($cometido->solicita_viatico || $cometido->solicita_reembolso),
            'es_reembolso' => $esReembolso,
            'categoria' => $catalogo ? ($catalogo->estamento . ' / ' . $catalogo->cargo_funcion) : '—',
            'cargo_funcion_referencial' => $catalogo?->cargo_funcion,
            'dias_con_pernoctar' => $esReembolso ? 0 : $diasConPernoctar,
            'dias_sin_pernoctar' => $esReembolso ? 0 : $diasSinPernoctar,
            'dias_servicio_colacion' => $esReembolso ? 0 : $diasServicioColacion,
            'dias_solo_pernoctacion' => $esReembolso ? 0 : $diasServicioColacion,
            'dias_solo_alojamiento' => 0,
            'total_con_pernoctar' => $esReembolso ? 0 : $totalConPernoctar,
            'total_sin_pernoctar' => $esReembolso ? 0 : $totalSinPernoctar,
            'total_servicio_colacion' => $esReembolso ? 0 : $totalServicioColacion,
            'total_solo_pernoctacion' => $esReembolso ? 0 : $totalServicioColacion,
            'total_solo_alojamiento' => 0,
            'total' => $esReembolso ? 0 : $total,
            'rangos' => $this->rangosCodigoAdministrativoPdf($fecha, false),
            'nota' => $esReembolso
                ? 'Cometido sólo con reembolso: la tabla de cálculo de viático se informa sin valores asociados.'
                : 'Cuadro a visar por Subdirección de Planificación y Control, dependiendo de disponibilidad presupuestaria.',
        ];
    }

    private function rangosCodigoAdministrativoPdf(string $fecha, bool $forzarCero = false): array
    {
        $rangos = ['1° al 4°', '5° al 10°', '11° al 21°', '22° al 31°'];
        return collect($rangos)->map(function (string $rango) use ($fecha, $forzarCero) {
            $valor = $forzarCero ? null : $this->catalogoPorEstamentoCargoPdf('Código Administrativo', $rango, $fecha);
            return [
                'rango' => $rango,
                'valor_100' => $forzarCero ? 0 : (int) ($valor->valor_100 ?? 0),
                'valor_60' => $forzarCero ? 0 : (int) ($valor->valor_60 ?? 0),
                'valor_40' => $forzarCero ? 0 : (int) ($valor->valor_40 ?? 0),
            ];
        })->all();
    }

    private function catalogoAutomaticoViaticoPdf(CometidoFuncionario $cometido, string $fecha): ?ViaticoReembolsoValor
    {
        if ($cometido->esAdministracionCentral()) {
            $reglaAc = $this->reglaCatalogoFuncionarioAcPdf($cometido);
            if ($reglaAc) {
                return $this->catalogoPorEstamentoCargoPdf($reglaAc['estamento'], $reglaAc['cargo_funcion'], $fecha);
            }
        }

        $categoria = $this->categoriaViaticoAaeePdf($cometido->cargo_funcion ?? $cometido->estamento);
        if (! $categoria) {
            return null;
        }

        return $this->catalogoPorEstamentoCargoPdf('AAEE', $categoria, $fecha);
    }

    private function reglaCatalogoFuncionarioAcPdf(CometidoFuncionario $cometido): ?array
    {
        $funcionarioAc = $cometido->relationLoaded('funcionarioAcAutorizado')
            ? $cometido->funcionarioAcAutorizado
            : $cometido->funcionarioAcAutorizado()->first();

        $grado = $this->extraerGradoNumericoPdf($funcionarioAc?->grado ?? '');
        if ($grado !== null) {
            $tramo = $this->tramoCodigoAdministrativoPorGradoPdf($grado);
            if ($tramo) {
                return [
                    'estamento' => 'Código Administrativo',
                    'cargo_funcion' => $tramo,
                ];
            }
        }

        $escalafonFuncionario = $funcionarioAc?->escalafon
            ?: $this->extraerDatoAcDesdeObservacionPdf($funcionarioAc?->observaciones, 'escalafon')
            ?: '';
        $escalafon = $this->normalizaPdf($escalafonFuncionario . ' ' . ($cometido->estamento ?? ''));
        if (str_contains($escalafon, 'DOCENTE')) {
            return [
                'estamento' => 'Docente',
                'cargo_funcion' => 'Docentes',
            ];
        }

        return null;
    }

    private function extraerDatoAcDesdeObservacionPdf(?string $observaciones, string $campo): ?string
    {
        $observaciones = trim((string) $observaciones);
        if ($observaciones === '') {
            return null;
        }

        $patrones = [
            'unidad' => '/Unidad:\s*(.*?)(?:\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'subdireccion_dependencia' => '/Subdirecci[oó]n dependencia:\s*(.*?)(?:\s+Unidad:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'escalafon' => '/Escalaf[oó]n:\s*(.*?)(?:\s+Unidad:|\s+Subdirecci[oó]n dependencia:|\s+Calidad jur[ií]dica:|$)/iu',
            'calidad_juridica' => '/Calidad jur[ií]dica:\s*(.*?)(?:\s+Unidad:|\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|$)/iu',
        ];

        if (! isset($patrones[$campo])) {
            return null;
        }

        if (preg_match($patrones[$campo], $observaciones, $coincidencias)) {
            $valor = trim(preg_replace('/\s+/', ' ', $coincidencias[1] ?? ''));
            return $valor !== '' ? $valor : null;
        }

        return null;
    }

    private function catalogoPorEstamentoCargoPdf(string $estamento, string $cargoFuncion, string $fecha): ?ViaticoReembolsoValor
    {
        return ViaticoReembolsoValor::query()
            ->activos()
            ->whereDate('vigente_desde', '<=', $fecha)
            ->whereDate('vigente_hasta', '>=', $fecha)
            ->get()
            ->first(function (ViaticoReembolsoValor $valor) use ($estamento, $cargoFuncion) {
                return $this->normalizaPdf($valor->estamento) === $this->normalizaPdf($estamento)
                    && $this->normalizaPdf($valor->cargo_funcion) === $this->normalizaPdf($cargoFuncion);
            });
    }

    private function diasCometidoPdf(CometidoFuncionario $cometido): array
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
            $dias[] = ['numero' => $numero, 'fecha' => $cursor->toDateString()];
            $cursor->addDay();
            $numero++;
        }

        return $dias;
    }


    private function valorCatalogoPorPorcentajePdf(ViaticoReembolsoValor $catalogo, int $porcentaje): int
    {
        return match ($porcentaje) {
            100 => (int) $catalogo->valor_100,
            60 => (int) ($catalogo->valor_60 ?? 0),
            40 => (int) $catalogo->valor_40,
            default => 0,
        };
    }

    private function porcentajeAutomaticoViaticoPdf(CometidoFuncionario $cometido, int $totalDias, int $index): int
    {
        if ($totalDias <= 0) {
            return 0;
        }
        if ((string) ($cometido->servicio_contempla_colacion ?? 'no_informado') === 'si') {
            return $index === $totalDias - 1 ? 0 : 60;
        }
        if ($totalDias === 1) {
            return $this->cubreTramoAlimentacionViaticoPdf($cometido) ? 40 : 0;
        }
        if ((bool) $cometido->contempla_alojamiento) {
            return 40;
        }
        return $index === $totalDias - 1 ? 40 : 100;
    }

    private function cubreTramoAlimentacionViaticoPdf(CometidoFuncionario $cometido): bool
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

    private function extraerGradoNumericoPdf(?string $grado): ?int
    {
        if (preg_match('/\d+/', trim((string) $grado), $matches) !== 1) {
            return null;
        }
        $numero = (int) $matches[0];
        return $numero > 0 ? $numero : null;
    }

    private function tramoCodigoAdministrativoPorGradoPdf(int $grado): ?string
    {
        return match (true) {
            $grado >= 1 && $grado <= 4 => '1° al 4°',
            $grado >= 5 && $grado <= 10 => '5° al 10°',
            $grado >= 11 && $grado <= 21 => '11° al 21°',
            $grado >= 22 && $grado <= 31 => '22° al 31°',
            default => null,
        };
    }

    private function categoriaViaticoAaeePdf(?string $texto): ?string
    {
        $normalizado = $this->normalizaPdf($texto);
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

    private function normalizaPdf(?string $texto): string
    {
        return Str::upper(Str::ascii(trim((string) $texto)));
    }

    private function informeFinancialData(CometidoFuncionario $cometido): array
    {
        $montoTotalViatico = (int) ($cometido->monto_viatico_solicitado_director
            ?? $cometido->cdp_viatico_total
            ?? $cometido->monto_pagado_viatico
            ?? 0);
        $montoAnticipoViatico = (int) ($cometido->monto_anticipo_viatico ?? 0);
        $montoPendienteViatico = $cometido->monto_saldo_viatico !== null
            ? (int) $cometido->monto_saldo_viatico
            : max(0, $montoTotalViatico - $montoAnticipoViatico);

        $rendicion = CometidoFuncionarioRendicion::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();

        $resolucion = null;
        if ($rendicion) {
            $resolucion = CometidoFuncionarioResolucionReembolso::query()
                ->where('rendicion_id', $rendicion->id)
                ->latest('id')
                ->first();
        }

        if (! $resolucion) {
            $resolucion = CometidoFuncionarioResolucionReembolso::query()
                ->where('cometido_funcionario_id', $cometido->id)
                ->latest('id')
                ->first();
        }

        $montoTotalReembolso = (int) ($resolucion?->monto_resolucion
            ?? $rendicion?->monto_cdp_reembolso
            ?? $rendicion?->monto_autorizado_daf
            ?? $rendicion?->monto_rendido
            ?? 0);
        $montoPagadoReembolso = (int) ($resolucion?->monto_pagado_reembolso ?? 0);
        $montoPendienteReembolso = max(0, $montoTotalReembolso - $montoPagadoReembolso);

        return [
            'viatico' => [
                'mostrar' => (bool) $cometido->solicita_viatico,
                'anticipo' => $montoAnticipoViatico,
                'pendiente' => $montoPendienteViatico,
                'total' => $montoTotalViatico,
            ],
            'reembolso' => [
                'mostrar' => (bool) $cometido->solicita_reembolso,
                'anticipo' => 0,
                'pendiente' => $montoPendienteReembolso,
                'total' => $montoTotalReembolso,
            ],
        ];
    }

    private function informeJefaturaPdfData(CometidoFuncionario $cometido, CometidoFuncionarioInforme $informe, CometidoFuncionarioDocumentoGenerado $documento): array
    {
        $firmaJefatura = $documento->firmas
            ->where('tipo_firma', 'jefatura_informe')
            ->sortByDesc('fecha_firma')
            ->first();

        $rutJefatura = $informe->jefaturaRevisora?->rut_normalized
            ?? strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($firmaJefatura?->rut_firmante ?? '')));

        $jefaturaAc = null;
        if ($rutJefatura !== '') {
            $jefaturaAc = FuncionarioAcAutorizado::query()
                ->where('rut_normalizado', $rutJefatura)
                ->latest('id')
                ->first();
        }

        $esSubrogante = (bool) ($firmaJefatura?->es_subrogante ?? $cometido->autorizado_por_subrogante ?? false);
        $subdireccionTitular = $jefaturaAc?->subdireccion_dependencia
            ?: ($firmaJefatura?->dependencia_firmante ?: ($cometido->subdireccion_dependencia_ac ?: '—'));

        return [
            'nombre' => $firmaJefatura?->nombre_firmante ?: ($informe->jefaturaRevisora?->nombre_completo ?: 'Jefatura'),
            'rut' => $firmaJefatura?->rut_firmante ?: ($informe->jefaturaRevisora?->rut ?? '—'),
            'subdireccion' => $subdireccionTitular !== '—'
                ? $subdireccionTitular . ($esSubrogante ? ' (S)' : '')
                : ($esSubrogante ? '(S)' : '—'),
            'es_subrogante' => $esSubrogante,
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = resource_path('images/logo-andaliencosta.png');
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($contents);
    }

    private function renderPdf(CometidoFuncionarioDocumentoGenerado $documento, string $view, array $data): CometidoFuncionarioDocumentoGenerado
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
        $path = 'cometidos-funcionarios/documentos-generados/' . $documento->tipo_documento . '_' . $documento->id . '_' . now()->format('YmdHis') . '.pdf';
        Storage::put($path, $pdf->output());
        $hash = hash_file('sha256', Storage::path($path));
        if ($documento->archivo_pdf_path && Storage::exists($documento->archivo_pdf_path)) {
            Storage::delete($documento->archivo_pdf_path);
        }
        $documento->update([
            'archivo_pdf_path' => $path,
            'documento_hash' => $hash,
        ]);

        return $documento->fresh();
    }

    private function numeroCometido(CometidoFuncionario $cometido): string
    {
        return 'CF-AC-' . now()->format('Y') . '-' . str_pad((string) $cometido->id, 6, '0', STR_PAD_LEFT);
    }

    private function numeroSolicitudPedido(CometidoFuncionarioPasajeAereo $pasaje): string
    {
        return 'SP-' . now()->format('Y') . '-' . str_pad((string) $pasaje->id, 6, '0', STR_PAD_LEFT);
    }
}
