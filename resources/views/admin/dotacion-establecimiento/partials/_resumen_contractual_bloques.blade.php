@if (($proceso2027['aplica'] ?? false) && !empty($proceso2027['bloques']))
    @php
        $referenciasBloques = [
            'bloque_1' => ['vacantes' => 'plan_estudio', 'contrato' => 'horas_contrato_docentes_aula_general'],
            'bloque_2' => ['vacantes' => 'parvularia', 'contrato' => 'horas_contrato_docentes_parvularia'],
            'bloque_3' => ['vacantes' => 'pie', 'contrato' => 'horas_contrato_docente_pie'],
        ];
    @endphp
    <section class="card dotacion-section mb-4" aria-labelledby="revision-bloques-titulo">
        <div class="dotacion-section-header">
            <div class="dotacion-eyebrow">Cuadratura contractual · {{ $anio }}</div>
            <h2 id="revision-bloques-titulo" class="h5 fw-bold mb-1">Contrato y ocupación por bloque</h2>
            <p class="small text-muted mb-0">Horas de contrato. Las reservas se muestran separadas de las asignaciones y se descuentan una sola vez.</p>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach ($referenciasBloques as $clave => $referencia)
                    @php
                        $bloqueRevision = $proceso2027['bloques'][$clave] ?? [];
                        $contratoRevision = data_get($resumen ?? [], $referencia['contrato']);
                        $reservaRevision = (float) ($bloqueRevision['reservadas_no_normativas'] ?? 0);
                        $redondeoRevision = (float) ($bloqueRevision['redondeo_parvularia'] ?? 0);
                        $ocupadoRevision = (float) ($bloqueRevision['asignadas_con_redondeo'] ?? ((float) ($bloqueRevision['asignadas'] ?? 0) + $redondeoRevision));
                        $asignadoRevision = round($ocupadoRevision - $reservaRevision, 2);
                        $saldoRevision = $contratoRevision === null ? null : round((float) $contratoRevision - $ocupadoRevision, 2);
                    @endphp
                    <div class="col-xl-4">
                        <article class="dotacion-contract-block h-100" data-contract-block="{{ $clave }}">
                            <h3 class="h6 fw-bold mb-3">{{ $bloqueRevision['label'] ?? \App\Support\DotacionProceso2027Calculator::BLOQUES[$clave] }}</h3>
                            <dl class="dotacion-contract-values mb-0">
                                <div><dt>Contrato vigente</dt><dd data-contract-value="vigente">{{ $contratoRevision === null ? '—' : $fmt($contratoRevision).' h' }}</dd></div>
                                <div><dt>Asignadas</dt><dd data-contract-value="asignadas">{{ $fmt($asignadoRevision) }} h</dd></div>
                                <div><dt>Reservadas sin función</dt><dd data-contract-value="reservadas">{{ $fmt($reservaRevision) }} h</dd></div>
                                <div class="border-top pt-2"><dt>Saldo contractual neto</dt><dd data-contract-value="saldo" class="{{ $saldoRevision < -0.01 ? 'text-danger' : '' }}">{{ $saldoRevision === null ? '—' : $fmt($saldoRevision).' h' }}</dd></div>
                                <div><dt>Máximo autorizado</dt><dd>{{ ($bloqueRevision['maximo'] ?? null) === null ? 'Sin configurar' : $fmt($bloqueRevision['maximo']).' h' }}</dd></div>
                                <div><dt>Vacantes revisables</dt><dd>{{ $fmt(data_get($sobredotacion ?? [], 'vacantes_por_bloque.'.$referencia['vacantes'].'.horas_total', 0)) }} h</dd></div>
                            </dl>
                            @if ($redondeoRevision > 0.01)
                                <p class="small text-muted mt-3 mb-0">Asignadas incluye +{{ $fmt($redondeoRevision) }} h de redondeo individual NT. No modifica las horas guardadas.</p>
                            @endif
                            @if ((float) ($bloqueRevision['sin_padron_asignadas'] ?? 0) > 0.01)
                                <p class="small text-muted mt-2 mb-0">La ocupación incluye {{ $fmt($bloqueRevision['sin_padron_asignadas']) }} h de cupos por contratar o asignaciones sin padrón vigente.</p>
                            @endif
                            @if ((float) ($bloqueRevision['asignadas_asistentes_obligatorias'] ?? 0) > 0.01)
                                <p class="small text-muted mt-2 mb-0">Cobertura AAEE: {{ $fmt($bloqueRevision['asignadas_asistentes_obligatorias']) }} h. Completa necesidades sin consumir contrato docente.</p>
                            @endif
                        </article>
                    </div>
                @endforeach
            </div>
            <p class="small text-muted mt-3 mb-0">El saldo neto puede ser negativo y compensa sobreasignaciones. Las vacantes revisables son saldos individuales: excluyen contratos protegidos y no compensan el exceso de otro docente. La cobertura consolidada por curso puede diferir de la conversión por docente; consulte la conciliación en Detalle sobredotación.</p>
        </div>
    </section>
@endif
