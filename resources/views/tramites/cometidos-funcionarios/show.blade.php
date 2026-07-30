@extends('layouts.app')

@section('content')
    @php
        $documentosPorTipo = $cometido->documentos->groupBy('tipo');
        $cdpDocumento = $documentosPorTipo->get('cdp', collect())->sortByDesc('id')->first();
        $citacionPreview = $documentosPorTipo->get('citacion_invitacion', collect())->sortByDesc('id')->first();
        $tipoDocumentoLabels = [
            'oficio' => 'Oficio',
            'formulario_cometido' => 'Formulario de Cometido',
            'citacion_invitacion' => 'Citación o invitación',
            'cdp' => 'Certificado de Disponibilidad Presupuestaria',
            'resolucion_cometido' => 'Resolución de cometido',
            'respaldo_rendicion' => 'Respaldo de rendición',
            'resolucion_pago_viatico' => 'Resolución de pago de viático',
            'otro' => 'Otro documento',
        ];

        $documentosFlujo = collect();

        foreach ($cometido->documentos->sortByDesc('id') as $documento) {
            $documentosFlujo->push([
                'key' => 'documento-' . $documento->id,
                'tipo' => $documento->tipo,
                'titulo' => $tipoDocumentoLabels[$documento->tipo] ?? ucfirst(str_replace('_', ' ', (string) $documento->tipo)),
                'nombre' => $documento->nombre_original,
                'size' => $documento->size ?? 0,
                'meta' => null,
                'view_url' => route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $documento]),
                'download_url' => route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $documento, 'download' => 1]),
                'is_cdp' => $documento->tipo === 'cdp',
                'source' => 'documentos',
            ]);
        }

        foreach ($cometido->documentosGenerados->sortByDesc('id') as $documentoGeneradoFlujo) {
            if (! $documentoGeneradoFlujo->archivo_pdf_path) {
                continue;
            }
            $documentosFlujo->push([
                'key' => 'documento-generado-' . $documentoGeneradoFlujo->id,
                'tipo' => $documentoGeneradoFlujo->tipo_documento,
                'titulo' => 'Documento generado - ' . \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $documentoGeneradoFlujo->tipo_documento)),
                'nombre' => ($documentoGeneradoFlujo->numero_documento ?: \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $documentoGeneradoFlujo->tipo_documento))) . '.pdf',
                'size' => null,
                'meta' => $documentoGeneradoFlujo->codigo_validacion ? 'Código de validación: ' . $documentoGeneradoFlujo->codigo_validacion : null,
                'view_url' => route('tramites.cometidos-funcionarios.documentos-generados.ver', [$cometido, $documentoGeneradoFlujo]),
                'download_url' => route('tramites.cometidos-funcionarios.documentos-generados.ver', [$cometido, $documentoGeneradoFlujo, 'download' => 1]),
                'is_cdp' => false,
                'source' => 'generado',
            ]);
        }

        $agregarDocumentoFlujo = function (string $key, ?string $path, string $titulo, string $tipo = 'otro', ?string $meta = null, ?int $size = null, bool $isCdp = false) use (&$documentosFlujo) {
            $path = trim((string) $path);
            if ($path === '') {
                return;
            }

            if ($documentosFlujo->contains(fn ($doc) => ($doc['key'] ?? null) === $key || ($doc['path'] ?? null) === $path)) {
                return;
            }

            $nombre = basename($path);
            $documentosFlujo->push([
                'key' => $key,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'nombre' => $nombre,
                'size' => $size,
                'meta' => $meta,
                'path' => $path,
                'view_url' => \Illuminate\Support\Facades\Storage::url($path),
                'download_url' => \Illuminate\Support\Facades\Storage::url($path) . '?download=1',
                'is_cdp' => $isCdp,
                'source' => 'flujo',
            ]);
        };

        $rendicionesFlujoDocs = \App\Models\CometidoFuncionarioRendicion::with('resolucion')
            ->where('cometido_funcionario_id', $cometido->id)
            ->orderByDesc('id')
            ->get();

        foreach ($rendicionesFlujoDocs as $rendicionFlujoDoc) {
            $documentosRespaldoRendicion = is_array($rendicionFlujoDoc->documentos_respaldo ?? null)
                ? $rendicionFlujoDoc->documentos_respaldo
                : [];

            foreach ($documentosRespaldoRendicion as $idxDocumentoRendicion => $documentoRendicion) {
                $pathDocumentoRendicion = is_array($documentoRendicion)
                    ? ($documentoRendicion['path'] ?? $documentoRendicion['archivo'] ?? $documentoRendicion['ruta'] ?? null)
                    : $documentoRendicion;

                $metaDocumentoRendicion = null;
                if (is_array($documentoRendicion)) {
                    $metaPartes = array_filter([
                        ! empty($documentoRendicion['fecha_documento']) ? 'Fecha: ' . $documentoRendicion['fecha_documento'] : null,
                        isset($documentoRendicion['monto_documento']) ? 'Monto: $' . number_format((int) $documentoRendicion['monto_documento'], 0, ',', '.') : null,
                        ! empty($documentoRendicion['detalle_gasto']) ? $documentoRendicion['detalle_gasto'] : null,
                    ]);
                    $metaDocumentoRendicion = implode(' · ', $metaPartes);
                }

                $agregarDocumentoFlujo(
                    'rendicion-' . $rendicionFlujoDoc->id . '-respaldo-' . $idxDocumentoRendicion,
                    $pathDocumentoRendicion,
                    'Documento fiscal de rendición',
                    'respaldo_rendicion',
                    $metaDocumentoRendicion ?: 'Comprobante fiscal cargado por el establecimiento.'
                );
            }

            $agregarDocumentoFlujo(
                'rendicion-' . $rendicionFlujoDoc->id . '-daf',
                $rendicionFlujoDoc->documento_daf_path ?? null,
                'Documento DAF aprobación de rendición',
                'documento_daf',
                $rendicionFlujoDoc->monto_autorizado_daf !== null ? 'Monto aprobado DAF: $' . number_format((int) $rendicionFlujoDoc->monto_autorizado_daf, 0, ',', '.') : null
            );

            $agregarDocumentoFlujo(
                'rendicion-' . $rendicionFlujoDoc->id . '-cdp',
                $rendicionFlujoDoc->documento_cdp_reembolso_path ?? null,
                'CDP rendición de reembolso',
                'cdp_rendicion',
                $rendicionFlujoDoc->referencia_cdp_reembolso ? 'Referencia: ' . $rendicionFlujoDoc->referencia_cdp_reembolso : null,
                null,
                true
            );

            if ($rendicionFlujoDoc->resolucion) {
                $agregarDocumentoFlujo(
                    'rendicion-' . $rendicionFlujoDoc->id . '-rex-reembolso',
                    $rendicionFlujoDoc->resolucion->documento_resolucion_path ?? null,
                    'REX de pago de reembolso',
                    'resolucion_pago_reembolso',
                    $rendicionFlujoDoc->resolucion->numero_resolucion ? 'Resolución: ' . $rendicionFlujoDoc->resolucion->numero_resolucion : null
                );

                $agregarDocumentoFlujo(
                    'rendicion-' . $rendicionFlujoDoc->id . '-pago-reembolso',
                    $rendicionFlujoDoc->resolucion->documento_pago_path ?? null,
                    'Comprobante de pago de reembolso',
                    'pago_reembolso',
                    $rendicionFlujoDoc->resolucion->monto_pagado_reembolso !== null ? 'Monto pagado: $' . number_format((int) $rendicionFlujoDoc->resolucion->monto_pagado_reembolso, 0, ',', '.') : null
                );
            }
        }

        $activeRoleVista = strtolower(trim((string) ($activeRole ?? '')));

        $funcionarioAcResumen = $cometido->funcionarioAcAutorizado;
        $observacionesAcResumen = (string) ($funcionarioAcResumen->observaciones ?? '');
        $extraerDatoAcResumen = function (string $campo) use ($observacionesAcResumen): ?string {
            $patrones = [
                'subdireccion_dependencia' => '/Subdirecci[oó]n dependencia:\s*(.*?)(?:\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
                'escalafon' => '/Escalaf[oó]n:\s*(.*?)(?:\s+Calidad jur[ií]dica:|\s+Unidad:|\s+Subdirecci[oó]n dependencia:|$)/iu',
                'calidad_juridica' => '/Calidad jur[ií]dica:\s*(.*?)(?:\s+Unidad:|\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|$)/iu',
                'unidad' => '/Unidad:\s*(.*?)(?:\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            ];

            if (! isset($patrones[$campo]) || $observacionesAcResumen === '') {
                return null;
            }

            if (preg_match($patrones[$campo], $observacionesAcResumen, $matches)) {
                $valor = trim((string) ($matches[1] ?? ''));
                return $valor !== '' ? $valor : null;
            }

            return null;
        };
        $esResumenAc = $cometido->esAdministracionCentral();
        $subdireccionResumenAc = $funcionarioAcResumen?->subdireccion_dependencia
            ?: $cometido->subdireccion_dependencia_ac
            ?: $extraerDatoAcResumen('subdireccion_dependencia');
        $escalafonResumenAc = $funcionarioAcResumen?->escalafon
            ?: $extraerDatoAcResumen('escalafon')
            ?: $cometido->estamento;
        $calidadJuridicaResumenAc = $funcionarioAcResumen?->calidad_juridica
            ?: $extraerDatoAcResumen('calidad_juridica')
            ?: $cometido->calidad_juridica;
        $unidadResumenAc = ($funcionarioAcResumen->unidad_departamento ?? null)
            ?: ($funcionarioAcResumen->unidad ?? null)
            ?: $extraerDatoAcResumen('unidad');
        $telefonoResumenAc = trim((string) ($funcionarioAcResumen->telefono ?? ''));
        $emailResumenAc = trim((string) ($funcionarioAcResumen->email ?? $funcionarioAcResumen->registeredUser?->email ?? $cometido->solicitante?->email ?? ''));
        $fechaNacimientoResumenAc = ! empty($funcionarioAcResumen->fecha_nacimiento)
            ? \Illuminate\Support\Carbon::parse($funcionarioAcResumen->fecha_nacimiento)->format('d-m-Y')
            : '';

        $puedeRevisarUatpVista = (bool) ($puedeRevisarUatp ?? false)
            || in_array($activeRoleVista, ['admin', 'coordinador_uatp'], true);

        $puedeRevisarCdpVista = (bool) ($puedeRevisarCdp ?? false)
            || in_array($activeRoleVista, ['admin', 'supervisor_plani', 'coordinador_plani'], true);

        $puedeVerBandejaGdpVista = (bool) ($puedeVerBandejaGdp ?? false)
            || in_array($activeRoleVista, ['admin', 'coordinador_gdp', 'funcionario_slep'], true);

        $puedeResolverDirectorSinDisponibilidadVista = in_array($activeRoleVista, ['admin', 'director_ejecutivo'], true);

        $estadoActualCometido = (string) ($cometido->estado ?? '');
        $solicitaGasto = (bool) ($cometido->solicita_viatico || $cometido->solicita_reembolso);
        $esCometidoSinGastoVista = ! (bool) ($cometido->solicita_viatico ?? false) && ! (bool) ($cometido->solicita_reembolso ?? false);
        $cdpMontosPorTipo = $cometido->cdpMontos->groupBy('tipo');
        $cdpValoresCatalogo = collect($cdpValoresCatalogo ?? []);
        $diasCometidoCdp = collect($diasCometidoCdp ?? []);
        $forzarViatico40PorAlojamiento = (bool) ($cometido->solicita_viatico ?? false)
            && (bool) ($cometido->contempla_alojamiento ?? false)
            && $diasCometidoCdp->count() > 1;
        $viaticoAutomaticoCdp = $viaticoAutomaticoCdp ?? ['aplica' => false, 'catalogo' => null, 'rows' => [], 'total' => 0, 'error' => null, 'categoria' => null];
        $viaticoAutomaticoRows = collect($viaticoAutomaticoCdp['rows'] ?? [])->keyBy('fecha');
        $soloViaticoCdp = (bool) ($cometido->solicita_viatico ?? false) && ! (bool) ($cometido->solicita_reembolso ?? false);
        $soloReembolsoVista = ! (bool) ($cometido->solicita_viatico ?? false) && (bool) ($cometido->solicita_reembolso ?? false);
        $esFlujoParaleloCdpVista = (bool) ($cometido->solicita_viatico ?? false) && (bool) ($cometido->solicita_reembolso ?? false);
        $cdpValoresJson = $cdpValoresCatalogo->map(fn ($valor) => [
            'id' => $valor->id,
            'estamento' => $valor->estamento,
            'cargo_funcion' => $valor->cargo_funcion,
            'valor_100' => (int) $valor->valor_100,
            'valor_60' => (int) ($valor->valor_60 ?? 0),
            'valor_40' => (int) $valor->valor_40,
            'label' => $valor->estamento . ' / ' . $valor->cargo_funcion . ' — $' . number_format((int) $valor->valor_100, 0, ',', '.') . ' (100%) / $' . number_format((int) ($valor->valor_60 ?? 0), 0, ',', '.') . ' (60%) / $' . number_format((int) $valor->valor_40, 0, ',', '.') . ' (40%)',
        ])->values();
        $cdpAprobado = $cometido->cdp_aprobado === true;
        $cdpRechazado = $cometido->cdp_aprobado === false || in_array($estadoActualCometido, ['cdp_rechazado', 'autorizado_sin_gasto'], true);
        $rechazadoUatp = $estadoActualCometido === 'rechazado_uatp';
        $observadoUatp = $estadoActualCometido === 'observado_uatp';

        $estadoLabelsCometido = [
            'borrador' => 'Borrador',
            'en_revision_uatp' => 'En revisión UATP',
            'observado_uatp' => 'Observado por UATP',
            'rechazado_uatp' => 'Rechazado por UATP',
            'aprobado_uatp' => 'Aprobado por UATP',
            'en_revision_cdp' => 'En revisión CDP',
            'pendiente_autorizacion_director_sin_disponibilidad' => 'Pendiente Director Ejecutivo',
            'reconvertido_a_reembolso_por_sin_disponibilidad' => 'Reconversión a reembolso aprobada',
            'rechazado_director_sin_disponibilidad' => 'Rechazado por Director Ejecutivo',
            'cdp_aprobado' => 'CDP aprobado',
            'cdp_rechazado' => 'CDP rechazado',
            'autorizado_sin_gasto' => 'Autorizado sin gasto',
            'en_gdp_resolucion' => 'En GDP para registro',
            'en_gdp_rex_cgr' => 'En GDP para REX CGR',
            'resolucion_cometido_emitida' => 'Resolución de cometido emitida',
            'informe_pendiente_funcionario' => 'Informe de cometido pendiente',
            'informe_pendiente_jefatura' => 'Informe pendiente de jefatura',
            'informe_observado' => 'Informe observado',
            'informe_aprobado' => 'Informe aprobado',
            'informe_rechazado' => 'Informe rechazado',
            'en_daf_viatico' => 'En DAF para viático',
            'en_daf_contable_viatico' => 'En DAF contable - viático',
            'en_pago_viatico' => 'En pago de viático',
            'viatico_pagado' => 'Viático pagado',
            'en_daf_reembolso' => 'En DAF para reembolso',
            'pendiente_rendicion' => 'Rendición habilitada',
            'pendiente_rendicion_informe' => 'Pendiente de rendición e informe',
            'en_rendicion_reembolso' => 'En rendición de reembolso',
            'rendicion_enviada_pendiente_informe' => 'Rendición enviada, informe pendiente',
            'rendicion_rectificada_pendiente_daf' => 'Rendición rectificada pendiente DAF',
            'rendicion_enviada' => 'Rendición enviada',
            'en_revision_daf_rendicion' => 'En revisión DAF de rendición',
            'rendicion_observada_daf' => 'Rendición observada por DAF',
            'rendicion_rechazada_daf' => 'Rendición rechazada por DAF',
            'rendicion_autorizada_daf' => 'Rendición aprobada por DAF',
            'en_revision_cdp_rendicion' => 'En CDP de rendición',
            'cdp_observado_rendicion' => 'CDP rendición observado',
            'cdp_rechazado_rendicion' => 'CDP rendición rechazado',
            'cdp_reembolso_aprobado' => 'CDP rendición aprobado',
            'en_juridica_resolucion_reembolso' => 'En Jurídica para resolución',
            'observada_juridica_reembolso' => 'Observado por Jurídica',
            'resolucion_reembolso_emitida' => 'Resolución reembolso emitida',
            'en_daf_contable_reembolso' => 'En DAF contable - reembolso',
            'en_pago_reembolso' => 'En pago de reembolso',
            'reembolso_pagado' => 'Reembolso pagado',
            'cerrado_sin_pago_reembolso' => 'Cerrado sin pago reembolso',
            'cerrado' => 'Cerrado',
        ];

        $estadoBadgeVisual = 'is-muted';
        if (in_array($estadoActualCometido, ['observado_uatp'], true)) {
            $estadoBadgeVisual = 'is-warning';
        } elseif (in_array($estadoActualCometido, ['rechazado_uatp', 'cdp_rechazado'], true)) {
            $estadoBadgeVisual = 'is-danger';
        } elseif (in_array($estadoActualCometido, ['aprobado_uatp', 'cdp_aprobado', 'resolucion_cometido_emitida', 'viatico_pagado', 'reembolso_pagado', 'cerrado'], true)) {
            $estadoBadgeVisual = 'is-completed';
        } elseif (in_array($estadoActualCometido, ['en_revision_uatp', 'en_revision_cdp', 'pendiente_autorizacion_director_sin_disponibilidad', 'en_gdp_resolucion', 'en_gdp_rex_cgr', 'en_gestion_paralela', 'autorizado_sin_gasto', 'informe_pendiente_funcionario', 'informe_pendiente_jefatura', 'informe_observado', 'informe_aprobado', 'en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'en_daf_reembolso', 'en_rendicion_reembolso', 'pendiente_rendicion', 'pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe', 'rendicion_rectificada_pendiente_daf', 'en_revision_daf_rendicion', 'en_revision_cdp_rendicion', 'en_juridica_resolucion_reembolso', 'en_daf_contable_reembolso', 'en_pago_reembolso'], true)) {
            $estadoBadgeVisual = 'is-current';
        }

        $estadoViaticoTimeline = method_exists($cometido, 'estadoViaticoActual') ? $cometido->estadoViaticoActual() : ($cometido->estado_viatico ?? null);
        $estadoReembolsoTimeline = method_exists($cometido, 'estadoReembolsoActual') ? $cometido->estadoReembolsoActual() : ($cometido->estado_reembolso ?? null);
        $pasoDirectorSinDisponibilidadTimeline = in_array($estadoActualCometido, ['pendiente_autorizacion_director_sin_disponibilidad', 'rechazado_director_sin_disponibilidad', 'en_gdp_rex_cgr'], true)
            || in_array((string) ($cometido->estado_viatico ?? ''), ['sin_disponibilidad', 'no_pagado_sin_disponibilidad'], true)
            || in_array((string) ($cometido->estado_reembolso ?? ''), ['pendiente_rex_cgr', 'en_gdp_rex_cgr'], true)
            || ! empty($cometido->estado_autorizacion_director)
            || (bool) ($cometido->viatico_reconvertido_a_reembolso ?? false)
            || (bool) ($cometido->requiere_autorizacion_director_sin_disponibilidad ?? false);
        $requiereRexCgrReembolsoAcTimeline = $cometido->esAdministracionCentral() && (bool) $cometido->solicita_reembolso && ! (bool) $cometido->solicita_viatico;
        $ultimaRendicionParaEstado = \App\Models\CometidoFuncionarioRendicion::query()
            ->where('cometido_funcionario_id', $cometido->id)
            ->latest('id')
            ->first();
        if ($ultimaRendicionParaEstado && in_array($ultimaRendicionParaEstado->estado, [
            'rendicion_enviada',
            'rendicion_enviada_pendiente_informe',
            'rendicion_rectificada_pendiente_daf',
            'en_revision_daf_rendicion',
            'rendicion_observada_daf',
            'rendicion_rechazada_daf',
            'rendicion_autorizada_daf',
            'en_revision_cdp_rendicion',
            'cdp_observado_rendicion',
            'cdp_rechazado_rendicion',
            'cdp_reembolso_aprobado',
            'en_juridica_resolucion_reembolso',
            'observada_juridica_reembolso',
            'resolucion_reembolso_emitida',
            'en_daf_contable_reembolso',
            'en_pago_reembolso',
            'reembolso_pagado',
            'cerrado_sin_pago_reembolso',
        ], true)) {
            $estadoReembolsoTimeline = $ultimaRendicionParaEstado->estado;
        }
        $ultimaRendicionTimeline = $rendicionesFlujoDocs->first();
        $resolucionReembolsoTimeline = $ultimaRendicionTimeline?->resolucion;
        $rendicionEnviadaTimeline = (bool) ($ultimaRendicionTimeline && (($ultimaRendicionTimeline->monto_rendido ?? 0) > 0 || ! empty($ultimaRendicionTimeline->documentos_respaldo)));
        $dafRendicionAprobadaTimeline = (bool) ($ultimaRendicionTimeline && (($ultimaRendicionTimeline->monto_autorizado_daf ?? null) !== null || $ultimaRendicionTimeline->documento_daf_path));
        $cdpRendicionAprobadoTimeline = (bool) ($ultimaRendicionTimeline && (($ultimaRendicionTimeline->monto_cdp_reembolso ?? null) !== null || $ultimaRendicionTimeline->referencia_cdp_reembolso || $ultimaRendicionTimeline->documento_cdp_reembolso_path));
        $resolucionReembolsoEmitidaTimeline = (bool) ($resolucionReembolsoTimeline && ($resolucionReembolsoTimeline->numero_resolucion || $resolucionReembolsoTimeline->documento_resolucion_path || ($resolucionReembolsoTimeline->monto_resolucion ?? null) !== null));
        $pagoReembolsoRegistradoTimeline = (bool) ($resolucionReembolsoTimeline && (($resolucionReembolsoTimeline->monto_pagado_reembolso ?? null) !== null || $resolucionReembolsoTimeline->fecha_pago_reembolso || $resolucionReembolsoTimeline->documento_pago_path));
        $viaticoPagadoTimeline = method_exists($cometido, 'viaticoPagado') ? $cometido->viaticoPagado() : ($estadoViaticoTimeline === 'viatico_pagado' || $cometido->fecha_pago_viatico);
        $listoParaCierreTimeline = method_exists($cometido, 'listoParaCierre') ? $cometido->listoParaCierre() : false;
        if ($esCometidoSinGastoVista && $estadoActualCometido === 'resolucion_cometido_emitida') {
            $listoParaCierreTimeline = true;
        }

        $aplicaFlujoReembolsoTimeline = (bool) ($cometido->solicita_reembolso ?? false)
            || (bool) ($cometido->viatico_reconvertido_a_reembolso ?? false)
            || in_array($estadoReembolsoTimeline, [
                'pendiente_rex_cgr',
                'en_gdp_rex_cgr',
                'pendiente_rendicion',
                'pendiente_rendicion_informe',
                'en_rendicion_reembolso',
                'rendicion_enviada',
                'rendicion_enviada_pendiente_informe',
                'en_revision_daf_rendicion',
                'rendicion_rectificada_pendiente_daf',
                'rendicion_autorizada_daf',
                'en_revision_cdp_rendicion',
                'cdp_reembolso_aprobado',
                'en_juridica_resolucion_reembolso',
                'observada_juridica_reembolso',
                'resolucion_reembolso_emitida',
                'en_daf_contable_reembolso',
                'en_pago_reembolso',
                'reembolso_pagado',
                'cerrado_sin_pago_reembolso',
            ], true);

        $informeTimeline = $cometido->informeCometidoActual;
        $estadoInformeTimeline = (string) ($informeTimeline->estado_informe ?? $estadoActualCometido);
        $informeEnviadoTimeline = (bool) ($informeTimeline && ! empty($informeTimeline->fecha_envio));
        $informeAprobadoTimeline = $estadoInformeTimeline === 'aprobado_jefatura'
            || in_array($estadoActualCometido, ['informe_aprobado', 'en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'viatico_pagado', 'en_revision_daf_rendicion', 'rendicion_autorizada_daf', 'en_revision_cdp_rendicion', 'en_juridica_resolucion_reembolso', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado', 'cerrado'], true);

        $pasajeTimeline = $cometido->pasajeAereo->sortByDesc('id')->first();
        $jefaturaTimeline = null;
        if ($cometido->esAdministracionCentral() && ! empty($cometido->jefatura_autorizadora_ac_id)) {
            $jefaturaTimeline = \App\Models\FuncionarioAcAutorizado::find($cometido->jefatura_autorizadora_ac_id);
        }
        $nombreJefaturaTimeline = trim((string) ($jefaturaTimeline->nombre_completo ?? ''));
        if ($nombreJefaturaTimeline === '' && ! empty($cometido->jefatura_autorizadora_user_id)) {
            $usuarioJefaturaTimeline = \App\Models\User::find($cometido->jefatura_autorizadora_user_id);
            $nombreJefaturaTimeline = trim((string) ($usuarioJefaturaTimeline->nombre_completo ?? $usuarioJefaturaTimeline->name ?? ''));
        }
        $sufijoJefaturaTimeline = $nombreJefaturaTimeline !== '' ? ' por ' . $nombreJefaturaTimeline : '';

        $flujoSteps = [
            [
                'key' => 'solicitud',
                'label' => 'Solicitud',
                'status' => in_array($estadoActualCometido, ['borrador'], true) ? 'current' : 'completed',
                'description' => $estadoActualCometido === 'borrador'
                    ? 'Borrador pendiente de envío.'
                    : ($cometido->esAdministracionCentral() ? 'Solicitud enviada por funcionario de Administración Central.' : 'Solicitud enviada por establecimiento.'),
            ],
        ];

        if ($cometido->esAdministracionCentral()) {
            $estadoJefaturaTimeline = (string) ($cometido->estado_autorizacion_jefatura_ac ?: $cometido->estado);
            $statusJefaturaTimeline = match ($estadoJefaturaTimeline) {
                'rechazado', 'rechazado_jefatura_ac' => 'rejected',
                'observado', 'observado_jefatura_ac' => 'observed',
                'en_revision_jefatura_ac' => 'current',
                'aprobado', 'aprobado_jefatura_ac' => 'completed',
                default => (in_array($estadoActualCometido, ['borrador'], true) ? 'pending' : (in_array($estadoActualCometido, ['en_revision_jefatura_ac'], true) ? 'current' : 'completed')),
            };
            $descripcionJefaturaTimeline = match ($estadoJefaturaTimeline) {
                'rechazado', 'rechazado_jefatura_ac' => 'Solicitud rechazada por jefatura' . $sufijoJefaturaTimeline . '.',
                'observado', 'observado_jefatura_ac' => 'Solicitud observada por jefatura' . $sufijoJefaturaTimeline . '; requiere corrección del solicitante.',
                'aprobado', 'aprobado_jefatura_ac' => 'Solicitud aprobada por jefatura' . $sufijoJefaturaTimeline . '.',
                'en_revision_jefatura_ac' => $nombreJefaturaTimeline !== '' ? 'Pendiente de revisión de jefatura por ' . $nombreJefaturaTimeline . '.' : 'Pendiente de revisión de jefatura.',
                default => in_array($estadoActualCometido, ['borrador'], true) ? 'Pendiente de envío.' : ($nombreJefaturaTimeline !== '' ? 'Pendiente de revisión de jefatura por ' . $nombreJefaturaTimeline . '.' : 'Pendiente de revisión de jefatura.'),
            };

            $flujoSteps[] = [
                'key' => 'jefatura_ac',
                'label' => 'Jefatura',
                'status' => $statusJefaturaTimeline,
                'description' => $descripcionJefaturaTimeline,
            ];

            if ($cometido->requiere_pasaje_aereo) {
                $jefaturaAcAprobadaTimeline = in_array((string) ($cometido->estado_autorizacion_jefatura_ac ?? ''), ['aprobado', 'aprobado_jefatura_ac'], true)
                    || ! empty($cometido->fecha_autorizacion_jefatura_ac)
                    || ! in_array($estadoActualCometido, ['borrador', 'en_revision_jefatura_ac', 'observado_jefatura_ac'], true);
                $estadoPasajeTimeline = (string) ($pasajeTimeline->estado_pasaje ?? 'pendiente_reserva');

                if (! $jefaturaAcAprobadaTimeline) {
                    $statusPasajeTimeline = 'pending';
                    $descripcionPasajeTimeline = 'Pendiente de autorización de jefatura antes de iniciar gestión de pasajes.';
                } else {
                    $statusPasajeTimeline = match ($estadoPasajeTimeline) {
                        'pendiente_reserva' => 'current',
                        'pendiente_cdp_pasaje', 'pendiente_compra' => 'current',
                        'boleto_disponible' => 'completed',
                        default => 'current',
                    };
                    $descripcionPasajeTimeline = match ($estadoPasajeTimeline) {
                        'pendiente_reserva' => 'DAF Compra debe gestionar la reserva del pasaje aéreo.',
                        'pendiente_cdp_pasaje' => 'Reserva cargada; Planificación debe emitir el CDP de pasaje.',
                        'pendiente_compra' => 'CDP de pasaje emitido; DAF Compra debe registrar la compra.',
                        'boleto_disponible' => 'Compra finalizada; boleto aéreo disponible para el funcionario solicitante.',
                        default => 'Flujo paralelo de compra de pasajes habilitado para este cometido.',
                    };
                }

                $flujoSteps[] = [
                    'key' => 'pasaje_aereo',
                    'label' => 'Pasajes',
                    'status' => $statusPasajeTimeline,
                    'description' => $descripcionPasajeTimeline,
                ];
            }
        } else {
            $flujoSteps[] = [
                'key' => 'uatp',
                'label' => 'UATP',
                'status' => $rechazadoUatp ? 'rejected' : ($observadoUatp ? 'observed' : (in_array($estadoActualCometido, ['borrador'], true) ? 'pending' : (in_array($estadoActualCometido, ['en_revision_uatp'], true) ? 'current' : 'completed'))),
                'description' => $rechazadoUatp ? 'Solicitud rechazada por UATP.' : ($observadoUatp ? 'Solicitud observada; requiere corrección del establecimiento.' : (in_array($estadoActualCometido, ['en_revision_uatp'], true) ? 'Pendiente de revisión de pertinencia pedagógica.' : (in_array($estadoActualCometido, ['borrador'], true) ? 'Pendiente de envío.' : 'Pertinencia pedagógica aprobada.'))),
            ];
        }

        if ($cometido->solicita_viatico || $pasoDirectorSinDisponibilidadTimeline) {
            $cdpPendientePorJefaturaAc = $cometido->esAdministracionCentral()
                && ! $cdpAprobado
                && ! $cdpRechazado
                && ! in_array($estadoActualCometido, ['en_revision_cdp'], true)
                && ! in_array($estadoViaticoTimeline, ['en_revision_cdp'], true)
                && ! in_array((string) ($cometido->estado_autorizacion_jefatura_ac ?? ''), ['aprobado'], true);
            $cdpPendientePreRevision = in_array($estadoActualCometido, ['borrador', 'en_revision_uatp', 'observado_uatp', 'aprobado_uatp', 'en_revision_jefatura_ac', 'observado_jefatura_ac'], true);
            $enDecisionDirectorSinDisponibilidad = in_array($estadoActualCometido, ['pendiente_autorizacion_director_sin_disponibilidad', 'rechazado_director_sin_disponibilidad'], true)
                || in_array((string) ($cometido->estado_viatico ?? ''), ['sin_disponibilidad', 'no_pagado_sin_disponibilidad'], true)
                || $pasoDirectorSinDisponibilidadTimeline;
            $cdpTimelineStatus = $rechazadoUatp
                ? 'not_applicable'
                : ($estadoActualCometido === 'rechazado_director_sin_disponibilidad'
                    ? 'completed'
                    : ($enDecisionDirectorSinDisponibilidad
                        ? 'completed'
                        : ($cdpRechazado
                            ? 'rejected'
                            : ($cdpAprobado
                                ? 'completed'
                                : (($estadoActualCometido === 'en_revision_cdp' || $estadoViaticoTimeline === 'en_revision_cdp')
                                    ? 'current'
                                    : (($cdpPendientePorJefaturaAc || $cdpPendientePreRevision) ? 'pending' : 'pending'))))));
            $cdpTimelineDescription = $enDecisionDirectorSinDisponibilidad
                ? 'Planificación revisó el CDP y detectó falta de disponibilidad presupuestaria; el cometido fue derivado a decisión del Director Ejecutivo.'
                : ($cdpRechazado
                    ? 'Sin disponibilidad presupuestaria para viático; se autoriza sin gasto de viático.'
                    : ($cdpAprobado
                        ? ($cometido->solicita_reembolso ? 'CDP inicial aprobado sólo para viático; reembolso diferido a rendición.' : 'Disponibilidad presupuestaria aprobada para viático.')
                        : (($estadoActualCometido === 'en_revision_cdp' || $estadoViaticoTimeline === 'en_revision_cdp')
                            ? 'Planificación debe revisar disponibilidad presupuestaria y emitir el certificado CDP del viático.'
                            : ($cometido->esAdministracionCentral() ? 'Pendiente de aprobación de jefatura antes de revisión CDP.' : 'Pendiente según aprobación UATP.'))));

            $flujoSteps[] = [
                'key' => 'cdp_inicial',
                'label' => $pasoDirectorSinDisponibilidadTimeline ? 'CDP sin disponibilidad' : ($cometido->solicita_reembolso ? 'CDP viático' : 'CDP'),
                'status' => $cdpTimelineStatus,
                'description' => $cdpTimelineDescription,
            ];
            if ($estadoActualCometido === 'pendiente_autorizacion_director_sin_disponibilidad' || ! empty($cometido->estado_autorizacion_director)) {
                $flujoSteps[] = [
                    'key' => 'director_sin_disponibilidad',
                    'label' => 'Director Ejecutivo',
                    'status' => $estadoActualCometido === 'rechazado_director_sin_disponibilidad'
                        ? 'rejected'
                        : ((string) ($cometido->estado_autorizacion_director ?? '') === 'aprobada' || (bool) ($cometido->viatico_reconvertido_a_reembolso ?? false) ? 'completed' : 'current'),
                    'description' => $estadoActualCometido === 'rechazado_director_sin_disponibilidad'
                        ? 'Director Ejecutivo rechazó la continuidad financiera por falta de disponibilidad.'
                        : ((bool) ($cometido->viatico_reconvertido_a_reembolso ?? false)
                            ? 'Director Ejecutivo aprobó reconvertir el cometido a flujo de reembolso.'
                            : 'Debe aprobar reconversión a reembolso o rechazar continuidad financiera.'),
                ];
            }
        }

        if (! $soloReembolsoVista || $requiereRexCgrReembolsoAcTimeline || $pasoDirectorSinDisponibilidadTimeline) {
            $gdpActual = in_array($estadoActualCometido, ['en_gdp_resolucion', 'autorizado_sin_gasto'], true)
                || ($estadoActualCometido === 'en_gdp_rex_cgr' && $requiereRexCgrReembolsoAcTimeline)
                || $estadoViaticoTimeline === 'en_gdp_resolucion'
                || in_array($estadoReembolsoTimeline, ['en_gdp_resolucion'], true)
                || (in_array($estadoReembolsoTimeline, ['en_gdp_rex_cgr', 'pendiente_rex_cgr'], true) && $requiereRexCgrReembolsoAcTimeline);
            $gdpCompletado = (bool) ($cometido->numero_resolucion_cometido || $cometido->archivo_resolucion_cometido_path || in_array($estadoActualCometido, ['resolucion_cometido_emitida', 'informe_pendiente_funcionario', 'informe_pendiente_jefatura', 'informe_aprobado', 'en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'viatico_pagado', 'pendiente_rendicion', 'pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'en_revision_cdp_rendicion', 'en_juridica_resolucion_reembolso', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado', 'cerrado'], true) || in_array($estadoViaticoTimeline, ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'viatico_pagado'], true) || in_array($estadoReembolsoTimeline, ['pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso', 'rendicion_enviada', 'rendicion_enviada_pendiente_informe', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'rendicion_autorizada_daf', 'en_revision_cdp_rendicion', 'cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso', 'resolucion_reembolso_emitida', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'], true));

            $flujoSteps[] = [
                'key' => 'gdp',
                'label' => 'GDP',
                'status' => $rechazadoUatp ? 'not_applicable' : ($gdpActual ? 'current' : ($gdpCompletado ? 'completed' : 'pending')),
                'description' => $gdpActual
                    ? ($requiereRexCgrReembolsoAcTimeline ? 'GDP debe emitir REX para CGR antes de habilitar informe y rendición de reembolso.' : ($esCometidoSinGastoVista ? 'GDP debe registrar el cometido antes del cierre.' : ($cdpRechazado ? 'Debe emitir resolución de cometido sin gasto autorizado.' : 'GDP debe emitir REX del cometido o del componente viático.')))
                    : ($gdpCompletado ? ($requiereRexCgrReembolsoAcTimeline ? 'GDP emitió REX para CGR y habilitó informe y rendición de reembolso.' : ($esCometidoSinGastoVista ? 'Cometido registrado por GDP.' : 'Resolución de cometido emitida.')) : 'Pendiente de derivación.'),
            ];
        }

        if ($cometido->solicita_viatico || $aplicaFlujoReembolsoTimeline) {
            $informeStatusTimeline = $informeAprobadoTimeline
                ? 'completed'
                : (in_array($estadoInformeTimeline, ['observado_jefatura', 'informe_observado'], true)
                    ? 'observed'
                    : (in_array($estadoInformeTimeline, ['rechazado_jefatura', 'informe_rechazado'], true)
                        ? 'rejected'
                        : (in_array($estadoActualCometido, ['informe_pendiente_funcionario', 'informe_pendiente_jefatura', 'pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe'], true) || $informeEnviadoTimeline ? 'current' : 'pending')));
            $flujoSteps[] = [
                'key' => 'informe_cometido',
                'label' => 'Informe',
                'status' => $rechazadoUatp ? 'not_applicable' : $informeStatusTimeline,
                'description' => $informeAprobadoTimeline
                    ? 'Informe de cometido aprobado por jefatura.'
                    : ($informeEnviadoTimeline ? 'Informe enviado; pendiente de revisión o regularización de jefatura.' : 'Funcionario debe completar informe de cometido.'),
            ];
        }

        if ($cometido->solicita_viatico) {
            $viaticoEstadoFinanciero = (string) ($estadoViaticoTimeline ?: $estadoActualCometido);
            $flujoSteps[] = [
                'key' => 'pago_viatico',
                'label' => 'Viático: DAF contable/pago',
                'status' => ($cdpRechazado || $rechazadoUatp) ? 'not_applicable' : ($viaticoPagadoTimeline ? 'completed' : (in_array($viaticoEstadoFinanciero, ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico'], true) ? 'current' : 'pending')),
                'description' => $viaticoPagadoTimeline
                    ? 'Pago de viático registrado por DAF/Finanzas.'
                    : (in_array($viaticoEstadoFinanciero, ['en_daf_contable_viatico', 'en_pago_viatico'], true)
                        ? 'DAF debe registrar compromiso, devengo y pago de viático.'
                        : (in_array($viaticoEstadoFinanciero, ['en_daf_viatico'], true) ? 'DAF debe iniciar gestión financiera del viático.' : 'Pendiente de informe, REX y derivación a DAF.')),
            ];
        }

        if ($aplicaFlujoReembolsoTimeline) {
            $rendicionActualAc = $cometido->esAdministracionCentral()
                && ! $rendicionEnviadaTimeline
                && in_array($estadoReembolsoTimeline, ['pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso'], true);
            $flujoSteps[] = [
                'key' => 'rendicion',
                'label' => 'Rendición',
                'status' => $rechazadoUatp ? 'not_applicable' : ($rendicionEnviadaTimeline || in_array($estadoReembolsoTimeline, ['rendicion_enviada', 'rendicion_enviada_pendiente_informe', 'en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'rendicion_autorizada_daf', 'en_revision_cdp_rendicion', 'cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso', 'resolucion_reembolso_emitida', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'], true) ? 'completed' : ($rendicionActualAc || in_array($estadoReembolsoTimeline, ['pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso'], true) || in_array($estadoActualCometido, ['pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso', 'en_gestion_paralela'], true) ? 'current' : 'pending')),
                'description' => $rendicionEnviadaTimeline
                    ? ($cometido->esAdministracionCentral() ? 'El funcionario AC envió documentos fiscales de rendición.' : 'El establecimiento envió documentos fiscales de rendición.')
                    : ($cometido->esAdministracionCentral() ? 'El funcionario AC debe cargar documentos fiscales de respaldo.' : 'El establecimiento debe cargar documentos fiscales de respaldo.'),
            ];
            $flujoSteps[] = [
                'key' => 'daf_rendicion',
                'label' => 'DAF',
                'status' => $rechazadoUatp ? 'not_applicable' : (in_array($estadoReembolsoTimeline, ['rendicion_rechazada_daf'], true) ? 'rejected' : (in_array($estadoReembolsoTimeline, ['rendicion_observada_daf'], true) ? 'observed' : ($dafRendicionAprobadaTimeline || in_array($estadoReembolsoTimeline, ['rendicion_autorizada_daf', 'en_revision_cdp_rendicion', 'cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso', 'resolucion_reembolso_emitida', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'], true) ? 'completed' : (in_array($estadoReembolsoTimeline, ['en_revision_daf_rendicion', 'rendicion_rectificada_pendiente_daf', 'rendicion_enviada'], true) ? 'current' : 'pending')))),
                'description' => $dafRendicionAprobadaTimeline ? 'DAF autorizó el monto rendido.' : 'DAF revisa documentos fiscales y monto rendido.',
            ];
            $flujoSteps[] = [
                'key' => 'cdp_rendicion',
                'label' => 'CDP rendición',
                'status' => $rechazadoUatp ? 'not_applicable' : (in_array($estadoReembolsoTimeline, ['cdp_rechazado_rendicion'], true) ? 'rejected' : (in_array($estadoReembolsoTimeline, ['cdp_observado_rendicion'], true) ? 'observed' : ($cdpRendicionAprobadoTimeline || in_array($estadoReembolsoTimeline, ['cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso', 'resolucion_reembolso_emitida', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'], true) ? 'completed' : (in_array($estadoReembolsoTimeline, ['en_revision_cdp_rendicion'], true) ? 'current' : 'pending')))),
                'description' => $cdpRendicionAprobadoTimeline ? 'Planificación aprobó el CDP de rendición.' : 'Planificación emite CDP por el monto autorizado por DAF.',
            ];
            $flujoSteps[] = [
                'key' => 'juridica',
                'label' => 'Jurídica',
                'status' => $rechazadoUatp ? 'not_applicable' : (in_array($estadoReembolsoTimeline, ['observada_juridica_reembolso'], true) ? 'observed' : ($resolucionReembolsoEmitidaTimeline || in_array($estadoReembolsoTimeline, ['resolucion_reembolso_emitida', 'en_daf_contable_reembolso', 'en_pago_reembolso', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'], true) ? 'completed' : (in_array($estadoReembolsoTimeline, ['en_juridica_resolucion_reembolso'], true) ? 'current' : 'pending'))),
                'description' => $resolucionReembolsoEmitidaTimeline ? 'Jurídica emitió REX de pago de reembolso.' : 'Jurídica emite resolución de pago del reembolso.',
            ];
            $flujoSteps[] = [
                'key' => 'daf_contable_reembolso',
                'label' => 'DAF contable',
                'status' => $rechazadoUatp ? 'not_applicable' : (in_array($estadoReembolsoTimeline, ['en_pago_reembolso', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'], true) ? 'completed' : (in_array($estadoReembolsoTimeline, ['en_daf_contable_reembolso'], true) ? 'current' : 'pending')),
                'description' => in_array($estadoReembolsoTimeline, ['en_pago_reembolso', 'reembolso_pagado', 'cerrado_sin_pago_reembolso'], true)
                    ? 'DAF registró compromiso y devengo de reembolso.'
                    : 'DAF debe registrar folio de compromiso y devengo antes del pago.',
            ];
            $flujoSteps[] = [
                'key' => 'pago_reembolso',
                'label' => 'Pago',
                'status' => $rechazadoUatp ? 'not_applicable' : ($pagoReembolsoRegistradoTimeline || in_array($estadoReembolsoTimeline, ['reembolso_pagado', 'cerrado_sin_pago_reembolso'], true) ? 'completed' : (in_array($estadoReembolsoTimeline, ['en_pago_reembolso'], true) ? 'current' : 'pending')),
                'description' => $pagoReembolsoRegistradoTimeline ? 'DAF/Finanzas registró el pago del reembolso.' : 'DAF/Finanzas registra el pago autorizado por resolución.',
            ];
        }

        $flujoSteps[] = [
            'key' => 'cierre',
            'label' => 'Cierre',
            'status' => ($estadoActualCometido === 'cerrado' || $listoParaCierreTimeline) ? 'completed' : ($rechazadoUatp ? 'rejected' : 'pending'),
            'description' => $estadoActualCometido === 'cerrado'
                ? 'Trámite cerrado.'
                : ($rechazadoUatp
                    ? 'Trámite finalizado por rechazo UATP.'
                    : ($listoParaCierreTimeline
                        ? ($esCometidoSinGastoVista ? 'Cometido registrado y flujo finalizado.' : 'Pagos registrados y flujo financiero finalizado.')
                        : 'Pendiente de completar etapas del flujo.')),
        ];

        $stepClasses = [
            'completed' => ['class' => 'is-completed', 'icon' => 'bi-check-lg', 'badge' => 'Completado'],
            'current' => ['class' => 'is-current', 'icon' => 'bi-hourglass-split', 'badge' => 'Actual'],
            'pending' => ['class' => 'is-pending', 'icon' => 'bi-clock', 'badge' => 'Pendiente'],
            'not_applicable' => ['class' => 'is-na', 'icon' => 'bi-dash-lg', 'badge' => 'No aplica'],
            'observed' => ['class' => 'is-observed', 'icon' => 'bi-exclamation-triangle', 'badge' => 'Observado'],
            'rejected' => ['class' => 'is-rejected', 'icon' => 'bi-x-lg', 'badge' => 'Finalizado'],
        ];

        $stepIconMap = [
            'solicitud' => 'bi-send',
            'jefatura_ac' => 'bi-person-check',
            'pasaje_aereo' => 'bi-airplane',
            'uatp' => 'bi-mortarboard',
            'cdp_inicial' => 'bi-file-earmark-text',
            'director_sin_disponibilidad' => 'bi-person-circle',
            'gdp' => 'bi-file-earmark-check',
            'informe_cometido' => 'bi-journal-text',
            'pago_viatico' => 'bi-cash-coin',
            'rendicion' => 'bi-receipt',
            'daf_rendicion' => 'bi-journal-check',
            'cdp_rendicion' => 'bi-file-earmark-check',
            'juridica' => 'bi-file-earmark-ruled',
            'daf_contable_reembolso' => 'bi-calculator',
            'pago_reembolso' => 'bi-cash-coin',
            'cierre' => 'bi-flag',
        ];


        $flujoStepsIndexed = collect($flujoSteps)->mapWithKeys(fn ($step) => [$step['key'] => $step]);
        $diagramStatusLabels = [
            'completed' => 'Completado',
            'current' => 'Actual',
            'pending' => 'Pendiente',
        ];
        $diagramStatusClasses = [
            'completed' => 'is-completed',
            'current' => 'is-current',
            'pending' => 'is-pending',
        ];
        $normalizeDiagramStatus = function (?string $status) {
            return in_array($status, ['completed', 'current'], true) ? $status : 'pending';
        };
        $makeDiagramStage = function (string $key, string $fallbackLabel, string $fallbackDescription = '') use ($flujoStepsIndexed, $stepIconMap, $normalizeDiagramStatus, $diagramStatusLabels, $diagramStatusClasses) {
            $step = $flujoStepsIndexed->get($key, []);
            $status = $normalizeDiagramStatus($step['status'] ?? 'pending');

            return [
                'type' => 'stage',
                'key' => $key,
                'label' => $step['label'] ?? $fallbackLabel,
                'description' => $step['description'] ?? $fallbackDescription,
                'status' => $status,
                'badge' => $diagramStatusLabels[$status] ?? 'Pendiente',
                'class' => $diagramStatusClasses[$status] ?? $diagramStatusClasses['pending'],
                'icon' => $stepIconMap[$key] ?? 'bi-circle',
            ];
        };
        $makeDiagramDecision = function (string $key, string $label, string $decisionText, string $status = 'pending') use ($normalizeDiagramStatus, $diagramStatusLabels, $diagramStatusClasses) {
            $normalized = $normalizeDiagramStatus($status);

            return [
                'type' => 'decision',
                'key' => $key,
                'label' => $label,
                'status' => $normalized,
                'badge' => $diagramStatusLabels[$normalized] ?? 'Pendiente',
                'class' => $diagramStatusClasses[$normalized] ?? $diagramStatusClasses['pending'],
                'decision' => $decisionText,
            ];
        };
        $makeDiagramTerminal = function (string $key, string $label, string $status = 'pending') use ($normalizeDiagramStatus, $diagramStatusLabels, $diagramStatusClasses) {
            $normalized = $normalizeDiagramStatus($status);

            return [
                'type' => 'terminal',
                'key' => $key,
                'label' => $label,
                'status' => $normalized,
                'badge' => $diagramStatusLabels[$normalized] ?? 'Pendiente',
                'class' => $diagramStatusClasses[$normalized] ?? $diagramStatusClasses['pending'],
            ];
        };

        $diagramNodes = [];
        $diagramNodes[] = $makeDiagramTerminal('inicio', 'Inicio', 'completed');
        $diagramNodes[] = $makeDiagramStage('solicitud', 'Solicitud', 'Ingreso y envío de la solicitud de cometido.');
        $diagramNodes[] = $cometido->esAdministracionCentral()
            ? $makeDiagramStage('jefatura_ac', 'Jefatura', 'Revisión y aprobación de jefatura.')
            : $makeDiagramStage('uatp', 'UATP', 'Revisión y aprobación de pertinencia pedagógica.');

        if ($flujoStepsIndexed->has('pasaje_aereo')) {
            $diagramNodes[] = $makeDiagramStage('pasaje_aereo', 'Pasajes', 'Gestión paralela de reserva, CDP y compra de pasajes.');
        }
        if ($flujoStepsIndexed->has('cdp_inicial')) {
            $diagramNodes[] = $makeDiagramStage('cdp_inicial', 'CDP', 'Revisión y aprobación presupuestaria del cometido.');
        }
        if ($flujoStepsIndexed->has('director_sin_disponibilidad')) {
            $diagramNodes[] = $makeDiagramStage('director_sin_disponibilidad', 'Director Ejecutivo', 'Resolución por falta de disponibilidad presupuestaria.');
        }
        if ($flujoStepsIndexed->has('gdp')) {
            $diagramNodes[] = $makeDiagramStage('gdp', 'GDP', 'Emisión de resolución del cometido.');
        }
        if ($cometido->solicita_viatico || $aplicaFlujoReembolsoTimeline) {
            $statusDecisionInforme = $flujoStepsIndexed->has('informe_cometido')
                ? ($flujoStepsIndexed->get('informe_cometido')['status'] ?? 'pending')
                : ($flujoStepsIndexed->has('gdp') ? ($flujoStepsIndexed->get('gdp')['status'] ?? 'pending') : 'pending');
            $diagramNodes[] = $makeDiagramDecision('decision_informe', '¿Requiere informe?', 'Sí', $statusDecisionInforme);
            if ($flujoStepsIndexed->has('informe_cometido')) {
                $diagramNodes[] = $makeDiagramStage('informe_cometido', 'Informe', 'Registro y revisión del informe de cometido.');
            }
        }
        if ($flujoStepsIndexed->has('pago_viatico')) {
            $diagramNodes[] = $makeDiagramStage('pago_viatico', 'Viático: DAF / pago', 'Registro contable y pago efectivo del viático.');
        }
        if ($aplicaFlujoReembolsoTimeline) {
            $statusDecisionReembolso = $flujoStepsIndexed->has('rendicion')
                ? ($flujoStepsIndexed->get('rendicion')['status'] ?? 'pending')
                : 'pending';
            $diagramNodes[] = $makeDiagramDecision('decision_reembolso', '¿Requiere reembolso?', 'Sí', $statusDecisionReembolso);
            foreach ([
                ['rendicion', 'Rendición', 'Carga de documentos y respaldo del gasto efectuado.'],
                ['daf_rendicion', 'DAF', 'Revisión DAF de la rendición y monto informado.'],
                ['cdp_rendicion', 'CDP rendición', 'Emisión del CDP por el monto autorizado.'],
                ['juridica', 'Jurídica', 'Emisión de resolución de pago del reembolso.'],
                ['daf_contable_reembolso', 'DAF contable', 'Registro de compromiso y devengo para pago.'],
                ['pago_reembolso', 'Pago reembolso', 'Registro del pago final del reembolso.'],
            ] as [$key, $label, $description]) {
                if ($flujoStepsIndexed->has($key)) {
                    $diagramNodes[] = $makeDiagramStage($key, $label, $description);
                }
            }
        } elseif ($cometido->solicita_viatico || $aplicaFlujoReembolsoTimeline) {
            $statusDecisionReembolso = $flujoStepsIndexed->has('pago_viatico')
                ? ($flujoStepsIndexed->get('pago_viatico')['status'] ?? 'pending')
                : ($flujoStepsIndexed->has('informe_cometido') ? ($flujoStepsIndexed->get('informe_cometido')['status'] ?? 'pending') : 'pending');
            $diagramNodes[] = $makeDiagramDecision('decision_reembolso', '¿Requiere reembolso?', 'No', $statusDecisionReembolso);
        }
        $diagramNodes[] = $makeDiagramStage('cierre', 'Cierre', 'Finalización administrativa y financiera del cometido.');
        $diagramNodes[] = $makeDiagramTerminal('fin', 'Fin', ($flujoStepsIndexed->get('cierre')['status'] ?? 'pending'));

        $historialVisualConfig = [
            'observa' => ['class' => 'is-observed', 'icon' => 'bi-exclamation-triangle'],
            'rechaza' => ['class' => 'is-rejected', 'icon' => 'bi-x-lg'],
            'rechaz' => ['class' => 'is-rejected', 'icon' => 'bi-x-lg'],
            'aprueba' => ['class' => 'is-completed', 'icon' => 'bi-check-lg'],
            'aprob' => ['class' => 'is-completed', 'icon' => 'bi-check-lg'],
            'autoriz' => ['class' => 'is-completed', 'icon' => 'bi-check-lg'],
            'valid' => ['class' => 'is-completed', 'icon' => 'bi-check-lg'],
            'cerró' => ['class' => 'is-completed', 'icon' => 'bi-check2-circle'],
            'cerro' => ['class' => 'is-completed', 'icon' => 'bi-check2-circle'],
            'cerrad' => ['class' => 'is-completed', 'icon' => 'bi-check2-circle'],
            'complet' => ['class' => 'is-completed', 'icon' => 'bi-check2-circle'],
            'emitió' => ['class' => 'is-completed', 'icon' => 'bi-file-earmark-check'],
            'emitio' => ['class' => 'is-completed', 'icon' => 'bi-file-earmark-check'],
            'registró el pago' => ['class' => 'is-completed', 'icon' => 'bi-cash-coin'],
            'registro el pago' => ['class' => 'is-completed', 'icon' => 'bi-cash-coin'],
            'pagó' => ['class' => 'is-completed', 'icon' => 'bi-cash-coin'],
            'pago ' => ['class' => 'is-completed', 'icon' => 'bi-cash-coin'],
            'deriva' => ['class' => 'is-current', 'icon' => 'bi-arrow-right'],
            'envia' => ['class' => 'is-current', 'icon' => 'bi-send'],
            'enviada' => ['class' => 'is-current', 'icon' => 'bi-send'],
            'corrige' => ['class' => 'is-current', 'icon' => 'bi-pencil-square'],
            'actualizado' => ['class' => 'is-pending', 'icon' => 'bi-pencil'],
            'creado' => ['class' => 'is-pending', 'icon' => 'bi-plus-lg'],
            'cdp' => ['class' => 'is-current', 'icon' => 'bi-file-earmark-text'],
        ];

        $historialEstadosCompletados = [
            'cerrado',
            'autorizado',
            'reembolso_pagado',
            'viatico_pagado',
            'pagado',
            'cdp_aprobado',
            'rendicion_aprobada',
        ];

        $historialVisual = $cometido->historial->map(function ($item) use ($historialVisualConfig, $estadoLabelsCometido, $historialEstadosCompletados) {
            $accionNormalizada = strtolower(trim((string) $item->accion));
            $estadoNuevoNormalizado = strtolower(trim((string) ($item->estado_nuevo ?? '')));
            $config = ['class' => 'is-pending', 'icon' => 'bi-circle'];
            foreach ($historialVisualConfig as $needle => $candidate) {
                if (str_contains($accionNormalizada, $needle)) {
                    $config = $candidate;
                    break;
                }
            }

            if (str_contains($accionNormalizada, 'boleto')) {
                $config = ['class' => 'is-completed', 'icon' => 'bi-ticket-perforated'];
            } elseif (str_contains($accionNormalizada, 'reserva') || str_contains($accionNormalizada, 'pasaje aéreo')) {
                $config = ['class' => 'is-current', 'icon' => 'bi-airplane'];
            } elseif (str_contains($accionNormalizada, 'cargó cdp') || str_contains($accionNormalizada, 'cargo cdp') || (str_contains($accionNormalizada, 'cdp') && str_contains($accionNormalizada, 'carg'))) {
                $config = ['class' => 'is-completed', 'icon' => 'bi-file-earmark-check'];
            } elseif (str_contains($accionNormalizada, 'documento generado') || str_contains($accionNormalizada, 'solicitud de pedido')) {
                $config = ['class' => 'is-completed', 'icon' => 'bi-file-earmark-check'];
            } elseif (str_contains($accionNormalizada, 'cargó') || str_contains($accionNormalizada, 'cargo ')) {
                $config = ['class' => 'is-completed', 'icon' => 'bi-check2-square'];
            }

            if (str_contains($accionNormalizada, 'informe de cometido') || str_contains($accionNormalizada, ' informe') || str_starts_with($accionNormalizada, 'informe')) {
                $config['icon'] = str_contains($accionNormalizada, 'aprob') ? 'bi-journal-check' : 'bi-journal-text';
            }

            if ($config['class'] === 'is-pending' && in_array($estadoNuevoNormalizado, $historialEstadosCompletados, true)) {
                $config = ['class' => 'is-completed', 'icon' => 'bi-check-lg'];

                if (str_contains($accionNormalizada, 'informe de cometido') || str_contains($accionNormalizada, ' informe') || str_starts_with($accionNormalizada, 'informe')) {
                    $config['icon'] = 'bi-journal-check';
                }
            }

            $badgeMap = [
                'is-completed' => ['label' => 'Completado', 'class' => 'is-completed'],
                'is-current' => ['label' => 'Movimiento', 'class' => 'is-current'],
                'is-observed' => ['label' => 'Observación', 'class' => 'is-warning'],
                'is-rejected' => ['label' => 'Rechazo', 'class' => 'is-danger'],
                'is-pending' => ['label' => 'Registro', 'class' => 'is-muted'],
            ];
            $badgeVisual = $badgeMap[$config['class']] ?? ['label' => 'Registro', 'class' => 'is-muted'];

            return [
                'item' => $item,
                'class' => $config['class'],
                'icon' => $config['icon'],
                'badge' => $badgeVisual['label'],
                'badge_class' => $badgeVisual['class'],
                'estado_anterior' => $estadoLabelsCometido[$item->estado_anterior ?? ''] ?? ($item->estado_anterior ?? null),
                'estado_nuevo' => $estadoLabelsCometido[$item->estado_nuevo ?? ''] ?? ($item->estado_nuevo ?? null),
            ];
        });

    @endphp

    <div class="container py-4">

        <style>
            .cometido-flow-card, .cometido-history-panel { border: 1px solid #d7dee8; border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15, 23, 42, .045); }
            .cometido-flow { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .95rem; position: relative; align-items: stretch; }
            .cometido-flow-step { position: relative; min-height: 165px; padding: 1rem; border: 1px solid #e3eaf3; border-radius: 1rem; background: #fff; box-shadow: 0 .22rem .8rem rgba(15,23,42,.04); color: #475569; display: flex; flex-direction: column; gap: .7rem; }
            .cometido-flow-step:not(:last-child)::after { content: ''; position: absolute; top: 1.55rem; left: calc(100% + .12rem); width: .75rem; height: 2px; background: linear-gradient(90deg, #dbe4f0 0%, #cbd5e1 100%); }
            .cometido-flow-step-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; min-width: 0; }
            .cometido-flow-dot { width: 2.55rem; height: 2.55rem; border-radius: .85rem; display: inline-flex; align-items: center; justify-content: center; background: #e2e8f0; color: #475569; font-size: 1.05rem; font-weight: 700; box-shadow: 0 .3rem .7rem rgba(15,23,42,.08); flex: 0 0 auto; }
            .cometido-flow-kicker { font-size: .72rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; margin-bottom: -.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .cometido-flow-title { font-weight: 800; color: #0f172a; line-height: 1.2; font-size: 1.05rem; word-break: normal; overflow-wrap: anywhere; }
            .cometido-flow-desc { font-size: .82rem; line-height: 1.45; color: #475569; margin-top: auto; }
            .cometido-flow-badge { display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; font-size: .71rem; font-weight: 800; border-radius: 999px; padding: .28rem .58rem; background: #e2e8f0; color: #475569; border: 1px solid transparent; max-width: 100%; }
            .cometido-flow-step.is-completed { border-color: #bcebd0; background: linear-gradient(180deg, #f8fdf9 0%, #ffffff 100%); }
            .cometido-flow-step.is-completed .cometido-flow-dot, .cometido-flow-step.is-completed .cometido-flow-badge { background: #ecfdf3; color: #0f5132; border-color: #bcebd0; }
            .cometido-flow-step.is-current { border-color: #b9d9ff; background: linear-gradient(180deg, #f7fbff 0%, #ffffff 100%); box-shadow: 0 0 0 .14rem rgba(13,110,253,.07); }
            .cometido-flow-step.is-current .cometido-flow-dot, .cometido-flow-step.is-current .cometido-flow-badge { background: #eef6ff; color: #0d47a1; border-color: #b9d9ff; }
            .cometido-flow-step.is-observed { border-color: #f5d58b; background: linear-gradient(180deg, #fffdfa 0%, #ffffff 100%); }
            .cometido-flow-step.is-observed .cometido-flow-dot, .cometido-flow-step.is-observed .cometido-flow-badge { background: #fff8e1; color: #8a4b00; border-color: #f5d58b; }
            .cometido-flow-step.is-rejected { border-color: #fecdd3; background: linear-gradient(180deg, #fff8f8 0%, #ffffff 100%); }
            .cometido-flow-step.is-rejected .cometido-flow-dot, .cometido-flow-step.is-rejected .cometido-flow-badge { background: #fff1f2; color: #b42318; border-color: #fecdd3; }
            .cometido-flow-step.is-na { border-color: #e5e7eb; background: #fbfcfd; opacity: .86; }
            .cometido-flow-step.is-na .cometido-flow-dot, .cometido-flow-step.is-na .cometido-flow-badge { background: #f1f5f9; color: #64748b; border-color: #dbe4f0; }
            .process-toolbar { display: inline-flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
            .process-diagram-toggle { display: inline-flex; align-items: center; gap: .55rem; padding: .78rem 1.1rem; border-radius: .95rem; border: 1px solid #d7dee8; background: #fff; color: #0f172a; font-weight: 700; box-shadow: 0 .2rem .7rem rgba(15, 23, 42, .05); transition: all .2s ease; }
            .process-diagram-toggle:hover { border-color: #b9d9ff; color: #0d47a1; background: #f8fbff; }
            .process-diagram-toggle .toggle-icon { width: 2rem; height: 2rem; border-radius: .75rem; display: inline-flex; align-items: center; justify-content: center; background: #eef2f7; color: #334155; }
            .process-diagram-panel { margin-top: 1.35rem; border: 1px solid #dbe4f0; border-radius: 1.15rem; background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%); box-shadow: inset 0 1px 0 rgba(255,255,255,.7), 0 .35rem 1rem rgba(15,23,42,.04); overflow: hidden; }
            .process-diagram-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1.2rem 1.35rem; border-bottom: 1px solid #e7eef7; }
            .process-diagram-title-wrap { display: flex; align-items: flex-start; gap: .9rem; }
            .process-diagram-icon { width: 3rem; height: 3rem; border-radius: 1rem; background: #eef2f7; color: #334155; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 .25rem .8rem rgba(15,23,42,.08); flex: 0 0 auto; }
            .process-diagram-title { font-size: 1.35rem; font-weight: 800; color: #0f172a; line-height: 1.15; margin-bottom: .2rem; }
            .process-diagram-subtitle { color: #475569; font-size: .94rem; }
            .process-diagram-close-hint { color: #94a3b8; font-size: 1rem; }
            .process-diagram-body { padding: 1.35rem; }
            .process-diagram-scroll { overflow-x: auto; padding-bottom: .45rem; }
            .process-diagram-track { display: flex; align-items: center; gap: 1rem; min-width: max-content; padding: .2rem 0 .35rem; }
            .process-diagram-connector { width: 2.15rem; height: 2px; background: #cbd5e1; position: relative; flex: 0 0 auto; }
            .process-diagram-connector::after { content: ''; position: absolute; right: -.05rem; top: 50%; width: .5rem; height: .5rem; border-top: 2px solid #94a3b8; border-right: 2px solid #94a3b8; transform: translateY(-50%) rotate(45deg); }
            .process-diagram-node { position: relative; flex: 0 0 auto; }
            .process-stage-node { width: 220px; min-height: 162px; border: 1px solid #d7dee8; border-radius: 1rem; background: #fff; padding: 1rem; display: flex; flex-direction: column; gap: .7rem; box-shadow: 0 .18rem .75rem rgba(15,23,42,.04); }
            .process-stage-node .node-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .65rem; }
            .process-stage-node .node-icon { width: 2.25rem; height: 2.25rem; border-radius: .8rem; display: inline-flex; align-items: center; justify-content: center; background: #edf2f7; color: #475569; font-size: 1rem; }
            .process-stage-node .node-badge, .process-terminal-node .node-badge { display: inline-flex; align-items: center; justify-content: center; padding: .22rem .55rem; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: .72rem; font-weight: 800; border: 1px solid #e2e8f0; white-space: nowrap; }
            .process-stage-node .node-kicker { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; font-weight: 800; }
            .process-stage-node .node-title { font-size: 1rem; color: #0f172a; font-weight: 800; line-height: 1.2; }
            .process-stage-node .node-desc { font-size: .82rem; line-height: 1.45; color: #475569; margin-top: auto; }
            .process-stage-node.is-completed { border-color: #bcebd0; background: linear-gradient(180deg, #f7fdf9 0%, #ffffff 100%); }
            .process-stage-node.is-completed .node-icon, .process-stage-node.is-completed .node-badge, .process-terminal-node.is-completed .node-badge { background: #ecfdf3; color: #166534; border-color: #bcebd0; }
            .process-stage-node.is-current { border-color: #b9d9ff; background: linear-gradient(180deg, #f7fbff 0%, #ffffff 100%); box-shadow: 0 0 0 .16rem rgba(37,99,235,.08); }
            .process-stage-node.is-current .node-icon, .process-stage-node.is-current .node-badge, .process-terminal-node.is-current .node-badge { background: #eef6ff; color: #1d4ed8; border-color: #b9d9ff; }
            .process-stage-node.is-pending { border-color: #d7dee8; background: linear-gradient(180deg, #fbfcfd 0%, #ffffff 100%); }
            .process-stage-node.is-pending .node-icon, .process-stage-node.is-pending .node-badge, .process-terminal-node.is-pending .node-badge { background: #f8fafc; color: #64748b; border-color: #d7dee8; }
            .process-decision-node { width: 158px; min-height: 158px; display: flex; align-items: center; justify-content: center; }
            .process-decision-shape { width: 116px; height: 116px; transform: rotate(45deg); border: 1px solid #d7dee8; border-radius: 1.05rem; background: linear-gradient(180deg, #fbfcfd 0%, #ffffff 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 .18rem .75rem rgba(15,23,42,.04); }
            .process-decision-shape.is-completed { border-color: #bcebd0; background: linear-gradient(180deg, #f7fdf9 0%, #ffffff 100%); }
            .process-decision-shape.is-current { border-color: #b9d9ff; background: linear-gradient(180deg, #f7fbff 0%, #ffffff 100%); box-shadow: 0 0 0 .16rem rgba(37,99,235,.08); }
            .process-decision-shape.is-pending { border-color: #d7dee8; }
            .process-decision-inner { transform: rotate(-45deg); width: 92px; text-align: center; }
            .process-decision-title { font-size: .83rem; line-height: 1.25; color: #0f172a; font-weight: 800; margin-bottom: .35rem; }
            .process-decision-value { display: inline-flex; align-items: center; justify-content: center; min-width: 2.1rem; padding: .22rem .5rem; border-radius: 999px; border: 1px solid #d7dee8; background: #fff; color: #475569; font-size: .72rem; font-weight: 800; }
            .process-decision-shape.is-completed .process-decision-value { background: #ecfdf3; color: #166534; border-color: #bcebd0; }
            .process-decision-shape.is-current .process-decision-value { background: #eef6ff; color: #1d4ed8; border-color: #b9d9ff; }
            .process-terminal-node { min-width: 94px; border-radius: 999px; border: 1px solid #d7dee8; background: #fff; padding: .85rem 1rem; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem; box-shadow: 0 .18rem .75rem rgba(15,23,42,.04); }
            .process-terminal-node .node-title { font-size: .95rem; color: #0f172a; font-weight: 800; }
            .process-terminal-node.is-completed { border-color: #bcebd0; background: #f7fdf9; }
            .process-terminal-node.is-current { border-color: #b9d9ff; background: #f7fbff; box-shadow: 0 0 0 .16rem rgba(37,99,235,.08); }
            .process-terminal-node.is-pending { border-color: #d7dee8; background: #ffffff; }
            .process-diagram-legend { display: flex; flex-wrap: wrap; gap: .9rem 1.25rem; margin-top: 1.2rem; padding: 1rem 1.1rem; border: 1px solid #e7eef7; border-radius: .95rem; background: #fff; }
            .process-diagram-legend-title { width: 100%; font-size: .82rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: .03em; }
            .process-diagram-legend-item { display: inline-flex; align-items: center; gap: .55rem; color: #475569; font-size: .85rem; font-weight: 700; }
            .process-diagram-legend-swatch { width: 1.15rem; height: 1.15rem; border-radius: .35rem; border: 1px solid #d7dee8; background: #fff; }
            .process-diagram-legend-swatch.is-completed { background: #ecfdf3; border-color: #bcebd0; }
            .process-diagram-legend-swatch.is-current { background: #eef6ff; border-color: #b9d9ff; }
            .process-diagram-legend-swatch.is-pending { background: #f8fafc; border-color: #d7dee8; }
            .cometido-history { position: relative; padding: .35rem 0 .35rem .82rem; }
            .cometido-history::before { content: ''; position: absolute; top: 1rem; bottom: 1rem; left: 1.6rem; width: 2px; background: linear-gradient(180deg, #dbe4f0 0%, #cbd5e1 100%); }
            .cometido-history-item { position: relative; display: grid; grid-template-columns: 2.35rem minmax(0, 1fr); column-gap: 1rem; padding: 0 0 1rem 0; align-items: flex-start; }
            .cometido-history-item:last-child { padding-bottom: 0; }
            .cometido-history-dot { position: relative; width: 2.1rem; height: 2.1rem; border-radius: .8rem; display: inline-flex; align-items: center; justify-content: center; background: #e2e8f0; color: #475569; border: 4px solid #fff; box-shadow: 0 0 0 1px #d9e2ef, 0 .3rem .75rem rgba(15,23,42,.08); z-index: 1; }
            .cometido-history-content { min-width: 0; }
            .cometido-history-card { min-width: 0; border: 1px solid #e3eaf3; border-left: 4px solid #dbe4f0; border-radius: 1rem; background: #fff; box-shadow: 0 .22rem .8rem rgba(15,23,42,.04); padding: 1rem; }
            .cometido-history-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; margin-bottom: .35rem; }
            .cometido-history-kicker { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .2rem; }
            .cometido-history-item.is-completed .cometido-history-dot { background: #0f5132; color: #fff; }
            .cometido-history-item.is-completed .cometido-history-card { border-left-color: #0f8f4d; background: linear-gradient(180deg, #f8fdf9 0%, #ffffff 100%); }
            .cometido-history-item.is-current .cometido-history-dot { background: #0d6efd; color: #fff; }
            .cometido-history-item.is-current .cometido-history-card { border-left-color: #0d6efd; background: linear-gradient(180deg, #f7fbff 0%, #ffffff 100%); }
            .cometido-history-item.is-observed .cometido-history-dot { background: #f59f00; color: #1f2937; }
            .cometido-history-item.is-observed .cometido-history-card { border-left-color: #f59f00; background: linear-gradient(180deg, #fffdf6 0%, #ffffff 100%); }
            .cometido-history-item.is-rejected .cometido-history-dot { background: #dc3545; color: #fff; }
            .cometido-history-item.is-rejected .cometido-history-card { border-left-color: #dc3545; background: linear-gradient(180deg, #fff8f8 0%, #ffffff 100%); }
            .cometido-history-title { font-weight: 800; color: #0f172a; line-height: 1.3; font-size: 1rem; margin-bottom: .1rem; }
            .cometido-history-meta { color: #64748b; font-size: .79rem; line-height: 1.45; margin-bottom: .55rem; }
            .cometido-history-state { color: #475569; font-size: .78rem; margin-top: .25rem; display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
            .cometido-history-state-label { color: #64748b; font-weight: 700; }
            .cometido-history-state-badge { display: inline-flex; align-items: center; max-width: 100%; padding: .18rem .52rem; border-radius: 999px; background: #f1f5f9; border: 1px solid #dbe4f0; color: #334155; font-weight: 700; line-height: 1.2; }
            .cometido-history-item.is-completed .cometido-history-state-badge { background: #ecfdf3; border-color: #bcebd0; color: #0f5132; }
            .cometido-history-item.is-current .cometido-history-state-badge { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
            .cometido-history-item.is-observed .cometido-history-state-badge { background: #fff7ed; border-color: #fed7aa; color: #b45309; }
            .cometido-history-item.is-rejected .cometido-history-state-badge { background: #fff1f2; border-color: #fecdd3; color: #be123c; }
            .cometido-history-arrow { color: #94a3b8; font-weight: 800; }
            .cometido-history-note { margin-top: .8rem; padding: .8rem .9rem; background: #f8fafc; border: 1px solid #dbe4f0; border-radius: .85rem; font-size: .82rem; color: #334155; line-height: 1.5; }
            .cometido-history-note-title { display: block; color: #475569; font-size: .72rem; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; margin-bottom: .35rem; }
            .cdp-review-card { border: 1px solid #d7dee8; border-radius: 1rem; overflow: hidden; }
            .cdp-review-header { background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
            .cdp-status-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .65rem; border-radius: 999px; border: 1px solid #ffcf8a; background: #fff7ed; color: #c05621; font-size: .78rem; font-weight: 700; }
            .cdp-info-banner { display: flex; gap: .85rem; align-items: flex-start; padding: .95rem 1rem; border: 1px solid #b9d9ff; border-radius: .85rem; background: #eff7ff; color: #1e3a8a; }
            .cdp-info-icon { flex: 0 0 auto; width: 2rem; height: 2rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: #0d6efd; color: #fff; }
            .cdp-catalog-card, .cdp-beneficio-card, .cdp-summary-card { border: 1px solid #e3eaf3; border-radius: 1rem; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15, 23, 42, .045); }
            .cdp-beneficio-card { padding: 1rem; }
            .cdp-beneficio-icon { width: 2.75rem; height: 2.75rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.25rem; }
            .cdp-beneficio-icon.is-viatico { background: #5b3fd0; }
            .cdp-beneficio-icon.is-reembolso { background: #0f8f4d; }
            .cdp-beneficio-toggle.form-check-input { width: 2.7rem; height: 1.35rem; cursor: pointer; }
            .cdp-montos-table { border: 1px solid #e5eaf1; border-radius: .85rem; overflow: hidden; }
            .cdp-montos-table thead th { background: #f8fafc; color: #0f172a; font-size: .78rem; letter-spacing: .01em; }
            .cdp-montos-table td, .cdp-montos-table th { padding: .72rem .8rem; }
            .cdp-porcentaje { min-width: 6.5rem; font-weight: 700; }
            .cdp-valor-dia { font-weight: 700; color: #0f172a; white-space: nowrap; }
            .cdp-total-bar { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-top: .85rem; padding: .8rem 1rem; border-radius: .75rem; font-weight: 700; }
            .cdp-total-bar.is-viatico { background: #f3f0ff; color: #4c35b5; border: 1px solid #ded6ff; }
            .cdp-total-bar.is-reembolso { background: #ecfdf3; color: #08783f; border: 1px solid #c8f3d8; }
            .cdp-total-bar .cdp-total-tipo { font-size: 1.25rem; }
            .cdp-summary-card { position: sticky; top: 1rem; padding: 1rem; }
            .cdp-summary-line { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: .55rem 0; color: #334155; }
            .cdp-summary-line strong { color: #0f172a; }
            .cdp-summary-total { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: .95rem 0; border-top: 1px solid #dbe4f0; border-bottom: 1px solid #edf2f7; margin: .35rem 0 .8rem; }
            .cdp-summary-total span:last-child { font-size: 1.45rem; font-weight: 800; color: #0f172a; }
            .cdp-upload-box { border: 1px dashed #b8c5d6; border-radius: .85rem; background: #f8fafc; padding: 1rem; text-align: center; }
            .cdp-upload-box .form-control { background: #fff; }
            .cdp-actions-bar { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: flex-end; padding-top: .85rem; }
            .cdp-reject-box { border-top: 1px solid #edf2f7; margin-top: 1rem; padding-top: 1rem; }
            .cometido-actions-top { display: flex; flex-wrap: wrap; gap: .65rem; justify-content: flex-end; }
            .cometido-action-row { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: flex-end; align-items: center; }
            .cometido-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; border-radius: .8rem; font-weight: 800; line-height: 1.2; letter-spacing: .005em; padding: .58rem .9rem; border-width: 1px; box-shadow: 0 .28rem .8rem rgba(15,23,42,.06); transition: transform .12s ease, box-shadow .12s ease, background-color .12s ease, border-color .12s ease; }
            .cometido-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 .42rem 1rem rgba(15,23,42,.1); }
            .cometido-btn:disabled { opacity: .58; cursor: not-allowed; box-shadow: none; transform: none; }
            .cometido-btn i { font-size: .98rem; line-height: 1; }
            .cometido-btn.is-primary { background: #0d6efd; border-color: #0d6efd; color: #fff; }
            .cometido-btn.is-primary:hover:not(:disabled) { background: #0b5ed7; border-color: #0b5ed7; color: #fff; }
            .cometido-btn.is-success { background: #0f8f4d; border-color: #0f8f4d; color: #fff; }
            .cometido-btn.is-success:hover:not(:disabled) { background: #0b7a40; border-color: #0b7a40; color: #fff; }
            .cometido-btn.is-danger { background: #fff; border-color: #ef4444; color: #dc2626; }
            .cometido-btn.is-danger:hover:not(:disabled) { background: #fff1f2; border-color: #dc2626; color: #b91c1c; }
            .cometido-btn.is-warning { background: #fff8e1; border-color: #f5c451; color: #8a4b00; }
            .cometido-btn.is-warning:hover:not(:disabled) { background: #ffefb8; border-color: #e6ad19; color: #6f3c00; }
            .cometido-btn.is-secondary { background: #fff; border-color: #cbd5e1; color: #334155; }
            .cometido-btn.is-secondary:hover:not(:disabled) { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
            .cometido-btn.is-document { background: #fff; border-color: #dbe4f0; color: #334155; padding: .42rem .68rem; border-radius: .65rem; box-shadow: none; font-size: .82rem; font-weight: 800; }
            .cometido-btn.is-document:hover:not(:disabled) { background: #f8fafc; border-color: #b8c5d6; color: #0f172a; box-shadow: 0 .18rem .55rem rgba(15,23,42,.07); }
            .cometido-btn.is-document-primary { background: #eef6ff; border-color: #b9d9ff; color: #0d47a1; }
            .cometido-btn.is-document-primary:hover:not(:disabled) { background: #dcebff; border-color: #8ec1ff; color: #073b85; }
            .cometido-btn.is-lg { padding: .72rem 1.05rem; min-height: 3rem; font-size: .98rem; }
            .cometido-btn.is-full { width: 100%; }
            .cdp-actions-bar .cometido-btn { min-width: min(100%, 16rem); }


            .stage-panel-card { border: 1px solid #d7dee8; border-radius: 1rem; overflow: hidden; background: #fff; }
            .stage-panel-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .9rem; padding: 1rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
            .stage-panel-title-wrap { display: flex; align-items: flex-start; gap: .8rem; min-width: 0; }
            .stage-panel-icon { flex: 0 0 auto; width: 2.55rem; height: 2.55rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; box-shadow: 0 .35rem .8rem rgba(15,23,42,.12); }
            .stage-panel-icon.is-solicitud { background: #475569; }
            .stage-panel-icon.is-uatp { background: #7c3aed; }
            .stage-panel-icon.is-gdp { background: #0d6efd; }
            .stage-panel-icon.is-daf { background: #0f8f4d; }
            .stage-panel-icon.is-reembolso { background: #2563eb; }
            .stage-panel-icon.is-info { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
            .stage-panel-icon.is-cierre { background: #0f172a; }
            .stage-panel-kicker { font-size: .74rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .1rem; }
            .stage-status-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .32rem .62rem; border-radius: 999px; font-size: .73rem; font-weight: 800; border: 1px solid transparent; white-space: nowrap; }
            .stage-status-badge.is-current { color: #0d47a1; background: #eef6ff; border-color: #b9d9ff; }
            .stage-status-badge.is-completed { color: #0f5132; background: #ecfdf3; border-color: #bcebd0; }
            .stage-status-badge.is-warning { color: #8a4b00; background: #fff8e1; border-color: #f5d58b; }
            .stage-status-badge.is-danger { color: #b42318; background: #fff1f2; border-color: #fecdd3; }
            .stage-status-badge.is-muted { color: #475569; background: #f1f5f9; border-color: #dbe4f0; }
            .stage-info-banner { display: flex; gap: .75rem; align-items: flex-start; padding: .8rem .9rem; border-radius: .85rem; margin-bottom: 1rem; font-size: .86rem; line-height: 1.35; }
            .stage-info-banner i { flex: 0 0 auto; font-size: 1.1rem; margin-top: .05rem; }
            .stage-info-banner.is-uatp { background: #f5f3ff; border: 1px solid #ddd6fe; color: #4c1d95; }
            .stage-info-banner.is-gdp { background: #eef6ff; border: 1px solid #b9d9ff; color: #1e3a8a; }
            .stage-info-banner.is-daf { background: #ecfdf3; border: 1px solid #bcebd0; color: #0f5132; }
            .stage-info-banner.is-warning { background: #fff8e1; border: 1px solid #f5d58b; color: #8a4b00; }
            .stage-action-card { border: 1px solid #e3eaf3; border-radius: .95rem; padding: .9rem; background: #fff; box-shadow: 0 .22rem .8rem rgba(15,23,42,.035); }
            .stage-action-card + .stage-action-card { margin-top: .85rem; }
            .stage-action-title { display: flex; align-items: center; gap: .45rem; font-weight: 800; color: #0f172a; margin-bottom: .25rem; }
            .stage-action-topline { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: .15rem; }
            .stage-action-topline .stage-action-title { margin-bottom: 0; }
            .stage-action-stage-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .24rem .58rem; border-radius: 999px; border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
            .stage-action-help { font-size: .78rem; color: #64748b; margin-bottom: .7rem; line-height: 1.35; }
            .stage-action-card.is-approve { border-left: 4px solid #0f8f4d; }
            .stage-action-card.is-observe { border-left: 4px solid #f59f00; }
            .stage-action-card.is-reject { border-left: 4px solid #dc3545; }
            .stage-action-card.is-approve .stage-action-title i { color: #0f8f4d; }
            .stage-action-card.is-observe .stage-action-title i { color: #f59f00; }
            .stage-action-card.is-reject .stage-action-title i { color: #dc3545; }
            .stage-panel-body { padding: 1rem; }
            .stage-summary-stack { display: grid; gap: .65rem; }
            .stage-mini-row { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; padding: .7rem .8rem; border: 1px solid #e3eaf3; border-radius: .75rem; background: #f8fafc; font-size: .86rem; }
            .stage-mini-row strong { color: #0f172a; text-align: right; }
            .stage-next-box { padding: .85rem; border-radius: .85rem; border: 1px dashed #b8c5d6; background: #f8fafc; color: #334155; font-size: .84rem; line-height: 1.4; }
            .stage-report-box { border: 1px solid #e3eaf3; border-radius: .85rem; padding: .9rem 1rem; background: #fff; }
            .stage-report-box + .stage-report-box { margin-top: .75rem; }
            .stage-report-box-title { display: flex; align-items: center; gap: .45rem; font-weight: 800; color: #0f172a; margin-bottom: .3rem; }
            .stage-report-box-title i { color: #2563eb; }
            .stage-report-box-meta { color: #475569; font-size: .82rem; line-height: 1.45; }
            .stage-finance-grid { display: grid; gap: .75rem; }
            .stage-finance-item { border: 1px solid #e3eaf3; border-radius: .85rem; padding: .85rem; background: #fff; }
            .stage-finance-label { color: #64748b; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .025em; }
            .stage-finance-amount { font-size: 1.2rem; font-weight: 800; color: #0f172a; }
            .stage-mini-stack { display: grid; gap: .65rem; }
            .stage-doc-list { display: grid; gap: .75rem; }
            .stage-doc-item { border: 1px solid #e3eaf3; border-radius: .9rem; padding: .85rem; background: #fff; box-shadow: 0 .22rem .8rem rgba(15,23,42,.03); }
            .stage-doc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
            .stage-doc-title { display: flex; align-items: center; gap: .5rem; font-weight: 700; color: #0f172a; }
            .stage-doc-title i { color: #0d6efd; }
            .stage-doc-meta { margin-top: .35rem; font-size: .8rem; color: #64748b; word-break: break-word; }
            .stage-doc-actions { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .75rem; }
            .stage-side-note { padding: .75rem .85rem; border-radius: .85rem; border: 1px dashed #c9d5e5; background: #f8fafc; color: #334155; font-size: .82rem; line-height: 1.45; }

            .info-section-card { border: 1px solid #d7dee8; border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15, 23, 42, .045); }
            .info-section-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .85rem; padding: 1rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
            .info-section-title-wrap { display: flex; align-items: flex-start; gap: .8rem; min-width: 0; }
            .info-section-icon { flex: 0 0 auto; width: 2.45rem; height: 2.45rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.05rem; box-shadow: 0 .35rem .8rem rgba(15,23,42,.12); }
            .info-section-icon.is-summary { background: #0d6efd; }
            .info-section-icon.is-trip { background: #0f8f4d; }
            .info-section-icon.is-activity { background: #7c3aed; }
            .info-section-icon.is-docs { background: #475569; }
            .info-section-kicker { font-size: .72rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .1rem; }
            .info-section-help { color: #64748b; font-size: .82rem; margin-top: .18rem; line-height: 1.35; }
            .info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
            .info-grid.is-single { grid-template-columns: 1fr; }
            .info-item { border: 1px solid #e3eaf3; border-radius: .85rem; padding: .85rem; background: #f8fafc; min-height: 100%; }
            .info-item.is-wide { grid-column: 1 / -1; }
            .info-label { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .025em; margin-bottom: .28rem; }
            .info-value { color: #0f172a; font-weight: 700; line-height: 1.35; word-break: break-word; }
            .info-value.is-muted { color: #64748b; font-weight: 600; }
            .info-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .26rem .55rem; border-radius: 999px; background: #eef6ff; border: 1px solid #b9d9ff; color: #0d47a1; font-size: .78rem; font-weight: 800; }
            .info-chip.is-success { background: #ecfdf3; border-color: #bcebd0; color: #0f5132; }
            .info-chip.is-muted { background: #f1f5f9; border-color: #dbe4f0; color: #475569; }
            .info-chip.is-warning { background: #fff8e1; border-color: #f5d58b; color: #8a4b00; }
            .activity-description-box { border: 1px solid #dbe4f0; border-radius: .95rem; padding: 1rem; background: #f8fafc; color: #334155; line-height: 1.5; }
            .activity-description-title { display: flex; align-items: center; gap: .45rem; color: #0f172a; font-weight: 800; margin-bottom: .55rem; }
            .cdp-approved-box { border: 1px solid #cfe1ff; border-radius: .95rem; padding: .95rem; background: #f8fbff; }
            .cdp-approved-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .75rem; margin-bottom: .75rem; }
            .cdp-approved-title { font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: .45rem; }
            .cdp-approved-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; }
            .cdp-approved-item { border: 1px solid #e3eaf3; border-radius: .75rem; padding: .75rem; background: #fff; }
            .cdp-approved-label { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .025em; }
            .cdp-approved-amount { color: #0f172a; font-size: 1.05rem; font-weight: 900; }
            .cdp-detail-table { border: 1px solid #e5eaf1; border-radius: .85rem; overflow: hidden; }
            .cdp-detail-table table { margin-bottom: 0; }
            .cdp-detail-table thead th { background: #f8fafc; color: #0f172a; font-size: .76rem; }
            .doc-list { display: grid; gap: .75rem; }
            .doc-card-item { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .85rem; padding: .9rem; border: 1px solid #e3eaf3; border-radius: .95rem; background: #fff; box-shadow: 0 .22rem .7rem rgba(15,23,42,.025); }
            .doc-card-item.is-cdp { border-left: 4px solid #0d6efd; background: #f8fbff; }
            .doc-card-main { display: flex; align-items: flex-start; gap: .75rem; min-width: 0; }
            .doc-card-icon { flex: 0 0 auto; width: 2.35rem; height: 2.35rem; border-radius: .75rem; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; color: #475569; font-size: 1.05rem; }
            .doc-card-item.is-cdp .doc-card-icon { background: #eef6ff; color: #0d6efd; }
            .doc-card-title { color: #0f172a; font-weight: 800; line-height: 1.25; }
            .doc-card-meta { color: #64748b; font-size: .79rem; margin-top: .15rem; }
            .doc-card-actions { display: flex; flex-wrap: wrap; gap: .45rem; margin-left: auto; }
            .doc-empty-state { text-align: center; padding: 1.5rem; border: 1px dashed #b8c5d6; border-radius: .95rem; background: #f8fafc; color: #64748b; }

            @media (max-width: 991.98px) {
                .cometido-flow { grid-template-columns: 1fr; }
                .cometido-flow-step { min-height: auto; }
                .cometido-flow-step:not(:last-child)::after { top: auto; left: 1.78rem; bottom: -.95rem; width: 2px; height: .95rem; }
                .process-toolbar { width: 100%; justify-content: flex-start; }
                .process-diagram-toggle { width: 100%; justify-content: center; }
                .process-diagram-header, .process-diagram-title-wrap { flex-direction: column; }
                .process-stage-node { width: 200px; min-height: 150px; }
                .process-decision-node { width: 138px; min-height: 138px; }
                .process-decision-shape { width: 102px; height: 102px; }
                .process-decision-inner { width: 82px; }
                .cometido-history { padding-left: .15rem; }
                .cometido-history::before { left: 1.15rem; }
                .cometido-history-item { grid-template-columns: 2rem minmax(0, 1fr); column-gap: .75rem; }
                .info-grid, .cdp-approved-grid { grid-template-columns: 1fr; }
                .info-item.is-wide { grid-column: auto; }
                .doc-card-actions { width: 100%; margin-left: 0; }
                .doc-card-actions .btn { flex: 1 1 auto; }
            }
        </style>

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Cometido funcionario #{{ $cometido->id }}</h1>
                <p class="text-muted mb-0">{{ $cometido->funcionario_nombre }} · {{ $cometido->funcionario_rut }}</p>
            </div>
            <div class="cometido-actions-top">
                <a href="{{ route('tramites.cometidos-funcionarios.index') }}" class="btn cometido-btn is-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                @if ($activeRole === 'funcionario_estab' && $cometido->esEditablePorEstablecimiento())
                    <a href="{{ route('tramites.cometidos-funcionarios.edit', $cometido) }}" class="btn cometido-btn is-primary">
                        <i class="bi bi-pencil-square"></i> {{ $cometido->estado === 'en_revision_uatp' ? 'Editar solicitud' : 'Corregir / editar' }}
                    </a>
                @endif
                @if ($activeRole === 'funcionario_estab' && method_exists($cometido, 'esEliminablePorEstablecimiento') && $cometido->esEliminablePorEstablecimiento())
                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.destroy', $cometido) }}" class="d-inline" onsubmit="return confirm('¿Confirma que desea eliminar este cometido funcionario? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn cometido-btn is-danger">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


        <div class="card shadow-sm cometido-flow-card mb-4 stage-panel-card">
            <div class="stage-panel-header">
                <div class="stage-panel-title-wrap">
                    <span class="stage-panel-icon is-solicitud"><i class="bi bi-diagram-3"></i></span>
                    <div>
                        <div class="stage-panel-kicker">Seguimiento del proceso</div>
                        <h2 class="h5 mb-1">Estado del trámite</h2>
                        <div class="small text-muted">Avance según reglas de negocio del cometido funcionario.</div>
                    </div>
                </div>
                <div class="process-toolbar">
                    <button class="process-diagram-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#processDiagramPanel{{ $cometido->id }}" aria-expanded="false" aria-controls="processDiagramPanel{{ $cometido->id }}">
                        <span class="toggle-icon"><i class="bi bi-diagram-3"></i></span>
                        <span>Diagrama del proceso</span>
                    </button>
                    <span class="stage-status-badge {{ $estadoBadgeVisual }}">{{ $cometido->etiquetaEstado() }}</span>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="cometido-flow">
                    @foreach ($flujoSteps as $step)
                        @php
                            $stepVisual = $stepClasses[$step['status']] ?? $stepClasses['pending'];
                            $stepStageIcon = $stepIconMap[$step['key']] ?? $stepVisual['icon'];
                        @endphp
                        <div class="cometido-flow-step {{ $stepVisual['class'] }}">
                            <div class="cometido-flow-step-head">
                                <div class="cometido-flow-dot"><i class="bi {{ $stepStageIcon }}"></i></div>
                                <span class="cometido-flow-badge">{{ $stepVisual['badge'] }}</span>
                            </div>
                            <div class="cometido-flow-kicker">Etapa {{ $loop->iteration }}</div>
                            <div class="cometido-flow-title">{{ $step['label'] }}</div>
                            <div class="cometido-flow-desc">{{ $step['description'] }}</div>
                        </div>
                    @endforeach
                </div>


                <div class="collapse" id="processDiagramPanel{{ $cometido->id }}">
                    <div class="process-diagram-panel">
                        <div class="process-diagram-header">
                            <div class="process-diagram-title-wrap">
                                <span class="process-diagram-icon"><i class="bi bi-diagram-3"></i></span>
                                <div>
                                    <div class="process-diagram-title">Diagrama de flujo del cometido</div>
                                    <div class="process-diagram-subtitle">Visualiza el flujo completo del proceso y el estado actual de cada etapa.</div>
                                </div>
                            </div>
                            <div class="process-diagram-close-hint d-none d-md-block"><i class="bi bi-arrows-collapse"></i></div>
                        </div>
                        <div class="process-diagram-body">
                            <div class="process-diagram-scroll">
                                <div class="process-diagram-track">
                                    @foreach ($diagramNodes as $node)
                                        <div class="process-diagram-node process-node-type-{{ $node['type'] }}">
                                            @if ($node['type'] === 'terminal')
                                                <div class="process-terminal-node {{ $node['class'] }}">
                                                    <div class="node-title">{{ $node['label'] }}</div>
                                                    <span class="node-badge">{{ $node['badge'] }}</span>
                                                </div>
                                            @elseif ($node['type'] === 'decision')
                                                <div class="process-decision-node">
                                                    <div class="process-decision-shape {{ $node['class'] }}">
                                                        <div class="process-decision-inner">
                                                            <div class="process-decision-title">{{ $node['label'] }}</div>
                                                            <span class="process-decision-value">{{ $node['decision'] }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="process-stage-node {{ $node['class'] }}">
                                                    <div class="node-head">
                                                        <span class="node-icon"><i class="bi {{ $node['icon'] }}"></i></span>
                                                        <span class="node-badge">{{ $node['badge'] }}</span>
                                                    </div>
                                                    <div class="node-kicker">{{ strtoupper(str_replace('_', ' ', $node['key'])) }}</div>
                                                    <div class="node-title">{{ $node['label'] }}</div>
                                                    <div class="node-desc">{{ $node['description'] }}</div>
                                                </div>
                                            @endif
                                        </div>
                                        @if (! $loop->last)
                                            <div class="process-diagram-connector" aria-hidden="true"></div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <div class="process-diagram-legend">
                                <div class="process-diagram-legend-title">Leyenda</div>
                                <div class="process-diagram-legend-item"><span class="process-diagram-legend-swatch is-pending"></span> Pendiente</div>
                                <div class="process-diagram-legend-item"><span class="process-diagram-legend-swatch is-completed"></span> Completado</div>
                                <div class="process-diagram-legend-item"><span class="process-diagram-legend-swatch is-current"></span> Actual</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    

        <div class="row g-4">
            <div class="col-lg-8">


                    @if ($puedeResolverDirectorSinDisponibilidadVista && $estadoActualCometido === 'pendiente_autorizacion_director_sin_disponibilidad')
                        <div class="card shadow-sm mb-4 border-warning">
                            <div class="card-header bg-warning-subtle border-warning p-4">
                                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                                    <div>
                                        <div class="small text-uppercase fw-bold text-warning-emphasis mb-1">Decisión Director Ejecutivo</div>
                                        <h2 class="h4 mb-0">Falta de disponibilidad para viático</h2>
                                    </div>
                                    <span class="badge text-bg-warning"><i class="bi bi-exclamation-triangle me-1"></i> Pendiente decisión</span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="alert alert-warning small">
                                    Planificación detectó que el viático calculado no cuenta con disponibilidad presupuestaria suficiente. No se pagará viático sin disponibilidad. El Director Ejecutivo debe resolver si se reconvierte el cometido a reembolso o si se rechaza la continuidad financiera.
                                </div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4"><div class="small text-muted">Viático calculado</div><div class="fw-bold fs-5">${{ number_format((int) ($cometido->monto_viatico_solicitado_director ?? $cometido->cdp_viatico_total ?? 0), 0, ',', '.') }}</div></div>
                                    <div class="col-md-4"><div class="small text-muted">Saldo disponible</div><div class="fw-bold fs-5">${{ number_format((int) ($cometido->monto_disponible_director ?? 0), 0, ',', '.') }}</div></div>
                                    <div class="col-md-4"><div class="small text-muted">Diferencia</div><div class="fw-bold fs-5 text-danger">${{ number_format((int) ($cometido->diferencia_presupuestaria_director ?? 0), 0, ',', '.') }}</div></div>
                                </div>
                                @if (!empty($cometido->fundamento_planificacion_director))
                                    <div class="mb-3"><div class="small text-muted">Fundamento de Planificación</div><div class="border rounded-3 p-3 bg-light">{{ $cometido->fundamento_planificacion_director }}</div></div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <form method="POST" action="{{ route('tramites.cometidos-funcionarios.director-sin-disponibilidad.aprobar-reconversion', $cometido) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="form-label fw-semibold">Observación obligatoria</label>
                                            <textarea name="observacion_director" class="form-control mb-3" rows="4" required placeholder="Fundamente la reconversión a reembolso por falta de disponibilidad presupuestaria"></textarea>
                                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-arrow-repeat me-1"></i> Aprobar reconversión a reembolso</button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <form method="POST" action="{{ route('tramites.cometidos-funcionarios.director-sin-disponibilidad.rechazar', $cometido) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="form-label fw-semibold">Observación obligatoria</label>
                                            <textarea name="observacion_director" class="form-control mb-3" rows="4" required placeholder="Fundamente el rechazo de continuidad financiera"></textarea>
                                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i> Rechazar</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($puedeRevisarCdpVista && ($cometido->estado === 'en_revision_cdp' || $estadoViaticoTimeline === 'en_revision_cdp'))
                            <div class="card shadow-sm cdp-review-card mb-4">
                                <div class="card-header cdp-review-header p-4">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                        <div>
                                            <div class="small text-muted mb-1">Etapa presupuestaria</div>
                                            <h2 class="h4 mb-0">Revisión CDP - Planificación</h2>
                                        </div>
                                        <span class="cdp-status-badge"><i class="bi bi-shield-check"></i> En revisión CDP</span>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <div class="cdp-info-banner mb-4">
                                        <span class="cdp-info-icon"><i class="bi bi-info-lg"></i></span>
                                        <div>
                                            <div class="fw-semibold mb-1">Revisión presupuestaria requerida</div>
                                            <div>La solicitud requiere revisión presupuestaria porque solicita viático y/o devolución de gastos.</div>
                                        </div>
                                    </div>

                                    @if ($cometido->solicita_viatico)
                                        @php
                                            $origenDisponibilidadVista = $cometido->esAdministracionCentral() ? 'administracion_central' : 'establecimientos';
                                            $fechaDisponibilidadVista = $cometido->fecha_desde ? \Carbon\Carbon::parse($cometido->fecha_desde) : now();
                                            $disponibilidadViaticoVista = \App\Models\ViaticoDisponibilidadPresupuestaria::query()
                                                ->where('anio', (int) $fechaDisponibilidadVista->year)
                                                ->where('activo', true)
                                                ->whereIn('origen_tipo', [$origenDisponibilidadVista, 'ambos'])
                                                ->whereDate('vigente_desde', '<=', $fechaDisponibilidadVista->toDateString())
                                                ->where(function ($query) use ($fechaDisponibilidadVista) {
                                                    $query->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fechaDisponibilidadVista->toDateString());
                                                })
                                                ->orderByRaw('CASE WHEN origen_tipo = ? THEN 0 ELSE 1 END', [$origenDisponibilidadVista])
                                                ->first();
                                            $montoViaticoEstimadoVista = (int) ($viaticoAutomaticoCdp['total'] ?? 0);
                                            $saldoDisponibleVista = (int) ($disponibilidadViaticoVista->saldo_disponible ?? 0);
                                            $saldoInsuficienteVista = $montoViaticoEstimadoVista > 0 && $disponibilidadViaticoVista && $saldoDisponibleVista < $montoViaticoEstimadoVista;
                                        @endphp
                                        <div class="alert {{ $saldoInsuficienteVista || ! $disponibilidadViaticoVista ? 'alert-warning' : 'alert-success' }} small mb-4">
                                            <div class="fw-semibold mb-1"><i class="bi bi-wallet2 me-1"></i> Disponibilidad presupuestaria para viáticos</div>
                                            @if ($disponibilidadViaticoVista)
                                                <div>Origen: <strong>{{ $disponibilidadViaticoVista->origenLabel() }}</strong> · Saldo disponible: <strong>${{ number_format($saldoDisponibleVista, 0, ',', '.') }}</strong> · Viático estimado CDP: <strong>${{ number_format($montoViaticoEstimadoVista, 0, ',', '.') }}</strong></div>
                                                @if ($saldoInsuficienteVista)
                                                    <div class="mt-1">El saldo es insuficiente. Al intentar aprobar CDP, el sistema bloqueará la operación y luego se deberá activar la autorización excepcional del Director Ejecutivo.</div>
                                                @else
                                                    <div class="mt-1">Al aprobar el CDP de viático, el monto será descontado automáticamente como compromiso presupuestario.</div>
                                                @endif
                                            @else
                                                <div>No existe disponibilidad activa para este origen/año. Cree un registro en el mantenedor <strong>Viáticos disponibilidad</strong> antes de aprobar el CDP.</div>
                                            @endif
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ url('/tramites/cometidos-funcionarios/'.$cometido->id.'/cdp/aprobar') }}" enctype="multipart/form-data" id="cdpAprobarForm">
                                        @csrf
                                        @method('PATCH')

                                        <div class="cdp-catalog-card p-3 p-md-4 mb-4">
                                            @if ($cometido->solicita_viatico)
                                                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start mb-3">
                                                    <div>
                                                        <div class="small text-uppercase fw-bold text-muted">Cálculo automático de viático</div>
                                                        <h3 class="h5 mb-1">Monto según estamento, escalafón, fechas y horarios</h3>
                                                        <div class="text-muted small">El viático se asigna automáticamente. Planificación sólo debe adjuntar el CDP y registrar la referencia.</div>
                                                    </div>
                                                    @if (!empty($viaticoAutomaticoCdp['catalogo']))
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $viaticoAutomaticoCdp['catalogo']->estamento }} / {{ $viaticoAutomaticoCdp['catalogo']->cargo_funcion }}</span>
                                                    @endif
                                                </div>
                                                @if (!empty($viaticoAutomaticoCdp['catalogo']))
                                                    <div class="row g-2 small">
                                                        <div class="col-md-3"><strong>Categoría:</strong><br>{{ $viaticoAutomaticoCdp['categoria'] ?? '—' }}</div>
                                                        <div class="col-md-3"><strong>Valor 100%:</strong><br>${{ number_format((int) $viaticoAutomaticoCdp['catalogo']->valor_100, 0, ',', '.') }}</div>
                                                        <div class="col-md-3"><strong>Valor 60%:</strong><br>${{ number_format((int) ($viaticoAutomaticoCdp['catalogo']->valor_60 ?? 0), 0, ',', '.') }}</div>
                                                        <div class="col-md-3"><strong>Valor 40%:</strong><br>${{ number_format((int) $viaticoAutomaticoCdp['catalogo']->valor_40, 0, ',', '.') }}</div>
                                                        <div class="col-md-3"><strong>Total automático:</strong><br>${{ number_format((int) ($viaticoAutomaticoCdp['total'] ?? 0), 0, ',', '.') }}</div>
                                                    </div>
                                                @else
                                                    <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>{{ $viaticoAutomaticoCdp['error'] ?? 'No fue posible determinar el valor automático de viático. Revise el escalafón/cargo y el catálogo vigente.' }}</div>
                                                @endif
                                                @if ($forzarViatico40PorAlojamiento)
                                                    <div class="alert alert-info small mt-3 mb-0">
                                                        <i class="bi bi-info-circle"></i> El servicio o invitación contempla alojamiento y el cometido dura más de un día; por regla, el viático será calculado al 40% en todos los días.
                                                    </div>
                                                @endif
                                            @endif

                                            @if ($cometido->solicita_reembolso && ! $esFlujoParaleloCdpVista)
                                                <div class="mt-3">
                                                    <label class="form-label required fw-semibold">Estamento y cargo / función para asignar tope de reembolso</label>
                                                    <select name="cdp_catalogo_valor_id" id="cdpCatalogoValor" class="form-select form-select-lg" required>
                                                        <option value="">Seleccione valor de catálogo...</option>
                                                        @foreach ($cdpValoresCatalogo as $valorCatalogo)
                                                            <option value="{{ $valorCatalogo->id }}" @selected(($catalogoReembolsoSugerido?->id ?? null) === $valorCatalogo->id)>
                                                                {{ $valorCatalogo->estamento }} / {{ $valorCatalogo->cargo_funcion }} — 100%: ${{ number_format((int) $valorCatalogo->valor_100, 0, ',', '.') }} · 60%: ${{ number_format((int) ($valorCatalogo->valor_60 ?? 0), 0, ',', '.') }} · 40%: ${{ number_format((int) $valorCatalogo->valor_40, 0, ',', '.') }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <div class="form-text mt-2">Este catálogo se usa para el tope máximo de reembolso. El viático, si existe, se calcula automáticamente.</div>
                                                    @if ($catalogoReembolsoSugerido && $cometido->esAdministracionCentral())
                                                        <div class="alert alert-info small mt-3 mb-0">
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            Valor sugerido automáticamente según datos del solicitante: <strong>{{ $catalogoReembolsoSugerido->estamento }} / {{ $catalogoReembolsoSugerido->cargo_funcion }}</strong>.
                                                            @if (($viaticoAutomaticoCdp['categoria'] ?? null) === 'Docente / Docentes')
                                                                Para funcionario AC sin grado y con escalafón Docente, se aplica el valor vigente <strong>Docente / Docentes</strong> del catálogo.
                                                            @elseif (str_starts_with((string) ($viaticoAutomaticoCdp['categoria'] ?? ''), 'Código Administrativo /'))
                                                                Para funcionario AC con grado informado, se aplica el tramo vigente de <strong>Código Administrativo</strong> según grado.
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                                @if ($cdpValoresCatalogo->isEmpty())
                                                    <div class="alert alert-warning small mt-3 mb-0">No existen valores vigentes en el catálogo para asignar CDP. Revise Catálogos → Viáticos y Reembolsos.</div>
                                                @endif
                                            @endif

                                            @if ($esFlujoParaleloCdpVista)
                                                <div class="alert alert-info small mt-3 mb-0">
                                                    <i class="bi bi-info-circle me-1"></i> Este CDP inicial corresponde sólo al viático. El reembolso no se selecciona ni se autoriza en esta etapa; se gestionará posteriormente en el flujo de rendición, revisión DAF y CDP de rendición.
                                                </div>
                                            @endif
                                        </div>

                                        @if ($diasCometidoCdp->isEmpty())
                                            <div class="alert alert-warning small">No fue posible construir los días del cometido. Revise las fechas antes de aprobar CDP.</div>
                                        @else
                                            <div class="row g-4 align-items-start">
                                                <div class="col-xl-8">
                                                    @if ($cometido->solicita_viatico)
                                                        <div class="cdp-beneficio-card mb-4" data-tipo="viatico">
                                                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                                                <div class="d-flex align-items-start gap-3">
                                                                    <span class="cdp-beneficio-icon is-viatico"><i class="bi bi-briefcase"></i></span>
                                                                    <div>
                                                                        <h3 class="h5 mb-1">Viático</h3>
                                                                        <div class="text-muted small">Monto fijo autorizado por día según porcentaje seleccionado.</div>
                                                                        @if ($forzarViatico40PorAlojamiento)
                                                                            <div class="small text-info fw-semibold mt-1">Porcentaje forzado al 40% por alojamiento contemplado.</div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div class="form-check form-switch m-0 text-nowrap">
                                                                    <input type="hidden" name="cdp_beneficios_habilitados[viatico]" value="0">
                                                                    <input class="form-check-input cdp-beneficio-toggle" type="checkbox" role="switch" id="cdpViaticoHabilitado" name="cdp_beneficios_habilitados[viatico]" value="1" data-tipo="viatico" checked>
                                                                    <label class="form-check-label small fw-semibold ms-1" for="cdpViaticoHabilitado">Autorizar pago</label>
                                                                </div>
                                                            </div>
                                                            <div class="alert alert-warning small py-2 mb-3 d-none cdp-beneficio-desactivado" data-tipo="viatico">Viático deshabilitado por Planificación: todos los días quedarán en $0 y no se podrá seleccionar porcentaje.</div>
                                                            <div class="table-responsive">
                                                                <table class="table table-hover align-middle mb-0 cdp-montos-table" data-tipo="viatico" data-auto="1">
                                                                    <thead><tr><th>Día</th><th>Fecha</th><th>% automático</th><th>Regla</th><th class="text-end">Valor diario</th></tr></thead>
                                                                    <tbody>
                                                                        @foreach ($diasCometidoCdp as $diaCdp)
                                                                            @php
                                                                                $autoRow = $viaticoAutomaticoRows->get($diaCdp['fecha'], ['porcentaje' => 0, 'monto' => 0, 'regla' => 'No calculado']);
                                                                            @endphp
                                                                            <tr data-auto-monto="{{ (int) ($autoRow['monto'] ?? 0) }}">
                                                                                <td class="fw-semibold">{{ $diaCdp['numero'] }}</td>
                                                                                <td>{{ $diaCdp['label'] }}</td>
                                                                                <td>
                                                                                    <input type="hidden" name="cdp_montos[viatico][{{ $diaCdp['fecha'] }}][porcentaje]" value="{{ (int) ($autoRow['porcentaje'] ?? 0) }}">
                                                                                    <span class="badge {{ (int) ($autoRow['porcentaje'] ?? 0) === 100 ? 'bg-success' : ((int) ($autoRow['porcentaje'] ?? 0) === 40 ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ (int) ($autoRow['porcentaje'] ?? 0) }}%</span>
                                                                                </td>
                                                                                <td class="small text-muted">{{ $autoRow['regla'] ?? 'No calculado' }}</td>
                                                                                <td class="text-end cdp-valor-dia">${{ number_format((int) ($autoRow['monto'] ?? 0), 0, ',', '.') }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <div class="cdp-total-bar is-viatico">
                                                                <span>Total viático fijo</span>
                                                                <span class="cdp-total-tipo" data-summary-target="viatico">$0</span>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    @if ($cometido->solicita_reembolso && ! $esFlujoParaleloCdpVista)
                                                        <div class="cdp-beneficio-card mb-4" data-tipo="reembolso">
                                                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                                                <div class="d-flex align-items-start gap-3">
                                                                    <span class="cdp-beneficio-icon is-reembolso"><i class="bi bi-receipt"></i></span>
                                                                    <div>
                                                                        <h3 class="h5 mb-1">Reembolso</h3>
                                                                        <div class="text-muted small">Tope máximo autorizado por día; el pago final dependerá de la rendición respaldada.</div>
                                                                    </div>
                                                                </div>
                                                                <div class="form-check form-switch m-0 text-nowrap">
                                                                    <input type="hidden" name="cdp_beneficios_habilitados[reembolso]" value="0">
                                                                    <input class="form-check-input cdp-beneficio-toggle" type="checkbox" role="switch" id="cdpReembolsoHabilitado" name="cdp_beneficios_habilitados[reembolso]" value="1" data-tipo="reembolso" checked>
                                                                    <label class="form-check-label small fw-semibold ms-1" for="cdpReembolsoHabilitado">Autorizar pago</label>
                                                                </div>
                                                            </div>
                                                            <div class="alert alert-warning small py-2 mb-3 d-none cdp-beneficio-desactivado" data-tipo="reembolso">Reembolso deshabilitado por Planificación: todos los días quedarán en $0 y no se podrá seleccionar porcentaje.</div>
                                                            <div class="table-responsive">
                                                                <table class="table table-hover align-middle mb-0 cdp-montos-table" data-tipo="reembolso">
                                                                    <thead><tr><th>Día</th><th>Fecha</th><th>%</th><th class="text-end">Valor diario (tope máximo)</th></tr></thead>
                                                                    <tbody>
                                                                        @foreach ($diasCometidoCdp as $diaCdp)
                                                                            <tr>
                                                                                <td class="fw-semibold">{{ $diaCdp['numero'] }}</td>
                                                                                <td>{{ $diaCdp['label'] }}</td>
                                                                                <td>
                                                                                    <select name="cdp_montos[reembolso][{{ $diaCdp['fecha'] }}][porcentaje]" class="form-select form-select-sm cdp-porcentaje" required>
                                                                                        <option value="100">100%</option>
                                                                                        <option value="60">60%</option>
                                                                                        <option value="40">40%</option>
                                                                                        <option value="0">0%</option>
                                                                                    </select>
                                                                                </td>
                                                                                <td class="text-end cdp-valor-dia">$0</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <div class="cdp-total-bar is-reembolso">
                                                                <span>Total máximo reembolso</span>
                                                                <span class="cdp-total-tipo" data-summary-target="reembolso">$0</span>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="col-xl-4">
                                                    <div class="cdp-summary-card">
                                                        <div class="d-flex align-items-center gap-2 mb-3">
                                                            <span class="cdp-beneficio-icon is-viatico" style="width:2.2rem;height:2.2rem;font-size:1rem;"><i class="bi bi-bar-chart"></i></span>
                                                            <h3 class="h5 mb-0">Resumen CDP</h3>
                                                        </div>
                                                        @if ($cometido->solicita_viatico)
                                                            <div class="cdp-summary-line">
                                                                <span>Total viático</span>
                                                                <strong id="cdpTotalViaticoResumen">$0</strong>
                                                            </div>
                                                        @endif
                                                        @if ($cometido->solicita_reembolso && ! $esFlujoParaleloCdpVista)
                                                            <div class="cdp-summary-line">
                                                                <span>Total reembolso<br><span class="small text-muted">tope máximo</span></span>
                                                                <strong id="cdpTotalReembolsoResumen">$0</strong>
                                                            </div>
                                                        @endif
                                                        <div class="cdp-summary-total">
                                                            <span class="fw-semibold">Total CDP</span>
                                                            <span id="cdpTotalGeneral">$0</span>
                                                        </div>

                                                        <label class="form-label required fw-semibold">Referencia / Nº CDP</label>
                                                        <input type="text" name="cdp_referencia" class="form-control mb-3" maxlength="255" placeholder="Ingrese referencia o Nº de CDP" required>

                                                        <label class="form-label required fw-semibold">Adjuntar CDP</label>
                                                        <div class="cdp-upload-box mb-2">
                                                            <div class="fs-2 text-muted mb-2"><i class="bi bi-cloud-arrow-up"></i></div>
                                                            <div class="small text-muted mb-2">Adjunte el certificado emitido por Planificación.</div>
                                                            <input type="file" name="archivo_cdp" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                                                        </div>
                                                        <div class="form-text mb-3">Formatos permitidos: PDF, JPG, PNG, DOC o DOCX. Tamaño máximo: 10 MB.</div>

                                                        <label class="form-label">Observación opcional</label>
                                                        <textarea name="observacion" class="form-control mb-3" rows="3" placeholder="Comentario para GDP o registro interno"></textarea>

                                                        <div class="alert alert-primary small py-2 mb-0">
                                                            <i class="bi bi-info-circle me-1"></i> El archivo CDP es obligatorio para continuar con la aprobación.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="cdp-actions-bar">
                                            <button class="btn cometido-btn is-success is-lg" type="submit" @if ($diasCometidoCdp->isEmpty() || ($cometido->solicita_reembolso && ! $esFlujoParaleloCdpVista && $cdpValoresCatalogo->isEmpty()) || ($cometido->solicita_viatico && empty($viaticoAutomaticoCdp['catalogo']))) disabled @endif>
                                                <i class="bi bi-check-circle"></i> Aprobar CDP y derivar a GDP
                                            </button>
                                        </div>
                                    </form>

                                    <form method="POST" action="{{ url('/tramites/cometidos-funcionarios/'.$cometido->id.'/cdp/rechazar') }}" class="cdp-reject-box mt-4">
                                        @csrf
                                        @method('PATCH')
                                        <div class="row g-3 align-items-end">
                                            <div class="col-lg-8">
                                                <label class="form-label required fw-semibold">Motivo / observación presupuestaria para rechazo</label>
                                                <textarea name="observacion" class="form-control" rows="2" required placeholder="Indique el motivo para autorizar el cometido sin gasto"></textarea>
                                            </div>
                                            <div class="col-lg-4 text-lg-end">
                                                <button class="btn cometido-btn is-danger is-lg is-full" type="submit"><i class="bi bi-x-circle"></i> Rechazar CDP y autorizar sin gasto</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif


                                @if (($puedeRevisarJefaturaAc ?? false) && $cometido->estado === 'en_revision_jefatura_ac')
                                    <div class="card shadow-sm mb-4 stage-panel-card">
                                        <div class="stage-panel-header">
                                            <div class="stage-panel-title-wrap">
                                                <span class="stage-panel-icon is-uatp"><i class="bi bi-person-check"></i></span>
                                                <div>
                                                    <div class="stage-panel-kicker">Administración Central</div>
                                                    <h2 class="h5 mb-0">Autorización Jefatura AC</h2>
                                                    <div class="small text-muted mt-1">Autoriza, observa o rechaza la solicitud del funcionario AC.</div>
                                                </div>
                                            </div>
                                            <span class="stage-status-badge is-current"><i class="bi bi-hourglass-split"></i> En revisión jefatura</span>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.jefatura-ac.aprobar', $cometido) }}" class="stage-action-card is-approve">
                                                @csrf @method('PATCH')
                                                <div class="stage-action-title"><i class="bi bi-check-circle"></i> Autorizar cometido</div>
                                                <label class="form-label small fw-semibold">Observación opcional</label>
                                                <textarea name="observacion" class="form-control mb-2" rows="3"></textarea>
                                                <button class="btn cometido-btn is-success is-full" type="submit"><i class="bi bi-check-circle"></i> Autorizar y continuar</button>
                                            </form>
                                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.jefatura-ac.observar', $cometido) }}" class="stage-action-card is-observe">
                                                @csrf @method('PATCH')
                                                <div class="stage-action-title"><i class="bi bi-chat-left-text"></i> Observar para corrección</div>
                                                <textarea name="observacion" class="form-control mb-2" rows="3" required></textarea>
                                                <button class="btn cometido-btn is-warning is-full" type="submit"><i class="bi bi-chat-left-text"></i> Observar</button>
                                            </form>
                                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.jefatura-ac.rechazar', $cometido) }}" class="stage-action-card is-reject">
                                                @csrf @method('PATCH')
                                                <div class="stage-action-title"><i class="bi bi-x-circle"></i> Rechazar solicitud</div>
                                                <textarea name="observacion" class="form-control mb-2" rows="3" required></textarea>
                                                <button class="btn cometido-btn is-danger is-full" type="submit"><i class="bi bi-x-circle"></i> Rechazar</button>
                                            </form>
                                        </div>
                                    </div>
                                @endif

                                @if ($cometido->requiere_pasaje_aereo)
                                    @php
                    $pasaje = $cometido->pasajeAereo->first();
                @endphp
                                    @if(($puedeGestionarPasajeReserva ?? false) && (!$pasaje || in_array($pasaje->estado_pasaje, ['pendiente_reserva','pendiente_compra'], true)))
                                        <div class="card shadow-sm mb-4 stage-panel-card">
                                            <div class="stage-panel-header"><h2 class="h5 mb-0"><i class="bi bi-airplane"></i> DAF Compra - Pasaje aéreo</h2></div>
                                            <div class="card-body">
                                                @if(!$pasaje || $pasaje->estado_pasaje === 'pendiente_reserva')
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.pasaje.reserva', $cometido) }}" enctype="multipart/form-data">
                                                        @csrf
                                                        <label class="form-label required">Archivo de reserva</label>
                                                        <input type="file" name="archivo_reserva" class="form-control mb-2" required>
                                                        <textarea name="observacion" class="form-control mb-2" rows="2" placeholder="Observación"></textarea>
                                                        <button class="btn cometido-btn is-primary is-full"><i class="bi bi-upload"></i> Cargar reserva</button>
                                                    </form>
                                                @elseif($pasaje->estado_pasaje === 'pendiente_compra')
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.pasaje.compra', $cometido) }}" enctype="multipart/form-data">
                                                        @csrf
                                                        <input type="text" name="proveedor" class="form-control mb-2" placeholder="Proveedor" required>
                                                        <input type="number" name="monto" class="form-control mb-2" placeholder="Monto" min="0" required>
                                                        <input type="date" name="fecha_compra" class="form-control mb-2" required>
                                                        <input type="text" name="numero_oc" class="form-control mb-2" placeholder="N° OC opcional">
                                                        <label class="form-label required">Archivo boleto / compra</label>
                                                        <input type="file" name="archivo_compra" class="form-control mb-2" required>
                                                        <textarea name="observacion" class="form-control mb-2" rows="2" placeholder="Observación"></textarea>
                                                        <button class="btn cometido-btn is-success is-full"><i class="bi bi-ticket-perforated"></i> Registrar compra</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    @if(($puedeGestionarPasajeCdp ?? false) && $pasaje && $pasaje->estado_pasaje === 'pendiente_cdp_pasaje')
                                        <div class="card shadow-sm mb-4 stage-panel-card">
                                            <div class="stage-panel-header"><h2 class="h5 mb-0"><i class="bi bi-file-earmark-check"></i> CDP Pasaje aéreo</h2></div>
                                            <div class="card-body">
                                                <form method="POST" action="{{ route('tramites.cometidos-funcionarios.pasaje.cdp', $cometido) }}" enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="text" name="cdp_referencia" class="form-control mb-2" placeholder="Referencia / N° CDP" required>
                                                    <input type="date" name="cdp_fecha" class="form-control mb-2" required>
                                                    <input type="file" name="archivo_cdp_pasaje" class="form-control mb-2" required>
                                                    <textarea name="observacion" class="form-control mb-2" rows="2" placeholder="Observación"></textarea>
                                                    <button class="btn cometido-btn is-success is-full"><i class="bi bi-check-circle"></i> Cargar CDP pasaje</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                @if ($puedeRevisarUatpVista && $cometido->estado === 'en_revision_uatp')
                                    <div class="card shadow-sm mb-4 stage-panel-card">
                                        <div class="stage-panel-header">
                                            <div class="stage-panel-title-wrap">
                                                <span class="stage-panel-icon is-uatp"><i class="bi bi-mortarboard"></i></span>
                                                <div>
                                                    <div class="stage-panel-kicker">Etapa técnica pedagógica</div>
                                                    <h2 class="h5 mb-0">Revisión UATP</h2>
                                                    <div class="small text-muted mt-1">Pertinencia, respaldo y consistencia de la solicitud.</div>
                                                </div>
                                            </div>
                                            <span class="stage-status-badge is-current"><i class="bi bi-hourglass-split"></i> En revisión UATP</span>
                                        </div>
                                        <div class="card-body">
                                            <div class="stage-info-banner is-uatp">
                                                <i class="bi bi-info-circle"></i>
                                                <div>
                                                    <div class="fw-semibold mb-1">Acciones disponibles para UATP</div>
                                                    <div>Puede aprobar la pertinencia, observar para corrección del establecimiento o rechazar fundadamente la solicitud.</div>
                                                </div>
                                            </div>

                                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.uatp.aprobar', $cometido) }}" class="stage-action-card is-approve">
                                                @csrf
                                                @method('PATCH')
                                                <div class="stage-action-title"><i class="bi bi-check-circle"></i> Aprobar solicitud</div>
                                                <div class="stage-action-help">Deriva el cometido a revisión presupuestaria si solicita gasto, o a GDP si no corresponde CDP.</div>
                                                <label class="form-label small fw-semibold">Observación opcional</label>
                                                <textarea name="observacion" class="form-control mb-2" rows="3" placeholder="Comentario interno o antecedente de aprobación"></textarea>
                                                <button class="btn cometido-btn is-success is-full" type="submit"><i class="bi bi-check-circle"></i> Aprobar y continuar</button>
                                            </form>

                                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.uatp.observar', $cometido) }}" class="stage-action-card is-observe">
                                                @csrf
                                                @method('PATCH')
                                                <div class="stage-action-title"><i class="bi bi-chat-left-text"></i> Observar para corrección</div>
                                                <div class="stage-action-help">Devuelve la solicitud al establecimiento para que corrija o complete antecedentes.</div>
                                                <label class="form-label required small fw-semibold">Observación para corregir</label>
                                                <textarea name="observacion" class="form-control mb-2" rows="3" required placeholder="Indique claramente qué debe corregir el establecimiento"></textarea>
                                                <button class="btn cometido-btn is-warning is-full" type="submit"><i class="bi bi-chat-left-text"></i> Observar solicitud</button>
                                            </form>

                                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.uatp.rechazar', $cometido) }}" class="stage-action-card is-reject">
                                                @csrf
                                                @method('PATCH')
                                                <div class="stage-action-title"><i class="bi bi-x-circle"></i> Rechazar solicitud</div>
                                                <div class="stage-action-help">Finaliza el trámite por rechazo técnico/pedagógico. Esta acción requiere fundamento.</div>
                                                <label class="form-label required small fw-semibold">Motivo de rechazo</label>
                                                <textarea name="observacion" class="form-control mb-2" rows="3" required placeholder="Registre el fundamento del rechazo"></textarea>
                                                <button class="btn cometido-btn is-danger is-full" type="submit"><i class="bi bi-x-circle"></i> Rechazar solicitud</button>
                                            </form>
                                        </div>
                                    </div>
                                @endif

                                @php
                                    $estadoReembolsoPermiteRendir = in_array($estadoReembolsoTimeline, ['pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso', 'rendicion_enviada_pendiente_informe', 'rendicion_observada_daf'], true);
                                @endphp
                                @if ((bool) $cometido->solicita_reembolso && (($activeRoleVista === 'funcionario_estab' && ! $cometido->esAdministracionCentral()) || ($activeRoleVista === 'funcionario_ac' && $cometido->esAdministracionCentral() && (int) $cometido->user_id === (int) auth()->id()) || $activeRoleVista === 'admin') && $estadoReembolsoPermiteRendir)
                                    <div class="card shadow-sm mb-4 stage-panel-card">
                                        <div class="stage-panel-header">
                                            <div class="stage-panel-title-wrap">
                                                <span class="stage-panel-icon is-reembolso"><i class="bi bi-receipt"></i></span>
                                                <div>
                                                    <div class="stage-panel-kicker">Rendición de reembolso</div>
                                                    <h2 class="h5 mb-0">Rendir documentos fiscales</h2>
                                                    <div class="small text-muted mt-1">Acceso habilitado para {{ $cometido->esAdministracionCentral() ? 'el funcionario AC solicitante' : 'el establecimiento' }} en el flujo de reembolso.</div>
                                                </div>
                                            </div>
                                            <span class="stage-status-badge {{ in_array($estadoReembolsoTimeline, ['pendiente_rendicion', 'pendiente_rendicion_informe', 'en_rendicion_reembolso'], true) || $estadoActualCometido === 'en_gestion_paralela' ? 'is-current' : 'is-muted' }}">
                                                <i class="bi bi-receipt-cutoff"></i> Rendición
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <div class="stage-info-banner is-daf">
                                                <i class="bi bi-info-circle"></i>
                                                <div>
                                                    <div class="fw-semibold mb-1">Carga de respaldos de reembolso</div>
                                                    <div>{{ $cometido->esAdministracionCentral() ? 'El funcionario AC debe cargar' : 'El establecimiento debe cargar' }} uno o más documentos fiscales emitidos correctamente, indicando fecha, monto y detalle del gasto.</div>
                                                </div>
                                            </div>
                                            <a href="{{ route('tramites.cometidos-funcionarios.rendicion.panel', $cometido) }}" class="btn cometido-btn is-primary is-full">
                                                <i class="bi bi-receipt-cutoff"></i> Ir a la rendición de reembolso
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                @php
                                    $requiereRexCgrReembolsoAcAccion = $cometido->esAdministracionCentral() && (bool) $cometido->solicita_reembolso && ! (bool) $cometido->solicita_viatico;
                                    $esRexCgrGdpAccion = ($cometido->estado === 'en_gdp_rex_cgr' || $estadoReembolsoTimeline === 'en_gdp_rex_cgr') && $requiereRexCgrReembolsoAcAccion;
                                @endphp
                                @if ($puedeVerBandejaGdpVista && (in_array($cometido->estado, ['en_gdp_resolucion', 'autorizado_sin_gasto'], true) || $esRexCgrGdpAccion || ($esCometidoSinGastoVista && $cometido->estado === 'resolucion_cometido_emitida') || $estadoViaticoTimeline === 'en_gdp_resolucion'))
                                    <div class="card shadow-sm mb-4 stage-panel-card">
                                        <div class="stage-panel-header">
                                            <div class="stage-panel-title-wrap">
                                                <span class="stage-panel-icon is-gdp"><i class="bi bi-file-earmark-text"></i></span>
                                                <div>
                                                    <div class="stage-panel-kicker">Etapa de gestión de personas</div>
                                                    <h2 class="h5 mb-0">Bandeja GDP</h2>
                                                    <div class="small text-muted mt-1">{{ $esRexCgrGdpAccion ? 'Emisión de Resolución Exenta para CGR asociada al reembolso.' : ($esCometidoSinGastoVista ? 'Registro administrativo del cometido sin gasto.' : 'Preparación y emisión de la resolución de cometido.') }}</div>
                                                </div>
                                            </div>
                                            <span class="stage-status-badge {{ $cometido->estado === 'autorizado_sin_gasto' || $cometido->cdp_aprobado === false ? 'is-warning' : 'is-current' }}">
                                                <i class="bi {{ $cometido->estado === 'autorizado_sin_gasto' || $cometido->cdp_aprobado === false ? 'bi-exclamation-triangle' : 'bi-hourglass-split' }}"></i>
                                                {{ $cometido->estado === 'resolucion_cometido_emitida' && $esCometidoSinGastoVista ? 'Disponible para cierre' : ($esRexCgrGdpAccion ? 'Pendiente REX CGR' : ($esCometidoSinGastoVista ? 'Pendiente registro' : ($cometido->estado === 'autorizado_sin_gasto' || $cometido->cdp_aprobado === false ? 'Sin gasto autorizado' : 'Pendiente resolución'))) }}
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            @if ($cometido->estado === 'autorizado_sin_gasto' || $cometido->cdp_aprobado === false)
                                                <div class="stage-info-banner is-warning">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                    <div>
                                                        <div class="fw-semibold mb-1">Cometido autorizado sin gasto</div>
                                                        <div>No corresponde viático, devolución ni reembolso por falta de disponibilidad presupuestaria. GDP puede continuar con la resolución sin gasto.</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="stage-info-banner is-gdp">
                                                    <i class="bi bi-info-circle"></i>
                                                    <div>
                                                        <div class="fw-semibold mb-1">{{ $esCometidoSinGastoVista ? 'Cometido disponible para registro' : 'Solicitud disponible para resolución' }}</div>
                                                        <div>{{ $esRexCgrGdpAccion ? 'GDP debe emitir o registrar la Resolución Exenta para CGR antes de continuar al paso posterior del reembolso.' : ($esCometidoSinGastoVista ? 'GDP debe registrar el cometido sin exigir número, fecha ni archivo de resolución, ya que no contempla viático ni reembolso.' : 'GDP debe emitir o registrar la Resolución de Cometido para continuar hacia las etapas financieras cuando corresponda.') }}</div>
                                                    </div>
                                                </div>
                                            @endif

                                            <div class="stage-summary-stack mb-3">
                                                <div class="stage-mini-row">
                                                    <span>Estado CDP</span>
                                                    <strong>
                                                        @if ($cometido->cdp_aprobado === true)
                                                            Aprobado con disponibilidad
                                                        @elseif ($cometido->cdp_aprobado === false)
                                                            Rechazado / sin disponibilidad
                                                        @else
                                                            No aplica o pendiente
                                                        @endif
                                                    </strong>
                                                </div>
                                                @if ($cometido->cdp_referencia)
                                                    <div class="stage-mini-row">
                                                        <span>Referencia CDP</span>
                                                        <strong>{{ $cometido->cdp_referencia }}</strong>
                                                    </div>
                                                @endif
                                                @if ($cometido->cdp_monto_total)
                                                    <div class="stage-mini-row">
                                                        <span>Total autorizado</span>
                                                        <strong>${{ number_format((int) $cometido->cdp_monto_total, 0, ',', '.') }}</strong>
                                                    </div>
                                                @endif
                                            </div>

                                            @php
                                                $gdpRegistroCompletado = $esRexCgrGdpAccion
                                                    ? false
                                                    : ($esCometidoSinGastoVista
                                                        ? ($cometido->estado === 'resolucion_cometido_emitida' || ! empty($cometido->gdp_revisado_at))
                                                        : ($cometido->numero_resolucion_cometido || $cometido->archivo_resolucion_cometido_path));
                                            @endphp

                                            @if (! $gdpRegistroCompletado)
                                                <form method="POST" action="{{ route('tramites.cometidos-funcionarios.gdp.resolucion', $cometido) }}" enctype="multipart/form-data" class="stage-action-card is-approve">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if ($esCometidoSinGastoVista)
                                                        <div class="stage-action-title"><i class="bi bi-clipboard-check"></i> Registrar cometido</div>
                                                        <div class="stage-action-help">Registra la gestión GDP del cometido sin viático ni reembolso. No se requiere número, fecha ni archivo de resolución.</div>
                                                        <label class="form-label small fw-semibold">Observación opcional</label>
                                                        <textarea name="observacion" class="form-control mb-3" rows="3" placeholder="Comentario interno o detalle del registro"></textarea>
                                                        <button class="btn cometido-btn is-primary is-full" type="submit"><i class="bi bi-clipboard-check"></i> Registrar cometido</button>
                                                    @else
                                                        <div class="stage-action-title"><i class="bi bi-file-earmark-check"></i> {{ $esRexCgrGdpAccion ? 'Registrar REX cometido CGR' : 'Registrar REX de cometido' }}</div>
                                                        <div class="stage-action-help">{{ $esRexCgrGdpAccion ? 'Sube o registra la Resolución Exenta para CGR. Una vez emitida, el trámite continuará al paso posterior del reembolso.' : 'Sube o registra la resolución emitida por GDP para continuar el flujo de viático y/o reembolso.' }}</div>
                                                        <label class="form-label required small fw-semibold">Número de resolución / REX</label>
                                                        <input type="text" name="numero_resolucion_cometido" class="form-control mb-2" required placeholder="Ej.: REX 1256/2026">
                                                        <label class="form-label required small fw-semibold">Fecha de resolución</label>
                                                        <input type="date" name="fecha_resolucion_cometido" class="form-control mb-2" required>
                                                        <label class="form-label small fw-semibold">Archivo resolución</label>
                                                        <input type="file" name="archivo_resolucion_cometido" class="form-control mb-2" accept=".pdf,.doc,.docx">
                                                        <label class="form-label small fw-semibold">Observación opcional</label>
                                                        <textarea name="observacion" class="form-control mb-3" rows="3" placeholder="Comentario interno o detalle de derivación"></textarea>
                                                        <button class="btn cometido-btn is-primary is-full" type="submit"><i class="bi bi-file-earmark-check"></i> Registrar REX y continuar</button>
                                                    @endif
                                                </form>
                                            @else
                                                <div class="stage-next-box">
                                                    <div class="fw-semibold mb-1"><i class="bi bi-check-circle me-1"></i> {{ $esCometidoSinGastoVista ? 'Cometido registrado por GDP' : 'Resolución GDP registrada' }}</div>
                                                    <div>{{ $esCometidoSinGastoVista ? 'El cometido sin gasto quedó registrado y disponible para cierre.' : 'La resolución de cometido ya fue registrada. El flujo continúa según viático y/o reembolso solicitado.' }}</div>
                                                </div>

                                                @if ($esCometidoSinGastoVista && $cometido->estado === 'resolucion_cometido_emitida')
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.cerrar', $cometido) }}" class="stage-action-card is-approve mt-3">
                                                        @csrf
                                                        @method('PATCH')
                                                        <div class="stage-action-title"><i class="bi bi-check2-circle"></i> Cerrar trámite</div>
                                                        <div class="stage-action-help">Cierra el cometido funcionario sin viático ni reembolso una vez realizado el registro GDP.</div>
                                                        <label class="form-label small fw-semibold">Observación de cierre opcional</label>
                                                        <textarea name="observacion" class="form-control mb-3" rows="3" placeholder="Comentario interno de cierre"></textarea>
                                                        <button class="btn cometido-btn is-success is-full" type="submit"><i class="bi bi-check2-circle"></i> Cerrar trámite</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @endif


                                @php
                                    $informeActual = $cometido->informeCometidoActual ?? null;
                                    $documentoInformeCometido = $cometido->documentosGenerados->where('tipo_documento', 'informe_cometido')->sortByDesc('id')->first();
                                    $requiereInformeViatico = (bool) $cometido->solicita_viatico && in_array($estadoViaticoTimeline, ['informe_pendiente_funcionario', 'informe_pendiente_jefatura', 'informe_observado'], true);
                                    $requiereInformeReembolso = (bool) $cometido->solicita_reembolso && in_array($estadoReembolsoTimeline, ['pendiente_rendicion_informe', 'rendicion_enviada_pendiente_informe', 'informe_observado'], true);
                                    $requiereInformeCometido = $requiereInformeViatico || $requiereInformeReembolso;
                                    $estadoInformeBadgeEstado = $requiereInformeViatico ? $estadoViaticoTimeline : ($requiereInformeReembolso ? $estadoReembolsoTimeline : $estadoViaticoTimeline);
                                    $puedeEnviarInformeCometido = (($requiereInformeViatico && in_array($estadoViaticoTimeline, ['informe_pendiente_funcionario', 'informe_observado'], true))
                                            || ($requiereInformeReembolso && in_array($estadoReembolsoTimeline, ['pendiente_rendicion_informe', 'informe_observado'], true)))
                                        && (in_array($activeRoleVista, ['admin'], true) || ((int) $cometido->user_id === (int) auth()->id() && in_array($activeRoleVista, ['funcionario_ac', 'funcionario_estab'], true)));
                                    $puedeRevisarInformeJefatura = $informeActual
                                        && in_array((string) $informeActual->estado_informe, ['enviado_pendiente_jefatura', 'pendiente_jefatura'], true)
                                        && in_array($activeRoleVista, ['admin', 'funcionario_ac', 'funcionario_estab'], true)
                                        && ($activeRoleVista === 'admin' || (int) $cometido->user_id !== (int) auth()->id());
                                    $informeAprobadoJefatura = $informeActual && in_array((string) $informeActual->estado_informe, ['aprobado_jefatura', 'informe_aprobado', 'aprobado'], true);
                                @endphp

                                @if ($requiereInformeCometido || $informeActual)
                                    <div class="card shadow-sm mb-4 stage-panel-card">
                                        <div class="stage-panel-header">
                                            <div class="stage-panel-title-wrap">
                                                <span class="stage-panel-icon is-info"><i class="bi bi-journal-text"></i></span>
                                                <div>
                                                    <div class="stage-panel-kicker">Etapa informe</div>
                                                    <h2 class="h5 mb-0">Informe de cometido</h2>
                                                    <div class="small text-muted mt-1">Antes de continuar al pago o revisión de reembolso, el funcionario debe informar las actividades realizadas y luego la jefatura debe revisar y firmar.</div>
                                                </div>
                                            </div>
                                            <span class="badge {{ in_array($estadoInformeBadgeEstado, ['informe_pendiente_jefatura', 'rendicion_enviada_pendiente_informe'], true) ? 'bg-warning text-dark' : 'bg-primary' }}">
                                                {{ $estadoLabelsVista[$estadoInformeBadgeEstado] ?? 'Informe de cometido' }}
                                            </span>
                                        </div>
                                        <div class="stage-panel-body">
                                            @if ($informeActual)
                                                <div class="stage-report-box mb-3">
                                                    <div class="stage-report-box-title"><i class="bi bi-check2-circle"></i> Informe enviado</div>
                                                    <div class="stage-report-box-meta">Fecha envío: {{ optional($informeActual->fecha_envio)->format('d-m-Y H:i') ?: '—' }} · Estado: {{ $informeActual->estado_informe ?: '—' }}</div>
                                                    @if ($informeActual->requiere_nuevo_cometido_diferencia)
                                                        <div class="alert alert-warning small mt-2 mb-0">La modificación de fechas u horarios genera una diferencia a favor del funcionario. Debe evaluarse un nuevo cometido por la diferencia de días.</div>
                                                    @endif
                                                    @if ($documentoInformeCometido)
                                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                                            <a target="_blank" href="{{ route('tramites.cometidos-funcionarios.documentos-generados.ver', [$cometido, $documentoInformeCometido]) }}" class="btn cometido-btn is-document is-document-primary">
                                                                <i class="bi bi-eye"></i> Ver PDF informe
                                                            </a>
                                                            <a href="{{ route('tramites.cometidos-funcionarios.documentos-generados.ver', [$cometido, $documentoInformeCometido, 'download' => 1]) }}" class="btn cometido-btn is-document">
                                                                <i class="bi bi-download"></i> Descargar informe
                                                            </a>
                                                            @if (in_array($activeRoleVista, ['admin', 'funcionario_ac', 'funcionario_estab'], true))
                                                                <form method="POST" action="{{ route('tramites.cometidos-funcionarios.informe.regenerar-pdf', $cometido) }}" class="d-inline" onsubmit="return confirm('¿Desea regenerar el PDF del informe de cometido con la plantilla vigente?');">
                                                                    @csrf
                                                                    <button type="submit" class="btn cometido-btn is-document">
                                                                        <i class="bi bi-arrow-clockwise"></i> Regenerar PDF
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($puedeEnviarInformeCometido)
                                                <div class="stage-action-card is-approve">
                                                    <div class="stage-action-topline">
                                                        <div class="stage-action-title"><i class="bi bi-journal-plus"></i> Completar informe de cometido</div>
                                                        <span class="stage-action-stage-chip"><i class="bi bi-journal-text"></i> Etapa informe</span>
                                                    </div>
                                                    <div class="stage-action-help">Registre organismos o relatores, actividades realizadas, resultados obtenidos, opiniones y propuestas. Si modifica fechas u horarios, deberá justificarlo.</div>
                                                    <a class="btn cometido-btn is-primary is-full" href="{{ route('tramites.cometidos-funcionarios.informe.create', $cometido) }}">
                                                        <i class="bi bi-journal-text"></i> Completar informe
                                                    </a>
                                                </div>
                                            @endif
                                            @if ($informeActual && $informeActual->observacion_jefatura)
                                                <div class="alert alert-light border small mb-3">
                                                    <strong>Observación jefatura:</strong> {{ $informeActual->observacion_jefatura }}
                                                </div>
                                            @endif
                                            @if ($informeAprobadoJefatura)
                                                <div class="stage-report-box mb-3">
                                                    <div class="stage-report-box-title"><i class="bi bi-check-circle"></i> Informe aprobado por jefatura</div>
                                                    <div class="stage-report-box-meta">Fecha revisión: {{ optional($informeActual->fecha_revision_jefatura)->format('d-m-Y H:i') ?: '—' }}.</div>
                                                </div>
                                            @endif
                                            @if ($puedeRevisarInformeJefatura)
                                                <div class="stage-action-card is-approve mb-3">
                                                    <div class="stage-action-title"><i class="bi bi-person-check"></i> Revisión de jefatura</div>
                                                    <div class="stage-action-help">Revise el informe del funcionario. Al aprobar, el PDF se actualizará con firma electrónica de jefatura.</div>
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.informe.jefatura.aprobar', $cometido) }}" class="mb-3">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="form-label small fw-semibold">Observación opcional</label>
                                                        <textarea name="observacion_jefatura" class="form-control mb-2" rows="2" placeholder="Comentario de aprobación, si corresponde"></textarea>
                                                        <button type="submit" class="btn cometido-btn is-success is-full"><i class="bi bi-check2-circle"></i> Aprobar informe</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.informe.jefatura.observar', $cometido) }}" class="mb-3">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="form-label small fw-semibold">Observación obligatoria</label>
                                                        <textarea name="observacion_jefatura" class="form-control mb-2" rows="3" required placeholder="Indique qué debe corregir el funcionario"></textarea>
                                                        <button type="submit" class="btn cometido-btn is-warning is-full"><i class="bi bi-exclamation-triangle"></i> Observar informe</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.informe.jefatura.rechazar', $cometido) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label class="form-label small fw-semibold">Fundamento obligatorio</label>
                                                        <textarea name="observacion_jefatura" class="form-control mb-2" rows="3" required placeholder="Fundamente el rechazo del informe"></textarea>
                                                        <button type="submit" class="btn cometido-btn is-danger is-full"><i class="bi bi-x-circle"></i> Rechazar informe</button>
                                                    </form>
                                                </div>
                                            @elseif ($estadoViaticoTimeline === 'informe_pendiente_jefatura' || ($informeActual && in_array((string) $informeActual->estado_informe, ['enviado_pendiente_jefatura', 'pendiente_jefatura'], true)))
                                                <div class="stage-next-box">El informe fue enviado y queda pendiente de revisión y firma electrónica de jefatura.</div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if ((in_array($cometido->estado, ['resolucion_cometido_emitida', 'en_daf_viatico', 'viatico_pagado', 'en_daf_reembolso', 'en_rendicion_reembolso', 'cerrado', 'en_gestion_paralela'], true) || in_array($estadoViaticoTimeline, ['en_daf_viatico', 'en_daf_contable_viatico', 'en_pago_viatico', 'viatico_pagado'], true) || in_array($estadoReembolsoTimeline, ['pendiente_rendicion', 'en_rendicion_reembolso', 'rendicion_enviada', 'en_revision_daf_rendicion', 'rendicion_autorizada_daf', 'en_revision_cdp_rendicion', 'cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso', 'resolucion_reembolso_emitida', 'en_pago_reembolso', 'reembolso_pagado'], true)) && $solicitaGasto && ($cometido->cdp_aprobado === true || (bool) $cometido->solicita_reembolso))
                                    @php
                                        $reembolsoEnGestionFinanciera = in_array($estadoReembolsoTimeline, ['en_gdp_rex_cgr', 'rendicion_enviada', 'en_revision_daf_rendicion', 'rendicion_autorizada_daf', 'en_revision_cdp_rendicion', 'cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso', 'resolucion_reembolso_emitida', 'en_pago_reembolso'], true);
                                        $dafActivo = in_array($cometido->estado, ['en_daf_viatico', 'en_daf_reembolso', 'en_rendicion_reembolso'], true)
                                            || $estadoViaticoTimeline === 'en_daf_viatico'
                                            || $reembolsoEnGestionFinanciera;
                                        $dafCerrado = in_array($cometido->estado, ['viatico_pagado', 'cerrado'], true)
                                            || $estadoViaticoTimeline === 'viatico_pagado'
                                            || in_array($estadoReembolsoTimeline, ['reembolso_pagado', 'cerrado_sin_pago_reembolso'], true);
                                        $tituloPanelDaf = $reembolsoEnGestionFinanciera
                                            ? 'DAF / Rendición'
                                            : ($estadoViaticoTimeline === 'en_daf_viatico' || $estadoViaticoTimeline === 'viatico_pagado' || $cometido->estado === 'en_daf_viatico' ? 'DAF / Pago de viático' : 'DAF / Rendición');
                                        $subtituloPanelDaf = $reembolsoEnGestionFinanciera
                                            ? 'Seguimiento de viático, reembolso aprobado y total de pagos.'
                                            : 'Registro financiero del pago de viático. El reembolso continúa en su flujo de rendición independiente.';
                                    @endphp
                                    <div class="card shadow-sm mb-4 stage-panel-card">
                                        <div class="stage-panel-header">
                                            <div class="stage-panel-title-wrap">
                                                <span class="stage-panel-icon is-daf"><i class="bi bi-cash-coin"></i></span>
                                                <div>
                                                    <div class="stage-panel-kicker">Etapa financiera</div>
                                                    <h2 class="h5 mb-0">{{ $tituloPanelDaf }}</h2>
                                                    <div class="small text-muted mt-1">{{ $subtituloPanelDaf }}</div>
                                                </div>
                                            </div>
                                            @php
                                                $reembolsoEnGestionFinanciera = in_array($estadoReembolsoTimeline, ['en_gdp_rex_cgr', 'rendicion_enviada', 'en_revision_daf_rendicion', 'rendicion_autorizada_daf', 'en_revision_cdp_rendicion', 'cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso', 'resolucion_reembolso_emitida', 'en_pago_reembolso'], true);
                                                $dafActivo = in_array($cometido->estado, ['en_daf_viatico', 'en_daf_reembolso', 'en_rendicion_reembolso'], true)
                                                    || $estadoViaticoTimeline === 'en_daf_viatico'
                                                    || $reembolsoEnGestionFinanciera;
                                                $dafCerrado = in_array($cometido->estado, ['viatico_pagado', 'cerrado'], true)
                                                    || $estadoViaticoTimeline === 'viatico_pagado'
                                                    || in_array($estadoReembolsoTimeline, ['reembolso_pagado', 'cerrado_sin_pago_reembolso'], true);
                                                $tituloPanelDaf = $reembolsoEnGestionFinanciera
                                                    ? 'DAF / Rendición'
                                                    : ($estadoViaticoTimeline === 'en_daf_viatico' || $estadoViaticoTimeline === 'viatico_pagado' || $cometido->estado === 'en_daf_viatico' ? 'DAF / Pago de viático' : 'DAF / Rendición');
                                                $subtituloPanelDaf = $reembolsoEnGestionFinanciera
                                                    ? 'Seguimiento de viático, reembolso aprobado y total de pagos.'
                                                    : 'Registro financiero del pago de viático. El reembolso continúa en su flujo de rendición independiente.';
                                            @endphp
                                            <span class="stage-status-badge {{ $dafCerrado ? 'is-completed' : ($dafActivo ? 'is-current' : 'is-muted') }}">
                                                <i class="bi {{ $dafCerrado ? 'bi-check-circle' : ($dafActivo ? 'bi-hourglass-split' : 'bi-clock') }}"></i>
                                                {{ $dafCerrado ? 'Avance financiero registrado' : ($dafActivo ? 'En gestión financiera' : 'Pendiente DAF') }}
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            @php
                                                $rendicionResumenPago = \App\Models\CometidoFuncionarioRendicion::with('resolucion')
                                                    ->where('cometido_funcionario_id', $cometido->id)
                                                    ->latest('id')
                                                    ->first();
                                                $resolucionReembolsoResumen = $rendicionResumenPago?->resolucion;
                                                $montoViaticoResumen = (int) ($cometido->cdp_viatico_total ?? 0);
                                                $montoViaticoPagadoResumen = (int) ($cometido->monto_pagado_viatico ?? $montoViaticoResumen);
                                                $montoReembolsoAprobadoResumen = (int) (
                                                    $resolucionReembolsoResumen?->monto_pagado_reembolso
                                                    ?? $resolucionReembolsoResumen?->monto_resolucion
                                                    ?? $rendicionResumenPago?->monto_cdp_reembolso
                                                    ?? $rendicionResumenPago?->monto_autorizado_daf
                                                    ?? 0
                                                );
                                                $mostrarViaticoResumen = (bool) $cometido->solicita_viatico && $montoViaticoResumen > 0;
                                                $mostrarReembolsoResumen = (bool) $cometido->solicita_reembolso && $montoReembolsoAprobadoResumen > 0;
                                                $totalPagosResumen = ($mostrarViaticoResumen ? $montoViaticoPagadoResumen : 0) + ($mostrarReembolsoResumen ? $montoReembolsoAprobadoResumen : 0);
                                            @endphp

                                            <div class="stage-info-banner is-daf">
                                                <i class="bi bi-info-circle"></i>
                                                <div>
                                                    <div class="fw-semibold mb-1">Montos aprobados y pagos del cometido</div>
                                                    <div>Esta sección muestra los valores reales autorizados o pagados para viático y reembolso; no muestra topes referenciales.</div>
                                                </div>
                                            </div>

                                            @php
                                                $rolesGestionReembolso = ['admin', 'funcionario_daf', 'supervisor_plani', 'coordinador_plani', 'juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica', 'coordinador_gdp'];
                                                $puedeAccederGestionReembolso = in_array($activeRoleVista, $rolesGestionReembolso, true);
                                                $textoBotonGestionReembolso = 'Ir a gestión de reembolso';
                                                $tituloGestionReembolso = 'Gestión financiera del reembolso';
                                                $ayudaGestionReembolso = 'Disponible sólo para los equipos internos cuando la rendición ya fue enviada o está en una etapa financiera posterior.';

                                                if (in_array($activeRoleVista, ['funcionario_daf', 'admin'], true) && in_array($estadoReembolsoTimeline, ['en_gdp_rex_cgr', 'rendicion_enviada', 'en_revision_daf_rendicion'], true)) {
                                                    $textoBotonGestionReembolso = 'Ir a revisión de rendición';
                                                    $tituloGestionReembolso = 'Revisión DAF de rendición';
                                                    $ayudaGestionReembolso = 'La rendición ya fue enviada por el establecimiento. DAF debe revisar documentos fiscales, monto rendido y autorizar, observar o rechazar según corresponda.';
                                                } elseif (in_array($activeRoleVista, ['supervisor_plani', 'coordinador_plani'], true) && in_array($estadoReembolsoTimeline, ['rendicion_autorizada_daf', 'en_revision_cdp_rendicion'], true)) {
                                                    $textoBotonGestionReembolso = 'Ir a CDP de rendición';
                                                    $tituloGestionReembolso = 'CDP de rendición';
                                                    $ayudaGestionReembolso = 'La rendición fue autorizada por DAF. Planificación debe revisar y emitir el CDP de reembolso.';
                                                } elseif (in_array($activeRoleVista, ['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica'], true) && in_array($estadoReembolsoTimeline, ['cdp_reembolso_aprobado', 'en_juridica_resolucion_reembolso'], true)) {
                                                    $textoBotonGestionReembolso = 'Ir a resolución de reembolso';
                                                    $tituloGestionReembolso = 'Resolución jurídica de reembolso';
                                                    $ayudaGestionReembolso = 'El CDP de rendición fue aprobado. Jurídica debe emitir la resolución de pago del reembolso.';
                                                } elseif (in_array($activeRoleVista, ['funcionario_daf', 'admin', 'coordinador_gdp'], true) && in_array($estadoReembolsoTimeline, ['resolucion_reembolso_emitida', 'en_pago_reembolso'], true)) {
                                                    $textoBotonGestionReembolso = 'Ir a pago de reembolso';
                                                    $tituloGestionReembolso = 'Pago de reembolso';
                                                    $ayudaGestionReembolso = 'La resolución de pago fue emitida. Corresponde registrar el pago efectivo del reembolso.';
                                                }
                                            @endphp

                                            @if ((bool) $cometido->solicita_reembolso && $reembolsoEnGestionFinanciera && $puedeAccederGestionReembolso)
                                                <div class="stage-action-card is-approve">
                                                    <div class="stage-action-title"><i class="bi bi-receipt"></i> {{ $tituloGestionReembolso }}</div>
                                                    <div class="stage-action-help">{{ $ayudaGestionReembolso }}</div>
                                                    <a href="{{ route('tramites.cometidos-funcionarios.rendicion.panel', $cometido) }}" class="btn cometido-btn is-primary is-full">
                                                        <i class="bi bi-receipt-cutoff"></i> {{ $textoBotonGestionReembolso }}
                                                    </a>
                                                </div>
                                            @endif

                                            @if (in_array($activeRoleVista, ['admin', 'funcionario_daf'], true) && (in_array($cometido->estado, ['en_daf_viatico'], true) || $estadoViaticoTimeline === 'en_daf_viatico'))
                                                    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.daf.contabilidad-viatico', $cometido) }}" enctype="multipart/form-data" class="stage-action-card border border-info-subtle bg-info bg-opacity-10 mb-3">
                                                        @csrf
                                                        @method('PATCH')
                                                        <div class="stage-action-title"><i class="bi bi-journal-check"></i> Registrar compromiso y devengo del viático</div>
                                                        <div class="stage-action-help">Antes del pago, DAF debe registrar los folios contables de compromiso y devengo.</div>
                                                        <div class="row g-2">
                                                            <div class="col-md-6"><input type="text" name="folio_compromiso_viatico" class="form-control" placeholder="Folio compromiso" required></div>
                                                            <div class="col-md-6"><input type="date" name="fecha_compromiso_viatico" class="form-control" required></div>
                                                            <div class="col-md-6"><input type="text" name="folio_devengo_viatico" class="form-control" placeholder="Folio devengo" required></div>
                                                            <div class="col-md-6"><input type="date" name="fecha_devengo_viatico" class="form-control" required></div>
                                                            <div class="col-12"><input type="file" name="documento_contable_viatico" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></div>
                                                            <div class="col-12"><textarea name="observacion_contable_viatico" rows="2" class="form-control" placeholder="Observación contable opcional"></textarea></div>
                                                        </div>
                                                        <button class="btn btn-info mt-3"><i class="bi bi-save"></i> Guardar registro contable</button>
                                                    </form>
                                                @endif

                                                @if (in_array($activeRoleVista, ['admin', 'funcionario_daf'], true) && (in_array($cometido->estado, ['en_pago_viatico'], true) || $estadoViaticoTimeline === 'en_pago_viatico'))
                                                <form method="POST" action="{{ route('tramites.cometidos-funcionarios.daf.pago-viatico', $cometido) }}" enctype="multipart/form-data" class="stage-action-card is-approve">
                                                    @csrf
                                                    @method('PATCH')
                                                    <div class="stage-action-title"><i class="bi bi-cash-coin"></i> Registrar pago de viático</div>
                                                    <div class="stage-action-help">DAF/Finanzas registra el pago efectivo del componente viático. Esta acción sólo se habilita después del compromiso y devengo contable.</div>
                                                    <label class="form-label required small fw-semibold">Monto pagado</label>
                                                    <input type="number" name="monto_pagado_viatico" min="0" class="form-control mb-2" value="{{ (int) ($cometido->cdp_viatico_total ?? $montoViaticoResumen ?? 0) }}" required>
                                                    <label class="form-label required small fw-semibold">Fecha de pago</label>
                                                    <input type="date" name="fecha_pago_viatico" class="form-control mb-2" required>
                                                    <label class="form-label required small fw-semibold">Folio de Tesorería</label>
                                                    <input type="text" name="folio_tesoreria_viatico" maxlength="100" class="form-control mb-2" placeholder="Ingrese folio de Tesorería" required>
                                                    <label class="form-label small fw-semibold">Comprobante de pago</label>
                                                    <input type="file" name="archivo_pago_viatico" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                                    <label class="form-label small fw-semibold">Observación opcional</label>
                                                    <textarea name="observacion_pago_viatico" class="form-control mb-3" rows="3" placeholder="Detalle o referencia de pago"></textarea>
                                                    <button class="btn cometido-btn is-success is-full" type="submit"><i class="bi bi-cash-coin"></i> Registrar pago de viático</button>
                                                </form>
                                            @endif

                                            @if ($mostrarViaticoResumen || $mostrarReembolsoResumen)
                                                <div class="stage-finance-grid">
                                                    @if ($mostrarViaticoResumen)
                                                        <div class="stage-finance-item">
                                                            <div class="stage-finance-label">Viático {{ $cometido->fecha_pago_viatico ? 'pagado' : 'autorizado' }}</div>
                                                            <div class="stage-finance-amount">${{ number_format((int) ($cometido->monto_pagado_viatico ?? $montoViaticoResumen), 0, ',', '.') }}</div>
                                                            <div class="small text-muted mt-1">{{ $cometido->fecha_pago_viatico ? 'Pago registrado el ' . optional($cometido->fecha_pago_viatico)->format('d-m-Y') : 'Monto de viático definido por CDP según asignación diaria.' }}</div>
                                                            @if(!empty($cometido->folio_tesoreria_viatico))
                                                                <div class="small text-muted">Tesorería: {{ $cometido->folio_tesoreria_viatico }}</div>
                                                            @endif
                                                            @if(!empty($cometido->folio_devengo_viatico))
                                                                <div class="small text-muted">Devengo: {{ $cometido->folio_devengo_viatico }}</div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if ($mostrarReembolsoResumen)
                                                        <div class="stage-finance-item">
                                                            <div class="stage-finance-label">Reembolso aprobado</div>
                                                            <div class="stage-finance-amount">${{ number_format($montoReembolsoAprobadoResumen, 0, ',', '.') }}</div>
                                                            <div class="small text-muted mt-1">Monto real aprobado/pagado según rendición y respaldo documental.</div>
                                                        </div>
                                                    @endif
                                                    <div class="stage-finance-item bg-light">
                                                        <div class="stage-finance-label">Total pagos</div>
                                                        <div class="stage-finance-amount">${{ number_format($totalPagosResumen, 0, ',', '.') }}</div>
                                                        <div class="small text-muted mt-1">Suma de viático y reembolso aprobado cuando corresponda.</div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="stage-empty-box">
                                                    Aún no existen montos de viático o reembolso aprobados para mostrar como pagos del cometido.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                <div class="card shadow-sm mb-4 info-section-card">
                    <div class="info-section-header">
                        <div class="info-section-title-wrap">
                            <span class="info-section-icon is-summary"><i class="bi bi-person-vcard"></i></span>
                            <div>
                                <div class="info-section-kicker">Identificación de la solicitud</div>
                                <h2 class="h5 mb-0">Resumen</h2>
                                <div class="info-section-help">Datos principales del funcionario, establecimiento y situación contractual.</div>
                            </div>
                        </div>
                        <span class="stage-status-badge is-muted"><i class="bi bi-tag"></i> {{ $cometido->etiquetaEstado() }}</span>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Fecha solicitud</div>
                                <div class="info-value">{{ optional($cometido->fecha_solicitud)->format('d-m-Y') ?? '—' }}</div>
                            </div>
                            <div class="info-item">
                                @if ($esResumenAc)
                                    <div class="info-label">Subdirección dependencia</div>
                                    <div class="info-value">{{ $subdireccionResumenAc ?: '—' }}</div>
                                    <div class="small text-muted mt-1">Administración Central</div>
                                @else
                                    <div class="info-label">Establecimiento</div>
                                    <div class="info-value">{{ $cometido->establecimiento->nombre_establecimiento ?? '—' }}</div>
                                    <div class="small text-muted mt-1">RBD {{ $cometido->rbd ?? '—' }}</div>
                                @endif
                            </div>
                            <div class="info-item is-wide">
                                <div class="info-label">Funcionario</div>
                                <div class="info-value">{{ $cometido->funcionario_nombre }}</div>
                                <div class="small text-muted mt-1">{{ $cometido->funcionario_rut }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Calidad jurídica</div>
                                <div class="info-value">{{ $esResumenAc ? ($calidadJuridicaResumenAc ?: '—') : ($cometido->calidad_juridica ?: '—') }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">{{ $esResumenAc ? 'Escalafón' : 'Estamento' }}</div>
                                <div class="info-value">{{ $esResumenAc ? ($escalafonResumenAc ?: '—') : ($cometido->estamento ?: '—') }}</div>
                            </div>
                            <div class="info-item is-wide">
                                <div class="info-label">{{ $esResumenAc ? 'Unidad' : 'Cargo / función' }}</div>
                                <div class="info-value">{{ $esResumenAc ? ($unidadResumenAc ?: '—') : ($cometido->cargo_funcion ?: '—') }}</div>
                            </div>
                            @if ($esResumenAc)
                                <div class="info-item">
                                    <div class="info-label">Teléfono</div>
                                    <div class="info-value">{{ $telefonoResumenAc !== '' ? $telefonoResumenAc : '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Correo electrónico</div>
                                    <div class="info-value">{{ $emailResumenAc !== '' ? $emailResumenAc : '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Fecha de nacimiento</div>
                                    <div class="info-value">{{ $fechaNacimientoResumenAc !== '' ? $fechaNacimientoResumenAc : '—' }}</div>
                                    @if ($cometido->requiere_pasaje_aereo)
                                        <div class="small text-muted mt-1">Dato requerido para compra de pasaje aéreo.</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($cometido->solicita_viatico || $cometido->solicita_reembolso)
                    <div class="card shadow-sm mb-4 info-section-card">
                        <div class="info-section-header">
                            <div class="info-section-title-wrap">
                                <span class="info-section-icon is-summary"><i class="bi bi-bank"></i></span>
                                <div>
                                    <div class="info-section-kicker">Pago de viático y/o reembolso</div>
                                    <h2 class="h5 mb-0">Datos bancarios</h2>
                                    <div class="info-section-help">Información declarada por el establecimiento para gestionar pagos asociados al cometido.</div>
                                </div>
                            </div>
                            <span class="stage-status-badge is-muted"><i class="bi bi-cash-coin"></i> Pago</span>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Banco</div>
                                    <div class="info-value">{{ $cometido->banco_pago ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Tipo de cuenta</div>
                                    <div class="info-value">{{ $cometido->tipo_cuenta_pago ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Número de cuenta</div>
                                    <div class="info-value">{{ $cometido->numero_cuenta_pago ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card shadow-sm mb-4 info-section-card">
                    <div class="info-section-header">
                        <div class="info-section-title-wrap">
                            <span class="info-section-icon is-trip"><i class="bi bi-geo-alt"></i></span>
                            <div>
                                <div class="info-section-kicker">Destino y duración</div>
                                <h2 class="h5 mb-0">Detalle del viaje</h2>
                                <div class="info-section-help">Información de desplazamiento, destino, fechas y horario declarado.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Región destino</div>
                                <div class="info-value">{{ config('chile.regiones.' . $cometido->region_destino, $cometido->region_destino) }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Comuna destino</div>
                                <div class="info-value">{{ $cometido->comuna_destino_nombre ?: '—' }}</div>
                            </div>
                            <div class="info-item is-wide">
                                <div class="info-label">Institución</div>
                                <div class="info-value">{{ $cometido->institucion_destino }}</div>
                            </div>
                            <div class="info-item is-wide">
                                <div class="info-label">Destino específico</div>
                                <div class="info-value">{{ $cometido->destino }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Periodo</div>
                                <div class="info-value">{{ optional($cometido->fecha_desde)->format('d-m-Y') }} a {{ optional($cometido->fecha_hasta)->format('d-m-Y') }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Horario</div>
                                <div class="info-value">{{ substr((string) $cometido->hora_salida, 0, 5) }} a {{ substr((string) $cometido->hora_regreso, 0, 5) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 info-section-card">
                    <div class="info-section-header">
                        <div class="info-section-title-wrap">
                            <span class="info-section-icon is-activity"><i class="bi bi-clipboard2-check"></i></span>
                            <div>
                                <div class="info-section-kicker">Justificación y beneficios</div>
                                <h2 class="h5 mb-0">Motivo y actividad</h2>
                                <div class="info-section-help">Contexto del cometido, transporte, respaldos y beneficios solicitados.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-grid mb-3">
                            <div class="info-item is-wide">
                                <div class="info-label">Medios de transporte</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @forelse (($cometido->medios_transporte ?? []) as $medio)
                                        <span class="info-chip is-muted"><i class="bi bi-signpost"></i> {{ $medio }}</span>
                                    @empty
                                        <span class="info-value is-muted">—</span>
                                    @endforelse
                                    @if ($cometido->medio_transporte_otro)
                                        <span class="info-chip is-muted"><i class="bi bi-plus-circle"></i> Otro: {{ $cometido->medio_transporte_otro }}</span>
                                    @endif
                                </div>
                            </div>
                            @if (in_array('Vehículo institucional', $cometido->medios_transporte ?? [], true))
                                <div class="info-item is-wide">
                                    <div class="info-label">Servicios Generales</div>
                                    @if($cometido->ssgg_notificado_vehiculo_at)
                                        <div class="info-value text-success">
                                            <i class="bi bi-check-circle"></i>
                                            Notificado el {{ optional($cometido->ssgg_notificado_vehiculo_at)->format('d-m-Y H:i') }} a {{ $cometido->ssgg_notificado_vehiculo_email }}
                                        </div>
                                    @else
                                        <div class="info-value text-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Pendiente de notificación automática al autorizar el cometido.
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if ($cometido->requiere_pasaje_aereo)
                                <div class="info-item">
                                    <div class="info-label">Tipo de pasaje aéreo</div>
                                    <div class="info-value">{{ ['solo_ida' => 'Solo ida', 'solo_regreso' => 'Solo regreso', 'ida_y_regreso' => 'Ida y regreso'][$cometido->tipo_pasaje_aereo] ?? 'No informado' }}</div>
                                </div>
                            @endif
                            <div class="info-item is-wide">
                                <div class="info-label">Motivo</div>
                                <div class="info-value">{{ $cometido->motivo }}{{ $cometido->motivo_otro ? ': ' . $cometido->motivo_otro : '' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Citación / invitación</div>
                                <div class="info-value">
                                    <span class="info-chip {{ $cometido->existe_citacion_invitacion ? 'is-success' : 'is-muted' }}">
                                        <i class="bi {{ $cometido->existe_citacion_invitacion ? 'bi-check-circle' : 'bi-dash-circle' }}"></i>
                                        {{ $cometido->existe_citacion_invitacion ? 'Sí' : 'No' }}
                                    </span>
                                </div>
                                @if ($cometido->archivo_citacion_invitacion_nombre)
                                    <div class="small text-muted mt-2">Archivo cargado: {{ $cometido->archivo_citacion_invitacion_nombre }}</div>
                                    @if ($citacionPreview)
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <a href="{{ route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $citacionPreview]) }}" target="_blank" class="btn cometido-btn is-document">
                                                <i class="bi bi-eye"></i> Ver documento
                                            </a>
                                            <a href="{{ route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $citacionPreview, 'download' => 1]) }}" class="btn cometido-btn is-document">
                                                <i class="bi bi-download"></i> Descargar
                                            </a>
                                        </div>
                                    @endif
                                @endif
                            </div>
                            <div class="info-item">
                                <div class="info-label">Beneficios solicitados</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="info-chip {{ $cometido->solicita_viatico ? 'is-success' : 'is-muted' }}"><i class="bi bi-briefcase"></i> Viático: {{ $cometido->solicita_viatico ? 'Sí' : 'No' }}</span>
                                    @if ($cometido->solicita_viatico)
                                        <span class="info-chip {{ $cometido->contempla_alojamiento ? 'is-warning' : 'is-muted' }}"><i class="bi bi-house-check"></i> Alojamiento incluido: {{ $cometido->contempla_alojamiento ? 'Sí' : 'No' }}</span>
                                    @endif
                                    <span class="info-chip {{ $cometido->solicita_reembolso ? 'is-success' : 'is-muted' }}"><i class="bi bi-receipt"></i> Reembolso: {{ $cometido->solicita_reembolso ? 'Sí' : 'No' }}</span>
                                </div>
                            </div>
                            <div class="info-item is-wide">
                                <div class="info-label">Declaración jurada</div>
                                @if ($cometido->declaracion_aceptada)
                                    <span class="info-chip is-success"><i class="bi bi-check-circle"></i> Aceptada</span>
                                    @if ($cometido->declaracion_aceptada_at)
                                        <div class="small text-muted mt-2">Aceptada el {{ $cometido->declaracion_aceptada_at->format('d-m-Y H:i') }}</div>
                                    @endif
                                    @if ($cometido->declaracion_texto)
                                        <div class="small text-muted mt-2">{{ $cometido->declaracion_texto }}</div>
                                    @endif
                                @else
                                    <span class="info-chip is-muted"><i class="bi bi-dash-circle"></i> No aceptada</span>
                                @endif
                            </div>

                            @if (!is_null($cometido->cdp_aprobado) || $cometido->cdp_observacion || $cometido->cdp_referencia)
                                <div class="info-item is-wide">
                                    <div class="cdp-approved-box">
                                        <div class="cdp-approved-header">
                                            <div>
                                                <div class="cdp-approved-title"><i class="bi bi-file-earmark-check"></i> Resultado CDP</div>
                                                @if ($cometido->cdp_referencia)
                                                    <div class="small text-muted mt-1">Referencia: {{ $cometido->cdp_referencia }}</div>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($cometido->cdp_aprobado === true)
                                                    <span class="stage-status-badge is-completed"><i class="bi bi-check-circle"></i> Con disponibilidad presupuestaria</span>
                                                @elseif ($cometido->cdp_aprobado === false)
                                                    <span class="stage-status-badge is-danger"><i class="bi bi-x-circle"></i> Sin disponibilidad presupuestaria</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($cometido->cdp_estamento || $cometido->cdp_cargo_funcion || $cometido->cdp_monto_total)
                                            @if ($cometido->cdp_estamento || $cometido->cdp_cargo_funcion)
                                                <div class="small text-muted mb-3">Catálogo aplicado: {{ $cometido->cdp_estamento }} / {{ $cometido->cdp_cargo_funcion }}</div>
                                            @endif
                                            <div class="cdp-approved-grid">
                                                @if ($cometido->solicita_viatico)
                                                    <div class="cdp-approved-item">
                                                        <div class="cdp-approved-label">Viático fijo autorizado</div>
                                                        <div class="cdp-approved-amount">${{ number_format((int) ($cometido->cdp_viatico_total ?? 0), 0, ',', '.') }}</div>
                                                    </div>
                                                @endif
                                                @if ($cometido->solicita_reembolso)
                                                    <div class="cdp-approved-item">
                                                        <div class="cdp-approved-label">Tope máximo reembolsable</div>
                                                        <div class="cdp-approved-amount">${{ number_format((int) ($cometido->cdp_reembolso_total_maximo ?? 0), 0, ',', '.') }}</div>
                                                    </div>
                                                @endif
                                                <div class="cdp-approved-item">
                                                    <div class="cdp-approved-label">Total CDP</div>
                                                    <div class="cdp-approved-amount">${{ number_format((int) ($cometido->cdp_monto_total ?? 0), 0, ',', '.') }}</div>
                                                </div>
                                            </div>

                                            @foreach (['viatico' => 'Detalle diario de viático', 'reembolso' => 'Detalle diario de reembolso máximo'] as $tipoCdp => $tituloCdp)
                                                @php $montosTipo = $cdpMontosPorTipo->get($tipoCdp, collect()); @endphp
                                                @if ($montosTipo->isNotEmpty())
                                                    <div class="mt-3">
                                                        <div class="fw-semibold small mb-2">{{ $tituloCdp }}</div>
                                                        <div class="table-responsive cdp-detail-table">
                                                            <table class="table table-sm align-middle small">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Día</th>
                                                                        <th>Fecha</th>
                                                                        <th>%</th>
                                                                        <th>Valor diario</th>
                                                                        <th>Monto día</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($montosTipo as $montoCdp)
                                                                        <tr>
                                                                            <td>{{ $montoCdp->dia_numero }}</td>
                                                                            <td>{{ optional($montoCdp->fecha)->format('d-m-Y') }}</td>
                                                                            <td>{{ (int) $montoCdp->porcentaje === 0 ? 'No autorizado' : $montoCdp->porcentaje . '%' }}</td>
                                                                            <td>${{ number_format((int) $montoCdp->valor_diario, 0, ',', '.') }}</td>
                                                                            <td class="fw-semibold">${{ number_format((int) $montoCdp->monto, 0, ',', '.') }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        @endif

                                        @if ($cometido->cdp_observacion)
                                            <div class="activity-description-box mt-3">
                                                <div class="activity-description-title"><i class="bi bi-chat-left-text"></i> Observación CDP</div>
                                                <div>{!! nl2br(e($cometido->cdp_observacion)) !!}</div>
                                            </div>
                                        @endif

                                        @if ($pasoDirectorSinDisponibilidadTimeline || $cometido->cdp_aprobado === false || ! empty($cometido->estado_autorizacion_director) || (bool) ($cometido->viatico_reconvertido_a_reembolso ?? false))
                                            @php
                                                $estadoAutorizacionDirectorCdp = (string) ($cometido->estado_autorizacion_director ?? '');
                                                $directorAproboReconversionCdp = $estadoAutorizacionDirectorCdp === 'aprobada' || (bool) ($cometido->viatico_reconvertido_a_reembolso ?? false);
                                                $directorRechazoReconversionCdp = $estadoAutorizacionDirectorCdp === 'rechazada' || $estadoActualCometido === 'rechazado_director_sin_disponibilidad';
                                                $directorPendienteReconversionCdp = ! $directorAproboReconversionCdp && ! $directorRechazoReconversionCdp;
                                                $directorBadgeClassCdp = $directorAproboReconversionCdp ? 'is-completed' : ($directorRechazoReconversionCdp ? 'is-danger' : 'is-current');
                                                $directorBadgeIconCdp = $directorAproboReconversionCdp ? 'bi-check-circle' : ($directorRechazoReconversionCdp ? 'bi-x-circle' : 'bi-hourglass-split');
                                                $directorBadgeLabelCdp = $directorAproboReconversionCdp ? 'Reconversión aprobada' : ($directorRechazoReconversionCdp ? 'Continuidad rechazada' : 'Pendiente Director Ejecutivo');
                                                $montoViaticoDirectorCdp = (int) ($cometido->monto_viatico_solicitado_director ?? $cometido->cdp_viatico_total ?? $cometido->cdp_monto_total ?? 0);
                                                $montoDisponibleDirectorCdp = (int) ($cometido->monto_disponible_director ?? 0);
                                                $diferenciaDirectorCdp = (int) ($cometido->diferencia_presupuestaria_director ?? max(0, $montoViaticoDirectorCdp - $montoDisponibleDirectorCdp));
                                            @endphp
                                            <div class="activity-description-box mt-3">
                                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                                    <div class="activity-description-title mb-0"><i class="bi bi-person-check"></i> Autorización Director Ejecutivo</div>
                                                    <span class="stage-status-badge {{ $directorBadgeClassCdp }}"><i class="bi {{ $directorBadgeIconCdp }}"></i> {{ $directorBadgeLabelCdp }}</span>
                                                </div>
                                                <div class="small text-muted mb-3">
                                                    Planificación informó falta de disponibilidad presupuestaria para emitir CDP de viático, por lo que el cometido se derivó a resolución del Director Ejecutivo.
                                                </div>

                                                @if ($directorAproboReconversionCdp)
                                                    <div class="info-chip is-success mb-3"><i class="bi bi-arrow-repeat"></i> El cometido continúa reconvertido a reembolso</div>
                                                @elseif ($directorRechazoReconversionCdp)
                                                    <div class="info-chip is-muted mb-3"><i class="bi bi-slash-circle"></i> El cometido no continúa por falta de disponibilidad</div>
                                                @else
                                                    <div class="info-chip is-warning mb-3"><i class="bi bi-exclamation-triangle"></i> Pendiente de aprobar reconversión o rechazar continuidad</div>
                                                @endif

                                                <div class="cdp-approved-grid">
                                                    <div class="cdp-approved-item">
                                                        <div class="cdp-approved-label">Viático requerido</div>
                                                        <div class="cdp-approved-amount">${{ number_format($montoViaticoDirectorCdp, 0, ',', '.') }}</div>
                                                    </div>
                                                    <div class="cdp-approved-item">
                                                        <div class="cdp-approved-label">Saldo disponible</div>
                                                        <div class="cdp-approved-amount">${{ number_format($montoDisponibleDirectorCdp, 0, ',', '.') }}</div>
                                                    </div>
                                                    <div class="cdp-approved-item">
                                                        <div class="cdp-approved-label">Diferencia presupuestaria</div>
                                                        <div class="cdp-approved-amount">${{ number_format($diferenciaDirectorCdp, 0, ',', '.') }}</div>
                                                    </div>
                                                </div>

                                                @if (! empty($cometido->fundamento_planificacion_director))
                                                    <div class="activity-description-box mt-3">
                                                        <div class="activity-description-title"><i class="bi bi-journal-text"></i> Fundamento de Planificación</div>
                                                        <div>{!! nl2br(e($cometido->fundamento_planificacion_director)) !!}</div>
                                                    </div>
                                                @endif

                                                @if (! empty($cometido->observacion_director))
                                                    <div class="activity-description-box mt-3">
                                                        <div class="activity-description-title"><i class="bi bi-chat-square-text"></i> Observación del Director Ejecutivo</div>
                                                        <div>{!! nl2br(e($cometido->observacion_director)) !!}</div>
                                                    </div>
                                                @endif

                                                @if ($directorAproboReconversionCdp || $aplicaFlujoReembolsoTimeline)
                                                    <div class="mt-3">
                                                        <div class="fw-semibold small mb-2"><i class="bi bi-diagram-3"></i> Etapas posteriores del reembolso</div>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <span class="info-chip is-muted"><i class="bi bi-file-earmark-check"></i> GDP / REX CGR</span>
                                                            <span class="info-chip is-muted"><i class="bi bi-journal-text"></i> Informe</span>
                                                            <span class="info-chip is-muted"><i class="bi bi-receipt"></i> Rendición</span>
                                                            <span class="info-chip is-muted"><i class="bi bi-journal-check"></i> DAF</span>
                                                            <span class="info-chip is-muted"><i class="bi bi-file-earmark-check"></i> CDP rendición</span>
                                                            <span class="info-chip is-muted"><i class="bi bi-file-earmark-ruled"></i> Jurídica</span>
                                                            <span class="info-chip is-muted"><i class="bi bi-cash-coin"></i> Pago</span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($cdpDocumento)
                                            <div class="doc-card-item is-cdp mt-3">
                                                <div class="doc-card-main">
                                                    <span class="doc-card-icon"><i class="bi bi-file-earmark-pdf"></i></span>
                                                    <div>
                                                        <div class="doc-card-title">Documento CDP cargado</div>
                                                        <div class="doc-card-meta">{{ $cdpDocumento->nombre_original }} · {{ number_format(($cdpDocumento->size ?? 0) / 1024, 1, ',', '.') }} KB</div>
                                                    </div>
                                                </div>
                                                <div class="doc-card-actions">
                                                    <a href="{{ route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $cdpDocumento]) }}" target="_blank" class="btn cometido-btn is-document is-document-primary">
                                                        <i class="bi bi-eye"></i> Ver documento
                                                    </a>
                                                    <a href="{{ route('tramites.cometidos-funcionarios.documentos.ver', [$cometido, $cdpDocumento, 'download' => 1]) }}" class="btn cometido-btn is-document is-document-primary">
                                                        <i class="bi bi-download"></i> Descargar
                                                    </a>
                                                </div>
                                            </div>
                                        @elseif ($cometido->cdp_aprobado === true)
                                            <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small">
                                                CDP aprobado sin documento visible asociado. Revise si el archivo fue cargado correctamente.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="activity-description-box">
                            <div class="activity-description-title"><i class="bi bi-card-text"></i> Descripción de actividades</div>
                            <div>{!! nl2br(e($cometido->descripcion_actividades)) !!}</div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4 info-section-card">
                    <div class="info-section-header">
                        <div class="info-section-title-wrap">
                            <span class="info-section-icon is-docs"><i class="bi bi-folder2-open"></i></span>
                            <div>
                                <div class="info-section-kicker">Expediente documental</div>
                                <h2 class="h5 mb-0">Expediente completo del cometido</h2>
                                <div class="info-section-help">Solicitud, documentos generados, respaldos cargados, informe, rendición, documentos contables y comprobantes de pago disponibles.</div>
                            </div>
                        </div>
                        <span class="stage-status-badge is-muted"><i class="bi bi-paperclip"></i> {{ $documentosFlujo->count() }} archivo{{ $documentosFlujo->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="card-body">
                        @if ($documentosFlujo->isNotEmpty())
                            <div class="doc-list">
                                @foreach ($documentosFlujo as $documentoFlujo)
                                    <div class="doc-card-item {{ ($documentoFlujo['is_cdp'] ?? false) ? 'is-cdp' : '' }}">
                                        <div class="doc-card-main">
                                            <span class="doc-card-icon"><i class="bi {{ ($documentoFlujo['is_cdp'] ?? false) ? 'bi-file-earmark-check' : 'bi-file-earmark-text' }}"></i></span>
                                            <div>
                                                <div class="doc-card-title">
                                                    {{ $documentoFlujo['titulo'] }}
                                                    @if (($documentoFlujo['is_cdp'] ?? false))
                                                        <span class="badge text-bg-primary ms-1">CDP</span>
                                                    @endif
                                                    @if (($documentoFlujo['source'] ?? '') === 'flujo')
                                                        <span class="badge text-bg-success ms-1">Flujo</span>
                                                    @endif
                                                </div>
                                                <div class="doc-card-meta">
                                                    {{ $documentoFlujo['nombre'] }}
                                                    @if (! empty($documentoFlujo['size']))
                                                        · {{ number_format(($documentoFlujo['size'] ?? 0) / 1024, 1, ',', '.') }} KB
                                                    @endif
                                                </div>
                                                @if (! empty($documentoFlujo['meta']))
                                                    <div class="doc-card-meta mt-1">{{ $documentoFlujo['meta'] }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="doc-card-actions">
                                            <a href="{{ $documentoFlujo['view_url'] }}" target="_blank" class="btn cometido-btn is-document {{ ($documentoFlujo['is_cdp'] ?? false) ? 'is-document-primary' : '' }}">
                                                <i class="bi bi-eye"></i> Ver documento
                                            </a>
                                            <a href="{{ $documentoFlujo['download_url'] }}" class="btn cometido-btn is-document {{ ($documentoFlujo['is_cdp'] ?? false) ? 'is-document-primary' : '' }}">
                                                <i class="bi bi-download"></i> Descargar documento
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="doc-empty-state">
                                <div class="h5 mb-1"><i class="bi bi-folder-x"></i></div>
                                <div class="fw-semibold">No hay documentos adjuntos.</div>
                                <div class="small">Cuando se carguen respaldos, quedarán disponibles en esta sección.</div>
                            </div>
                        @endif
                    </div>
                </div>


                @if ($cometido->esAdministracionCentral())
                    @php
                        $estadoJefaturaAc = (string) ($cometido->estado_autorizacion_jefatura_ac ?? '');
                        $jefaturaBadgeClass = match ($estadoJefaturaAc) {
                            'aprobado', 'aprobado_jefatura_ac' => 'is-completed',
                            'observado', 'observado_jefatura_ac' => 'is-warning',
                            'rechazado', 'rechazado_jefatura_ac' => 'is-danger',
                            'en_revision_jefatura_ac' => 'is-current',
                            default => 'is-muted',
                        };
                        $jefaturaBadgeLabel = $estadoJefaturaAc !== '' ? \Illuminate\Support\Str::headline(str_replace('_', ' ', $estadoJefaturaAc)) : 'Sin registro';
                        $pasaje = $cometido->pasajeAereo->first();
                        $pasajeBadgeClass = match ((string) ($pasaje->estado_pasaje ?? '')) {
                            'boleto_disponible' => 'is-completed',
                            'pendiente_reserva', 'pendiente_cdp_pasaje', 'pendiente_compra' => 'is-current',
                            default => 'is-muted',
                        };
                        $pasajeBadgeLabel = $pasaje ? \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $pasaje->estado_pasaje)) : 'Sin flujo';
                        $puedeVerBoletoPasaje = $pasaje && $pasaje->compra_archivo_path
                            && (
                                in_array($activeRole, ['admin', 'funcionario_daf_compra'], true)
                                || ($activeRole === 'funcionario_ac' && (int) $cometido->user_id === (int) auth()->id())
                            );
                    @endphp

                    <div class="card shadow-sm mb-4 info-section-card">
                        <div class="info-section-header">
                            <div class="info-section-title-wrap">
                                <span class="info-section-icon is-summary"><i class="bi bi-building-check"></i></span>
                                <div>
                                    <div class="info-section-kicker">Administración Central</div>
                                    <h2 class="h5 mb-0">Autorización, documentos generados y pasajes</h2>
                                    <div class="info-section-help">Información propia del flujo AC asociada a esta solicitud de cometido.</div>
                                </div>
                            </div>
                            <span class="stage-status-badge {{ $jefaturaBadgeClass }}">{{ $jefaturaBadgeLabel }}</span>
                        </div>
                        <div class="card-body">
                            @if($cometido->observacion_jefatura_ac)
                                <div class="stage-info-banner is-warning mb-3">
                                    <i class="bi bi-exclamation-circle"></i>
                                    <div><strong>Observación jefatura:</strong><br>{!! nl2br(e($cometido->observacion_jefatura_ac)) !!}</div>
                                </div>
                            @endif

                            <div class="info-grid mb-3">
                                <div class="info-item">
                                    <div class="info-label">N° cometido</div>
                                    <div class="info-value">{{ $cometido->numero_cometido_interno ?: '—' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Estado jefatura</div>
                                    <div class="info-value">{{ $jefaturaBadgeLabel }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Pasaje aéreo</div>
                                    <div class="info-value">{{ $cometido->requiere_pasaje_aereo ? 'Sí' : 'No' }}</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Anticipo viático</div>
                                    <div class="info-value">{{ $cometido->solicita_anticipo_viatico ? ('$' . number_format((int) ($cometido->monto_anticipo_viatico ?? 0), 0, ',', '.')) : 'No' }}</div>
                                </div>
                                @if ($cometido->requiere_pasaje_aereo)
                                    <div class="info-item">
                                        <div class="info-label">Tipo pasaje</div>
                                        <div class="info-value">{{ ['solo_ida' => 'Solo ida', 'solo_regreso' => 'Solo regreso', 'ida_y_regreso' => 'Ida y regreso'][$cometido->tipo_pasaje_aereo] ?? 'No informado' }}</div>
                                    </div>
                                @endif
                                <div class="info-item is-wide">
                                    <div class="info-label">Subdirección dependencia</div>
                                    <div class="info-value">{{ $cometido->subdireccion_dependencia_ac ?: 'Sin dependencia' }}</div>
                                </div>
                            </div>

                            @if($cometido->documentosGenerados->isNotEmpty())
                                <div class="mb-3">
                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-2">
                                        <div class="activity-description-title mb-0"><i class="bi bi-file-earmark-text"></i> Documentos generados</div>
                                        @if($puedeRegenerarSolicitudCometidoPdf ?? false)
                                            <form method="POST" action="{{ route('tramites.cometidos-funcionarios.documentos-generados.regenerar-solicitud', $cometido) }}" onsubmit="return confirm('Se regenerará el PDF de Solicitud de Cometido con los datos y valores vigentes antes de la aprobación de jefatura. ¿Desea continuar?');">
                                                @csrf
                                                <button type="submit" class="btn cometido-btn is-document is-document-primary">
                                                    <i class="bi bi-arrow-clockwise"></i> Regenerar PDF solicitud
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                    @if($puedeRegenerarSolicitudCometidoPdf ?? false)
                                        <div class="stage-info-banner is-info mb-3">
                                            <i class="bi bi-info-circle"></i>
                                            <div>Disponible sólo antes de la aprobación de jefatura. Use esta opción si el documento fue emitido con valores de viático desactualizados o incompletos.</div>
                                        </div>
                                    @endif
                                    <div class="stage-doc-list">
                                        @foreach($cometido->documentosGenerados as $docGen)
                                            <div class="stage-doc-item">
                                                <div class="stage-doc-head">
                                                    <div>
                                                        <div class="stage-doc-title"><i class="bi bi-file-earmark-pdf"></i><span>{{ $docGen->numero_documento }}</span></div>
                                                        <div class="stage-doc-meta">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $docGen->tipo_documento)) }}</div>
                                                    </div>
                                                    <span class="stage-status-badge is-completed"><i class="bi bi-patch-check"></i> Emitido</span>
                                                </div>
                                                <div class="stage-doc-meta">Código de validación: <strong>{{ $docGen->codigo_validacion }}</strong></div>
                                                <div class="stage-doc-actions">
                                                    <a target="_blank" href="{{ route('tramites.cometidos-funcionarios.documentos-generados.ver', [$cometido, $docGen]) }}" class="btn cometido-btn is-document is-document-primary">
                                                        <i class="bi bi-eye"></i> Ver documento
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($pasaje)
                                <div>
                                    <div class="activity-description-title mb-2"><i class="bi bi-airplane"></i> Flujo paralelo de pasaje aéreo</div>
                                    <div class="stage-doc-item">
                                        <div class="stage-doc-head">
                                            <div>
                                                <div class="stage-doc-title"><i class="bi bi-airplane"></i><span>Estado del pasaje: {{ $pasajeBadgeLabel }}</span></div>
                                                <div class="stage-doc-meta">Seguimiento de reserva, CDP, compra y entrega del boleto.</div>
                                            </div>
                                            <span class="stage-status-badge {{ $pasajeBadgeClass }}">{{ $pasajeBadgeLabel }}</span>
                                        </div>
                                        <div class="info-grid mt-3">
                                            <div class="info-item"><div class="info-label">Reserva</div><div class="info-value">{{ $pasaje->reserva_nombre_original ?: 'Pendiente' }}</div></div>
                                            <div class="info-item"><div class="info-label">CDP</div><div class="info-value">{{ $pasaje->cdp_referencia ?: 'Pendiente' }}</div></div>
                                            <div class="info-item"><div class="info-label">Proveedor</div><div class="info-value">{{ $pasaje->proveedor ?: 'Pendiente' }}</div></div>
                                            <div class="info-item"><div class="info-label">Monto</div><div class="info-value">{{ $pasaje->monto ? '$'.number_format($pasaje->monto,0,',','.') : 'Pendiente' }}</div></div>
                                        </div>
                                        @if($puedeVerBoletoPasaje)
                                            <div class="stage-doc-actions">
                                                <a href="{{ route('tramites.cometidos-funcionarios.pasaje.boleto', $cometido) }}" target="_blank" class="btn cometido-btn is-document is-document-primary">
                                                    <i class="bi bi-ticket-perforated"></i> Ver boleto / respaldo
                                                </a>
                                                <a href="{{ route('tramites.cometidos-funcionarios.pasaje.boleto', [$cometido, 'download' => 1]) }}" class="btn cometido-btn is-document is-document-primary">
                                                    <i class="bi bi-download"></i> Descargar boleto
                                                </a>
                                            </div>
                                        @elseif($pasaje->estado_pasaje === 'boleto_disponible')
                                            <div class="stage-side-note mt-3">El boleto está disponible sólo para el funcionario solicitante, DAF Compra y administración.</div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($cometido->uatp_observacion)
                    <div class="alert alert-warning">
                        <div class="fw-semibold">Observación UATP</div>
                        <div>{!! nl2br(e($cometido->uatp_observacion)) !!}</div>
                    </div>
                @endif
            </div>



            <div class="col-lg-4">
                <div class="card shadow-sm cometido-history-panel stage-panel-card mb-4">
            <div class="stage-panel-header">
                <div class="stage-panel-title-wrap">
                    <span class="stage-panel-icon is-cierre"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <div class="stage-panel-kicker">Trazabilidad del proceso</div>
                        <h2 class="h5 mb-1">Historial del trámite</h2>
                        <div class="small text-muted">Línea temporal de acciones, estados y observaciones.</div>
                    </div>
                </div>
                <span class="stage-status-badge is-muted">{{ $historialVisual->count() }} evento{{ $historialVisual->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="card-body p-4">
                @if ($historialVisual->isNotEmpty())
                    <div class="cometido-history">
                        @foreach ($historialVisual as $historial)
                            @php
                                $item = $historial['item'];
                            @endphp
                            <div class="cometido-history-item {{ $historial['class'] }}">
                                <div class="cometido-history-dot"><i class="bi {{ $historial['icon'] }}"></i></div>
                                <div class="cometido-history-content">
                                    <div class="cometido-history-card">
                                        <div class="cometido-history-card-head">
                                            <div>
                                                <div class="cometido-history-kicker">Acción registrada</div>
                                                <div class="cometido-history-title">{{ $item->accion }}</div>
                                            </div>
                                            <span class="stage-status-badge {{ $historial['badge_class'] }}">{{ $historial['badge'] }}</span>
                                        </div>
                                        <div class="cometido-history-meta">
                                            <i class="bi bi-calendar3 me-1"></i>{{ optional($item->created_at)->format('d-m-Y H:i') }}
                                            <span class="mx-1">·</span>
                                            <i class="bi bi-person-circle me-1"></i>{{ $item->usuario->nombre_completo ?? $item->usuario->email ?? 'Sistema' }}
                                        </div>
                                        @if ($historial['estado_anterior'] || $historial['estado_nuevo'])
                                            <div class="cometido-history-state">
                                                <span class="cometido-history-state-label">Estado</span>
                                                @if ($historial['estado_anterior'])
                                                    <span class="cometido-history-state-badge">{{ $historial['estado_anterior'] }}</span>
                                                @endif
                                                @if ($historial['estado_anterior'] && $historial['estado_nuevo'])
                                                    <span class="cometido-history-arrow">→</span>
                                                @endif
                                                @if ($historial['estado_nuevo'])
                                                    <span class="cometido-history-state-badge">{{ $historial['estado_nuevo'] }}</span>
                                                @endif
                                            </div>
                                        @endif
                                        @if ($item->observacion)
                                            <div class="cometido-history-note">
                                                <span class="cometido-history-note-title">Observación</span>
                                                {!! nl2br(e($item->observacion)) !!}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">Sin historial.</p>
                @endif
            </div>
        </div>
    </div>


        </div>
    </div>

    @if ($puedeRevisarCdpVista && ($cometido->estado === 'en_revision_cdp' || $estadoViaticoTimeline === 'en_revision_cdp'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const catalogo = @json($cdpValoresJson);
                const selectCatalogo = document.getElementById('cdpCatalogoValor');
                const totalGeneral = document.getElementById('cdpTotalGeneral');
                const formatter = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });

                function valorSeleccionado() {
                    if (!selectCatalogo) return null;
                    const id = Number(selectCatalogo.value || 0);
                    return catalogo.find(item => Number(item.id) === id) || null;
                }

                function beneficioHabilitado(tipo) {
                    const toggle = document.querySelector('.cdp-beneficio-toggle[data-tipo="' + tipo + '"]');
                    return !toggle || toggle.checked;
                }

                function pintarPorcentaje(selectPorcentaje) {
                    if (!selectPorcentaje) return;
                    selectPorcentaje.classList.remove('text-success', 'text-info', 'text-warning', 'text-muted');
                    if (selectPorcentaje.value === '100') selectPorcentaje.classList.add('text-success');
                    if (selectPorcentaje.value === '60') selectPorcentaje.classList.add('text-info');
                    if (selectPorcentaje.value === '40') selectPorcentaje.classList.add('text-warning');
                    if (selectPorcentaje.value === '0') selectPorcentaje.classList.add('text-muted');
                }

                function actualizarResumenTipo(tipo, monto) {
                    const resumen = document.getElementById(tipo === 'viatico' ? 'cdpTotalViaticoResumen' : 'cdpTotalReembolsoResumen');
                    if (resumen) resumen.textContent = formatter.format(monto);
                }

                function recalcularCdp() {
                    const valor = valorSeleccionado();
                    let total = 0;
                    const totalesPorTipo = { viatico: 0, reembolso: 0 };

                    document.querySelectorAll('.cdp-montos-table').forEach(function (table) {
                        const tipo = table.dataset.tipo || '';
                        const habilitado = beneficioHabilitado(tipo);
                        let totalTipo = 0;

                        table.querySelectorAll('tbody tr').forEach(function (row) {
                            const selectPorcentaje = row.querySelector('.cdp-porcentaje');
                            if (selectPorcentaje) {
                                selectPorcentaje.disabled = !habilitado || selectPorcentaje.dataset.forzadoAlojamiento === '1';
                                pintarPorcentaje(selectPorcentaje);
                            }
                            const porcentaje = selectPorcentaje?.value || '100';
                            const esAutomatico = table.dataset.auto === '1';
                            const montoAutomatico = Number(row.dataset.autoMonto || 0);
                            const monto = habilitado
                                ? (esAutomatico ? montoAutomatico : ((valor && porcentaje !== '0') ? Number(porcentaje === '40' ? valor.valor_40 : (porcentaje === '60' ? valor.valor_60 : valor.valor_100)) : 0))
                                : 0;
                            totalTipo += monto;
                            const cell = row.querySelector('.cdp-valor-dia');
                            if (cell) cell.textContent = formatter.format(monto);
                        });

                        const totalCells = document.querySelectorAll('.cdp-total-tipo[data-summary-target="' + tipo + '"]');
                        totalCells.forEach(function (totalCell) { totalCell.textContent = formatter.format(totalTipo); });
                        totalesPorTipo[tipo] = totalTipo;
                        total += totalTipo;
                    });

                    document.querySelectorAll('.cdp-beneficio-desactivado').forEach(function (alerta) {
                        const tipo = alerta.dataset.tipo || '';
                        alerta.classList.toggle('d-none', beneficioHabilitado(tipo));
                    });

                    actualizarResumenTipo('viatico', totalesPorTipo.viatico || 0);
                    actualizarResumenTipo('reembolso', totalesPorTipo.reembolso || 0);
                    if (totalGeneral) totalGeneral.textContent = formatter.format(total);
                }

                if (selectCatalogo) selectCatalogo.addEventListener('change', recalcularCdp);
                document.querySelectorAll('.cdp-porcentaje').forEach(function (select) {
                    select.addEventListener('change', recalcularCdp);
                });
                document.querySelectorAll('.cdp-beneficio-toggle').forEach(function (toggle) {
                    toggle.addEventListener('change', recalcularCdp);
                });
                recalcularCdp();
            });
        </script>
    @endif
@endsection
