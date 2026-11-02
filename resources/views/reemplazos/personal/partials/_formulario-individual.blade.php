@php
    $tramos = ['No Regido Ley 20903', 'Acceso', 'Inicial', 'Temprano', 'Avanzado', 'Experto 1', 'Experto 2'];
    $tramoActual = (string) old('tramo', $item?->tramo ?? '');
    if ($tramoActual !== '' && !in_array($tramoActual, $tramos, true)) { $tramos[] = $tramoActual; }
@endphp
<form method="POST" action="{{ route('reemplazos.individual.store') }}" data-padron-form data-today="{{ now()->toDateString() }}" novalidate>
    @csrf
    <input type="hidden" name="rut" value="{{ $rut }}"><input type="hidden" name="huella" value="{{ $huella }}"><input type="hidden" name="periodo" value="{{ $periodo }}">
    @if($item)<input type="hidden" name="personal_id" value="{{ $item->id }}">@endif
    <div class="slep-listing__notice slep-listing__notice--danger" role="alert" data-padron-validation hidden></div>
    <section class="slep-listing__panel" aria-labelledby="padron-formulario-titulo">
        <div class="slep-listing__panel-header"><div><p class="slep-listing__eyebrow">Paso 3 · {{ $item ? 'Actualización' : 'Ingreso' }}</p><h2 id="padron-formulario-titulo">{{ $item ? 'Actualizar ID '.$item->id.' sin cambiar sus referencias' : 'Ingresar nuevo registro' }}</h2><p class="slep-listing__muted mb-0">Completa los antecedentes. Los campos con <span class="text-danger">*</span> son obligatorios.</p></div><span class="slep-listing__chip"><i class="bi bi-calendar3" aria-hidden="true"></i>{{ $textoPeriodo }}</span></div>
        <div class="padron-body">
            @if(!$item && $tieneRegular)
                <div class="slep-record-state slep-record-state--warning mb-4"><strong>Este RUT ya tiene contratos regulares.</strong> Sólo puedes crear un REEMPLAZO aquí. Para modificar un contrato regular, selecciona su ID arriba.
                    @if($editables->isEmpty())<p class="mt-2 mb-0">Los contratos regulares son de períodos anteriores. Su incorporación al período abierto debe resolverse en la carga completa, conservando el ID y el historial mensual.</p>@endif
                </div>
            @endif
            @if($item && !$item->vigente)<div class="slep-record-state slep-record-state--warning mb-4">Al guardar se reactivará este mismo ID en el período abierto. Los bloqueos del RUT se mantienen.</div>@endif
            <fieldset class="padron-section"><legend><i class="bi bi-person" aria-hidden="true"></i>Identificación de la persona</legend><div class="row g-3">
                <div class="col-md-8">@include('reemplazos.personal.partials._campo-individual', ['definicion' => ['campo' => 'nombre', 'etiqueta' => 'Nombre completo', 'valor' => $item?->nombre ?? $registros->last()?->nombre, 'obligatorio' => true, 'maximo' => 255]])</div>
                <div class="col-md-4">@include('reemplazos.personal.partials._campo-individual', ['definicion' => ['campo' => 'fecha_nacimiento', 'etiqueta' => 'Fecha de nacimiento', 'fecha' => true]])</div>
            </div></fieldset>
            <fieldset class="padron-section"><legend><i class="bi bi-building" aria-hidden="true"></i>Establecimiento y contrato</legend><div class="row g-3">
                <div class="col-lg-8"><label for="establecimiento_id" class="form-label">Establecimiento <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="establecimiento_id" name="establecimiento_id" class="form-select @error('establecimiento_id') is-invalid @enderror" data-slep-select="search" data-padron-field required aria-describedby="establecimiento_id-help establecimiento_id-error" @error('establecimiento_id') aria-invalid="true" @enderror><option value="">Seleccione</option>
                        @foreach($establecimientos as $est)<option value="{{ $est->id }}" @selected((int) old('establecimiento_id', $item?->establecimiento_id) === $est->id)>{{ $est->nombre_establecimiento }} · RBD {{ $est->rbd }}</option>@endforeach
                    </select><p class="form-text" id="establecimiento_id-help">Busca por nombre o RBD. En un traslado, selecciona el establecimiento de destino.</p><div class="invalid-feedback" id="establecimiento_id-error">@error('establecimiento_id'){{ $message }}@enderror</div>
                </div>
                <div class="col-lg-4"><label for="tipocontrato" class="form-label">Tipo de contrato <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="tipocontrato" name="tipocontrato" class="form-select @error('tipocontrato') is-invalid @enderror" data-slep-select="compact" data-padron-field required aria-describedby="tipocontrato-help tipocontrato-error" @error('tipocontrato') aria-invalid="true" @enderror><option value="">Seleccione</option>
                        @foreach(\App\Services\Padron\PadronIndividualService::CONTRATOS as $tipo)
                            @if(($item && $tipo !== 'REEMPLAZO') || (!$item && (!$tieneRegular || $tipo === 'REEMPLAZO')))<option value="{{ $tipo }}" @selected(old('tipocontrato', $item?->tipocontrato ?? ($tieneRegular ? 'REEMPLAZO' : '')) === $tipo)>{{ $tipo }}</option>@endif
                        @endforeach
                    </select><p class="form-text" id="tipocontrato-help">Si el nombre antiguo incluía SEP/PIE, mantén el financiamiento separado.</p><div class="invalid-feedback" id="tipocontrato-error">@error('tipocontrato'){{ $message }}@enderror</div>
                </div>
                @foreach(['financiamiento' => 'Financiamiento', 'estatuto' => 'Estatuto (DOCENTE / AAEE)', 'escalafon' => 'Escalafón'] as $campo => $etiqueta)
                    <div class="col-md-4">@include('reemplazos.personal.partials._campo-individual', ['definicion' => ['campo' => $campo, 'etiqueta' => $etiqueta, 'obligatorio' => true, 'maximo' => 255]])</div>
                @endforeach
            </div></fieldset>
            <fieldset class="padron-section"><legend><i class="bi bi-calendar-range" aria-hidden="true"></i>Fechas del vínculo laboral</legend><div class="row g-3">
                @foreach(['fecha_ingreso' => 'Fecha de inicio', 'fecha_termino' => 'Fecha de término', 'fecha_antiguedad' => 'Fecha de antigüedad'] as $campo => $etiqueta)
                    <div class="col-md-4">@include('reemplazos.personal.partials._campo-individual', ['definicion' => ['campo' => $campo, 'etiqueta' => $etiqueta, 'fecha' => true, 'obligatorio' => $campo === 'fecha_ingreso' || ($campo === 'fecha_termino' && in_array(old('tipocontrato', $item?->tipocontrato), ['REEMPLAZO', 'PLAZO FIJO'], true))]])</div>
                @endforeach
            </div><p class="form-text mt-3">La fecha de término es obligatoria para REEMPLAZO y PLAZO FIJO.</p></fieldset>
            <fieldset class="padron-section"><legend><i class="bi bi-clock" aria-hidden="true"></i>Jornada y antecedentes de carrera</legend><div class="row g-3">
                @foreach(['jornada' => 'Jornada total', 'jornada_basica' => 'Jornada básica', 'jornada_media' => 'Jornada media', 'bienios' => 'Bienios'] as $campo => $etiqueta)
                    <div class="col-md-3">@include('reemplazos.personal.partials._campo-individual', ['definicion' => ['campo' => $campo, 'etiqueta' => $etiqueta, 'entero' => true, 'minimo' => $campo === 'jornada' ? 1 : 0, 'maximo' => $campo === 'bienios' ? 50 : 168, 'unidad' => $campo === 'bienios' ? null : 'h', 'valor' => $item?->$campo ?? ($campo === 'jornada' ? '' : 0), 'obligatorio' => $campo !== 'bienios']])</div>
                @endforeach
                <div class="col-md-6"><label for="tramo" class="form-label">Tramo</label><select id="tramo" name="tramo" class="form-select @error('tramo') is-invalid @enderror" data-slep-select="tags" data-padron-field aria-describedby="tramo-help tramo-error" @error('tramo') aria-invalid="true" @enderror><option value=""></option>@foreach($tramos as $tramo)<option value="{{ $tramo }}" @selected($tramoActual === $tramo)>{{ $tramo }}</option>@endforeach</select><p class="form-text" id="tramo-help">Busca un tramo o escribe otro valor registrado. Máximo 100 caracteres.</p><div class="invalid-feedback" id="tramo-error">@error('tramo'){{ $message }}@enderror</div></div>
            </div><p class="form-text mt-3">Las jornadas básica y media son componentes: su suma no puede superar la jornada total.</p></fieldset>
            <fieldset class="padron-section"><legend><i class="bi bi-journal-check" aria-hidden="true"></i>Justificación y autorización</legend>
                <label for="justificacion" class="form-label">Justificación del ingreso o modificación <span class="text-danger" aria-hidden="true">*</span></label><textarea id="justificacion" name="justificacion" class="form-control @error('justificacion') is-invalid @enderror" rows="3" minlength="10" maxlength="2000" data-padron-field required aria-describedby="justificacion-help justificacion-error" @error('justificacion') aria-invalid="true" @enderror>{{ old('justificacion') }}</textarea><p class="form-text" id="justificacion-help">Explica el motivo del cambio; entre 10 y 2.000 caracteres. Quedará registrado en el historial.</p><div class="invalid-feedback" id="justificacion-error">@error('justificacion'){{ $message }}@enderror</div>
                <details class="padron-disclosure mt-4" @if($errors->has('autorizar_exceso') || $errors->has('justificacion_exceso') || old('autorizar_exceso')) open @endif>
                    <summary><span><i class="bi bi-shield-exclamation me-2" aria-hidden="true"></i>Autorización excepcional: más de 44 horas docentes</span><i class="bi bi-chevron-down padron-disclosure-arrow" aria-hidden="true"></i></summary>
                    <div class="padron-disclosure-body"><p class="slep-listing__muted">Se controlan contratos docentes simultáneos del RUT entre establecimientos. Los contratos consecutivos sin superposición no se suman entre sí.</p>
                        <div class="padron-check"><input type="checkbox" class="form-check-input @error('autorizar_exceso') is-invalid @enderror" id="autorizar_exceso" name="autorizar_exceso" value="1" @checked(old('autorizar_exceso')) aria-describedby="autorizar_exceso-error" @error('autorizar_exceso') aria-invalid="true" @enderror><label for="autorizar_exceso">Autorizo expresamente el exceso de 44 horas</label></div><div class="invalid-feedback" id="autorizar_exceso-error">@error('autorizar_exceso'){{ $message }}@enderror</div>
                        <label for="justificacion_exceso" class="form-label mt-3">Justificación de la excepción</label><textarea id="justificacion_exceso" name="justificacion_exceso" class="form-control @error('justificacion_exceso') is-invalid @enderror" rows="3" maxlength="2000" data-padron-field aria-describedby="justificacion_exceso-help justificacion_exceso-error">{{ old('justificacion_exceso') }}</textarea><p class="form-text" id="justificacion_exceso-help">Para autorizar un exceso, explica el motivo con al menos 10 caracteres.</p><div class="invalid-feedback" id="justificacion_exceso-error">@error('justificacion_exceso'){{ $message }}@enderror</div>
                    </div>
                </details>
            </fieldset>
        </div>
    </section>
    @if($asignaciones->isNotEmpty())@include('reemplazos.personal.partials._asignaciones-individual')@endif
    <div class="slep-listing__panel padron-save-bar"><p class="slep-listing__muted mb-0"><i class="bi bi-shield-check me-2" aria-hidden="true"></i>Se guardarán el registro y las decisiones sobre asignaciones en una sola operación.</p><div class="slep-listing__actions"><a class="slep-listing__button slep-listing__button--secondary" href="{{ route('reemplazos.index') }}">Cancelar</a><button type="submit" class="slep-listing__button slep-listing__button--primary"><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ $item ? 'Guardar cambios conservando ID '.$item->id : 'Crear nuevo registro' }}</span></button></div></div>
    <p class="visually-hidden" role="status" data-padron-submit-status></p>
</form>
