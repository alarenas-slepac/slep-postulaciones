@php
    $contratos = collect($contratos ?? []);
    $reemplazos = collect($reemplazos ?? []);
    $externo = (bool) ($externo ?? false);
@endphp
<div class="slep-record-states">
    @if ($contratos->isNotEmpty())
        <section class="slep-record-state slep-record-state--warning" aria-label="Vinculación laboral activa registrada">
            <div class="slep-record-state__title"><i class="bi bi-briefcase-fill" aria-hidden="true"></i> Vinculación laboral activa registrada</div>
            <ul class="slep-record-state__list">
                @foreach ($contratos as $contrato)
                    <li>
                        <strong>{{ $contrato->establecimiento?->nombre_establecimiento ?: 'Establecimiento no informado' }}</strong>
                        @if ($contrato->establecimiento?->rbd)<span class="slep-record-state__detail">RBD {{ $contrato->establecimiento->rbd }}</span>@endif
                        <span class="slep-record-state__detail">{{ $contrato->tipo_contrato ?: 'Tipo de contrato no informado' }} · {{ $contrato->cantidad_horas !== null ? $contrato->cantidad_horas.' h' : 'Horas no informadas' }}</span>
                        <span class="slep-record-state__detail">{{ $contrato->fecha_termino ? 'Término: '.$contrato->fecha_termino->format('d-m-Y') : 'Sin fecha de término registrada' }}</span>
                        @if ($mostrarAuditoria ?? false)
                            <span class="slep-record-state__detail">Registrado {{ $contrato->created_at?->format('d-m-Y H:i') ?: 'sin fecha informada' }} @if ($contrato->registradoPor)por {{ $contrato->registradoPor->nombre_completo }}@endif</span>
                        @endif
                        @if ($gestionarContratos ?? false)
                            <button type="button" class="slep-listing__button slep-profile__button-danger slep-listing__button--compact mt-2" data-bs-toggle="modal" data-bs-target="#desactivarContratoLaboralModal{{ $contrato->id }}"><i class="bi bi-x-circle" aria-hidden="true"></i> Finalizar este vínculo</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @else
        <section class="slep-record-state slep-record-state--neutral" aria-label="Sin vinculación laboral activa registrada">
            <div class="slep-record-state__title"><i class="bi bi-briefcase" aria-hidden="true"></i> Sin vinculación laboral activa registrada</div>
            <p class="slep-record-state__detail mb-0">Este dato no confirma disponibilidad para un reemplazo.</p>
        </section>
    @endif
    @if ($externo)
        <section class="slep-record-state slep-record-state--success" aria-label="Informó trabajo externo">
            <div class="slep-record-state__title"><i class="bi bi-telephone-outbound" aria-hidden="true"></i> Informó trabajo externo</div>
            <p class="slep-record-state__detail mb-0">{{ ($observacion ?? null) ?: 'Informó que trabaja en otro lugar, sin detallar la institución.' }}</p>
            @if (($mostrarAuditoria ?? false) && ($usuario?->trabaja_en_otro_lugar_marcado_en ?? null))
                <p class="slep-record-state__detail mb-0">Marcado el {{ $usuario->trabaja_en_otro_lugar_marcado_en->format('d-m-Y H:i') }} @if ($usuario->trabajoExternoMarcadoPor)por {{ $usuario->trabajoExternoMarcadoPor->nombre_completo }}@endif</p>
            @endif
        </section>
    @endif
    @if ($reemplazos->isNotEmpty())
        <section class="slep-record-state slep-record-state--info" aria-label="En reemplazo vigente">
            <div class="slep-record-state__title"><i class="bi bi-person-check" aria-hidden="true"></i> En reemplazo vigente</div>
            <ul class="slep-record-state__list">
                @foreach ($reemplazos as $reemplazo)
                    <li>
                        <strong>{{ $reemplazo->establecimiento?->nombre_establecimiento ?: 'Establecimiento no informado' }}</strong>
                        <span class="slep-record-state__detail">Solicitud {{ $reemplazo->numero_solicitud ?: '#'.$reemplazo->id }}</span>
                        <span class="slep-record-state__detail">{{ $reemplazo->fecha_termino ? 'Término: '.$reemplazo->fecha_termino->format('d-m-Y') : 'Término no informado' }}</span>
                        @if ($mostrarAuditoria ?? false)<span class="slep-record-state__detail">Estado: {{ \Illuminate\Support\Str::headline($reemplazo->estado ?: 'No informado') }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
