@extends('layouts.app')
@push('styles')
    @vite('resources/js/slep-listados.js')
@endpush
@section('content')
@php
    $fmt = fn ($v) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($v);
    $origen = array_intersect_key($fila, array_flip(['anio', 'origen_id', 'rut', 'bloque', 'tipo_horas']));
    $usuario = fn ($u) => $u ? trim(implode(' ', [$u->nombres, $u->apellido_paterno, $u->apellido_materno])) : 'Usuario no disponible';
@endphp
<div class="slep-listing" data-slep-listing>
    <header class="slep-listing__header"><div class="slep-listing__heading"><span class="slep-listing__icon"><i class="bi bi-signpost-split" aria-hidden="true"></i></span><div><p class="slep-listing__eyebrow">{{ $bloques[$fila['bloque']] }} · {{ $anio }}</p><h1>{{ $corregir ? 'Corregir distribución' : 'Asignar establecimiento' }}</h1><p class="mb-0">{{ $fila['nombre'] }} · {{ $fila['rut'] }}</p></div></div><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.disposicion.index', ['anio' => $anio, 'bloque' => $fila['bloque']]) }}">Volver a la nómina</a></header>
    @if(session('success'))<div class="alert alert-success rounded-4" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="slep-listing__notice slep-listing__notice--danger" data-slep-errors tabindex="-1" role="alert"><strong>Revise los datos.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(! $fila['finalizado'])<div class="alert alert-warning rounded-4" role="alert">El establecimiento de origen no está revisado y finalizado para {{ $anio }}. Puede consultar y anular el historial; vuelva a finalizar la revisión para distribuir o corregir horas.</div>@endif
    @if($fila['exceso'] > 0)<div class="alert alert-warning rounded-4" role="alert">El saldo de origen cambió. Hay {{ $fmt($fila['exceso']) }} h distribuidas de más. Corrija o anule las distribuciones antes de incorporar nuevas horas.</div>@endif
    <section class="slep-listing__panel p-4" aria-labelledby="dis-antecedente"><h2 id="dis-antecedente" class="mb-3">Antecedentes de origen</h2><div class="row g-3"><div class="col-md-6"><strong>RBD {{ $fila['rbd'] }} · {{ $fila['establecimiento'] }}</strong><div>{{ $fila['funcion'] }} · {{ $fila['titulo'] }}</div><div>{{ $fila['tipo'] }} · {{ $fila['situacion'] }}</div></div><div class="col-md-6"><div>Horas a justificar: <strong>{{ $fmt($fila['horas']) }} h</strong></div><div>Distribuidas: {{ $fmt($fila['distribuidas']) }} h · Disponibles: <strong>{{ $fmt($fila['saldo']) }} h</strong></div></div></div><h3 class="mt-4">Justificación</h3><span class="slep-status-pill slep-status-pill--{{ $fila['estado_justificacion'] === 'Vigente' ? 'success' : 'warning' }}">{{ $fila['estado_justificacion'] }}</span><p class="mt-2 mb-0 text-break" style="white-space: pre-line">{{ $fila['justificacion'] }}</p></section>
    @if($maximo > 0 && (! $fila['exceso'] || $corregir))
        <section class="slep-listing__panel" aria-labelledby="dis-formulario"><div class="slep-listing__panel-header"><h2 id="dis-formulario">{{ $corregir ? 'Nueva versión de la distribución #'.$corregir->id : 'Registrar destino' }}</h2></div>
            <form method="POST" action="{{ $corregir ? route('admin.dotacion-establecimiento.disposicion.update', $corregir->id) : route('admin.dotacion-establecimiento.disposicion.store') }}" class="slep-listing__filters">
                @csrf @if($corregir) @method('PUT') @endif
                @foreach($origen as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
                <input type="hidden" name="solicitud_uuid" value="{{ old('solicitud_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="dis-destino">Establecimiento de destino *</label>
                        <select class="form-select @error('destino_id') is-invalid @enderror" id="dis-destino" name="destino_id" data-slep-select="search" aria-describedby="dis-destino-ayuda" required>
                            <option value="">Seleccione un establecimiento</option>
                            @foreach($destinos as $destino)
                                <option value="{{ $destino['id'] }}" @selected(old('destino_id', $corregir?->destino_id) == $destino['id'])>
                                    {{ $destino['rbd'] }} · {{ $destino['nombre'] }} · {{ $bloques[$fila['bloque']] }}: {{ $destino['horas'] === null ? 'Sin información' : $fmt($destino['horas']).' h en cupos con asignación' }}
                                    · Por contratar: {{ $fmt($destino['cupos']) }} h · Contrata completa: {{ $fmt($destino['contrata']) }} h
                                </option>
                            @endforeach
                        </select>
                        @if($destinos->isEmpty())<p class="small text-warning-emphasis mt-2">No hay otros establecimientos revisados y finalizados para {{ $anio }}. Registre su revisión en la vista general de dotación.</p>@endif
                        <p class="form-text" id="dis-destino-ayuda">Horas asignadas del bloque y año {{ $anio }} a docentes por contratar o docentes reales con todas sus horas a contrata. Se excluyen Director, Inspector, UTP, Coordinación PIE, Convivencia Educativa y docentes con fuero maternal o gremial. Esta referencia no limita la distribución; puede seleccionar destinos con 0 h.</p>
                        @error('destino_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4"><label class="form-label" for="dis-horas">Horas a distribuir *</label><input class="form-control @error('horas') is-invalid @enderror" id="dis-horas" name="horas" type="number" min="0.01" max="{{ $maximo }}" step="0.01" value="{{ old('horas', $corregir?->horas) }}" aria-describedby="dis-horas-ayuda" required><p class="form-text" id="dis-horas-ayuda">Máximo de origen: {{ $fmt($maximo) }} h. Admite hasta 2 decimales.@if($corregir) Incluye las horas de esta distribución que se reemplazará.@endif</p>@error('horas')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="dis-observacion">Observación</label><textarea class="form-control @error('observacion') is-invalid @enderror" id="dis-observacion" name="observacion" rows="3" maxlength="3000">{{ old('observacion', $corregir?->observacion) }}</textarea>@error('observacion')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    @if($corregir)<div class="col-12"><label class="form-label" for="dis-motivo-correccion">Motivo de la corrección *</label><textarea class="form-control @error('motivo_correccion') is-invalid @enderror" id="dis-motivo-correccion" name="motivo_correccion" rows="2" minlength="10" maxlength="1000" required>{{ old('motivo_correccion') }}</textarea><p class="form-text">La versión anterior quedará anulada y ambas se conservarán en el historial.</p>@error('motivo_correccion')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endif
                </div>
                <div class="slep-listing__filter-actions"><p class="mb-0 slep-listing__muted">Se conservarán el contrato, las horas y el establecimiento del registro de origen.</p><div class="slep-listing__actions"><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.disposicion.gestionar', $origen) }}">Cancelar</a><button class="slep-listing__button slep-listing__button--primary" type="submit" @disabled($destinos->isEmpty())>{{ $corregir ? 'Guardar corrección' : 'Guardar distribución' }}</button></div></div>
            </form>
        </section>
    @else<div class="alert alert-info rounded-4">{{ $fila['finalizado'] ? 'No hay saldo disponible para nuevas distribuciones.' : 'La distribución está suspendida hasta finalizar la revisión anual del origen.' }} Puede revisar el historial y anular registros activos.</div>@endif
    <section class="slep-listing__panel p-4" aria-labelledby="dis-historial"><h2 id="dis-historial" class="mb-3">Destinos e historial</h2>
        @forelse($fila['historial'] as $registro)
            <article class="border rounded-4 p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2"><h3>#{{ $registro->id }} · {{ $registro->destino?->rbd }} · {{ $registro->destino?->nombre_establecimiento ?? 'Establecimiento no disponible' }}</h3><span class="slep-status-pill slep-status-pill--{{ $registro->anulada_at ? 'neutral' : 'success' }}">{{ $registro->anulada_at ? 'Anulada' : 'Activa' }} · {{ $fmt($registro->horas) }} h</span></div>
                <p class="small mb-2">Registrada por {{ $usuario($registro->creador) }} · {{ $registro->created_at?->format('d-m-Y H:i') }}@if($registro->reemplaza_id) · Reemplaza #{{ $registro->reemplaza_id }}@endif</p>
                @if($registro->observacion)<p class="text-break" style="white-space: pre-line">{{ $registro->observacion }}</p>@endif
                <details class="mb-2"><summary>Ver antecedente al distribuir</summary><p class="mt-2 mb-0 text-break" style="white-space: pre-line">{{ $registro->antecedente['tipo'] ?? '' }} · {{ $fmt($registro->antecedente['horas'] ?? 0) }} h de origen · {{ $registro->antecedente['situacion'] ?? '' }}<br>{{ $registro->antecedente['justificacion'] ?? 'Sin justificación' }}</p></details>
                @if($registro->anulada_at)<p class="small mb-0 text-break">Anulada por {{ $usuario($registro->anulador) }} · {{ $registro->anulada_at->format('d-m-Y H:i') }} · {{ $registro->motivo_anulacion }}</p>
                @else
                    <div class="slep-listing__actions mt-3">@if(empty($fila['sin_origen']) && $fila['finalizado'])<a class="slep-listing__button slep-listing__button--secondary" href="{{ route('admin.dotacion-establecimiento.disposicion.gestionar', $origen + ['corregir' => $registro->id]) }}">Corregir distribución</a>@endif</div>
                    <details class="mt-3" @if((int) old('registro_anular') === (int) $registro->id) open @endif><summary>Anular esta distribución</summary><form class="mt-3" method="POST" action="{{ route('admin.dotacion-establecimiento.disposicion.anular', $registro->id) }}">@csrf<input type="hidden" name="anio" value="{{ $anio }}"><input type="hidden" name="registro_anular" value="{{ $registro->id }}"><label class="form-label" for="dis-anular-{{ $registro->id }}">Motivo de anulación *</label><textarea class="form-control" id="dis-anular-{{ $registro->id }}" name="motivo" rows="2" minlength="10" maxlength="1000" required>{{ (int) old('registro_anular') === (int) $registro->id ? old('motivo') : '' }}</textarea><p class="form-text mb-3">Liberará {{ $fmt($registro->horas) }} h distribuidas. El registro permanecerá en el historial.</p><button class="btn btn-outline-danger rounded-3" type="submit">Confirmar anulación</button></form></details>
                @endif
            </article>
        @empty<p class="mb-0 slep-listing__muted">Este docente aún no tiene distribuciones registradas para este año, bloque y tipo de horas.</p>@endforelse
    </section>
</div>
@endsection
