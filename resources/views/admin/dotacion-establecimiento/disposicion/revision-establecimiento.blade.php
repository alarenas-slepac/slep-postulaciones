@php
    $historialRevision = ($revisionesDotacion ?? collect())->get($establecimiento->id, collect());
    $ultimaRevision = $historialRevision->first();
    $finalizadaRevision = $ultimaRevision && ! $ultimaRevision->reabierta_at;
@endphp
<span class="badge rounded-pill {{ $finalizadaRevision ? 'text-bg-success' : 'text-bg-light border text-dark' }}">{{ $finalizadaRevision ? 'Revisado y finalizado' : ($ultimaRevision ? 'Reabierto' : 'Pendiente de revisión') }}</span>
@if($finalizadaRevision)
    <div class="small text-muted mt-2">{{ $ultimaRevision->finalizada_at->format('d-m-Y H:i') }} · {{ $ultimaRevision->responsable?->display_name ?? 'Responsable no disponible' }}</div>
@endif
@if($revisionesDotacionDisponibles ?? false)
    <details class="mt-2">
        <summary class="small">{{ $finalizadaRevision ? 'Reabrir revisión' : 'Marcar revisado y finalizado' }}</summary>
        @if($finalizadaRevision)
            <form class="mt-2" method="POST" action="{{ route('admin.dotacion-establecimiento.revision.reabrir', $establecimiento->id) }}">
                @csrf<input type="hidden" name="anio" value="{{ $anio }}"><input type="hidden" name="revision_id" value="{{ $ultimaRevision->id }}">
                <label class="form-label small" for="revision-motivo-{{ $establecimiento->id }}">Motivo de reapertura *</label>
                <textarea class="form-control" id="revision-motivo-{{ $establecimiento->id }}" name="motivo" rows="2" minlength="10" maxlength="1000" required></textarea>
                <p class="small text-muted mt-2">Dejará de aportar nuevos docentes y destinos para {{ $anio }}. El historial se conserva.</p>
                <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit">Confirmar reapertura</button>
            </form>
        @else
            <form class="mt-2" method="POST" action="{{ route('admin.dotacion-establecimiento.revision.finalizar', $establecimiento->id) }}">
                @csrf<input type="hidden" name="anio" value="{{ $anio }}">
                <div class="form-check"><input class="form-check-input" id="revision-confirmar-{{ $establecimiento->id }}" name="confirmacion" type="checkbox" value="1" required><label class="form-check-label small" for="revision-confirmar-{{ $establecimiento->id }}">Confirmo que revisé y finalicé la dotación de {{ $anio }}.</label></div>
                <p class="small text-muted mt-2">Habilitará el establecimiento como origen y destino en Docentes a disposición.</p>
                <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit">Guardar revisión finalizada</button>
            </form>
        @endif
    </details>
    @if($historialRevision->isNotEmpty())
        <details class="small mt-2"><summary>Historial de revisión</summary><ul class="ps-3 mt-2">@foreach($historialRevision as $revision)<li>Finalizado el {{ $revision->finalizada_at->format('d-m-Y H:i') }} por {{ $revision->responsable?->display_name ?? 'Responsable no disponible' }}.@if($revision->reabierta_at) Reabierto el {{ $revision->reabierta_at->format('d-m-Y H:i') }}: {{ $revision->motivo_reapertura }} @endif</li>@endforeach</ul></details>
    @endif
@else
    <div class="small text-muted mt-2">Aplique la migración de revisiones anuales para habilitar esta marca.</div>
@endif
