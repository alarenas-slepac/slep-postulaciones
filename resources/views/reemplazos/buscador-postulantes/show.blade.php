@extends('layouts.app')

@push('styles')
    @vite('resources/js/postulante-ficha.js')
@endpush

@section('content')
@php
    $contratosActivos = $profile->contratosLaboralesActivos ?? collect();
    $contratoActivo = $contratosActivos->sortByDesc('id')->first();
    $ultimaVinculacionLaboral = $ultimaVinculacionLaboral ?? ($profile->relationLoaded('ultimaVinculacionLaboral') ? $profile->ultimaVinculacionLaboral : null);
    $restrictionContext = $restrictionContext ?? [];
    $hasCourtRestriction = (bool) ($restrictionContext['court_active'] ?? false);
    $hasManualRestriction = (bool) ($restrictionContext['manual_active'] ?? false);
    $canManageContratoLaboral = (bool) ($canManageContratoLaboral ?? false);
    $canManageTrabajoExterno = (bool) ($canManageTrabajoExterno ?? false);
    $campo = function ($nombre, $defecto = ''): string {
        $valor = old($nombre, $defecto);
        return is_scalar($valor) ? (string) $valor : '';
    };
    $vinculacionesOld = old('vinculaciones');
    $vinculaciones = is_array($vinculacionesOld)
        ? collect($vinculacionesOld)->filter(fn ($item) => is_array($item))->values()->take(10)
        : $contratosActivos->map(fn ($contrato) => ['establecimiento_id' => $contrato->establecimiento_id, 'cantidad_horas' => $contrato->cantidad_horas])->values();
    if ($vinculaciones->isEmpty()) { $vinculaciones = collect([['establecimiento_id' => '', 'cantidad_horas' => '']]); }
    $erroresContrato = collect($errors->keys())->contains(fn ($key) => in_array($key, ['tipo_contrato','fecha_termino','vinculaciones'], true) || str_starts_with($key, 'vinculaciones.'));
    $idDesactivar = $campo('_ficha_contrato_id');
    $modalError = $canManageContratoLaboral && $erroresContrato ? 'contratoLaboralModal'
        : ($canManageContratoLaboral && $errors->has('motivo_desactivacion') && $contratosActivos->contains(fn ($c) => (string) $c->id === $idDesactivar) ? 'desactivarContratoLaboralModal'.$idDesactivar : '');
    $puedeVerDocumentos = $user && \Illuminate\Support\Facades\Route::has('reemplazos.documents.forUser') && (auth()->user()?->can('viewAny', \App\Models\UserDocument::class) ?? false);
@endphp
<div class="slep-listing slep-profile" data-slep-listing data-ficha data-ficha-error-modal="{{ $modalError }}">
    <header class="slep-listing__header">
        <div class="slep-listing__heading">
            <span class="slep-listing__icon"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
            <div>
                <p class="slep-listing__eyebrow">Reemplazos · Antecedentes de la persona</p>
                <h1>Ficha del usuario</h1>
                <p class="slep-profile__identity">{{ $user?->nombre_completo ?: 'Nombre no informado' }}</p>
                <p class="slep-listing__muted mb-0">RUT {{ $user?->rut ?: 'No informado' }}</p>
            </div>
        </div>
        <div class="slep-listing__actions">
            <a href="{{ route('reemplazos.buscador-postulantes.index') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al buscador</a>
            <a href="{{ route('reemplazos.buscador-postulantes.perfil.view', $profile) }}" class="slep-listing__button slep-listing__button--secondary" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Ver perfil PDF<span class="visually-hidden"> en una pestaña nueva</span></a>
            <a href="{{ route('reemplazos.buscador-postulantes.perfil.pdf', $profile) }}" class="slep-listing__button slep-listing__button--primary"><i class="bi bi-download" aria-hidden="true"></i> Descargar perfil</a>
        </div>
    </header>
    @if (session('success'))
        <div class="slep-listing__notice slep-profile__notice--success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i> {{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="slep-listing__notice slep-listing__notice--danger" role="alert" tabindex="-1" data-slep-errors><strong>Revisa los datos ingresados</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <nav class="slep-profile__nav" aria-label="Secciones de la ficha">
        <a href="#ficha-datos"><i class="bi bi-person" aria-hidden="true"></i> Datos principales</a>
        <a href="#ficha-laboral"><i class="bi bi-briefcase" aria-hidden="true"></i> Estado laboral</a>
        <a href="#ficha-restricciones"><i class="bi bi-shield-check" aria-hidden="true"></i> Restricciones</a>
        <a href="#ficha-solicitudes"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Solicitudes</a>
    </nav>
    <div class="slep-profile__grid">
        <div>
            <section class="slep-listing__panel" id="ficha-datos" aria-labelledby="ficha-datos-titulo">
                <div class="slep-listing__panel-header"><h2 id="ficha-datos-titulo"><i class="bi bi-person-badge" aria-hidden="true"></i> Datos principales</h2></div>
                <div class="slep-profile__body">
                    <dl class="slep-profile__data">
                        @foreach ([
                            'Nombre completo' => $user?->nombre_completo,
                            'RUT' => $user?->rut,
                            'Correo de contacto' => $profile->email_contacto ?: $user?->email,
                            'Teléfonos' => collect([$profile->telefono1, $profile->telefono2])->filter()->implode(' · '),
                            'Comunas de desempeño' => $user?->communes?->pluck('name')->implode(', '),
                            'Área de desempeño' => $profile->areaDesempeno?->nombre ?: $profile->area_desempeno_nombre,
                            'Estamento' => $profile->estamento,
                            'Experiencia' => $profile->anios_experiencia !== null ? $profile->anios_experiencia.' años' : null,
                        ] as $etiqueta => $valor)
                            <div><dt>{{ $etiqueta }}</dt><dd>{{ $valor ?: 'No informado' }}</dd></div>
                        @endforeach
                        <div class="slep-profile__data-wide"><dt>Dirección y comuna de residencia</dt><dd>{{ collect([$profile->direccion, $profile->comuna?->name])->filter()->implode(' · ') ?: 'No informada' }}</dd></div>
                        @if ($profile->especialidad_tp)<div><dt>Especialidad TP</dt><dd>{{ $profile->especialidad_tp }}</dd></div>@endif
                        @if ($profile->mencion)<div><dt>Mención</dt><dd>{{ $profile->mencion }}</dd></div>@endif
                    </dl>
                </div>
            </section>
            <section class="slep-listing__panel" id="ficha-restricciones" aria-labelledby="ficha-restricciones-titulo">
                <div class="slep-listing__panel-header"><h2 id="ficha-restricciones-titulo"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Restricciones para ejercer</h2></div>
                <div class="slep-profile__body">
                    @if (! $hasCourtRestriction && ! $hasManualRestriction)
                        <div class="slep-listing__notice slep-profile__notice--success mb-0"><strong>No registra restricciones vigentes</strong><p class="mb-0 mt-1">No se registran restricciones judiciales ni manuales vigentes para esta persona.</p></div>
                    @else
                        @if ($hasCourtRestriction)
                            <section class="slep-listing__notice slep-listing__notice--danger" aria-label="Bloqueo judicial vigente"><h3>Bloqueo judicial vigente</h3>
                                <dl class="slep-profile__data">
                                    @foreach (['Nombre en nómina'=>'court_name','RUN nómina'=>'court_run','Juzgado de origen'=>'court_juzgado_origen','RIT'=>'court_rit','Fecha del fallo'=>'court_fecha_fallo','Inhabilidad'=>'court_inhabilidad'] as $label => $key)
                                        <div><dt>{{ $label }}</dt><dd>{{ ($restrictionContext[$key] ?? null) ?: 'No informado' }}</dd></div>
                                    @endforeach
                                </dl>
                            </section>
                        @endif
                        @if ($hasManualRestriction)
                            <section class="slep-listing__notice slep-profile__notice--warning mb-0" aria-label="Bloqueo manual vigente"><h3>Bloqueo manual vigente</h3>
                                <dl class="slep-profile__data">
                                    @foreach (['Fecha de inicio'=>'manual_start','Fecha de término'=>'manual_end','Motivo o comentario'=>'manual_comment'] as $label => $key)
                                        <div @class(['slep-profile__data-wide' => $key === 'manual_comment'])><dt>{{ $label }}</dt><dd>{{ ($restrictionContext[$key] ?? null) ?: 'No informado' }}</dd></div>
                                    @endforeach
                                </dl>
                            </section>
                        @endif
                    @endif
                </div>
            </section>
            @if ($puedeVerDocumentos)
                <section class="slep-listing__panel" aria-labelledby="ficha-documentos-titulo">
                    <div class="slep-listing__panel-header"><h2 id="ficha-documentos-titulo"><i class="bi bi-folder2-open" aria-hidden="true"></i> Documentos del usuario</h2></div>
                    <div class="slep-profile__body"><p class="slep-listing__muted">Consulta los antecedentes y archivos cargados por esta persona.</p><a href="{{ route('reemplazos.documents.forUser', ['user' => $user, 'return_to' => url()->full()]) }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-folder2" aria-hidden="true"></i> Ver documentos del usuario</a></div>
                </section>
            @endif
        </div>
        <div>
            <section class="slep-listing__panel" id="ficha-laboral" aria-labelledby="ficha-laboral-titulo">
                <div class="slep-listing__panel-header"><h2 id="ficha-laboral-titulo"><i class="bi bi-briefcase" aria-hidden="true"></i> Estado laboral</h2></div>
                <div class="slep-profile__body">
                    @include('partials.ui.estado-laboral', ['contratos' => $contratosActivos, 'reemplazos' => $reemplazosActivos ?? collect(), 'externo' => $user?->trabaja_en_otro_lugar ?? false, 'observacion' => $user?->trabaja_en_otro_lugar_observacion, 'mostrarAuditoria' => true, 'gestionarContratos' => $canManageContratoLaboral, 'usuario' => $user])
                    @if (! $user?->trabaja_en_otro_lugar)<p class="slep-listing__muted small mt-3 mb-0">Trabajo externo: sin información marcada.</p>@endif
                    @if ($canManageContratoLaboral)
                        <button type="button" class="slep-listing__button slep-listing__button--secondary mt-3" data-bs-toggle="modal" data-bs-target="#contratoLaboralModal"><i class="bi bi-pencil-square" aria-hidden="true"></i> {{ $contratosActivos->isNotEmpty() ? 'Actualizar vinculación' : 'Registrar vinculación' }}</button>
                    @endif
                    @if ($contratosActivos->isEmpty() && $ultimaVinculacionLaboral)
                        <section class="slep-profile__history mt-3" aria-label="Última vinculación registrada">
                            <h3>Última vinculación registrada</h3><p>{{ $ultimaVinculacionLaboral->resumen }}</p>
                            @if ($ultimaVinculacionLaboral->fecha_termino && $ultimaVinculacionLaboral->fecha_termino->toDateString() < now()->toDateString())<p class="slep-listing__muted">La fecha de término registrada ya venció.</p>@endif
                            @if (! $ultimaVinculacionLaboral->activo && $ultimaVinculacionLaboral->motivo_desactivacion)
                                <p><strong>Motivo de desactivación:</strong> {{ $ultimaVinculacionLaboral->motivo_desactivacion }}</p>
                                @if ($ultimaVinculacionLaboral->desactivado_at)<p class="slep-listing__muted mb-0">Desactivada el {{ $ultimaVinculacionLaboral->desactivado_at->format('d-m-Y H:i') }} @if ($ultimaVinculacionLaboral->desactivadoPor)por {{ $ultimaVinculacionLaboral->desactivadoPor->nombre_completo }}@endif</p>@endif
                            @endif
                        </section>
                    @endif
                </div>
            </section>
            @if ($canManageTrabajoExterno)
                <section class="slep-listing__panel" aria-labelledby="ficha-externo-titulo">
                    <div class="slep-listing__panel-header"><h2 id="ficha-externo-titulo"><i class="bi bi-telephone-outbound" aria-hidden="true"></i> Actualizar trabajo externo informado</h2></div>
                    <form method="POST" action="{{ route('reemplazos.buscador-postulantes.trabajo-externo.update', $profile) }}" class="slep-profile__body" data-ficha-form="externo" novalidate>
                        @csrf
                        <input type="hidden" name="trabaja_en_otro_lugar" value="0">
                        <div class="slep-profile__switch">
                            <input type="checkbox" role="switch" id="trabajaEnOtroLugar" name="trabaja_en_otro_lugar" value="1" @checked($campo('trabaja_en_otro_lugar', $user?->trabaja_en_otro_lugar ? 1 : 0) === '1')>
                            <label for="trabajaEnOtroLugar">Informó que actualmente trabaja en otro lugar</label>
                        </div>
                        <p class="form-text mb-3">Información recibida por contacto con la persona. Puede registrarse sin indicar institución.</p>
                        <label for="trabajaEnOtroLugarObservacion" class="form-label">Observación</label>
                        <textarea id="trabajaEnOtroLugarObservacion" name="trabaja_en_otro_lugar_observacion" class="form-control @error('trabaja_en_otro_lugar_observacion') is-invalid @enderror" rows="3" maxlength="1000" data-ficha-text aria-describedby="trabajaEnOtroLugarObservacion-error">{{ $campo('trabaja_en_otro_lugar_observacion', $user?->trabaja_en_otro_lugar_observacion) }}</textarea>
                        <div id="trabajaEnOtroLugarObservacion-error" class="invalid-feedback">@error('trabaja_en_otro_lugar_observacion'){{ $message }}@enderror</div>
                        <p class="form-text">Al desmarcar, la observación se limpia al guardar.</p>
                        <button type="submit" class="slep-listing__button slep-listing__button--primary mt-3"><i class="bi bi-check2" aria-hidden="true"></i> Guardar situación informada</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
    <section class="slep-listing__panel" id="ficha-solicitudes" aria-labelledby="ficha-solicitudes-titulo">
        <div class="slep-listing__panel-header"><div><p class="slep-listing__eyebrow">Historial reciente</p><h2 id="ficha-solicitudes-titulo"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Solicitudes asociadas</h2></div><span class="slep-listing__chip">{{ ($solicitudes ?? collect())->count() }} {{ ($solicitudes ?? collect())->count() === 1 ? 'solicitud' : 'solicitudes' }}</span></div>
        @if (($solicitudes ?? collect())->isEmpty())
            <div class="slep-profile__body slep-profile__empty"><i class="bi bi-clipboard" aria-hidden="true"></i><p class="mb-0">Este usuario no registra solicitudes asociadas como persona propuesta.</p></div>
        @else
            <div class="slep-listing__table-wrap">
                <table class="slep-listing__table slep-profile__requests" role="table">
                    <caption class="visually-hidden">Últimas solicitudes en que esta persona fue propuesta.</caption>
                    <thead role="rowgroup"><tr role="row"><th scope="col">Solicitud</th><th scope="col">Establecimiento</th><th scope="col">Área de desempeño</th><th scope="col">Estado</th></tr></thead>
                    <tbody role="rowgroup">
                        @foreach ($solicitudes as $solicitud)
                            <tr role="row">
                                <td data-label="Solicitud" role="cell">
                                    @if ((auth()->user()?->canModule('gestion.solicitudes-reemplazo') ?? false) && \Illuminate\Support\Facades\Route::has('gestion.solicitudes-reemplazo.show'))
                                        <a class="slep-profile__request-link" href="{{ route('gestion.solicitudes-reemplazo.show', ['solicitud' => $solicitud, 'return_to' => url()->full()]) }}">{{ $solicitud->numero_solicitud ?: '#'.$solicitud->id }}</a>
                                    @else
                                        {{ $solicitud->numero_solicitud ?: '#'.$solicitud->id }}
                                    @endif
                                </td>
                                <td data-label="Establecimiento" role="cell">{{ $solicitud->establecimiento?->nombre_establecimiento ?: 'No informado' }}</td>
                                <td data-label="Área de desempeño" role="cell">{{ $solicitud->areaDesempeno?->nombre ?: 'No informada' }}</td>
                                <td data-label="Estado" role="cell"><span class="slep-status-pill slep-status-pill--neutral">{{ \Illuminate\Support\Str::headline($solicitud->estado ?: 'Sin estado informado') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="slep-profile__body slep-listing__muted small mb-0">Se muestran hasta 10 solicitudes asociadas, ordenadas desde la más reciente.</p>
        @endif
    </section>

    @if ($canManageContratoLaboral)
        <div class="modal slep-profile__modal" id="contratoLaboralModal" tabindex="-1" aria-labelledby="contratoLaboralModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" action="{{ route('reemplazos.buscador-postulantes.contrato.store', $profile) }}" data-ficha-form="contrato" novalidate>
                        @csrf
                        <div class="modal-header"><h2 id="contratoLaboralModalLabel" class="modal-title">Registrar vinculación laboral activa</h2><button type="button" class="slep-profile__close" data-bs-dismiss="modal" aria-label="Cerrar registro de vinculación"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                        <div class="modal-body">
                            <div class="slep-listing__notice slep-profile__notice--info">Indica el tipo de contrato y hasta 10 establecimientos, con las horas de cada uno. Los campos con * son obligatorios.</div>
                            @if ($erroresContrato)<div class="slep-listing__notice slep-listing__notice--danger" role="alert">Revisa la vinculación ingresada.<ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label for="tipoContratoLaboral" class="form-label">Tipo de contrato *</label>
                                    <select name="tipo_contrato" id="tipoContratoLaboral" class="form-select @error('tipo_contrato') is-invalid @enderror" data-slep-select="compact" data-ficha-tipo required aria-describedby="tipoContratoLaboral-error">
                                        @foreach (['Contrata','Plazo Fijo','Honorarios','Indefinido'] as $tipo)<option value="{{ $tipo }}" @selected($campo('tipo_contrato', $contratoActivo?->tipo_contrato ?: 'Contrata') === $tipo)>{{ $tipo }}</option>@endforeach
                                    </select>
                                    <div id="tipoContratoLaboral-error" class="invalid-feedback">@error('tipo_contrato'){{ $message }}@enderror</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="fechaTerminoLaboral" class="form-label">Fecha de término *</label>
                                    <input id="fechaTerminoLaboral" name="fecha_termino" type="text" class="form-control @error('fecha_termino') is-invalid @enderror" value="{{ $campo('fecha_termino', $contratoActivo?->fecha_termino?->format('Y-m-d')) }}" placeholder="AAAA-MM-DD" data-ficha-date required autocomplete="off" aria-describedby="fechaTerminoLaboral-ayuda fechaTerminoLaboral-error">
                                    <p class="form-text" id="fechaTerminoLaboral-ayuda" data-ficha-date-help>Formato AAAA-MM-DD. No se solicita para Indefinido.</p>
                                    <div id="fechaTerminoLaboral-error" class="invalid-feedback">@error('fecha_termino'){{ $message }}@enderror</div>
                                </div>
                            </div>
                            <div class="slep-profile__row-heading"><h3>Establecimientos y horas</h3><button type="button" class="slep-listing__button slep-listing__button--secondary slep-listing__button--compact" data-ficha-add><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar establecimiento</button></div>
                            <div class="slep-profile__contract-rows" data-ficha-rows>
                                @foreach ($vinculaciones as $idx => $vinculacion)
                                    @include('reemplazos.buscador-postulantes.partials.vinculacion-form-row')
                                @endforeach
                            </div>
                            <p class="form-text" data-ficha-row-status aria-live="polite"></p>
                            <div class="invalid-feedback" data-ficha-rows-error></div>
                            <template data-ficha-template>@include('reemplazos.buscador-postulantes.partials.vinculacion-form-row', ['idx' => '__INDEX__', 'vinculacion' => []])</template>
                            <p class="form-text mt-3">Al guardar, las vinculaciones activas anteriores se cierran y se reemplazan por este conjunto.</p>
                        </div>
                        <div class="modal-footer"><button type="button" class="slep-listing__button slep-listing__button--secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="slep-listing__button slep-listing__button--primary"><i class="bi bi-check2" aria-hidden="true"></i> Guardar vinculación</button></div>
                    </form>
                </div>
            </div>
        </div>
        @foreach ($contratosActivos as $contrato)
            <div class="modal slep-profile__modal" id="desactivarContratoLaboralModal{{ $contrato->id }}" tabindex="-1" aria-labelledby="desactivarContratoLaboralModalLabel{{ $contrato->id }}" aria-hidden="true">
                <div class="modal-dialog"><div class="modal-content">
                    <form method="POST" action="{{ route('reemplazos.buscador-postulantes.contrato.destroy', [$profile, $contrato]) }}" data-ficha-form="desactivar" novalidate>
                        @csrf @method('DELETE')
                        <input type="hidden" name="_ficha_contrato_id" value="{{ $contrato->id }}">
                        <div class="modal-header"><h2 id="desactivarContratoLaboralModalLabel{{ $contrato->id }}" class="modal-title">Finalizar vinculación laboral</h2><button type="button" class="slep-profile__close" data-bs-dismiss="modal" aria-label="Cerrar finalización de vínculo"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
                        <div class="modal-body">
                            <div class="slep-listing__notice slep-profile__notice--warning">Se marcará como no vigente el vínculo con <strong>{{ $contrato->establecimiento?->nombre_establecimiento ?: 'el establecimiento seleccionado' }}</strong>. El registro se conserva en el historial.</div>
                            <label for="motivo-desactivar-{{ $contrato->id }}" class="form-label">Motivo de desactivación *</label>
                            <textarea id="motivo-desactivar-{{ $contrato->id }}" name="motivo_desactivacion" rows="4" minlength="5" maxlength="500" class="form-control" data-ficha-reason required aria-describedby="motivo-desactivar-{{ $contrato->id }}-error">{{ $idDesactivar === (string) $contrato->id ? $campo('motivo_desactivacion') : '' }}</textarea>
                            <p class="form-text">Entre 5 y 500 caracteres. Por ejemplo, término anticipado, renuncia o corrección de un registro.</p>
                            <div id="motivo-desactivar-{{ $contrato->id }}-error" class="invalid-feedback">@if ($idDesactivar === (string) $contrato->id)@error('motivo_desactivacion'){{ $message }}@enderror @endif</div>
                        </div>
                        <div class="modal-footer"><button type="button" class="slep-listing__button slep-listing__button--secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="slep-listing__button slep-profile__button-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Finalizar vínculo</button></div>
                    </form>
                </div></div>
            </div>
        @endforeach
    @endif
</div>
@endsection
