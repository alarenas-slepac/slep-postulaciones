@extends('layouts.app')
@include('tramites.maternidad.partials._assets')
@section('content')
@php
    $datos = $caso?->toArray() ?? $seleccionado ?? [];
    $documentosActuales = collect($caso?->documentos ?? [])->unique('tipo')->keyBy('tipo');
    $hijos = old('hijos', $caso?->hijos?->toArray() ?? [[]]);
    $flag = fn ($campo) => (bool) old($campo, data_get($datos, $campo, false));
    $puedeAmpliar = app(\App\Services\Maternidad\MaternidadRegistroService::class)->puedeAmpliarDominga();
    $reglaDominga = old('regla_fuero_dominga', $caso?->regla_fuero_dominga ?? config('maternidad.regla_fuero_dominga'));
@endphp
<div class="slep-listing mat-workspace" data-slep-listing>
    <header class="slep-listing__header"><div class="slep-listing__heading"><span class="slep-listing__icon"><i class="bi bi-person-heart" aria-hidden="true"></i></span><div><p class="slep-listing__eyebrow">Maternidad · {{ $caso?->codigo ?? 'Ingreso individual' }}</p><h1>{{ $caso ? 'Editar caso' : 'Registrar caso' }}</h1><p class="slep-listing__muted mb-0">Datos laborales, antecedentes del embarazo, beneficios y documentos de respaldo.</p></div></div><a class="slep-listing__button slep-listing__button--secondary" href="{{ $caso ? route('tramites.maternidad.show', $caso) : route('tramites.maternidad.index') }}">Volver</a></header>
    @include('tramites.maternidad.partials._mensajes')
    @if (! $caso)
        <section class="slep-listing__panel" aria-labelledby="mat-buscar-titulo"><div class="slep-listing__panel-header"><h2 id="mat-buscar-titulo">1. Buscar funcionaria por RUT</h2></div>
            <form method="GET" action="{{ route('tramites.maternidad.create') }}" class="slep-listing__filters" id="mat-busqueda" data-url="{{ route('tramites.maternidad.buscar') }}" data-candidatos="{{ json_encode($candidatos) }}">
                <div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label" for="mat-buscar-rut">RUT con dígito verificador</label><input class="form-control" id="mat-buscar-rut" name="rut" value="{{ old('rut', request('rut')) }}" maxlength="32" required placeholder="12.345.678-5" autocomplete="off"></div>
                    <div class="col-md-6"><label class="form-label" for="mat-fuente">Vínculo laboral encontrado</label><select class="form-select" id="mat-fuente" name="fuente" data-slep-select="search"><option value="">Seleccione un vínculo</option>@foreach ($candidatos as $candidato)<option value="{{ $candidato['fuente'] }}" @selected(($seleccionado['fuente'] ?? '') === $candidato['fuente'])>{{ $candidato['descripcion'] }}</option>@endforeach</select></div>
                    <div class="col-md-2"><button class="slep-listing__button slep-listing__button--primary w-100" type="submit">Buscar / usar</button></div></div>
                <p class="form-text mt-3" role="status" id="mat-busqueda-estado">{{ request('rut') && ! $candidatos ? 'No se encontraron vínculos. Revise el RUT o los antecedentes en padrón y reemplazos.' : 'Busca en el padrón y en el RUT de la persona reemplazante de las solicitudes de reemplazo.' }}</p>
                <noscript><p class="form-text">Busque el RUT, seleccione un vínculo y pulse de nuevo «Buscar / usar» para completar los datos.</p></noscript>
            </form>
        </section>
    @endif
    @if ($caso?->origen_registro === 'masivo' && $caso->documentos_pendientes)
        <div class="slep-record-state slep-record-state--warning mb-4">Caso ingresado por Excel. Puede completar los respaldos gradualmente; los beneficios sin certificado permanecen pendientes de acreditación.</div>
    @endif
    <form method="POST" enctype="multipart/form-data" novalidate action="{{ $caso ? route('tramites.maternidad.update', $caso) : route('tramites.maternidad.store') }}" data-mat-form
          data-documentos="{{ $documentosActuales->keys()->toJson() }}" data-regla-dominga="{{ $reglaDominga }}">
        @csrf
        @if ($caso) @method('PUT') <input type="hidden" name="version" value="{{ old('version', $caso->version) }}"> @endif
        <input type="hidden" name="fuente" value="{{ old('fuente', $seleccionado['fuente'] ?? '') }}">
        <section class="slep-listing__panel" aria-labelledby="mat-identidad"><div class="slep-listing__panel-header"><h2 id="mat-identidad">{{ $caso ? 'Datos de la funcionaria' : '2. Datos de la funcionaria' }}</h2></div><div class="slep-listing__filters">
            <div class="row g-3">
                @include('tramites.maternidad.partials._campo', ['name' => 'rut', 'label' => 'RUT de la funcionaria', 'obligatorio' => true, 'soloLectura' => true, 'maximo' => 32])
                @include('tramites.maternidad.partials._campo', ['name' => 'referencia_caso', 'label' => 'Referencia del caso', 'soloLectura' => (bool) $caso, 'maximo' => 100, 'ayuda' => 'Identifique el embarazo o proceso. Si se deja vacía al crear, se asigna una referencia automática.'])
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_registro', 'label' => 'Fecha de registro', 'tipo' => 'date', 'obligatorio' => true, 'defecto' => today()->toDateString()])
                @include('tramites.maternidad.partials._campo', ['name' => 'nombres', 'label' => 'Nombres', 'obligatorio' => true, 'maximo' => 150])
                @include('tramites.maternidad.partials._campo', ['name' => 'apellido_paterno', 'label' => 'Apellido paterno', 'obligatorio' => true, 'maximo' => 100])
                @include('tramites.maternidad.partials._campo', ['name' => 'apellido_materno', 'label' => 'Apellido materno', 'maximo' => 100])
                <div class="col-12"><p class="slep-record-state slep-record-state--info mb-0" data-mat-nombre-aviso @if (! ($seleccionado['nombre_requiere_revision'] ?? false)) hidden @endif>El nombre se separó automáticamente desde el padrón. Revise nombres y apellidos compuestos antes de guardar.</p></div>
                <div class="col-md-6"><label class="form-label" for="mat-estamento">Estamento <span class="text-danger">*</span></label><select class="form-select @error('estamento') is-invalid @enderror" id="mat-estamento" name="estamento" data-slep-select="compact" required aria-describedby="mat-estamento-error"><option value="">Seleccione estamento</option>@foreach (config('maternidad.estamentos') as $key => $nombre)<option value="{{ $key }}" @selected(old('estamento', $datos['estamento'] ?? '') === $key)>{{ $nombre }}</option>@endforeach</select><div class="invalid-feedback" id="mat-estamento-error">@error('estamento'){{ $message }}@enderror</div></div>
                @include('tramites.maternidad.partials._campo', ['name' => 'calidad_juridica', 'label' => 'Calidad jurídica / tipo de contrato', 'obligatorio' => true, 'col' => 'col-md-6', 'maximo' => 120])
                <div class="col-md-8"><label class="form-label" for="mat-establecimiento">Establecimiento del vínculo seleccionado</label><input class="form-control" id="mat-establecimiento" value="{{ $datos['establecimiento_nombre'] ?? '' }}" readonly></div>
                <div class="col-md-4"><label class="form-label" for="mat-comuna">Comuna del establecimiento</label><input class="form-control" id="mat-comuna" value="{{ $datos['comuna'] ?? '' }}" readonly></div>
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_ingreso', 'label' => 'Fecha de ingreso', 'tipo' => 'date', 'col' => 'col-md-6'])
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_termino', 'label' => 'Fecha de término del contrato', 'tipo' => 'date', 'col' => 'col-md-6', 'ayuda' => 'Deje vacío si no tiene término informado.'])
            </div>
        </div></section>
        <section class="slep-listing__panel" aria-labelledby="mat-embarazo"><div class="slep-listing__panel-header"><h2 id="mat-embarazo">Embarazo y nacimiento</h2></div><div class="slep-listing__filters"><div class="row g-3">
            @include('tramites.maternidad.partials._campo', ['name' => 'fecha_certificado_embarazo', 'label' => 'Fecha del certificado de embarazo', 'tipo' => 'date'])
            @include('tramites.maternidad.partials._campo', ['name' => 'fpc', 'label' => 'Fecha posible de concepción (FPC)', 'tipo' => 'date', 'ayuda' => 'Registre la fecha informada en el antecedente; no se deduce automáticamente.'])
            @include('tramites.maternidad.partials._campo', ['name' => 'fpp', 'label' => 'Fecha probable de parto (FPP)', 'tipo' => 'date'])
            @include('tramites.maternidad.partials._archivo', ['tipoDocumento' => 'embarazo'])
            @include('tramites.maternidad.partials._archivo', ['tipoDocumento' => 'orden_trabajo'])
            @include('tramites.maternidad.partials._campo', ['name' => 'fnh', 'label' => 'Fecha de nacimiento del hijo/a (FNH)', 'tipo' => 'date', 'col' => 'col-md-6', 'ayuda' => 'Para usar esta fecha como base definitiva debe existir certificado de nacimiento.'])
            @include('tramites.maternidad.partials._archivo', ['tipoDocumento' => 'nacimiento'])
        </div></div></section>
        <section class="slep-listing__panel" aria-labelledby="mat-dominga"><div class="slep-listing__panel-header"><h2 id="mat-dominga">Ley Dominga</h2><span class="slep-listing__chip">7 días hábiles de permiso informado</span></div><div class="slep-listing__filters">
            <input type="hidden" name="ley_dominga" value="0"><div class="form-check form-switch mb-3"><input class="form-check-input" id="mat-ley-dominga" name="ley_dominga" type="checkbox" value="1" @checked($flag('ley_dominga')) data-mat-flag="ley_dominga" role="switch"><label class="form-check-label fw-semibold" for="mat-ley-dominga">Sí, registrar Ley Dominga</label></div>
            <div class="row g-3" data-mat-condicional="ley_dominga">
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_ley_dominga', 'label' => 'Fecha Ley Dominga', 'tipo' => 'date', 'col' => 'col-md-6'])
                @include('tramites.maternidad.partials._archivo', ['tipoDocumento' => 'ley_dominga'])
                @if ($puedeAmpliar)
                    <div class="col-md-6"><label class="form-label" for="mat-regla-dominga">Seguimiento Ley Dominga · decisión GDP</label><select class="form-select @error('regla_fuero_dominga') is-invalid @enderror" name="regla_fuero_dominga" id="mat-regla-dominga" data-slep-select="compact"><option value="30_dias" @selected($reglaDominga === '30_dias')>30 días</option><option value="anio_84_dias" @selected($reglaDominga === 'anio_84_dias')>Ampliar a un año y 84 días</option></select><div class="invalid-feedback">@error('regla_fuero_dominga'){{ $message }}@enderror</div></div>
                    <div class="col-md-6"><label class="form-label" for="mat-causal-dominga">Causal de la ampliación</label><select class="form-select @error('causal_ampliacion_dominga') is-invalid @enderror" name="causal_ampliacion_dominga" id="mat-causal-dominga" data-slep-select="compact"><option value="">Sin ampliación</option>@foreach (config('maternidad.causales_ampliacion_dominga') as $key => $label)<option value="{{ $key }}" @selected(old('causal_ampliacion_dominga', $caso?->causal_ampliacion_dominga) === $key)>{{ $label }}</option>@endforeach</select><div class="invalid-feedback">@error('causal_ampliacion_dominga'){{ $message }}@enderror</div></div>
                    @include('tramites.maternidad.partials._campo', ['name' => 'motivo_ampliacion_dominga', 'label' => 'Fundamento de GDP para la ampliación', 'col' => 'col-12', 'maximo' => 2000, 'ayuda' => 'Obligatorio al ampliar. La decisión, su causal y el responsable quedan en el historial.'])
                @else
                    <p class="small slep-listing__muted mb-0">Seguimiento registrado: {{ $reglaDominga === 'anio_84_dias' ? 'un año y 84 días, ampliado por GDP' : '30 días' }} desde la fecha Ley Dominga acreditada. GDP o Administración pueden modificar la ampliación.</p>
                @endif
            </div>
        </div></section>
        <section class="slep-listing__panel" aria-labelledby="mat-lactancia"><div class="slep-listing__panel-header"><h2 id="mat-lactancia">Beneficio de horas de lactancia</h2></div><div class="slep-listing__filters">
            <input type="hidden" name="lactancia" value="0"><div class="form-check form-switch mb-3"><input class="form-check-input" id="mat-lactancia-flag" name="lactancia" type="checkbox" value="1" @checked($flag('lactancia')) data-mat-flag="lactancia" role="switch"><label class="form-check-label fw-semibold" for="mat-lactancia-flag">Sí, registrar beneficio de lactancia</label></div>
            <div class="row g-3" data-mat-condicional="lactancia">
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_inicio_lactancia', 'label' => 'Fecha de inicio del beneficio', 'tipo' => 'date', 'col' => 'col-md-6'])
                @include('tramites.maternidad.partials._archivo', ['tipoDocumento' => 'lactancia'])
                <p class="form-text mb-0">El término se calcula a los dos años del nacimiento, con solicitud del beneficio y nacimiento acreditados.</p>
            </div>
        </div></section>
        <section class="slep-listing__panel" aria-labelledby="mat-postnatal"><div class="slep-listing__panel-header"><h2 id="mat-postnatal">Permiso postnatal parental</h2></div><div class="slep-listing__filters">
            <input type="hidden" name="postnatal_parental" value="0"><div class="form-check form-switch mb-3"><input class="form-check-input" id="mat-postnatal-flag" name="postnatal_parental" type="checkbox" value="1" @checked($flag('postnatal_parental')) data-mat-flag="postnatal_parental" role="switch"><label class="form-check-label fw-semibold" for="mat-postnatal-flag">Registrar antecedentes del permiso postnatal parental</label></div>
            <p class="form-text mb-3">El permiso permanece sin acreditar hasta cargar el documento. Al adjuntarlo se activa su registro.</p>
            <div class="row g-3" data-mat-condicional="postnatal_parental">
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_inicio_postnatal', 'label' => 'Inicio del permiso postnatal parental', 'tipo' => 'date'])
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_termino_postnatal', 'label' => 'Término del permiso postnatal parental', 'tipo' => 'date'])
                <div class="col-md-6 col-xl-4"><label class="form-label" for="mat-modalidad-postnatal">Modalidad del permiso</label><select class="form-select @error('modalidad_postnatal') is-invalid @enderror" id="mat-modalidad-postnatal" name="modalidad_postnatal" data-slep-select="compact"><option value="">Seleccione modalidad</option>@foreach (config('maternidad.modalidades') as $key => $label)<option value="{{ $key }}" @selected(old('modalidad_postnatal', $datos['modalidad_postnatal'] ?? '') === $key)>{{ $label }}</option>@endforeach</select><div class="invalid-feedback">@error('modalidad_postnatal'){{ $message }}@enderror</div></div>
                @include('tramites.maternidad.partials._archivo', ['tipoDocumento' => 'postnatal_parental'])
                @include('tramites.maternidad.partials._archivo', ['tipoDocumento' => 'rex_postnatal'])
                @include('tramites.maternidad.partials._campo', ['name' => 'fecha_rex_postnatal', 'label' => 'Fecha REX de permiso postnatal parental', 'tipo' => 'date'])
            </div>
        </div></section>
        <section class="slep-listing__panel" aria-labelledby="mat-hijos"><div class="slep-listing__panel-header"><div><h2 id="mat-hijos">Datos del hijo/a</h2><p class="small slep-listing__muted mb-0">Puede agregar más de un hijo/a al mismo caso.</p></div><button class="slep-listing__button slep-listing__button--secondary" type="button" data-mat-agregar-hijo>Agregar hijo/a</button></div><div class="slep-listing__filters">
            <div data-mat-hijos>@foreach ($hijos as $indice => $hijo) @include('tramites.maternidad.partials._hijo', ['indice' => $indice, 'datos' => ['hijos' => $hijos]]) @endforeach</div>
            <template data-mat-hijo-template>@include('tramites.maternidad.partials._hijo', ['indice' => '__INDEX__', 'datos' => []])</template>
            <p class="form-text mb-0" role="status" data-mat-hijos-estado>Revise nombres, fechas y RUT de los hijos informados. Máximo 10 por caso.</p>
        </div></section>
        <section class="slep-listing__panel" aria-labelledby="mat-fechas-calculadas"><div class="slep-listing__panel-header"><h2 id="mat-fechas-calculadas">Fechas de seguimiento calculadas</h2></div><div class="slep-listing__filters">
            <dl class="mat-datos"><div><dt>Término estimado por FPP</dt><dd data-mat-fecha="estimado">{{ $caso?->fuero_estimado?->format('d-m-Y') ?? 'Se calcula al guardar' }}</dd></div><div><dt>Término por nacimiento certificado</dt><dd data-mat-fecha="nacimiento">{{ $caso?->fuero_nacimiento?->format('d-m-Y') ?? 'Pendiente de fecha y certificado' }}</dd></div><div><dt>Término por Ley Dominga</dt><dd data-mat-fecha="dominga">{{ $caso?->fuero_dominga?->format('d-m-Y') ?? 'No acreditado' }}</dd></div><div><dt>Término del beneficio de lactancia</dt><dd data-mat-fecha="lactancia">{{ $caso?->fecha_termino_lactancia?->format('d-m-Y') ?? 'No acreditado' }}</dd></div></dl>
            <p class="form-text mb-0">Vista previa informativa. El servidor calcula las fechas al guardar: FPP + un año y 84 días; con nacimiento certificado usa FNH + un año y 84 días. Ley Dominga acreditada tiene prioridad.</p>
        </div></section>
        <section class="slep-listing__panel" aria-labelledby="mat-observacion"><div class="slep-listing__panel-header"><h2 id="mat-observacion">Observación para el historial</h2></div><div class="slep-listing__filters"><label class="form-label" for="mat-observacion-texto">Observación de este registro o actualización</label><textarea class="form-control @error('observacion') is-invalid @enderror" id="mat-observacion-texto" name="observacion" rows="3" maxlength="5000">{{ old('observacion') }}</textarea><div class="form-text">Se agrega con fecha y usuario. Las observaciones anteriores se conservan.</div><div class="invalid-feedback">@error('observacion'){{ $message }}@enderror</div></div></section>
        <div class="mat-form-actions"><p class="small slep-listing__muted mb-0" data-mat-save-help>{{ ! $caso && ! old('fuente', $seleccionado['fuente'] ?? null) ? 'Seleccione primero el vínculo laboral de la funcionaria.' : 'Los documentos se guardan de forma privada y los cambios quedan en el historial.' }}</p><div class="slep-listing__actions"><a class="slep-listing__button slep-listing__button--secondary" href="{{ $caso ? route('tramites.maternidad.show', $caso) : route('tramites.maternidad.index') }}">Cancelar</a><button class="slep-listing__button slep-listing__button--primary" type="submit" data-mat-save @disabled(! $caso && ! old('fuente', $seleccionado['fuente'] ?? null))>Guardar caso</button></div></div>
    </form>
</div>
@endsection
