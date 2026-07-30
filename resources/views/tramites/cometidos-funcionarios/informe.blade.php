@extends('layouts.app')

@section('content')
@php
    $esAc = method_exists($cometido, 'esAdministracionCentral') && $cometido->esAdministracionCentral();
    $funcionarioAc = $cometido->funcionarioAcAutorizado;
    $dependencia = $esAc
        ? ($cometido->subdireccion_dependencia_ac ?: ($funcionarioAc->subdireccion_dependencia ?? 'Administración Central'))
        : ($cometido->establecimiento->nombre ?? 'Establecimiento');
    $unidad = $esAc
        ? ($cometido->unidad_departamento_ac ?: ($funcionarioAc->unidad_departamento ?? '—'))
        : ($cometido->cargo_funcion ?? '—');

    $observacionesAc = (string) ($funcionarioAc->observaciones ?? '');
    $extraerDatoAc = function (string $campo) use ($observacionesAc): ?string {
        $patrones = [
            'subdireccion_dependencia' => '/Subdirecci[oó]n dependencia:\s*(.*?)(?:\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'escalafon' => '/Escalaf[oó]n:\s*(.*?)(?:\s+Calidad jur[ií]dica:|\s+Unidad:|\s+Subdirecci[oó]n dependencia:|$)/iu',
            'unidad' => '/Unidad:\s*(.*?)(?:\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
        ];

        if (! isset($patrones[$campo]) || $observacionesAc === '') {
            return null;
        }

        if (preg_match($patrones[$campo], $observacionesAc, $matches)) {
            $valor = trim((string) ($matches[1] ?? ''));
            return $valor !== '' ? $valor : null;
        }

        return null;
    };

    $escalafon = $cometido->estamento ?: ($funcionarioAc->escalafon ?? $extraerDatoAc('escalafon') ?? '—');
    $unidad = $unidad !== '—' ? $unidad : ($extraerDatoAc('unidad') ?? '—');
    $dependencia = $dependencia !== 'Administración Central' ? $dependencia : ($extraerDatoAc('subdireccion_dependencia') ?? $dependencia);
    $numeroCometido = $cometido->numero_cometido_interno ?: ('CF-' . str_pad((string) $cometido->id, 6, '0', STR_PAD_LEFT));
@endphp

<div class="container py-4 cometido-informe-view">
    <style>
        .cometido-informe-view .cometido-btn { display: inline-flex; align-items: center; gap: .5rem; border-radius: .9rem; padding: .78rem 1rem; font-weight: 700; border: 1px solid transparent; box-shadow: 0 .2rem .7rem rgba(15,23,42,.05); text-decoration: none; transition: all .2s ease; }
        .cometido-informe-view .cometido-btn i { font-size: 1rem; }
        .cometido-informe-view .cometido-btn.is-secondary { background: #fff; color: #334155; border-color: #d7dee8; }
        .cometido-informe-view .cometido-btn.is-secondary:hover { background: #f8fafc; color: #0f172a; border-color: #c7d3e2; }
        .cometido-informe-view .cometido-btn.is-primary { background: linear-gradient(135deg, #0d6efd 0%, #1d4ed8 100%); color: #fff; }
        .cometido-informe-view .cometido-btn.is-primary:hover { filter: brightness(.98); box-shadow: 0 .45rem 1rem rgba(13,110,253,.22); }

        .cometido-informe-view .page-hero { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
        .cometido-informe-view .page-title { font-size: clamp(1.6rem, 2vw, 2.2rem); font-weight: 800; color: #0f172a; line-height: 1.1; margin-bottom: .25rem; }
        .cometido-informe-view .page-subtitle { color: #64748b; margin-bottom: 0; font-size: 1rem; }
        .cometido-informe-view .page-meta { display: flex; flex-wrap: wrap; gap: .55rem; margin-top: .75rem; }

        .cometido-informe-view .info-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .58rem; border-radius: 999px; background: #eef6ff; border: 1px solid #b9d9ff; color: #0d47a1; font-size: .78rem; font-weight: 800; }
        .cometido-informe-view .info-chip.is-success { background: #ecfdf3; border-color: #bcebd0; color: #0f5132; }
        .cometido-informe-view .info-chip.is-muted { background: #f1f5f9; border-color: #dbe4f0; color: #475569; }

        .cometido-informe-view .stage-panel-card,
        .cometido-informe-view .info-section-card { border: 1px solid #d7dee8; border-radius: 1rem; overflow: hidden; background: #fff; box-shadow: 0 .35rem 1.2rem rgba(15,23,42,.045); }
        .cometido-informe-view .stage-panel-header,
        .cometido-informe-view .info-section-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: .9rem; padding: 1rem; background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%); border-bottom: 1px solid #e5edf6; }
        .cometido-informe-view .stage-panel-title-wrap,
        .cometido-informe-view .info-section-title-wrap { display: flex; align-items: flex-start; gap: .8rem; min-width: 0; }
        .cometido-informe-view .stage-panel-icon,
        .cometido-informe-view .info-section-icon { flex: 0 0 auto; width: 2.55rem; height: 2.55rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; box-shadow: 0 .35rem .8rem rgba(15,23,42,.12); }
        .cometido-informe-view .stage-panel-icon.is-summary,
        .cometido-informe-view .info-section-icon.is-summary { background: #0d6efd; }
        .cometido-informe-view .stage-panel-icon.is-trip,
        .cometido-informe-view .info-section-icon.is-trip { background: #0f8f4d; }
        .cometido-informe-view .stage-panel-icon.is-form { background: #1d4ed8; }
        .cometido-informe-view .stage-panel-icon.is-support { background: #7c3aed; }
        .cometido-informe-view .stage-panel-kicker,
        .cometido-informe-view .info-section-kicker { font-size: .74rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: .035em; margin-bottom: .1rem; }
        .cometido-informe-view .stage-panel-help,
        .cometido-informe-view .info-section-help { color: #64748b; font-size: .84rem; margin-top: .18rem; line-height: 1.35; }
        .cometido-informe-view .stage-status-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .4rem .72rem; border-radius: 999px; border: 1px solid #dbe4f0; background: #f8fafc; color: #334155; font-size: .8rem; font-weight: 800; }
        .cometido-informe-view .stage-status-badge.is-info { background: #eef6ff; border-color: #b9d9ff; color: #0d47a1; }
        .cometido-informe-view .stage-panel-body { padding: 1.15rem; }

        .cometido-informe-view .info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .cometido-informe-view .info-grid.is-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .cometido-informe-view .info-item { border: 1px solid #e3eaf3; border-radius: .85rem; padding: .85rem; background: #f8fafc; min-height: 100%; }
        .cometido-informe-view .info-item.is-wide { grid-column: 1 / -1; }
        .cometido-informe-view .info-label { color: #64748b; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .025em; margin-bottom: .28rem; }
        .cometido-informe-view .info-value { color: #0f172a; font-weight: 700; line-height: 1.38; word-break: break-word; }
        .cometido-informe-view .info-value.is-muted { color: #64748b; font-weight: 600; }

        .cometido-informe-view .alert-inline { border-radius: .95rem; border: 1px solid #cfe1ff; background: #f5f9ff; color: #0f3d91; padding: .95rem 1rem; display: flex; align-items: flex-start; gap: .75rem; font-size: .9rem; line-height: 1.45; }
        .cometido-informe-view .alert-inline i { font-size: 1.1rem; margin-top: .05rem; }

        .cometido-informe-view .form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 1rem; }
        .cometido-informe-view .field-col-3 { grid-column: span 3; }
        .cometido-informe-view .field-col-4 { grid-column: span 4; }
        .cometido-informe-view .field-col-6 { grid-column: span 6; }
        .cometido-informe-view .field-col-12 { grid-column: span 12; }
        .cometido-informe-view .form-block { border: 1px solid #e3eaf3; border-radius: .95rem; background: #fff; overflow: hidden; }
        .cometido-informe-view .form-block-head { padding: .85rem 1rem; border-bottom: 1px solid #e5edf6; background: #f8fbff; }
        .cometido-informe-view .form-block-title { display: flex; align-items: center; gap: .45rem; color: #0f172a; font-size: .96rem; font-weight: 800; }
        .cometido-informe-view .form-block-help { color: #64748b; font-size: .8rem; line-height: 1.4; margin-top: .2rem; }
        .cometido-informe-view .form-block-body { padding: 1rem; }
        .cometido-informe-view .form-label { font-weight: 700; color: #0f172a; margin-bottom: .4rem; }
        .cometido-informe-view .form-label.required::after { content: ' *'; color: #dc3545; }
        .cometido-informe-view .form-control { border-radius: .8rem; border-color: #d7dee8; padding: .72rem .85rem; box-shadow: none; }
        .cometido-informe-view .form-control:focus { border-color: #93c5fd; box-shadow: 0 0 0 .18rem rgba(13,110,253,.12); }
        .cometido-informe-view textarea.form-control { min-height: 120px; resize: vertical; }
        .cometido-informe-view .form-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .75rem; padding: 1rem 1.15rem 1.15rem; border-top: 1px solid #e5edf6; background: #fbfdff; }

        .cometido-informe-view .error-panel { border: 1px solid #fecdd3; background: #fff8f8; border-radius: 1rem; padding: 1rem 1.1rem; color: #7f1d1d; box-shadow: 0 .25rem .8rem rgba(127,29,29,.05); }
        .cometido-informe-view .error-panel-title { display: flex; align-items: center; gap: .5rem; font-weight: 800; margin-bottom: .5rem; color: #991b1b; }
        .cometido-informe-view .error-panel ul { margin-bottom: 0; padding-left: 1.2rem; }

        @media (max-width: 991.98px) {
            .cometido-informe-view .info-grid,
            .cometido-informe-view .info-grid.is-4 { grid-template-columns: 1fr; }
            .cometido-informe-view .field-col-3,
            .cometido-informe-view .field-col-4,
            .cometido-informe-view .field-col-6,
            .cometido-informe-view .field-col-12 { grid-column: span 12; }
            .cometido-informe-view .form-actions .cometido-btn { width: 100%; justify-content: center; }
        }
    </style>

    <div class="page-hero">
        <div>
            <h1 class="page-title">Informe de cometido funcionario</h1>
            <p class="page-subtitle">Cometido {{ $numeroCometido }}</p>
            <div class="page-meta">
                <span class="info-chip"><i class="bi bi-person-badge"></i> {{ $esAc ? 'Administración Central' : 'Establecimiento' }}</span>
                <span class="info-chip is-success"><i class="bi bi-journal-check"></i> Preparación de informe</span>
                <span class="info-chip is-muted"><i class="bi bi-calendar-event"></i> {{ optional($cometido->fecha_desde)->format('d-m-Y') }} al {{ optional($cometido->fecha_hasta)->format('d-m-Y') }}</span>
            </div>
        </div>

        <a href="{{ route('tramites.cometidos-funcionarios.show', $cometido) }}" class="cometido-btn is-secondary">
            <i class="bi bi-arrow-left"></i> Volver al cometido
        </a>
    </div>

    @if ($errors->any())
        <div class="error-panel mb-4">
            <div class="error-panel-title"><i class="bi bi-exclamation-triangle-fill"></i> Revise los campos del informe</div>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="info-section-card mb-4">
        <div class="info-section-header">
            <div class="info-section-title-wrap">
                <span class="info-section-icon is-summary"><i class="bi bi-person-vcard"></i></span>
                <div>
                    <div class="info-section-kicker">Resumen del funcionario</div>
                    <h2 class="h5 mb-0">Identificación del funcionario</h2>
                    <div class="info-section-help">Datos de identificación utilizados como referencia para el informe de cometido.</div>
                </div>
            </div>
        </div>
        <div class="stage-panel-body">
            <div class="info-grid is-4">
                <div class="info-item">
                    <div class="info-label">Nombre</div>
                    <div class="info-value">{{ $cometido->funcionario_nombre ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">RUN</div>
                    <div class="info-value">{{ $cometido->funcionario_rut ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dependencia</div>
                    <div class="info-value">{{ $dependencia ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Unidad / cargo</div>
                    <div class="info-value">{{ $unidad ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Escalafón</div>
                    <div class="info-value">{{ $escalafon ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Grado</div>
                    <div class="info-value">{{ $funcionarioAc->grado ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Teléfono</div>
                    <div class="info-value">{{ $funcionarioAc->telefono ?? '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Correo</div>
                    <div class="info-value">{{ $funcionarioAc->email ?? optional($cometido->solicitante)->email ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="info-section-card mb-4">
        <div class="info-section-header">
            <div class="info-section-title-wrap">
                <span class="info-section-icon is-trip"><i class="bi bi-geo-alt"></i></span>
                <div>
                    <div class="info-section-kicker">Datos base del trámite</div>
                    <h2 class="h5 mb-0">Detalle original del cometido</h2>
                    <div class="info-section-help">Se muestra la información originalmente aprobada para contrastarla con la ejecución real del cometido.</div>
                </div>
            </div>
            <span class="stage-status-badge is-info"><i class="bi bi-clock-history"></i> Referencia original</span>
        </div>
        <div class="stage-panel-body">
            <div class="info-grid is-4 mb-3">
                <div class="info-item">
                    <div class="info-label">Desde</div>
                    <div class="info-value">{{ optional($cometido->fecha_desde)->format('d-m-Y') ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Hasta</div>
                    <div class="info-value">{{ optional($cometido->fecha_hasta)->format('d-m-Y') ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Salida</div>
                    <div class="info-value">{{ $cometido->hora_salida ?: '—' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Regreso</div>
                    <div class="info-value">{{ $cometido->hora_regreso ?: '—' }}</div>
                </div>
                <div class="info-item field-span-6">
                    <div class="info-label">Origen</div>
                    <div class="info-value">{{ $cometido->comuna_origen_nombre ?: '—' }}</div>
                </div>
                <div class="info-item field-span-6">
                    <div class="info-label">Destino</div>
                    <div class="info-value">{{ $cometido->comuna_destino_nombre ?: $cometido->destino ?: '—' }}</div>
                </div>
                <div class="info-item is-wide">
                    <div class="info-label">Propósito</div>
                    <div class="info-value">{{ trim(($cometido->motivo ?: '') . (($cometido->motivo_otro ?? null) ? ' - ' . $cometido->motivo_otro : '')) ?: '—' }}</div>
                </div>
                <div class="info-item is-wide">
                    <div class="info-label">Descripción original</div>
                    <div class="info-value {{ $cometido->descripcion_actividades ? '' : 'is-muted' }}">{{ $cometido->descripcion_actividades ?: 'Sin descripción registrada.' }}</div>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('tramites.cometidos-funcionarios.informe.store', $cometido) }}" class="stage-panel-card">
        @csrf
        <div class="stage-panel-header">
            <div class="stage-panel-title-wrap">
                <span class="stage-panel-icon is-form"><i class="bi bi-journal-text"></i></span>
                <div>
                    <div class="stage-panel-kicker">Etapa informe</div>
                    <h2 class="h5 mb-0">Formulario de informe de cometido</h2>
                    <div class="stage-panel-help">Complete la ejecución real del cometido, actividades realizadas, resultados obtenidos y propuestas derivadas de la comisión de servicio.</div>
                </div>
            </div>
            <span class="stage-status-badge"><i class="bi bi-pencil-square"></i> Edición del funcionario</span>
        </div>

        <div class="stage-panel-body">
            <div class="alert-inline mb-4">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Si modifica fechas u horarios respecto de la solicitud original, deberá justificar el cambio. Si la modificación genera días adicionales a favor del funcionario, el sistema registrará una alerta para generar un nuevo cometido por la diferencia.
                </div>
            </div>

            <div class="form-grid">
                <div class="field-col-12 form-block">
                    <div class="form-block-head">
                        <div class="form-block-title"><i class="bi bi-calendar3"></i> Ejecución real del cometido</div>
                        <div class="form-block-help">Informe las fechas y horarios efectivamente realizados por el funcionario.</div>
                    </div>
                    <div class="form-block-body">
                        <div class="form-grid">
                            <div class="field-col-3">
                                <label class="form-label required">Fecha real desde</label>
                                <input type="date" name="fecha_desde_real" class="form-control" required value="{{ old('fecha_desde_real', optional($informe->fecha_desde_real ?: $cometido->fecha_desde)->format('Y-m-d')) }}">
                            </div>
                            <div class="field-col-3">
                                <label class="form-label required">Fecha real hasta</label>
                                <input type="date" name="fecha_hasta_real" class="form-control" required value="{{ old('fecha_hasta_real', optional($informe->fecha_hasta_real ?: $cometido->fecha_hasta)->format('Y-m-d')) }}">
                            </div>
                            <div class="field-col-3">
                                <label class="form-label required">Hora real salida</label>
                                <input type="time" name="hora_salida_real" class="form-control" required value="{{ old('hora_salida_real', substr((string) ($informe->hora_salida_real ?: $cometido->hora_salida), 0, 5)) }}">
                            </div>
                            <div class="field-col-3">
                                <label class="form-label required">Hora real regreso</label>
                                <input type="time" name="hora_regreso_real" class="form-control" required value="{{ old('hora_regreso_real', substr((string) ($informe->hora_regreso_real ?: $cometido->hora_regreso), 0, 5)) }}">
                            </div>
                            <div class="field-col-12">
                                <label class="form-label">Justificación por cambio de fechas u horarios</label>
                                <textarea name="justificacion_cambio_fechas" class="form-control" rows="3">{{ old('justificacion_cambio_fechas', $informe->justificacion_cambio_fechas) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field-col-12 form-block">
                    <div class="form-block-head">
                        <div class="form-block-title"><i class="bi bi-people"></i> Desarrollo de la actividad</div>
                        <div class="form-block-help">Registre los actores que participaron y describa el trabajo desarrollado durante el cometido.</div>
                    </div>
                    <div class="form-block-body">
                        <div class="form-grid">
                            <div class="field-col-12">
                                <label class="form-label required">Organismos, autoridades o relatores de la actividad</label>
                                <textarea name="organismos_autoridades_relatores" class="form-control" rows="3" required>{{ old('organismos_autoridades_relatores', $informe->organismos_autoridades_relatores) }}</textarea>
                            </div>
                            <div class="field-col-12">
                                <label class="form-label required">Descripción de las actividades realizadas</label>
                                <textarea name="descripcion_actividades_realizadas" class="form-control" rows="5" required>{{ old('descripcion_actividades_realizadas', $informe->descripcion_actividades_realizadas) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field-col-12 form-block">
                    <div class="form-block-head">
                        <div class="form-block-title"><i class="bi bi-lightbulb"></i> Resultados y propuestas</div>
                        <div class="form-block-help">Consolide los principales resultados obtenidos y las propuestas derivadas del cometido.</div>
                    </div>
                    <div class="form-block-body">
                        <div class="form-grid">
                            <div class="field-col-12">
                                <label class="form-label required">Resultados obtenidos</label>
                                <textarea name="resultados_obtenidos" class="form-control" rows="4" required>{{ old('resultados_obtenidos', $informe->resultados_obtenidos) }}</textarea>
                            </div>
                            <div class="field-col-12">
                                <label class="form-label required">Opiniones y propuestas</label>
                                <textarea name="opiniones_propuestas" class="form-control" rows="4" required>{{ old('opiniones_propuestas', $informe->opiniones_propuestas) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="{{ route('tramites.cometidos-funcionarios.show', $cometido) }}" class="cometido-btn is-secondary">
                <i class="bi bi-x-circle"></i> Cancelar
            </a>
            <button type="submit" class="cometido-btn is-primary">
                <i class="bi bi-send-check"></i> Enviar informe a jefatura
            </button>
        </div>
    </form>
</div>
@endsection
